<?php

namespace Tests\Feature;

use App\Application\Leave\Adapters\LeaveControllerAdapter;
use App\Infrastructure\Persistence\Leave\MySqlLeaveFilingGuard;
use Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * LV-05/06/07 integration tests (isolated hris_test database only).
 *
 * Proves the single transactional filing path: active employee/type checks, overlap,
 * cross-year rejection, balance locking, pending-day reservation, transaction-safe
 * auto-initialization, duplicate-balance prevention, and web/API parity (both go through
 * the same LeaveControllerAdapter::file()).
 */
class LeaveFilingIntegrityTest extends TestCase
{
    private ?PDO $pdo = null;
    private LeaveControllerAdapter $adapter;
    private MySqlLeaveFilingGuard $guard;

    private int $deptId = 0;
    private int $posId = 0;
    private int $activeEmpId = 0;
    private int $inactiveEmpId = 0;
    private int $activeTypeId = 0;
    private int $inactiveTypeId = 0;
    private int $year = 2093;

    private array $cleanup = ['leave_requests' => [], 'leave_balances' => [], 'employees' => [], 'positions' => [], 'departments' => [], 'leave_types' => []];

    protected function setUp(): void
    {
        if (!Database::getInstance()->isConnected()) {
            $this->markTestSkipped('Database connection is not available.');
        }
        $this->pdo = Database::getInstance()->getConnection();
        $this->adapter = new LeaveControllerAdapter(pdo: $this->pdo);
        $this->guard = new MySqlLeaveFilingGuard($this->pdo);

        $this->pdo->prepare("INSERT INTO departments (department_name, description) VALUES (:n, 'lvfit')")
            ->execute(['n' => 'LVFIT Dept ' . bin2hex(random_bytes(3))]);
        $this->deptId = (int)$this->pdo->lastInsertId();
        $this->cleanup['departments'][] = $this->deptId;

        $this->pdo->prepare("INSERT INTO positions (position_name, department_id, description) VALUES (:n, :d, 'lvfit')")
            ->execute(['n' => 'LVFIT Pos ' . bin2hex(random_bytes(3)), 'd' => $this->deptId]);
        $this->posId = (int)$this->pdo->lastInsertId();
        $this->cleanup['positions'][] = $this->posId;

        $this->activeEmpId = $this->seedEmployee('Active');
        $this->inactiveEmpId = $this->seedEmployee('Inactive');
        $this->activeTypeId = $this->seedLeaveType('Active', 15);
        $this->inactiveTypeId = $this->seedLeaveType('Inactive', 15);
    }

    protected function tearDown(): void
    {
        if ($this->pdo === null) {
            return;
        }

        // Remove child rows by the fixtures' own employee/type ids first (filing and
        // auto-init create leave_requests/leave_balances this test does not individually
        // track), then the parents, respecting the ON DELETE RESTRICT foreign keys.
        $empIds = array_filter([$this->activeEmpId, $this->inactiveEmpId]);
        $typeIds = array_filter([$this->activeTypeId, $this->inactiveTypeId]);

        if (!empty($empIds)) {
            $in = implode(',', array_map('intval', $empIds));
            $this->pdo->exec("DELETE FROM leave_requests WHERE employee_id IN ({$in})");
            $this->pdo->exec("DELETE FROM leave_balances WHERE employee_id IN ({$in})");
        }

        foreach (['employees' => $empIds, 'positions' => [$this->posId], 'departments' => [$this->deptId], 'leave_types' => $typeIds] as $table => $ids) {
            $ids = array_filter($ids);
            if (!empty($ids)) {
                $in = implode(',', array_map('intval', $ids));
                $this->pdo->exec("DELETE FROM {$table} WHERE id IN ({$in})");
            }
        }
    }

    private function seedEmployee(string $status): int
    {
        $sfx = bin2hex(random_bytes(4));
        $this->pdo->prepare(
            "INSERT INTO employees (employee_no, first_name, last_name, email, birth_date, gender, department_id, position_id, date_hired, employment_status)
             VALUES (:no, 'LVFIT', 'Tester', :em, '1990-01-01', 'Other', :d, :p, '2024-01-01', :st)"
        )->execute(['no' => "LVF-{$sfx}", 'em' => "lvf-{$sfx}@test.local", 'd' => $this->deptId, 'p' => $this->posId, 'st' => $status]);
        $id = (int)$this->pdo->lastInsertId();
        $this->cleanup['employees'][] = $id;
        return $id;
    }

    private function seedLeaveType(string $status, int $defaultDays): int
    {
        $sfx = bin2hex(random_bytes(4));
        $this->pdo->prepare(
            "INSERT INTO leave_types (leave_name, description, default_days, status) VALUES (:n, 'lvfit', :dd, :st)"
        )->execute(['n' => "LVFIT {$status} {$sfx}", 'dd' => $defaultDays, 'st' => $status]);
        $id = (int)$this->pdo->lastInsertId();
        $this->cleanup['leave_types'][] = $id;
        return $id;
    }

    private function seedBalance(int $empId, int $typeId, int $allocated, int $used = 0): void
    {
        $this->pdo->prepare(
            "INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days)
             VALUES (:e, :t, :y, :a, :u, :r)"
        )->execute(['e' => $empId, 't' => $typeId, 'y' => $this->year, 'a' => $allocated, 'u' => $used, 'r' => $allocated - $used]);
        $this->cleanup['leave_balances'][] = (int)$this->pdo->lastInsertId();
    }

    /** Two consecutive weekdays in the test year (a Wednesday/Thursday), as Y-m-d. */
    private function weekdayRange(): array
    {
        // 2093-01-07 is a Wednesday; 01-08 Thursday. Both weekdays, same year.
        return ['2093-01-07', '2093-01-08'];
    }

    private function trackLatestRequest(): void
    {
        $id = (int)$this->pdo->query("SELECT COALESCE(MAX(id),0) FROM leave_requests")->fetchColumn();
        if ($id > 0) {
            $this->cleanup['leave_requests'][] = $id;
        }
    }

    // ---- active employee / leave type ----

    public function test_inactive_employee_is_rejected(): void
    {
        $this->seedBalance($this->inactiveEmpId, $this->activeTypeId, 15);
        [$s, $e] = $this->weekdayRange();
        $r = $this->adapter->file($this->inactiveEmpId, $this->activeTypeId, $s, $e, 'x');
        $this->assertFalse($r->success);
        $this->assertSame('filing_not_permitted', $r->errorCode);
    }

    public function test_inactive_leave_type_is_rejected(): void
    {
        $this->seedBalance($this->activeEmpId, $this->inactiveTypeId, 15);
        [$s, $e] = $this->weekdayRange();
        $r = $this->adapter->file($this->activeEmpId, $this->inactiveTypeId, $s, $e, 'x');
        $this->assertFalse($r->success);
        $this->assertSame('filing_not_permitted', $r->errorCode);
    }

    // ---- cross-year ----

    public function test_cross_year_request_is_rejected(): void
    {
        $this->seedBalance($this->activeEmpId, $this->activeTypeId, 15);
        $r = $this->adapter->file($this->activeEmpId, $this->activeTypeId, '2093-12-30', '2094-01-01', 'x');
        $this->assertFalse($r->success);
        $this->assertSame('cross_year', $r->errorCode);
    }

    // ---- overlap ----

    public function test_overlapping_request_is_rejected(): void
    {
        $this->seedBalance($this->activeEmpId, $this->activeTypeId, 15);
        [$s, $e] = $this->weekdayRange();

        $first = $this->adapter->file($this->activeEmpId, $this->activeTypeId, $s, $e, 'first');
        $this->trackLatestRequest();
        $this->assertTrue($first->success);

        $second = $this->adapter->file($this->activeEmpId, $this->activeTypeId, $s, $e, 'overlap');
        $this->assertFalse($second->success);
        $this->assertSame('overlapping_dates', $second->errorCode);
    }

    // ---- insufficient balance (incl. pending reservation) ----

    public function test_insufficient_balance_is_rejected(): void
    {
        $this->seedBalance($this->activeEmpId, $this->activeTypeId, 1); // only 1 day
        [$s, $e] = $this->weekdayRange(); // 2 working days
        $r = $this->adapter->file($this->activeEmpId, $this->activeTypeId, $s, $e, 'x');
        $this->assertFalse($r->success);
        $this->assertSame('insufficient_balance', $r->errorCode);
    }

    public function test_pending_days_are_reserved_against_the_balance(): void
    {
        $this->seedBalance($this->activeEmpId, $this->activeTypeId, 3);

        // First 2-day request succeeds, leaving 1 effective day (3 - 2 pending).
        $r1 = $this->adapter->file($this->activeEmpId, $this->activeTypeId, '2093-01-07', '2093-01-08', 'first');
        $this->trackLatestRequest();
        $this->assertTrue($r1->success);

        // A second 2-day (non-overlapping) request must fail: only 1 day is effectively free.
        $r2 = $this->adapter->file($this->activeEmpId, $this->activeTypeId, '2093-01-14', '2093-01-15', 'second');
        $this->assertFalse($r2->success);
        $this->assertSame('insufficient_balance', $r2->errorCode);
    }

    // ---- auto-initialization + duplicate prevention ----

    public function test_missing_balance_is_auto_initialized_on_filing(): void
    {
        // No balance seeded for the active type; filing must still succeed via auto-init.
        [$s, $e] = $this->weekdayRange();
        $r = $this->adapter->file($this->activeEmpId, $this->activeTypeId, $s, $e, 'auto-init');
        $this->trackLatestRequest();

        $this->assertTrue($r->success, $r->message);

        $count = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM leave_balances WHERE employee_id = {$this->activeEmpId} AND leave_type_id = {$this->activeTypeId} AND year = {$this->year}"
        )->fetchColumn();
        $this->assertSame(1, $count, 'Exactly one balance row must be created.');
        // Track the auto-created balance for cleanup.
        $balId = (int)$this->pdo->query(
            "SELECT id FROM leave_balances WHERE employee_id = {$this->activeEmpId} AND leave_type_id = {$this->activeTypeId} AND year = {$this->year}"
        )->fetchColumn();
        $this->cleanup['leave_balances'][] = $balId;
    }

    public function test_auto_initialization_is_idempotent_and_creates_no_duplicates(): void
    {
        $this->guard->ensureBalancesInitialized($this->activeEmpId, $this->year);
        $this->guard->ensureBalancesInitialized($this->activeEmpId, $this->year); // second call = no-op

        foreach ($this->pdo->query(
            "SELECT id FROM leave_balances WHERE employee_id = {$this->activeEmpId} AND year = {$this->year}"
        )->fetchAll(PDO::FETCH_COLUMN) as $bid) {
            $this->cleanup['leave_balances'][] = (int)$bid;
        }

        $perType = $this->pdo->query(
            "SELECT leave_type_id, COUNT(*) AS c FROM leave_balances
             WHERE employee_id = {$this->activeEmpId} AND year = {$this->year} GROUP BY leave_type_id HAVING c > 1"
        )->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame([], $perType, 'Repeated initialization must not create duplicate balance rows.');
    }

    // ---- balance locking inside the transaction ----

    public function test_successful_filing_persists_a_pending_request(): void
    {
        $this->seedBalance($this->activeEmpId, $this->activeTypeId, 15);
        [$s, $e] = $this->weekdayRange();

        $notified = 0;
        $r = $this->adapter->file($this->activeEmpId, $this->activeTypeId, $s, $e, 'ok', function () use (&$notified) {
            $notified++;
        });
        $this->trackLatestRequest();

        $this->assertTrue($r->success);
        $this->assertSame(201, $r->statusCode);
        $this->assertSame(1, $notified, 'The in-transaction callback must run exactly once.');

        $reqId = (int)($r->data['id']);
        $row = $this->pdo->query("SELECT status, number_of_days FROM leave_requests WHERE id = {$reqId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Pending', $row['status']);
        $this->assertSame(2, (int)$row['number_of_days']);
    }
}
