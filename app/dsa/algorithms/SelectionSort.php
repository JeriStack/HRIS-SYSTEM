<?php
/**
 * SelectionSort Algorithm Implementation
 * Employee HRIS & Leave Management System
 *
 * Time:  Best/Average/Worst O(n²)
 * Space: O(1) auxiliary beyond the returned copy
 * Stable: no (long-distance swaps can reorder equal keys)
 * Mutates input: no (operates on a re-indexed copy, returns a new array)
 *
 * Supports scalar arrays and associative records via $attribute, ascending or
 * descending, using the shared App\Dsa\Arrays\ArrayUtils comparison contract.
 */

namespace App\Dsa\Algorithms;

use App\Dsa\Arrays\ArrayUtils;

class SelectionSort
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
            $targetIdx = $i;
            for ($j = $i + 1; $j < $n; $j++) {
                // Select the element that should come first under the active direction
                if (ArrayUtils::compare($items[$j], $items[$targetIdx], $attribute, $direction) < 0) {
                    $targetIdx = $j;
                }
            }

            if ($targetIdx !== $i) {
                $temp = $items[$i];
                $items[$i] = $items[$targetIdx];
                $items[$targetIdx] = $temp;
            }
        }

        return $items;
    }
}
