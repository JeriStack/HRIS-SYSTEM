<?php

/**
 * LeaveUseCasesWithRealDatabaseTest
 * Integration verification of Application Use Cases wired to MySQL Repositories
 */

declare(strict_types=1);

namespace Tests\Integration\Leave;

use PHPUnit\Framework\TestCase;
use Core\Database;
use PDO;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Persistence\Leave\MySqlLeaveRequestRepository;
use App\Infrastructure\Persistence\Leave\MySqlLeaveBalanceRepository;
use App\Application\Leave\UseCases\FileLeaveRequestUseCase;
use App\Application\Leave\UseCases\ApproveLeaveRequestUseCase;
use App\Application\Leave\UseCases\CancelLeaveRequestUseCase;
use App\Application\Leave\Commands\FileLeaveCommand;
use App\Application\Leave\Commands\ApproveLeaveCommand;
use App\Application\Leave\Commands\CancelLeaveCommand;
use App\Domain\Leave\Entities\LeaveBalance;
use DateTimeImmutable;

class LeaveUseCasesWithRealDatabaseTest extends TestCase
{
    private PDO $pdo;
    private PdoTransactionManager $txManager;
    private MySqlLeaveRequestRepository $requestRepo;
    private MySqlLeaveBalanceRepository $balanceRepo;

    private FileLeaveRequestUseCase $fileUseCase;
    private ApproveLeaveRequestUseCase $approveUseCase;
    private CancelLeaveRequestUseCase $cancelUseCase;

    private int $testEmployeeId = 1;
    private int $testLeaveTypeId = 1;
    private int $testApproverId = 1;
    private int $testYear = 2099;

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

        $this->fileUseCase = new FileLeaveRequestUseCase(
            $this->requestRepo,
            $this->balanceRepo,
            $this->txManager
        );
        $this->approveUseCase = new ApproveLeaveRequestUseCase(
            $this->requestRepo,
            $this->balanceRepo,
            $this->txManager
        );
        $this->cancelUseCase = new CancelLeaveRequestUseCase(
            $this->requestRepo,
            $this->balanceRepo,
            $this->txManager
        );

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

    public function test_full_lifecycle_filing_approval_and_cancellation(): void
    {
        // 1. Initialize balance in MySQL: 15 allocated days for year 2099
        $balance = LeaveBalance::create(
            employeeId: $this->testEmployeeId,
            leaveTypeId: $this->testLeaveTypeId,
            year: $this->testYear,
            allocatedDays: 15
        );
        $balId = $this->balanceRepo->save($balance);
        $this->cleanupBalanceIds[] = $balId;

        // Nov 2, 2099 is Monday; Nov 4, 2099 is Wednesday = 3 working days
        $fileCommand = new FileLeaveCommand(
            employeeId: $this->testEmployeeId,
            leaveTypeId: $this->testLeaveTypeId,
            startDate: '2099-11-02',
            endDate: '2099-11-04',
            reason: 'Full lifecycle DDD integration test'
        );

        // 2. File leave request
        $requestId = $this->fileUseCase->execute($fileCommand);
        $this->assertGreaterThan(0, $requestId);
        $this->cleanupRequestIds[] = $requestId;

        $created = $this->requestRepo->findById($requestId);
        $this->assertNotNull($created);
        $this->assertTrue($created->isPending());
        $this->assertSame(3, $created->numberOfDays());

        // 3. Approve leave request
        $approveCommand = new ApproveLeaveCommand(
            requestId: $requestId,
            approverUserId: $this->testApproverId,
            approvedAt: new DateTimeImmutable('2099-10-31 10:00:00')
        );

        $this->approveUseCase->execute($approveCommand);

        $approved = $this->requestRepo->findById($requestId);
        $this->assertNotNull($approved);
        $this->assertTrue($approved->isApproved());
        $this->assertSame($this->testApproverId, $approved->approvedBy());

        // Verify balance deducted in MySQL
        $balanceAfterApprove = $this->balanceRepo->findByEmployeeAndType(
            $this->testEmployeeId,
            $this->testLeaveTypeId,
            $this->testYear
        );
        $this->assertNotNull($balanceAfterApprove);
        $this->assertSame(3, $balanceAfterApprove->usedDays());
        $this->assertSame(12, $balanceAfterApprove->remainingDays());

        // 4. Cancel approved leave request (HR cancellation)
        $cancelCommand = new CancelLeaveCommand(
            requestId: $requestId,
            requestingUserId: $this->testApproverId,
            isHrOrAdmin: true
        );

        $this->cancelUseCase->execute($cancelCommand);

        $cancelled = $this->requestRepo->findById($requestId);
        $this->assertNotNull($cancelled);
        $this->assertTrue($cancelled->isCancelled());

        // Verify balance refunded in MySQL
        $balanceAfterCancel = $this->balanceRepo->findByEmployeeAndType(
            $this->testEmployeeId,
            $this->testLeaveTypeId,
            $this->testYear
        );
        $this->assertNotNull($balanceAfterCancel);
        $this->assertSame(0, $balanceAfterCancel->usedDays());
        $this->assertSame(15, $balanceAfterCancel->remainingDays());
    }
}
