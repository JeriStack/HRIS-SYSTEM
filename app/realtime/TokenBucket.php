<?php
/**
 * Token Bucket Rate Limiter (per WebSocket connection)
 * Employee HRIS & Leave Management System
 */

namespace App\Realtime;

final class TokenBucket
{
    private float $tokens;
    private float $updatedAt;

    public function __construct(
        private readonly int $capacity,
        private readonly float $refillPerSecond,
        float $now,
    ) {
        $this->tokens = (float)$capacity;
        $this->updatedAt = $now;
    }

    public function allow(float $now, float $cost = 1.0): bool
    {
        $elapsed = max(0.0, $now - $this->updatedAt);
        $this->tokens = min((float)$this->capacity, $this->tokens + $elapsed * $this->refillPerSecond);
        $this->updatedAt = $now;

        if ($this->tokens >= $cost) {
            $this->tokens -= $cost;
            return true;
        }

        return false;
    }
}
