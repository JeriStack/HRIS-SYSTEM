<?php
/**
 * HTTP Upgrade Handshake Guard (sits between Ratchet HttpServer and WsServer)
 * Employee HRIS & Leave Management System
 *
 * Rejects before the WebSocket upgrade when:
 *   - the path is not WS_PATH (404) or the method is not GET (405)
 *   - a query string is present (400) - credentials never travel in URLs
 *   - the Origin header is missing or not allow-listed (403)
 *   - the global (503) or per-IP (429) connection limit is reached
 * After the upgrade it enforces a raw inbound byte budget per connection,
 * which bounds memory used by partially received frames.
 * GET /healthz answers 200 for container health checks (no data disclosed).
 */

namespace App\Realtime;

use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;

final class HandshakeGuard implements HttpServerInterface
{
    private const BYTE_WINDOW_SECONDS = 10.0;

    /** @var array<string,int> */
    private array $perIp = [];

    /** @var array<int, array{ip:string, windowStart:float, bytes:int}> */
    private array $tracked = [];

    /** @var callable(): float */
    private $clock;

    public function __construct(
        private readonly HttpServerInterface $next,
        private readonly RealtimeConfig $config,
        private readonly OriginPolicy $origins,
        private readonly Logger $logger,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    public function onOpen(ConnectionInterface $conn, ?RequestInterface $request = null)
    {
        if ($request === null) {
            return $this->reject($conn, 400);
        }

        $path = $request->getUri()->getPath();
        $remote = isset($conn->remoteAddress) ? (string)$conn->remoteAddress : '';
        $ip = $this->clientIp($remote, $request);

        if ($path === '/healthz' && $request->getMethod() === 'GET') {
            return $this->respond($conn, 200, 'ok');
        }

        if ($path !== $this->config->path) {
            return $this->reject($conn, 404);
        }

        if ($request->getMethod() !== 'GET') {
            return $this->reject($conn, 405);
        }

        if ($request->getUri()->getQuery() !== '') {
            $this->logger->warning('ws.handshake_rejected', ['ip' => $ip, 'reason' => 'query_string']);
            return $this->reject($conn, 400);
        }

        if (!$this->origins->isAllowed($request->getHeaderLine('Origin') ?: null)) {
            $this->logger->warning('ws.handshake_rejected', ['ip' => $ip, 'reason' => 'origin']);
            return $this->reject($conn, 403);
        }

        if (count($this->tracked) >= $this->config->maxConnections) {
            $this->logger->warning('ws.handshake_rejected', ['ip' => $ip, 'reason' => 'server_full']);
            return $this->reject($conn, 503);
        }

        if (($this->perIp[$ip] ?? 0) >= $this->config->maxConnectionsPerIp) {
            $this->logger->warning('ws.handshake_rejected', ['ip' => $ip, 'reason' => 'ip_limit']);
            return $this->reject($conn, 429);
        }

        $id = spl_object_id($conn);
        $this->tracked[$id] = ['ip' => $ip, 'windowStart' => $this->now(), 'bytes' => 0];
        $this->perIp[$ip] = ($this->perIp[$ip] ?? 0) + 1;
        $conn->hrisClientIp = $ip;

        return $this->next->onOpen($conn, $request);
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $id = spl_object_id($from);
        if (!isset($this->tracked[$id])) {
            $from->close();
            return;
        }

        $now = $this->now();
        $entry = &$this->tracked[$id];
        if ($now - $entry['windowStart'] >= self::BYTE_WINDOW_SECONDS) {
            $entry['windowStart'] = $now;
            $entry['bytes'] = 0;
        }
        $entry['bytes'] += strlen((string)$msg);

        if ($entry['bytes'] > $this->config->maxInboundBytesPerWindow) {
            $this->logger->warning('ws.byte_budget_exceeded', ['conn' => $id, 'ip' => $entry['ip']]);
            unset($entry);
            $from->close();
            return;
        }
        unset($entry);

        $this->next->onMessage($from, $msg);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $id = spl_object_id($conn);
        if (isset($this->tracked[$id])) {
            $ip = $this->tracked[$id]['ip'];
            unset($this->tracked[$id]);
            $this->perIp[$ip] = max(0, ($this->perIp[$ip] ?? 1) - 1);
            if ($this->perIp[$ip] === 0) {
                unset($this->perIp[$ip]);
            }
            $this->next->onClose($conn);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        if (isset($this->tracked[spl_object_id($conn)])) {
            $this->next->onError($conn, $e);
            return;
        }
        $conn->close();
    }

    public function openConnections(): int
    {
        return count($this->tracked);
    }

    /**
     * Use X-Real-IP / X-Forwarded-For only when the TCP peer is a trusted proxy.
     */
    private function clientIp(string $remote, RequestInterface $request): string
    {
        if ($remote !== '' && IpRange::matchesAny($remote, $this->config->trustedProxies)) {
            $realIp = trim($request->getHeaderLine('X-Real-IP'));
            if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP)) {
                return $realIp;
            }
            $forwarded = array_map('trim', explode(',', $request->getHeaderLine('X-Forwarded-For')));
            $last = end($forwarded);
            if (is_string($last) && filter_var($last, FILTER_VALIDATE_IP)) {
                return $last;
            }
        }

        return $remote !== '' ? $remote : 'unknown';
    }

    private function reject(ConnectionInterface $conn, int $status): void
    {
        $this->respond($conn, $status, '');
    }

    private function respond(ConnectionInterface $conn, int $status, string $body): void
    {
        $response = new Response($status, [
            'Content-Type'   => 'text/plain; charset=utf-8',
            'Cache-Control'  => 'no-store',
            'Connection'     => 'close',
            'Content-Length' => (string)strlen($body),
        ], $body);

        $conn->send(Message::toString($response));
        $conn->close();
    }

    private function now(): float
    {
        return ($this->clock)();
    }
}
