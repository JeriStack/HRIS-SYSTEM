<?php
/**
 * Singly Linked List Data Structure
 * Used for sequential leave history logs and transaction chains
 * Employee HRIS & Leave Management System — Phase 11
 */

namespace App\Dsa\Structures;

class ListNode
{
    public mixed $data;
    public ?ListNode $next = null;

    public function __construct(mixed $data)
    {
        $this->data = $data;
    }
}

class LinkedList
{
    private ?ListNode $head = null;
    private ?ListNode $tail = null;
    private int $count = 0;

    /**
     * Append a value to the tail of the list.
     * O(1) time complexity.
     */
    public function append(mixed $value): void
    {
        $node = new ListNode($value);
        if ($this->head === null) {
            $this->head = $node;
            $this->tail = $node;
        } else {
            $this->tail->next = $node;
            $this->tail = $node;
        }
        $this->count++;
    }

    /**
     * Backward-compatible alias for append().
     */
    public function insertLast(mixed $data): void
    {
        $this->append($data);
    }

    /**
     * Prepend a value to the head of the list.
     * O(1) time complexity.
     */
    public function prepend(mixed $value): void
    {
        $node = new ListNode($value);
        if ($this->head === null) {
            $this->head = $node;
            $this->tail = $node;
        } else {
            $node->next = $this->head;
            $this->head = $node;
        }
        $this->count++;
    }

    /**
     * Backward-compatible alias for prepend().
     */
    public function insertFirst(mixed $data): void
    {
        $this->prepend($data);
    }

    /**
     * Remove the first occurrence of a value from the list.
     * Handles head, middle, tail, and single-node cases.
     * Returns true if found and removed; false otherwise.
     * O(n) time complexity.
     */
    public function remove(mixed $value): bool
    {
        if ($this->head === null) {
            return false;
        }

        // Case 1: Head node matches
        if ($this->matches($this->head->data, $value)) {
            $this->head = $this->head->next;
            if ($this->head === null) {
                $this->tail = null;
            }
            $this->count--;
            return true;
        }

        // Case 2: Middle or tail node matches
        $current = $this->head;
        while ($current->next !== null) {
            if ($this->matches($current->next->data, $value)) {
                if ($current->next === $this->tail) {
                    $this->tail = $current;
                }
                $current->next = $current->next->next;
                $this->count--;
                return true;
            }
            $current = $current->next;
        }

        return false;
    }

    /**
     * Backward-compatible alias for remove().
     */
    public function delete(mixed $target): bool
    {
        return $this->remove($target);
    }

    /**
     * Find an item in the list matching the target.
     * Returns the matching item data or null if absent.
     * O(n) time complexity.
     */
    public function find(mixed $value): mixed
    {
        $current = $this->head;
        while ($current !== null) {
            if ($this->matches($current->data, $value)) {
                return $current->data;
            }
            $current = $current->next;
        }
        return null;
    }

    /**
     * Backward-compatible alias for find().
     */
    public function search(mixed $target): mixed
    {
        return $this->find($target);
    }

    /**
     * Retrieve the item data at a specific 0-based index.
     * Returns null if the index is out of bounds.
     * O(n) time complexity.
     */
    public function getAt(int $index): mixed
    {
        if ($index < 0 || $index >= $this->count || $this->head === null) {
            return null;
        }

        $current = $this->head;
        for ($i = 0; $i < $index; $i++) {
            $current = $current->next;
        }

        return $current->data;
    }

    /**
     * Return the total number of nodes in the list.
     * O(1) time complexity.
     */
    public function size(): int
    {
        return $this->count;
    }

    /**
     * Check whether the list has no nodes.
     * O(1) time complexity.
     */
    public function isEmpty(): bool
    {
        return $this->head === null;
    }

    /**
     * Clear all nodes from the list.
     * O(1) reset.
     */
    public function clear(): void
    {
        $this->head = null;
        $this->tail = null;
        $this->count = 0;
    }

    /**
     * Convert the linked list into an array preserving order.
     * O(n) time complexity.
     *
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        $current = $this->head;
        while ($current !== null) {
            $result[] = $current->data;
            $current = $current->next;
        }
        return $result;
    }

    /**
     * Equality and record matching helper.
     */
    private function matches(mixed $data, mixed $target): bool
    {
        if (is_array($data)) {
            if (isset($data['id']) && $data['id'] === $target) {
                return true;
            }
            if (isset($data['employee_no']) && $data['employee_no'] === $target) {
                return true;
            }
        }
        return $data === $target;
    }
}
