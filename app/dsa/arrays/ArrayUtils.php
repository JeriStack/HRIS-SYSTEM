<?php
/**
 * Array Handling & Comparison Utilities for the custom DSA module.
 * Employee HRIS & Leave Management System — Phase 10
 *
 * This is the single comparison contract shared by every custom search and
 * sort algorithm so their ordering, direction, type, and null rules stay
 * identical. It deliberately uses only manual logic (no sort()/usort()/
 * array_search()) for the comparison itself.
 */

namespace App\Dsa\Arrays;

use InvalidArgumentException;

class ArrayUtils
{
    public const ASC = 'asc';
    public const DESC = 'desc';

    /**
     * Extract the comparison value for an element.
     *
     * @param mixed $element Scalar, or an associative record when $attribute is set
     * @param string|null $attribute Record key to compare on (null = compare the scalar itself)
     * @return mixed The value to compare, or null when the key/record is missing
     */
    public static function valueOf(mixed $element, ?string $attribute): mixed
    {
        if ($attribute === null) {
            return $element;
        }
        if (is_array($element) && array_key_exists($attribute, $element)) {
            return $element[$attribute];
        }
        return null;
    }

    /**
     * Compare two already-extracted values with a stable, documented contract:
     *   - null sorts last in ascending order (and first in descending).
     *   - If both values are numeric (int/float or numeric string), compare numerically
     *     so "EMP-2" does not sort after "EMP-10" when the field is purely numeric.
     *   - Otherwise compare as case-insensitive, locale-independent strings.
     * Employee identifiers are never coerced to a different type for the caller;
     * coercion happens only inside this comparison.
     *
     * @return int -1 if $a < $b, 0 if equal, 1 if $a > $b (before direction is applied)
     */
    public static function compareValues(mixed $a, mixed $b): int
    {
        $aNull = $a === null;
        $bNull = $b === null;
        if ($aNull || $bNull) {
            if ($aNull && $bNull) {
                return 0;
            }
            // Non-null values order before nulls in ascending order
            return $aNull ? 1 : -1;
        }

        if (self::isNumeric($a) && self::isNumeric($b)) {
            $an = $a + 0;
            $bn = $b + 0;
            return $an <=> $bn;
        }

        $as = self::normalizeString($a);
        $bs = self::normalizeString($b);
        return strcmp($as, $bs);
    }

    /**
     * Full element comparison: extract $attribute from each element, compare, then
     * apply the sort direction. All sort algorithms route every comparison here.
     *
     * @return int Negative/zero/positive respecting $direction
     */
    public static function compare(mixed $left, mixed $right, ?string $attribute, string $direction = self::ASC): int
    {
        $result = self::compareValues(
            self::valueOf($left, $attribute),
            self::valueOf($right, $attribute)
        );

        return self::normalizeDirection($direction) === self::DESC ? -$result : $result;
    }

    /**
     * Equality test used by the search algorithms. Mirrors compareValues():
     * numeric fields match numerically, text fields match case-insensitively.
     */
    public static function valuesMatch(mixed $candidate, mixed $target): bool
    {
        if ($candidate === null || $target === null) {
            return $candidate === $target;
        }
        if (self::isNumeric($candidate) && self::isNumeric($target)) {
            return ($candidate + 0) == ($target + 0);
        }
        return self::normalizeString($candidate) === self::normalizeString($target);
    }

    /**
     * Validate and lower-case a sort/search direction.
     *
     * @throws InvalidArgumentException on an unrecognized direction
     */
    public static function normalizeDirection(string $direction): string
    {
        $dir = strtolower(trim($direction));
        if ($dir === '' || $dir === self::ASC) {
            return self::ASC;
        }
        if ($dir === self::DESC) {
            return self::DESC;
        }
        throw new InvalidArgumentException("Invalid sort direction '{$direction}'. Use 'asc' or 'desc'.");
    }

    /**
     * Re-index an array to sequential 0..n-1 integer keys without reordering,
     * so sparse or string-keyed inputs are safe for index-based algorithms.
     * Records themselves are returned unchanged (by value).
     *
     * @return list<mixed>
     */
    public static function toList(array $arr): array
    {
        $list = [];
        foreach ($arr as $item) {
            $list[] = $item;
        }
        return $list;
    }

    /**
     * Assert that every element is an associative record containing $attribute.
     * Used by integrations that must not silently drop malformed rows.
     *
     * @throws InvalidArgumentException naming the first offending index
     */
    public static function assertRecordsHaveField(array $records, string $attribute): void
    {
        $index = 0;
        foreach ($records as $record) {
            if (!is_array($record) || !array_key_exists($attribute, $record)) {
                throw new InvalidArgumentException(
                    "Record at position {$index} is missing required field '{$attribute}'."
                );
            }
            $index++;
        }
    }

    /**
     * True when the dataset is ordered by $attribute under this comparator in the
     * given direction. Binary search uses this to refuse unverified input.
     */
    public static function isSortedBy(array $arr, ?string $attribute, string $direction = self::ASC): bool
    {
        $list = self::toList($arr);
        $count = count($list);
        for ($i = 1; $i < $count; $i++) {
            if (self::compare($list[$i - 1], $list[$i], $attribute, $direction) > 0) {
                return false;
            }
        }
        return true;
    }

    private static function isNumeric(mixed $value): bool
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
    }

    private static function normalizeString(mixed $value): string
    {
        return mb_strtolower(trim((string)$value));
    }
}
