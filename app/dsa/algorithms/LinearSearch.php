<?php
/**
 * Linear Search Algorithm Implementation
 * Employee HRIS & Leave Management System
 *
 * Time:  Best O(1), Average/Worst O(n)
 * Space: O(1) for search(); O(k) for searchAll() where k = number of matches
 * Requires sorted input: no
 *
 * Works on unsorted data and associative records via $attribute, using the
 * shared App\Dsa\Arrays\ArrayUtils matching contract (numeric fields match
 * numerically, text fields match case-insensitively).
 */

namespace App\Dsa\Algorithms;

use App\Dsa\Arrays\ArrayUtils;

class LinearSearch
{
    /**
     * Return the index of the FIRST matching element, or false if none.
     *
     * @param array $arr Scalars, or associative records when $attribute is given
     * @param mixed $target Value to match
     * @param string|null $attribute Record key to match on (null = match the scalar)
     * @return int|false Zero-based index into the re-indexed array, or false
     */
    public static function search(array $arr, mixed $target, ?string $attribute = null): int|false
    {
        $items = ArrayUtils::toList($arr);
        $n = count($items);
        for ($i = 0; $i < $n; $i++) {
            if (ArrayUtils::valuesMatch(ArrayUtils::valueOf($items[$i], $attribute), $target)) {
                return $i;
            }
        }
        return false;
    }

    /**
     * Return EVERY matching record (not indexes), preserving input order.
     * Useful for multi-match directory searches with duplicate field values.
     *
     * @param array $arr Associative records (or scalars when $attribute is null)
     * @return list<mixed> Matching elements; empty array when none match
     */
    public static function searchAll(array $arr, mixed $target, ?string $attribute = null): array
    {
        $items = ArrayUtils::toList($arr);
        $matches = [];
        foreach ($items as $item) {
            if (ArrayUtils::valuesMatch(ArrayUtils::valueOf($item, $attribute), $target)) {
                $matches[] = $item;
            }
        }
        return $matches;
    }

    /**
     * Return every record whose $attribute CONTAINS $needle (case-insensitive
     * substring). This is the partial-match variant the directory uses for
     * free-text queries; it never uses array_search()/in_array().
     *
     * @return list<mixed>
     */
    public static function searchContains(array $arr, string $needle, ?string $attribute = null): array
    {
        $items = ArrayUtils::toList($arr);
        $needleNorm = mb_strtolower(trim($needle));
        if ($needleNorm === '') {
            return $items;
        }

        $matches = [];
        foreach ($items as $item) {
            $value = ArrayUtils::valueOf($item, $attribute);
            if ($value === null) {
                continue;
            }
            if (mb_strpos(mb_strtolower((string)$value), $needleNorm) !== false) {
                $matches[] = $item;
            }
        }
        return $matches;
    }
}
