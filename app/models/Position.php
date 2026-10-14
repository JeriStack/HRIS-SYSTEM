<?php
/**
 * Position Model
 * Job classification and departmental alignment management
 * Employee HRIS & Leave Management System
 */

namespace App\Models;

use Core\Model;
use PDO;

class Position extends Model
{
    /**
     * Retrieve all positions with joined department name and active employee count.
     *
     * @param int|null $departmentId Filter by department if supplied
     * @return array
     */
    public function getAll(?int $departmentId = null): array
    {
        $params = [];
        $whereClause = "";

        if ($departmentId !== null) {
            $whereClause = "WHERE p.department_id = :dept_id";
            $params['dept_id'] = $departmentId;
        }

        $sql = "
            SELECT 
                p.id,
                p.position_name,
                p.department_id,
                p.description,
                p.created_at,
                d.department_name,
                COUNT(DISTINCT CASE WHEN e.employment_status = 'Active' THEN e.id ELSE NULL END) AS active_employee_count,
                COUNT(DISTINCT e.id) AS total_employee_count
            FROM positions p
            INNER JOIN departments d ON p.department_id = d.id
            LEFT JOIN employees e ON p.id = e.position_id
            {$whereClause}
            GROUP BY p.id
            ORDER BY d.department_name ASC, p.position_name ASC
        ";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Find a single position by ID with department details.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT 
                p.id,
                p.position_name,
                p.department_id,
                p.description,
                p.created_at,
                d.department_name,
                COUNT(DISTINCT CASE WHEN e.employment_status = 'Active' THEN e.id ELSE NULL END) AS active_employee_count
            FROM positions p
            INNER JOIN departments d ON p.department_id = d.id
            LEFT JOIN employees e ON p.id = e.position_id
            WHERE p.id = :id
            GROUP BY p.id
            LIMIT 1
        ";

        return $this->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Retrieve all positions associated with a specific department.
     *
     * @param int $departmentId
     * @return array
     */
    public function getByDepartmentId(int $departmentId): array
    {
        $sql = "
            SELECT id, position_name, department_id, description
            FROM positions
            WHERE department_id = :dept_id
            ORDER BY position_name ASC
        ";

        return $this->fetchAll($sql, ['dept_id' => $departmentId]);
    }

    /**
     * Enforce uniqueness rule: Position name must be unique within the assigned department.
     * Constraint: UNIQUE(department_id, position_name)
     *
     * @param string $name
     * @param int $departmentId
     * @param int|null $excludeId
     * @return bool True if available (unique), false if already exists
     */
    public function isUniqueInDepartment(string $name, int $departmentId, ?int $excludeId = null): bool
    {
        $cleanName = trim($name);

        if ($excludeId !== null) {
            $sql = "
                SELECT COUNT(*) 
                FROM positions 
                WHERE LOWER(TRIM(position_name)) = LOWER(:name) 
                  AND department_id = :dept_id 
                  AND id != :id
            ";
            $count = (int)$this->query($sql, [
                'name'    => $cleanName,
                'dept_id' => $departmentId,
                'id'      => $excludeId
            ])->fetchColumn();
        } else {
            $sql = "
                SELECT COUNT(*) 
                FROM positions 
                WHERE LOWER(TRIM(position_name)) = LOWER(:name) 
                  AND department_id = :dept_id
            ";
            $count = (int)$this->query($sql, [
                'name'    => $cleanName,
                'dept_id' => $departmentId
            ])->fetchColumn();
        }

        return $count === 0;
    }

    /**
     * Create a new position record.
     *
     * @param array $data ['position_name' => ..., 'department_id' => ..., 'description' => ...]
     * @return int Inserted ID
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO positions (position_name, department_id, description, created_at)
            VALUES (:name, :dept_id, :description, NOW())
        ";

        $this->execute($sql, [
            'name'        => trim($data['position_name']),
            'dept_id'     => (int)$data['department_id'],
            'description' => !empty($data['description']) ? trim($data['description']) : null,
        ]);

        return (int)$this->lastInsertId();
    }

    /**
     * Update an existing position record.
     *
     * @param int $id
     * @param array $data ['position_name' => ..., 'department_id' => ..., 'description' => ...]
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $sql = "
            UPDATE positions
            SET position_name = :name,
                department_id = :dept_id,
                description = :description
            WHERE id = :id
        ";

        return $this->execute($sql, [
            'id'          => $id,
            'name'        => trim($data['position_name']),
            'dept_id'     => (int)$data['department_id'],
            'description' => !empty($data['description']) ? trim($data['description']) : null,
        ]);
    }

    /**
     * Get count of employees currently assigned to this position.
     *
     * @param int $id
     * @return int
     */
    public function getDependentEmployeeCount(int $id): int
    {
        $sql = "SELECT COUNT(*) FROM employees WHERE position_id = :id";
        return (int)$this->query($sql, ['id' => $id])->fetchColumn();
    }
}
