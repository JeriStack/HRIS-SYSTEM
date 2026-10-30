<?php
/**
 * WebSocket Origin Allow-List
 * Employee HRIS & Leave Management System
 *
 * Browsers always send an Origin header on WebSocket handshakes. Comparing the
 * full scheme://host[:port] blocks cross-site WebSocket hijacking. The ticket
 * remains the real credential; Origin checks are defense in depth.
 */

namespace App\Realtime;

final class OriginPolicy
{
    /** @var array<string,true> */
    private array $allowed = [];

    /**
     * @param string[] $allowedOrigins
     */
    public function __construct(array $allowedOrigins)
    {
        foreach ($allowedOrigins as $origin) {
            $normalized = self::normalize($origin);
            if ($normalized !== null) {
                $this->allowed[$normalized] = true;
            }
        }
    }

    public function isAllowed(?string $origin): bool
    {
        if ($origin === null || $origin === '' || $this->allowed === []) {
            return false; // Fail closed
        }
        $normalized = self::normalize($origin);
        return $normalized !== null && isset($this->allowed[$normalized]);
    }

    /**
     * @return string[]
     */
    public function allowedOrigins(): array
    {
        return array_keys($this->allowed);
    }

    /**
     * Normalize an origin or URL to scheme://host[:port] (default ports removed).
     */
    public static function normalize(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 2048) {
            return null;
        }

        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        return $scheme . '://' . $host . ($port !== null ? ':' . $port : '');
    }
}
