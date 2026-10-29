<?php
/**
 * LIFO Stack Data Structure
 * Used for activity audit trail and recent activity operations
 * Employee HRIS & Leave Management System — Phase 11
 */

namespace App\Dsa\Structures;

class Stack
{
    /**
     * @var array<int, mixed>
     */
    private array $items = [];

    /**
     * Push an item onto the top of the stack.
     * Amortized O(1) time complexity.
     */
    public function push(mixed $item): void
    {
        $this->items[] = $item;
    }

    /**
     * Remove and return the top item from the stack.
     * Returns null if the stack is empty.
     * O(1) time complexity.
     */
    public function pop(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }
        return array_pop($this->items);
    }

    /**
     * Return the top item without removing it.
     * Returns null if the stack is empty.
     * O(1) time complexity.
     */
    public function peek(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }
        return end($this->items);
    }

    /**
     * Check whether the stack contains any items.
     * O(1) time complexity.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Return the total number of items on the stack.
     * O(1) time complexity.
     */
    public function size(): int
    {
        return count($this->items);
    }

    /**
     * Clear all items from the stack.
     * O(1) memory reset.
     */
    public function clear(): void
    {
        $this->items = [];
    }

    /**
     * Return all items as a standard indexed array (bottom to top).
     *
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        return array_values($this->items);
    }
}
