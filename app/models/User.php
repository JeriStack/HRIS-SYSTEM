<?php
/**
 * User Model
 * Authentication, identity verification, and role resolution
 * Employee HRIS & Leave Management System
 */

namespace App\Models;

use Core\Model;

class User extends Model
{
    /**
     * Find user record by email with joined employee, department, and position data.
     * Normalizes the input email (trims whitespace and converts to lower case).
     *
     * @param string $email Registered account email address
     * @return array|null User record or null if not found
     */
    public function findByEmail(string $email): ?array
    {
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '' || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $sql = "
            SELECT 
                u.id,
                u.employee_id,
                u.username,
                COALESCE(u.email, e.email) AS email,
                u.password,
                u.role,
                u.status,
                u.created_at,
                e.employee_no,
                e.first_name,
                e.middle_name,
                e.last_name,
                e.employment_status,
                e.department_id,
                e.position_id,
                d.department_name,
                p.position_name
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE LOWER(TRIM(COALESCE(u.email, e.email))) = :email
            LIMIT 1
        ";

        return $this->fetchOne($sql, ['email' => $normalizedEmail]);
    }

    /**
     * Check if an email address is already registered to another user account.
     *
     * @param string $email
     * @param int|null $excludeUserId
     * @return bool
     */
    public function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        $normalized = strtolower(trim($email));
        if ($excludeUserId !== null) {
            $sql = "
                SELECT COUNT(*) FROM users u 
                LEFT JOIN employees e ON u.employee_id = e.id 
                WHERE LOWER(TRIM(COALESCE(u.email, e.email))) = :email
                  AND u.id != :exclude_id
            ";
            return (int)$this->fetchColumn($sql, ['email' => $normalized, 'exclude_id' => $excludeUserId]) > 0;
        }

        $sql = "
            SELECT COUNT(*) FROM users u 
            LEFT JOIN employees e ON u.employee_id = e.id 
            WHERE LOWER(TRIM(COALESCE(u.email, e.email))) = :email
        ";
        return (int)$this->fetchColumn($sql, ['email' => $normalized]) > 0;
    }

    /**
     * Create a new user account with secure password hash.
     *
     * @param array $data ['employee_id' => ?, 'username' => ?, 'email' => ?, 'password' => ?, 'role' => ?, 'status' => ?]
     * @return int Inserted user ID
     */
    public function create(array $data): int
    {
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $username = trim((string)($data['username'] ?? ''));
        if ($username === '' && $email !== '') {
            $username = explode('@', $email)[0];
        }

        $role = strtolower(trim((string)($data['role'] ?? 'employee')));
        if ($role !== 'hr' && $role !== 'employee') {
            throw new \InvalidArgumentException("Invalid role '{$role}'. Only 'hr' and 'employee' are permitted.");
        }

        $passwordHash = (string)$data['password'];
        if (!str_starts_with($passwordHash, '$2y$') && !str_starts_with($passwordHash, '$argon2')) {
            $passwordHash = password_hash($passwordHash, PASSWORD_BCRYPT);
        }

        $sql = "
            INSERT INTO users (
                employee_id, username, email, password, role, status, created_at
            ) VALUES (
                :employee_id, :username, :email, :password, :role, :status, NOW()
            )
        ";

        $this->execute($sql, [
            'employee_id' => !empty($data['employee_id']) ? (int)$data['employee_id'] : null,
            'username'    => $username,
            'email'       => $email,
            'password'    => $passwordHash,
            'role'        => $role,
            'status'      => $data['status'] ?? 'Active',
        ]);

        return (int)$this->lastInsertId();
    }

    /**
     * Find user record by username with joined employee, department, and position data.
     *
     * @param string $username Unique login username
     * @return array|null User record or null if not found
     */
    public function findByUsername(string $username): ?array
    {
        $sql = "
            SELECT 
                u.id,
                u.employee_id,
                u.username,
                COALESCE(u.email, e.email) AS email,
                u.password,
                u.role,
                u.status,
                u.created_at,
                e.employee_no,
                e.first_name,
                e.middle_name,
                e.last_name,
                e.employment_status,
                e.department_id,
                e.position_id,
                d.department_name,
                p.position_name
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE u.username = :username
            LIMIT 1
        ";

        return $this->fetchOne($sql, ['username' => $username]);
    }

    /**
     * Find user by primary user ID with profile details.
     *
     * @param int $id Primary user ID
     * @return array|null User record or null
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT 
                u.id,
                u.employee_id,
                u.username,
                COALESCE(u.email, e.email) AS email,
                u.role,
                u.status,
                u.created_at,
                e.employee_no,
                e.first_name,
                e.middle_name,
                e.last_name,
                e.employment_status,
                e.department_id,
                e.position_id,
                d.department_name,
                p.position_name
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE u.id = :id
            LIMIT 1
        ";

        return $this->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Find user by associated employee ID.
     *
     * @param int $employeeId
     * @return array|null
     */
    public function findByEmployeeId(int $employeeId): ?array
    {
        $sql = "
            SELECT 
                u.id,
                u.employee_id,
                u.username,
                u.role,
                u.status,
                u.created_at,
                e.employee_no,
                e.first_name,
                e.last_name,
                e.employment_status
            FROM users u
            INNER JOIN employees e ON u.employee_id = e.id
            WHERE u.employee_id = :employee_id
            LIMIT 1
        ";

        return $this->fetchOne($sql, ['employee_id' => $employeeId]);
    }

    /**
     * Update user password hash.
     *
     * @param int $id
     * @param string $newHash
     * @return bool
     */
    public function updatePasswordHash(int $id, string $newHash): bool
    {
        $sql = "UPDATE users SET password = :password WHERE id = :id";
        return $this->execute($sql, [
            'password' => $newHash,
            'id'       => $id,
        ]);
    }

    /**
     * Verify plaintext password against stored password hash.
     *
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if password hash requires algorithmic rehashing.
     *
     * @param string $hash
     * @return bool
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    /**
     * Record an audit event in the activity_logs table.
     * Passwords and sensitive secrets are NEVER written to logs.
     *
     * @param int|null $userId
     * @param string $action Action key (e.g. AUTH_LOGIN, AUTH_LOGOUT, AUTH_FAILED)
     * @param string $description High-level safe description
     * @param string|null $entityType
     * @param string|null $entityId
     * @return bool
     */
    public function logActivity(?int $userId, string $action, string $description, ?string $entityType = null, ?string $entityId = null): bool
    {
        try {
            $sql = "
                INSERT INTO activity_logs (
                    user_id, action, description, entity_type, entity_id, ip_address, user_agent, created_at
                ) VALUES (
                    :user_id, :action, :description, :entity_type, :entity_id, :ip_address, :user_agent, NOW()
                )
            ";

            return $this->execute($sql, [
                'user_id'     => $userId,
                'action'      => $action,
                'description' => $description,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255),
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to write to activity_logs: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve all system user accounts joined with employee, department, and position data.
     *
     * @return array
     */
    public function getAllWithDetails(): array
    {
        $sql = "
            SELECT 
                u.id,
                u.employee_id,
                u.username,
                COALESCE(u.email, e.email) AS email,
                u.role,
                u.status,
                u.created_at,
                e.employee_no,
                e.first_name,
                e.last_name,
                e.employment_status,
                d.department_name,
                p.position_name
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            ORDER BY u.id ASC
        ";

        return $this->fetchAll($sql);
    }

    /**
     * Count active HR administrator accounts, optionally excluding a specific user ID.
     * Essential for protecting against last-active-HR lockout.
     *
     * @param int|null $excludeUserId
     * @return int
     */
    public function countActiveHr(?int $excludeUserId = null): int
    {
        if ($excludeUserId !== null) {
            $sql = "SELECT COUNT(*) FROM users WHERE role = 'hr' AND status = 'Active' AND id != :exclude_id";
            return (int)$this->fetchColumn($sql, ['exclude_id' => $excludeUserId]);
        }

        $sql = "SELECT COUNT(*) FROM users WHERE role = 'hr' AND status = 'Active'";
        return (int)$this->fetchColumn($sql);
    }

    /**
     * Count active administrator accounts (delegates to countActiveHr for 2-role system).
     *
     * @param int|null $excludeUserId
     * @return int
     */
    public function countActiveAdmins(?int $excludeUserId = null): int
    {
        return $this->countActiveHr($excludeUserId);
    }

    /**
     * Update a user account's assigned role and operational status.
     *
     * @param int $id
     * @param string $role Validated role ('admin', 'hr', 'employee')
     * @param string $status Validated status ('Active', 'Inactive')
     * @return bool
     */
    public function updateRoleAndStatus(int $id, string $role, string $status): bool
    {
        $sql = "UPDATE users SET role = :role, status = :status WHERE id = :id";
        return $this->execute($sql, [
            'role'   => $role,
            'status' => $status,
            'id'     => $id,
        ]);
    }
}

