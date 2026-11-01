<?php
/**
 * Custom LIFO (Last-In, First-Out) Stack Data Structure
 * Employee HRIS & Leave Management System
 *
 * Implemented using an explicit zero-based top-of-stack index pointer ($top = -1)
 * rather than delegating to PHP's built-in array_pop() or end() functions.
 *
 * Complexity Summary:
 * - push(item): O(1) time, O(1) auxiliary space
 * - pop():      O(1) time, O(1) auxiliary space (safe null return on underflow)
 * - peek():     O(1) time, O(1) auxiliary space (safe null return on empty stack)
 * - isEmpty():  O(1) time
 * - size():     O(1) time
 */

namespace App\Dsa\Structures;

class Stack
{
    /** @var array<int, mixed> Internal contiguous storage */
    private array $items = [];

    /** @var int Explicit index pointer to top element (-1 when empty) */
    private int $top = -1;

    /**
     * Push an item onto the top of the stack.
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function push(mixed $item): void
    {
        $this->top++;
        $this->items[$this->top] = $item;
    }

    /**
     * Remove and return the item at the top of the stack.
     * Handles stack underflow safely by returning null when empty.
     *
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function pop(): mixed
    {
        if ($this->top < 0) {
            return null;
        }

        $value = $this->items[$this->top];
        unset($this->items[$this->top]);
        $this->top--;

        return $value;
    }

    /**
     * Inspect the item at the top of the stack without removing it.
     * Returns null safely when the stack is empty.
     *
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function peek(): mixed
    {
        if ($this->top < 0) {
            return null;
        }
        return $this->items[$this->top];
    }

    /**
     * Record a reversible in-memory operation frame onto the LIFO stack.
     * Demonstrates internal state-transition tracking without exposing a user-facing undo UI.
     *
     * @param string $operation Operation identifier (e.g. 'UPDATE_DEPARTMENT', 'DEACTIVATE_EMPLOYEE')
     * @param array<string, mixed> $beforeState Snapshot before the in-memory transition
     * @param array<string, mixed> $afterState Snapshot after the in-memory transition
     */
    public function pushOperation(string $operation, array $beforeState, array $afterState = []): void
    {
        $this->push([
            'operation' => $operation,
            'before'    => $beforeState,
            'after'     => $afterState,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     *Check if the stack contains no elements.
     * Time Complexity: O(1)
     */
    public function isEmpty(): bool
    {
        return $this->top < 0;
    }

    /**
     * Return the number of elements currently in the stack.
     * Time Complexity: O(1)
     */
    public function size(): int
    {
        return $this->top + 1;
    }

    /**
     * Clear all elements from the stack and reset top pointer.
     * Time Complexity: O(1)
     */
    public function clear(): void
    {
        $this->items = [];
        $this->top = -1;
    }

    /**
     * Export stack elements from bottom (index 0) to top (index size - 1).
     * Time Complexity: O(n) | Space Complexity: O(n)
     *
     * @return list<mixed>
     */
    public function toArray(): array
    {
        $out = [];
        for ($i = 0; $i <= $this->top; $i++) {
            $out[] = $this->items[$i];
        }
        return $out;
    }
}
