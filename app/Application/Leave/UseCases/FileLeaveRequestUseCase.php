<?php

/**
 * FileLeaveRequestUseCase
 * HRIS-SYSTEM — Application Layer (Leave Domain)
 */

declare(strict_types=1);

namespace App\Application\Leave\UseCases;

use App\Application\Leave\Commands\FileLeaveCommand;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use App\Domain\Leave\Exceptions\OverlappingLeaveException;
use App\Domain\Leave\Exceptions\LeaveBalanceNotFoundException;
use App\Domain\Leave\Exceptions\InsufficientLeaveBalanceException;

class FileLeaveRequestUseCase
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly LeaveBalanceRepositoryInterface $leaveBalanceRepository,
        private readonly TransactionManagerInterface $transactionManager
    ) {
    }

    public function execute(FileLeaveCommand $command): int
    {
        return $this->transactionManager->transactional(function () use ($command): int {
            // 1. Construct domain value object (validates dates, ordering, and weekend invariants)
            $dateRange = LeaveDateRange::fromStrings($command->startDate, $command->endDate);

            // 2. Invariant: Check for overlapping active leave requests for this employee
            $overlapping = $this->leaveRequestRepository->findOverlapping(
                $command->employeeId,
                $dateRange
            );

            if (!empty($overlapping)) {
                throw OverlappingLeaveException::forDates($command->startDate, $command->endDate);
            }

            // 3. Resolve quota year and verify sufficient remaining balance
            $year = (int) $dateRange->startDate()->format('Y');
            $balance = $this->leaveBalanceRepository->findByEmployeeAndType(
                $command->employeeId,
                $command->leaveTypeId,
                $year
            );

            if ($balance === null) {
                throw LeaveBalanceNotFoundException::forEmployee(
                    $command->employeeId,
                    $command->leaveTypeId,
                    $year
                );
            }

            if (!$balance->hasSufficientBalance($dateRange->workingDays())) {
                throw InsufficientLeaveBalanceException::forDays(
                    $balance->remainingDays(),
                    $dateRange->workingDays()
                );
            }

            // 4. Create pending LeaveRequest domain entity
            $request = LeaveRequest::create(
                $command->employeeId,
                $command->leaveTypeId,
                $dateRange,
                $command->reason
            );

            // 5. Persist and return new ID
            return $this->leaveRequestRepository->save($request);
        });
    }
}
