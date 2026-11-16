<?php

namespace Tests\Unit;

use App\Models\Leave;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class LeaveSidebarCountTest extends TestCase
{
    public function test_count_pending_by_employee_queries_pending_status_for_employee(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['emp_id' => 42])
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('3');

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(static function (string $sql): bool {
                return str_contains($sql, 'FROM leave_requests')
                    && str_contains($sql, 'employee_id = :emp_id')
                    && str_contains($sql, "status = 'Pending'");
            }))
            ->willReturn($stmt);

        $leave = new Leave($pdo);
        $this->assertSame(3, $leave->countPendingByEmployee(42));
    }

    public function test_count_pending_by_employee_returns_zero_when_none(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['emp_id' => 7])
            ->willReturn(true);
        $stmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $leave = new Leave($pdo);
        $this->assertSame(0, $leave->countPendingByEmployee(7));
    }

    public function test_count_pending_all_queries_pending_requests_across_organization(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('8');

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with($this->callback(static function (string $sql): bool {
                return str_contains($sql, 'FROM leave_requests')
                    && str_contains($sql, "status = 'Pending'")
                    && !str_contains($sql, 'employee_id');
            }))
            ->willReturn($stmt);

        $leave = new Leave($pdo);
        $this->assertSame(8, $leave->countPendingAll());
    }

    public function test_count_pending_all_returns_zero_when_query_fails(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->willReturn(false);

        $leave = new Leave($pdo);
        $this->assertSame(0, $leave->countPendingAll());
    }
}
