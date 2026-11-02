<?php
/**
 * Custom FIFO (First-In, First-Out) Queue Data Structure
 * Employee HRIS & Leave Management System
 *
 * Implemented using linked pointer nodes (QueueNode) with explicit $head and
 * $tail references so both enqueue() and dequeue() execute in strict O(1)
 * worst-case time without shifting array elements (avoids O(n) array_shift).
 *
 * Complexity Summary:
 * - enqueue(item):   O(1) time, O(1) space
 * - dequeue():       O(1) time, O(1) space (safe null return when empty)
 * - front()/peek():  O(1) time, O(1) space (safe null return when empty)
 * - rear():          O(1) time, O(1) space
 * - isEmpty()/size(): O(1) time
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
     * Add an item to the rear (tail) of the queue.
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function enqueue(mixed $item): void
    {
        $node = new QueueNode($item);

        if ($this->tail !== null) {
            $this->tail->next = $node;
        }
        $this->tail = $node;

        if ($this->head === null) {
            $this->head = $node;
        }

        $this->count++;
    }

    /**
     * Remove and return the item from the front (head) of the queue.
     * Returns null safely when the queue is empty.
     *
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function dequeue(): mixed
    {
        if ($this->head === null) {
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
     * Inspect the item at the front of the queue without removing it.
     * Returns null safely when the queue is empty.
     *
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function front(): mixed
    {
        return $this->head?->value;
    }

    /**
     * Alias for front() — inspect the item at the front of the queue without removing it.
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function peek(): mixed
    {
        return $this->front();
    }

    /**
     * Inspect the item at the rear (tail) of the queue without removing it.
     * Returns null safely when the queue is empty.
     *
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function rear(): mixed
    {
        return $this->tail?->value;
    }

    /**
     * Check if the queue is empty.
     * Time Complexity: O(1)
     */
    public function isEmpty(): bool
    {
        return $this->head === null;
    }

    /**
     * Return the current number of items in the queue.
     * Time Complexity: O(1)
     */
    public function size(): int
    {
        return $this->count;
    }

    /**
     * Empty the queue and release all node references.
     * Time Complexity: O(1)
     */
    public function clear(): void
    {
        $this->head = null;
        $this->tail = null;
        $this->count = 0;
    }

    /**
     * Return all queue elements as an ordered array from front to rear.
     * Does not mutate the queue.
     *
     * Time Complexity: O(n) | Space Complexity: O(n)
     *
     * @return list<mixed>
     */
    public function toArray(): array
    {
        $result = [];
        $current = $this->head;
        while ($current !== null) {
            $result[] = $current->value;
            $current = $current->next;
        }
        return $result;
    }
}
