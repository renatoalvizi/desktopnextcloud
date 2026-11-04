<?php

/**
 * Entry point: https://updates.edalvix.cloud/client/
 *
 * SPDX-FileCopyrightText: 2026 EDALVIX
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

require __DIR__ . '/../src/UpdateServer.php';

use Edalvix\UpdateServer\UpdateServer;

$manifestPath = getenv('EDALVIX_RELEASES_FILE') ?: __DIR__ . '/../config/releases.json';

header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');

try {
	$server = UpdateServer::fromFile($manifestPath);
	$response = $server->respond(array_map('strval', array_filter($_GET, 'is_string')));
} catch (\Throwable $e) {
	error_log('Edalvix update server: ' . $e->getMessage());
	http_response_code(500);
	header('Content-Type: text/plain; charset=utf-8');
	echo "Update information is temporarily unavailable.\n";
	return;
}

header('Content-Type: ' . $response['contentType']);
echo $response['body'];
