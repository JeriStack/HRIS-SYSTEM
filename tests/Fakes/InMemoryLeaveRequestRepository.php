<?php

/**
 * In-Memory LeaveRequest Repository Fake for Unit Testing
 */

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\ValueObjects\LeaveDateRange;

class InMemoryLeaveRequestRepository implements LeaveRequestRepositoryInterface
{
    /** @var array<int, LeaveRequest> */
    private array $storage = [];
    private int $autoIncrement = 1;
    private array $lockedIds = [];

    public function findById(int $id): ?LeaveRequest
    {
        return $this->storage[$id] ?? null;
    }

    public function findByIdForUpdate(int $id): ?LeaveRequest
    {
        $this->lockedIds[] = $id;
        return $this->findById($id);
    }

    public function save(LeaveRequest $request): int
    {
        $id = $request->id();
        if ($id === null) {
            $id = $this->autoIncrement++;
            // Reconstitute with assigned ID
            $request = LeaveRequest::reconstitute(
                id: $id,
                employeeId: $request->employeeId(),
                leaveTypeId: $request->leaveTypeId(),
                dateRange: $request->dateRange(),
                status: $request->status(),
                reason: $request->reason(),
                rejectionReason: $request->rejectionReason(),
                approvedBy: $request->approvedBy(),
                approvedAt: $request->approvedAt(),
                createdAt: $request->createdAt()
            );
        }

        $this->storage[$id] = $request;
        return $id;
    }

    public function findOverlapping(int $employeeId, LeaveDateRange $range, ?int $excludeRequestId = null): array
    {
        $overlapping = [];
        foreach ($this->storage as $req) {
            if ($req->employeeId() !== $employeeId) {
                continue;
            }
            if ($excludeRequestId !== null && $req->id() === $excludeRequestId) {
                continue;
            }
            if ($req->isCancelled() || $req->isRejected()) {
                continue;
            }
            if ($req->dateRange()->overlapsWith($range)) {
                $overlapping[] = $req;
            }
        }
        return $overlapping;
    }

    public function findByEmployee(int $employeeId): array
    {
        return array_values(
            array_filter($this->storage, fn(LeaveRequest $req) => $req->employeeId() === $employeeId)
        );
    }

    public function wasLocked(int $id): bool
    {
        return in_array($id, $this->lockedIds, true);
    }
}
