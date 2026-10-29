<?php
/**
 * QuickSort Algorithm Implementation (divide and conquer, Lomuto partition)
 * Employee HRIS & Leave Management System
 *
 * Time:  Best/Average O(n log n), Worst O(n²) (pathological input)
 * Space: O(log n) average recursion depth; the recursion always descends into
 *        the smaller partition first to bound stack depth to O(log n).
 * Pivot: median-of-three (low, mid, high) to avoid the O(n²) trap on already
 *        sorted or reverse-sorted data, which is the common directory case.
 * Stable: no (partitioning swaps across equal keys)
 * Mutates input: no (sorts a re-indexed copy, returns a new array)
 *
 * Supports scalar arrays and associative records via $attribute, ascending or
 * descending, using the shared App\Dsa\Arrays\ArrayUtils comparison contract.
 */

namespace App\Dsa\Algorithms;

use App\Dsa\Arrays\ArrayUtils;

class QuickSort
{
    /**
     * @param array $arr Scalars, or associative records when $attribute is given
     * @param string|null $attribute Record key to sort on (null = sort scalars)
     * @param string $direction ArrayUtils::ASC or ArrayUtils::DESC
     * @return array A new sorted array, re-indexed from 0
     */
    public static function sort(array $arr, ?string $attribute = null, string $direction = ArrayUtils::ASC): array
    {
        $direction = ArrayUtils::normalizeDirection($direction);
        $items = ArrayUtils::toList($arr);
        $n = count($items);
        if ($n > 1) {
            self::quickSort($items, 0, $n - 1, $attribute, $direction);
        }
        return $items;
    }

    /**
     * Iterative-friendly recursion: recurse into the smaller side, loop on the
     * larger side, keeping worst-case stack depth at O(log n).
     *
     * @param list<mixed> $items Passed by reference and sorted in place
     */
    private static function quickSort(array &$items, int $low, int $high, ?string $attribute, string $direction): void
    {
        while ($low < $high) {
            $p = self::partition($items, $low, $high, $attribute, $direction);

            if (($p - $low) < ($high - $p)) {
                self::quickSort($items, $low, $p - 1, $attribute, $direction);
                $low = $p + 1;
            } else {
                self::quickSort($items, $p + 1, $high, $attribute, $direction);
                $high = $p - 1;
            }
        }
    }

    /**
     * Lomuto partition around a median-of-three pivot.
     *
     * @param list<mixed> $items
     * @return int Final index of the pivot
     */
    private static function partition(array &$items, int $low, int $high, ?string $attribute, string $direction): int
    {
        $mid = $low + intdiv($high - $low, 2);
        $pivotIndex = self::medianOfThree($items, $low, $mid, $high, $attribute, $direction);

        // Move the chosen pivot to the end
        self::swap($items, $pivotIndex, $high);
        $pivot = $items[$high];

        $i = $low;
        for ($j = $low; $j < $high; $j++) {
            if (ArrayUtils::compare($items[$j], $pivot, $attribute, $direction) < 0) {
                self::swap($items, $i, $j);
                $i++;
            }
        }
        self::swap($items, $i, $high);
        return $i;
    }

    /**
     * @param list<mixed> $items
     * @return int Index whose value is the median of the three candidates
     */
    private static function medianOfThree(array &$items, int $a, int $b, int $c, ?string $attribute, string $direction): int
    {
        $ab = ArrayUtils::compare($items[$a], $items[$b], $attribute, $direction);
        $bc = ArrayUtils::compare($items[$b], $items[$c], $attribute, $direction);
        $ac = ArrayUtils::compare($items[$a], $items[$c], $attribute, $direction);

        if (($ab <= 0 && $bc <= 0) || ($ab >= 0 && $bc >= 0)) {
            return $b;
        }
        if (($ab <= 0 && $ac >= 0) || ($ab >= 0 && $ac <= 0)) {
            return $a;
        }
        return $c;
    }

    /**
     * @param list<mixed> $items
     */
    private static function swap(array &$items, int $x, int $y): void
    {
        if ($x === $y) {
            return;
        }
        $temp = $items[$x];
        $items[$x] = $items[$y];
        $items[$y] = $temp;
    }
}
