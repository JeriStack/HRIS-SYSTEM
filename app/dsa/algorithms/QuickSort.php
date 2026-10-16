<?php
/**
 * QuickSort Algorithm Implementation (O(n log n))
 * Employee HRIS & Leave Management System
 */

namespace App\Dsa\Algorithms;

class QuickSort
{
    public static function sort(array $arr): array
    {
        $count = count($arr);
        if ($count <= 1) {
            return $arr;
        }

        $pivot = $arr[0];
        $left = [];
        $right = [];

        for ($i = 1; $i < $count; $i++) {
            if ($arr[$i] < $pivot) {
                $left[] = $arr[$i];
            } else {
                $right[] = $arr[$i];
            }
        }

        return array_merge(self::sort($left), [$pivot], self::sort($right));
    }
}
