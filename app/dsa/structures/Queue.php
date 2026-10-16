<?php
/**
 * FIFO Queue Data Structure
 * Implemented with pointer nodes to prevent array_shift() O(N) re-indexing penalties
 * Used for chronological leave application review queues
 * Employee HRIS & Leave Management System
 */

namespace App\Dsa\Structures;

class QueueNode
{
    public mixed $value;
    public ?QueueNode $next = null;

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }
}

class Queue
{
    private ?QueueNode $head = null;
    private ?QueueNode $tail = null;
    private int $count = 0;

    public function enqueue(mixed $item): void
    {
        $node = new QueueNode($item);
        if ($this->tail === null) {
            $this->head = $node;
            $this->tail = $node;
        } else {
            $this->tail->next = $node;
            $this->tail = $node;
        }
        $this->count++;
    }

    public function dequeue(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        $value = $this->head->value;
        $this->head = $this->head->next;
        if ($this->head === null) {
            $this->tail = null;
        }
        $this->count--;
        return $value;
    }

    public function peek(): mixed
    {
        return $this->head !== null ? $this->head->value : null;
    }

    public function isEmpty(): bool
    {
        return $this->head === null;
    }

    public function size(): int
    {
        return $this->count;
    }

    public function toArray(): array
    {
        $items = [];
        $current = $this->head;
        while ($current !== null) {
            $items[] = $current->value;
            $current = $current->next;
        }
        return $items;
    }
}
