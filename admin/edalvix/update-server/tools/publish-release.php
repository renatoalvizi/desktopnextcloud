<?php

/**
 * Add or replace a release in releases.json.
 *
 *   php tools/publish-release.php --platform=win32 --version=35.0.50.1 \
 *       --url=https://updates.edalvix.cloud/download/EdalvixCloud-35.0.50-x64.msi \
 *       [--file=EdalvixCloud-35.0.50-x64.msi] [--channel=stable] [--arch=arm64] \
 *       [--versionstring="Edalvix Cloud 35.0.50"] [--minimum-version=34.0.0] \
 *       [--sparkle-url=...pkg.tbz --sparkle-file=...pkg.tbz --ed-signature=...] \
 *       [--manifest=config/releases.json]
 *
 * --file computes sha256; --sparkle-file computes the enclosure length.
 *
 * SPDX-FileCopyrightText: 2026 EDALVIX
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

$opts = getopt('', [
	'platform:', 'version:', 'url:', 'file:', 'channel:', 'arch:', 'versionstring:',
	'minimum-version:', 'sparkle-url:', 'sparkle-file:', 'ed-signature:', 'manifest:', 'date:',
]);

function fail(string $message): never {
	fwrite(STDERR, "error: $message\n");
	exit(1);
}

foreach (['platform', 'version', 'url'] as $required) {
	if (empty($opts[$required])) {
		fail("--$required is required");
	}
}
if (!in_array($opts['platform'], ['win32', 'macos', 'linux'], true)) {
	fail('--platform must be win32, macos or linux');
}
if (!preg_match('/^\d+\.\d+\.\d+(\.\d+)?$/', $opts['version'])) {
	fail('--version must look like 35.0.50 or 35.0.50.1');
}
if (!str_starts_with($opts['url'], 'https://')) {
	fail('--url must be an https:// URL');
}

$manifestPath = $opts['manifest'] ?? __DIR__ . '/../config/releases.json';
$manifest = json_decode((string)@file_get_contents($manifestPath), true) ?: ['name' => 'Edalvix Cloud', 'channels' => []];
$channel = $opts['channel'] ?? 'stable';
$key = $opts['platform'] . (empty($opts['arch']) ? '' : '-' . strtolower($opts['arch']));

$short = implode('.', array_slice(explode('.', $opts['version']), 0, 3));
$release = [
	'version' => $opts['version'],
	'versionstring' => $opts['versionstring'] ?? "Edalvix Cloud $short",
	'downloadurl' => $opts['url'],
	'date' => $opts['date'] ?? gmdate('Y-m-d'),
];
if (!empty($opts['minimum-version'])) {
	$release['minimumVersion'] = $opts['minimum-version'];
}
if (!empty($opts['file'])) {
	$hash = @hash_file('sha256', $opts['file']);
	$hash !== false || fail("cannot read {$opts['file']}");
	$release['sha256'] = $hash;
}
if ($opts['platform'] === 'macos') {
	if (empty($opts['ed-signature'])) {
		fail('macOS releases need --ed-signature (Sparkle sign_update output)');
	}
	$length = 0;
	if (!empty($opts['sparkle-file'])) {
		$length = @filesize($opts['sparkle-file']);
		$length !== false || fail("cannot read {$opts['sparkle-file']}");
	}
	$release['sparkle'] = [
		'url' => $opts['sparkle-url'] ?? $opts['url'],
		'length' => $length,
		'edSignature' => $opts['ed-signature'],
	];
}

$manifest['channels'][$channel][$key] = $release;
$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
$tmp = $manifestPath . '.tmp';
file_put_contents($tmp, $json) !== false || fail("cannot write $tmp");
rename($tmp, $manifestPath) || fail("cannot replace $manifestPath");

echo "Published {$release['versionstring']} ($key, channel $channel) to $manifestPath\n";
