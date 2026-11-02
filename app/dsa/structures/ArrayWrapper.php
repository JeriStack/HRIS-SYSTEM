<?php

/**
 * Custom Dynamic Array Abstraction (ArrayWrapper)
 * Employee HRIS & Leave Management System
 *
 * Indexing & Complexity:
 * - Zero-based contiguous integer indexing [0 .. size - 1].
 * - Retrieval / Update by index (get, update, set): O(1) time, O(1) auxiliary space.
 * - Append at end (add, append): O(1) amortized time, O(1) auxiliary space.
 * - Insertion / Deletion at arbitrary index (insert, delete, removeAt): O(n) time due to
 *   manual element shifting to preserve contiguous 0..n-1 indices without array_splice().
 * - Traversal / Filtering / Linear Search (traverse, filter, search): O(n) time.
 * - Sorting (sort via custom MergeSort / QuickSort / BubbleSort / SelectionSort / InsertionSort):
 *   O(n log n) or O(n²) depending on algorithm.
 */

namespace App\Dsa\Structures;

use App\Dsa\Algorithms\BubbleSort;
use App\Dsa\Algorithms\InsertionSort;
use App\Dsa\Algorithms\LinearSearch;
use App\Dsa\Algorithms\MergeSort;
use App\Dsa\Algorithms\QuickSort;
use App\Dsa\Algorithms\SelectionSort;
use App\Dsa\Arrays\ArrayUtils;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, mixed>
 * @phpstan-consistent-constructor
 */
class ArrayWrapper implements Countable, IteratorAggregate
{
    /** @var array<int, mixed> Internal contiguous zero-indexed storage */
    protected array $items = [];

    /** @var int Explicit element count */
    protected int $length = 0;

    /**
     * Initialize the custom array structure from an optional iterable or array.
     *
     * @param array<mixed> $initialItems
     */
    public function __construct(array $initialItems = [])
    {
        foreach ($initialItems as $item) {
            $this->items[$this->length] = $item;
            $this->length++;
        }
    }

    /**
     * Append an element to the end of the array.
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function add(mixed $item): void
    {
        $this->items[$this->length] = $item;
        $this->length++;
    }

    /**
     * Alias for add() — append an element to the end of the collection.
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function append(mixed $item): void
    {
        $this->add($item);
    }

    /**
     * Insert an element at a specific zero-based index by shifting subsequent
     * elements one position to the right manually (no array_splice).
     * Supports both insert($index, $item) and legacy insert($item, $index).
     *
     * Time Complexity: O(n) | Space Complexity: O(1)
     *
     * @param mixed $indexOrItem Target index in [0 .. size] or item when called in legacy order
     * @param mixed $itemOrIndex Element to insert or target index when called in legacy order
     * @return bool True if inserted, false if index is out of bounds
     */
    public function insert(mixed $indexOrItem, mixed $itemOrIndex = null): bool
    {
        if (func_num_args() === 1) {
            $this->add($indexOrItem);
            return true;
        }

        if (
            is_int($itemOrIndex)
            && $itemOrIndex >= 0
            && $itemOrIndex <= $this->length
            && (!is_int($indexOrItem) || $indexOrItem < 0 || $indexOrItem > $this->length)
        ) {
            $index = $itemOrIndex;
            $item = $indexOrItem;
        } elseif (is_int($indexOrItem)) {
            $index = $indexOrItem;
            $item = $itemOrIndex;
        } else {
            return false;
        }

        if ($index < 0 || $index > $this->length) {
            return false;
        }

        // Shift elements [index .. length - 1] one slot to the right
        for ($i = $this->length; $i > $index; $i--) {
            $this->items[$i] = $this->items[$i - 1];
        }

        $this->items[$index] = $item;
        $this->length++;
        return true;
    }

    /**
     * Retrieve the element at the specified zero-based index.
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function get(int $index): mixed
    {
        if ($index < 0 || $index >= $this->length) {
            return null;
        }
        return $this->items[$index];
    }

    /**
     * Update an existing element at the specified zero-based index.
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function update(int $index, mixed $item): bool
    {
        if ($index < 0 || $index >= $this->length) {
            return false;
        }
        $this->items[$index] = $item;
        return true;
    }

    /**
     * Alias for update() — set the value at an existing index.
     * Time Complexity: O(1) | Space Complexity: O(1)
     */
    public function set(int $index, mixed $item): bool
    {
        return $this->update($index, $item);
    }

    /**
     * Delete the element at a specific zero-based index by manually shifting
     * subsequent elements one position to the left (no array_splice).
     *
     * Time Complexity: O(n) | Space Complexity: O(1)
     */
    public function delete(int $index): bool
    {
        if ($index < 0 || $index >= $this->length) {
            return false;
        }

        for ($i = $index; $i < $this->length - 1; $i++) {
            $this->items[$i] = $this->items[$i + 1];
        }

        unset($this->items[$this->length - 1]);
        $this->length--;
        return true;
    }

    /**
     * Remove and return the element at the specified index, or null if out of bounds.
     * Time Complexity: O(n) | Space Complexity: O(1)
     */
    public function removeAt(int $index): mixed
    {
        if ($index < 0 || $index >= $this->length) {
            return null;
        }
        $removed = $this->items[$index];
        $this->delete($index);
        return $removed;
    }

    /**
     * Traverse all elements in sequential index order [0 .. size - 1].
     * If a callback is provided, invokes $callback($item, $index) for each element.
     *
     * Time Complexity: O(n) | Space Complexity: O(n)
     *
     * @param callable|null $callback Optional function(mixed $item, int $index): mixed
     * @return array<int, mixed>
     */
    public function traverse(?callable $callback = null): array
    {
        $result = [];
        for ($i = 0; $i < $this->length; $i++) {
            $result[] = $callback !== null ? $callback($this->items[$i], $i) : $this->items[$i];
        }
        return $result;
    }

    /**
     * Search for the first index matching $target using custom LinearSearch.
     * Supports scalar values or associative employee records via $attribute.
     *
     * Time Complexity: O(n) | Space Complexity: O(1)
     *
     * @return int|false Zero-based index of the first match, or false if not found
     */
    public function search(mixed $target, ?string $attribute = null): int|false
    {
        return LinearSearch::search($this->toArray(), $target, $attribute);
    }

    /**
     * Find and return the first matching element (or null if absent).
     * Accepts either a predicate callable or a target value (with optional $attribute).
     *
     * Time Complexity: O(n) | Space Complexity: O(1)
     */
    public function find(mixed $criteria, ?string $attribute = null): mixed
    {
        if (is_callable($criteria)) {
            for ($i = 0; $i < $this->length; $i++) {
                if ($criteria($this->items[$i], $i)) {
                    return $this->items[$i];
                }
            }
            return null;
        }

        $idx = $this->search($criteria, $attribute);
        return $idx !== false ? $this->items[$idx] : null;
    }

    /**
     * Filter elements using a predicate callable and return a new instance.
     * Does not mutate the current collection.
     *
     * Time Complexity: O(n) | Space Complexity: O(k) where k is matching count
     *
     * @param callable $predicate function(mixed $item, int $index): bool
     * @return static
     */
    public function filter(callable $predicate): static
    {
        $filtered = [];
        for ($i = 0; $i < $this->length; $i++) {
            if ($predicate($this->items[$i], $i)) {
                $filtered[] = $this->items[$i];
            }
        }
        return new static($filtered);
    }

    /**
     * Return a new sorted instance using one of the custom sorting algorithms
     * ('merge', 'quick', 'insertion', 'selection', 'bubble').
     * Does not mutate the current collection.
     *
     * Time Complexity: O(n log n) for merge/quick, O(n²) for insertion/selection/bubble
     *
     * @return static
     */
    public function sort(
        string $algorithm = 'merge',
        ?string $attribute = null,
        string $direction = ArrayUtils::ASC
    ): static {
        $raw = $this->toArray();
        $algo = strtolower(trim($algorithm));

        $sorted = match ($algo) {
            'bubble'    => BubbleSort::sort($raw, $attribute, $direction),
            'selection' => SelectionSort::sort($raw, $attribute, $direction),
            'insertion' => InsertionSort::sort($raw, $attribute, $direction),
            'quick'     => QuickSort::sort($raw, $attribute, $direction),
            default     => MergeSort::sort($raw, $attribute, $direction),
        };

        return new static($sorted);
    }

    /**
     * Check if the array is empty.
     * Time Complexity: O(1)
     */
    public function isEmpty(): bool
    {
        return $this->length === 0;
    }

    /**
     * Return the number of elements in the array.
     * Time Complexity: O(1)
     */
    public function size(): int
    {
        return $this->length;
    }

    /**
     * Countable interface implementation.
     * Time Complexity: O(1)
     */
    public function count(): int
    {
        return $this->length;
    }

    /**
     * Clear all elements from the structure.
     * Time Complexity: O(1)
     */
    public function clear(): void
    {
        $this->items = [];
        $this->length = 0;
    }

    /**
     * Export a clean zero-indexed copy of the internal array.
     * Time Complexity: O(n)
     *
     * @return list<mixed>
     */
    public function toArray(): array
    {
        $out = [];
        for ($i = 0; $i < $this->length; $i++) {
            $out[] = $this->items[$i];
        }
        return $out;
    }

    /**
     * IteratorAggregate implementation for foreach traversal.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }
}
