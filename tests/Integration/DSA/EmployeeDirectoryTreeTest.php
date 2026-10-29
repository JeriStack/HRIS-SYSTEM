<?php

namespace Tests\Integration\DSA;

use App\Models\Employee;
use App\Services\EmployeeDirectoryTreeService;
use PHPUnit\Framework\TestCase;

class EmployeeDirectoryTreeTest extends TestCase
{
    public function test_build_tree_search_and_inorder_extraction(): void
    {
        $mockEmployee = $this->createMock(Employee::class);
        $mockEmployee->method('getAll')->willReturn([
            ['id' => 3, 'employee_no' => 'EMP-050', 'first_name' => 'Charlie', 'last_name' => 'Cruz', 'department_name' => 'IT', 'position_name' => 'Dev', 'employment_status' => 'Active'],
            ['id' => 1, 'employee_no' => 'EMP-010', 'first_name' => 'Alice', 'last_name' => 'Alvarez', 'department_name' => 'HR', 'position_name' => 'Specialist', 'employment_status' => 'Active'],
            ['id' => 2, 'employee_no' => 'EMP-090', 'first_name' => 'Bob', 'last_name' => 'Bautista', 'department_name' => 'Finance', 'position_name' => 'Analyst', 'employment_status' => 'Active'],
        ]);

        $service = new EmployeeDirectoryTreeService($mockEmployee);
        $tree = $service->buildEmployeeTree();

        $this->assertSame(3, $tree->size());

        // Logarithmic Search
        $found = $service->searchByEmployeeNo($tree, 'EMP-050');
        $this->assertNotNull($found);
        $this->assertSame('Charlie', $found['first_name']);

        $missing = $service->searchByEmployeeNo($tree, 'EMP-999');
        $this->assertNull($missing);

        // Sorted In-Order Traversal produces alphabetical/numeric employee order
        $sortedEmployees = $service->getSortedEmployees($tree);
        $this->assertSame(['EMP-010', 'EMP-050', 'EMP-090'], array_column($sortedEmployees, 'employee_no'));
    }
}

