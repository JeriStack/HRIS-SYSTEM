<?php
/**
 * FIFO Queue Data Structure
 * Used for chronological leave application review queues
 * Employee HRIS & Leave Management System
 */

namespace App\Dsa\Structures;

class Queue
{
    private array $items = [];

    public function enqueue(mixed $item): void
    {
        $this->items[] = $item;
    }

    public function dequeue(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }
        return array_shift($this->items);
    }

    public function peek(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function size(): int
    {
        return count($this->items);
    }

    public function toArray(): array
    {
        return $this->items;
    }
}
