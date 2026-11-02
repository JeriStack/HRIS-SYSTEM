<?php
/**
 * Custom Hash Table Data Structure with Separate Chaining
 * Employee HRIS & Leave Management System
 *
 * Hash Function:
 * - Deterministic polynomial rolling hash with prime multiplier 31:
 *   h(k) = (sum_{i=0..L-1} (hash * 31 + ord(k[i])) mod 2^31-1) mod capacity
 *
 * Collision Resolution Strategy:
 * - Separate chaining using linked HashNode lists per bucket.
 * - Dynamic resizing & rehashing when load factor (size / capacity) >= 0.75.
 *
 * Duplicate Key Policy:
 * - Keys are unique. Setting an existing key updates its value in place without
 *   creating a duplicate node or increasing size().
 *
 * Complexity Summary:
 * - Insert (set/insert):   O(1) average, O(n) worst case (all keys hash to 1 bucket or rehash step)
 * - Lookup (get/search):   O(1) average, O(n) worst case
 * - Delete (remove/delete): O(1) average, O(n) worst case
 * - Key check (containsKey): O(1) average, O(n) worst case
 * - Space Complexity:      O(n + m) where n = stored pairs and m = bucket capacity
 */

namespace App\Dsa\Structures;

class HashNode
{
    public string $key;
    public mixed $value;
    public ?HashNode $next = null;

    public function __construct(string $key, mixed $value, ?HashNode $next = null)
    {
        $this->key = $key;
        $this->value = $value;
        $this->next = $next;
    }
}

class HashTable
{
    /** @var array<int, ?HashNode> Fixed-size array of bucket heads */
    private array $buckets;
    private int $capacity;
    private int $count = 0;
    private int $collisions = 0;
    private const LOAD_FACTOR_THRESHOLD = 0.75;

    public function __construct(int $capacity = 64)
    {
        $this->capacity = max(8, $capacity);
        $this->buckets = array_fill(0, $this->capacity, null);
    }

    /**
     * Compute deterministic bucket index for a key using a polynomial rolling hash
     * with prime factor 31.
     *
     * Time Complexity: O(L) where L is string key length (O(1) for bounded keys)
     */
    public function hash(string|int $key): int
    {
        $str = (string)$key;
        $len = strlen($str);
        $hash = 0;

        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash * 31 + ord($str[$i])) & 0x7FFFFFFF;
        }

        return $hash % $this->capacity;
    }

    /**
     * Insert or update a key-value pair in the Hash Table.
     * Time Complexity: O(1) average, O(n) worst case
     */
    public function set(string|int $key, mixed $value): void
    {
        if (($this->count / $this->capacity) >= self::LOAD_FACTOR_THRESHOLD) {
            $this->resize($this->capacity * 2);
        }

        $strKey = (string)$key;
        $index = $this->hash($strKey);
        $current = $this->buckets[$index];

        // Traverse bucket chain to check if key already exists
        while ($current !== null) {
            if ($current->key === $strKey) {
                $current->value = $value;
                return;
            }
            $current = $current->next;
        }

        // If bucket already had at least one node, record a collision event
        if ($this->buckets[$index] !== null) {
            $this->collisions++;
        }

        // Prepend new node to bucket chain
        $this->buckets[$index] = new HashNode($strKey, $value, $this->buckets[$index]);
        $this->count++;
    }

    /**
     * Alias for set() — insert or update a key-value pair.
     * Time Complexity: O(1) average, O(n) worst case
     */
    public function insert(string|int $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Retrieve a value by key in O(1) average time.
     * Returns null if key is not found.
     *
     * Time Complexity: O(1) average, O(n) worst case
     */
    public function get(string|int $key): mixed
    {
        $strKey = (string)$key;
        $index = $this->hash($strKey);
        $current = $this->buckets[$index];

        while ($current !== null) {
            if ($current->key === $strKey) {
                return $current->value;
            }
            $current = $current->next;
        }

        return null;
    }

    /**
     * Alias for get() — search for a value by key.
     * Time Complexity: O(1) average, O(n) worst case
     */
    public function search(string|int $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Check if a key exists in the Hash Table (distinguishes stored null values from absent keys).
     * Time Complexity: O(1) average, O(n) worst case
     */
    public function containsKey(string|int $key): bool
    {
        $strKey = (string)$key;
        $index = $this->hash($strKey);
        $current = $this->buckets[$index];

        while ($current !== null) {
            if ($current->key === $strKey) {
                return true;
            }
            $current = $current->next;
        }

        return false;
    }

    /**
     * Remove a key-value pair from the Hash Table.
     * Time Complexity: O(1) average, O(n) worst case
     */
    public function remove(string|int $key): bool
    {
        $strKey = (string)$key;
        $index = $this->hash($strKey);
        $current = $this->buckets[$index];
        $prev = null;

        while ($current !== null) {
            if ($current->key === $strKey) {
                if ($prev === null) {
                    $this->buckets[$index] = $current->next;
                } else {
                    $prev->next = $current->next;
                }
                $this->count--;
                return true;
            }
            $prev = $current;
            $current = $current->next;
        }

        return false;
    }

    /**
     * Alias for remove() — delete a key-value pair by key.
     * Time Complexity: O(1) average, O(n) worst case
     */
    public function delete(string|int $key): bool
    {
        return $this->remove($key);
    }

    /**
     * Dynamically resize the bucket array and rehash all existing entries.
     * Time Complexity: O(n + m)
     */
    private function resize(int $newCapacity): void
    {
        $oldBuckets = $this->buckets;
        $this->capacity = $newCapacity;
        $this->buckets = array_fill(0, $this->capacity, null);
        $this->count = 0;
        $this->collisions = 0;

        foreach ($oldBuckets as $head) {
            $current = $head;
            while ($current !== null) {
                $this->set($current->key, $current->value);
                $current = $current->next;
            }
        }
    }

    public function size(): int
    {
        return $this->count;
    }

    public function isEmpty(): bool
    {
        return $this->count === 0;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    /**
     * Return the number of bucket collisions observed in the current table state.
     */
    public function getCollisionCount(): int
    {
        return $this->collisions;
    }

    public function clear(): void
    {
        $this->buckets = array_fill(0, $this->capacity, null);
        $this->count = 0;
        $this->collisions = 0;
    }

    /**
     * Return all keys currently stored in the Hash Table.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = [];
        foreach ($this->buckets as $head) {
            $current = $head;
            while ($current !== null) {
                $keys[] = $current->key;
                $current = $current->next;
            }
        }
        return $keys;
    }

    /**
     * Return all values currently stored in the Hash Table.
     *
     * @return list<mixed>
     */
    public function values(): array
    {
        $values = [];
        foreach ($this->buckets as $head) {
            $current = $head;
            while ($current !== null) {
                $values[] = $current->value;
                $current = $current->next;
            }
        }
        return $values;
    }

    /**
     * Export all key-value pairs as an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->buckets as $head) {
            $current = $head;
            while ($current !== null) {
                $result[$current->key] = $current->value;
                $current = $current->next;
            }
        }
        return $result;
    }
}
