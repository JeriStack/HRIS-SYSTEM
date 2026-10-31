<?php

/**
 * Leave Controller Adapter (Anti-Corruption Layer)
 * HRIS-SYSTEM — Clean Architecture / Presentation Layer Adapter
 */

declare(strict_types=1);

namespace App\Application\Leave\Adapters;

use App\Application\Leave\Commands\FileLeaveCommand;
use App\Application\Leave\Commands\ApproveLeaveCommand;
use App\Application\Leave\Commands\RejectLeaveCommand;
use App\Application\Leave\Commands\CancelLeaveCommand;
use App\Application\Leave\UseCases\FileLeaveRequestUseCase;
use App\Application\Leave\UseCases\ApproveLeaveRequestUseCase;
use App\Application\Leave\UseCases\RejectLeaveRequestUseCase;
use App\Application\Leave\UseCases\CancelLeaveRequestUseCase;
use App\Domain\Common\OutboxRepositoryInterface;
use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Leave\Exceptions\LeaveRequestNotFoundException;
use App\Domain\Leave\Exceptions\LeaveBalanceNotFoundException;
use App\Domain\Leave\Exceptions\InsufficientLeaveBalanceException;
use App\Domain\Leave\Exceptions\InvalidStatusTransitionException;
use App\Domain\Leave\Exceptions\InvalidDateRangeException;
use App\Domain\Leave\Exceptions\OverlappingLeaveException;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Events\EventBootstrap;
use App\Infrastructure\Outbox\MySqlOutboxRepository;
use App\Infrastructure\Persistence\Leave\MySqlLeaveRequestRepository;
use App\Infrastructure\Persistence\Leave\MySqlLeaveBalanceRepository;
use Core\Database;
use PDO;
use InvalidArgumentException;
use DomainException;
use DateTimeImmutable;
use Throwable;

class LeaveControllerAdapter
{
    private FileLeaveRequestUseCase $fileUseCase;
    private ApproveLeaveRequestUseCase $approveUseCase;
    private RejectLeaveRequestUseCase $rejectUseCase;
    private CancelLeaveRequestUseCase $cancelUseCase;

    public function __construct(
        ?FileLeaveRequestUseCase $fileUseCase = null,
        ?ApproveLeaveRequestUseCase $approveUseCase = null,
        ?RejectLeaveRequestUseCase $rejectUseCase = null,
        ?CancelLeaveRequestUseCase $cancelUseCase = null,
        ?PDO $pdo = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?OutboxRepositoryInterface $outboxRepository = null
    ) {
        if ($fileUseCase && $approveUseCase && $rejectUseCase && $cancelUseCase) {
            $this->fileUseCase = $fileUseCase;
            $this->approveUseCase = $approveUseCase;
            $this->rejectUseCase = $rejectUseCase;
            $this->cancelUseCase = $cancelUseCase;
            return;
        }

        $connection = $pdo ?? Database::getInstance()->getConnection();
        $txManager = new PdoTransactionManager($connection);
        $requestRepo = new MySqlLeaveRequestRepository($connection);
        $balanceRepo = new MySqlLeaveBalanceRepository($connection);
        $outboxRepo = $outboxRepository ?? new MySqlOutboxRepository($connection);

        $dispatcher = $eventDispatcher ?? EventBootstrap::createConfiguredDispatcher();

        $this->fileUseCase = $fileUseCase ?? new FileLeaveRequestUseCase($requestRepo, $balanceRepo, $txManager);
        $this->approveUseCase = $approveUseCase ?? new ApproveLeaveRequestUseCase(
            $requestRepo,
            $balanceRepo,
            $txManager,
            $dispatcher,
            $outboxRepo
        );
        $this->rejectUseCase = $rejectUseCase ?? new RejectLeaveRequestUseCase(
            $requestRepo,
            $txManager,
            $dispatcher,
            $outboxRepo
        );
        $this->cancelUseCase = $cancelUseCase ?? new CancelLeaveRequestUseCase($requestRepo, $balanceRepo, $txManager);
    }

    public function approve(
        int $requestId,
        int $approverUserId,
        ?DateTimeImmutable $approvedAt = null
    ): LeaveOperationResult {
        try {
            $command = new ApproveLeaveCommand($requestId, $approverUserId, $approvedAt);
            $this->approveUseCase->execute($command);

            return LeaveOperationResult::ok(
                sprintf("Leave request #%d approved successfully.", $requestId),
                ['id' => $requestId, 'status' => 'Approved'],
                200
            );
        } catch (LeaveRequestNotFoundException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 404, 'not_found');
        } catch (InsufficientLeaveBalanceException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 400, 'insufficient_balance');
        } catch (InvalidStatusTransitionException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 400, 'invalid_status');
        } catch (LeaveBalanceNotFoundException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 400, 'balance_not_found');
        } catch (InvalidArgumentException | DomainException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 400, 'validation_error');
        } catch (Throwable $e) {
            error_log('[LeaveControllerAdapter::approve] Error: ' . $e->getMessage());
            return LeaveOperationResult::fail('Unable to approve leave request at this time.', 500, 'server_error');
        }
    }

    public function reject(
        int $requestId,
        int $approverUserId,
        string $rejectionReason,
        ?DateTimeImmutable $rejectedAt = null
    ): LeaveOperationResult {
        try {
            $command = new RejectLeaveCommand($requestId, $approverUserId, $rejectionReason, $rejectedAt);
            $this->rejectUseCase->execute($command);

            return LeaveOperationResult::ok(
                sprintf("Leave request #%d rejected.", $requestId),
                ['id' => $requestId, 'status' => 'Rejected'],
                200
            );
        } catch (LeaveRequestNotFoundException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 404, 'not_found');
        } catch (InvalidStatusTransitionException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 400, 'invalid_status');
        } catch (InvalidArgumentException | DomainException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 400, 'validation_error');
        } catch (Throwable $e) {
            error_log('[LeaveControllerAdapter::reject] Error: ' . $e->getMessage());
            return LeaveOperationResult::fail('Unable to reject leave request at this time.', 500, 'server_error');
        }
    }

    public function file(
        int $employeeId,
        int $leaveTypeId,
        string $startDate,
        string $endDate,
        string $reason
    ): LeaveOperationResult {
        try {
            $command = new FileLeaveCommand($employeeId, $leaveTypeId, $startDate, $endDate, $reason);
            $newId = $this->fileUseCase->execute($command);

            return LeaveOperationResult::ok(
                "Leave request submitted successfully.",
                ['id' => $newId],
                201
            );
        } catch (OverlappingLeaveException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 400, 'overlapping_dates');
        } catch (InsufficientLeaveBalanceException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 400, 'insufficient_balance');
        } catch (InvalidDateRangeException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 400, 'invalid_dates');
        } catch (LeaveBalanceNotFoundException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 400, 'balance_not_found');
        } catch (InvalidArgumentException | DomainException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 400, 'validation_error');
        } catch (Throwable $e) {
            error_log('[LeaveControllerAdapter::file] Error: ' . $e->getMessage());
            return LeaveOperationResult::fail('Unable to submit leave request at this time.', 500, 'server_error');
        }
    }

    public function cancel(
        int $requestId,
        int $cancellerUserId,
        bool $isHr = false,
        ?string $reason = null
    ): LeaveOperationResult {
        try {
            $command = new CancelLeaveCommand($requestId, $cancellerUserId, $isHr, $reason);
            $this->cancelUseCase->execute($command);

            return LeaveOperationResult::ok(
                sprintf("Leave request #%d cancelled.", $requestId),
                ['id' => $requestId, 'status' => 'Cancelled'],
                200
            );
        } catch (LeaveRequestNotFoundException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 404, 'not_found');
        } catch (InvalidStatusTransitionException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 400, 'invalid_status');
        } catch (DomainException $e) {
            $code = str_contains(strtolower($e->getMessage()), 'unauthorized') ? 403 : 400;
            return LeaveOperationResult::fail($e->getMessage(), $code, 'unauthorized');
        } catch (InvalidArgumentException $e) {
            return LeaveOperationResult::fail($e->getMessage(), 400, 'validation_error');
        } catch (Throwable $e) {
            error_log('[LeaveControllerAdapter::cancel] Error: ' . $e->getMessage());
            return LeaveOperationResult::fail('Unable to cancel leave request at this time.', 500, 'server_error');
        }
    }
}
