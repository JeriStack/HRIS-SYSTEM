<?php

/**
 * MySqlLeaveRepositoryIntegrationTest
 * TDD Phase 6 - Infrastructure & Persistence
 */

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use PHPUnit\Framework\TestCase;
use Core\Database;
use PDO;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Persistence\Leave\MySqlLeaveRequestRepository;
use App\Infrastructure\Persistence\Leave\MySqlLeaveBalanceRepository;
use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\Entities\LeaveBalance;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\ValueObjects\LeaveStatus;
use DateTimeImmutable;

class MySqlLeaveRepositoryIntegrationTest extends TestCase
{
    private PDO $pdo;
    private PdoTransactionManager $txManager;
    private MySqlLeaveRequestRepository $requestRepo;
    private MySqlLeaveBalanceRepository $balanceRepo;

    private int $testEmployeeId = 9999;
    private int $testLeaveTypeId = 1;
    private int $testApproverId = 1;
    private int $testYear = 2098;
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
        $this->txManager = new PdoTransactionManager($this->pdo);
        $this->requestRepo = new MySqlLeaveRequestRepository($this->pdo);
        $this->balanceRepo = new MySqlLeaveBalanceRepository($this->pdo);

        // Ensure test employee exists or use existing employee from seed
        $stmt = $this->pdo->query("SELECT id FROM employees LIMIT 1");
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
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

    public function test_can_persist_and_retrieve_leave_balance(): void
    {
        $balance = LeaveBalance::create(
            employeeId: $this->testEmployeeId,
            leaveTypeId: $this->testLeaveTypeId,
            year: $this->testYear,
            allocatedDays: 15
        );

        $id = $this->balanceRepo->save($balance);
        $this->assertGreaterThan(0, $id);
        $this->cleanupBalanceIds[] = $id;

        $found = $this->balanceRepo->findByEmployeeAndType(
            $this->testEmployeeId,
            $this->testLeaveTypeId,
            $this->testYear
        );

        $this->assertNotNull($found);
        $this->assertSame($id, $found->id());
        $this->assertSame(15, $found->allocatedDays());
        $this->assertSame(0, $found->usedDays());
        $this->assertSame(15, $found->remainingDays());
    }

    public function test_can_lock_and_update_leave_balance_inside_transaction(): void
    {
        $balance = LeaveBalance::create(
            employeeId: $this->testEmployeeId,
            leaveTypeId: $this->testLeaveTypeId,
            year: $this->testYear,
            allocatedDays: 15
        );
        $id = $this->balanceRepo->save($balance);
        $this->cleanupBalanceIds[] = $id;

        $this->txManager->transactional(function () use ($id) {
            $locked = $this->balanceRepo->findByEmployeeAndTypeForUpdate(
                $this->testEmployeeId,
                $this->testLeaveTypeId,
                $this->testYear
            );
            $this->assertNotNull($locked);

            $locked->deduct(5);
            $this->balanceRepo->save($locked);
        });

        $reloaded = $this->balanceRepo->findByEmployeeAndType(
            $this->testEmployeeId,
            $this->testLeaveTypeId,
            $this->testYear
        );

        $this->assertNotNull($reloaded);
        $this->assertSame(5, $reloaded->usedDays());
        $this->assertSame(10, $reloaded->remainingDays());
    }

    public function test_can_save_find_and_update_leave_request(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2098-11-03', '2098-11-05'); // 3 working days (Mon to Wed)
        $request = LeaveRequest::create(
            employeeId: $this->testEmployeeId,
            leaveTypeId: $this->testLeaveTypeId,
            dateRange: $dateRange,
            reason: 'TDD DDD Persistence Test'
        );

        $requestId = $this->requestRepo->save($request);
        $this->assertGreaterThan(0, $requestId);
        $this->cleanupRequestIds[] = $requestId;

        // Retrieve and verify
        $found = $this->requestRepo->findById($requestId);
        $this->assertNotNull($found);
        $this->assertSame($requestId, $found->id());
        $this->assertSame(3, $found->numberOfDays());
        $this->assertTrue($found->isPending());

        // Approve and update
        $found->approve($this->testApproverId, new DateTimeImmutable('2098-10-31 09:00:00'));
        $this->requestRepo->save($found);

        $updated = $this->requestRepo->findById($requestId);
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isApproved());
        $this->assertSame($this->testApproverId, $updated->approvedBy());
    }

    public function test_detects_overlapping_requests_in_mysql(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2098-11-03', '2098-11-07');
        $request = LeaveRequest::create(
            employeeId: $this->testEmployeeId,
            leaveTypeId: $this->testLeaveTypeId,
            dateRange: $dateRange,
            reason: 'Original range'
        );

        $reqId = $this->requestRepo->save($request);
        $this->cleanupRequestIds[] = $reqId;

        // Overlapping range
        $overlappingRange = LeaveDateRange::fromStrings('2098-11-06', '2098-11-10');
        $overlaps = $this->requestRepo->findOverlapping($this->testEmployeeId, $overlappingRange);

        $this->assertCount(1, $overlaps);
        $this->assertSame($reqId, $overlaps[0]->id());

        // Non-overlapping range
        $nonOverlappingRange = LeaveDateRange::fromStrings('2098-11-10', '2098-11-14');
        $nonOverlaps = $this->requestRepo->findOverlapping($this->testEmployeeId, $nonOverlappingRange);

        $this->assertCount(0, $nonOverlaps);
    }
}
