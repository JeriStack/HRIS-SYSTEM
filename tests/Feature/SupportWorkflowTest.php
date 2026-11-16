<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\SupportTicketHandler;
use App\Services\SupportAccessService;
use App\Services\SupportAttachmentService;
use App\Services\SupportWorkflowService;
use Core\Database;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SupportWorkflowTest extends TestCase
{
    private PDO $pdo;
    private SupportWorkflowService $workflow;
    private SupportTicket $ticketModel;

    private int $deptId = 0;
    private int $posId = 0;

    /** @var array<int, int> */
    private array $empIds = [];
    /** @var array<int, int> */
    private array $userIds = [];
    /** @var array<int, int> */
    private array $ticketIds = [];

    protected function setUp(): void
    {
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database is unavailable.');
        }
        $this->pdo = $db->getConnection();

        $storageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexora_wf_store_' . bin2hex(random_bytes(3));
        @mkdir($storageDir, 0775, true);
        $attService = new SupportAttachmentService($storageDir, static fn (string $p): bool => is_file($p));
        $this->ticketModel = new SupportTicket($this->pdo);
        $this->workflow = new SupportWorkflowService(
            $this->pdo,
            $this->ticketModel,
            new SupportTicketHandler($this->pdo),
            new SupportAccessService(),
            $attService
        );

        $tag = bin2hex(random_bytes(3));
        $this->pdo->prepare("INSERT INTO departments (department_name, description) VALUES (:n, 'sup_wf_test')")
            ->execute(['n' => 'Sup WF Dept ' . $tag]);
        $this->deptId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO positions (position_name, department_id, description) VALUES (:n, :d, 'sup_wf_test')")
            ->execute(['n' => 'Sup WF Pos ' . $tag, 'd' => $this->deptId]);
        $this->posId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }

        if ($this->ticketIds !== []) {
            $in = implode(',', array_map('intval', $this->ticketIds));
            $this->pdo->exec("DELETE FROM support_sensitive_access_logs WHERE ticket_id IN ({$in})");
            $this->pdo->exec("DELETE FROM notifications WHERE entity_id IN ({$in}) AND (event_type LIKE 'support_%')");
            $this->pdo->exec("DELETE FROM support_tickets WHERE id IN ({$in})");
        }

        if ($this->userIds !== []) {
            $uIn = implode(',', array_map('intval', $this->userIds));
            $this->pdo->exec("DELETE FROM support_ticket_handlers WHERE user_id IN ({$uIn})");
            $this->pdo->exec("DELETE FROM activity_logs WHERE user_id IN ({$uIn})");
            $this->pdo->exec("DELETE FROM notifications WHERE user_id IN ({$uIn})");
            $this->pdo->exec("DELETE FROM users WHERE id IN ({$uIn})");
        }

        if ($this->empIds !== []) {
            $eIn = implode(',', array_map('intval', $this->empIds));
            $this->pdo->exec("DELETE FROM employees WHERE id IN ({$eIn})");
        }

        if ($this->posId > 0) {
            $this->pdo->prepare("DELETE FROM positions WHERE id = :id")->execute(['id' => $this->posId]);
        }
        if ($this->deptId > 0) {
            $this->pdo->prepare("DELETE FROM departments WHERE id = :id")->execute(['id' => $this->deptId]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function createTestUser(string $role): array
    {
        $suffix = bin2hex(random_bytes(4));
        $empNo = 'TSUP-' . strtoupper($suffix);
        $email = "sup_{$suffix}@example.test";

        $this->pdo->prepare(
            "INSERT INTO employees (employee_no, first_name, last_name, email, birth_date, gender, department_id, position_id, date_hired, employment_status)
             VALUES (:no, :fn, 'Tester', :em, '1994-05-10', 'Other', :dept, :pos, '2024-01-15', 'Active')"
        )->execute([
            'no'   => $empNo,
            'fn'   => ucfirst($role),
            'em'   => $email,
            'dept' => $this->deptId,
            'pos'  => $this->posId,
        ]);
        $empId = (int)$this->pdo->lastInsertId();
        $this->empIds[] = $empId;

        $roleStmt = $this->pdo->prepare("SELECT id FROM roles WHERE slug = :slug LIMIT 1");
        $roleStmt->execute(['slug' => $role]);
        $roleId = $roleStmt->fetchColumn() ?: null;

        $username = 'sup_' . $role . '_' . $suffix;
        $hash = password_hash('TestPass123!', PASSWORD_BCRYPT);
        $this->pdo->prepare(
            "INSERT INTO users (employee_id, username, email, password, role, role_id, status)
             VALUES (:eid, :un, :em, :pw, :role, :rid, 'Active')"
        )->execute([
            'eid'  => $empId,
            'un'   => $username,
            'em'   => $email,
            'pw'   => $hash,
            'role' => $role,
            'rid'  => $roleId,
        ]);
        $userId = (int)$this->pdo->lastInsertId();
        $this->userIds[] = $userId;

        return [
            'id'          => $userId,
            'employee_id' => $empId,
            'username'    => $username,
            'role'        => $role,
            'role_id'     => $roleId,
            'status'      => 'Active',
        ];
    }

    public function test_ticket_reference_generation_is_unique_and_rejects_employee_spoofing(): void
    {
        $empA = $this->createTestUser('employee');
        $empB = $this->createTestUser('employee');

        $t1 = $this->workflow->createSubmission($empA, [
            'category'    => 'Technical Issue',
            'priority'    => 'High',
            'subject'     => 'Cannot export my leave balance sheet',
            'description' => 'When opening Leave Balances in dark mode, the CSV export button throws an error.',
        ]);
        $this->ticketIds[] = (int)$t1['id'];

        $t2 = $this->workflow->createSubmission($empA, [
            'category'    => 'General Inquiry',
            'subject'     => 'Question regarding company shuttle hours',
            'description' => 'Could HR confirm the evening shuttle pickup times for Building B staff?',
        ]);
        $this->ticketIds[] = (int)$t2['id'];

        $this->assertMatchesRegularExpression('/^SUP-\d{4}-\d{5}$/', (string)$t1['ticket_ref']);
        $this->assertMatchesRegularExpression('/^SUP-\d{4}-\d{5}$/', (string)$t2['ticket_ref']);
        $this->assertNotSame($t1['ticket_ref'], $t2['ticket_ref']);
        $this->assertSame((int)$empA['employee_id'], (int)$t1['employee_id']);
        $this->assertSame('Submitted', $t1['status']);

        // Ownership isolation: Emp B cannot list Emp A's tickets
        $empBList = $this->ticketModel->findForEmployeeList((int)$empB['employee_id']);
        $this->assertSame([], $empBList);

        // Client cannot spoof employee_id in payload
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(403);
        $this->workflow->createSubmission($empA, [
            'employee_id' => $empB['employee_id'],
            'category'    => 'General Inquiry',
            'subject'     => 'Spoofed request attempt',
            'description' => 'Attempting to create a ticket for another employee record.',
        ]);
    }

    public function test_full_support_lifecycle_notifications_and_reopen_close_guards(): void
    {
        $emp = $this->createTestUser('employee');
        $hr = $this->createTestUser('hr');

        $ticket = $this->workflow->createSubmission($emp, [
            'category'    => 'Leave Concern',
            'subject'     => 'SIL credit calculation inquiry for 2026',
            'description' => 'Please help verify why my Service Incentive Leave reflects Vacation Leave satisfaction.',
        ]);
        $ticketId = (int)$ticket['id'];
        $this->ticketIds[] = $ticketId;

        // Employee cannot confirm closure before ticket is Resolved
        try {
            $this->workflow->employeeConfirmClose($emp, $ticketId, 'Try early close');
            $this->fail('Expected early closure rejection');
        } catch (RuntimeException $e) {
            $this->assertSame(403, $e->getCode());
        }

        // Assign handler -> Submitted moves to Under Review and notifies handler
        $afterAssign = $this->workflow->assignHandler($hr, $ticketId, (int)$hr['id'], 'Assigned to leave specialist');
        $this->assertSame('Under Review', $afterAssign['status']);
        $this->assertSame((int)$hr['id'], (int)$afterAssign['assigned_to_user_id']);

        // HR requests additional info -> moves to Waiting for Employee
        $afterInfoReq = $this->workflow->hrRespond($hr, $ticketId, [
            'response_mode' => 'request_info',
            'body'          => 'Please specify your employment start date and screenshot if available.',
        ]);
        $this->assertSame('Waiting for Employee', $afterInfoReq['status']);

        // Employee replies -> moves back to Under Review
        $afterReply = $this->workflow->employeeReply($emp, $ticketId, 'Hired Jan 15, 2024; screenshot attached separately.');
        $this->assertSame('Under Review', $afterReply['status']);

        // HR resolves with remarks
        $afterResolve = $this->workflow->updateStatusAndPriority($hr, $ticketId, [
            'status'             => 'Resolved',
            'priority'           => 'Medium',
            'resolution_remarks' => 'Verified Article 95 compliance: 15 Vacation Leave days satisfy the 5-day SIL minimum.',
        ]);
        $this->assertSame('Resolved', $afterResolve['status']);

        // Employee reopens Resolved ticket within 14-day policy window
        $afterReopen = $this->workflow->employeeReopen($emp, $ticketId, 'One more clarification needed about unused carryover.');
        $this->assertSame('Under Review', $afterReopen['status']);

        // HR resolves again, then test expired reopen window fails
        $this->workflow->updateStatusAndPriority($hr, $ticketId, [
            'status'             => 'Resolved',
            'priority'           => 'Medium',
            'resolution_remarks' => 'Carryover breakdown posted in Leave Balances.',
        ]);

        // Simulate 20 days old resolution -> reopen must be rejected
        $this->pdo->prepare("UPDATE support_tickets SET resolved_at = DATE_SUB(NOW(), INTERVAL 20 DAY) WHERE id = :id")
            ->execute(['id' => $ticketId]);

        try {
            $this->workflow->employeeReopen($emp, $ticketId, 'Too late to reopen');
            $this->fail('Expected expired reopen window rejection');
        } catch (RuntimeException $e) {
            $this->assertSame(403, $e->getCode());
        }

        // Employee confirms closure on Resolved ticket -> Closed
        $afterClose = $this->workflow->employeeConfirmClose($emp, $ticketId, 'Thank you, explanation is clear.');
        $this->assertSame('Closed', $afterClose['status']);
        $this->assertNotEmpty($afterClose['closed_at']);

        // History tracks every transition chronologically
        $history = $this->ticketModel->getStatusHistory($ticketId);
        $statuses = array_column($history, 'to_status');
        $this->assertSame(
            ['Submitted', 'Under Review', 'Waiting for Employee', 'Under Review', 'Resolved', 'Under Review', 'Resolved', 'Closed'],
            $statuses
        );

        // Employee received meaningful notifications across lifecycle
        $notifStmt = $this->pdo->prepare("SELECT event_type FROM notifications WHERE user_id = :uid AND entity_id = :eid");
        $notifStmt->execute(['uid' => $emp['id'], 'eid' => $ticketId]);
        $events = $notifStmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('support_ticket.submitted', $events);
        $this->assertContains('support_ticket.info_requested', $events);
        $this->assertContains('support_ticket.status_changed', $events);
    }

    public function test_missing_complaint_category_or_acknowledgment_fails_validation(): void
    {
        $emp = $this->createTestUser('employee');

        $this->expectException(InvalidArgumentException::class);
        $this->workflow->createSubmission($emp, [
            'request_type'       => 'complaint',
            'complaint_category' => 'Workplace Conduct',
            'subject'            => 'Conduct concern without acknowledgment',
            'description'        => 'Submitting complaint without checking required disclosure checkbox.',
        ]);
    }
}
