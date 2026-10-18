<?php
/**
 * Employee Model
 * Core workforce registry and historical records management
 * Employee HRIS & Leave Management System
 */

namespace App\Models;

use Core\Model;
use PDO;

class Employee extends Model
{
    /**
     * Retrieve all employees with optional search query, status filter, and department filter.
     *
     * @param string|null $search Search term matching employee_no, names, or email
     * @param string|null $status 'Active' or 'Inactive'
     * @param int|null $deptId Filter by department
     * @return array
     */
    public function getAll(?string $search = null, ?string $status = null, ?int $deptId = null): array
    {
        $params = [];
        $whereConditions = [];

        if (!empty($search)) {
            $searchTerm = '%' . trim($search) . '%';
            $whereConditions[] = "(
                e.employee_no LIKE :s1 OR 
                e.first_name LIKE :s2 OR 
                e.last_name LIKE :s3 OR 
                CONCAT(e.first_name, ' ', e.last_name) LIKE :s4 OR 
                e.email LIKE :s5
            )";
            $params['s1'] = $searchTerm;
            $params['s2'] = $searchTerm;
            $params['s3'] = $searchTerm;
            $params['s4'] = $searchTerm;
            $params['s5'] = $searchTerm;
        }

        if (!empty($status) && in_array($status, ['Active', 'Inactive', 'Terminated'], true)) {
            $whereConditions[] = "e.employment_status = :status";
            $params['status'] = $status;
        }

        if (!empty($deptId)) {
            $whereConditions[] = "e.department_id = :dept_id";
            $params['dept_id'] = (int)$deptId;
        }

        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

        $sql = "
            SELECT 
                e.id,
                e.employee_no,
                e.first_name,
                e.middle_name,
                e.last_name,
                e.email,
                e.phone,
                e.address,
                e.birth_date,
                e.gender,
                e.department_id,
                e.position_id,
                e.date_hired,
                e.employment_status,
                e.created_at,
                d.department_name,
                p.position_name,
                u.id AS user_account_id,
                u.username AS account_username
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN users u ON e.id = u.employee_id
            {$whereClause}
            ORDER BY e.last_name ASC, e.first_name ASC
        ";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Retrieve a single employee by internal primary key ID.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT 
                e.*,
                d.department_name,
                p.position_name,
                u.id AS user_account_id,
                u.username AS account_username,
                u.role AS user_role,
                u.status AS user_status
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            LEFT JOIN users u ON e.id = u.employee_id
            WHERE e.id = :id
            LIMIT 1
        ";

        return $this->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Find employee by unique employee number.
     *
     * @param string $employeeNo
     * @return array|null
     */
    public function findByEmployeeNo(string $employeeNo): ?array
    {
        $sql = "
            SELECT e.*, d.department_name, p.position_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.employee_no = :emp_no
            LIMIT 1
        ";

        return $this->fetchOne($sql, ['emp_no' => trim($employeeNo)]);
    }

    /**
     * Find employee by registered email.
     *
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "
            SELECT e.*, d.department_name, p.position_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE LOWER(e.email) = LOWER(:email)
            LIMIT 1
        ";

        return $this->fetchOne($sql, ['email' => trim($email)]);
    }

    /**
     * Check if employee number is unique.
     *
     * @param string $employeeNo
     * @param int|null $excludeId
     * @return bool True if available (unique), false if already taken
     */
    public function isEmployeeNoUnique(string $employeeNo, ?int $excludeId = null): bool
    {
        $cleanNo = trim($employeeNo);

        if ($excludeId !== null) {
            $sql = "SELECT COUNT(*) FROM employees WHERE employee_no = :emp_no AND id != :id";
            $count = (int)$this->query($sql, ['emp_no' => $cleanNo, 'id' => $excludeId])->fetchColumn();
        } else {
            $sql = "SELECT COUNT(*) FROM employees WHERE employee_no = :emp_no";
            $count = (int)$this->query($sql, ['emp_no' => $cleanNo])->fetchColumn();
        }

        return $count === 0;
    }

    /**
     * Check if email is unique.
     *
     * @param string $email
     * @param int|null $excludeId
     * @return bool True if available (unique), false if already taken
     */
    public function isEmailUnique(string $email, ?int $excludeId = null): bool
    {
        $cleanEmail = trim($email);

        if ($excludeId !== null) {
            $sql = "SELECT COUNT(*) FROM employees WHERE LOWER(email) = LOWER(:email) AND id != :id";
            $count = (int)$this->query($sql, ['email' => $cleanEmail, 'id' => $excludeId])->fetchColumn();
        } else {
            $sql = "SELECT COUNT(*) FROM employees WHERE LOWER(email) = LOWER(:email)";
            $count = (int)$this->query($sql, ['email' => $cleanEmail])->fetchColumn();
        }

        return $count === 0;
    }

    /**
     * Create a new employee record.
     *
     * @param array $data
     * @return int Inserted ID
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO employees (
                employee_no, first_name, middle_name, last_name, email, phone, 
                address, birth_date, gender, department_id, position_id, 
                date_hired, employment_status, created_at
            ) VALUES (
                :employee_no, :first_name, :middle_name, :last_name, :email, :phone, 
                :address, :birth_date, :gender, :department_id, :position_id, 
                :date_hired, :employment_status, NOW()
            )
        ";

        $this->execute($sql, [
            'employee_no'       => trim($data['employee_no']),
            'first_name'        => trim($data['first_name']),
            'middle_name'       => !empty($data['middle_name']) ? trim($data['middle_name']) : null,
            'last_name'         => trim($data['last_name']),
            'email'             => trim($data['email']),
            'phone'             => !empty($data['phone']) ? trim($data['phone']) : null,
            'address'           => !empty($data['address']) ? trim($data['address']) : null,
            'birth_date'        => $data['birth_date'],
            'gender'            => $data['gender'],
            'department_id'     => (int)$data['department_id'],
            'position_id'       => (int)$data['position_id'],
            'date_hired'        => $data['date_hired'],
            'employment_status' => $data['employment_status'] ?? 'Active',
        ]);

        return (int)$this->lastInsertId();
    }

    /**
     * Update an existing employee record.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $sql = "
            UPDATE employees
            SET 
                employee_no       = :employee_no,
                first_name        = :first_name,
                middle_name       = :middle_name,
                last_name         = :last_name,
                email             = :email,
                phone             = :phone,
                address           = :address,
                birth_date        = :birth_date,
                gender            = :gender,
                department_id     = :department_id,
                position_id       = :position_id,
                date_hired        = :date_hired,
                employment_status = :employment_status
            WHERE id = :id
        ";

        return $this->execute($sql, [
            'id'                => $id,
            'employee_no'       => trim($data['employee_no']),
            'first_name'        => trim($data['first_name']),
            'middle_name'       => !empty($data['middle_name']) ? trim($data['middle_name']) : null,
            'last_name'         => trim($data['last_name']),
            'email'             => trim($data['email']),
            'phone'             => !empty($data['phone']) ? trim($data['phone']) : null,
            'address'           => !empty($data['address']) ? trim($data['address']) : null,
            'birth_date'        => $data['birth_date'],
            'gender'            => $data['gender'],
            'department_id'     => (int)$data['department_id'],
            'position_id'       => (int)$data['position_id'],
            'date_hired'        => $data['date_hired'],
            'employment_status' => $data['employment_status'] ?? 'Active',
        ]);
    }

    /**
     * Soft deactivation of an employee record.
     * Historical leave and audit logs are 100% preserved.
     * Also deactivates associated user login account if present.
     *
     * @param int $id
     * @return bool
     */
    public function deactivate(int $id): bool
    {
        $emp = $this->findById($id);
        if (!$emp || $emp['employment_status'] === 'Inactive') {
            return false;
        }

        $this->beginTransaction();

        try {
            // 1. Update employee status to Inactive
            $sqlEmp = "UPDATE employees SET employment_status = 'Inactive' WHERE id = :id";
            $this->execute($sqlEmp, ['id' => $id]);

            // 2. Also deactivate linked login user account to block login
            $sqlUser = "UPDATE users SET status = 'Inactive' WHERE employee_id = :emp_id";
            $this->execute($sqlUser, ['emp_id' => $id]);

            $this->commit();
            return true;
        } catch (\Throwable $e) {
            $this->rollBack();
            error_log("Failed to deactivate employee {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reactivate an inactive employee.
     *
     * @param int $id
     * @return bool
     */
    public function activate(int $id): bool
    {
        $this->beginTransaction();

        try {
            $sqlEmp = "UPDATE employees SET employment_status = 'Active' WHERE id = :id";
            $this->execute($sqlEmp, ['id' => $id]);

            $sqlUser = "UPDATE users SET status = 'Active' WHERE employee_id = :emp_id";
            $this->execute($sqlUser, ['emp_id' => $id]);

            $this->commit();
            return true;
        } catch (\Throwable $e) {
            $this->rollBack();
            error_log("Failed to activate employee {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve aggregated workforce counts.
     *
     * @return array ['total' => int, 'active' => int, 'inactive' => int]
     */
    public function getCounts(): array
    {
        $sql = "
            SELECT 
                COUNT(*) AS total,
                COUNT(CASE WHEN employment_status = 'Active' THEN 1 END) AS active,
                COUNT(CASE WHEN employment_status != 'Active' THEN 1 END) AS inactive
            FROM employees
        ";

        $res = $this->fetchOne($sql);
        return [
            'total'    => (int)($res['total'] ?? 0),
            'active'   => (int)($res['active'] ?? 0),
            'inactive' => (int)($res['inactive'] ?? 0),
        ];
    }

    /**
     * Retrieve active employees who currently do not have a user account provisioned.
     *
     * @return array
     */
    public function getEmployeesWithoutUserAccount(): array
    {
        $sql = "
            SELECT 
                e.id,
                e.employee_no,
                e.first_name,
                e.last_name,
                e.email,
                d.department_name,
                p.position_name
            FROM employees e
            LEFT JOIN users u ON e.id = u.employee_id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE u.id IS NULL AND e.employment_status = 'Active'
            ORDER BY e.last_name ASC, e.first_name ASC
        ";

        return $this->fetchAll($sql);
    }
}
