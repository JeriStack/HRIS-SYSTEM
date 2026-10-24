<?php
/**
 * WebSocket Server Bootstrapper (Ratchet + ReactPHP event loop)
 * Employee HRIS & Leave Management System
 *
 * Stack: IoServer -> HttpServer -> HandshakeGuard -> WsServer -> RealtimeHub
 */

namespace App\Realtime;

use App\Realtime\Contracts\RealtimeStore;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Throwable;

final class Server
{
    public function __construct(
        private readonly RealtimeConfig $config,
        private readonly RealtimeStore $store,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Block until the database and realtime tables are reachable.
     */
    public function waitForStore(int $attempts = 30, int $delaySeconds = 2): bool
    {
        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $this->store->assertReady();
                return true;
            } catch (Throwable $e) {
                $this->logger->warning('ws.store_not_ready', [
                    'attempt' => $i,
                    'hint'    => 'Ensure MySQL is up and run: php bin/migrate.php',
                ]);
                sleep($delaySeconds);
            }
        }
        return false;
    }

    public function run(): void
    {
        $loop = Loop::get();

        $hub = new RealtimeHub($this->store, $this->config, new ChannelPolicy(), new EventAuthorizer(), $this->logger);
        $ws = new WsServer($hub);
        $ws->enableKeepAlive($loop, $this->config->keepAliveInterval);

        $origins = new OriginPolicy($this->config->allowedOrigins);
        $guard = new HandshakeGuard($ws, $this->config, $origins, $this->logger);

        $address = $this->config->listenHost . ':' . $this->config->listenPort;
        $socket = new SocketServer($address, [], $loop);
        new IoServer(new HttpServer($guard), $socket, $loop);

        $outbox = new NotificationOutbox($this->store);
        $outbox->initialize();

        // Auth timeouts and session deadlines
        $loop->addPeriodicTimer(1.0, static fn () => $hub->tick());

        // Outbox polling (committed notifications only)
        $loop->addPeriodicTimer($this->config->pollInterval, function () use ($hub, $outbox): void {
            try {
                $rows = $outbox->poll();
                if ($rows !== []) {
                    $hub->dispatch($rows);
                }
            } catch (Throwable $e) {
                $this->logger->error('ws.poll_failed', ['error' => get_class($e)]);
            }
        });

        // Logout / deactivation / role change enforcement
        $loop->addPeriodicTimer((float)$this->config->revalidateInterval, static fn () => $hub->revalidate());

        // Ticket housekeeping (keep rows well past any live connection's lifetime)
        $retention = max(86400, $this->config->sessionLifetime * 2);
        $loop->addPeriodicTimer(3600.0, function () use ($retention): void {
            try {
                $purged = $this->store->purgeTickets($retention);
                if ($purged > 0) {
                    $this->logger->info('ws.tickets_purged', ['count' => $purged]);
                }
            } catch (Throwable $e) {
                $this->logger->error('ws.purge_failed', ['error' => get_class($e)]);
            }
        });

        // Periodic stats (counts only)
        $loop->addPeriodicTimer(300.0, function () use ($hub): void {
            $this->logger->info('ws.stats', $hub->stats());
        });

        if (function_exists('pcntl_signal')) {
            foreach ([SIGTERM, SIGINT] as $signal) {
                $loop->addSignal($signal, function () use ($loop, $socket): void {
                    $this->logger->info('ws.shutdown');
                    $socket->close();
                    $loop->stop();
                });
            }
        }

        $this->logger->info('ws.listening', [
            'address'         => $address,
            'path'            => $this->config->path,
            'allowed_origins' => $origins->allowedOrigins(),
        ]);

        if ($origins->allowedOrigins() === []) {
            $this->logger->warning('ws.no_allowed_origins', ['hint' => 'Set WS_ALLOWED_ORIGINS or BASE_URL; all browser handshakes will be rejected.']);
        }

        $loop->run();
    }
}
