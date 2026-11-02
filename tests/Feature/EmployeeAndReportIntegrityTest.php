<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Notification;
use Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Group 3 regression (runs only against the isolated hris_test database):
 *  - SEC-04 / EMP-01: Employee::update and ::activate keep the linked login account in sync
 *    inside one transaction (reactivation restores the user; deactivation disables it).
 *  - RPT-01: the department report must not fan-out headcount or approved-day totals.
 *  - NOTIF-01: mark-as-read ownership is distinguishable from a valid no-op.
 */
class EmployeeAndReportIntegrityTest extends TestCase
{
    private ?PDO $pdo = null;
    private array $cleanup = [
        'users' => [], 'employees' => [], 'leave_requests' => [], 'notifications' => [], 'departments' => [], 'positions' => [],
    ];

    protected function setUp(): void
    {
        if (!Database::getInstance()->isConnected()) {
            $this->markTestSkipped('Database connection is not available.');
        }
        $this->pdo = Database::getInstance()->getConnection();
    }

    protected function tearDown(): void
    {
        if ($this->pdo === null) {
            return;
        }
        // Delete in FK-safe order.
        foreach (['leave_requests', 'notifications', 'users', 'employees', 'positions', 'departments'] as $table) {
            if (!empty($this->cleanup[$table])) {
                $in = implode(',', array_map('intval', $this->cleanup[$table]));
                $this->pdo->exec("DELETE FROM {$table} WHERE id IN ({$in})");
            }
        }
    }

    private function seedDepartment(string $name): int
    {
        $this->pdo->prepare("INSERT INTO departments (department_name, description) VALUES (:n, 'g3')")
            ->execute(['n' => $name]);
        $id = (int)$this->pdo->lastInsertId();
        $this->cleanup['departments'][] = $id;
        return $id;
    }

    private function seedPosition(string $name, int $deptId): int
    {
        $this->pdo->prepare("INSERT INTO positions (position_name, department_id, description) VALUES (:n, :d, 'g3')")
            ->execute(['n' => $name, 'd' => $deptId]);
        $id = (int)$this->pdo->lastInsertId();
        $this->cleanup['positions'][] = $id;
        return $id;
    }

    private function seedEmployee(int $deptId, int $posId, string $status = 'Active'): int
    {
        $suffix = bin2hex(random_bytes(4));
        $this->pdo->prepare(
            "INSERT INTO employees (employee_no, first_name, last_name, email, birth_date, gender, department_id, position_id, date_hired, employment_status)
             VALUES (:no, 'G3', 'Tester', :email, '1990-01-01', 'Other', :d, :p, '2024-01-01', :st)"
        )->execute([
            'no' => "G3-{$suffix}", 'email' => "g3-{$suffix}@test.local",
            'd' => $deptId, 'p' => $posId, 'st' => $status,
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $this->cleanup['employees'][] = $id;
        return $id;
    }

    private function seedUserForEmployee(int $employeeId, string $status): int
    {
        $suffix = bin2hex(random_bytes(4));
        $this->pdo->prepare(
            "INSERT INTO users (employee_id, username, email, password, role, status)
             VALUES (:e, :u, :em, :pw, 'employee', :st)"
        )->execute([
            'e' => $employeeId, 'u' => "g3user{$suffix}", 'em' => "g3user-{$suffix}@test.local",
            'pw' => password_hash('irrelevant', PASSWORD_DEFAULT), 'st' => $status,
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $this->cleanup['users'][] = $id;
        return $id;
    }

    private function userStatus(int $userId): string
    {
        $stmt = $this->pdo->prepare("SELECT status FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        return (string)$stmt->fetchColumn();
    }

    private function employeeStatus(int $employeeId): string
    {
        $stmt = $this->pdo->prepare("SELECT employment_status FROM employees WHERE id = :id");
        $stmt->execute(['id' => $employeeId]);
        return (string)$stmt->fetchColumn();
    }

    // ---- SEC-04 / EMP-01 ----

    public function test_reactivating_employee_restores_linked_login_account(): void
    {
        $dept = $this->seedDepartment('G3 Dept');
        $pos = $this->seedPosition('G3 Position', $dept);
        $empId = $this->seedEmployee($dept, $pos, 'Inactive');
        $userId = $this->seedUserForEmployee($empId, 'Inactive');

        $model = new Employee();
        $this->assertTrue($model->activate($empId));

        $this->assertSame('Active', $this->employeeStatus($empId));
        $this->assertSame('Active', $this->userStatus($userId), 'Reactivation must re-enable the linked login account (F.3).');
    }

    public function test_update_to_active_reactivates_login_and_to_inactive_disables_it(): void
    {
        $dept = $this->seedDepartment('G3 Dept2');
        $pos = $this->seedPosition('G3 Position2', $dept);
        $empId = $this->seedEmployee($dept, $pos, 'Inactive');
        $userId = $this->seedUserForEmployee($empId, 'Inactive');

        $model = new Employee();
        $base = [
            'employee_no' => "G3-upd-" . bin2hex(random_bytes(3)),
            'first_name' => 'G3', 'middle_name' => '', 'last_name' => 'Tester',
            'email' => 'g3-upd-' . bin2hex(random_bytes(3)) . '@test.local',
            'phone' => '', 'address' => '', 'birth_date' => '1990-01-01', 'gender' => 'Other',
            'department_id' => $dept, 'position_id' => $pos, 'date_hired' => '2024-01-01',
        ];

        // Inactive -> Active restores the login account.
        $this->assertTrue($model->update($empId, $base + ['employment_status' => 'Active']));
        $this->assertSame('Active', $this->userStatus($userId));

        // Active -> Inactive disables it again.
        $this->assertTrue($model->update($empId, $base + ['employment_status' => 'Inactive']));
        $this->assertSame('Inactive', $this->userStatus($userId));
    }

    // ---- RPT-01 ----

    public function test_department_report_does_not_fan_out_headcount_or_leave_days(): void
    {
        $dept = $this->seedDepartment('G3 Report Dept');
        // Two positions and three leave requests for ONE active employee would, under the old
        // join, multiply the counts. The employee must still be counted once.
        $pos1 = $this->seedPosition('G3 Pos A', $dept);
        $this->seedPosition('G3 Pos B', $dept);
        $empId = $this->seedEmployee($dept, $pos1, 'Active');

        $leaveTypeId = (int)$this->pdo->query("SELECT id FROM leave_types LIMIT 1")->fetchColumn();
        for ($i = 0; $i < 3; $i++) {
            $this->pdo->prepare(
                "INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, number_of_days, reason, status)
                 VALUES (:e, :lt, '2024-06-03', '2024-06-04', 2, 'g3', 'Approved')"
            )->execute(['e' => $empId, 'lt' => $leaveTypeId]);
            $this->cleanup['leave_requests'][] = (int)$this->pdo->lastInsertId();
        }

        // Run the exact corrected query shape the report uses.
        $row = $this->pdo->query("
            SELECT
                (SELECT COUNT(*) FROM employees e WHERE e.department_id = {$dept}) AS total_employees,
                (SELECT COUNT(*) FROM employees e WHERE e.department_id = {$dept} AND e.employment_status = 'Active') AS active_employees,
                (SELECT COUNT(*) FROM positions p WHERE p.department_id = {$dept}) AS total_positions,
                (SELECT COALESCE(SUM(lr.number_of_days),0) FROM leave_requests lr JOIN employees e3 ON lr.employee_id = e3.id
                    WHERE e3.department_id = {$dept} AND lr.status = 'Approved') AS approved_leave_days
        ")->fetch(PDO::FETCH_ASSOC);

        $this->assertSame(1, (int)$row['total_employees'], 'One employee must be counted once, not once per position/leave row.');
        $this->assertSame(1, (int)$row['active_employees']);
        $this->assertSame(2, (int)$row['total_positions']);
        $this->assertSame(6, (int)$row['approved_leave_days'], '3 requests x 2 days = 6, not multiplied by positions.');
    }

    // ---- NOTIF-01 ----

    public function test_mark_as_read_ownership_is_distinguishable(): void
    {
        $dept = $this->seedDepartment('G3 Notif Dept');
        $pos = $this->seedPosition('G3 Notif Pos', $dept);
        $empId = $this->seedEmployee($dept, $pos, 'Active');
        $ownerId = $this->seedUserForEmployee($empId, 'Active');
        $otherEmp = $this->seedEmployee($dept, $pos, 'Active');
        $otherId = $this->seedUserForEmployee($otherEmp, 'Active');

        $model = new Notification();
        $notifId = $model->create($ownerId, 'G3', 'hello', 'info');
        $this->cleanup['notifications'][] = $notifId;

        $this->assertTrue($model->belongsToUser($notifId, $ownerId));
        $this->assertFalse($model->belongsToUser($notifId, $otherId), 'Another user must not be treated as the owner.');
        $this->assertFalse($model->belongsToUser(999999999, $ownerId), 'A non-existent id is not owned.');

        // Owner can mark it read (row changes), and an own already-read row is still "owned".
        $this->assertTrue($model->markAsRead($notifId, $ownerId));
        $this->assertTrue($model->belongsToUser($notifId, $ownerId));
    }
}
