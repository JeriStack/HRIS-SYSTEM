<?php

namespace Tests\Unit\Realtime;

use App\Realtime\NotificationOutbox;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryRealtimeStore;

class NotificationOutboxTest extends TestCase
{
    private function row(int $id): array
    {
        return ['id' => $id, 'user_id' => 1, 'event_type' => 'leave_request.created'];
    }

    public function test_history_before_startup_is_not_replayed(): void
    {
        $store = new InMemoryRealtimeStore();
        $store->notifications = [$this->row(1), $this->row(2)];

        $outbox = new NotificationOutbox($store);
        $outbox->initialize();

        $this->assertSame([], $outbox->poll());
    }

    public function test_each_committed_row_is_emitted_exactly_once(): void
    {
        $store = new InMemoryRealtimeStore();
        $outbox = new NotificationOutbox($store);
        $outbox->initialize();

        $store->notifications[] = $this->row(1);
        $store->notifications[] = $this->row(2);
        $this->assertSame([1, 2], array_column($outbox->poll(), 'id'));
        $this->assertSame([], $outbox->poll(), 'Re-polling must not duplicate events.');
    }

    public function test_out_of_order_commit_inside_lookback_is_delivered_once(): void
    {
        $store = new InMemoryRealtimeStore();
        $outbox = new NotificationOutbox($store, lookback: 50);
        $outbox->initialize();

        // id 2 commits first; id 1 (older transaction) commits later
        $store->notifications[] = $this->row(2);
        $this->assertSame([2], array_column($outbox->poll(), 'id'));

        $store->notifications[] = $this->row(1);
        $this->assertSame([1], array_column($outbox->poll(), 'id'));
        $this->assertSame([], $outbox->poll());
    }

    public function test_uncommitted_rows_are_invisible_until_commit(): void
    {
        // The store only exposes committed rows (separate MySQL connection).
        // Simulate: nothing visible, then the row appears after COMMIT.
        $store = new InMemoryRealtimeStore();
        $outbox = new NotificationOutbox($store);
        $outbox->initialize();

        $this->assertSame([], $outbox->poll());
        $store->notifications[] = $this->row(10);
        $this->assertSame([10], array_column($outbox->poll(), 'id'));
    }
}
