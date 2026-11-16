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
        $empStmt = $this->pdo->prepare("SELECT * FROM employees WHERE id = :id LIMIT 1");
        $empStmt->execute(['id' => $employeeId]);
        $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee) {
            return;
        }

        $policyEngine = new \App\Services\LeavePolicyEngine($this->pdo);
        $ledger = new \App\Services\LeaveLedgerService($this->pdo);
        $asOfDate = sprintf('%04d-12-31', $year);
        $types = $policyEngine->getAllTypesWithActivePolicies(true, $asOfDate);

        $existsStmt = $this->pdo->prepare("
            SELECT id FROM leave_balances
            WHERE employee_id = :emp_id AND leave_type_id = :lt_id AND year = :year
            LIMIT 1
        ");
        $insertStmt = $this->pdo->prepare("
            INSERT INTO leave_balances (
                employee_id, leave_type_id, year, allocated_days, used_days, remaining_days,
                policy_version_id, sil_credited_from_vl, eligibility_status, notes
            ) VALUES (
                :emp_id, :lt_id, :year, :alloc, 0, :rem,
                :pv_id, :sil_vl, :elig_status, :notes
            )
        ");

        foreach ($types as $type) {
            // Event-based and unpaid leave types are NOT ordinary annual balance quotas
            if (($type['entitlement_model'] ?? 'annual') !== 'annual') {
                continue;
            }

            $ltId = (int)$type['id'];
            $existsStmt->execute(['emp_id' => $employeeId, 'lt_id' => $ltId, 'year' => $year]);
            if ($existsStmt->fetchColumn() !== false) {
                continue;
            }

            $code = strtoupper((string)($type['leave_code'] ?? ''));
            $eval = $policyEngine->evaluateEmployeeTypeEligibility($employee, $type, $year, $asOfDate);

            // Only auto-allocate qualifying annual credits (skip ineligible SIL / unverified SPIC)
            if (in_array($code, [\App\Services\LeavePolicyEngine::CODE_SIL, \App\Services\LeavePolicyEngine::CODE_SOLO_PARENT], true) && empty($eval['eligible'])) {
                continue;
            }

            $allocDays = max(0, (int)round((float)($eval['entitled_days'] ?? $type['default_days'] ?? 0)));
            $insertStmt->execute([
                'emp_id'      => $employeeId,
                'lt_id'       => $ltId,
                'year'        => $year,
                'alloc'       => $allocDays,
                'rem'         => $allocDays,
                'pv_id'       => $type['policy_version_id'] ?? null,
                'sil_vl'      => !empty($eval['credited_via_vl']) ? 1 : 0,
                'elig_status' => 'Available',
                'notes'       => substr((string)($eval['reason'] ?? ''), 0, 250) ?: null,
            ]);

            $balanceId = (int)$this->pdo->lastInsertId();
            if ($balanceId > 0) {
                $ledger->record([
                    'employee_id'       => $employeeId,
                    'leave_type_id'     => $ltId,
                    'leave_balance_id'  => $balanceId,
                    'policy_version_id' => $type['policy_version_id'] ?? null,
                    'balance_year'      => $year,
                    'transaction_type'  => 'initial_allocation',
                    'days_delta'        => $allocDays,
                    'balance_before'    => 0,
                    'balance_after'     => $allocDays,
                    'remarks'           => sprintf('Annual allocation initialized for %s (%d)', $type['leave_name'], $year),
                    'is_confidential'   => !empty($type['is_confidential']) ? 1 : 0,
                ]);
            }
        }
    }
}
