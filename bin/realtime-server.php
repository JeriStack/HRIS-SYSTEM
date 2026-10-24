<?php
/**
 * CLI: HRIS Real-time WebSocket Server
 *
 * Usage:
 *   php bin/realtime-server.php
 *
 * Docker: started by the `realtime` service in docker-compose.yml.
 * Configuration: WS_* environment variables (see .env.example).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This script must only be run via CLI.\n";
    exit(1);
}

// The WebSocket process never uses PHP sessions
define('HRIS_SKIP_SESSION', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/bootstrap.php';

// Ratchet 0.4.4 / ReactPHP emit PHP 8.4+ "implicitly nullable" and dynamic
// property deprecations. They are harmless on PHP 8.5 but would flood logs.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', 'stderr');
ini_set('log_errors', '0');

use App\Realtime\Logger;
use App\Realtime\PdoRealtimeStore;
use App\Realtime\RealtimeConfig;
use App\Realtime\Server;

$config = RealtimeConfig::fromEnvironment();
$logger = new Logger($config->logLevel);

if (!$config->enabled) {
    $logger->warning('ws.disabled', ['hint' => 'WS_ENABLED is false; exiting.']);
    exit(0);
}

$store = PdoRealtimeStore::fromEnvironment();
$server = new Server($config, $store, $logger);

if (!$server->waitForStore()) {
    $logger->error('ws.store_unavailable', ['hint' => 'Database or realtime tables unavailable. Run php bin/migrate.php.']);
    exit(1);
}

$server->run();
