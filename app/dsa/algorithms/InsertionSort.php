<?php
/**
 * InsertionSort Algorithm Implementation (O(n²), Best O(n))
 * Efficient for small or nearly sorted collections
 * Employee HRIS & Leave Management System
 */

namespace App\Dsa\Algorithms;

class InsertionSort
{
    /**
     * Sort an array in ascending order using insertion sort.
     *
     * @param array $arr
     * @param string|null $attribute Optional array key for associative sorting
     * @return array Sorted array
     */
    public static function sort(array $arr, ?string $attribute = null): array
    {
        $n = count($arr);
        for ($i = 1; $i < $n; $i++) {
            $keyItem = $arr[$i];
            $keyVal = $attribute !== null ? ($keyItem[$attribute] ?? null) : $keyItem;
            $j = $i - 1;

            while ($j >= 0) {
                $currVal = $attribute !== null ? ($arr[$j][$attribute] ?? null) : $arr[$j];
                if ($currVal > $keyVal) {
                    $arr[$j + 1] = $arr[$j];
                    $j--;
                } else {
                    break;
                }
            }
            $arr[$j + 1] = $keyItem;
        }

        return $arr;
    }
}
