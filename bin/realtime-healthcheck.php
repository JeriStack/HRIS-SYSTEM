<?php
/**
 * CLI: Health check for the WebSocket container.
 * Exit 0 when GET http://127.0.0.1:${WS_PORT}/healthz returns "ok", else 1.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$port = (int)(getenv('WS_PORT') ?: 8090);
$socket = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 3);
if (!$socket) {
    fwrite(STDERR, "unhealthy: cannot connect\n");
    exit(1);
}

stream_set_timeout($socket, 3);
fwrite($socket, "GET /healthz HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n");
$response = stream_get_contents($socket, 4096);
fclose($socket);

if (is_string($response) && str_starts_with($response, 'HTTP/1.1 200') && str_ends_with(rtrim($response), 'ok')) {
    exit(0);
}

fwrite(STDERR, "unhealthy: unexpected response\n");
exit(1);
