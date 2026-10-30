<?php
/**
 * Employee Directory DSA Integration Service
 * Employee HRIS & Leave Management System — Phase 10
 *
 * Bridges the authorized employee dataset to the custom DSA search/sort
 * algorithms. All field, direction, and algorithm choices pass through explicit
 * server-side allowlists; client input never names a class or method.
 *
 * Dataset safety: application-level DSA runs only on a bounded, pre-filtered
 * dataset (see MAX_DSA_DATASET). Beyond that cap the directory falls back to
 * SQL ordering so a large workforce cannot exhaust memory or CPU in PHP.
 */

namespace App\Services;

use App\Dsa\Algorithms\BinarySearch;
use App\Dsa\Algorithms\BubbleSort;
use App\Dsa\Algorithms\InsertionSort;
use App\Dsa\Algorithms\LinearSearch;
use App\Dsa\Algorithms\MergeSort;
use App\Dsa\Algorithms\QuickSort;
use App\Dsa\Algorithms\SelectionSort;
use App\Dsa\Arrays\ArrayUtils;

class EmployeeDirectoryService
{
    /**
     * Maximum number of rows that will be processed by in-PHP DSA. This keeps
     * the O(n²) sorts and in-memory copies bounded. The seeded HRIS workforce
     * is far below this; a larger deployment should rely on SQL ordering.
     */
    public const MAX_DSA_DATASET = 2000;

    /**
     * Sortable fields -> the record key the algorithms compare on.
     * Only fields the HR directory already exposes are listed.
     *
     * @var array<string,string>
     */
    private const SORT_FIELDS = [
        'employee_no'       => 'employee_no',
        'first_name'        => 'first_name',
        'last_name'         => 'last_name',
        'department'        => 'department_name',
        'position'          => 'position_name',
        'employment_status' => 'employment_status',
        'date_hired'        => 'date_hired',
    ];

    /**
     * Searchable fields for single-field custom search.
     *
     * @var array<string,string>
     */
    private const SEARCH_FIELDS = [
        'employee_no' => 'employee_no',
        'first_name'  => 'first_name',
        'last_name'   => 'last_name',
        'email'       => 'email',
        'department'  => 'department_name',
    ];

    /**
     * Selector value -> [class, humanLabel]. The client sends only the key;
     * the class is resolved here, never instantiated from client input.
     *
     * @var array<string,array{0:class-string,1:string}>
     */
    private const SORT_ALGORITHMS = [
        'bubble'    => [BubbleSort::class, 'Bubble Sort'],
        'selection' => [SelectionSort::class, 'Selection Sort'],
        'insertion' => [InsertionSort::class, 'Insertion Sort'],
        'merge'     => [MergeSort::class, 'Merge Sort'],
        'quick'     => [QuickSort::class, 'Quick Sort'],
    ];

    private const SEARCH_ALGORITHMS = [
        'linear' => 'Linear Search',
        'binary' => 'Binary Search',
    ];

    /** @return array<string,string> */
    public static function sortFields(): array
    {
        return self::SORT_FIELDS;
    }

    /** @return array<string,string> */
    public static function searchFields(): array
    {
        return self::SEARCH_FIELDS;
    }

    /** @return array<string,string> selector => label */
    public static function sortAlgorithmChoices(): array
    {
        $out = [];
        foreach (self::SORT_ALGORITHMS as $key => [$class, $label]) {
            $out[$key] = $label;
        }
        return $out;
    }

    /** @return array<string,string> selector => label */
    public static function searchAlgorithmChoices(): array
    {
        return self::SEARCH_ALGORITHMS;
    }

    public static function isValidSortField(string $field): bool
    {
        return array_key_exists($field, self::SORT_FIELDS);
    }

    public static function isValidSortAlgorithm(string $algo): bool
    {
        return array_key_exists($algo, self::SORT_ALGORITHMS);
    }

    public static function isValidSearchField(string $field): bool
    {
        return array_key_exists($field, self::SEARCH_FIELDS);
    }

    public static function isValidSearchAlgorithm(string $algo): bool
    {
        return array_key_exists($algo, self::SEARCH_ALGORITHMS);
    }

    /**
     * Sort a dataset using the selected custom algorithm.
     *
     * @param array $employees Authorized employee records (already filtered)
     * @param string $field Allowlisted sort field key
     * @param string $direction 'asc' or 'desc'
     * @param string $algorithm Allowlisted algorithm selector
     * @return array{employees:array, meta:array} Sorted records plus run metadata
     */
    public function sort(array $employees, string $field, string $direction, string $algorithm): array
    {
        if (!self::isValidSortField($field)) {
            throw new \InvalidArgumentException("Unsupported sort field '{$field}'.");
        }
        if (!self::isValidSortAlgorithm($algorithm)) {
            throw new \InvalidArgumentException("Unsupported sort algorithm '{$algorithm}'.");
        }
        $direction = ArrayUtils::normalizeDirection($direction);

        $recordKey = self::SORT_FIELDS[$field];
        [$class, $label] = self::SORT_ALGORITHMS[$algorithm];

        $capped = $this->cap($employees);
        $start = microtime(true);
        /** @var callable $sorter */
        $sorter = [$class, 'sort'];
        $sorted = $sorter($capped['records'], $recordKey, $direction);
        $elapsedMs = round((microtime(true) - $start) * 1000, 4);

        return [
            'employees' => $sorted,
            'meta' => [
                'algorithm'      => $algorithm,
                'algorithm_name' => $label,
                'field'          => $field,
                'direction'      => $direction,
                'count'          => count($sorted),
                'elapsed_ms'     => $elapsedMs,
                'capped'         => $capped['capped'],
                'cap'            => self::MAX_DSA_DATASET,
            ],
        ];
    }

    /**
     * Search a dataset using the selected custom algorithm.
     *
     * Linear search scans the unsorted dataset directly (supports partial,
     * case-insensitive matching across the field). Binary search first orders
     * the dataset by the search field with a custom sort (documented
     * preprocessing cost) and then performs an exact O(log n) lookup.
     *
     * @return array{employees:array, meta:array}
     */
    public function search(array $employees, string $field, string $term, string $algorithm): array
    {
        if (!self::isValidSearchField($field)) {
            throw new \InvalidArgumentException("Unsupported search field '{$field}'.");
        }
        if (!self::isValidSearchAlgorithm($algorithm)) {
            throw new \InvalidArgumentException("Unsupported search algorithm '{$algorithm}'.");
        }

        $recordKey = self::SEARCH_FIELDS[$field];
        $term = trim($term);
        $capped = $this->cap($employees);
        $records = $capped['records'];

        $sortMs = null;
        $start = microtime(true);

        if ($algorithm === 'binary') {
            // Binary search requires ordered input: sort by the search field first.
            $sortStart = microtime(true);
            $ordered = MergeSort::sort($records, $recordKey, ArrayUtils::ASC);
            $sortMs = round((microtime(true) - $sortStart) * 1000, 4);

            $matches = [];
            $firstIdx = BinarySearch::searchFirst($ordered, $term, $recordKey, ArrayUtils::ASC);
            if ($firstIdx !== false) {
                // Collect contiguous exact matches (duplicates) from the sorted run
                $n = count($ordered);
                for ($i = $firstIdx; $i < $n; $i++) {
                    if (ArrayUtils::valuesMatch(ArrayUtils::valueOf($ordered[$i], $recordKey), $term)) {
                        $matches[] = $ordered[$i];
                    } else {
                        break;
                    }
                }
            }
        } else {
            // Linear search: partial, case-insensitive substring match.
            $matches = LinearSearch::searchContains($records, $term, $recordKey);
        }

        $elapsedMs = round((microtime(true) - $start) * 1000, 4);

        return [
            'employees' => $matches,
            'meta' => [
                'algorithm'      => $algorithm,
                'algorithm_name' => self::SEARCH_ALGORITHMS[$algorithm],
                'field'          => $field,
                'term'           => $term,
                'count'          => count($matches),
                'elapsed_ms'     => $elapsedMs,
                'sort_ms'        => $sortMs,
                'match_type'     => $algorithm === 'binary' ? 'exact' : 'partial',
                'capped'         => $capped['capped'],
                'cap'            => self::MAX_DSA_DATASET,
            ],
        ];
    }

    /**
     * Bound the dataset to MAX_DSA_DATASET rows for in-PHP processing.
     *
     * @return array{records:array, capped:bool}
     */
    private function cap(array $employees): array
    {
        $list = ArrayUtils::toList($employees);
        if (count($list) <= self::MAX_DSA_DATASET) {
            return ['records' => $list, 'capped' => false];
        }
        return ['records' => array_slice($list, 0, self::MAX_DSA_DATASET), 'capped' => true];
    }
}
