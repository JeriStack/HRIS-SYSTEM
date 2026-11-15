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
use App\Services\LeavePolicyEngine;
use PDO;

class FileLeaveRequestUseCase
{
    /** @var (callable(int $requestId): void)|null */
    private $onFiled = null;

    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly LeaveBalanceRepositoryInterface $leaveBalanceRepository,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly ?LeaveFilingGuardInterface $filingGuard = null,
        private readonly ?PDO $pdo = null
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
            $policyEngine = $this->pdo !== null ? new LeavePolicyEngine($this->pdo) : null;
            $typePolicy = $policyEngine !== null
                ? $policyEngine->resolveLeaveTypeWithPolicy($command->leaveTypeId, $command->startDate)
                : null;

            $unit = $command->entitlementUnit
                ?? ($typePolicy['entitlement_unit'] ?? 'working_days');
            $unit = $unit === 'calendar_days' ? 'calendar_days' : 'working_days';

            // 1. Validate date range using the policy's day unit (Mon-Fri working or calendar).
            $dateRange = LeaveDateRange::fromStringsForUnit($command->startDate, $command->endDate, $unit);

            // 2. Reject cross-year ranges unless explicitly permitted (e.g. RA 11210 Maternity).
            $startYear = (int) $dateRange->startDate()->format('Y');
            $endYear = (int) $dateRange->endDate()->format('Y');
            $code = strtoupper((string)($typePolicy['leave_code'] ?? ''));
            $model = (string)($typePolicy['entitlement_model'] ?? 'annual');
            $rules = is_array($typePolicy['rules_decoded'] ?? null) ? $typePolicy['rules_decoded'] : [];
            $allowsCrossYear = ($code === LeavePolicyEngine::CODE_MATERNITY)
                || ($model === 'event_based' && $unit === 'calendar_days')
                || !empty($rules['allow_cross_year'])
                || $command->entitlementUnit === 'calendar_days';

            if ($startYear !== $endYear && !$allowsCrossYear) {
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

            // 5. Evaluate statutory/company rules via LeavePolicyEngine when database is present.
            $resolvedFiling = null;
            if ($policyEngine !== null) {
                $meta = $command->metadata ?? [];
                $resolvedFiling = $policyEngine->validateFiling(
                    $command->employeeId,
                    $command->leaveTypeId,
                    $command->startDate,
                    $command->endDate,
                    $meta,
                    !empty($meta['_has_doc']),
                    !empty($meta['_strict_form'])
                );
            }

            $isPaid = $resolvedFiling !== null ? !empty($resolvedFiling['is_paid']) : true;
            $isConfidential = $resolvedFiling !== null ? !empty($resolvedFiling['is_confidential']) : false;
            $policyVersionId = $command->policyVersionId
                ?? ($resolvedFiling['policy_version_id'] ?? ($typePolicy['policy_version_id'] ?? null));
            $eventEntitlementId = $command->eventEntitlementId
                ?? ($resolvedFiling['event_entitlement_id'] ?? null);
            $cleanMeta = $command->metadata ?? [];
            unset($cleanMeta['_has_doc'], $cleanMeta['_strict_form']);
            $metaJson = !empty($cleanMeta) ? json_encode($cleanMeta, JSON_UNESCAPED_UNICODE) : null;
            $requestedDays = $command->overrideDays
                ?? ($resolvedFiling['number_of_days'] ?? $dateRange->effectiveDays($unit));

            // 6. Enforce quota by entitlement model (annual vs event_based vs unpaid).
            if ($model === 'unpaid') {
                // Unpaid leave (LWOP) tracks days separately without checking/deducting paid balances.
            } elseif ($model === 'event_based' && $policyEngine !== null && $this->pdo !== null) {
                $eventEntitlementId = $policyEngine->ensureEventEntitlementForRequest(
                    $command->employeeId,
                    $command->leaveTypeId,
                    $command->startDate,
                    $command->endDate,
                    $resolvedFiling ?? [
                        'entitlement_model'  => 'event_based',
                        'leave_code'         => $code,
                        'number_of_days'     => $requestedDays,
                        'policy_version_id'  => $policyVersionId,
                        'is_confidential'    => $isConfidential ? 1 : 0,
                        'metadata'           => $cleanMeta,
                        'type_policy'        => $typePolicy ?? [],
                    ]
                );
            } else {
                // Annual quota path: initialize, lock row FOR UPDATE, reserve pending days.
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
            }

            // 7. Persist the Pending request, then run the in-transaction callback.
            $request = LeaveRequest::create(
                employeeId: $command->employeeId,
                leaveTypeId: $command->leaveTypeId,
                dateRange: $dateRange,
                reason: $command->reason,
                entitlementUnit: $unit,
                isPaid: $isPaid,
                isConfidential: $isConfidential,
                policyVersionId: $policyVersionId !== null ? (int)$policyVersionId : null,
                eventEntitlementId: $eventEntitlementId !== null ? (int)$eventEntitlementId : null,
                applicationMetadataJson: $metaJson ?: null,
                durationDaysOverride: $command->overrideDays
            );

            $newId = $this->leaveRequestRepository->save($request);

            if ($callback !== null) {
                $callback($newId);
            }

            return $newId;
        });
    }
}
