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
use App\Services\LeaveLedgerService;
use App\Services\LeavePolicyEngine;
use DomainException;
use InvalidArgumentException;
use PDO;

class CancelLeaveRequestUseCase
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly LeaveBalanceRepositoryInterface $leaveBalanceRepository,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly ?PDO $pdo = null
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
                $days = $request->numberOfDays();
                $policyEngine = $this->pdo !== null ? new LeavePolicyEngine($this->pdo) : null;
                $typePolicy = $policyEngine !== null
                    ? $policyEngine->resolveLeaveTypeWithPolicy($request->leaveTypeId(), $request->dateRange()->startDateString())
                    : null;
                $model = (string)($typePolicy['entitlement_model'] ?? 'annual');
                $ledger = $this->pdo !== null ? new LeaveLedgerService($this->pdo) : null;
                $isConf = $request->isConfidential() || !empty($typePolicy['is_confidential']);

                if ($model === 'unpaid') {
                    if ($ledger !== null) {
                        $ledger->record([
                            'employee_id'       => $request->employeeId(),
                            'leave_type_id'     => $request->leaveTypeId(),
                            'leave_request_id'  => $request->id(),
                            'policy_version_id' => $request->policyVersionId(),
                            'balance_year'      => $year,
                            'transaction_type'  => 'balance_restoration',
                            'days_delta'        => 0,
                            'balance_before'    => 0,
                            'balance_after'     => 0,
                            'remarks'           => 'Approved unpaid leave cancelled by HR',
                            'is_confidential'   => $isConf ? 1 : 0,
                            'actor_user_id'     => $command->requestingUserId,
                        ]);
                    }
                } elseif ($model === 'event_based' && $this->pdo !== null && $request->eventEntitlementId() !== null) {
                    $eid = $request->eventEntitlementId();
                    $evStmt = $this->pdo->prepare("SELECT remaining_days FROM leave_event_entitlements WHERE id = :eid LIMIT 1 FOR UPDATE");
                    $evStmt->execute(['eid' => $eid]);
                    $before = $evStmt->fetchColumn();
                    if ($before !== false) {
                        $after = (float)$before + $days;
                        $updEv = $this->pdo->prepare("
                            UPDATE leave_event_entitlements
                            SET used_days = GREATEST(0, used_days - :days),
                                remaining_days = remaining_days + :rdays,
                                verification_status = 'Active'
                            WHERE id = :eid
                        ");
                        $updEv->execute(['days' => $days, 'rdays' => $days, 'eid' => $eid]);

                        if ($ledger !== null) {
                            $ledger->record([
                                'employee_id'          => $request->employeeId(),
                                'leave_type_id'        => $request->leaveTypeId(),
                                'leave_request_id'     => $request->id(),
                                'event_entitlement_id' => $eid,
                                'policy_version_id'    => $request->policyVersionId(),
                                'balance_year'         => $year,
                                'transaction_type'     => 'balance_restoration',
                                'days_delta'           => $days,
                                'balance_before'       => (float)$before,
                                'balance_after'        => $after,
                                'remarks'              => $isConf
                                    ? sprintf('Restored %d day(s) for cancelled confidential leave #%d', $days, (int)$request->id())
                                    : sprintf('Restored %d event leave day(s) on HR cancellation', $days),
                                'is_confidential'      => $isConf ? 1 : 0,
                                'actor_user_id'        => $command->requestingUserId,
                            ]);
                        }
                    }
                } else {
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

                    $before = $balance->remainingDays();
                    $balance->refund($days);
                    $this->leaveBalanceRepository->save($balance);

                    if ($ledger !== null) {
                        $ledger->record([
                            'employee_id'       => $request->employeeId(),
                            'leave_type_id'     => $request->leaveTypeId(),
                            'leave_balance_id'  => $balance->id(),
                            'leave_request_id'  => $request->id(),
                            'policy_version_id' => $request->policyVersionId(),
                            'balance_year'      => $year,
                            'transaction_type'  => 'balance_restoration',
                            'days_delta'        => $days,
                            'balance_before'    => $before,
                            'balance_after'     => $balance->remainingDays(),
                            'remarks'           => $isConf
                                ? sprintf('Restored %d day(s) on cancelled confidential request #%d', $days, (int)$request->id())
                                : sprintf('Restored %d day(s) from cancelled approved leave #%d', $days, (int)$request->id()),
                            'is_confidential'   => $isConf ? 1 : 0,
                            'actor_user_id'     => $command->requestingUserId,
                        ]);
                    }
                }
            }

            // Release pending reservation on event entitlement if pending was cancelled
            if ($request->isPending() && $this->pdo !== null && $request->eventEntitlementId() !== null) {
                $updPending = $this->pdo->prepare("
                    UPDATE leave_event_entitlements
                    SET pending_days = GREATEST(0, pending_days - :days)
                    WHERE id = :eid
                ");
                $updPending->execute(['days' => $request->numberOfDays(), 'eid' => $request->eventEntitlementId()]);
            }

            // 4. Cancel the leave request (throws if already rejected or cancelled)
            $request->cancel();

            // 5. Persist updated status
            $this->leaveRequestRepository->save($request);
        });
    }
}
