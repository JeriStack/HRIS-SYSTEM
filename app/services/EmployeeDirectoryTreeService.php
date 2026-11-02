<?php
/**
 * Employee Directory Binary Search Tree Service
 * Builds an in-memory BST for logarithmic search and recursive ordered traversals
 * Employee HRIS & Leave Management System — Phase 11
 */

namespace App\Services;

use App\Dsa\Structures\BinarySearchTree;
use App\Models\Employee;

class EmployeeDirectoryTreeService
{
    private Employee $employeeModel;

    public function __construct(?Employee $model = null)
    {
        $this->employeeModel = $model ?? new Employee();
    }

    /**
     * Build an in-memory Binary Search Tree from workforce records.
     * Keys are employee_no strings, compared via ArrayUtils::compareValues.
     *
     * @param int $maxRecords Safe upper limit for demonstration
     * @return BinarySearchTree
     */
    public function buildEmployeeTree(int $maxRecords = 100): BinarySearchTree
    {
        $employees = $this->employeeModel->getAll();
        if ($maxRecords > 0 && count($employees) > $maxRecords) {
            $employees = array_slice($employees, 0, $maxRecords);
        }

        $tree = new BinarySearchTree();
        foreach ($employees as $emp) {
            $key = strtoupper(trim((string)($emp['employee_no'] ?? '')));
            if ($key !== '') {
                $safeRecord = [
                    'id'                => (int)($emp['id'] ?? 0),
                    'employee_no'       => $key,
                    'first_name'        => (string)($emp['first_name'] ?? ''),
                    'last_name'         => (string)($emp['last_name'] ?? ''),
                    'department_name'   => (string)($emp['department_name'] ?? ''),
                    'position_name'     => (string)($emp['position_name'] ?? ''),
                    'employment_status' => (string)($emp['employment_status'] ?? ''),
                ];
                $tree->insert($key, $safeRecord);
            }
        }

        return $tree;
    }

    /**
     * Search the BST for an employee by key.
     * Average O(log n), Worst O(n).
     *
     * @return array<string, mixed>|null
     */
    public function searchByEmployeeNo(BinarySearchTree $tree, string $employeeNo): ?array
    {
        $key = strtoupper(trim($employeeNo));
        $result = $tree->search($key);
        return is_array($result) ? $result : null;
    }

    /**
     * Extract sorted employee records using recursive In-Order Traversal.
     *
     * @return list<array<string, mixed>>
     */
    public function getSortedEmployees(BinarySearchTree $tree): array
    {
        $traversal = $tree->inOrderTraversal();
        $records = [];
        foreach ($traversal as $node) {
            if (is_array($node['value'])) {
                $records[] = $node['value'];
            }
        }
        return $records;
    }
}

