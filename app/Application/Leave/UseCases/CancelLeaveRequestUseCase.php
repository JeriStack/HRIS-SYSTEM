<?php

/**
 * CancelLeaveRequestUseCase
 * HRIS-SYSTEM — Application Layer (Leave Domain)
 */

declare(strict_types=1);

namespace App\Application\Leave\UseCases;

use App\Application\Leave\Commands\CancelLeaveCommand;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use App\Domain\Leave\Exceptions\LeaveRequestNotFoundException;
use DomainException;

class CancelLeaveRequestUseCase
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly LeaveBalanceRepositoryInterface $leaveBalanceRepository,
        private readonly TransactionManagerInterface $transactionManager
    ) {
    }

    public function execute(CancelLeaveCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
            // 1. Lock and retrieve leave request
            $request = $this->leaveRequestRepository->findByIdForUpdate($command->requestId);
            if ($request === null) {
                throw LeaveRequestNotFoundException::forId($command->requestId);
            }

            // 2. Authorization invariant: Only owner employee or HR/Admin may cancel
            if (!$command->isHrOrAdmin && $request->employeeId() !== $command->requestingUserId) {
                throw new DomainException('Unauthorized: Employees can only cancel their own leave requests.');
            }

            // 3. If request was already approved, refund the deducted balance
            if ($request->isApproved()) {
                $year = (int) $request->dateRange()->startDate()->format('Y');
                $balance = $this->leaveBalanceRepository->findByEmployeeAndTypeForUpdate(
                    $request->employeeId(),
                    $request->leaveTypeId(),
                    $year
                );

                if ($balance !== null) {
                    $balance->refund($request->numberOfDays());
                    $this->leaveBalanceRepository->save($balance);
                }
            }

            // 4. Cancel the leave request (throws if already rejected or cancelled)
            $request->cancel();

            // 5. Persist updated status
            $this->leaveRequestRepository->save($request);
        });
    }
}
