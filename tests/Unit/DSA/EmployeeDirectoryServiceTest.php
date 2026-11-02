<?php

namespace Tests\Unit\DSA;

use App\Dsa\Structures\ArrayStructure;
use App\Dsa\Structures\LinkedList;
use App\Dsa\Structures\Stack;
use App\Services\EmployeeDirectoryService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Integration-level tests for the Employee Directory DSA bridge: confirm the
 * custom algorithms are genuinely invoked, allowlists reject invalid input,
 * the binary-search pipeline sorts first, the dataset cap is enforced,
 * and dashboard views no longer display DSA modules.
 */
class EmployeeDirectoryServiceTest extends TestCase
{
    private EmployeeDirectoryService $service;

    protected function setUp(): void
    {
        $this->service = new EmployeeDirectoryService();
    }

    /** @return list<array<string,mixed>> */
    private function employees(): array
    {
        return [
            ['id' => 1, 'employee_no' => 'EMP-003', 'first_name' => 'Carol', 'last_name' => 'Zamora', 'department_name' => 'Finance', 'position_name' => 'Analyst', 'employment_status' => 'Active', 'date_hired' => '2021-03-01', 'email' => 'carol@x.test'],
            ['id' => 2, 'employee_no' => 'EMP-001', 'first_name' => 'Alice', 'last_name' => 'Alvarez', 'department_name' => 'Admin', 'position_name' => 'Clerk', 'employment_status' => 'Active', 'date_hired' => '2019-07-15', 'email' => 'alice@x.test'],
            ['id' => 3, 'employee_no' => 'EMP-002', 'first_name' => 'Bob', 'last_name' => 'Mendoza', 'department_name' => 'IT', 'position_name' => 'Engineer', 'employment_status' => 'Inactive', 'date_hired' => '2020-01-20', 'email' => 'bob@x.test'],
        ];
    }

    public function test_allowlists_are_exposed_for_the_ui(): void
    {
        $this->assertArrayHasKey('employee_no', EmployeeDirectoryService::sortFields());
        $this->assertArrayHasKey('quick', EmployeeDirectoryService::sortAlgorithmChoices());
        $this->assertArrayHasKey('linear', EmployeeDirectoryService::searchAlgorithmChoices());
        $this->assertArrayHasKey('binary', EmployeeDirectoryService::searchAlgorithmChoices());
    }

    public function test_sort_validation_helpers(): void
    {
        $this->assertTrue(EmployeeDirectoryService::isValidSortField('employee_no'));
        $this->assertFalse(EmployeeDirectoryService::isValidSortField('password'));
        $this->assertTrue(EmployeeDirectoryService::isValidSortAlgorithm('merge'));
        $this->assertFalse(EmployeeDirectoryService::isValidSortAlgorithm('system'));
    }

    public function test_wrap_collection_and_sequential_roster_and_reversible_stack(): void
    {
        $collection = $this->service->wrapCollection($this->employees());
        $this->assertInstanceOf(ArrayStructure::class, $collection);
        $this->assertSame(3, $collection->size());

        $activeOnly = $collection->filter(fn(array $e): bool => $e['employment_status'] === 'Active');
        $this->assertSame(2, $activeOnly->size());

        $roster = $this->service->buildSequentialRoster($this->employees());
        $this->assertInstanceOf(LinkedList::class, $roster);
        $this->assertSame(3, $roster->size());
        $found = $roster->find(2);
        $this->assertIsArray($found);
        $this->assertSame('EMP-001', $found['employee_no']);

        $opStack = new Stack();
        $this->service->recordReversibleOperation(
            $opStack,
            'DEACTIVATE_EMPLOYEE',
            ['id' => 1, 'employment_status' => 'Active'],
            ['id' => 1, 'employment_status' => 'Inactive']
        );
        $this->assertSame(1, $opStack->size());
        $lastOp = $opStack->peek();
        $this->assertIsArray($lastOp);
        $this->assertSame('DEACTIVATE_EMPLOYEE', $lastOp['operation']);
    }

    public function test_hr_and_employee_dashboards_and_sidebar_do_not_expose_dsa_module(): void
    {
        $root = dirname(__DIR__, 3);
        $filesToCheck = [
            $root . '/app/views/partials/sidebar.php',
            $root . '/app/views/dashboard/hr.php',
            $root . '/app/views/dashboard/employee.php',
            $root . '/app/views/dashboard/admin.php',
            $root . '/app/views/dashboard/employee_requests.php',
            $root . '/app/views/activity_logs/index.php',
        ];

        foreach ($filesToCheck as $file) {
            $this->assertFileExists($file);
            $contents = (string)file_get_contents($file);
            $this->assertStringNotContainsString('DSA Laboratory', $contents, "DSA Laboratory must be removed from {$file}");
            $this->assertStringNotContainsString("url('/dsa')", $contents, "Direct /dsa dashboard link must be removed from {$file}");
        }
    }

    #[DataProvider('algoProvider')]
    public function test_sort_by_employee_no_ascending_for_each_algorithm(string $algo): void
    {
        $result = $this->service->sort($this->employees(), 'employee_no', 'asc', $algo);
        $this->assertSame(
            ['EMP-001', 'EMP-002', 'EMP-003'],
            array_column($result['employees'], 'employee_no')
        );
        $this->assertSame($algo, $result['meta']['algorithm']);
        $this->assertSame('employee_no', $result['meta']['field']);
        $this->assertSame(3, $result['meta']['count']);
        $this->assertFalse($result['meta']['capped']);
    }

    /** @return array<string,array{0:string}> */
    public static function algoProvider(): array
    {
        return [
            'bubble' => ['bubble'],
            'selection' => ['selection'],
            'insertion' => ['insertion'],
            'merge' => ['merge'],
            'quick' => ['quick'],
        ];
    }

    public function test_sort_descending_by_last_name(): void
    {
        $result = $this->service->sort($this->employees(), 'last_name', 'desc', 'quick');
        $this->assertSame(
            ['Zamora', 'Mendoza', 'Alvarez'],
            array_column($result['employees'], 'last_name')
        );
        $this->assertSame('desc', $result['meta']['direction']);
    }

    public function test_sort_by_department_name_mapped_field(): void
    {
        $result = $this->service->sort($this->employees(), 'department', 'asc', 'merge');
        $this->assertSame(
            ['Admin', 'Finance', 'IT'],
            array_column($result['employees'], 'department_name')
        );
    }

    public function test_sort_rejects_invalid_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->sort($this->employees(), 'salary', 'asc', 'quick');
    }

    public function test_sort_rejects_invalid_algorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->sort($this->employees(), 'employee_no', 'asc', 'system_exec');
    }

    public function test_sort_rejects_invalid_direction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->sort($this->employees(), 'employee_no', 'sideways', 'quick');
    }

    public function test_linear_search_partial_case_insensitive(): void
    {
        $result = $this->service->search($this->employees(), 'last_name', 'alv', 'linear');
        $this->assertSame('partial', $result['meta']['match_type']);
        $this->assertCount(1, $result['employees']);
        $this->assertSame('EMP-001', $result['employees'][0]['employee_no']);
        $this->assertNull($result['meta']['sort_ms'], 'Linear search performs no pre-sort.');
    }

    public function test_binary_search_exact_match_sorts_first(): void
    {
        $result = $this->service->search($this->employees(), 'employee_no', 'EMP-002', 'binary');
        $this->assertSame('exact', $result['meta']['match_type']);
        $this->assertCount(1, $result['employees']);
        $this->assertSame('EMP-002', $result['employees'][0]['employee_no']);
        $this->assertIsFloat($result['meta']['sort_ms'], 'Binary search must record its preprocessing sort cost.');
    }

    public function test_binary_search_no_match(): void
    {
        $result = $this->service->search($this->employees(), 'employee_no', 'EMP-999', 'binary');
        $this->assertCount(0, $result['employees']);
    }

    public function test_search_rejects_invalid_field_and_algorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->search($this->employees(), 'ssn', 'x', 'linear');
    }

    public function test_dataset_cap_is_enforced(): void
    {
        $big = [];
        $total = EmployeeDirectoryService::MAX_DSA_DATASET + 50;
        for ($i = 1; $i <= $total; $i++) {
            $big[] = ['id' => $i, 'employee_no' => 'EMP-' . str_pad((string)$i, 5, '0', STR_PAD_LEFT), 'last_name' => 'N' . $i];
        }
        $result = $this->service->sort($big, 'employee_no', 'asc', 'merge');
        $this->assertTrue($result['meta']['capped']);
        $this->assertSame(EmployeeDirectoryService::MAX_DSA_DATASET, $result['meta']['count']);
    }

    public function test_empty_dataset_returns_empty_results(): void
    {
        $result = $this->service->sort([], 'employee_no', 'asc', 'quick');
        $this->assertSame([], $result['employees']);
        $this->assertSame(0, $result['meta']['count']);
    }

    public function test_chained_search_and_sort_pipeline(): void
    {
        $records = [
            ['id' => 1, 'employee_no' => 'EMP-003', 'department_name' => 'IT', 'last_name' => 'Torres'],
            ['id' => 2, 'employee_no' => 'EMP-001', 'department_name' => 'IT', 'last_name' => 'Alvarez'],
            ['id' => 3, 'employee_no' => 'EMP-002', 'department_name' => 'Finance', 'last_name' => 'Bautista'],
            ['id' => 4, 'employee_no' => 'EMP-004', 'department_name' => 'IT', 'last_name' => 'Mendoza'],
        ];

        $searchRes = $this->service->search($records, 'department', 'IT', 'linear');
        $this->assertCount(3, $searchRes['employees']);

        $sortRes = $this->service->sort($searchRes['employees'], 'last_name', 'asc', 'merge');
        $this->assertSame(['Alvarez', 'Mendoza', 'Torres'], array_column($sortRes['employees'], 'last_name'));
    }

    public function test_binary_search_finds_all_duplicate_records(): void
    {
        $records = [
            ['id' => 1, 'department_name' => 'Engineering', 'employee_no' => 'EMP-001'],
            ['id' => 2, 'department_name' => 'Finance', 'employee_no' => 'EMP-002'],
            ['id' => 3, 'department_name' => 'Engineering', 'employee_no' => 'EMP-003'],
            ['id' => 4, 'department_name' => 'HR', 'employee_no' => 'EMP-004'],
            ['id' => 5, 'department_name' => 'Engineering', 'employee_no' => 'EMP-005'],
        ];

        $result = $this->service->search($records, 'department', 'Engineering', 'binary');
        $this->assertCount(3, $result['employees']);
        $this->assertSame(
            ['EMP-001', 'EMP-003', 'EMP-005'],
            array_column($result['employees'], 'employee_no')
        );
    }
}
