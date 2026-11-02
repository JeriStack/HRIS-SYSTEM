<?php

namespace Tests\Feature;

use App\Models\Leave;
use Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * LV-08 regression: Leave::adjustBalance must preserve the stored balance invariant
 * remaining_days = allocated_days - used_days, and must refuse an allocation below the
 * days already used instead of silently clamping remaining_days to 0 (which later breaks
 * LeaveBalance::reconstitute and every approval/cancellation for that balance).
 *
 * Runs only against the isolated hris_test database (enforced by tests/bootstrap.php).
 */
class LeaveBalanceAdjustmentTest extends TestCase
{
    private ?PDO $pdo = null;
    private Leave $leave;
    private int $employeeId = 0;
    private int $leaveTypeId = 0;
    private int $year = 2095;
    private array $balanceIds = [];

    protected function setUp(): void
    {
        if (!Database::getInstance()->isConnected()) {
            $this->markTestSkipped('Database connection is not available.');
        }
        $this->pdo = Database::getInstance()->getConnection();
        $this->leave = new Leave();

        $this->employeeId = (int)$this->pdo->query("SELECT id FROM employees LIMIT 1")->fetchColumn();
        $this->leaveTypeId = (int)$this->pdo->query("SELECT id FROM leave_types LIMIT 1")->fetchColumn();
        if ($this->employeeId <= 0 || $this->leaveTypeId <= 0) {
            $this->markTestSkipped('No employee/leave-type fixtures in hris_test.');
        }

        // Seed a balance with 5 days already used.
        $this->pdo->prepare(
            "INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days)
             VALUES (:e, :t, :y, 15, 5, 10)"
        )->execute(['e' => $this->employeeId, 't' => $this->leaveTypeId, 'y' => $this->year]);
        $this->balanceIds[] = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null && !empty($this->balanceIds)) {
            $in = implode(',', array_map('intval', $this->balanceIds));
            $this->pdo->exec("DELETE FROM leave_balances WHERE id IN ({$in})");
        }
    }

    private function currentBalance(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT allocated_days, used_days, remaining_days FROM leave_balances
             WHERE employee_id = :e AND leave_type_id = :t AND year = :y LIMIT 1"
        );
        $stmt->execute(['e' => $this->employeeId, 't' => $this->leaveTypeId, 'y' => $this->year]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function test_valid_adjustment_keeps_balance_invariant(): void
    {
        $this->assertTrue($this->leave->adjustBalance($this->employeeId, $this->leaveTypeId, $this->year, 20));

        $bal = $this->currentBalance();
        $this->assertSame(20, (int)$bal['allocated_days']);
        $this->assertSame(5, (int)$bal['used_days']);
        $this->assertSame(15, (int)$bal['remaining_days'], 'remaining must equal allocated - used.');
    }

    public function test_allocation_below_used_days_is_rejected_without_mutation(): void
    {
        // 5 days already used; allocating 3 would force remaining negative.
        $result = $this->leave->adjustBalance($this->employeeId, $this->leaveTypeId, $this->year, 3);

        $this->assertFalse($result, 'Allocation below used days must be rejected.');
        $this->assertSame('allocation_below_used', $this->leave->getLastErrorCode());
        $this->assertNotNull($this->leave->getLastError());

        $bal = $this->currentBalance();
        $this->assertSame(15, (int)$bal['allocated_days'], 'Rejected adjustment must not mutate the row.');
        $this->assertSame(10, (int)$bal['remaining_days']);
    }

    public function test_allocation_equal_to_used_days_is_allowed(): void
    {
        $this->assertTrue($this->leave->adjustBalance($this->employeeId, $this->leaveTypeId, $this->year, 5));

        $bal = $this->currentBalance();
        $this->assertSame(5, (int)$bal['allocated_days']);
        $this->assertSame(0, (int)$bal['remaining_days']);
    }
}
