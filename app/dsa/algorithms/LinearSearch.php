<?php
/**
 * Linear Search Algorithm Implementation (O(n))
 * Sequential scan over unsorted records
 * Employee HRIS & Leave Management System
 */

namespace App\Dsa\Algorithms;

class LinearSearch
{
    /**
     * Search for a target value in an array sequentially.
     *
     * @param array $arr
     * @param mixed $target
     * @param string|null $attribute Object/array property or null for scalar values
     * @return int|false Index of found element or false
     */
    public static function search(array $arr, mixed $target, ?string $attribute = null): int|false
    {
        $n = count($arr);
        for ($i = 0; $i < $n; $i++) {
            $val = $attribute !== null ? ($arr[$i][$attribute] ?? null) : $arr[$i];
            if ($val === $target) {
                return $i;
            }
        }

        return false;
    }
}
