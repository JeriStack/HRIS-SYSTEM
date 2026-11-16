<?php

/**
 * Centralized Audit Trail & Security Activity Monitoring Service
 * Employee HRIS & Leave Management System
 *
 * Implements authoritative event recording, sensitive field redaction,
 * before-and-after change diffing, and query services for compliance auditing.
 */

namespace Core;

use PDO;
use Throwable;

class AuditService
{
    public const CATEGORY_AUTH = 'auth';
    public const CATEGORY_USER = 'user';
    public const CATEGORY_RBAC = 'rbac';
    public const CATEGORY_EMPLOYEE = 'employee';
    public const CATEGORY_ORGANIZATION = 'organization';
    public const CATEGORY_LEAVE = 'leave';
    public const CATEGORY_SUPPORT = 'support';
    public const CATEGORY_ANNOUNCEMENT = 'announcement';
    public const CATEGORY_REPORTS = 'reports';
    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_GENERAL = 'general';

    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILURE = 'FAILURE';
    public const STATUS_WARNING = 'WARNING';

    private const SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'current_password',
        'new_password',
        'confirm_password',
        'token',
        'secret',
        'otp',
        'totp',
        'totp_secret',
        'auth_fingerprint',
        'session_id',
        'cookie',
        'api_key',
        'private_key',
        'credit_card',
    ];

    private static ?self $instance = null;
    private static ?string $currentRequestId = null;
    private ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public static function getInstance(?PDO $pdo = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($pdo);
        } elseif ($pdo !== null) {
            self::$instance->pdo = $pdo;
        }

        return self::$instance;
    }

    /**
     * Resolve the active database connection lazily.
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
     * Get or generate a persistent correlation request ID for this execution cycle.
     */
    public static function getRequestId(): string
    {
        if (self::$currentRequestId === null) {
            try {
                self::$currentRequestId = 'req_' . bin2hex(random_bytes(10));
            } catch (Throwable) {
                self::$currentRequestId = 'req_' . uniqid('', true);
            }
        }

        return self::$currentRequestId;
    }

    /**
     * Explicitly set or override request correlation ID (e.g. for testing).
     */
    public static function setRequestId(?string $requestId): void
    {
        self::$currentRequestId = $requestId;
    }

    /**
     * Recursively redact sensitive keys from payloads and values.
     *
     * @param mixed $data
     * @return mixed
     */
    public static function redact(mixed $data): mixed
    {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $val) {
                $keyStr = strtolower((string)$key);
                $isSensitive = false;
                foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                    if (str_contains($keyStr, $sensitiveKey)) {
                        $isSensitive = true;
                        break;
                    }
                }

                if ($isSensitive) {
                    $cleaned[$key] = '[REDACTED]';
                } else {
                    $cleaned[$key] = self::redact($val);
                }
            }
            return $cleaned;
        }

        if (is_string($data)) {
            // Mask any raw private key content if present
            if (preg_match('/BEGIN\s+[A-Z\s]*PRIVATE\s+KEY/', $data)) {
                return '[REDACTED_CRYPTOGRAPHIC_KEY]';
            }
        }

        return $data;
    }

    /**
     * Generate a cryptographically random unique event ID.
     */
    public static function generateEventId(): string
    {
        try {
            return 'evt_' . bin2hex(random_bytes(12));
        } catch (Throwable) {
            return 'evt_' . uniqid('', true);
        }
    }

    /**
     * Infer the logical functional category based on action token.
     */
    public static function inferCategory(string $action): string
    {
        $actionUpper = strtoupper(trim($action));

        if (
            str_starts_with($actionUpper, 'AUTH_') ||
            str_contains($actionUpper, 'LOGIN') ||
            str_contains($actionUpper, 'LOGOUT') ||
            str_starts_with($actionUpper, 'PASSWORD_') ||
            str_starts_with($actionUpper, 'MFA_') ||
            str_starts_with($actionUpper, 'OTP_')
        ) {
            return self::CATEGORY_AUTH;
        }

        if (str_starts_with($actionUpper, 'USER_') || str_starts_with($actionUpper, 'ACCOUNT_')) {
            return self::CATEGORY_USER;
        }

        if (str_starts_with($actionUpper, 'ROLE_') || str_starts_with($actionUpper, 'PERM_') || str_starts_with($actionUpper, 'RBAC_')) {
            return self::CATEGORY_RBAC;
        }

        if (str_starts_with($actionUpper, 'EMPLOYEE_') || str_starts_with($actionUpper, 'STAFF_')) {
            return self::CATEGORY_EMPLOYEE;
        }

        if (str_starts_with($actionUpper, 'DEPARTMENT_') || str_starts_with($actionUpper, 'POSITION_')) {
            return self::CATEGORY_ORGANIZATION;
        }

        if (str_starts_with($actionUpper, 'LEAVE_')) {
            return self::CATEGORY_LEAVE;
        }

        if (
            str_starts_with($actionUpper, 'SUPPORT_') ||
            str_starts_with($actionUpper, 'HR_SUPPORT_') ||
            str_starts_with($actionUpper, 'COMPLAINT_')
        ) {
            return self::CATEGORY_SUPPORT;
        }

        if (str_starts_with($actionUpper, 'ANNOUNCEMENT_')) {
            return self::CATEGORY_ANNOUNCEMENT;
        }

        if (str_starts_with($actionUpper, 'REPORT_') || str_starts_with($actionUpper, 'AUDIT_') || str_starts_with($actionUpper, 'EXPORT_')) {
            return self::CATEGORY_REPORTS;
        }

        if (str_starts_with($actionUpper, 'SETTING') || str_starts_with($actionUpper, 'SYSTEM_') || str_starts_with($actionUpper, 'CONFIG_')) {
            return self::CATEGORY_SYSTEM;
        }

        return self::CATEGORY_GENERAL;
    }

    /**
     * Record a standardized audit event into activity_logs.
     *
     * @param array<string, mixed> $params
     * @return string Unique Event ID
     */
    public function record(array $params): string
    {
        $action = trim((string)($params['action'] ?? 'GENERIC_EVENT'));
        $description = trim((string)($params['description'] ?? 'System activity recorded'));
        $category = !empty($params['category']) ? trim((string)$params['category']) : self::inferCategory($action);
        $status = !empty($params['status']) ? strtoupper(trim((string)$params['status'])) : self::STATUS_SUCCESS;

        $userId = array_key_exists('actor_user_id', $params)
            ? ($params['actor_user_id'] !== null ? (int)$params['actor_user_id'] : null)
            : (array_key_exists('user_id', $params) ? ($params['user_id'] !== null ? (int)$params['user_id'] : null) : null);

        if ($userId === null && function_exists('currentUserId')) {
            $currentId = currentUserId();
            $userId = $currentId !== null ? (int)$currentId : null;
        }

        $actorRole = !empty($params['actor_role']) ? strtolower(trim((string)$params['actor_role'])) : null;
        if ($actorRole === null && function_exists('currentUserRole')) {
            $actorRole = currentUserRole();
        }

        $entityType = !empty($params['entity_type']) ? trim((string)$params['entity_type']) : (!empty($params['resource_type']) ? trim((string)$params['resource_type']) : null);
        $entityId = isset($params['entity_id']) && $params['entity_id'] !== '' ? (string)$params['entity_id'] : (isset($params['resource_id']) && $params['resource_id'] !== '' ? (string)$params['resource_id'] : null);

        // Sanitize and redact state-change values
        $oldValues = array_key_exists('old_values', $params) && $params['old_values'] !== null ? self::redact($params['old_values']) : null;
        $newValues = array_key_exists('new_values', $params) && $params['new_values'] !== null ? self::redact($params['new_values']) : null;

        $oldValuesJson = $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $newValuesJson = $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        // Generate cryptographically random event ID
        $eventId = !empty($params['event_id']) ? (string)$params['event_id'] : self::generateEventId();

        $ip = !empty($params['ip_address']) ? (string)$params['ip_address'] : (function_exists('client_ip') ? (client_ip() ?? '127.0.0.1') : '127.0.0.1');
        $agent = !empty($params['user_agent']) ? substr((string)$params['user_agent'], 0, 255) : substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255);
        $requestId = !empty($params['request_id']) ? (string)$params['request_id'] : self::getRequestId();

        $pdo = $this->getPdo();
        if ($pdo !== null) {
            try {
                $sql = "
                    INSERT INTO activity_logs (
                        event_id,
                        user_id,
                        actor_role,
                        action,
                        category,
                        description,
                        entity_type,
                        entity_id,
                        old_values,
                        new_values,
                        status,
                        ip_address,
                        user_agent,
                        request_id,
                        created_at
                    ) VALUES (
                        :event_id,
                        :user_id,
                        :actor_role,
                        :action,
                        :category,
                        :description,
                        :entity_type,
                        :entity_id,
                        :old_values,
                        :new_values,
                        :status,
                        :ip_address,
                        :user_agent,
                        :request_id,
                        NOW()
                    )
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'event_id'    => $eventId,
                    'user_id'     => $userId,
                    'actor_role'  => $actorRole,
                    'action'      => $action,
                    'category'    => $category,
                    'description' => $description,
                    'entity_type' => $entityType,
                    'entity_id'   => $entityId,
                    'old_values'  => $oldValuesJson,
                    'new_values'  => $newValuesJson,
                    'status'      => $status,
                    'ip_address'  => $ip,
                    'user_agent'  => $agent,
                    'request_id'  => $requestId,
                ]);
            } catch (Throwable $e) {
                error_log('[Core\AuditService::record] Persistence failure: ' . $e->getMessage());
            }
        }

        return $eventId;
    }

    /**
     * Retrieve filtered audit logs with actor and employee profile details.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getFiltered(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return [];
        }

        $sql = "
            SELECT 
                a.*,
                u.username,
                u.email AS user_email,
                u.role AS user_current_role,
                e.first_name,
                e.last_name,
                e.employee_no
            FROM activity_logs a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN employees e ON u.employee_id = e.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $sql .= " AND (
                a.description LIKE :q 
                OR a.action LIKE :q 
                OR a.event_id LIKE :q 
                OR a.ip_address LIKE :q 
                OR a.entity_id LIKE :q 
                OR u.username LIKE :q 
                OR u.email LIKE :q 
                OR e.first_name LIKE :q 
                OR e.last_name LIKE :q 
                OR e.employee_no LIKE :q
            )";
            $params['q'] = "%{$q}%";
        }

        if (!empty($filters['category'])) {
            $sql .= " AND a.category = :category";
            $params['category'] = trim((string)$filters['category']);
        }

        if (!empty($filters['action'])) {
            $sql .= " AND a.action = :action";
            $params['action'] = trim((string)$filters['action']);
        }

        if (!empty($filters['status'])) {
            $sql .= " AND a.status = :status";
            $params['status'] = strtoupper(trim((string)$filters['status']));
        }

        if (!empty($filters['role'])) {
            $sql .= " AND (a.actor_role = :role OR u.role = :role)";
            $params['role'] = strtolower(trim((string)$filters['role']));
        }

        if (!empty($filters['from_date'])) {
            $sql .= " AND a.created_at >= :from_date";
            $params['from_date'] = trim((string)$filters['from_date']) . ' 00:00:00';
        }

        if (!empty($filters['to_date'])) {
            $sql .= " AND a.created_at <= :to_date";
            $params['to_date'] = trim((string)$filters['to_date']) . ' 23:59:59';
        }

        if (!empty($filters['entity_type'])) {
            $sql .= " AND a.entity_type = :entity_type";
            $params['entity_type'] = trim((string)$filters['entity_type']);
        }

        if (!empty($filters['entity_id'])) {
            $sql .= " AND a.entity_id = :entity_id";
            $params['entity_id'] = trim((string)$filters['entity_id']);
        }

        $sql .= " ORDER BY a.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total matching records for server-side pagination.
     *
     * @param array<string, mixed> $filters
     */
    public function countFiltered(array $filters = []): int
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return 0;
        }

        $sql = "
            SELECT COUNT(*)
            FROM activity_logs a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN employees e ON u.employee_id = e.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $sql .= " AND (
                a.description LIKE :q 
                OR a.action LIKE :q 
                OR a.event_id LIKE :q 
                OR a.ip_address LIKE :q 
                OR a.entity_id LIKE :q 
                OR u.username LIKE :q 
                OR u.email LIKE :q 
                OR e.first_name LIKE :q 
                OR e.last_name LIKE :q 
                OR e.employee_no LIKE :q
            )";
            $params['q'] = "%{$q}%";
        }

        if (!empty($filters['category'])) {
            $sql .= " AND a.category = :category";
            $params['category'] = trim((string)$filters['category']);
        }

        if (!empty($filters['action'])) {
            $sql .= " AND a.action = :action";
            $params['action'] = trim((string)$filters['action']);
        }

        if (!empty($filters['status'])) {
            $sql .= " AND a.status = :status";
            $params['status'] = strtoupper(trim((string)$filters['status']));
        }

        if (!empty($filters['role'])) {
            $sql .= " AND (a.actor_role = :role OR u.role = :role)";
            $params['role'] = strtolower(trim((string)$filters['role']));
        }

        if (!empty($filters['from_date'])) {
            $sql .= " AND a.created_at >= :from_date";
            $params['from_date'] = trim((string)$filters['from_date']) . ' 00:00:00';
        }

        if (!empty($filters['to_date'])) {
            $sql .= " AND a.created_at <= :to_date";
            $params['to_date'] = trim((string)$filters['to_date']) . ' 23:59:59';
        }

        if (!empty($filters['entity_type'])) {
            $sql .= " AND a.entity_type = :entity_type";
            $params['entity_type'] = trim((string)$filters['entity_type']);
        }

        if (!empty($filters['entity_id'])) {
            $sql .= " AND a.entity_id = :entity_id";
            $params['entity_id'] = trim((string)$filters['entity_id']);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Compute real database-backed audit statistics.
     *
     * @return array<string, int>
     */
    public function getStats(): array
    {
        $pdo = $this->getPdo();
        $defaults = [
            'total_events'          => 0,
            'success_count'         => 0,
            'failure_count'         => 0,
            'security_count'        => 0,
            'recent_admin_changes'  => 0,
        ];

        if ($pdo === null) {
            return $defaults;
        }

        try {
            $sql = "
                SELECT
                    COUNT(*) AS total_events,
                    SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) AS success_count,
                    SUM(CASE WHEN status = 'FAILURE' THEN 1 ELSE 0 END) AS failure_count,
                    SUM(CASE WHEN category IN ('auth', 'rbac') OR action LIKE '%SECURITY%' OR action LIKE '%LOCKOUT%' THEN 1 ELSE 0 END) AS security_count,
                    SUM(CASE WHEN (actor_role = 'admin' OR action LIKE '%ROLE%' OR action LIKE '%ADMIN%') AND created_at >= NOW() - INTERVAL 24 HOUR THEN 1 ELSE 0 END) AS recent_admin_changes
                FROM activity_logs
            ";
            $stmt = $pdo->query($sql);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'total_events'         => (int)($row['total_events'] ?? 0),
                    'success_count'        => (int)($row['success_count'] ?? 0),
                    'failure_count'        => (int)($row['failure_count'] ?? 0),
                    'security_count'       => (int)($row['security_count'] ?? 0),
                    'recent_admin_changes' => (int)($row['recent_admin_changes'] ?? 0),
                ];
            }
        } catch (Throwable $e) {
            error_log('[Core\AuditService::getStats] ' . $e->getMessage());
        }

        return $defaults;
    }

    /**
     * Retrieve audit history for a specific resource entity (e.g. Employee #5).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getResourceHistory(string $entityType, string $entityId, int $limit = 20): array
    {
        return $this->getFiltered([
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
        ], $limit, 0);
    }

    /**
     * Get distinct actions currently logged in the database.
     *
     * @return array<int, string>
     */
    public function getDistinctActions(): array
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return [];
        }

        try {
            $stmt = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC");
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Export filtered audit records to clean CSV format with spreadsheet formula injection defense.
     *
     * @param array<string, mixed> $filters
     */
    public function exportCsv(array $filters = []): string
    {
        $logs = $this->getFiltered($filters, 2000, 0);

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        // CSV Header
        fputcsv($output, [
            'Log ID',
            'Event ID',
            'Timestamp (UTC)',
            'Actor Name',
            'Username',
            'Actor Role',
            'Category',
            'Action',
            'Status',
            'Resource Type',
            'Resource ID',
            'Description',
            'IP Address',
            'Request ID',
        ]);

        foreach ($logs as $log) {
            $fullName = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''));
            if ($fullName === '') {
                $fullName = $log['username'] ?? 'System';
            }

            fputcsv($output, [
                $this->sanitizeCsvField((string)$log['id']),
                $this->sanitizeCsvField((string)($log['event_id'] ?? '')),
                $this->sanitizeCsvField((string)$log['created_at']),
                $this->sanitizeCsvField($fullName),
                $this->sanitizeCsvField((string)($log['username'] ?? 'System')),
                $this->sanitizeCsvField((string)($log['actor_role'] ?? $log['user_current_role'] ?? '')),
                $this->sanitizeCsvField((string)($log['category'] ?? 'general')),
                $this->sanitizeCsvField((string)$log['action']),
                $this->sanitizeCsvField((string)($log['status'] ?? 'SUCCESS')),
                $this->sanitizeCsvField((string)($log['entity_type'] ?? '')),
                $this->sanitizeCsvField((string)($log['entity_id'] ?? '')),
                $this->sanitizeCsvField((string)$log['description']),
                $this->sanitizeCsvField((string)($log['ip_address'] ?? '')),
                $this->sanitizeCsvField((string)($log['request_id'] ?? '')),
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        // Record audit event for the export operation itself
        $this->record([
            'action'      => 'AUDIT_EXPORTED',
            'category'    => self::CATEGORY_REPORTS,
            'description' => 'Exported ' . count($logs) . ' audit records to CSV',
            'status'      => self::STATUS_SUCCESS,
        ]);

        return $csvContent !== false ? $csvContent : '';
    }

    /**
     * Neutralize formula injection in CSV exports (=, +, -, @, tab, carriage return).
     */
    public static function sanitizeCsvField(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
