<?php
/**
 * BubbleSort Algorithm Implementation
 * Employee HRIS & Leave Management System
 *
 * Time:  Best O(n) (early exit on a clean pass), Average/Worst O(n²)
 * Space: O(1) auxiliary beyond the returned copy
 * Stable: yes (equal keys keep their input order; strict comparison, no swap on equal)
 * Mutates input: no (operates on a re-indexed copy, returns a new array)
 *
 * Supports scalar arrays and associative records via $attribute, ascending or
 * descending, using the shared App\Dsa\Arrays\ArrayUtils comparison contract.
 */

namespace App\Dsa\Algorithms;

use App\Dsa\Arrays\ArrayUtils;

class BubbleSort
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

        for ($i = 0; $i < $n - 1; $i++) {
            $swapped = false;
            for ($j = 0; $j < $n - $i - 1; $j++) {
                if (ArrayUtils::compare($items[$j], $items[$j + 1], $attribute, $direction) > 0) {
                    $temp = $items[$j];
                    $items[$j] = $items[$j + 1];
                    $items[$j + 1] = $temp;
                    $swapped = true;
                }
            }
            if (!$swapped) {
                break;
            }
        }

        return $items;
    }
}
