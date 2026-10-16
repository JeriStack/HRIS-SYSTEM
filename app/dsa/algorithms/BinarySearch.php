<?php
/**
 * Binary Search Algorithm Implementation (O(log n))
 * Precondition: Requires array to be sorted by search key
 * Employee HRIS & Leave Management System
 */

namespace App\Dsa\Algorithms;

class BinarySearch
{
    /**
     * Search for a target key or value in a sorted array.
     *
     * @param array $sortedArray
     * @param mixed $target
     * @param string|null $attribute Object/array property or null for scalar values
     * @return int|false Index of found element or false
     */
    public static function search(array $sortedArray, mixed $target, ?string $attribute = null): int|false
    {
        $low = 0;
        $high = count($sortedArray) - 1;

        while ($low <= $high) {
            $mid = $low + intdiv($high - $low, 2);
            $current = $attribute !== null ? ($sortedArray[$mid][$attribute] ?? null) : $sortedArray[$mid];

            if ($current === $target) {
                return $mid;
            }

            if ($current < $target) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return false;
    }
}
