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
use App\Domain\Leave\Exceptions\LeaveBalanceNotFoundException;
use DomainException;
use InvalidArgumentException;

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

            // 2. Authorization invariant: an employee may cancel only their own request.
            if (!$command->isHrOrAdmin && $request->employeeId() !== $command->requestingUserId) {
                throw new DomainException('Unauthorized: Employees can only cancel their own leave requests.');
            }

            // 3. Policy: cancelling an already-approved request restores leave and is an
            //    HR-only action that must carry a reason (approved business decision F.2).
            //    Non-HR callers may cancel only Pending requests.
            if ($request->isApproved()) {
                if (!$command->isHrOrAdmin) {
                    throw new DomainException('Unauthorized: only HR may cancel an approved leave request.');
                }
                if (trim((string)$command->reason) === '') {
                    throw new InvalidArgumentException('A cancellation reason is required to cancel an approved leave request.');
                }

                $year = (int) $request->dateRange()->startDate()->format('Y');
                $balance = $this->leaveBalanceRepository->findByEmployeeAndTypeForUpdate(
                    $request->employeeId(),
                    $request->leaveTypeId(),
                    $year
                );

                // Refund must happen exactly once and must not be silently skipped:
                // a missing balance row for an approved request is a data-integrity fault.
                if ($balance === null) {
                    throw LeaveBalanceNotFoundException::forEmployee(
                        $request->employeeId(),
                        $request->leaveTypeId(),
                        $year
                    );
                }

                $balance->refund($request->numberOfDays());
                $this->leaveBalanceRepository->save($balance);
            }

            // 4. Cancel the leave request (throws if already rejected or cancelled)
            $request->cancel();

            // 5. Persist updated status
            $this->leaveRequestRepository->save($request);
        });
    }
}
