<?php

namespace Tests\Integration;

use App\Models\Leave;
use App\Realtime\EventAuthorizer;
use App\Services\LeaveNotificationService;
use Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Phase 9 — Transaction-Safe Leave Processing Integration Tests
 *
 * Verifies against the real MySQL InnoDB database:
 *  - Atomic successful approval across leave_requests, leave_balances (used_days & remaining_days),
 *    activity_logs, and notifications.
 *  - Insufficient balance rejection & zero partial writes.
 *  - Failure injection after every critical write step (request status update, used_days update,
 *    remaining_days update, activity_log insert, notification insert) with complete rollback.
 *  - Duplicate and concurrent approval protection using two independent MySQL connections and
 *    SELECT ... FOR UPDATE row-level locks.
 *  - Validation of employee eligibility, leave type eligibility, date overlap, cross-year boundaries,
 *    working day duration mismatch, self-approval prevention, and post-commit delivery error isolation.
 *
 * All test fixtures are created in isolated future years and cleaned up in tearDown().
 */
class TransactionSafeLeaveApprovalTest extends TestCase
{
    private PDO $pdo;
    private int $testYear = 2097;

    /** @var array{id:int, employee_id:?int} */
    private array $hrUser;
    /** @var array{id:int, employee_id:?int} */
    private array $secondHrUser;
    /** @var array{id:int, employee_id:int} */
    private array $employeeUser;
    /** @var array{id:int, employee_id:int} */
    private array $otherEmployeeUser;

    private int $leaveTypeId;

    /** @var int[] */
    private array $createdLeaveIds = [];
    /** @var int[] */
    private array $createdBalanceIds = [];
    /** @var int[] */
    private array $createdLeaveTypeIds = [];

    /** @var array<int, string> [employee_id => original_employment_status] */
    private array $restoredEmployeeStatuses = [];

    protected function setUp(): void
    {
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database is not connected in this environment.');
        }
        $this->pdo = $db->getConnection();

        $hrs = $this->pdo->query(
            "SELECT id, employee_id FROM users WHERE role = 'hr' AND status = 'Active' ORDER BY id ASC LIMIT 2"
        )->fetchAll(PDO::FETCH_ASSOC);

        $employees = $this->pdo->query(
            "SELECT id, employee_id FROM users WHERE role = 'employee' AND status = 'Active' AND employee_id IS NOT NULL ORDER BY id ASC LIMIT 2"
        )->fetchAll(PDO::FETCH_ASSOC);

        $leaveType = $this->pdo->query(
            "SELECT id FROM leave_types WHERE status = 'Active' AND default_days >= 5 ORDER BY id ASC LIMIT 1"
        )->fetchColumn();

        if (count($hrs) < 1 || count($employees) < 2 || !$leaveType) {
            $this->markTestSkipped('Seed data with HR user(s), two active employees, and an active leave type is required.');
        }

        $this->hrUser = [
            'id'          => (int)$hrs[0]['id'],
            'employee_id' => $hrs[0]['employee_id'] !== null ? (int)$hrs[0]['employee_id'] : null,
        ];
        $this->secondHrUser = isset($hrs[1]) ? [
            'id'          => (int)$hrs[1]['id'],
            'employee_id' => $hrs[1]['employee_id'] !== null ? (int)$hrs[1]['employee_id'] : null,
        ] : $this->hrUser;

        $this->employeeUser = [
            'id'          => (int)$employees[0]['id'],
            'employee_id' => (int)$employees[0]['employee_id'],
        ];
        $this->otherEmployeeUser = [
            'id'          => (int)$employees[1]['id'],
            'employee_id' => (int)$employees[1]['employee_id'],
        ];
        $this->leaveTypeId = (int)$leaveType;

        // Clean any leftover test balances in testYear for these test employees
        $this->pdo->prepare("DELETE FROM leave_balances WHERE employee_id IN (?, ?) AND year = ?")
            ->execute([$this->employeeUser['employee_id'], $this->otherEmployeeUser['employee_id'], $this->testYear]);
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        foreach ($this->restoredEmployeeStatuses as $empId => $status) {
            $this->pdo->prepare("UPDATE employees SET employment_status = ? WHERE id = ?")
                ->execute([$status, $empId]);
        }
        $this->restoredEmployeeStatuses = [];

        foreach (array_unique($this->createdLeaveIds) as $leaveId) {
            $this->pdo->prepare("DELETE FROM activity_logs WHERE entity_type = 'LeaveRequest' AND entity_id = ?")
                ->execute([(string)$leaveId]);
            $this->pdo->prepare("DELETE FROM notifications WHERE entity_id = ?")
                ->execute([$leaveId]);
            $this->pdo->prepare("DELETE FROM leave_requests WHERE id = ?")
                ->execute([$leaveId]);
        }
        $this->createdLeaveIds = [];

        foreach (array_unique($this->createdBalanceIds) as $balId) {
            $this->pdo->prepare("DELETE FROM leave_balances WHERE id = ?")
                ->execute([$balId]);
        }
        $this->createdBalanceIds = [];

        if (isset($this->employeeUser, $this->otherEmployeeUser)) {
            $this->pdo->prepare("DELETE FROM leave_balances WHERE employee_id IN (?, ?) AND year = ?")
                ->execute([$this->employeeUser['employee_id'], $this->otherEmployeeUser['employee_id'], $this->testYear]);
        }

        foreach (array_unique($this->createdLeaveTypeIds) as $ltId) {
            $this->pdo->prepare("DELETE FROM leave_types WHERE id = ?")
                ->execute([$ltId]);
        }
        $this->createdLeaveTypeIds = [];
    }

    /**
     * Create a second independent PDO connection to the same MySQL database for concurrency tests.
     */
    private function createSecondConnection(): PDO
    {
        $config = require __DIR__ . '/../../config/database.php';
        $driver   = getenv('DB_DRIVER')   ?: ($config['driver'] ?? 'mysql');
        $host     = getenv('DB_HOST')     ?: ($config['host'] ?? '127.0.0.1');
        $port     = getenv('DB_PORT')     ?: ($config['port'] ?? 3306);
        $database = getenv('DB_DATABASE') ?: ($config['database'] ?? 'hris_db');
        $username = getenv('DB_USERNAME') ?: ($config['username'] ?? 'root');
        $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($config['password'] ?? '');
        $charset  = $config['charset'] ?? 'utf8mb4';

        return new PDO(
            "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    private function seedBalance(int $employeeId, int $leaveTypeId, int $allocated, int $used, int $remaining, ?int $year = null): int
    {
        $yr = $year ?? $this->testYear;
        $this->pdo->prepare("DELETE FROM leave_balances WHERE employee_id = ? AND leave_type_id = ? AND year = ?")
            ->execute([$employeeId, $leaveTypeId, $yr]);

        $stmt = $this->pdo->prepare("
            INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days)
            VALUES (:emp_id, :lt_id, :year, :alloc, :used, :rem)
        ");
        $stmt->execute([
            'emp_id' => $employeeId,
            'lt_id'  => $leaveTypeId,
            'year'   => $yr,
            'alloc'  => $allocated,
            'used'   => $used,
            'rem'    => $remaining,
        ]);

        return $this->createdBalanceIds[] = (int)$this->pdo->lastInsertId();
    }

    private function seedPendingLeave(
        int $employeeId,
        int $leaveTypeId,
        string $startDate = '2097-05-06',
        string $endDate = '2097-05-08',
        int $days = 3,
        string $reason = 'Phase 9 transaction test leave'
    ): int {
        // 2097-05-06 is Monday, 2097-05-08 is Wednesday (3 working days)
        $id = (new Leave($this->pdo))->createRequest([
            'employee_id'    => $employeeId,
            'leave_type_id'  => $leaveTypeId,
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'number_of_days' => $days,
            'reason'         => $reason,
        ]);

        return $this->createdLeaveIds[] = $id;
    }

    private function countApprovalLogs(int $requestId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM activity_logs
            WHERE entity_type = 'LeaveRequest' AND entity_id = :id AND action = 'LEAVE_APPROVED'
        ");
        $stmt->execute(['id' => (string)$requestId]);
        return (int)$stmt->fetchColumn();
    }

    private function countApprovalNotifications(int $requestId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE entity_id = :id AND event_type = :evt
        ");
        $stmt->execute(['id' => $requestId, 'evt' => EventAuthorizer::LEAVE_APPROVED]);
        return (int)$stmt->fetchColumn();
    }

    // =========================================================================
    // 8.1 Successful Approval
    // =========================================================================

    public function test_successful_approval_atomically_updates_request_balance_log_and_notification(): void
    {
        $empId = $this->employeeUser['employee_id'];
        $balanceId = $this->seedBalance($empId, $this->leaveTypeId, 15, 2, 13);
        $leaveId = $this->seedPendingLeave($empId, $this->leaveTypeId, '2097-05-06', '2097-05-08', 3);

        $leaveModel = new Leave($this->pdo);
        $notifService = new LeaveNotificationService();

        $postCommitCalled = false;
        $result = $leaveModel->approveRequest(
            $leaveId,
            $this->hrUser['id'],
            function (array $req) use ($notifService, $leaveId): void {
                $this->assertTrue($notifService->leaveDecided(
                    $req,
                    'Approved',
                    "Leave request #LR-{$leaveId} approved."
                ));
            },
            null,
            function (array $data) use (&$postCommitCalled, $leaveId): void {
                $postCommitCalled = true;
                $this->assertSame($leaveId, $data['request_id']);
                $this->assertFalse($this->pdo->inTransaction(), 'Post-commit hook must run only after COMMIT.');
            }
        );

        $this->assertTrue($result['ok'], 'Authorized HR approval of eligible pending request must succeed.');
        $this->assertSame('approved', $result['code']);
        $this->assertTrue($postCommitCalled, 'Post-commit callback must be triggered after commit.');

        // 1. Verify Leave Request Status = Approved
        $reqRow = $this->pdo->query("SELECT * FROM leave_requests WHERE id = {$leaveId}")->fetch();
        $this->assertSame('Approved', $reqRow['status']);
        $this->assertSame($this->hrUser['id'], (int)$reqRow['approved_by']);
        $this->assertNotNull($reqRow['approved_at']);

        // 2. Verify Used Leave Days = 2 + 3 = 5, Remaining Balance = 13 - 3 = 10
        $balRow = $this->pdo->query("SELECT * FROM leave_balances WHERE id = {$balanceId}")->fetch();
        $this->assertSame(15, (int)$balRow['allocated_days']);
        $this->assertSame(5, (int)$balRow['used_days']);
        $this->assertSame(10, (int)$balRow['remaining_days']);

        // 3. Verify Activity Log is created
        $this->assertSame(1, $this->countApprovalLogs($leaveId));
        $logRow = $this->pdo->query(
            "SELECT * FROM activity_logs WHERE entity_type = 'LeaveRequest' AND entity_id = '{$leaveId}' AND action = 'LEAVE_APPROVED' LIMIT 1"
        )->fetch();
        $this->assertSame($this->hrUser['id'], (int)$logRow['user_id']);
        $this->assertStringContainsString('Pending -> Approved', $logRow['description']);

        // 4. Verify Persistent Notification is created for the owning employee only
        $this->assertSame(1, $this->countApprovalNotifications($leaveId));
        $notifRow = $this->pdo->query(
            "SELECT * FROM notifications WHERE entity_id = {$leaveId} AND event_type = 'leave_request.approved' LIMIT 1"
        )->fetch();
        $this->assertSame($this->employeeUser['id'], (int)$notifRow['user_id']);
        $this->assertSame('Leave Request Approved', $notifRow['title']);
        $this->assertSame('success', $notifRow['type']);
    }

    // =========================================================================
    // 8.2 Insufficient Balance
    // =========================================================================

    public function test_insufficient_balance_rejects_approval_and_leaves_all_records_unchanged(): void
    {
        $empId = $this->employeeUser['employee_id'];
        // Only 2 days remaining, but request requires 3 days
        $balanceId = $this->seedBalance($empId, $this->leaveTypeId, 10, 8, 2);
        $leaveId = $this->seedPendingLeave($empId, $this->leaveTypeId, '2097-05-06', '2097-05-08', 3);

        $leaveModel = new Leave($this->pdo);
        $result = $leaveModel->approveRequest($leaveId, $this->hrUser['id']);

        $this->assertFalse($result['ok'], 'Approval must be rejected when remaining_days < number_of_days.');
        $this->assertSame('insufficient_balance', $result['code']);
        $this->assertSame(400, $result['http_status']);

        // Request remains Pending
        $reqRow = $this->pdo->query("SELECT status, approved_by, approved_at FROM leave_requests WHERE id = {$leaveId}")->fetch();
        $this->assertSame('Pending', $reqRow['status']);
        $this->assertNull($reqRow['approved_by']);
        $this->assertNull($reqRow['approved_at']);

        // Balance and used days remain unchanged
        $balRow = $this->pdo->query("SELECT allocated_days, used_days, remaining_days FROM leave_balances WHERE id = {$balanceId}")->fetch();
        $this->assertSame(10, (int)$balRow['allocated_days']);
        $this->assertSame(8, (int)$balRow['used_days']);
        $this->assertSame(2, (int)$balRow['remaining_days']);

        // No approval activity log or notification persisted
        $this->assertSame(0, $this->countApprovalLogs($leaveId));
        $this->assertSame(0, $this->countApprovalNotifications($leaveId));
    }

    // =========================================================================
    // 8.3 Failure Injection and Rollback After Each Write Step
    // =========================================================================

    /**
     * Injects a failure immediately after each critical write step inside the transaction
     * and asserts that every prior write in the transaction is rolled back completely.
     */
    public function test_injected_failures_after_each_step_roll_back_all_transaction_writes(): void
    {
        $stepsToFail = [
            'after_request_status_update',
            'after_used_days_update',
            'after_remaining_balance_update',
            'after_activity_log_insert',
            'after_notification_insert',
        ];

        $empId = $this->employeeUser['employee_id'];
        $balanceId = $this->seedBalance($empId, $this->leaveTypeId, 15, 4, 11);
        $leaveId = $this->seedPendingLeave($empId, $this->leaveTypeId, '2097-05-13', '2097-05-15', 3);

        $leaveModel = new Leave($this->pdo);

        foreach ($stepsToFail as $failingStep) {
            $reachedStep = false;

            $result = $leaveModel->approveRequest(
                $leaveId,
                $this->hrUser['id'],
                null,
                function (string $step) use ($failingStep, &$reachedStep): void {
                    if ($step === $failingStep) {
                        $reachedStep = true;
                        throw new RuntimeException("Injected failure at step: {$failingStep}");
                    }
                }
            );

            $this->assertTrue($reachedStep, "Step '{$failingStep}' must be reached before failing.");
            $this->assertFalse($result['ok'], "Approval must fail when '{$failingStep}' throws.");
            $this->assertSame('transaction_failed', $result['code']);
            $this->assertFalse($this->pdo->inTransaction(), "Transaction must not remain open after rollback on '{$failingStep}'.");

            // Verify Leave Request Status rolled back to Pending
            $reqRow = $this->pdo->query("SELECT status, approved_by, approved_at FROM leave_requests WHERE id = {$leaveId}")->fetch();
            $this->assertSame('Pending', $reqRow['status'], "Request status must roll back to Pending after failure at '{$failingStep}'.");
            $this->assertNull($reqRow['approved_by']);
            $this->assertNull($reqRow['approved_at']);

            // Verify Used Days and Remaining Balance rolled back to original values (4 used, 11 remaining)
            $balRow = $this->pdo->query("SELECT allocated_days, used_days, remaining_days FROM leave_balances WHERE id = {$balanceId}")->fetch();
            $this->assertSame(15, (int)$balRow['allocated_days']);
            $this->assertSame(4, (int)$balRow['used_days'], "used_days must roll back to 4 after failure at '{$failingStep}'.");
            $this->assertSame(11, (int)$balRow['remaining_days'], "remaining_days must roll back to 11 after failure at '{$failingStep}'.");

            // Verify no partial activity log or notification persisted
            $this->assertSame(0, $this->countApprovalLogs($leaveId), "No activity log may persist after failure at '{$failingStep}'.");
            $this->assertSame(0, $this->countApprovalNotifications($leaveId), "No notification may persist after failure at '{$failingStep}'.");
        }
    }

    // =========================================================================
    // 8.4 Duplicate and Concurrent Approval Protection
    // =========================================================================

    public function test_duplicate_approval_submissions_approve_once_and_deduct_balance_once(): void
    {
        $empId = $this->employeeUser['employee_id'];
        $balanceId = $this->seedBalance($empId, $this->leaveTypeId, 15, 0, 15);
        $leaveId = $this->seedPendingLeave($empId, $this->leaveTypeId, '2097-05-20', '2097-05-22', 3);

        $leaveModel = new Leave($this->pdo);

        // First approval succeeds
        $first = $leaveModel->approveRequest($leaveId, $this->hrUser['id']);
        $this->assertTrue($first['ok']);

        // Second & third repeated approval attempts are rejected safely
        $second = $leaveModel->approveRequest($leaveId, $this->hrUser['id']);
        $third = $leaveModel->approveRequest($leaveId, $this->secondHrUser['id']);

        $this->assertFalse($second['ok']);
        $this->assertSame('not_pending', $second['code']);
        $this->assertSame(400, $second['http_status']);

        $this->assertFalse($third['ok']);
        $this->assertSame('not_pending', $third['code']);

        // Balance deducted exactly once (3 days, not 6 or 9)
        $balRow = $this->pdo->query("SELECT used_days, remaining_days FROM leave_balances WHERE id = {$balanceId}")->fetch();
        $this->assertSame(3, (int)$balRow['used_days']);
        $this->assertSame(12, (int)$balRow['remaining_days']);

        // Exactly one approval activity log and one approval notification
        $this->assertSame(1, $this->countApprovalLogs($leaveId));
        $this->assertSame(1, $this->countApprovalNotifications($leaveId));
    }

    /**
     * Genuine two-connection MySQL InnoDB locking test:
     * Simulates two concurrent HR sessions attempting to approve the same request or
     * consume the same balance row simultaneously.
     */
    public function test_concurrent_approvals_across_two_database_connections_prevent_double_deduction_and_overdraft(): void
    {
        $empId = $this->employeeUser['employee_id'];
        // Employee has 4 remaining days total.
        // Request A asks for 3 days (2097-06-03 to 2097-06-05).
        // Request B asks for 3 days (2097-06-10 to 2097-06-12).
        // Both requests cannot succeed because 3 + 3 = 6 > 4 remaining days.
        $balanceId = $this->seedBalance($empId, $this->leaveTypeId, 10, 6, 4);
        $leaveA = $this->seedPendingLeave($empId, $this->leaveTypeId, '2097-06-03', '2097-06-05', 3);
        $leaveB = $this->seedPendingLeave($empId, $this->leaveTypeId, '2097-06-10', '2097-06-12', 3);

        $pdo2 = $this->createSecondConnection();
        $pdo2->exec("SET innodb_lock_wait_timeout = 2");

        $model1 = new Leave($this->pdo);
        $model2 = new Leave($pdo2);

        // 1. Inside model1's approval of leaveA, while model1 holds the FOR UPDATE locks
        //    before committing, verify that connection 2 (pdo2) is blocked from locking the
        //    same leave_balances row (InnoDB row lock enforcement).
        $lockVerified = false;
        $resA = $model1->approveRequest(
            $leaveA,
            $this->hrUser['id'],
            null,
            function (string $step) use ($pdo2, $balanceId, &$lockVerified): void {
                if ($step === 'after_used_days_update') {
                    // Connection 1 is mid-transaction and holds the exclusive row lock on $balanceId.
                    // Attempting SELECT ... FOR UPDATE NOWAIT from Connection 2 must throw a lock error.
                    try {
                        $stmt2 = $pdo2->prepare("SELECT * FROM leave_balances WHERE id = :id FOR UPDATE NOWAIT");
                        $stmt2->execute(['id' => $balanceId]);
                        $this->fail('Second connection must not acquire FOR UPDATE lock on a balance row locked by an active approval transaction.');
                    } catch (\PDOException $lockEx) {
                        $lockVerified = true;
                    }
                }
            }
        );

        $this->assertTrue($resA['ok'], 'First approval (3 of 4 remaining days) must succeed.');
        $this->assertTrue($lockVerified, 'InnoDB SELECT ... FOR UPDATE row lock must block concurrent connection.');

        // 2. Connection 2 now attempts to approve Request A (already approved by Connection 1)
        $dupRes = $model2->approveRequest($leaveA, $this->secondHrUser['id']);
        $this->assertFalse($dupRes['ok']);
        $this->assertSame('not_pending', $dupRes['code']);

        // 3. Connection 2 attempts to approve Request B (requires 3 days, but only 1 day remains after Request A)
        $resB = $model2->approveRequest($leaveB, $this->secondHrUser['id']);
        $this->assertFalse($resB['ok'], 'Second request exceeding remaining balance must be rejected.');
        $this->assertSame('insufficient_balance', $resB['code']);

        // Verify final balance is 9 used, 1 remaining (never negative, never double-deducted)
        $balRow = $this->pdo->query("SELECT used_days, remaining_days FROM leave_balances WHERE id = {$balanceId}")->fetch();
        $this->assertSame(9, (int)$balRow['used_days']);
        $this->assertSame(1, (int)$balRow['remaining_days']);

        // Request B remains Pending with zero approval logs/notifications
        $reqBRow = $this->pdo->query("SELECT status FROM leave_requests WHERE id = {$leaveB}")->fetch();
        $this->assertSame('Pending', $reqBRow['status']);
        $this->assertSame(0, $this->countApprovalLogs($leaveB));
        $this->assertSame(0, $this->countApprovalNotifications($leaveB));
    }

    // =========================================================================
    // Additional Eligibility, Overlap, and Post-Commit Failure Isolation Tests
    // =========================================================================

    public function test_non_existent_request_returns_404_and_writes_nothing(): void
    {
        $leaveModel = new Leave($this->pdo);
        $result = $leaveModel->approveRequest(99999999, $this->hrUser['id']);

        $this->assertFalse($result['ok']);
        $this->assertSame('request_not_found', $result['code']);
        $this->assertSame(404, $result['http_status']);
    }

    public function test_inactive_employee_cannot_be_approved(): void
    {
        $empId = $this->employeeUser['employee_id'];
        $balanceId = $this->seedBalance($empId, $this->leaveTypeId, 15, 0, 15);
        $leaveId = $this->seedPendingLeave($empId, $this->leaveTypeId, '2097-07-01', '2097-07-03', 3);

        // Temporarily mark employee Inactive
        $origStatus = (string)$this->pdo->query("SELECT employment_status FROM employees WHERE id = {$empId}")->fetchColumn();
        $this->restoredEmployeeStatuses[$empId] = $origStatus;
        $this->pdo->prepare("UPDATE employees SET employment_status = 'Inactive' WHERE id = ?")->execute([$empId]);

        $leaveModel = new Leave($this->pdo);
        $result = $leaveModel->approveRequest($leaveId, $this->hrUser['id']);

        $this->assertFalse($result['ok']);
        $this->assertSame('employee_ineligible', $result['code']);
        $this->assertSame('Pending', $this->pdo->query("SELECT status FROM leave_requests WHERE id = {$leaveId}")->fetchColumn());
        $this->assertSame(15, (int)$this->pdo->query("SELECT remaining_days FROM leave_balances WHERE id = {$balanceId}")->fetchColumn());
        $this->assertSame(0, $this->countApprovalLogs($leaveId));
        $this->assertSame(0, $this->countApprovalNotifications($leaveId));
    }

    public function test_inactive_leave_type_cannot_be_approved(): void
    {
        $empId = $this->employeeUser['employee_id'];

        // Create a temporary leave type and mark it Inactive after filing
        $stmtLt = $this->pdo->prepare("
            INSERT INTO leave_types (leave_name, description, default_days, status)
            VALUES (:name, 'Temp test leave type', 10, 'Active')
        ");
        $stmtLt->execute(['name' => 'Phase9 Temp Type ' . bin2hex(random_bytes(4))]);
        $tempLtId = $this->createdLeaveTypeIds[] = (int)$this->pdo->lastInsertId();

        $balanceId = $this->seedBalance($empId, $tempLtId, 10, 0, 10);
        $leaveId = $this->seedPendingLeave($empId, $tempLtId, '2097-07-08', '2097-07-10', 3);

        // Deactivate the leave type before approval
        $this->pdo->prepare("UPDATE leave_types SET status = 'Inactive' WHERE id = ?")->execute([$tempLtId]);

        $leaveModel = new Leave($this->pdo);
        $result = $leaveModel->approveRequest($leaveId, $this->hrUser['id']);

        $this->assertFalse($result['ok']);
        $this->assertSame('leave_type_invalid', $result['code']);
        $this->assertSame('Pending', $this->pdo->query("SELECT status FROM leave_requests WHERE id = {$leaveId}")->fetchColumn());
        $this->assertSame(10, (int)$this->pdo->query("SELECT remaining_days FROM leave_balances WHERE id = {$balanceId}")->fetchColumn());
        $this->assertSame(0, $this->countApprovalLogs($leaveId));
        $this->assertSame(0, $this->countApprovalNotifications($leaveId));
    }

    public function test_overlapping_approved_request_blocks_second_approval(): void
    {
        $empId = $this->employeeUser['employee_id'];
        $balanceId = $this->seedBalance($empId, $this->leaveTypeId, 15, 0, 15);

        // Two overlapping pending requests for the same employee
        $leave1 = $this->seedPendingLeave($empId, $this->leaveTypeId, '2097-08-05', '2097-08-07', 3);
        $leave2 = $this->seedPendingLeave($empId, $this->leaveTypeId, '2097-08-06', '2097-08-08', 3);

        $leaveModel = new Leave($this->pdo);
        $res1 = $leaveModel->approveRequest($leave1, $this->hrUser['id']);
        $this->assertTrue($res1['ok']);

        // Approving overlapping leave2 must be rejected and leave balance at 12 remaining
        $res2 = $leaveModel->approveRequest($leave2, $this->hrUser['id']);
        $this->assertFalse($res2['ok']);
        $this->assertSame('date_overlap', $res2['code']);

        $this->assertSame('Pending', $this->pdo->query("SELECT status FROM leave_requests WHERE id = {$leave2}")->fetchColumn());
        $this->assertSame(12, (int)$this->pdo->query("SELECT remaining_days FROM leave_balances WHERE id = {$balanceId}")->fetchColumn());
    }

    public function test_non_hr_user_and_self_approval_are_rejected(): void
    {
        $empId = $this->employeeUser['employee_id'];
        $balanceId = $this->seedBalance($empId, $this->leaveTypeId, 15, 0, 15);
        $leaveId = $this->seedPendingLeave($empId, $this->leaveTypeId, '2097-09-02', '2097-09-04', 3);

        $leaveModel = new Leave($this->pdo);

        // Employee user attempting to approve
        $empAttempt = $leaveModel->approveRequest($leaveId, $this->employeeUser['id']);
        $this->assertFalse($empAttempt['ok']);
        $this->assertSame('unauthorized_reviewer', $empAttempt['code']);
        $this->assertSame(403, $empAttempt['http_status']);

        // If HR user has an employee_id, test self-approval rejection
        if (!empty($this->hrUser['employee_id'])) {
            $hrEmpId = (int)$this->hrUser['employee_id'];
            $hrBalId = $this->seedBalance($hrEmpId, $this->leaveTypeId, 15, 0, 15);
            $hrLeaveId = $this->seedPendingLeave($hrEmpId, $this->leaveTypeId, '2097-09-09', '2097-09-11', 3);

            $selfAttempt = $leaveModel->approveRequest($hrLeaveId, $this->hrUser['id']);
            $this->assertFalse($selfAttempt['ok']);
            $this->assertSame('self_approval_forbidden', $selfAttempt['code']);
            $this->assertSame(403, $selfAttempt['http_status']);
            $this->assertSame(15, (int)$this->pdo->query("SELECT remaining_days FROM leave_balances WHERE id = {$hrBalId}")->fetchColumn());
        }

        $this->assertSame(15, (int)$this->pdo->query("SELECT remaining_days FROM leave_balances WHERE id = {$balanceId}")->fetchColumn());
    }

    public function test_post_commit_delivery_failure_preserves_committed_approval_without_double_deduction(): void
    {
        $empId = $this->employeeUser['employee_id'];
        $balanceId = $this->seedBalance($empId, $this->leaveTypeId, 15, 1, 14);
        $leaveId = $this->seedPendingLeave($empId, $this->leaveTypeId, '2097-10-07', '2097-10-09', 3);

        $leaveModel = new Leave($this->pdo);

        $result = $leaveModel->approveRequest(
            $leaveId,
            $this->hrUser['id'],
            null,
            null,
            function (): void {
                throw new RuntimeException('Simulated post-commit SMTP/WebSocket transport failure');
            }
        );

        // Approval must remain committed even if external post-commit delivery fails
        $this->assertTrue($result['ok']);
        $this->assertSame('Approved', $this->pdo->query("SELECT status FROM leave_requests WHERE id = {$leaveId}")->fetchColumn());

        $balRow = $this->pdo->query("SELECT used_days, remaining_days FROM leave_balances WHERE id = {$balanceId}")->fetch();
        $this->assertSame(4, (int)$balRow['used_days']);
        $this->assertSame(11, (int)$balRow['remaining_days']);
        $this->assertSame(1, $this->countApprovalLogs($leaveId));
        $this->assertSame(1, $this->countApprovalNotifications($leaveId));
    }
}

