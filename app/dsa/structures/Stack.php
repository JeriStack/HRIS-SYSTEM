<?php
/**
 * LIFO Stack Data Structure
 * Used for activity audit trail and rollback operations
 * Employee HRIS & Leave Management System
 */

namespace App\Dsa\Structures;

class Stack
{
    private array $items = [];

    public function push(mixed $item): void
    {
        $this->items[] = $item;
    }

    public function pop(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }
        return array_pop($this->items);
    }

    public function peek(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }
        return end($this->items);
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
