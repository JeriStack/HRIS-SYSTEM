<?php
/**
 * Instrumented Algorithm Implementations for Accurate Operation Counting
 * Replicates the exact algorithm logic from App\Dsa\Algorithms and App\Dsa\Structures
 * Employee HRIS & Leave Management System — Phase 15
 */

namespace App\Dsa\Benchmarks;

use App\Dsa\Arrays\ArrayUtils;
use App\Dsa\Structures\HashTable;

class InstrumentedAlgorithms
{
    // =========================================================================
    // SEARCH ALGORITHMS
    // =========================================================================

    public static function linearSearch(array $arr, mixed $target, ?string $attribute, OperationCounter $counter): int|false
    {
        $items = ArrayUtils::toList($arr);
        $n = count($items);

        for ($i = 0; $i < $n; $i++) {
            $counter->comparisons++;
            if (ArrayUtils::valuesMatch(ArrayUtils::valueOf($items[$i], $attribute), $target)) {
                return $i;
            }
        }

        return false;
    }

    public static function binarySearch(array $sortedArray, mixed $target, ?string $attribute, OperationCounter $counter, string $direction = ArrayUtils::ASC): int|false
    {
        $items = ArrayUtils::toList($sortedArray);
        $low = 0;
        $high = count($items) - 1;

        while ($low <= $high) {
            $mid = $low + intdiv($high - $low, 2);
            $midValue = ArrayUtils::valueOf($items[$mid], $attribute);

            $counter->comparisons++;
            if (ArrayUtils::valuesMatch($midValue, $target)) {
                return $mid;
            }

            // Direction-aware comparison
            $counter->comparisons++;
            $cmp = ArrayUtils::compareValues($midValue, $target, $direction);
            if ($cmp < 0) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return false;
    }

    public static function hashTableLookup(HashTable $table, string $key, OperationCounter $counter): mixed
    {
        // Use reflection or direct bucket access to instrument probe chain
        $index = $table->hash($key);
        
        $ref = new \ReflectionClass($table);
        $bucketsProp = $ref->getProperty('buckets');
        $buckets = $bucketsProp->getValue($table);

        $current = $buckets[$index] ?? null;

        while ($current !== null) {
            $counter->probes++;
            $counter->comparisons++;
            if ($current->key === $key) {
                return $current->value;
            }
            $current = $current->next;
        }

        $counter->probes++;
        return null;
    }

    // =========================================================================
    // SORTING ALGORITHMS
    // =========================================================================

    public static function bubbleSort(array $arr, ?string $attribute, OperationCounter $counter, string $direction = ArrayUtils::ASC): array
    {
        $direction = ArrayUtils::normalizeDirection($direction);
        $items = ArrayUtils::toList($arr);
        $n = count($items);

        for ($i = 0; $i < $n - 1; $i++) {
            $swapped = false;
            for ($j = 0; $j < $n - $i - 1; $j++) {
                $counter->comparisons++;
                if (ArrayUtils::compare($items[$j], $items[$j + 1], $attribute, $direction) > 0) {
                    $temp = $items[$j];
                    $items[$j] = $items[$j + 1];
                    $items[$j + 1] = $temp;
                    $counter->swaps++;
                    $swapped = true;
                }
            }
            if (!$swapped) {
                break;
            }
        }

        return $items;
    }

    public static function selectionSort(array $arr, ?string $attribute, OperationCounter $counter, string $direction = ArrayUtils::ASC): array
    {
        $direction = ArrayUtils::normalizeDirection($direction);
        $items = ArrayUtils::toList($arr);
        $n = count($items);

        for ($i = 0; $i < $n - 1; $i++) {
            $targetIdx = $i;
            for ($j = $i + 1; $j < $n; $j++) {
                $counter->comparisons++;
                if (ArrayUtils::compare($items[$j], $items[$targetIdx], $attribute, $direction) < 0) {
                    $targetIdx = $j;
                }
            }

            if ($targetIdx !== $i) {
                $temp = $items[$i];
                $items[$i] = $items[$targetIdx];
                $items[$targetIdx] = $temp;
                $counter->swaps++;
            }
        }

        return $items;
    }

    public static function insertionSort(array $arr, ?string $attribute, OperationCounter $counter, string $direction = ArrayUtils::ASC): array
    {
        $direction = ArrayUtils::normalizeDirection($direction);
        $items = ArrayUtils::toList($arr);
        $n = count($items);

        for ($i = 1; $i < $n; $i++) {
            $keyItem = $items[$i];
            $j = $i - 1;

            while ($j >= 0) {
                $counter->comparisons++;
                if (ArrayUtils::compare($items[$j], $keyItem, $attribute, $direction) > 0) {
                    $items[$j + 1] = $items[$j];
                    $counter->moves++;
                    $j--;
                } else {
                    break;
                }
            }
            $items[$j + 1] = $keyItem;
            $counter->moves++;
        }

        return $items;
    }

    public static function mergeSort(array $arr, ?string $attribute, OperationCounter $counter, string $direction = ArrayUtils::ASC): array
    {
        $direction = ArrayUtils::normalizeDirection($direction);
        $items = ArrayUtils::toList($arr);
        return self::mergeSortInternal($items, $attribute, $direction, $counter);
    }

    private static function mergeSortInternal(array $items, ?string $attribute, string $direction, OperationCounter $counter): array
    {
        $count = count($items);
        if ($count <= 1) {
            return $items;
        }

        $counter->enterRecursion();

        $mid = intdiv($count, 2);
        $left = [];
        for ($i = 0; $i < $mid; $i++) {
            $left[] = $items[$i];
            $counter->moves++;
        }
        $right = [];
        for ($i = $mid; $i < $count; $i++) {
            $right[] = $items[$i];
            $counter->moves++;
        }

        $sortedLeft  = self::mergeSortInternal($left, $attribute, $direction, $counter);
        $sortedRight = self::mergeSortInternal($right, $attribute, $direction, $counter);

        $merged = self::mergeBuffers($sortedLeft, $sortedRight, $attribute, $direction, $counter);

        $counter->leaveRecursion();
        return $merged;
    }

    private static function mergeBuffers(array $left, array $right, ?string $attribute, string $direction, OperationCounter $counter): array
    {
        $merged = [];
        $i = 0;
        $j = 0;
        $leftCount = count($left);
        $rightCount = count($right);

        while ($i < $leftCount && $j < $rightCount) {
            $counter->comparisons++;
            if (ArrayUtils::compare($left[$i], $right[$j], $attribute, $direction) <= 0) {
                $merged[] = $left[$i];
                $i++;
            } else {
                $merged[] = $right[$j];
                $j++;
            }
            $counter->moves++;
        }

        while ($i < $leftCount) {
            $merged[] = $left[$i];
            $i++;
            $counter->moves++;
        }

        while ($j < $rightCount) {
            $merged[] = $right[$j];
            $j++;
            $counter->moves++;
        }

        return $merged;
    }

    public static function quickSort(array $arr, ?string $attribute, OperationCounter $counter, string $direction = ArrayUtils::ASC): array
    {
        $direction = ArrayUtils::normalizeDirection($direction);
        $items = ArrayUtils::toList($arr);
        $n = count($items);
        if ($n > 1) {
            self::quickSortInternal($items, 0, $n - 1, $attribute, $direction, $counter);
        }
        return $items;
    }

    private static function quickSortInternal(array &$items, int $low, int $high, ?string $attribute, string $direction, OperationCounter $counter): void
    {
        while ($low < $high) {
            $counter->enterRecursion();
            $p = self::partition($items, $low, $high, $attribute, $direction, $counter);

            if (($p - $low) < ($high - $p)) {
                self::quickSortInternal($items, $low, $p - 1, $attribute, $direction, $counter);
                $low = $p + 1;
            } else {
                self::quickSortInternal($items, $p + 1, $high, $attribute, $direction, $counter);
                $high = $p - 1;
            }
            $counter->leaveRecursion();
        }
    }

    private static function partition(array &$items, int $low, int $high, ?string $attribute, string $direction, OperationCounter $counter): int
    {
        $counter->partitions++;
        $mid = $low + intdiv($high - $low, 2);
        $pivotIndex = self::medianOfThree($items, $low, $mid, $high, $attribute, $direction, $counter);

        // Move chosen pivot to end
        $temp = $items[$pivotIndex];
        $items[$pivotIndex] = $items[$high];
        $items[$high] = $temp;
        $counter->swaps++;

        $pivot = $items[$high];
        $i = $low;

        for ($j = $low; $j < $high; $j++) {
            $counter->comparisons++;
            if (ArrayUtils::compare($items[$j], $pivot, $attribute, $direction) < 0) {
                $temp = $items[$i];
                $items[$i] = $items[$j];
                $items[$j] = $temp;
                $counter->swaps++;
                $i++;
            }
        }

        $temp = $items[$i];
        $items[$i] = $items[$high];
        $items[$high] = $temp;
        $counter->swaps++;

        return $i;
    }

    private static function medianOfThree(array &$items, int $a, int $b, int $c, ?string $attribute, string $direction, OperationCounter $counter): int
    {
        $counter->comparisons += 3;
        $ab = ArrayUtils::compare($items[$a], $items[$b], $attribute, $direction);
        $bc = ArrayUtils::compare($items[$b], $items[$c], $attribute, $direction);
        $ac = ArrayUtils::compare($items[$a], $items[$c], $attribute, $direction);

        if (($ab <= 0 && $bc <= 0) || ($ab >= 0 && $bc >= 0)) {
            return $b;
        }
        if (($ab <= 0 && $ac >= 0) || ($ab >= 0 && $ac <= 0)) {
            return $a;
        }
        return $c;
    }
}

