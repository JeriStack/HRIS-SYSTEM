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
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use App\Domain\Leave\Exceptions\LeaveRequestNotFoundException;
use App\Domain\Leave\Exceptions\LeaveBalanceNotFoundException;

class ApproveLeaveRequestUseCase
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly LeaveBalanceRepositoryInterface $leaveBalanceRepository,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly ?EventDispatcherInterface $eventDispatcher = null
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

            // 2. Resolve calendar year for quota tracking
            $year = (int) $request->dateRange()->startDate()->format('Y');

            // 3. Lock and retrieve leave balance
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

            // 4. Transition request state (throws if already finalized)
            $request->approve($command->approverUserId, $command->approvedAt);
            $events = $request->releaseEvents();

            // 5. Deduct from leave balance (throws if insufficient remaining days)
            $balance->deduct($request->numberOfDays());

            // 6. Persist state within transaction boundary
            $this->leaveRequestRepository->save($request);
            $this->leaveBalanceRepository->save($balance);
        });

        // 7. Strictly Post-Commit Publication: Dispatched only after database transaction commits
        if ($this->eventDispatcher !== null) {
            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }
        }
    }
}
