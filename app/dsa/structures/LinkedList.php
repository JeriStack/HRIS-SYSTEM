<?php
/**
 * Custom Singly Linked List Data Structure
 * Employee HRIS & Leave Management System
 *
 * Implements a pointer-based Singly Linked List using Node objects without
 * relying on PHP array indexing.
 *
 * Complexity Summary:
 * - prepend / insertFirst (head insertion): O(1) time, O(1) space
 * - append / insertLast (tail insertion via $tail pointer): O(1) time, O(1) space
 * - insertAt(position, value): O(1) at head/tail, O(n) arbitrary interior position
 * - remove / delete (by value): O(n) time, O(1) space
 * - removeAt / deleteAt (by position): O(1) at head, O(n) arbitrary position
 * - getAt(index) / find / search / traverse / toArray: O(n) time
 */

namespace App\Dsa\Structures;

class ListNode extends Node
{
}

class LinkedList
{
    private ?Node $head = null;
    private ?Node $tail = null;
    private int $count = 0;

    /**
     * Insert a new node at the end of the linked list.
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function append(mixed $value): void
    {
        $newNode = new ListNode($value);
        if ($this->head === null) {
            $this->head = $newNode;
            $this->tail = $newNode;
        } else {
            $this->tail->next = $newNode;
            $this->tail = $newNode;
        }
        $this->count++;
    }

    /**
     * Alias for append() — insert at the end of the list.
     * Time Complexity: O(1)
     */
    public function insertLast(mixed $value): void
    {
        $this->append($value);
    }

    /**
     * Insert a new node at the beginning (head) of the linked list.
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function prepend(mixed $value): void
    {
        $newNode = new ListNode($value, $this->head);
        $this->head = $newNode;
        if ($this->tail === null) {
            $this->tail = $newNode;
        }
        $this->count++;
    }

    /**
     * Alias for prepend() — insert at the beginning of the list.
     * Time Complexity: O(1)
     */
    public function insertFirst(mixed $value): void
    {
        $this->prepend($value);
    }

    /**
     * Insert a new node at a specified zero-based position [0 .. size].
     * Returns false safely when $position is negative or greater than $this->count.
     *
     * Time Complexity: O(1) at position 0 or size, O(n) for interior positions.
     * Space Complexity: O(1)
     */
    public function insertAt(int $position, mixed $value): bool
    {
        if ($position < 0 || $position > $this->count) {
            return false;
        }

        if ($position === 0) {
            $this->prepend($value);
            return true;
        }

        if ($position === $this->count) {
            $this->append($value);
            return true;
        }

        $prev = $this->head;
        for ($i = 0; $i < $position - 1 && $prev !== null; $i++) {
            $prev = $prev->next;
        }

        if ($prev === null) {
            return false;
        }

        $newNode = new ListNode($value, $prev->next);
        $prev->next = $newNode;
        $this->count++;
        return true;
    }

    /**
     * Retrieve the value at a specific zero-based index.
     * Time Complexity: O(n) | Space Complexity: O(1)
     */
    public function getAt(int $index): mixed
    {
        if ($index < 0 || $index >= $this->count) {
            return null;
        }

        $current = $this->head;
        for ($i = 0; $i < $index && $current !== null; $i++) {
            $current = $current->next;
        }

        return $current?->value;
    }

    /**
     * Search for the first node matching a target value, associative record 'id',
     * specific $attribute, or custom predicate callable.
     *
     * Time Complexity: O(n) | Space Complexity: O(1)
     */
    public function find(mixed $criteria, ?string $attribute = null): mixed
    {
        $current = $this->head;
        while ($current !== null) {
            if ($this->nodeMatches($current->value, $criteria, $attribute)) {
                return $current->value;
            }
            $current = $current->next;
        }
        return null;
    }

    /**
     * Search for the zero-based position of the first matching node, or false if absent.
     * Time Complexity: O(n) | Space Complexity: O(1)
     */
    public function search(mixed $criteria, ?string $attribute = null): int|false
    {
        $current = $this->head;
        $index = 0;
        while ($current !== null) {
            if ($this->nodeMatches($current->value, $criteria, $attribute)) {
                return $index;
            }
            $current = $current->next;
            $index++;
        }
        return false;
    }

    /**
     * Remove the first node matching the given value or record ID.
     * Time Complexity: O(n) | Space Complexity: O(1)
     */
    public function remove(mixed $value, ?string $attribute = null): bool
    {
        if ($this->head === null) {
            return false;
        }

        // Case 1: Head node matches
        if ($this->nodeMatches($this->head->value, $value, $attribute)) {
            $this->head = $this->head->next;
            if ($this->head === null) {
                $this->tail = null;
            }
            $this->count--;
            return true;
        }

        // Case 2: Subsequent node matches
        $current = $this->head;
        while ($current->next !== null) {
            if ($this->nodeMatches($current->next->value, $value, $attribute)) {
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
     * Alias for remove() — delete first node matching value.
     * Time Complexity: O(n)
     */
    public function delete(mixed $value, ?string $attribute = null): bool
    {
        return $this->remove($value, $attribute);
    }

    /**
     * Remove and return the node value at a specified zero-based position.
     * Returns null safely when list is empty or position is out of bounds.
     *
     * Time Complexity: O(1) at head (position 0), O(n) at arbitrary position
     * Space Complexity: O(1)
     */
    public function removeAt(int $position): mixed
    {
        if ($position < 0 || $position >= $this->count || $this->head === null) {
            return null;
        }

        if ($position === 0) {
            $removedValue = $this->head->value;
            $this->head = $this->head->next;
            if ($this->head === null) {
                $this->tail = null;
            }
            $this->count--;
            return $removedValue;
        }

        $prev = $this->head;
        for ($i = 0; $i < $position - 1 && $prev->next !== null; $i++) {
            $prev = $prev->next;
        }

        $target = $prev->next;
        if ($target === null) {
            return null;
        }

        if ($target === $this->tail) {
            $this->tail = $prev;
        }
        $prev->next = $target->next;
        $this->count--;

        return $target->value;
    }

    /**
     * Delete a node by zero-based position and return true on success, false if invalid.
     * Time Complexity: O(1) at head, O(n) arbitrary position
     */
    public function deleteAt(int $position): bool
    {
        if ($position < 0 || $position >= $this->count || $this->head === null) {
            return false;
        }
        $this->removeAt($position);
        return true;
    }

    /**
     * Traverse all nodes sequentially from head to tail.
     * If a callback is provided, invokes $callback($value, $index) on each node.
     *
     * Time Complexity: O(n) | Space Complexity: O(n)
     *
     * @param callable|null $callback Optional function(mixed $value, int $index): mixed
     * @return list<mixed>
     */
    public function traverse(?callable $callback = null): array
    {
        $items = [];
        $current = $this->head;
        $index = 0;
        while ($current !== null) {
            $items[] = $callback !== null ? $callback($current->value, $index) : $current->value;
            $current = $current->next;
            $index++;
        }
        return $items;
    }

    /**
     * Check if the linked list is empty.
     * Time Complexity: O(1)
     */
    public function isEmpty(): bool
    {
        return $this->head === null;
    }

    /**
     * Get the total number of nodes in the linked list.
     * Time Complexity: O(1)
     */
    public function size(): int
    {
        return $this->count;
    }

    /**
     * Return the head Node reference (for inspection/testing).
     * Time Complexity: O(1)
     */
    public function getHead(): ?Node
    {
        return $this->head;
    }

    /**
     * Return the tail Node reference (for inspection/testing).
     * Time Complexity: O(1)
     */
    public function getTail(): ?Node
    {
        return $this->tail;
    }

    /**
     * Reset and empty the linked list.
     * Time Complexity: O(1)
     */
    public function clear(): void
    {
        $this->head = null;
        $this->tail = null;
        $this->count = 0;
    }

    /**
     * Traverse the list from head to tail and return an array of values.
     * Time Complexity: O(n) | Space Complexity: O(n)
     *
     * @return list<mixed>
     */
    public function toArray(): array
    {
        return $this->traverse();
    }

    /**
     * Internal matching helper supporting callables, associative attributes,
     * record 'id' keys, and scalar equality.
     */
    private function nodeMatches(mixed $nodeValue, mixed $criteria, ?string $attribute = null): bool
    {
        if (is_callable($criteria)) {
            return (bool)$criteria($nodeValue);
        }
        if ($attribute !== null && is_array($nodeValue) && array_key_exists($attribute, $nodeValue)) {
            return $nodeValue[$attribute] == $criteria;
        }
        if (is_array($nodeValue) && isset($nodeValue['id']) && $nodeValue['id'] == $criteria) {
            return true;
        }
        return $nodeValue === $criteria;
    }
}
