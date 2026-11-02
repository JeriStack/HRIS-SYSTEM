<?php
/**
 * MergeSort Algorithm Implementation (divide and conquer)
 * Employee HRIS & Leave Management System
 *
 * Time:  Best/Average/Worst O(n log n)
 * Space: O(n) auxiliary for the merge buffers, plus O(log n) recursion depth
 * Stable: yes (left subarray wins ties in merge)
 * Mutates input: no (returns a new array)
 *
 * Supports scalar arrays and associative records via $attribute, ascending or
 * descending, using the shared App\Dsa\Arrays\ArrayUtils comparison contract.
 * The merge step is hand-written; no built-in sort is used.
 */

namespace App\Dsa\Algorithms;

use App\Dsa\Arrays\ArrayUtils;

class MergeSort
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
        return self::sortList(ArrayUtils::toList($arr), $attribute, $direction);
    }

    /**
     * @param list<mixed> $items Already re-indexed list
     * @return list<mixed>
     */
    private static function sortList(array $items, ?string $attribute, string $direction): array
    {
        $count = count($items);
        if ($count <= 1) {
            return $items;
        }

        $mid = intdiv($count, 2);
        $left = [];
        for ($i = 0; $i < $mid; $i++) {
            $left[] = $items[$i];
        }
        $right = [];
        for ($i = $mid; $i < $count; $i++) {
            $right[] = $items[$i];
        }

        return self::merge(
            self::sortList($left, $attribute, $direction),
            self::sortList($right, $attribute, $direction),
            $attribute,
            $direction
        );
    }

    /**
     * Stable merge of two ordered lists under the shared comparator.
     *
     * @param list<mixed> $left
     * @param list<mixed> $right
     * @return list<mixed>
     */
    private static function merge(array $left, array $right, ?string $attribute, string $direction): array
    {
        $result = [];
        $i = 0;
        $j = 0;
        $leftLen = count($left);
        $rightLen = count($right);

        while ($i < $leftLen && $j < $rightLen) {
            // <= 0 keeps equal elements in left-then-right order (stability)
            if (ArrayUtils::compare($left[$i], $right[$j], $attribute, $direction) <= 0) {
                $result[] = $left[$i++];
            } else {
                $result[] = $right[$j++];
            }
        }

        while ($i < $leftLen) {
            $result[] = $left[$i++];
        }
        while ($j < $rightLen) {
            $result[] = $right[$j++];
        }

        return $result;
    }
}
