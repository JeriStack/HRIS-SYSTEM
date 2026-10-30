<?php
/**
 * Deterministic Synthetic Dataset Generator for DSA Benchmarks
 * Employee HRIS & Leave Management System — Phase 15
 */

namespace App\Dsa\Benchmarks;

use App\Dsa\Arrays\ArrayUtils;

class DatasetGenerator
{
    public const SIZES = [
        'DS-010'   => 10,
        'DS-100'   => 100,
        'DS-500'   => 500,
        'DS-1000'  => 1000,
        'DS-5000'  => 5000,
        'DS-10000' => 10000,
    ];

    private static array $firstNames = [
        'Marcus', 'Eleanor', 'Sofia', 'David', 'Maria', 'James', 'Patricia', 'John', 'Jennifer',
        'Robert', 'Linda', 'Michael', 'Elizabeth', 'William', 'Barbara', 'Richard', 'Susan',
        'Joseph', 'Jessica', 'Thomas', 'Sarah', 'Charles', 'Karen', 'Christopher', 'Nancy',
        'Daniel', 'Lisa', 'Matthew', 'Betty', 'Anthony'
    ];

    private static array $lastNames = [
        'Tan', 'Vance', 'Reyes', 'Cruz', 'Santos', 'Smith', 'Johnson', 'Williams', 'Brown',
        'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez',
        'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
        'Lee', 'Perez', 'Thompson', 'White', 'Harris'
    ];

    private int $seed;

    public function __construct(int $seed = 42)
    {
        $this->seed = $seed;
    }

    /**
     * Generate synthetic employee records for a given count and arrangement.
     *
     * @param int $count Number of records to generate
     * @param string $arrangement 'random', 'sorted', 'reverse_sorted', 'nearly_sorted', 'duplicate_heavy'
     * @param string $sortAttribute Attribute used for sorting arrangements ('employee_no', 'id', 'salary')
     * @return list<array<string, mixed>>
     */
    public function generate(int $count, string $arrangement = 'random', string $sortAttribute = 'employee_no'): array
    {
        mt_srand($this->seed + $count);

        $records = [];
        $firstNameCount = count(self::$firstNames);
        $lastNameCount  = count(self::$lastNames);

        for ($i = 1; $i <= $count; $i++) {
            $fn = self::$firstNames[($i - 1) % $firstNameCount];
            $ln = self::$lastNames[(intdiv($i - 1, $firstNameCount)) % $lastNameCount];
            
            // Format employee_no with zero padding matching size magnitude (e.g. EMP000001)
            $empNo = sprintf('EMP%06d', $i);
            $deptId = ($arrangement === 'duplicate_heavy') ? (($i % 4) + 1) : (($i % 8) + 1);
            $posId  = ($i % 15) + 1;
            $salary = 30000 + (($i * 37) % 90000);

            $records[] = [
                'id'                => $i,
                'employee_no'       => $empNo,
                'first_name'        => $fn,
                'last_name'         => $ln,
                'name'              => "{$fn} {$ln}",
                'email'             => strtolower("{$fn}.{$ln}.{$i}@company.local"),
                'department_id'     => $deptId,
                'position_id'       => $posId,
                'salary'            => $salary,
                'employment_status' => ($i % 12 === 0) ? 'Inactive' : 'Active',
            ];
        }

        return $this->applyArrangement($records, $arrangement, $sortAttribute);
    }

    /**
     * Apply target arrangement to records.
     */
    public function applyArrangement(array $records, string $arrangement, string $sortAttribute = 'employee_no'): array
    {
        $n = count($records);
        if ($n <= 1) {
            return $records;
        }

        switch ($arrangement) {
            case 'sorted':
                usort($records, function ($a, $b) use ($sortAttribute) {
                    return ArrayUtils::compare($a, $b, $sortAttribute, ArrayUtils::ASC);
                });
                return array_values($records);

            case 'reverse_sorted':
                usort($records, function ($a, $b) use ($sortAttribute) {
                    return ArrayUtils::compare($a, $b, $sortAttribute, ArrayUtils::DESC);
                });
                return array_values($records);

            case 'nearly_sorted':
                // Sort ascending first
                usort($records, function ($a, $b) use ($sortAttribute) {
                    return ArrayUtils::compare($a, $b, $sortAttribute, ArrayUtils::ASC);
                });
                $records = array_values($records);

                // Perform ~5% random neighbor or small-distance swaps
                $swapCount = max(1, (int)round($n * 0.05));
                for ($k = 0; $k < $swapCount; $k++) {
                    $i = mt_rand(0, $n - 2);
                    $j = min($n - 1, $i + mt_rand(1, 3));
                    $tmp = $records[$i];
                    $records[$i] = $records[$j];
                    $records[$j] = $tmp;
                }
                return $records;

            case 'duplicate_heavy':
                // Already synthesized with repetitive department_id and salary bands
                // Apply a shuffle
                $this->shuffleWithSeed($records);
                return $records;

            case 'random':
            default:
                $this->shuffleWithSeed($records);
                return $records;
        }
    }

    /**
     * Deterministic Fisher-Yates shuffle.
     */
    private function shuffleWithSeed(array &$items): void
    {
        $n = count($items);
        for ($i = $n - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            $tmp = $items[$i];
            $items[$i] = $items[$j];
            $items[$j] = $tmp;
        }
    }

    /**
     * Retrieve search targets for a given dataset and scenario.
     *
     * @param list<array<string, mixed>> $dataset
     * @param string $scenario 'beginning', 'middle', 'end', 'miss'
     * @param string $attribute
     * @return mixed Target value
     */
    public static function getTarget(array $dataset, string $scenario, string $attribute = 'employee_no'): mixed
    {
        $n = count($dataset);
        if ($n === 0) {
            return ($attribute === 'employee_no') ? 'EMP999999' : -1;
        }

        return match ($scenario) {
            'beginning' => $dataset[0][$attribute] ?? null,
            'middle'    => $dataset[intdiv($n, 2)][$attribute] ?? null,
            'end'       => $dataset[$n - 1][$attribute] ?? null,
            'miss'      => ($attribute === 'employee_no') ? 'EMP999999_MISSING' : 999999999,
            default     => $dataset[0][$attribute] ?? null,
        };
    }
}

