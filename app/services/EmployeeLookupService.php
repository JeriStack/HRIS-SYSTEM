<?php
/**
 * Employee Lookup Hash Table Service
 * Builds a bounded in-memory Hash Table for O(1) employee record retrieval
 * Employee HRIS & Leave Management System — Phase 11
 */

namespace App\Services;

use App\Dsa\Structures\HashTable;
use App\Models\Employee;

class EmployeeLookupService
{
    private Employee $employeeModel;

    public function __construct(?Employee $model = null)
    {
        $this->employeeModel = $model ?? new Employee();
    }

    /**
     * Build an in-memory Hash Table index of employees keyed by employee_no.
     * Only authorized, non-sensitive demographic and workforce fields are indexed.
     *
     * @param int $capacity Initial hash table bucket capacity
     * @return HashTable
     */
    public function buildEmployeeNumberIndex(int $capacity = 64): HashTable
    {
        $employees = $this->employeeModel->getAll();
        $table = new HashTable($capacity);

        foreach ($employees as $emp) {
            $key = strtoupper(trim((string)($emp['employee_no'] ?? '')));
            if ($key !== '') {
                // Sanitize and isolate public profile data
                $safeRecord = [
                    'id'                => (int)($emp['id'] ?? 0),
                    'employee_no'       => $key,
                    'first_name'        => (string)($emp['first_name'] ?? ''),
                    'last_name'         => (string)($emp['last_name'] ?? ''),
                    'email'             => (string)($emp['email'] ?? ''),
                    'department_name'   => (string)($emp['department_name'] ?? ''),
                    'position_name'     => (string)($emp['position_name'] ?? ''),
                    'employment_status' => (string)($emp['employment_status'] ?? ''),
                ];
                $table->set($key, $safeRecord);
            }
        }

        return $table;
    }

    /**
     * Look up an employee record by employee_no with O(1) average lookup.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmployeeNumber(HashTable $table, string $employeeNo): ?array
    {
        $normalized = strtoupper(trim($employeeNo));
        if ($normalized === '' || !$table->containsKey($normalized)) {
            return null;
        }

        $record = $table->get($normalized);
        return is_array($record) ? $record : null;
    }
}

