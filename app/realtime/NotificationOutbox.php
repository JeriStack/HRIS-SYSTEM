<?php
/**
 * Transactional Outbox Reader over the `notifications` table
 * Employee HRIS & Leave Management System
 *
 * The HTTP app writes notification rows inside the same DB transaction as the
 * leave change. This reader only sees rows after COMMIT. Because InnoDB
 * auto-increment ids can commit out of order, each poll re-scans a small id
 * window behind the cursor and de-duplicates by id.
 */

namespace App\Realtime;

use App\Realtime\Contracts\RealtimeStore;

final class NotificationOutbox
{
    private int $cursor = 0;
    private int $baseline = 0;
    private bool $initialized = false;

    /** @var array<int,true> */
    private array $seen = [];

    public function __construct(
        private readonly RealtimeStore $store,
        private readonly int $lookback = 200,
        private readonly int $batchSize = 500,
    ) {
    }

    /**
     * Start from the current head: history is loaded by clients over HTTP,
     * so the socket only streams rows committed after startup.
     */
    public function initialize(): void
    {
        $this->baseline = $this->store->latestNotificationId();
        $this->cursor = $this->baseline;
        $this->seen = [];
        $this->initialized = true;
    }

    /**
     * @return array<int, array<string,mixed>> Newly committed rows, ascending by id
     */
    public function poll(): array
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $floor = max($this->baseline, $this->cursor - $this->lookback);
        $rows = $this->store->fetchNotificationsAfter($floor, $this->batchSize);

        $fresh = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            if ($id <= $this->baseline || isset($this->seen[$id])) {
                continue;
            }
            $this->seen[$id] = true;
            $this->cursor = max($this->cursor, $id);
            $fresh[] = $row;
        }

        // Bound memory: ids below the scan window can never be returned again
        $newFloor = max($this->baseline, $this->cursor - $this->lookback);
        foreach ($this->seen as $id => $_) {
            if ($id <= $newFloor) {
                unset($this->seen[$id]);
            }
        }

        return $fresh;
    }

    public function cursor(): int
    {
        return $this->cursor;
    }
}
