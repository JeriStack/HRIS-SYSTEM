<?php
/**
 * FIFO Queue Data Structure
 * Implemented with pointer nodes to prevent array_shift() O(N) re-indexing penalties
 * Used for chronological leave application review queues
 * Employee HRIS & Leave Management System — Phase 11
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

    /**
     * Add an item to the rear of the queue.
     * O(1) time complexity.
     */
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

    /**
     * Remove and return the front item from the queue.
     * Returns null if the queue is empty.
     * O(1) time complexity.
     */
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

    /**
     * Inspect the front item without removing it.
     * Returns null if the queue is empty.
     * O(1) time complexity.
     */
    public function peek(): mixed
    {
        return $this->head !== null ? $this->head->value : null;
    }

    /**
     * Check whether the queue is empty.
     * O(1) time complexity.
     */
    public function isEmpty(): bool
    {
        return $this->head === null;
    }

    /**
     * Return the total count of queued items.
     * O(1) time complexity.
     */
    public function size(): int
    {
        return $this->count;
    }

    /**
     * Reset the queue to an empty state.
     * O(1) time complexity.
     */
    public function clear(): void
    {
        $this->head = null;
        $this->tail = null;
        $this->count = 0;
    }

    /**
     * Return all queued items as an ordered array from front to rear.
     * O(n) time complexity.
     *
     * @return array<int, mixed>
     */
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
