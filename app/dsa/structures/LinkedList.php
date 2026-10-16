<?php
/**
 * Singly Linked List Data Structure
 * Used for sequential leave history logs and transaction chains
 * Employee HRIS & Leave Management System
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

    public function insertLast(mixed $data): void
    {
        $node = new ListNode($data);
        if ($this->head === null) {
            $this->head = $node;
            $this->tail = $node;
        } else {
            $this->tail->next = $node;
            $this->tail = $node;
        }
        $this->count++;
    }

    public function insertFirst(mixed $data): void
    {
        $node = new ListNode($data);
        if ($this->head === null) {
            $this->head = $node;
            $this->tail = $node;
        } else {
            $node->next = $this->head;
            $this->head = $node;
        }
        $this->count++;
    }

    public function delete(mixed $target): bool
    {
        if ($this->head === null) {
            return false;
        }

        if ($this->matches($this->head->data, $target)) {
            $this->head = $this->head->next;
            if ($this->head === null) {
                $this->tail = null;
            }
            $this->count--;
            return true;
        }

        $current = $this->head;
        while ($current->next !== null) {
            if ($this->matches($current->next->data, $target)) {
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

    public function search(mixed $target): mixed
    {
        $current = $this->head;
        while ($current !== null) {
            if ($this->matches($current->data, $target)) {
                return $current->data;
            }
            $current = $current->next;
        }
        return null;
    }

    public function size(): int
    {
        return $this->count;
    }

    public function isEmpty(): bool
    {
        return $this->head === null;
    }

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
