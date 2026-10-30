<?php

/**
 * RejectLeaveRequestUseCase
 * HRIS-SYSTEM — Application Layer (Leave Domain)
 */

declare(strict_types=1);

namespace App\Application\Leave\UseCases;

use App\Application\Leave\Commands\RejectLeaveCommand;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Exceptions\LeaveRequestNotFoundException;

class RejectLeaveRequestUseCase
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly TransactionManagerInterface $transactionManager
    ) {
    }

    public function execute(RejectLeaveCommand $command): void
    {
        $this->transactionManager->transactional(function () use ($command) {
            // 1. Lock and retrieve leave request
            $request = $this->leaveRequestRepository->findByIdForUpdate($command->requestId);
            if ($request === null) {
                throw LeaveRequestNotFoundException::forId($command->requestId);
            }

            // 2. Reject request (validates reason and status transition invariants)
            $request->reject(
                $command->approverUserId,
                $command->rejectionReason,
                $command->rejectedAt
            );

            // 3. Persist state
            $this->leaveRequestRepository->save($request);
        });
    }
}
