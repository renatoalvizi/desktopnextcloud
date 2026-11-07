<?php

/**
 * Edalvix Cloud desktop client update server.
 *
 * Speaks the protocol of the client's built-in updater (src/gui/updater):
 * the client sends GET ?version=&platform=&channel=&currentArch=&msi=&sparkle=
 * and expects either an <owncloudclient> document (Windows/Linux) or a
 * Sparkle appcast (macOS). An empty <owncloudclient/> means "up to date".
 *
 * SPDX-FileCopyrightText: 2026 EDALVIX
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Edalvix\UpdateServer;

final class UpdateServer {
	private const PLATFORMS = ['win32', 'macos', 'linux'];
	private const DEFAULT_CHANNEL = 'stable';

	/** @param array<string,mixed> $manifest decoded releases.json */
	public function __construct(
		private readonly array $manifest,
	) {
	}

	public static function fromFile(string $path): self {
		$json = @file_get_contents($path);
		if ($json === false) {
			throw new \RuntimeException("Cannot read releases manifest: $path");
		}
		$manifest = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
		if (!is_array($manifest) || !is_array($manifest['channels'] ?? null)) {
			throw new \RuntimeException("Invalid releases manifest (missing 'channels'): $path");
		}
		return new self($manifest);
	}

	/**
	 * Same arithmetic as Updater::Helper::stringVersionToInt() in the client:
	 * "major.minor.patch.build", anything after the numbers is ignored.
	 *
	 * @return int[] four components
	 */
	public static function parseVersion(string $version): array {
		preg_match('/^\s*(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:\.(\d+))?/', $version, $m);
		return [(int)($m[1] ?? 0), (int)($m[2] ?? 0), (int)($m[3] ?? 0), (int)($m[4] ?? 0)];
	}

	public static function isNewer(string $candidate, string $installed): bool {
		return self::parseVersion($candidate) > self::parseVersion($installed);
	}

	/**
	 * Release entry offered to a client, or null when it is up to date.
	 *
	 * @param array<string,string> $query
	 * @return array<string,mixed>|null
	 */
	public function findUpdate(array $query): ?array {
		$platform = (string)($query['platform'] ?? '');
		if (!in_array($platform, self::PLATFORMS, true)) {
			return null;
		}
		$installed = (string)($query['version'] ?? '');
		if ($installed === '') {
			return null;
		}

		$channels = $this->manifest['channels'];
		$channel = (string)($query['channel'] ?? self::DEFAULT_CHANNEL);
		if (!is_array($channels[$channel] ?? null)) {
			$channel = self::DEFAULT_CHANNEL;
		}
		$releases = $channels[$channel] ?? [];
		if (!is_array($releases)) {
			return null;
		}

		// Architecture-specific build first (e.g. "win32-arm64"), then the generic one.
		$arch = strtolower((string)($query['currentArch'] ?? ''));
		$release = null;
		if ($arch !== '' && is_array($releases["$platform-$arch"] ?? null)) {
			$release = $releases["$platform-$arch"];
		} elseif (is_array($releases[$platform] ?? null)) {
			$release = $releases[$platform];
		}
		if ($release === null || empty($release['version'])) {
			return null;
		}

		// Optional floor: clients older than this cannot update in place.
		if (!empty($release['minimumVersion']) && self::isNewer((string)$release['minimumVersion'], $installed)) {
			return null;
		}

		if (!self::isNewer((string)$release['version'], $installed)) {
			return null;
		}

		$release['web'] = $release['web'] ?? ($this->manifest['web'] ?? '');
		return $release;
	}

	/**
	 * @param array<string,string> $query
	 * @return array{contentType:string, body:string}
	 */
	public function respond(array $query): array {
		$release = $this->findUpdate($query);
		$wantsSparkle = ($query['platform'] ?? '') === 'macos' && ($query['sparkle'] ?? '') === 'true';

		if ($wantsSparkle) {
			return ['contentType' => 'application/rss+xml; charset=utf-8', 'body' => $this->sparkleFeed($release)];
		}
		return ['contentType' => 'text/xml; charset=utf-8', 'body' => $this->ownCloudClientXml($release)];
	}

	/** @param array<string,mixed>|null $release */
	private function ownCloudClientXml(?array $release): string {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		if ($release === null) {
			return $xml . "<owncloudclient/>\n";
		}
		$xml .= "<owncloudclient>\n";
		foreach (['version', 'versionstring', 'web', 'downloadurl'] as $tag) {
			$xml .= sprintf("  <%s>%s</%s>\n", $tag, self::esc((string)($release[$tag] ?? '')), $tag);
		}
		return $xml . "</owncloudclient>\n";
	}

	/** @param array<string,mixed>|null $release */
	private function sparkleFeed(?array $release): string {
		$title = self::esc((string)($this->manifest['name'] ?? 'Edalvix Cloud'));
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<rss version="2.0" xmlns:sparkle="http://www.andymatuschak.org/xml-namespaces/sparkle">' . "\n"
			. "  <channel>\n    <title>$title</title>\n";
		$sparkle = $release['sparkle'] ?? null;
		if ($release !== null && is_array($sparkle) && !empty($sparkle['edSignature'])) {
			$xml .= "    <item>\n"
				. '      <title>' . self::esc((string)($release['versionstring'] ?? $release['version'])) . "</title>\n"
				. '      <pubDate>' . self::esc(gmdate(DATE_RSS, strtotime((string)($release['date'] ?? 'now')) ?: time())) . "</pubDate>\n"
				. '      <enclosure url="' . self::esc((string)($sparkle['url'] ?? $release['downloadurl'] ?? '')) . '"'
				. ' sparkle:version="' . self::esc((string)$release['version']) . '"'
				. ' sparkle:shortVersionString="' . self::esc((string)($release['versionstring'] ?? $release['version'])) . '"'
				. ' sparkle:edSignature="' . self::esc((string)$sparkle['edSignature']) . '"'
				. ' length="' . (int)($sparkle['length'] ?? 0) . '"'
				. ' type="application/octet-stream"/>' . "\n"
				. "    </item>\n";
		}
		return $xml . "  </channel>\n</rss>\n";
	}

	private static function esc(string $value): string {
		return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}
}
