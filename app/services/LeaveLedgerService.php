<?php

/**
 * Leave Balance Transaction Ledger Service
 * Append-only ledger recording initial allocations, annual accruals, approved
 * deductions, restorations on approved cancellations, manual adjustments,
 * carry-overs, expirations, SIL cash-conversion references (annual & separation),
 * event allocations, and unpaid leave tracking without payroll computation.
 */

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use DomainException;
use InvalidArgumentException;
use PDO;
use Throwable;

final class LeaveLedgerService
{
    /** @var array<string, bool> */
    private static array $tableCache = [];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /**
     * Append an immutable ledger record inside the caller's transaction (or standalone).
     *
     * @param array<string, mixed> $entry
     */
    public function record(array $entry): int
    {
        if (!self::tableExists($this->pdo, 'leave_balance_transactions')) {
            return 0;
        }

        $allowedTypes = [
            'initial_allocation',
            'annual_allocation',
            'approved_deduction',
            'balance_restoration',
            'adjustment',
            'carry_over',
            'expiration',
            'sil_conversion',
            'event_allocation',
            'unpaid_recorded',
        ];
        $txType = (string)($entry['transaction_type'] ?? '');
        if (!in_array($txType, $allowedTypes, true)) {
            throw new InvalidArgumentException("Unsupported leave ledger transaction_type '{$txType}'.");
        }

        $scope = (string)($entry['conversion_scope'] ?? 'none');
        if (!in_array($scope, ['none', 'annual_unused', 'separation'], true)) {
            $scope = 'none';
        }

        $remarks = trim((string)($entry['remarks'] ?? ''));
        if ($remarks === '') {
            $remarks = ucfirst(str_replace('_', ' ', $txType));
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO leave_balance_transactions (
                employee_id, leave_type_id, leave_balance_id, leave_request_id,
                event_entitlement_id, policy_version_id, balance_year, transaction_type,
                days_delta, balance_before, balance_after, conversion_scope,
                conversion_reference, remarks, is_confidential, actor_user_id
            ) VALUES (
                :employee_id, :leave_type_id, :leave_balance_id, :leave_request_id,
                :event_entitlement_id, :policy_version_id, :balance_year, :transaction_type,
                :days_delta, :balance_before, :balance_after, :conversion_scope,
                :conversion_reference, :remarks, :is_confidential, :actor_user_id
            )
        ");

        $stmt->execute([
            'employee_id'          => (int)$entry['employee_id'],
            'leave_type_id'        => (int)$entry['leave_type_id'],
            'leave_balance_id'     => isset($entry['leave_balance_id']) ? (int)$entry['leave_balance_id'] : null,
            'leave_request_id'     => isset($entry['leave_request_id']) ? (int)$entry['leave_request_id'] : null,
            'event_entitlement_id' => isset($entry['event_entitlement_id']) ? (int)$entry['event_entitlement_id'] : null,
            'policy_version_id'    => isset($entry['policy_version_id']) ? (int)$entry['policy_version_id'] : null,
            'balance_year'         => (int)($entry['balance_year'] ?? date('Y')),
            'transaction_type'     => $txType,
            'days_delta'           => round((float)($entry['days_delta'] ?? 0), 2),
            'balance_before'       => round((float)($entry['balance_before'] ?? 0), 2),
            'balance_after'        => round((float)($entry['balance_after'] ?? 0), 2),
            'conversion_scope'     => $scope,
            'conversion_reference' => isset($entry['conversion_reference']) && trim((string)$entry['conversion_reference']) !== ''
                ? substr(trim((string)$entry['conversion_reference']), 0, 80)
                : null,
            'remarks'              => substr($remarks, 0, 255),
            'is_confidential'      => !empty($entry['is_confidential']) ? 1 : 0,
            'actor_user_id'        => isset($entry['actor_user_id']) ? (int)$entry['actor_user_id'] : null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Record an approved Service Incentive Leave (SIL) cash conversion (annual or separation)
     * without computing payroll monetary amounts.
     *
     * @return array<string, mixed>
     */
    public function recordSilConversion(
        int $employeeId,
        int $leaveTypeId,
        int $year,
        int $daysToConvert,
        string $scope,
        string $conversionReference,
        string $remarks,
        ?int $actorUserId = null
    ): array {
        if ($daysToConvert <= 0) {
            throw new InvalidArgumentException('Conversion days must be a positive integer.');
        }

        if (!in_array($scope, ['annual_unused', 'separation'], true)) {
            throw new InvalidArgumentException('Conversion scope must be annual_unused or separation.');
        }

        $ref = trim($conversionReference);
        if ($ref === '') {
            throw new InvalidArgumentException('A conversion reference code or HR memo number is required.');
        }

        $typeStmt = $this->pdo->prepare("
            SELECT lt.id, lt.leave_name, lt.leave_code, lt.active_policy_version_id,
                   lpv.cash_convertible, lpv.cash_conversion_max_days
            FROM leave_types lt
            LEFT JOIN leave_policy_versions lpv ON lpv.id = lt.active_policy_version_id
            WHERE lt.id = :id
            LIMIT 1
        ");
        $typeStmt->execute(['id' => $leaveTypeId]);
        $type = $typeStmt->fetch(PDO::FETCH_ASSOC);
        if (!$type) {
            throw new DomainException('Leave type not found.');
        }

        $isSil = strtoupper((string)($type['leave_code'] ?? '')) === 'SIL';
        $cashConvertible = !empty($type['cash_convertible']) || $isSil;
        if (!$cashConvertible) {
            throw new DomainException('This leave type is non-convertible to cash under its active policy.');
        }

        $startedTx = !$this->pdo->inTransaction();
        if ($startedTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $balStmt = $this->pdo->prepare("
                SELECT id, allocated_days, used_days, remaining_days,
                       COALESCE(converted_days, 0) AS converted_days,
                       COALESCE(expired_days, 0) AS expired_days,
                       policy_version_id, sil_credited_from_vl
                FROM leave_balances
                WHERE employee_id = :emp_id AND leave_type_id = :lt_id AND year = :yr
                LIMIT 1
                FOR UPDATE
            ");
            $balStmt->execute(['emp_id' => $employeeId, 'lt_id' => $leaveTypeId, 'yr' => $year]);
            $bal = $balStmt->fetch(PDO::FETCH_ASSOC);

            if (!$bal) {
                throw new DomainException('No leave balance record found for conversion in the specified year.');
            }
            if (!empty($bal['sil_credited_from_vl'])) {
                throw new DomainException('SIL is satisfied via qualifying Vacation Leave for this employee and cannot be separately converted.');
            }

            // Exclude days reserved by Pending leave requests
            $pendingStmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(number_of_days), 0)
                FROM leave_requests
                WHERE employee_id = :emp_id AND leave_type_id = :lt_id
                  AND YEAR(start_date) = :yr AND status = 'Pending'
            ");
            $pendingStmt->execute(['emp_id' => $employeeId, 'lt_id' => $leaveTypeId, 'yr' => $year]);
            $pendingDays = (int)$pendingStmt->fetchColumn();

            $remaining = (int)$bal['remaining_days'];
            $availableForConversion = max(0, $remaining - $pendingDays);
            if ($daysToConvert > $availableForConversion) {
                throw new DomainException(sprintf(
                    'Only %d unencumbered day(s) are available for conversion (%d remaining minus %d pending).',
                    $availableForConversion,
                    $remaining,
                    $pendingDays
                ));
            }

            $maxCap = isset($type['cash_conversion_max_days']) && (float)$type['cash_conversion_max_days'] > 0
                ? (float)$type['cash_conversion_max_days']
                : 0.0;
            $alreadyConverted = (float)$bal['converted_days'];
            if ($maxCap > 0 && ($alreadyConverted + $daysToConvert) > $maxCap) {
                throw new DomainException(sprintf(
                    'Requested conversion exceeds policy cap of %.0f convertible day(s) per year.',
                    $maxCap
                ));
            }

            $newConverted = $alreadyConverted + $daysToConvert;
            $newRemaining = $remaining - $daysToConvert;

            $updStmt = $this->pdo->prepare("
                UPDATE leave_balances
                SET converted_days = :conv,
                    remaining_days = :rem
                WHERE id = :id
            ");
            $updStmt->execute([
                'conv' => $newConverted,
                'rem'  => $newRemaining,
                'id'   => (int)$bal['id'],
            ]);

            $note = trim($remarks) !== ''
                ? trim($remarks)
                : sprintf('SIL cash conversion (%s): %d day(s) [Ref: %s]', $scope, $daysToConvert, $ref);

            $txId = $this->record([
                'employee_id'          => $employeeId,
                'leave_type_id'        => $leaveTypeId,
                'leave_balance_id'     => (int)$bal['id'],
                'policy_version_id'    => $bal['policy_version_id'] ?? $type['active_policy_version_id'],
                'balance_year'         => $year,
                'transaction_type'     => 'sil_conversion',
                'days_delta'           => -$daysToConvert,
                'balance_before'       => $remaining,
                'balance_after'        => $newRemaining,
                'conversion_scope'     => $scope,
                'conversion_reference' => $ref,
                'remarks'              => $note,
                'is_confidential'      => 0,
                'actor_user_id'        => $actorUserId,
            ]);

            if ($startedTx) {
                $this->pdo->commit();
            }

            return [
                'transaction_id'       => $txId,
                'days_converted'       => $daysToConvert,
                'remaining_after'      => $newRemaining,
                'conversion_reference' => $ref,
                'conversion_scope'     => $scope,
            ];
        } catch (Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Retrieve ledger history for an employee, redacting confidential remarks if viewer lacks clearance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHistoryByEmployee(
        int $employeeId,
        ?int $year = null,
        bool $includeConfidential = false,
        int $limit = 100
    ): array {
        if (!self::tableExists($this->pdo, 'leave_balance_transactions')) {
            return [];
        }

        $sql = "
            SELECT lbt.*,
                   lt.leave_name,
                   lt.leave_code,
                   lt.classification,
                   lt.entitlement_model,
                   CONCAT(COALESCE(actor_e.first_name, actor_u.username, 'System'),
                          CASE WHEN actor_e.last_name IS NOT NULL THEN CONCAT(' ', actor_e.last_name) ELSE '' END) AS actor_name
            FROM leave_balance_transactions lbt
            INNER JOIN leave_types lt ON lt.id = lbt.leave_type_id
            LEFT JOIN users actor_u ON actor_u.id = lbt.actor_user_id
            LEFT JOIN employees actor_e ON actor_e.id = actor_u.employee_id
            WHERE lbt.employee_id = :emp_id
        ";
        $params = ['emp_id' => $employeeId];
        if ($year !== null && $year > 0) {
            $sql .= " AND lbt.balance_year = :year";
            $params['year'] = $year;
        }
        $sql .= " ORDER BY lbt.created_at DESC, lbt.id DESC LIMIT " . max(1, min(500, $limit));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if (!empty($row['is_confidential']) && !$includeConfidential) {
                $row['leave_name'] = 'Confidential Statutory Leave';
                $row['leave_code'] = 'CONFIDENTIAL';
                $row['remarks'] = '[Confidential — Protected entry details redacted]';
                $row['is_redacted'] = true;
            } else {
                $row['is_redacted'] = false;
            }
        }
        unset($row);

        return $rows;
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
}
