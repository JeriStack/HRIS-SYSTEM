<?php
/**
 * Hash Table Data Structure with Separate Chaining
 * Demonstrates polynomial rolling hash and linked collision resolution
 * Employee HRIS & Leave Management System
 */

namespace App\Dsa\Structures;

class HashNode
{
    public string $key;
    public mixed $value;
    public ?HashNode $next = null;

    public function __construct(string $key, mixed $value)
    {
        $this->key = $key;
        $this->value = $value;
    }
}

class HashTable
{
    private int $capacity;
    private array $buckets;
    private int $size = 0;

    public function __construct(int $capacity = 64)
    {
        $this->capacity = max(8, $capacity);
        $this->buckets = array_fill(0, $this->capacity, null);
    }

    /**
     * Polynomial rolling hash algorithm.
     */
    public function hash(string $key): int
    {
        $hash = 0;
        $len = strlen($key);
        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash * 31 + ord($key[$i])) % $this->capacity;
        }
        return $hash;
    }

    public function insert(string $key, mixed $value): void
    {
        $index = $this->hash($key);
        $current = $this->buckets[$index];

        // Search for existing key to update
        while ($current !== null) {
            if ($current->key === $key) {
                $current->value = $value;
                return;
            }
            $current = $current->next;
        }

        // Insert at beginning of bucket chain
        $newNode = new HashNode($key, $value);
        $newNode->next = $this->buckets[$index];
        $this->buckets[$index] = $newNode;
        $this->size++;
    }

    public function search(string $key): mixed
    {
        $index = $this->hash($key);
        $current = $this->buckets[$index];

        while ($current !== null) {
            if ($current->key === $key) {
                return $current->value;
            }
            $current = $current->next;
        }

        return null;
    }

    public function delete(string $key): bool
    {
        $index = $this->hash($key);
        $current = $this->buckets[$index];
        $prev = null;

        while ($current !== null) {
            if ($current->key === $key) {
                if ($prev === null) {
                    $this->buckets[$index] = $current->next;
                } else {
                    $prev->next = $current->next;
                }
                $this->size--;
                return true;
            }
            $prev = $current;
            $current = $current->next;
        }

        return false;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function isEmpty(): bool
    {
        return $this->size === 0;
    }

    public function toArray(): array
    {
        $result = [];
        for ($i = 0; $i < $this->capacity; $i++) {
            $current = $this->buckets[$i];
            while ($current !== null) {
                $result[$current->key] = $current->value;
                $current = $current->next;
            }
        }
        return $result;
    }
}
