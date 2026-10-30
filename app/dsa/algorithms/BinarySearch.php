<?php
/**
 * Binary Search Algorithm Implementation
 * Employee HRIS & Leave Management System
 *
 * Time:  Best O(1), Average/Worst O(log n)
 * Space: O(1) (iterative)
 * Requires sorted input: YES — the array must already be ordered by the same
 *   $attribute and direction used here. Sorting an unordered dataset first adds
 *   an O(n log n) preprocessing cost, so binary search is not automatically
 *   faster end-to-end than a single linear scan.
 *
 * Duplicate policy: search() returns the index of ONE matching element (not
 * necessarily the first). Use searchFirst() when the earliest match is required.
 */

namespace App\Dsa\Algorithms;

use App\Dsa\Arrays\ArrayUtils;
use InvalidArgumentException;

class BinarySearch
{
    /**
     * Find the index of a matching element in a sorted array.
     *
     * @param array $sortedArray Must be ordered by $attribute in $direction
     * @param mixed $target Value to find
     * @param string|null $attribute Record key to compare (null = scalar array)
     * @param string $direction Ordering the input is sorted in (default asc)
     * @param bool $assertSorted When true, throws if the input is not ordered
     * @return int|false Index of a matching element, or false if absent
     * @throws InvalidArgumentException When $assertSorted is true and input is unsorted
     */
    public static function search(
        array $sortedArray,
        mixed $target,
        ?string $attribute = null,
        string $direction = ArrayUtils::ASC,
        bool $assertSorted = false
    ): int|false {
        $direction = ArrayUtils::normalizeDirection($direction);
        $items = ArrayUtils::toList($sortedArray);

        if ($assertSorted && !ArrayUtils::isSortedBy($items, $attribute, $direction)) {
            throw new InvalidArgumentException(
                'BinarySearch requires input ordered by the search key. Sort the dataset with the same attribute and direction first.'
            );
        }

        $low = 0;
        $high = count($items) - 1;

        while ($low <= $high) {
            $mid = $low + intdiv($high - $low, 2);
            $midValue = ArrayUtils::valueOf($items[$mid], $attribute);

            if (ArrayUtils::valuesMatch($midValue, $target)) {
                return $mid;
            }

            // Direction-aware narrowing: compare the midpoint element to a
            // synthetic element carrying the target value.
            $cmp = self::compareToTarget($items[$mid], $target, $attribute, $direction);
            if ($cmp < 0) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return false;
    }

    /**
     * Find the index of the FIRST matching element (lowest index) in a sorted
     * array when duplicate keys exist. Returns false when absent.
     */
    public static function searchFirst(
        array $sortedArray,
        mixed $target,
        ?string $attribute = null,
        string $direction = ArrayUtils::ASC,
        bool $assertSorted = false
    ): int|false {
        $direction = ArrayUtils::normalizeDirection($direction);
        $items = ArrayUtils::toList($sortedArray);

        if ($assertSorted && !ArrayUtils::isSortedBy($items, $attribute, $direction)) {
            throw new InvalidArgumentException(
                'BinarySearch requires input ordered by the search key. Sort the dataset with the same attribute and direction first.'
            );
        }

        $low = 0;
        $high = count($items) - 1;
        $found = false;

        while ($low <= $high) {
            $mid = $low + intdiv($high - $low, 2);
            $midValue = ArrayUtils::valueOf($items[$mid], $attribute);

            if (ArrayUtils::valuesMatch($midValue, $target)) {
                $found = $mid;
                // Keep searching the left half for an earlier duplicate
                $high = $mid - 1;
                continue;
            }

            if (self::compareToTarget($items[$mid], $target, $attribute, $direction) < 0) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $found;
    }

    /**
     * Compare an element's key against the target value, honoring direction.
     * Returns negative when the element should precede the target in the sorted
     * order, positive when it should follow, zero when equal.
     */
    private static function compareToTarget(mixed $element, mixed $target, ?string $attribute, string $direction): int
    {
        $base = ArrayUtils::compareValues(ArrayUtils::valueOf($element, $attribute), $target);
        return $direction === ArrayUtils::DESC ? -$base : $base;
    }
}
