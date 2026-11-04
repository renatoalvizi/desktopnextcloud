<?php

/**
 * Tests for the Edalvix Cloud update server: php tests/run.php
 *
 * SPDX-FileCopyrightText: 2026 EDALVIX
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

require __DIR__ . '/../src/UpdateServer.php';

use Edalvix\UpdateServer\UpdateServer;

$failures = 0;
function check(bool $condition, string $name): void {
	global $failures;
	echo ($condition ? 'ok   ' : 'FAIL ') . $name . "\n";
	if (!$condition) {
		$failures++;
	}
}

$manifest = [
	'name' => 'Edalvix Cloud',
	'web' => 'https://edalvix.com.br/cloud/download',
	'channels' => [
		'stable' => [
			'win32' => ['version' => '35.0.50.7', 'versionstring' => 'Edalvix Cloud 35.0.50', 'downloadurl' => 'https://u.example/x64.msi'],
			'win32-arm64' => ['version' => '35.0.50.7', 'versionstring' => 'Edalvix Cloud 35.0.50', 'downloadurl' => 'https://u.example/arm64.msi'],
			'linux' => ['version' => '35.0.50.7', 'versionstring' => 'Edalvix Cloud 35.0.50', 'downloadurl' => 'https://u.example/a.AppImage', 'minimumVersion' => '30.0.0'],
			'macos' => ['version' => '35.0.50.7', 'versionstring' => 'Edalvix Cloud 35.0.50 & co', 'downloadurl' => 'https://u.example/a.pkg',
				'date' => '2026-11-04', 'sparkle' => ['url' => 'https://u.example/a.pkg.tbz', 'length' => 42, 'edSignature' => 'SIG==']],
		],
		'beta' => [
			'win32' => ['version' => '36.0.0.1', 'versionstring' => 'Edalvix Cloud 36.0.0 beta', 'downloadurl' => 'https://u.example/beta.msi'],
		],
	],
];
$server = new UpdateServer($manifest);

// Version arithmetic mirrors the client
check(UpdateServer::parseVersion('35.0.50.7') === [35, 0, 50, 7], 'parses four-part version');
check(UpdateServer::parseVersion('35.0.50daily') === [35, 0, 50, 0], 'ignores suffix');
check(UpdateServer::isNewer('35.0.50.7', '35.0.50.6'), 'build number counts');
check(!UpdateServer::isNewer('35.0.50.7', '35.0.50.7'), 'same version is not newer');
check(UpdateServer::isNewer('35.0.10', '35.0.9'), 'numeric, not lexical, comparison');

// Offers
$win = $server->findUpdate(['platform' => 'win32', 'version' => '35.0.0.1', 'channel' => 'stable', 'currentArch' => 'x86_64']);
check(($win['downloadurl'] ?? '') === 'https://u.example/x64.msi', 'windows x64 gets the generic msi');
check(($win['web'] ?? '') === 'https://edalvix.com.br/cloud/download', 'web falls back to manifest');
$arm = $server->findUpdate(['platform' => 'win32', 'version' => '35.0.0.1', 'currentArch' => 'arm64']);
check(($arm['downloadurl'] ?? '') === 'https://u.example/arm64.msi', 'windows arm64 gets the arm64 msi');
check($server->findUpdate(['platform' => 'win32', 'version' => '35.0.50.7']) === null, 'up to date gets nothing');
check($server->findUpdate(['platform' => 'win32', 'version' => '99.0.0']) === null, 'newer than published gets nothing');
check(($server->findUpdate(['platform' => 'win32', 'version' => '35.0.0', 'channel' => 'beta'])['version'] ?? '') === '36.0.0.1', 'beta channel');
check(($server->findUpdate(['platform' => 'win32', 'version' => '35.0.0', 'channel' => 'enterprise'])['version'] ?? '') === '35.0.50.7', 'unknown channel falls back to stable');
check($server->findUpdate(['platform' => 'linux', 'version' => '29.0.0']) === null, 'below minimumVersion gets nothing');
check($server->findUpdate(['platform' => 'linux', 'version' => '30.0.0']) !== null, 'at minimumVersion gets update');
check($server->findUpdate(['platform' => 'stranger', 'version' => '1.0']) === null, 'unknown platform gets nothing');
check($server->findUpdate(['platform' => 'win32']) === null, 'missing version gets nothing');

// Wire format
$r = $server->respond(['platform' => 'win32', 'version' => '35.0.0.1', 'msi' => 'true']);
$doc = simplexml_load_string($r['body']);
check($doc !== false && $doc->getName() === 'owncloudclient', 'owncloudclient root');
check((string)$doc->version === '35.0.50.7' && (string)$doc->downloadurl === 'https://u.example/x64.msi', 'owncloudclient fields');
check(str_starts_with($r['contentType'], 'text/xml'), 'xml content type');
$none = simplexml_load_string($server->respond(['platform' => 'win32', 'version' => '35.0.50.7'])['body']);
check($none !== false && $none->getName() === 'owncloudclient' && count($none->children()) === 0, 'empty owncloudclient when up to date');

$feed = $server->respond(['platform' => 'macos', 'version' => '35.0.0', 'sparkle' => 'true']);
$rss = simplexml_load_string($feed['body']);
$enclosure = $rss->channel->item->enclosure ?? null;
$sp = $enclosure ? $enclosure->attributes('http://www.andymatuschak.org/xml-namespaces/sparkle') : null;
check($enclosure !== null && (string)$enclosure['url'] === 'https://u.example/a.pkg.tbz', 'sparkle enclosure url');
check($sp !== null && (string)$sp->edSignature === 'SIG==' && (string)$sp->version === '35.0.50.7', 'sparkle signature and version');
check((string)$rss->channel->item->title === 'Edalvix Cloud 35.0.50 & co', 'sparkle title escaped correctly');
$emptyFeed = simplexml_load_string($server->respond(['platform' => 'macos', 'version' => '35.0.50.7', 'sparkle' => 'true'])['body']);
check($emptyFeed !== false && !isset($emptyFeed->channel->item), 'sparkle feed without items when up to date');
$noSparkle = simplexml_load_string($server->respond(['platform' => 'macos', 'version' => '35.0.0'])['body']);
check((string)$noSparkle->downloadurl === 'https://u.example/a.pkg', 'macos without sparkle gets owncloudclient');

// publish-release.php round trip
$tmp = tempnam(sys_get_temp_dir(), 'rel');
file_put_contents($tmp, json_encode(['name' => 'Edalvix Cloud', 'channels' => ['stable' => []]]));
$cmd = sprintf('%s %s --manifest=%s --platform=win32 --version=35.0.51.2 --url=https://u.example/new.msi --file=%s 2>&1',
	escapeshellarg(PHP_BINARY), escapeshellarg(__DIR__ . '/../tools/publish-release.php'), escapeshellarg($tmp), escapeshellarg(__FILE__));
exec($cmd, $out, $code);
$published = UpdateServer::fromFile($tmp)->findUpdate(['platform' => 'win32', 'version' => '35.0.50']);
check($code === 0 && ($published['versionstring'] ?? '') === 'Edalvix Cloud 35.0.51', 'publish-release adds a release');
check(($published['sha256'] ?? '') === hash_file('sha256', __FILE__), 'publish-release records sha256');
exec(sprintf('%s %s --manifest=%s --platform=macos --version=35.0.51 --url=https://u.example/a.pkg 2>&1',
	escapeshellarg(PHP_BINARY), escapeshellarg(__DIR__ . '/../tools/publish-release.php'), escapeshellarg($tmp)), $out, $code);
check($code !== 0, 'publish-release refuses macOS without signature');
exec(sprintf('%s %s --manifest=%s --platform=win32 --version=35.0.51 --url=http://insecure/a.msi 2>&1',
	escapeshellarg(PHP_BINARY), escapeshellarg(__DIR__ . '/../tools/publish-release.php'), escapeshellarg($tmp)), $out, $code);
check($code !== 0, 'publish-release refuses http URLs');
unlink($tmp);

// Shipped manifests are valid
UpdateServer::fromFile(__DIR__ . '/../config/releases.json');
UpdateServer::fromFile(__DIR__ . '/../config/releases.example.json');
check(true, 'shipped manifests parse');

echo $failures === 0 ? "\nAll tests passed\n" : "\n$failures test(s) failed\n";
exit($failures === 0 ? 0 : 1);
