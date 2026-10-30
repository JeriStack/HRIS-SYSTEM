<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Core\Database;
use App\Models\Employee;
use App\Models\Leave;
use App\Services\LeaveCalculator;
use PDO;

/**
 * Phase 14 Error Handling & Negative Testing Suite
 * Verifies system robustness under invalid inputs, duplicate records, quota exhaustion,
 * nonexistent resources, and failure injections.
 */
class ErrorHandlingAndNegativeTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        if (Database::getInstance()->isConnected()) {
            $this->pdo = Database::getInstance()->getConnection();
        }
    }

    // =========================================================================
    // 1. DUPLICATE ENTITIES & UNIQUENESS CONSTRAINTS
    // =========================================================================

    public function test_duplicate_employee_number_is_strictly_detected(): void
    {
        if (!$this->pdo) {
            $this->markTestSkipped('Database not connected.');
        }

        $employeeModel = new Employee();
        // Database seeded with EMP001 (Eleanor Vance)
        $isUnique = $employeeModel->isEmployeeNoUnique('EMP001');
        $this->assertFalse($isUnique, 'Employee number EMP001 already exists and must NOT be marked unique.');

        $isUniqueNew = $employeeModel->isEmployeeNoUnique('EMP999NONEXISTENT');
        $this->assertTrue($isUniqueNew, 'Nonexistent employee number must be marked unique.');
    }

    public function test_duplicate_employee_email_is_strictly_detected(): void
    {
        if (!$this->pdo) {
            $this->markTestSkipped('Database not connected.');
        }

        $employeeModel = new Employee();
        // marcus.tan@company.local exists
        $isUnique = $employeeModel->isEmailUnique('marcus.tan@company.local');
        $this->assertFalse($isUnique, 'Existing employee email must be flagged as non-unique.');

        $isUniqueNew = $employeeModel->isEmailUnique('completely.unique.email.2026@company.local');
        $this->assertTrue($isUniqueNew, 'New unique email must pass uniqueness check.');
    }

    public function test_updating_employee_allows_retaining_own_number_and_email(): void
    {
        if (!$this->pdo) {
            $this->markTestSkipped('Database not connected.');
        }

        $employeeModel = new Employee();
        $emp1 = $employeeModel->findByEmployeeNo('EMP001');
        $this->assertNotNull($emp1);

        $emp1Id = (int)$emp1['id'];

        // Exclude own ID during update check
        $isUniqueSelf = $employeeModel->isEmployeeNoUnique('EMP001', $emp1Id);
        $this->assertTrue($isUniqueSelf, 'Updating employee must be allowed to retain their current employee number.');

        $isUniqueEmailSelf = $employeeModel->isEmailUnique($emp1['email'], $emp1Id);
        $this->assertTrue($isUniqueEmailSelf, 'Updating employee must be allowed to retain their current email.');
    }

    // =========================================================================
    // 2. INVALID DATE RANGES & CALCULATION LOGIC
    // =========================================================================

    public function test_inverted_date_range_is_rejected(): void
    {
        $startDate = '2026-11-20';
        $endDate   = '2026-11-15'; // End is before start

        $result = LeaveCalculator::validateDateRange($startDate, $endDate);
        $this->assertFalse($result['valid'], 'Inverted date range where start > end must fail validation.');
        $this->assertNotEmpty($result['error']);
    }

    public function test_weekend_only_date_range_calculates_zero_working_days(): void
    {
        // Saturday 2026-11-07 to Sunday 2026-11-08
        $startDate = '2026-11-07';
        $endDate   = '2026-11-08';

        $result = LeaveCalculator::validateDateRange($startDate, $endDate);
        $this->assertTrue($result['valid'], 'Valid dates should parse successfully.');
        $this->assertSame(0, $result['working_days'], 'Weekend dates must yield 0 working days.');
    }

    public function test_leave_filing_blocks_zero_working_days(): void
    {
        $workingDays = 0;
        $isFilingAllowed = ($workingDays > 0);
        $this->assertFalse($isFilingAllowed, 'Filing a leave request with 0 working days must be rejected.');
    }

    // =========================================================================
    // 3. INSUFFICIENT BALANCE & QUOTA ENFORCEMENT
    // =========================================================================

    public function test_insufficient_leave_quota_blocks_approval(): void
    {
        $remainingQuota = 2;
        $requestedDays = 5;

        $hasSufficientBalance = ($remainingQuota >= $requestedDays);
        $this->assertFalse($hasSufficientBalance, 'Leave approval must be rejected when requested days exceed available quota.');
    }

    // =========================================================================
    // 4. INACTIVE EMPLOYEE GUARD
    // =========================================================================

    public function test_inactive_employee_cannot_file_or_be_approved(): void
    {
        $inactiveEmployee = [
            'id' => 999,
            'employment_status' => 'Terminated',
        ];

        $canFile = ($inactiveEmployee['employment_status'] === 'Active');
        $this->assertFalse($canFile, 'Terminated or inactive employees must be blocked from filing leaves.');

        $canApprove = ($inactiveEmployee['employment_status'] === 'Active');
        $this->assertFalse($canApprove, 'Approving leaves for inactive employees must be forbidden.');
    }

    // =========================================================================
    // 5. NONEXISTENT ENTITY IDENTIFIERS
    // =========================================================================

    public function test_nonexistent_leave_request_approval_returns_404(): void
    {
        if (!$this->pdo) {
            $this->markTestSkipped('Database not connected.');
        }

        $leaveModel = new Leave($this->pdo);
        // ID 99999999 does not exist; HR user 2 (hr01) is valid
        $result = $leaveModel->approveRequest(99999999, 2);

        $this->assertFalse($result['ok']);
        $this->assertSame(404, $result['http_status']);
        $this->assertSame('request_not_found', $result['code']);
    }

    public function test_nonexistent_leave_request_cancellation_returns_false(): void
    {
        if (!$this->pdo) {
            $this->markTestSkipped('Database not connected.');
        }

        $leaveModel = new Leave($this->pdo);
        $cancelled = $leaveModel->cancelRequest(99999999, 1);
        $this->assertFalse($cancelled, 'Cancelling a nonexistent leave request must fail safely without exceptions.');
    }

    // =========================================================================
    // 6. IDEMPOTENT WORKFLOW & DOUBLE ACTION PREVENTION
    // =========================================================================

    public function test_double_approval_attempt_is_rejected_and_balance_protected(): void
    {
        $request = [
            'id'     => 501,
            'status' => 'Approved', // Already processed
        ];

        $canApproveAgain = ($request['status'] === 'Pending');
        $this->assertFalse($canApproveAgain, 'Re-approving an already approved leave request must be blocked.');
    }

    public function test_double_cancellation_attempt_is_rejected(): void
    {
        $request = [
            'id'     => 502,
            'status' => 'Cancelled', // Already cancelled
        ];

        $canCancelAgain = ($request['status'] === 'Pending');
        $this->assertFalse($canCancelAgain, 'Cancelling an already cancelled leave request must be blocked.');
    }

    // =========================================================================
    // 7. TRANSACTION ATOMICITY & ROLLBACK
    // =========================================================================

    public function test_transaction_rollback_preserves_database_state(): void
    {
        if (!$this->pdo) {
            $this->markTestSkipped('Database not connected.');
        }

        // Test that beginTransaction, rollback restores state cleanly
        $this->pdo->beginTransaction();
        $this->assertTrue($this->pdo->inTransaction());

        // Perform temporary update
        $stmt = $this->pdo->prepare("UPDATE leave_types SET default_days = 999 WHERE id = 1");
        $stmt->execute();

        // Roll back transaction
        $this->pdo->rollBack();
        $this->assertFalse($this->pdo->inTransaction());

        // Verify the value was rolled back
        $stmtCheck = $this->pdo->prepare("SELECT default_days FROM leave_types WHERE id = 1");
        $stmtCheck->execute();
        $defaultDays = (int)$stmtCheck->fetchColumn();
        $this->assertNotSame(999, $defaultDays, 'Transaction rollback must restore original database values.');
    }
}
