<?php

/**
 * MySqlLeaveRequestRepository
 * HRIS-SYSTEM — Infrastructure Layer (Leave Domain Persistence)
 */

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Leave;

use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use App\Domain\Leave\ValueObjects\LeaveStatus;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use PDO;
use DateTimeImmutable;

class MySqlLeaveRequestRepository implements LeaveRequestRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?LeaveRequest
    {
        $stmt = $this->pdo->prepare("
            SELECT id, employee_id, leave_type_id, start_date, end_date, number_of_days,
                   reason, rejection_reason, status, approved_by, approved_at, created_at
            FROM leave_requests
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findByIdForUpdate(int $id): ?LeaveRequest
    {
        $stmt = $this->pdo->prepare("
            SELECT id, employee_id, leave_type_id, start_date, end_date, number_of_days,
                   reason, rejection_reason, status, approved_by, approved_at, created_at
            FROM leave_requests
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function save(LeaveRequest $request): int
    {
        if ($request->id() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO leave_requests (
                    employee_id, leave_type_id, start_date, end_date, number_of_days,
                    reason, rejection_reason, status, approved_by, approved_at, created_at
                ) VALUES (
                    :emp_id, :lt_id, :start_date, :end_date, :num_days,
                    :reason, :rej_reason, :status, :approved_by, :approved_at, :created_at
                )
            ");

            $stmt->execute([
                'emp_id'      => $request->employeeId(),
                'lt_id'       => $request->leaveTypeId(),
                'start_date'  => $request->dateRange()->startDateString(),
                'end_date'    => $request->dateRange()->endDateString(),
                'num_days'    => $request->numberOfDays(),
                'reason'      => $request->reason(),
                'rej_reason'  => $request->rejectionReason(),
                'status'      => $request->status()->value(),
                'approved_by' => $request->approvedBy(),
                'approved_at' => $request->approvedAt()?->format('Y-m-d H:i:s'),
                'created_at'  => $request->createdAt()?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
            ]);

            return (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("
            UPDATE leave_requests
            SET status = :status,
                rejection_reason = :rej_reason,
                approved_by = :approved_by,
                approved_at = :approved_at
            WHERE id = :id
        ");

        $stmt->execute([
            'status'      => $request->status()->value(),
            'rej_reason'  => $request->rejectionReason(),
            'approved_by' => $request->approvedBy(),
            'approved_at' => $request->approvedAt()?->format('Y-m-d H:i:s'),
            'id'          => $request->id(),
        ]);

        return $request->id();
    }

    public function findOverlapping(int $employeeId, LeaveDateRange $range, ?int $excludeRequestId = null): array
    {
        $sql = "
            SELECT id, employee_id, leave_type_id, start_date, end_date, number_of_days,
                   reason, rejection_reason, status, approved_by, approved_at, created_at
            FROM leave_requests
            WHERE employee_id = :emp_id
              AND status IN ('Pending', 'Approved')
              AND start_date <= :end_date
              AND end_date >= :start_date
        ";

        $params = [
            'emp_id'     => $employeeId,
            'start_date' => $range->startDateString(),
            'end_date'   => $range->endDateString(),
        ];

        if ($excludeRequestId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeRequestId;
        }

        $sql .= " ORDER BY start_date ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->mapRowToEntity($row);
        }

        return $results;
    }

    public function findByEmployee(int $employeeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, employee_id, leave_type_id, start_date, end_date, number_of_days,
                   reason, rejection_reason, status, approved_by, approved_at, created_at
            FROM leave_requests
            WHERE employee_id = :emp_id
            ORDER BY id DESC
        ");

        $stmt->execute(['emp_id' => $employeeId]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->mapRowToEntity($row);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToEntity(array $row): LeaveRequest
    {
        return LeaveRequest::reconstitute(
            id: (int) $row['id'],
            employeeId: (int) $row['employee_id'],
            leaveTypeId: (int) $row['leave_type_id'],
            dateRange: LeaveDateRange::fromStrings($row['start_date'], $row['end_date']),
            status: LeaveStatus::fromString($row['status']),
            reason: (string) $row['reason'],
            rejectionReason: $row['rejection_reason'] !== null ? (string) $row['rejection_reason'] : null,
            approvedBy: $row['approved_by'] !== null ? (int) $row['approved_by'] : null,
            approvedAt: $row['approved_at'] !== null ? new DateTimeImmutable($row['approved_at']) : null,
            createdAt: $row['created_at'] !== null ? new DateTimeImmutable($row['created_at']) : null
        );
    }
}
