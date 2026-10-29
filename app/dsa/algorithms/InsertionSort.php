<?php
/**
 * InsertionSort Algorithm Implementation
 * Employee HRIS & Leave Management System
 *
 * Time:  Best O(n) (already ordered), Average/Worst O(n²)
 * Space: O(1) auxiliary beyond the returned copy
 * Stable: yes (equal keys are not shifted past one another)
 * Mutates input: no (operates on a re-indexed copy, returns a new array)
 *
 * Supports scalar arrays and associative records via $attribute, ascending or
 * descending, using the shared App\Dsa\Arrays\ArrayUtils comparison contract.
 */

namespace App\Dsa\Algorithms;

use App\Dsa\Arrays\ArrayUtils;

class InsertionSort
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

        for ($i = 1; $i < $n; $i++) {
            $keyItem = $items[$i];
            $j = $i - 1;

            // Shift larger (per direction) elements one position to the right
            while ($j >= 0 && ArrayUtils::compare($items[$j], $keyItem, $attribute, $direction) > 0) {
                $items[$j + 1] = $items[$j];
                $j--;
            }
            $items[$j + 1] = $keyItem;
        }

        return $items;
    }
}
