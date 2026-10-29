<?php
/**
 * Hash Table Data Structure with Separate Chaining
 * Demonstrates polynomial rolling hash and linked collision resolution
 * Employee HRIS & Leave Management System — Phase 11
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
    private const DEFAULT_CAPACITY = 32;
    private const MAX_LOAD_FACTOR = 0.75;

    private int $capacity;
    /** @var array<int, ?HashNode> */
    private array $buckets;
    private int $size = 0;

    public function __construct(int $capacity = self::DEFAULT_CAPACITY)
    {
        $this->capacity = max(8, $capacity);
        $this->buckets = array_fill(0, $this->capacity, null);
    }

    /**
     * Polynomial rolling hash algorithm: sum(c * 31^i) mod capacity.
     * O(k) where k is key length.
     */
    public function hash(string $key): int
    {
        $hash = 0;
        $len = strlen($key);
        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash * 31 + ord($key[$i])) % $this->capacity;
        }
        return abs($hash);
    }

    /**
     * Map a key to a value. Overwrites existing key or inserts a new bucket node.
     * Triggers dynamic rehashing if the load factor exceeds 0.75.
     * Average O(1), Worst O(n).
     */
    public function set(string $key, mixed $value): void
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

        // Check load factor and resize if needed
        if (($this->size / $this->capacity) > self::MAX_LOAD_FACTOR) {
            $this->resize($this->capacity * 2);
        }
    }

    /**
     * Backward-compatible alias for set().
     */
    public function insert(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Retrieve a value by key.
     * Returns null if key is absent or value is null. Use containsKey() to disambiguate.
     * Average O(1), Worst O(n).
     */
    public function get(string $key): mixed
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

    /**
     * Backward-compatible alias for get().
     */
    public function search(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Check whether a key exists in the hash table.
     * Accurately distinguishes a stored null from an absent key.
     * Average O(1), Worst O(n).
     */
    public function containsKey(string $key): bool
    {
        $index = $this->hash($key);
        $current = $this->buckets[$index];

        while ($current !== null) {
            if ($current->key === $key) {
                return true;
            }
            $current = $current->next;
        }

        return false;
    }

    /**
     * Remove a key and its associated value from the hash table.
     * Returns true if removed; false if the key was not found.
     * Average O(1), Worst O(n).
     */
    public function remove(string $key): bool
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

    /**
     * Backward-compatible alias for remove().
     */
    public function delete(string $key): bool
    {
        return $this->remove($key);
    }

    /**
     * Return the total count of key-value pairs stored in the table.
     * O(1) time complexity.
     */
    public function size(): int
    {
        return $this->size;
    }

    /**
     * Check whether the table contains no entries.
     * O(1) time complexity.
     */
    public function isEmpty(): bool
    {
        return $this->size === 0;
    }

    /**
     * Return the current internal bucket capacity.
     */
    public function getCapacity(): int
    {
        return $this->capacity;
    }

    /**
     * Clear all entries from the table.
     * O(capacity) reset.
     */
    public function clear(): void
    {
        $this->buckets = array_fill(0, $this->capacity, null);
        $this->size = 0;
    }

    /**
     * Return all key-value entries as an associative array.
     * O(n + capacity) time complexity.
     *
     * @return array<string, mixed>
     */
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

    /**
     * Resize bucket capacity and rehash all existing entries.
     * O(n + capacity) time complexity.
     */
    private function resize(int $newCapacity): void
    {
        $oldBuckets = $this->buckets;
        $oldCapacity = $this->capacity;

        $this->capacity = $newCapacity;
        $this->buckets = array_fill(0, $this->capacity, null);
        $this->size = 0;

        for ($i = 0; $i < $oldCapacity; $i++) {
            $current = $oldBuckets[$i];
            while ($current !== null) {
                $this->set($current->key, $current->value);
                $current = $current->next;
            }
        }
    }
}
