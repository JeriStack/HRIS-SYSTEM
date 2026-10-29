<?php

namespace Tests\Unit\DSA;

use App\Dsa\Structures\Queue;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    private Queue $queue;

    protected function setUp(): void
    {
        $this->queue = new Queue();
    }

    public function test_empty_queue_behavior(): void
    {
        $this->assertTrue($this->queue->isEmpty());
        $this->assertSame(0, $this->queue->size());
        $this->assertNull($this->queue->dequeue());
        $this->assertNull($this->queue->peek());
        $this->assertSame([], $this->queue->toArray());
    }

    public function test_enqueue_and_dequeue_single_item(): void
    {
        $this->queue->enqueue('Request #1');
        $this->assertFalse($this->queue->isEmpty());
        $this->assertSame(1, $this->queue->size());
        $this->assertSame('Request #1', $this->queue->peek());
        $this->assertSame(1, $this->queue->size());

        $item = $this->queue->dequeue();
        $this->assertSame('Request #1', $item);
        $this->assertTrue($this->queue->isEmpty());
        $this->assertSame(0, $this->queue->size());
    }

    public function test_fifo_ordering(): void
    {
        $this->queue->enqueue('First');
        $this->queue->enqueue('Second');
        $this->queue->enqueue('Third');

        $this->assertSame(3, $this->queue->size());
        $this->assertSame('First', $this->queue->dequeue());
        $this->assertSame('Second', $this->queue->dequeue());
        $this->assertSame('Third', $this->queue->dequeue());
        $this->assertTrue($this->queue->isEmpty());
        $this->assertNull($this->queue->dequeue());
    }

    public function test_clear_queue(): void
    {
        $this->queue->enqueue('A');
        $this->queue->enqueue('B');
        $this->assertSame(2, $this->queue->size());

        $this->queue->clear();
        $this->assertTrue($this->queue->isEmpty());
        $this->assertSame(0, $this->queue->size());
        $this->assertNull($this->queue->dequeue());
    }

    public function test_to_array_returns_front_to_rear(): void
    {
        $this->queue->enqueue('R1');
        $this->queue->enqueue('R2');
        $this->queue->enqueue('R3');

        $this->assertSame(['R1', 'R2', 'R3'], $this->queue->toArray());
    }
}

