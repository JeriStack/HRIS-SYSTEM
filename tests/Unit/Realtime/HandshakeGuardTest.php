<?php

namespace Tests\Unit\Realtime;

use App\Realtime\HandshakeGuard;
use App\Realtime\Logger;
use App\Realtime\OriginPolicy;
use App\Realtime\RealtimeConfig;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Tests\Support\FakeConnection;

class HandshakeGuardTest extends TestCase
{
    private RealtimeConfig $config;

    /** @var object{opened:int, messages:array, closed:int} */
    private object $next;

    private HandshakeGuard $guard;

    protected function setUp(): void
    {
        $this->config = RealtimeConfig::fromEnvironment([
            'BASE_URL'                     => 'http://localhost:8080',
            'WS_MAX_CONNECTIONS'           => '3',
            'WS_MAX_CONNECTIONS_PER_IP'    => '2',
            'WS_MAX_INBOUND_BYTES_PER_10S' => '4096',
        ]);

        $this->next = new class implements HttpServerInterface {
            public int $opened = 0;
            public array $messages = [];
            public int $closed = 0;
            public function onOpen(ConnectionInterface $conn, ?RequestInterface $request = null) { $this->opened++; }
            public function onMessage(ConnectionInterface $from, $msg) { $this->messages[] = $msg; }
            public function onClose(ConnectionInterface $conn) { $this->closed++; }
            public function onError(ConnectionInterface $conn, \Exception $e) {}
        };

        $this->guard = new HandshakeGuard($this->next, $this->config, new OriginPolicy($this->config->allowedOrigins), Logger::silent());
    }

    private function request(string $uri = '/ws', ?string $origin = 'http://localhost:8080', string $method = 'GET', array $headers = []): Request
    {
        if ($origin !== null) {
            $headers['Origin'] = $origin;
        }
        return new Request($method, 'http://realtime:8090' . $uri, $headers);
    }

    private function statusOf(FakeConnection $conn): int
    {
        return (int)substr($conn->sent[0] ?? 'HTTP/1.1 000', 9, 3);
    }

    public function test_valid_handshake_is_delegated(): void
    {
        $conn = new FakeConnection();
        $this->guard->onOpen($conn, $this->request());

        $this->assertSame(1, $this->next->opened);
        $this->assertFalse($conn->closed);
        $this->assertSame('203.0.113.10', $conn->hrisClientIp);
    }

    public function test_rejections_before_upgrade(): void
    {
        $cases = [
            [$this->request('/other'), 404],
            [$this->request('/ws', 'http://evil.example'), 403],
            [$this->request('/ws', null), 403],
            [$this->request('/ws?ticket=' . str_repeat('a', 64)), 400],
            [$this->request('/ws', 'http://localhost:8080', 'POST'), 405],
        ];

        foreach ($cases as [$request, $expected]) {
            $conn = new FakeConnection();
            $this->guard->onOpen($conn, $request);
            $this->assertSame($expected, $this->statusOf($conn), (string)$request->getUri());
            $this->assertTrue($conn->closed);
        }
        $this->assertSame(0, $this->next->opened);
    }

    public function test_health_endpoint_answers_without_upgrade(): void
    {
        $conn = new FakeConnection();
        $this->guard->onOpen($conn, $this->request('/healthz', null));
        $this->assertSame(200, $this->statusOf($conn));
        $this->assertStringEndsWith('ok', $conn->sent[0]);
        $this->assertSame(0, $this->next->opened);
    }

    public function test_per_ip_and_global_limits(): void
    {
        $a1 = new FakeConnection('198.51.100.1');
        $a2 = new FakeConnection('198.51.100.1');
        $a3 = new FakeConnection('198.51.100.1');
        $this->guard->onOpen($a1, $this->request());
        $this->guard->onOpen($a2, $this->request());
        $this->guard->onOpen($a3, $this->request());
        $this->assertSame(429, $this->statusOf($a3));

        // Closing frees the slot
        $this->guard->onClose($a1);
        $a4 = new FakeConnection('198.51.100.1');
        $this->guard->onOpen($a4, $this->request());
        $this->assertFalse($a4->closed);

        $b1 = new FakeConnection('198.51.100.2');
        $this->guard->onOpen($b1, $this->request());
        $b2 = new FakeConnection('198.51.100.3');
        $this->guard->onOpen($b2, $this->request());
        $this->assertSame(503, $this->statusOf($b2), 'Global connection cap must apply.');
    }

    public function test_forwarded_ip_is_trusted_only_from_proxies(): void
    {
        $viaProxy = new FakeConnection('172.18.0.3');
        $this->guard->onOpen($viaProxy, $this->request('/ws', 'http://localhost:8080', 'GET', ['X-Real-IP' => '192.0.2.44']));
        $this->assertSame('192.0.2.44', $viaProxy->hrisClientIp);

        $direct = new FakeConnection('203.0.113.77');
        $this->guard->onOpen($direct, $this->request('/ws', 'http://localhost:8080', 'GET', ['X-Real-IP' => '10.9.9.9']));
        $this->assertSame('203.0.113.77', $direct->hrisClientIp, 'Spoofed X-Real-IP from an untrusted peer is ignored.');
    }

    public function test_inbound_byte_budget_closes_flooding_connection(): void
    {
        $conn = new FakeConnection();
        $this->guard->onOpen($conn, $this->request());

        $this->guard->onMessage($conn, str_repeat('x', 3000));
        $this->assertFalse($conn->closed);
        $this->guard->onMessage($conn, str_repeat('x', 2000));
        $this->assertTrue($conn->closed);
        $this->assertCount(1, $this->next->messages);
    }
}
