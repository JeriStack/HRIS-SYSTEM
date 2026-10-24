<?php
/**
 * IPv4/IPv6 address and CIDR matching helper.
 */

namespace App\Realtime;

final class IpRange
{
    /**
     * @param string[] $ranges IPs or CIDR blocks
     */
    public static function matchesAny(string $ip, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if (self::matches($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    public static function matches(string $ip, string $range): bool
    {
        $ipBin = @inet_pton($ip);
        if ($ipBin === false) {
            return false;
        }

        if (!str_contains($range, '/')) {
            $rangeBin = @inet_pton($range);
            return $rangeBin !== false && $rangeBin === $ipBin;
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $subnetBin = @inet_pton($subnet);
        if ($subnetBin === false || strlen($subnetBin) !== strlen($ipBin) || !ctype_digit($bits)) {
            return false;
        }

        $bits = (int)$bits;
        $maxBits = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        if (strncmp($ipBin, $subnetBin, $fullBytes) !== 0) {
            return false;
        }

        $remaining = $bits % 8;
        if ($remaining === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remaining)) & 0xFF;
        return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
    }
}
