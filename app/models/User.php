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
        if (!in_array($role, ['admin', 'hr', 'employee'], true)) {
            throw new \InvalidArgumentException("Invalid role '{$role}'. Only 'admin', 'hr', and 'employee' are permitted.");
        }

        $passwordHash = (string)$data['password'];
        if (!str_starts_with($passwordHash, '$2y$') && !str_starts_with($passwordHash, '$argon2')) {
            $passwordHash = password_hash($passwordHash, PASSWORD_DEFAULT);
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
     * Find a user with the minimal security-relevant state used for live session re-validation
     * (password hash, role, account status, and linked employment status).
     * Never expose the returned row to views or API responses.
     *
     * @param int $id Primary user ID
     * @return array|null
     */
    public function findByIdWithAuthState(int $id): ?array
    {
        $sql = "
            SELECT
                u.id,
                u.username,
                u.employee_id,
                u.password,
                u.role,
                u.status,
                COALESCE(u.email, e.email) AS email,
                e.employee_no,
                e.first_name,
                e.middle_name,
                e.last_name,
                e.employment_status,
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
     * Count audit events of one action recorded for a user within a rolling window.
     *
     * @param int $userId Acting user ID
     * @param string $action Audit action key (e.g. STEP_UP_FAILED)
     * @param int $windowSeconds Rolling window length in seconds
     * @return array{count: int, retry_after: int}
     */
    public function countRecentActions(int $userId, string $action, int $windowSeconds): array
    {
        // DB-clock window so PHP/MySQL timezone differences cannot skew the count.
        $window = max(1, $windowSeconds);
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS total,
                    GREATEST(0, {$window} - TIMESTAMPDIFF(SECOND, MIN(created_at), NOW())) AS retry_after
             FROM activity_logs
             WHERE user_id = :user_id
               AND action = :action
               AND created_at > (NOW() - INTERVAL {$window} SECOND)",
            ['user_id' => $userId, 'action' => $action]
        );

        return [
            'count'       => (int)($row['total'] ?? 0),
            'retry_after' => (int)($row['retry_after'] ?? 0),
        ];
    }

    /**
     * Determine whether primary password login is temporarily rate-limited for an
     * account identifier (normalized email) and/or source IP address.
     * Uses existing AUTH_FAILED audit records; no schema change is required.
     *
     * @param string $email Normalized email submitted on login
     * @param string|null $ipAddress Client IP address
     * @return array ['limited' => bool, 'retry_after' => int seconds]
     */
    public function checkLoginRateLimit(string $email, ?string $ipAddress = null): array
    {
        $window = max(60, (int)config('config.login_rate_limit_window', 900));
        $maxAccount = max(1, (int)config('config.login_rate_limit_max_account', 5));
        $maxIp = max(1, (int)config('config.login_rate_limit_max_ip', 15));
        $normalizedEmail = strtolower(trim($email));

        try {
            // Failures are recorded with entity_id = hashed login identifier (unknown account)
            // or user ID (known account). The raw email is also matched so rows written before
            // identifier hashing was introduced still count until they age out of the window.
            $user = $normalizedEmail !== '' ? $this->findByEmail($normalizedEmail) : null;
            $identifiers = [login_identifier_key($normalizedEmail), $normalizedEmail];
            if ($user) {
                $identifiers[] = (string)$user['id'];
            }

            // The rolling window and retry are evaluated on the database clock so a
            // timezone mismatch between PHP and MySQL cannot make the limit silently fail open.
            $placeholders = [];
            $params = [];
            foreach ($identifiers as $i => $identifier) {
                $key = 'ident' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $identifier;
            }

            $accountRow = $this->fetchOne(
                "SELECT COUNT(*) AS failures,
                        GREATEST(0, {$window} - TIMESTAMPDIFF(SECOND, MIN(created_at), NOW())) AS retry_after
                 FROM activity_logs
                 WHERE action = 'AUTH_FAILED'
                   AND entity_type = 'USER'
                   AND entity_id IN (" . implode(',', $placeholders) . ")
                   AND created_at > (NOW() - INTERVAL {$window} SECOND)",
                $params
            );

            if ($accountRow && (int)$accountRow['failures'] >= $maxAccount) {
                return [
                    'limited'     => true,
                    'retry_after' => max(1, (int)$accountRow['retry_after']),
                ];
            }

            if ($ipAddress !== null && $ipAddress !== '') {
                $ipRow = $this->fetchOne(
                    "SELECT COUNT(*) AS failures,
                            GREATEST(0, {$window} - TIMESTAMPDIFF(SECOND, MIN(created_at), NOW())) AS retry_after
                     FROM activity_logs
                     WHERE action = 'AUTH_FAILED'
                       AND ip_address = :ip
                       AND created_at > (NOW() - INTERVAL {$window} SECOND)",
                    ['ip' => $ipAddress]
                );

                if ($ipRow && (int)$ipRow['failures'] >= $maxIp) {
                    return [
                        'limited'     => true,
                        'retry_after' => max(1, (int)$ipRow['retry_after']),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Fail open on infrastructure error so a logging outage cannot lock out every user;
            // OTP lockouts still protect the second factor.
            error_log('[User::checkLoginRateLimit] ' . get_class($e));
        }

        return ['limited' => false, 'retry_after' => 0];
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
                'ip_address'  => client_ip() ?? '127.0.0.1',
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
     * Count active administrator accounts, optionally excluding a specific user ID.
     * Essential for protecting against last-active-Admin lockout.
     *
     * @param int|null $excludeUserId
     * @return int
     */
    public function countActiveAdmins(?int $excludeUserId = null): int
    {
        if ($excludeUserId !== null) {
            $sql = "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'Active' AND id != :exclude_id";
            return (int)$this->fetchColumn($sql, ['exclude_id' => $excludeUserId]);
        }

        $sql = "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'Active'";
        return (int)$this->fetchColumn($sql);
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
        try {
            $sql = "UPDATE users SET role = :role, role_id = (SELECT id FROM roles WHERE slug = :role_slug LIMIT 1), status = :status WHERE id = :id";
            return $this->execute($sql, [
                'role'      => $role,
                'role_slug' => strtolower(trim($role)),
                'status'    => $status,
                'id'        => $id,
            ]);
        } catch (\Throwable) {
            $sql = "UPDATE users SET role = :role, status = :status WHERE id = :id";
            return $this->execute($sql, [
                'role'   => $role,
                'status' => $status,
                'id'     => $id,
            ]);
        }
    }
}

