<?php
/**
 * CLI Command: Automated Leave Accrual & Annual Allocation Runner
 * 
 * Usage:
 *   php bin/accrue-leave.php [--year=YYYY] [--dry-run] [--force]
 * 
 * Flags:
 *   --year=YYYY   Target accrual year (defaults to current year)
 *   --dry-run     Simulate allocation without modifying the database
 *   --force       Force re-allocation / update for existing records
 * 
 * Idempotency & Safety:
 * - Allocates default quota for active employees across active leave types.
 * - Idempotent: Skips employees who already have an established balance for the year.
 * - Wrapped in transactional boundaries with activity log audit trail.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This script must only be executed via the CLI interface.\n";
    exit(1);
}

require_once __DIR__ . '/../core/bootstrap.php';

use Core\Database;

$options = getopt('', ['year::', 'dry-run', 'force', 'help']);

if (isset($options['help'])) {
    echo "Usage: php bin/accrue-leave.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --year=YYYY   Target accrual year (default: current year)\n";
    echo "  --dry-run     Preview allocations without writing to database\n";
    echo "  --force       Update existing balances to match default quota\n";
    echo "  --help        Show this help message\n";
    exit(0);
}

$targetYear = isset($options['year']) ? (int)$options['year'] : (int)date('Y');
$isDryRun   = isset($options['dry-run']);
$isForce    = isset($options['force']);

echo "====================================================================\n";
echo "  HRIS AUTOMATED LEAVE ACCRUAL & ANNUAL ALLOCATION ENGINE\n";
echo "  Target Year: {$targetYear} " . ($isDryRun ? "[DRY RUN]" : "") . "\n";
echo "====================================================================\n\n";

try {
    $db = Database::getInstance()->getConnection();

    $policyEngine = new \App\Services\LeavePolicyEngine($db);
    $ledger = new \App\Services\LeaveLedgerService($db);
    $asOfDate = sprintf('%04d-12-31', $targetYear);

    // 1. Fetch Active Leave Types enriched with active policy versions
    $leaveTypes = $policyEngine->getAllTypesWithActivePolicies(true, $asOfDate);

    if (empty($leaveTypes)) {
        echo "Notice: No active leave types found. No accrual required.\n";
        exit(0);
    }

    // 2. Fetch Active Employees
    $empStmt = $db->query("SELECT id, employee_no, first_name, last_name, gender, date_hired, employment_type, employment_status FROM employees WHERE employment_status = 'Active'");
    $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($employees)) {
        echo "Notice: No active employees found. No accrual required.\n";
        exit(0);
    }

    echo "Found " . count($employees) . " active employee(s) and " . count($leaveTypes) . " active leave type(s).\n\n";

    $createdCount = 0;
    $updatedCount = 0;
    $skippedCount = 0;

    if (!$isDryRun) {
        $db->beginTransaction();
    }

    $checkStmt = $db->prepare("
        SELECT id, allocated_days, used_days, remaining_days, COALESCE(converted_days, 0) AS converted_days, COALESCE(expired_days, 0) AS expired_days
        FROM leave_balances
        WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND year = :year
    ");

    $insertStmt = $db->prepare("
        INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days, policy_version_id, sil_credited_from_vl, eligibility_status, notes)
        VALUES (:employee_id, :leave_type_id, :year, :allocated_days, 0, :remaining_days, :pv_id, :sil_vl, :elig_status, :notes)
    ");

    $updateStmt = $db->prepare("
        UPDATE leave_balances
        SET allocated_days = :allocated_days,
            remaining_days = :remaining_days,
            policy_version_id = COALESCE(:pv_id, policy_version_id),
            eligibility_status = :elig_status,
            notes = :notes
        WHERE id = :id
    ");

    $logStmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, description, entity_type, entity_id, ip_address, user_agent)
        VALUES (NULL, 'leave_balance_accrual', :description, 'leave_balance', :entity_id, '127.0.0.1', 'CLI Accrual Runner')
    ");

    foreach ($employees as $employee) {
        $empId   = (int)$employee['id'];
        $empName = "{$employee['first_name']} {$employee['last_name']} ({$employee['employee_no']})";

        foreach ($leaveTypes as $leaveType) {
            $typeId      = (int)$leaveType['id'];
            $typeName    = $leaveType['leave_name'];
            $code        = strtoupper((string)($leaveType['leave_code'] ?? ''));

            // Skip event-based and unpaid leaves from annual balance accrual
            if (($leaveType['entitlement_model'] ?? 'annual') !== 'annual') {
                echo "  [SKIPPED] {$empName} -> {$typeName}: Non-annual ({$leaveType['entitlement_model']})\n";
                $skippedCount++;
                continue;
            }

            $eval = $policyEngine->evaluateEmployeeTypeEligibility($employee, $leaveType, $targetYear, $asOfDate);
            if (in_array($code, [\App\Services\LeavePolicyEngine::CODE_SIL, \App\Services\LeavePolicyEngine::CODE_SOLO_PARENT], true) && empty($eval['eligible'])) {
                echo "  [SKIPPED] {$empName} -> {$typeName}: {$eval['reason']}\n";
                $skippedCount++;
                continue;
            }

            $defaultDays = max(0, (int)round((float)($eval['entitled_days'] ?? $leaveType['default_days'])));

            $checkStmt->execute([
                'employee_id'   => $empId,
                'leave_type_id' => $typeId,
                'year'          => $targetYear,
            ]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                if (!$isDryRun) {
                    $insertStmt->execute([
                        'employee_id'    => $empId,
                        'leave_type_id'  => $typeId,
                        'year'           => $targetYear,
                        'allocated_days' => $defaultDays,
                        'remaining_days' => $defaultDays,
                        'pv_id'          => $leaveType['policy_version_id'] ?? null,
                        'sil_vl'         => !empty($eval['credited_via_vl']) ? 1 : 0,
                        'elig_status'    => 'Available',
                        'notes'          => substr((string)($eval['reason'] ?? ''), 0, 250) ?: null,
                    ]);
                    $newId = (int)$db->lastInsertId();

                    $ledger->record([
                        'employee_id'       => $empId,
                        'leave_type_id'     => $typeId,
                        'leave_balance_id'  => $newId,
                        'policy_version_id' => $leaveType['policy_version_id'] ?? null,
                        'balance_year'      => $targetYear,
                        'transaction_type'  => 'annual_allocation',
                        'days_delta'        => $defaultDays,
                        'balance_before'    => 0,
                        'balance_after'     => $defaultDays,
                        'remarks'           => "Annual accrual: {$defaultDays} day(s) of {$typeName} for {$targetYear}",
                    ]);

                    $logStmt->execute([
                        'description' => "Automated accrual: Allocated {$defaultDays} days of '{$typeName}' to {$empName} for {$targetYear}.",
                        'entity_id'   => (string)$newId,
                    ]);
                }
                echo "  [CREATED] {$empName} -> {$typeName}: {$defaultDays} days\n";
                $createdCount++;
            } elseif ($isForce) {
                $used = (int)$existing['used_days'];
                $converted = (int)round((float)$existing['converted_days']);
                $expired = (int)round((float)$existing['expired_days']);
                $minRequired = $used + $converted + $expired;
                if ($defaultDays < $minRequired) {
                    echo "  [SKIPPED] {$empName} -> {$typeName}: Cannot reset to {$defaultDays} (< {$minRequired} committed/used)\n";
                    $skippedCount++;
                    continue;
                }
                $newRemaining = $defaultDays - $minRequired;
                $oldRemaining = (int)$existing['remaining_days'];
                if (!$isDryRun) {
                    $updateStmt->execute([
                        'allocated_days' => $defaultDays,
                        'remaining_days' => $newRemaining,
                        'pv_id'          => $leaveType['policy_version_id'] ?? null,
                        'elig_status'    => 'Available',
                        'notes'          => substr((string)($eval['reason'] ?? ''), 0, 250) ?: null,
                        'id'             => $existing['id'],
                    ]);

                    $ledger->record([
                        'employee_id'       => $empId,
                        'leave_type_id'     => $typeId,
                        'leave_balance_id'  => (int)$existing['id'],
                        'policy_version_id' => $leaveType['policy_version_id'] ?? null,
                        'balance_year'      => $targetYear,
                        'transaction_type'  => 'adjustment',
                        'days_delta'        => $newRemaining - $oldRemaining,
                        'balance_before'    => $oldRemaining,
                        'balance_after'     => $newRemaining,
                        'remarks'           => "Force annual accrual reset to {$defaultDays} day(s) for {$typeName} ({$targetYear})",
                    ]);

                    $logStmt->execute([
                        'description' => "Force accrual update: Reset allocated days to {$defaultDays} for {$empName} - '{$typeName}' ({$targetYear}).",
                        'entity_id'   => (string)$existing['id'],
                    ]);
                }
                echo "  [UPDATED] {$empName} -> {$typeName}: Reset to {$defaultDays} days\n";
                $updatedCount++;
            } else {
                echo "  [SKIPPED] {$empName} -> {$typeName}: Already initialized ({$existing['remaining_days']} days remaining)\n";
                $skippedCount++;
            }
        }
    }

    if (!$isDryRun) {
        $db->commit();
    }

    echo "\n====================================================================\n";
    echo "  ACCRUAL SUMMARY\n";
    echo "  Allocations Created: {$createdCount}\n";
    echo "  Balances Updated:    {$updatedCount}\n";
    echo "  Existing (Skipped):  {$skippedCount}\n";
    echo "====================================================================\n";

    exit(0);

} catch (\Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "\nFATAL ERROR during leave accrual: " . $e->getMessage() . "\n";
    error_log("[Accrual Error] " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    exit(1);
}

