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
use App\Domain\Leave\Exceptions\InsufficientLeaveBalanceException;
use App\Services\LeaveLedgerService;
use App\Services\LeavePolicyEngine;
use PDO;

class ApproveLeaveRequestUseCase
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly LeaveBalanceRepositoryInterface $leaveBalanceRepository,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?OutboxRepositoryInterface $outboxRepository = null,
        private readonly ?LeaveApprovalGuardInterface $approvalGuard = null,
        private readonly ?PDO $pdo = null
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

            $policyEngine = $this->pdo !== null ? new LeavePolicyEngine($this->pdo) : null;
            $typePolicy = $policyEngine !== null
                ? $policyEngine->resolveLeaveTypeWithPolicy($request->leaveTypeId(), $request->dateRange()->startDateString())
                : null;
            $code = strtoupper((string)($typePolicy['leave_code'] ?? ''));
            $model = (string)($typePolicy['entitlement_model'] ?? 'annual');
            $unit = (string)($typePolicy['entitlement_unit'] ?? $request->entitlementUnit());
            $rules = is_array($typePolicy['rules_decoded'] ?? null) ? $typePolicy['rules_decoded'] : [];
            $allowsCrossYear = ($code === LeavePolicyEngine::CODE_MATERNITY)
                || ($model === 'event_based' && $unit === 'calendar_days')
                || !empty($rules['allow_cross_year'])
                || $request->entitlementUnit() === 'calendar_days';

            // 3. Reject cross-year unless allowed by statutory policy (e.g. RA 11210 Maternity).
            $startYear = (int) $request->dateRange()->startDate()->format('Y');
            $endYear = (int) $request->dateRange()->endDate()->format('Y');
            if ($startYear !== $endYear && !$allowsCrossYear) {
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

            $ledger = $this->pdo !== null ? new LeaveLedgerService($this->pdo) : null;
            $isConf = $request->isConfidential() || !empty($typePolicy['is_confidential']);
            $days = $request->numberOfDays();

            // 5. Enforce model-specific deduction/verification and transition request state
            if ($model === 'unpaid') {
                $request->approve($command->approverUserId, $command->approvedAt);
                $events = $request->releaseEvents();
                $this->leaveRequestRepository->save($request);

                if ($ledger !== null) {
                    $ledger->record([
                        'employee_id'       => $request->employeeId(),
                        'leave_type_id'     => $request->leaveTypeId(),
                        'leave_request_id'  => $request->id(),
                        'policy_version_id' => $request->policyVersionId(),
                        'balance_year'      => $year,
                        'transaction_type'  => 'unpaid_recorded',
                        'days_delta'        => 0,
                        'balance_before'    => 0,
                        'balance_after'     => 0,
                        'remarks'           => sprintf('Approved %d unpaid leave day(s) [%s to %s]', $days, $request->dateRange()->startDateString(), $request->dateRange()->endDateString()),
                        'is_confidential'   => $isConf ? 1 : 0,
                        'actor_user_id'     => $command->approverUserId,
                    ]);
                }
            } elseif ($model === 'event_based' && $this->pdo !== null) {
                if ($code === LeavePolicyEngine::CODE_PATERNITY && $policyEngine !== null) {
                    $patQual = $policyEngine->findLatestQualification($request->employeeId(), 'paternity_marriage');
                    if ($patQual !== null && ($patQual['verification_status'] ?? '') !== 'Verified') {
                        throw new LeaveApprovalNotPermittedException(
                            'Paternity Leave approval requires HR verification of marital status / spouse delivery proof first.'
                        );
                    }
                }

                $eventId = $request->eventEntitlementId();
                $beforeRem = 0.0;
                $afterRem = 0.0;
                if ($eventId !== null) {
                    $evStmt = $this->pdo->prepare("
                        SELECT id, entitled_days, used_days, pending_days, remaining_days
                        FROM leave_event_entitlements
                        WHERE id = :eid
                        LIMIT 1
                        FOR UPDATE
                    ");
                    $evStmt->execute(['eid' => $eventId]);
                    $ev = $evStmt->fetch(PDO::FETCH_ASSOC);
                    if ($ev) {
                        $beforeRem = (float)$ev['remaining_days'];
                        if ($days > $beforeRem) {
                            throw InsufficientLeaveBalanceException::forDays((int)floor($beforeRem), $days);
                        }
                        $afterRem = max(0.0, $beforeRem - $days);
                        $newStatus = $afterRem <= 0.0 ? 'Completed' : 'Active';
                        $updEv = $this->pdo->prepare("
                            UPDATE leave_event_entitlements
                            SET used_days = used_days + :days,
                                pending_days = GREATEST(0, pending_days - :pdays),
                                remaining_days = :rem,
                                verification_status = :vstatus,
                                verified_by = COALESCE(verified_by, :vby),
                                verified_at = COALESCE(verified_at, NOW())
                            WHERE id = :eid
                        ");
                        $updEv->execute([
                            'days'    => $days,
                            'pdays'   => $days,
                            'rem'     => $afterRem,
                            'vstatus' => $newStatus,
                            'vby'     => $command->approverUserId,
                            'eid'     => $eventId,
                        ]);
                    }
                }

                $request->approve($command->approverUserId, $command->approvedAt);
                $events = $request->releaseEvents();
                $this->leaveRequestRepository->save($request);

                if ($ledger !== null) {
                    $ledger->record([
                        'employee_id'          => $request->employeeId(),
                        'leave_type_id'        => $request->leaveTypeId(),
                        'leave_request_id'     => $request->id(),
                        'event_entitlement_id' => $eventId,
                        'policy_version_id'    => $request->policyVersionId(),
                        'balance_year'         => $year,
                        'transaction_type'     => 'approved_deduction',
                        'days_delta'           => -$days,
                        'balance_before'       => $beforeRem,
                        'balance_after'        => $afterRem,
                        'remarks'              => $isConf
                            ? sprintf('Approved confidential leave request #%d (%d day(s))', (int)$request->id(), $days)
                            : sprintf('Approved %d event-based day(s) for %s', $days, (string)($typePolicy['leave_name'] ?? 'Event Leave')),
                        'is_confidential'      => $isConf ? 1 : 0,
                        'actor_user_id'        => $command->approverUserId,
                    ]);
                }
            } else {
                // Standard annual balance deduction
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

                $beforeRem = $balance->remainingDays();
                $request->approve($command->approverUserId, $command->approvedAt);
                $events = $request->releaseEvents();

                $balance->deduct($days);

                $this->leaveRequestRepository->save($request);
                $this->leaveBalanceRepository->save($balance);

                if ($ledger !== null) {
                    $ledger->record([
                        'employee_id'       => $request->employeeId(),
                        'leave_type_id'     => $request->leaveTypeId(),
                        'leave_balance_id'  => $balance->id(),
                        'leave_request_id'  => $request->id(),
                        'policy_version_id' => $request->policyVersionId(),
                        'balance_year'      => $year,
                        'transaction_type'  => 'approved_deduction',
                        'days_delta'        => -$days,
                        'balance_before'    => $beforeRem,
                        'balance_after'     => $balance->remainingDays(),
                        'remarks'           => $isConf
                            ? sprintf('Approved confidential leave request #%d (%d day(s))', (int)$request->id(), $days)
                            : sprintf('Approved %d day(s) [%s to %s]', $days, $request->dateRange()->startDateString(), $request->dateRange()->endDateString()),
                        'is_confidential'   => $isConf ? 1 : 0,
                        'actor_user_id'     => $command->approverUserId,
                    ]);
                }
            }

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
