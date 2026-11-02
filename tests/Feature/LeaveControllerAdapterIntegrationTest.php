<?php

/**
 * LeaveControllerAdapterIntegrationTest
 * TDD Workstream 2 - Controller Adapter Integration
 */

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Core\Database;
use Core\Session;
use PDO;
use App\Controllers\Api\LeaveController as ApiLeaveController;
use App\Application\Leave\Adapters\LeaveControllerAdapter;
use App\Domain\Leave\Entities\LeaveBalance;
use App\Infrastructure\Persistence\Leave\MySqlLeaveBalanceRepository;
use App\Infrastructure\Persistence\Leave\MySqlLeaveRequestRepository;
use App\Infrastructure\Database\PdoTransactionManager;
use DateTimeImmutable;

class LeaveControllerAdapterIntegrationTest extends TestCase
{
    private PDO $pdo;
    private LeaveControllerAdapter $adapter;
    private MySqlLeaveBalanceRepository $balanceRepo;
    private MySqlLeaveRequestRepository $requestRepo;

    private int $testEmployeeId = 1;
    private int $testLeaveTypeId = 1;
    private int $testApproverId = 1;
    private int $testYear = 2096;

    /** @var int[] */
    private array $cleanupRequestIds = [];
    /** @var int[] */
    private array $cleanupBalanceIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database is not connected in this environment.');
        }

        $this->pdo = $db->getConnection();
        $this->adapter = new LeaveControllerAdapter(pdo: $this->pdo);
        $this->balanceRepo = new MySqlLeaveBalanceRepository($this->pdo);
        $this->requestRepo = new MySqlLeaveRequestRepository($this->pdo);

        $stmtEmp = $this->pdo->query("SELECT id FROM employees LIMIT 1");
        $emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);
        if ($emp) {
            $this->testEmployeeId = (int) $emp['id'];
        }

        $stmtLt = $this->pdo->query("SELECT id FROM leave_types LIMIT 1");
        $lt = $stmtLt->fetch(PDO::FETCH_ASSOC);
        if ($lt) {
            $this->testLeaveTypeId = (int) $lt['id'];
        }

        $stmtUser = $this->pdo->query("SELECT id FROM users LIMIT 1");
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $this->testApproverId = (int) $user['id'];
        }

        // Initialize 15 days quota in MySQL for year 2096
        $balance = LeaveBalance::create(
            employeeId: $this->testEmployeeId,
            leaveTypeId: $this->testLeaveTypeId,
            year: $this->testYear,
            allocatedDays: 15
        );
        $balId = $this->balanceRepo->save($balance);
        $this->cleanupBalanceIds[] = $balId;
    }

    protected function tearDown(): void
    {
        if (!empty($this->cleanupRequestIds)) {
            $in = implode(',', array_map('intval', $this->cleanupRequestIds));
            $this->pdo->exec("DELETE FROM leave_requests WHERE id IN ({$in})");
        }

        if (!empty($this->cleanupBalanceIds)) {
            $in = implode(',', array_map('intval', $this->cleanupBalanceIds));
            $this->pdo->exec("DELETE FROM leave_balances WHERE id IN ({$in})");
        }

        parent::tearDown();
    }

    public function test_adapter_full_filing_approval_and_cancellation_cycle(): void
    {
        // 1. File request via adapter (2096-11-05 is Monday, 2096-11-07 is Wednesday = 3 working days)
        $fileResult = $this->adapter->file(
            $this->testEmployeeId,
            $this->testLeaveTypeId,
            '2096-11-05',
            '2096-11-07',
            'Adapter test leave application'
        );

        $this->assertTrue($fileResult->success);
        $this->assertSame(201, $fileResult->statusCode);
        $requestId = (int)$fileResult->data['id'];
        $this->cleanupRequestIds[] = $requestId;

        // 2. Approve via adapter
        $approveResult = $this->adapter->approve($requestId, $this->testApproverId);
        $this->assertTrue($approveResult->success);
        $this->assertSame(200, $approveResult->statusCode);

        // Verify balance deducted in MySQL
        $balAfterApprove = $this->balanceRepo->findByEmployeeAndType(
            $this->testEmployeeId,
            $this->testLeaveTypeId,
            $this->testYear
        );
        $this->assertNotNull($balAfterApprove);
        $this->assertSame(3, $balAfterApprove->usedDays());
        $this->assertSame(12, $balAfterApprove->remainingDays());

        // 3. Cancel approved request (HR permission + mandatory reason per decision F.2)
        $cancelResult = $this->adapter->cancel($requestId, $this->testApproverId, true, 'Approved in error; reversing.');
        $this->assertTrue($cancelResult->success);
        $this->assertSame(200, $cancelResult->statusCode);

        // Verify balance refunded in MySQL
        $balAfterCancel = $this->balanceRepo->findByEmployeeAndType(
            $this->testEmployeeId,
            $this->testLeaveTypeId,
            $this->testYear
        );
        $this->assertNotNull($balAfterCancel);
        $this->assertSame(0, $balAfterCancel->usedDays());
        $this->assertSame(15, $balAfterCancel->remainingDays());
    }

    public function test_adapter_rejection_cycle(): void
    {
        $fileResult = $this->adapter->file(
            $this->testEmployeeId,
            $this->testLeaveTypeId,
            '2096-11-12',
            '2096-11-14',
            'Trip to be rejected'
        );
        $this->assertTrue($fileResult->success);
        $requestId = (int)$fileResult->data['id'];
        $this->cleanupRequestIds[] = $requestId;

        $rejectResult = $this->adapter->reject($requestId, $this->testApproverId, 'Critical deployment schedule');
        $this->assertTrue($rejectResult->success);
        $this->assertSame(200, $rejectResult->statusCode);

        $req = $this->requestRepo->findById($requestId);
        $this->assertNotNull($req);
        $this->assertTrue($req->isRejected());
        $this->assertSame('Critical deployment schedule', $req->rejectionReason());
    }
}
