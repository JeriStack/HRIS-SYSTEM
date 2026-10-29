<?php

namespace Tests\Integration\DSA;

use App\Models\Employee;
use App\Services\EmployeeLookupService;
use PHPUnit\Framework\TestCase;

class EmployeeLookupHashTableTest extends TestCase
{
    public function test_build_index_and_lookup(): void
    {
        $mockEmployee = $this->createMock(Employee::class);
        $mockEmployee->method('getAll')->willReturn([
            ['id' => 1, 'employee_no' => 'EMP-001', 'first_name' => 'Alice', 'last_name' => 'Alvarez', 'email' => 'alice@hris.test', 'department_name' => 'HR', 'position_name' => 'Manager', 'employment_status' => 'Active'],
            ['id' => 2, 'employee_no' => 'EMP-002', 'first_name' => 'Bob', 'last_name' => 'Bautista', 'email' => 'bob@hris.test', 'department_name' => 'IT', 'position_name' => 'Dev', 'employment_status' => 'Active'],
            ['id' => 3, 'employee_no' => 'EMP-003', 'first_name' => 'Charlie', 'last_name' => 'Cruz', 'email' => 'charlie@hris.test', 'department_name' => 'Finance', 'position_name' => 'Analyst', 'employment_status' => 'Inactive'],
        ]);

        $service = new EmployeeLookupService($mockEmployee);
        $table = $service->buildEmployeeNumberIndex(16);

        $this->assertSame(3, $table->size());

        // Test O(1) lookup with case-insensitivity
        $emp1 = $service->findByEmployeeNumber($table, 'emp-001');
        $this->assertNotNull($emp1);
        $this->assertSame('Alice', $emp1['first_name']);
        $this->assertSame('Manager', $emp1['position_name']);

        $emp2 = $service->findByEmployeeNumber($table, 'EMP-002');
        $this->assertNotNull($emp2);
        $this->assertSame('Bob', $emp2['first_name']);

        // Missing record handling without error
        $missing = $service->findByEmployeeNumber($table, 'EMP-999');
        $this->assertNull($missing);

        $empty = $service->findByEmployeeNumber($table, '');
        $this->assertNull($empty);
    }
}

