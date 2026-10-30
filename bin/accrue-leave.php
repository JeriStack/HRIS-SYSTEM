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

    // 1. Fetch Active Leave Types
    $typeStmt = $db->query("SELECT id, leave_name, default_days FROM leave_types WHERE status = 'Active'");
    $leaveTypes = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($leaveTypes)) {
        echo "Notice: No active leave types found. No accrual required.\n";
        exit(0);
    }

    // 2. Fetch Active Employees
    $empStmt = $db->query("SELECT id, employee_no, first_name, last_name FROM employees WHERE employment_status = 'Active'");
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
        SELECT id, allocated_days, used_days, remaining_days 
        FROM leave_balances 
        WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND year = :year
    ");

    $insertStmt = $db->prepare("
        INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days)
        VALUES (:employee_id, :leave_type_id, :year, :allocated_days, 0, :remaining_days)
    ");

    $updateStmt = $db->prepare("
        UPDATE leave_balances 
        SET allocated_days = :allocated_days, remaining_days = :allocated_days - used_days
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
            $defaultDays = (int)$leaveType['default_days'];

            $checkStmt->execute([
                'employee_id'   => $empId,
                'leave_type_id' => $typeId,
                'year'          => $targetYear,
            ]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                // Initial Allocation
                if (!$isDryRun) {
                    $insertStmt->execute([
                        'employee_id'    => $empId,
                        'leave_type_id'  => $typeId,
                        'year'           => $targetYear,
                        'allocated_days' => $defaultDays,
                        'remaining_days' => $defaultDays,
                    ]);
                    $newId = (int)$db->lastInsertId();

                    $logStmt->execute([
                        'description' => "Automated accrual: Allocated {$defaultDays} days of '{$typeName}' to {$empName} for {$targetYear}.",
                        'entity_id'   => (string)$newId,
                    ]);
                }
                echo "  [CREATED] {$empName} -> {$typeName}: {$defaultDays} days\n";
                $createdCount++;
            } elseif ($isForce) {
                // Force Update
                if (!$isDryRun) {
                    $updateStmt->execute([
                        'allocated_days' => $defaultDays,
                        'id'             => $existing['id'],
                    ]);

                    $logStmt->execute([
                        'description' => "Force accrual update: Reset allocated days to {$defaultDays} for {$empName} - '{$typeName}' ({$targetYear}).",
                        'entity_id'   => (string)$existing['id'],
                    ]);
                }
                echo "  [UPDATED] {$empName} -> {$typeName}: Reset to {$defaultDays} days\n";
                $updatedCount++;
            } else {
                // Idempotent Skip
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

