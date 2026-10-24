<?php
/**
 * Per-connection state tracked by the RealtimeHub.
 */

namespace App\Realtime;

use Ratchet\ConnectionInterface;

final class ClientState
{
    public ?Identity $identity = null;

    /** @var array<string,true> */
    public array $channels = [];

    public int $strikes = 0;
    public bool $closing = false;

    public function __construct(
        public readonly int $id,
        public readonly ConnectionInterface $connection,
        public readonly string $ip,
        public readonly float $openedAt,
        public readonly TokenBucket $bucket,
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->identity !== null;
    }
}
