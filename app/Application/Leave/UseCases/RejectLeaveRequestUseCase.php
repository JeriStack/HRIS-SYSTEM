<?php

/**
 * RejectLeaveRequestUseCase
 * HRIS-SYSTEM — Application Layer (Leave Domain)
 */

declare(strict_types=1);

namespace App\Application\Leave\UseCases;

use App\Application\Leave\Commands\RejectLeaveCommand;
use App\Domain\Common\TransactionManagerInterface;
use App\Domain\Common\EventDispatcherInterface;
use App\Domain\Common\OutboxRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Exceptions\LeaveRequestNotFoundException;
use PDO;

class RejectLeaveRequestUseCase
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?OutboxRepositoryInterface $outboxRepository = null,
        private readonly ?PDO $pdo = null
    ) {
    }

    public function execute(RejectLeaveCommand $command): void
    {
        /** @var \App\Domain\Common\DomainEventInterface[] $events */
        $events = [];

        $this->transactionManager->transactional(function () use ($command, &$events) {
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
            $events = $request->releaseEvents();

            // 3. Persist state and release pending reservation on event entitlement if linked
            $this->leaveRequestRepository->save($request);
            if ($this->pdo !== null && $request->eventEntitlementId() !== null) {
                $updPending = $this->pdo->prepare("
                    UPDATE leave_event_entitlements
                    SET pending_days = GREATEST(0, pending_days - :days)
                    WHERE id = :eid
                ");
                $updPending->execute([
                    'days' => $request->numberOfDays(),
                    'eid'  => $request->eventEntitlementId(),
                ]);
            }

            // 3b. Persist Outbox events atomically within the active transaction
            if ($this->outboxRepository !== null) {
                foreach ($events as $event) {
                    $this->outboxRepository->record($event, 'LeaveRequest', (string)$request->id());
                }
            }
        });

        // 4. Post-Commit Dispatch:
        // When Outbox is configured, events are delivered asynchronously by the outbox worker.
        // Fallback to direct synchronous dispatch only when outbox is omitted.
        if ($this->outboxRepository === null && $this->eventDispatcher !== null) {
            foreach ($events as $event) {
                $this->eventDispatcher->dispatch($event);
            }
        }
    }
}
