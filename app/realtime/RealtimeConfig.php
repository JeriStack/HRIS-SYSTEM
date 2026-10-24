<?php
/**
 * Real-time (WebSocket) Service Configuration
 * Employee HRIS & Leave Management System
 *
 * All values come from environment variables so the HTTP application and the
 * WebSocket container share one configuration source. No secrets live here.
 */

namespace App\Realtime;

final class RealtimeConfig
{
    /**
     * @param string[] $allowedOrigins Normalized origins (scheme://host[:port])
     * @param string[] $trustedProxies IPs or CIDR ranges allowed to set X-Real-IP / X-Forwarded-For
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly string $listenHost,
        public readonly int $listenPort,
        public readonly string $path,
        public readonly ?string $publicUrl,
        public readonly array $allowedOrigins,
        public readonly int $ticketTtl,
        public readonly int $ticketsPerMinute,
        public readonly int $maxConnections,
        public readonly int $maxConnectionsPerUser,
        public readonly int $maxConnectionsPerIp,
        public readonly int $maxMessageBytes,
        public readonly int $maxInboundBytesPerWindow,
        public readonly int $authTimeout,
        public readonly float $pollInterval,
        public readonly int $revalidateInterval,
        public readonly int $rateLimitBurst,
        public readonly float $rateLimitPerSecond,
        public readonly int $keepAliveInterval,
        public readonly array $trustedProxies,
        public readonly int $sessionLifetime,
        public readonly string $logLevel,
    ) {
    }

    /**
     * Build configuration from environment variables (getenv / $_ENV).
     *
     * @param array<string,string>|null $env Optional override map (used by tests)
     */
    public static function fromEnvironment(?array $env = null): self
    {
        $get = static function (string $key, ?string $default = null) use ($env): ?string {
            if ($env !== null) {
                return array_key_exists($key, $env) ? (string)$env[$key] : $default;
            }
            $value = getenv($key);
            if ($value === false) {
                $value = $_ENV[$key] ?? null;
            }
            return ($value === null || $value === '') ? $default : (string)$value;
        };

        $int = static fn (string $key, int $default, int $min, int $max): int
            => max($min, min($max, (int)($get($key, (string)$default))));

        $baseUrl = $get('BASE_URL', '');

        $origins = [];
        $configuredOrigins = $get('WS_ALLOWED_ORIGINS');
        if ($configuredOrigins !== null) {
            foreach (explode(',', $configuredOrigins) as $candidate) {
                $normalized = OriginPolicy::normalize(trim($candidate));
                if ($normalized !== null) {
                    $origins[] = $normalized;
                }
            }
        } elseif ($baseUrl !== '') {
            // Default: only the application's own origin may open sockets
            $normalized = OriginPolicy::normalize($baseUrl);
            if ($normalized !== null) {
                $origins[] = $normalized;
            }
        }

        $path = '/' . ltrim((string)$get('WS_PATH', '/ws'), '/');

        $proxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string)$get('WS_TRUSTED_PROXIES', '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'))
        )));

        $enabledRaw = strtolower((string)$get('WS_ENABLED', 'true'));

        return new self(
            enabled: !in_array($enabledRaw, ['0', 'false', 'off', 'no'], true),
            listenHost: (string)$get('WS_LISTEN_HOST', '0.0.0.0'),
            listenPort: $int('WS_PORT', 8090, 1, 65535),
            path: $path,
            publicUrl: $get('WS_PUBLIC_URL'),
            allowedOrigins: array_values(array_unique($origins)),
            ticketTtl: $int('WS_TICKET_TTL', 30, 5, 120),
            ticketsPerMinute: $int('WS_TICKETS_PER_MINUTE', 10, 1, 120),
            maxConnections: $int('WS_MAX_CONNECTIONS', 1000, 1, 100000),
            maxConnectionsPerUser: $int('WS_MAX_CONNECTIONS_PER_USER', 5, 1, 100),
            maxConnectionsPerIp: $int('WS_MAX_CONNECTIONS_PER_IP', 25, 1, 10000),
            maxMessageBytes: $int('WS_MAX_MESSAGE_BYTES', 4096, 256, 65536),
            maxInboundBytesPerWindow: $int('WS_MAX_INBOUND_BYTES_PER_10S', 65536, 4096, 10485760),
            authTimeout: $int('WS_AUTH_TIMEOUT', 5, 1, 60),
            pollInterval: max(0.2, min(30.0, (float)$get('WS_POLL_INTERVAL', '1.0'))),
            revalidateInterval: $int('WS_REVALIDATE_INTERVAL', 15, 5, 600),
            rateLimitBurst: $int('WS_RATE_LIMIT_BURST', 20, 1, 1000),
            rateLimitPerSecond: max(0.1, min(100.0, (float)$get('WS_RATE_LIMIT_PER_SECOND', '2'))),
            keepAliveInterval: $int('WS_KEEPALIVE_INTERVAL', 30, 5, 300),
            trustedProxies: $proxies,
            sessionLifetime: $int('SESSION_LIFETIME', 1800, 60, 604800),
            logLevel: strtolower((string)$get('WS_LOG_LEVEL', 'info')),
        );
    }

    /**
     * Resolve the browser-facing WebSocket URL.
     * Priority: WS_PUBLIC_URL, otherwise derived from BASE_URL (http->ws, https->wss).
     */
    public function browserUrl(string $baseUrl): string
    {
        if ($this->publicUrl !== null && $this->publicUrl !== '') {
            return $this->publicUrl;
        }

        $parts = parse_url($baseUrl);
        if (!is_array($parts) || empty($parts['host'])) {
            return $this->path; // Client resolves relative to window.location
        }

        $scheme = strtolower($parts['scheme'] ?? 'http') === 'https' ? 'wss' : 'ws';
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        return $scheme . '://' . $parts['host'] . $port . $this->path;
    }
}
