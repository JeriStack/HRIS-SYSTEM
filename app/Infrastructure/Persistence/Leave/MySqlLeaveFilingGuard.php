<?php

/**
 * MySqlLeaveFilingGuard
 * HRIS-SYSTEM — Infrastructure Layer (Leave Domain Persistence)
 *
 * Reads live employment/leave-type state and reserved pending days, and performs
 * transaction-safe balance auto-initialization for the filing use case. All reads
 * run on the same PDO connection/transaction the use case opened.
 */

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Leave;

use App\Domain\Leave\Repositories\LeaveFilingGuardInterface;
use PDO;

class MySqlLeaveFilingGuard implements LeaveFilingGuardInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function isEmployeeActive(int $employeeId): bool
    {
        $stmt = $this->pdo->prepare("SELECT employment_status FROM employees WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $employeeId]);
        $status = $stmt->fetchColumn();
        return $status !== false && (string)$status === 'Active';
    }

    public function isLeaveTypeActive(int $leaveTypeId): bool
    {
        $stmt = $this->pdo->prepare("SELECT status FROM leave_types WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $leaveTypeId]);
        $status = $stmt->fetchColumn();
        return $status !== false && (string)$status === 'Active';
    }

    public function pendingReservedDays(int $employeeId, int $leaveTypeId, int $year): int
    {
        // Mirrors Leave::getPendingDays: sum Pending days for this employee/type in the year.
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(number_of_days), 0)
            FROM leave_requests
            WHERE employee_id = :emp_id
              AND leave_type_id = :lt_id
              AND YEAR(start_date) = :year
              AND status = 'Pending'
        ");
        $stmt->execute(['emp_id' => $employeeId, 'lt_id' => $leaveTypeId, 'year' => $year]);
        return (int)$stmt->fetchColumn();
    }

    public function ensureBalancesInitialized(int $employeeId, int $year): void
    {
        // Create any missing balance rows from active leave types' default allocations.
        // INSERT ... SELECT ... WHERE NOT EXISTS is evaluated atomically per row, and the
        // unique key (employee_id, leave_type_id, year) is the final guard against duplicates
        // under concurrent filings. Runs inside the caller's transaction.
        $stmt = $this->pdo->prepare("
            INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days)
            SELECT :emp_id, lt.id, :year, lt.default_days, 0, lt.default_days
            FROM leave_types lt
            WHERE lt.status = 'Active'
              AND NOT EXISTS (
                  SELECT 1 FROM leave_balances lb
                  WHERE lb.employee_id = :emp_id2
                    AND lb.leave_type_id = lt.id
                    AND lb.year = :year2
              )
        ");
        $stmt->execute([
            'emp_id'  => $employeeId,
            'year'    => $year,
            'emp_id2' => $employeeId,
            'year2'   => $year,
        ]);
    }
}
