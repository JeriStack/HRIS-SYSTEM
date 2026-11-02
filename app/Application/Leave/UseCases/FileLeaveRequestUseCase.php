<?php

/**
 * FileLeaveRequestUseCase
 * HRIS-SYSTEM — Application Layer (Leave Domain)
 *
 * The single authoritative leave-filing path for both the web and API controllers.
 * Everything runs inside one transaction so concurrent filings cannot over-commit a
 * balance or create overlapping requests:
 *   1. Validate the date range (value object: Mon-Fri working days, start <= end, > 0).
 *   2. Reject a request that spans more than one leave year (quotas are per year).
 *   3. Reject an inactive employee or inactive leave type.
 *   4. Reject overlap with the employee's Pending/Approved requests.
 *   5. Auto-initialize missing balances, then lock the balance row FOR UPDATE.
 *   6. Reject when requested days exceed remaining-minus-already-reserved-pending days.
 *   7. Insert the Pending request and run the optional in-transaction callback
 *      (HR notification) so it commits or rolls back with the request.
 */

declare(strict_types=1);

namespace App\Application\Leave\UseCases;

use App\Application\Leave\Commands\FileLeaveCommand;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveFilingGuardInterface;
use App\Domain\Leave\Exceptions\OverlappingLeaveException;
use App\Domain\Leave\Exceptions\LeaveBalanceNotFoundException;
use App\Domain\Leave\Exceptions\InsufficientLeaveBalanceException;
use App\Domain\Leave\Exceptions\CrossYearLeaveException;
use App\Domain\Leave\Exceptions\LeaveApprovalNotPermittedException;

class FileLeaveRequestUseCase
{
    /** @var (callable(int $requestId): void)|null */
    private $onFiled = null;

    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly LeaveBalanceRepositoryInterface $leaveBalanceRepository,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly ?LeaveFilingGuardInterface $filingGuard = null
    ) {
    }

    /**
     * Register a callback to run inside the filing transaction after the request is
     * persisted (e.g. writing the HR notification) so it commits atomically with the
     * request. Cleared after each execute() call.
     *
     * @param (callable(int $requestId): void)|null $callback
     */
    public function withinTransaction(?callable $callback): self
    {
        $this->onFiled = $callback;
        return $this;
    }

    public function execute(FileLeaveCommand $command): int
    {
        $callback = $this->onFiled;
        $this->onFiled = null;

        return $this->transactionManager->transactional(function () use ($command, $callback): int {
            // 1. Validate date range (weekend/ordering/zero-day invariants live in the VO).
            $dateRange = LeaveDateRange::fromStrings($command->startDate, $command->endDate);

            // 2. A leave request must resolve to a single quota year.
            $startYear = (int) $dateRange->startDate()->format('Y');
            $endYear = (int) $dateRange->endDate()->format('Y');
            if ($startYear !== $endYear) {
                throw CrossYearLeaveException::forYears($startYear, $endYear);
            }
            $year = $startYear;

            // 3. Eligibility re-checks outside the Leave aggregate.
            if ($this->filingGuard !== null) {
                if (!$this->filingGuard->isEmployeeActive($command->employeeId)) {
                    throw LeaveApprovalNotPermittedException::employeeInactive();
                }
                if (!$this->filingGuard->isLeaveTypeActive($command->leaveTypeId)) {
                    throw LeaveApprovalNotPermittedException::leaveTypeInactive();
                }
            }

            // 4. Reject overlap with existing Pending/Approved requests.
            $overlapping = $this->leaveRequestRepository->findOverlapping(
                $command->employeeId,
                $dateRange
            );
            if (!empty($overlapping)) {
                throw OverlappingLeaveException::forDates($command->startDate, $command->endDate);
            }

            // 5. Auto-initialize missing balances, then lock the balance row for this filing.
            if ($this->filingGuard !== null) {
                $this->filingGuard->ensureBalancesInitialized($command->employeeId, $year);
            }

            $balance = $this->leaveBalanceRepository->findByEmployeeAndTypeForUpdate(
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

            // 6. Reserve days already committed to other Pending requests so two concurrent
            //    filings cannot both pass the balance check.
            $requestedDays = $dateRange->workingDays();
            $reserved = $this->filingGuard !== null
                ? $this->filingGuard->pendingReservedDays($command->employeeId, $command->leaveTypeId, $year)
                : 0;
            $effectiveRemaining = $balance->remainingDays() - $reserved;

            if ($requestedDays > $effectiveRemaining) {
                throw InsufficientLeaveBalanceException::forDays(
                    max(0, $effectiveRemaining),
                    $requestedDays
                );
            }

            // 7. Persist the Pending request, then run the in-transaction callback.
            $request = LeaveRequest::create(
                $command->employeeId,
                $command->leaveTypeId,
                $dateRange,
                $command->reason
            );

            $newId = $this->leaveRequestRepository->save($request);

            if ($callback !== null) {
                $callback($newId);
            }

            return $newId;
        });
    }
}
