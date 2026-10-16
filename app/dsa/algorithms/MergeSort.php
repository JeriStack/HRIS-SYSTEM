<?php
/**
 * MergeSort Algorithm Implementation (Stable O(n log n))
 * Employee HRIS & Leave Management System
 */

namespace App\Dsa\Algorithms;

class MergeSort
{
    public static function sort(array $arr): array
    {
        $count = count($arr);
        if ($count <= 1) {
            return $arr;
        }

        $mid = (int)($count / 2);
        $left = array_slice($arr, 0, $mid);
        $right = array_slice($arr, $mid);

        return self::merge(self::sort($left), self::sort($right));
    }

    private static function merge(array $left, array $right): array
    {
        $result = [];
        $i = 0;
        $j = 0;
        $leftLen = count($left);
        $rightLen = count($right);

        while ($i < $leftLen && $j < $rightLen) {
            if ($left[$i] <= $right[$j]) {
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
