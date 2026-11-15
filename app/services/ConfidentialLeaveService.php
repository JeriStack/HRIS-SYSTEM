<?php

/**
 * Confidential Leave Handling, Redaction & Access Audit Service
 * Enforces strict privacy for RA 9262 (VAWC Leave) and RA 9710 (Special Leave
 * for Women), preventing disclosure of medical diagnoses, abuse details, or
 * sensitive attachments in notifications, logs, queues, and shared reports.
 */

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Core\Permission;
use PDO;
use Throwable;

final class ConfidentialLeaveService
{
    public const PERMISSION_VIEW_CONFIDENTIAL = 'leave_confidential.view';

    /** @var string[] */
    public const CONFIDENTIAL_CODES = ['VAWC', 'SLW'];

    /** @var array<string, bool> */
    private static array $tableCache = [];

    /**
     * Determine whether a leave type classification requires confidential protection.
     *
     * @param array<string, mixed> $leaveType
     */
    public static function isConfidentialType(array $leaveType): bool
    {
        if (!empty($leaveType['is_confidential'])) {
            return true;
        }

        $code = strtoupper(trim((string)($leaveType['leave_code'] ?? '')));
        return in_array($code, self::CONFIDENTIAL_CODES, true);
    }

    /**
     * Determine whether a leave request record is confidential.
     *
     * @param array<string, mixed> $requestRow
     */
    public static function isConfidentialRequest(array $requestRow): bool
    {
        if (!empty($requestRow['is_confidential'])) {
            return true;
        }

        return self::isConfidentialType($requestRow);
    }

    /**
     * Determine whether a user may view unmasked confidential details for a request.
     *
     * @param array<string, mixed>|null $viewerUser
     * @param array<string, mixed> $requestRow
     */
    public static function canViewUnmasked(?array $viewerUser, array $requestRow, ?PDO $pdo = null): bool
    {
        if (!self::isConfidentialRequest($requestRow)) {
            return true;
        }

        if ($viewerUser === null) {
            return false;
        }

        $viewerEmpId = (int)($viewerUser['employee_id'] ?? 0);
        $ownerEmpId = (int)($requestRow['employee_id'] ?? 0);
        if ($viewerEmpId > 0 && $ownerEmpId > 0 && $viewerEmpId === $ownerEmpId) {
            return true;
        }

        return self::userHasConfidentialPermission($viewerUser, $pdo);
    }

    /**
     * Check whether the given user holds leave_confidential.view via RBAC.
     *
     * @param array<string, mixed> $viewerUser
     */
    public static function userHasConfidentialPermission(array $viewerUser, ?PDO $pdo = null): bool
    {
        $sessionUser = $_SESSION['user'] ?? null;
        if (
            is_array($sessionUser)
            && (int)($sessionUser['id'] ?? 0) === (int)($viewerUser['id'] ?? -1)
            && class_exists(Permission::class)
        ) {
            try {
                return Permission::can($viewerUser, self::PERMISSION_VIEW_CONFIDENTIAL);
            } catch (Throwable) {
                // Fall through to DB lookup when Permission cache cannot resolve in CLI/tests.
            }
        }

        $conn = $pdo;
        if ($conn === null) {
            try {
                $conn = Database::getInstance()->getConnection();
            } catch (Throwable) {
                $conn = null;
            }
        }

        $roleSlug = strtolower(trim((string)($viewerUser['role'] ?? '')));
        if ($conn !== null && self::tableExists($conn, 'role_permissions') && self::tableExists($conn, 'permissions')) {
            try {
                $roleId = isset($viewerUser['role_id']) ? (int)$viewerUser['role_id'] : 0;
                $sql = "
                    SELECT COUNT(*)
                    FROM role_permissions rp
                    INNER JOIN permissions p ON p.id = rp.permission_id
                    INNER JOIN roles r ON r.id = rp.role_id
                    WHERE p.slug = :slug
                      AND " . ($roleId > 0 ? 'r.id = :role_ref' : 'r.slug = :role_ref') . "
                ";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    'slug' => self::PERMISSION_VIEW_CONFIDENTIAL,
                    'role_ref' => $roleId > 0 ? $roleId : $roleSlug,
                ]);
                return ((int)$stmt->fetchColumn()) > 0;
            } catch (Throwable) {
                return false;
            }
        }

        return in_array($roleSlug, ['admin', 'hr'], true);
    }

    /**
     * Mask sensitive narrative fields on a leave request row if viewer lacks clearance.
     *
     * @param array<string, mixed> $requestRow
     * @param array<string, mixed>|null $viewerUser
     * @return array<string, mixed>
     */
    public static function maskRequestForViewer(array $requestRow, ?array $viewerUser, ?PDO $pdo = null): array
    {
        if (self::canViewUnmasked($viewerUser, $requestRow, $pdo)) {
            $requestRow['is_redacted'] = false;
            return $requestRow;
        }

        $masked = $requestRow;
        $masked['is_redacted'] = true;
        $masked['leave_name'] = 'Confidential Statutory Leave';
        $masked['leave_code'] = 'CONFIDENTIAL';
        $masked['reason'] = '[Confidential — Protected under Philippine Statutory Privacy Policy]';
        if (!empty($masked['rejection_reason'])) {
            $masked['rejection_reason'] = '[Confidential Decision Note Redacted]';
        }
        if (!empty($masked['hr_info_request_note'])) {
            $masked['hr_info_request_note'] = '[Confidential Information Request Redacted]';
        }
        if (!empty($masked['employee_info_response'])) {
            $masked['employee_info_response'] = '[Confidential Employee Response Redacted]';
        }
        $masked['application_metadata_json'] = null;

        return $masked;
    }

    public static function safeNotificationTitle(bool $isConfidential, string $defaultTitle): string
    {
        return $isConfidential ? 'Confidential Leave Application Update' : $defaultTitle;
    }

    public static function safeNotificationMessage(bool $isConfidential, int $requestId, string $defaultMessage): string
    {
        if (!$isConfidential) {
            return $defaultMessage;
        }

        return sprintf(
            'Confidential leave request #LR-%d requires authorized review. Open the protected leave record to view permitted details.',
            $requestId
        );
    }

    public static function safeActivityDescription(
        bool $isConfidential,
        int $requestId,
        string $actionVerb,
        string $defaultDescription
    ): string {
        if (!$isConfidential) {
            return $defaultDescription;
        }

        return sprintf('%s confidential leave request #%d (protected content omitted).', $actionVerb, $requestId);
    }

    /**
     * Record an audit trail entry whenever confidential leave details or documents are accessed.
     */
    public static function logSensitiveAccess(
        PDO $pdo,
        ?int $leaveRequestId,
        ?int $documentId,
        ?int $accessorUserId,
        string $actionType,
        bool $accessGranted = true,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        if (!self::tableExists($pdo, 'leave_sensitive_access_logs')) {
            return;
        }

        $allowedActions = [
            'view_request',
            'view_confidential_reason',
            'download_document',
            'export_redacted',
            'verify_qualification',
        ];
        if (!in_array($actionType, $allowedActions, true)) {
            $actionType = 'view_request';
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO leave_sensitive_access_logs (
                    leave_request_id, document_id, accessor_user_id,
                    action_type, access_granted, ip_address, user_agent
                ) VALUES (
                    :lr_id, :doc_id, :user_id, :action_type, :granted, :ip, :ua
                )
            ");
            $stmt->execute([
                'lr_id'       => $leaveRequestId,
                'doc_id'      => $documentId,
                'user_id'     => $accessorUserId,
                'action_type' => $actionType,
                'granted'     => $accessGranted ? 1 : 0,
                'ip'          => substr((string)($ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45) ?: null,
                'ua'          => substr((string)($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255) ?: null,
            ]);
        } catch (Throwable $e) {
            error_log('[ConfidentialLeaveService::logSensitiveAccess] ' . $e->getMessage());
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $key = $driver . ':' . $table;
        if (array_key_exists($key, self::$tableCache)) {
            return self::$tableCache[$key];
        }

        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :t");
                $stmt->execute(['t' => $table]);
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
                $stmt->execute(['t' => $table]);
            }
            $exists = ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable) {
            $exists = false;
        }

        self::$tableCache[$key] = $exists;
        return $exists;
    }

    public static function resetCache(): void
    {
        self::$tableCache = [];
    }
}
