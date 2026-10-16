<?php
/**
 * SelectionSort Algorithm Implementation (O(n²))
 * In-place comparison sort with minimum pass selection
 * Employee HRIS & Leave Management System
 */

namespace App\Dsa\Algorithms;

class SelectionSort
{
    /**
     * Sort an array in ascending order using selection sort.
     *
     * @param array $arr
     * @param string|null $attribute Optional array key for associative sorting
     * @return array Sorted array
     */
    public static function sort(array $arr, ?string $attribute = null): array
    {
        $n = count($arr);
        for ($i = 0; $i < $n - 1; $i++) {
            $minIdx = $i;
            $minVal = $attribute !== null ? ($arr[$minIdx][$attribute] ?? null) : $arr[$minIdx];

            for ($j = $i + 1; $j < $n; $j++) {
                $currVal = $attribute !== null ? ($arr[$j][$attribute] ?? null) : $arr[$j];
                if ($currVal < $minVal) {
                    $minIdx = $j;
                    $minVal = $currVal;
                }
            }

            if ($minIdx !== $i) {
                $temp = $arr[$i];
                $arr[$i] = $arr[$minIdx];
                $arr[$minIdx] = $temp;
            }
        }

        return $arr;
    }
}
