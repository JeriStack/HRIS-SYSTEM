<?php

/**
 * MySqlLeaveBalanceRepository
 * HRIS-SYSTEM — Infrastructure Layer (Leave Domain Persistence)
 */

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Leave;

use App\Domain\Leave\Entities\LeaveBalance;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use PDO;

class MySqlLeaveBalanceRepository implements LeaveBalanceRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmployeeAndType(int $employeeId, int $leaveTypeId, int $year): ?LeaveBalance
    {
        $stmt = $this->pdo->prepare("
            SELECT id, employee_id, leave_type_id, year, allocated_days, used_days, remaining_days
            FROM leave_balances
            WHERE employee_id = :emp_id AND leave_type_id = :lt_id AND year = :year
            LIMIT 1
        ");

        $stmt->execute([
            'emp_id' => $employeeId,
            'lt_id'  => $leaveTypeId,
            'year'   => $year,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findByEmployeeAndTypeForUpdate(int $employeeId, int $leaveTypeId, int $year): ?LeaveBalance
    {
        $stmt = $this->pdo->prepare("
            SELECT id, employee_id, leave_type_id, year, allocated_days, used_days, remaining_days
            FROM leave_balances
            WHERE employee_id = :emp_id AND leave_type_id = :lt_id AND year = :year
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'emp_id' => $employeeId,
            'lt_id'  => $leaveTypeId,
            'year'   => $year,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function save(LeaveBalance $balance): int
    {
        if ($balance->id() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO leave_balances (
                    employee_id, leave_type_id, year, allocated_days, used_days, remaining_days
                ) VALUES (
                    :emp_id, :lt_id, :year, :alloc, :used, :rem
                )
            ");

            $stmt->execute([
                'emp_id' => $balance->employeeId(),
                'lt_id'  => $balance->leaveTypeId(),
                'year'   => $balance->year(),
                'alloc'  => $balance->allocatedDays(),
                'used'   => $balance->usedDays(),
                'rem'    => $balance->remainingDays(),
            ]);

            return (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare("
            UPDATE leave_balances
            SET allocated_days = :alloc,
                used_days = :used,
                remaining_days = :rem
            WHERE id = :id
        ");

        $stmt->execute([
            'alloc' => $balance->allocatedDays(),
            'used'  => $balance->usedDays(),
            'rem'   => $balance->remainingDays(),
            'id'    => $balance->id(),
        ]);

        return $balance->id();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToEntity(array $row): LeaveBalance
    {
        return LeaveBalance::reconstitute(
            id: (int) $row['id'],
            employeeId: (int) $row['employee_id'],
            leaveTypeId: (int) $row['leave_type_id'],
            year: (int) $row['year'],
            allocatedDays: (int) $row['allocated_days'],
            usedDays: (int) $row['used_days'],
            remainingDays: (int) $row['remaining_days']
        );
    }
}
