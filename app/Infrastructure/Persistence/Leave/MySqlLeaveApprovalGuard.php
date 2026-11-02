<?php

/**
 * MySqlLeaveApprovalGuard
 * HRIS-SYSTEM — Infrastructure Layer (Leave Domain Persistence)
 *
 * Reads live account/organization state so ApproveLeaveRequestUseCase can
 * re-validate every approval invariant inside the approval transaction.
 * Rows are read FOR UPDATE where the value gates a state change, matching the
 * locking discipline of the legacy Leave::approveRequest implementation.
 */

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Leave;

use App\Domain\Leave\Repositories\LeaveApprovalGuardInterface;
use PDO;

class MySqlLeaveApprovalGuard implements LeaveApprovalGuardInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function activeHrReviewerEmployeeId(int $reviewerUserId): ?int
    {
        $stmt = $this->pdo->prepare("
            SELECT employee_id, role, status
            FROM users
            WHERE id = :uid
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute(['uid' => $reviewerUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || strtolower((string)($row['role'] ?? '')) !== 'hr' || ($row['status'] ?? '') !== 'Active') {
            return null;
        }

        // A reviewer may have no linked employee record; 0 is a sentinel that can
        // never equal a real employee id, so self-approval comparison stays safe.
        return $row['employee_id'] !== null ? (int)$row['employee_id'] : 0;
    }

    public function isEmployeeActive(int $employeeId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT employment_status
            FROM employees
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute(['id' => $employeeId]);
        $status = $stmt->fetchColumn();

        return $status !== false && (string)$status === 'Active';
    }

    public function isLeaveTypeActive(int $leaveTypeId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT status
            FROM leave_types
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute(['id' => $leaveTypeId]);
        $status = $stmt->fetchColumn();

        return $status !== false && (string)$status === 'Active';
    }
}
