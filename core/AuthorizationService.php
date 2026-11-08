<?php

/**
 * Centralized Database-Driven Authorization Service
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Provides authoritative, server-side, fail-closed permission evaluation,
 * dynamic database loading, permission caching, prompt cache invalidation,
 * and transaction-safe role-permission management.
 */

namespace Core;

use App\Models\ActivityLog;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class AuthorizationService
{
    private static ?self $instance = null;

    /**
     * Runtime in-memory cache of role permissions: [role_slug => [permission_slug => true]]
     *
     * @var array<string, array<string, bool>>
     */
    private static array $permissionCache = [];

    private ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * Singleton instance accessor.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Resolve the active database PDO connection lazily.
     */
    protected function getPdo(): ?PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        try {
            if (class_exists(Database::class) && Database::getInstance()->isConnected()) {
                $this->pdo = Database::getInstance()->getConnection();
            }
        } catch (Throwable) {
            $this->pdo = null;
        }

        return $this->pdo;
    }

    /**
     * Invalidate cached permissions for one or all roles.
     */
    public static function invalidateCache(?string $role = null): void
    {
        if ($role !== null) {
            unset(self::$permissionCache[strtolower(trim($role))]);
        } else {
            self::$permissionCache = [];
        }
    }

    /**
     * Normalize an input user or role representation to a clean role slug.
     * Accepts:
     * - A string role ('admin', 'hr', 'employee')
     * - A user array with 'role' key
     * - An object with ->role property
     */
    public function resolveRole(mixed $userOrRole): ?string
    {
        if (is_string($userOrRole)) {
            $clean = strtolower(trim($userOrRole));
            return $clean !== '' ? $clean : null;
        }

        if (is_array($userOrRole) && isset($userOrRole['role']) && is_string($userOrRole['role'])) {
            $clean = strtolower(trim($userOrRole['role']));
            return $clean !== '' ? $clean : null;
        }

        if (is_object($userOrRole) && isset($userOrRole->role) && is_string($userOrRole->role)) {
            $clean = strtolower(trim($userOrRole->role));
            return $clean !== '' ? $clean : null;
        }

        return null;
    }

    /**
     * Core authorization check: determine if a user or role possesses a permission.
     * Deny-by-default: returns false if role is invalid or permission is unrecognized.
     *
     * @param mixed $userOrRole User array/object or role string
     * @param string $permission Permission slug or capability token
     * @return bool
     */
    public function can(mixed $userOrRole, string $permission): bool
    {
        $role = $this->resolveRole($userOrRole);
        if ($role === null || !in_array($role, PermissionRegistry::ROLES, true) && !in_array($role, ['admin', 'hr', 'employee'], true)) {
            return false;
        }

        $canonicalPerm = PermissionRegistry::normalize($permission);
        if (!PermissionRegistry::isValidPermission($canonicalPerm) && !PermissionRegistry::isValidPermission($permission)) {
            return false;
        }

        // Mutual exclusion rule for role-specific dashboard views
        if ($permission === 'admin_dashboard') {
            return $role === PermissionRegistry::ROLE_ADMIN;
        }
        if ($permission === 'hr_dashboard') {
            return $role === PermissionRegistry::ROLE_HR;
        }
        if ($permission === 'employee_dashboard') {
            return $role === PermissionRegistry::ROLE_EMPLOYEE;
        }

        // Admin has guaranteed full application authority across all valid registered permissions
        if ($role === PermissionRegistry::ROLE_ADMIN) {
            return true;
        }

        // Check assigned permissions for HR and Employee roles
        $permissions = $this->getPermissions($role);

        // Check canonical slug first, then fallback to raw input token if different
        if (isset($permissions[$canonicalPerm]) && $permissions[$canonicalPerm] === true) {
            return true;
        }

        if (isset($permissions[$permission]) && $permissions[$permission] === true) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a user possesses a specific role.
     */
    public function hasRole(mixed $userOrRole, string $role): bool
    {
        $actualRole = $this->resolveRole($userOrRole);
        if ($actualRole === null) {
            return false;
        }

        return $actualRole === strtolower(trim($role));
    }

    /**
     * Determine if a user possesses ANY of the given permissions.
     *
     * @param mixed $userOrRole
     * @param array<string> $permissions
     * @return bool
     */
    public function hasAnyPermission(mixed $userOrRole, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($userOrRole, $permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine if a user possesses ALL of the given permissions.
     *
     * @param mixed $userOrRole
     * @param array<string> $permissions
     * @return bool
     */
    public function hasAllPermissions(mixed $userOrRole, array $permissions): bool
    {
        if (empty($permissions)) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (!$this->can($userOrRole, $permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Retrieve all active permissions for a given role as an associative array:
     * ['permission.slug' => true, ...]
     *
     * @param mixed $userOrRole
     * @return array<string, bool>
     */
    public function getPermissions(mixed $userOrRole): array
    {
        $role = $this->resolveRole($userOrRole);
        if ($role === null) {
            return [];
        }

        // Return from memory cache if available
        if (isset(self::$permissionCache[$role])) {
            return self::$permissionCache[$role];
        }

        // Admin has all registered permissions
        if ($role === PermissionRegistry::ROLE_ADMIN) {
            $adminPerms = [];
            foreach (PermissionRegistry::PERMISSIONS as $slug => $meta) {
                $adminPerms[$slug] = true;
            }
            foreach (PermissionRegistry::CAPABILITY_ALIASES as $alias => $slug) {
                $adminPerms[$alias] = true;
            }
            self::$permissionCache[$role] = $adminPerms;
            return $adminPerms;
        }

        // Attempt dynamic load from database
        $dbPermissions = $this->loadPermissionsFromDatabase($role);
        if ($dbPermissions !== null) {
            // Also map capability aliases for backward-compatibility
            foreach (PermissionRegistry::CAPABILITY_ALIASES as $alias => $slug) {
                if (!empty($dbPermissions[$slug])) {
                    $dbPermissions[$alias] = true;
                }
            }
            self::$permissionCache[$role] = $dbPermissions;
            return $dbPermissions;
        }

        // Fallback to authoritative defaults in PermissionRegistry
        $fallback = [];
        $defaults = PermissionRegistry::getDefaultRoleCapabilities();
        foreach ($defaults as $token => $allowedRoles) {
            if (in_array($role, $allowedRoles, true)) {
                $fallback[$token] = true;
            }
        }

        self::$permissionCache[$role] = $fallback;
        return $fallback;
    }

    /**
     * Query active permissions for a role from the database.
     *
     * @param string $roleSlug
     * @return array<string, bool>|null Null if database query cannot be completed
     */
    private function loadPermissionsFromDatabase(string $roleSlug): ?array
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT p.slug
                FROM role_permissions rp
                INNER JOIN roles r ON rp.role_id = r.id
                INNER JOIN permissions p ON rp.permission_id = p.id
                WHERE r.slug = :role
            ");
            $stmt->execute(['role' => $roleSlug]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $result = [];
            foreach ($rows as $slug) {
                $result[(string)$slug] = true;
            }

            return $result;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Retrieve roles overview for the Administration dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRolesOverview(): array
    {
        $pdo = $this->getPdo();
        $defaults = [
            [
                'id' => 1,
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Full system-wide administrative governance and security management',
                'is_system' => 1,
                'user_count' => 1,
                'permission_count' => count(PermissionRegistry::PERMISSIONS),
            ],
            [
                'id' => 2,
                'name' => 'Human Resources',
                'slug' => 'hr',
                'description' => 'Operational human resource and workforce operations management',
                'is_system' => 1,
                'user_count' => 2,
                'permission_count' => count(array_filter(
                    PermissionRegistry::PERMISSIONS,
                    fn ($p) => in_array('hr', $p['default_roles'], true)
                )),
            ],
            [
                'id' => 3,
                'name' => 'Employee',
                'slug' => 'employee',
                'description' => 'Employee personal self-service workspace and leave management',
                'is_system' => 1,
                'user_count' => 4,
                'permission_count' => count(array_filter(
                    PermissionRegistry::PERMISSIONS,
                    fn ($p) => in_array('employee', $p['default_roles'], true)
                )),
            ],
        ];

        if ($pdo === null) {
            return $defaults;
        }

        try {
            $sql = "
                SELECT 
                    r.id,
                    r.name,
                    r.slug,
                    r.description,
                    r.is_system,
                    COUNT(DISTINCT u.id) AS user_count,
                    COUNT(DISTINCT rp.permission_id) AS permission_count
                FROM roles r
                LEFT JOIN users u ON (u.role_id = r.id OR u.role = r.slug)
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                GROUP BY r.id, r.name, r.slug, r.description, r.is_system
                ORDER BY r.id ASC
            ";
            $stmt = $pdo->query($sql);
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($roles)) {
                return $defaults;
            }

            // Guarantee Admin displays total count of registered permissions
            foreach ($roles as &$roleRow) {
                if ($roleRow['slug'] === PermissionRegistry::ROLE_ADMIN) {
                    $roleRow['permission_count'] = count(PermissionRegistry::PERMISSIONS);
                }
            }

            return $roles;
        } catch (Throwable) {
            return $defaults;
        }
    }

    /**
     * Retrieve a matrix map of role permissions:
     * ['admin' => ['perm.slug' => true], 'hr' => [...], 'employee' => [...]]
     *
     * @return array<string, array<string, bool>>
     */
    public function getRolePermissionsMap(): array
    {
        $map = [
            PermissionRegistry::ROLE_ADMIN    => [],
            PermissionRegistry::ROLE_HR       => [],
            PermissionRegistry::ROLE_EMPLOYEE => [],
        ];

        // Admin always has all registered permissions
        foreach (PermissionRegistry::PERMISSIONS as $slug => $meta) {
            $map[PermissionRegistry::ROLE_ADMIN][$slug] = true;
        }

        $map[PermissionRegistry::ROLE_HR] = $this->getPermissions(PermissionRegistry::ROLE_HR);
        $map[PermissionRegistry::ROLE_EMPLOYEE] = $this->getPermissions(PermissionRegistry::ROLE_EMPLOYEE);

        return $map;
    }

    /**
     * Transaction-safe update of role permission assignments.
     *
     * Non-negotiable security safeguards:
     * 1. Cannot alter the 'admin' system role (Admin retains full application authority).
     * 2. Validates all submitted permission slugs against the authoritative registry.
     * 3. Executes atomically in a database transaction with rollback on error.
     * 4. Logs every granted and revoked permission in permission_audit_logs.
     * 5. Prompts immediate cache invalidation.
     *
     * @param int $actorUserId The administrator making the modification
     * @param string $roleSlug Target role ('hr' or 'employee')
     * @param array<string> $permissionSlugs Selected active permission slugs
     * @param string $ipAddress Client IP address
     * @param string $userAgent Client browser/device user agent
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function updateRolePermissions(
        int $actorUserId,
        string $roleSlug,
        array $permissionSlugs,
        string $ipAddress,
        string $userAgent
    ): bool {
        $normalizedRole = strtolower(trim($roleSlug));

        // Invariant 1: Admin role is immutable and cannot be edited via ordinary role management
        if ($normalizedRole === PermissionRegistry::ROLE_ADMIN) {
            throw new InvalidArgumentException("The 'admin' system role is immutable and retains full application authority.");
        }

        if (!in_array($normalizedRole, [PermissionRegistry::ROLE_HR, PermissionRegistry::ROLE_EMPLOYEE], true)) {
            throw new InvalidArgumentException("Invalid target role: '{$roleSlug}'. Only HR and Employee permissions can be configured.");
        }

        // Invariant 2: Validate all submitted permission slugs against the authoritative registry
        $validSlugs = array_keys(PermissionRegistry::PERMISSIONS);
        $submittedSlugs = array_values(array_unique(array_filter(array_map('trim', $permissionSlugs))));

        foreach ($submittedSlugs as $slug) {
            if (!in_array($slug, $validSlugs, true)) {
                throw new InvalidArgumentException("Unrecognized permission identifier: '{$slug}'. Malformed or arbitrary permissions are rejected.");
            }
        }

        $pdo = $this->getPdo();
        if ($pdo === null) {
            throw new RuntimeException("Database connection is not available to persist permission changes.");
        }

        $pdo->beginTransaction();

        try {
            // Find role ID
            $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE slug = :slug LIMIT 1");
            $roleStmt->execute(['slug' => $normalizedRole]);
            $roleId = $roleStmt->fetchColumn();

            if ($roleId === false) {
                throw new RuntimeException("Role '{$normalizedRole}' was not found in the roles table.");
            }
            $roleId = (int)$roleId;

            // Fetch current active permission slugs for this role to determine audit diff
            $currentStmt = $pdo->prepare("
                SELECT p.id, p.slug
                FROM role_permissions rp
                INNER JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role_id = :role_id
            ");
            $currentStmt->execute(['role_id' => $roleId]);
            $currentRows = $currentStmt->fetchAll(PDO::FETCH_ASSOC);

            $currentSlugs = array_column($currentRows, 'slug');
            $currentPermMap = array_column($currentRows, 'id', 'slug');

            // Compute granted and revoked diffs
            $toGrant = array_diff($submittedSlugs, $currentSlugs);
            $toRevoke = array_diff($currentSlugs, $submittedSlugs);

            // Fetch permission IDs for submitted slugs
            $permIdMap = [];
            if (!empty($submittedSlugs)) {
                $placeholders = implode(',', array_fill(0, count($submittedSlugs), '?'));
                $permStmt = $pdo->prepare("SELECT id, slug FROM permissions WHERE slug IN ($placeholders)");
                $permStmt->execute($submittedSlugs);
                $permIdMap = $permStmt->fetchAll(PDO::FETCH_KEY_PAIR); // [id => slug]
                $permSlugToId = array_flip($permIdMap); // [slug => id]
            }

            // Remove revoked permissions
            if (!empty($toRevoke)) {
                $deleteStmt = $pdo->prepare("
                    DELETE FROM role_permissions 
                    WHERE role_id = :role_id AND permission_id = :permission_id
                ");
                $auditStmt = $pdo->prepare("
                    INSERT INTO permission_audit_logs 
                    (actor_user_id, target_role_id, permission_id, action, old_value, new_value, ip_address, user_agent, created_at)
                    VALUES (:actor_id, :role_id, :perm_id, 'REVOKED', 'ACTIVE', 'REVOKED', :ip, :agent, NOW())
                ");

                foreach ($toRevoke as $revokedSlug) {
                    $permId = $currentPermMap[$revokedSlug] ?? null;
                    if ($permId !== null) {
                        $deleteStmt->execute([
                            'role_id'       => $roleId,
                            'permission_id' => $permId,
                        ]);
                        $auditStmt->execute([
                            'actor_id' => $actorUserId,
                            'role_id'  => $roleId,
                            'perm_id'  => $permId,
                            'ip'       => substr($ipAddress, 0, 45),
                            'agent'    => substr($userAgent, 0, 255),
                        ]);
                    }
                }
            }

            // Add newly granted permissions
            if (!empty($toGrant)) {
                $insertStmt = $pdo->prepare("
                    INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
                    VALUES (:role_id, :permission_id, NOW())
                ");
                $auditStmt = $pdo->prepare("
                    INSERT INTO permission_audit_logs 
                    (actor_user_id, target_role_id, permission_id, action, old_value, new_value, ip_address, user_agent, created_at)
                    VALUES (:actor_id, :role_id, :perm_id, 'GRANTED', 'REVOKED', 'ACTIVE', :ip, :agent, NOW())
                ");

                foreach ($toGrant as $grantedSlug) {
                    $permId = $permSlugToId[$grantedSlug] ?? null;
                    if ($permId !== null) {
                        $insertStmt->execute([
                            'role_id'       => $roleId,
                            'permission_id' => $permId,
                        ]);
                        $auditStmt->execute([
                            'actor_id' => $actorUserId,
                            'role_id'  => $roleId,
                            'perm_id'  => $permId,
                            'ip'       => substr($ipAddress, 0, 45),
                            'agent'    => substr($userAgent, 0, 255),
                        ]);
                    }
                }
            }

            // Record overall summary in activity_logs
            $summaryDesc = sprintf(
                "Updated '%s' role permissions: %d active (+%d granted, -%d revoked)",
                strtoupper($normalizedRole),
                count($submittedSlugs),
                count($toGrant),
                count($toRevoke)
            );

            try {
                if (class_exists(ActivityLog::class)) {
                    $actLog = new ActivityLog();
                    $actLog->log(
                        $actorUserId,
                        'PERMISSIONS_UPDATED',
                        $summaryDesc,
                        'Role',
                        (string)$roleId
                    );
                }
            } catch (Throwable $e) {
                error_log('[AuthorizationService::updateRolePermissions] activity log non-fatal: ' . $e->getMessage());
            }

            $pdo->commit();

            // Promptly invalidate in-memory permission cache
            self::invalidateCache($normalizedRole);

            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[AuthorizationService::updateRolePermissions] Transaction aborted: ' . $e->getMessage());
            throw $e;
        }
    }
}
