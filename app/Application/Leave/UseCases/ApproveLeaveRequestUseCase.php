<?php

/**
 * ApproveLeaveRequestUseCase
 * HRIS-SYSTEM — Application Layer (Leave Domain)
 */

declare(strict_types=1);

namespace App\Application\Leave\UseCases;

use App\Application\Leave\Commands\ApproveLeaveCommand;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Common\OutboxRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveApprovalGuardInterface;
use App\Domain\Leave\Exceptions\LeaveRequestNotFoundException;
use App\Domain\Leave\Exceptions\LeaveBalanceNotFoundException;
use App\Domain\Leave\Exceptions\LeaveApprovalNotPermittedException;

class ApproveLeaveRequestUseCase
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly LeaveBalanceRepositoryInterface $leaveBalanceRepository,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?OutboxRepositoryInterface $outboxRepository = null,
        private readonly ?LeaveApprovalGuardInterface $approvalGuard = null
    ) {
    }

    public function execute(ApproveLeaveCommand $command): void
    {
        /** @var \App\Domain\Common\DomainEventInterface[] $events */
        $events = [];

        $this->transactionManager->transactional(function () use ($command, &$events) {
            // 1. Lock and retrieve leave request
            $request = $this->leaveRequestRepository->findByIdForUpdate($command->requestId);
            if ($request === null) {
                throw LeaveRequestNotFoundException::forId($command->requestId);
            }

            // 2. Account/organization re-checks that live outside the Leave aggregate.
            //    These mirror the hardened legacy path so both share identical invariants:
            //    active HR reviewer, no self-approval, active employee, active leave type.
            if ($this->approvalGuard !== null) {
                $reviewerEmployeeId = $this->approvalGuard->activeHrReviewerEmployeeId($command->approverUserId);
                if ($reviewerEmployeeId === null) {
                    throw LeaveApprovalNotPermittedException::reviewerNotActiveHr();
                }
                if ($reviewerEmployeeId > 0 && $reviewerEmployeeId === $request->employeeId()) {
                    throw LeaveApprovalNotPermittedException::selfApproval();
                }
                if (!$this->approvalGuard->isEmployeeActive($request->employeeId())) {
                    throw LeaveApprovalNotPermittedException::employeeInactive();
                }
                if (!$this->approvalGuard->isLeaveTypeActive($request->leaveTypeId())) {
                    throw LeaveApprovalNotPermittedException::leaveTypeInactive();
                }
            }

            // 3. Leave year must be unambiguous: a request may not span two years
            //    because the balance quota is tracked per year.
            $startYear = (int) $request->dateRange()->startDate()->format('Y');
            $endYear = (int) $request->dateRange()->endDate()->format('Y');
            if ($startYear !== $endYear) {
                throw LeaveApprovalNotPermittedException::spansMultipleYears($startYear, $endYear);
            }
            $year = $startYear;

            // 4. Reject approval when the schedule overlaps an already-approved request
            //    (the pending request under review is excluded by id).
            $overlapping = $this->leaveRequestRepository->findOverlapping(
                $request->employeeId(),
                $request->dateRange(),
                $request->id()
            );
            foreach ($overlapping as $other) {
                if ($other->isApproved()) {
                    throw LeaveApprovalNotPermittedException::overlapsApproved((int)$other->id());
                }
            }

            // 5. Lock and retrieve leave balance
            $balance = $this->leaveBalanceRepository->findByEmployeeAndTypeForUpdate(
                $request->employeeId(),
                $request->leaveTypeId(),
                $year
            );

            if ($balance === null) {
                throw LeaveBalanceNotFoundException::forEmployee(
                    $request->employeeId(),
                    $request->leaveTypeId(),
                    $year
                );
            }

            // 6. Transition request state (throws if already finalized)
            $request->approve($command->approverUserId, $command->approvedAt);
            $events = $request->releaseEvents();

            // 5. Deduct from leave balance (throws if insufficient remaining days)
            $balance->deduct($request->numberOfDays());

            // 6. Persist state within transaction boundary
            $this->leaveRequestRepository->save($request);
            $this->leaveBalanceRepository->save($balance);

            // 6b. Persist Outbox events atomically within the active transaction
            if ($this->outboxRepository !== null) {
                foreach ($events as $event) {
                    $this->outboxRepository->record($event, 'LeaveRequest', (string)$request->id());
                }
            }
        });

        // 7. Post-Commit Dispatch:
        // When Outbox is configured, events are delivered asynchronously by the outbox worker.
        // Fallback to direct synchronous dispatch only when outbox is omitted.
        if ($this->outboxRepository === null && $this->eventDispatcher !== null) {
            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }
        }
    }
}
