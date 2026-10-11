<?php
/**
 * Department Model
 * Organizational functional units management
 * Employee HRIS & Leave Management System
 */

namespace App\Models;

use Core\Model;
use PDO;

class Department extends Model
{
    /**
     * Retrieve all departments with position count and active employee count.
     *
     * @return array
     */
    public function getAll(): array
    {
        $sql = "
            SELECT 
                d.id,
                d.department_name,
                d.description,
                d.created_at,
                COUNT(DISTINCT p.id) AS position_count,
                COUNT(DISTINCT CASE WHEN e.employment_status = 'Active' THEN e.id ELSE NULL END) AS active_employee_count,
                COUNT(DISTINCT e.id) AS total_employee_count
            FROM departments d
            LEFT JOIN positions p ON d.id = p.department_id
            LEFT JOIN employees e ON d.id = e.department_id
            GROUP BY d.id
            ORDER BY d.department_name ASC
        ";

        return $this->fetchAll($sql);
    }

    /**
     * Find a single department by primary key ID.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT 
                d.id,
                d.department_name,
                d.description,
                d.created_at,
                COUNT(DISTINCT p.id) AS position_count,
                COUNT(DISTINCT CASE WHEN e.employment_status = 'Active' THEN e.id ELSE NULL END) AS active_employee_count
            FROM departments d
            LEFT JOIN positions p ON d.id = p.department_id
            LEFT JOIN employees e ON d.id = e.department_id
            WHERE d.id = :id
            GROUP BY d.id
            LIMIT 1
        ";

        return $this->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Find a department by exact name.
     *
     * @param string $name
     * @return array|null
     */
    public function findByName(string $name): ?array
    {
        $sql = "SELECT * FROM departments WHERE LOWER(TRIM(department_name)) = LOWER(TRIM(:name)) LIMIT 1";
        return $this->fetchOne($sql, ['name' => $name]);
    }

    /**
     * Check if a department name is unique (case-insensitive and trimmed).
     *
     * @param string $name
     * @param int|null $excludeId ID to exclude during updates
     * @return bool True if available (unique), false if already taken
     */
    public function isNameUnique(string $name, ?int $excludeId = null): bool
    {
        $cleanName = trim($name);
        if ($excludeId !== null) {
            $sql = "SELECT COUNT(*) FROM departments WHERE LOWER(TRIM(department_name)) = LOWER(:name) AND id != :id";
            $count = (int)$this->query($sql, ['name' => $cleanName, 'id' => $excludeId])->fetchColumn();
        } else {
            $sql = "SELECT COUNT(*) FROM departments WHERE LOWER(TRIM(department_name)) = LOWER(:name)";
            $count = (int)$this->query($sql, ['name' => $cleanName])->fetchColumn();
        }

        return $count === 0;
    }

    /**
     * Create a new department record.
     *
     * @param array $data ['department_name' => ..., 'description' => ...]
     * @return int Inserted ID
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO departments (department_name, description, created_at)
            VALUES (:name, :description, NOW())
        ";

        $this->execute($sql, [
            'name'        => trim($data['department_name']),
            'description' => !empty($data['description']) ? trim($data['description']) : null,
        ]);

        return (int)$this->lastInsertId();
    }

    /**
     * Update an existing department record.
     *
     * @param int $id
     * @param array $data ['department_name' => ..., 'description' => ...]
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $sql = "
            UPDATE departments
            SET department_name = :name,
                description = :description
            WHERE id = :id
        ";

        return $this->execute($sql, [
            'id'          => $id,
            'name'        => trim($data['department_name']),
            'description' => !empty($data['description']) ? trim($data['description']) : null,
        ]);
    }

    /**
     * Check if department has dependent positions or employees.
     *
     * @param int $id
     * @return array ['positions' => int, 'employees' => int]
     */
    public function getDependentCounts(int $id): array
    {
        $posCount = (int)$this->query("SELECT COUNT(*) FROM positions WHERE department_id = :id", ['id' => $id])->fetchColumn();
        $empCount = (int)$this->query("SELECT COUNT(*) FROM employees WHERE department_id = :id", ['id' => $id])->fetchColumn();

        return [
            'positions' => $posCount,
            'employees' => $empCount,
        ];
    }
}
