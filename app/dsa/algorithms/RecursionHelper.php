<?php
/**
 * Recursion Helper & Mathematical Demonstrator
 * Demonstrates recursive execution frames, base cases, and call stacks
 * Employee HRIS & Leave Management System
 */

namespace App\Dsa\Algorithms;

class RecursionHelper
{
    /**
     * Calculate factorial of non-negative integer n recursively.
     */
    public static function factorial(int $n): int
    {
        if ($n <= 1) {
            return 1;
        }
        return $n * self::factorial($n - 1);
    }

    /**
     * Calculate nth Fibonacci number recursively.
     */
    public static function fibonacci(int $n): int
    {
        if ($n <= 0) {
            return 0;
        }
        if ($n === 1) {
            return 1;
        }
        return self::fibonacci($n - 1) + self::fibonacci($n - 2);
    }

    /**
     * Recursive Binary Search implementation.
     */
    public static function binarySearchRecursive(array $arr, mixed $target, int $low, int $high, ?string $attribute = null): int|false
    {
        if ($low > $high) {
            return false;
        }

        $mid = $low + intdiv($high - $low, 2);
        $val = $attribute !== null ? ($arr[$mid][$attribute] ?? null) : $arr[$mid];

        if ($val === $target) {
            return $mid;
        }

        if ($val < $target) {
            return self::binarySearchRecursive($arr, $target, $mid + 1, $high, $attribute);
        }

        return self::binarySearchRecursive($arr, $target, $low, $mid - 1, $attribute);
    }
}
