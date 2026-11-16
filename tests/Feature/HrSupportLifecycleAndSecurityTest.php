<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\AdminSupportController;
use App\Controllers\HrAdminSupportController;
use App\Models\HrSupportTicket;
use App\Models\Notification;
use App\Models\User;
use App\Services\HrSupportAccessService;
use App\Services\HrSupportNotificationService;
use App\Services\HrSupportWorkflowService;
use App\Services\MailService;
use App\Services\SupportAttachmentService;
use Core\AuthorizationService;
use Core\Database;
use Core\Session;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HrSupportLifecycleAndSecurityTest extends TestCase
{
    private PDO $pdo;
    private HrSupportTicket $ticketModel;
    private HrSupportWorkflowService $workflow;
    private MailService $mailer;
    private string $storageDir = '';

    private int $deptId = 0;
    private int $posId = 0;
    /** @var array<int, int> */
    private array $empIds = [];
    /** @var array<int, int> */
    private array $userIds = [];
    /** @var array<int, int> */
    private array $ticketIds = [];
    /** @var array<int, string> */
    private array $tempFiles = [];
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        Session::destroy();
        Session::start();
        AuthorizationService::invalidateCache();

        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database is unavailable.');
        }
        $this->pdo = $db->getConnection();

        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'hr_support_tickets'");
        if (!$tableCheck || $tableCheck->fetchColumn() === false) {
            $this->markTestSkipped('hr_support_tickets table is not migrated yet.');
        }

        $this->storageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexora_hrsup_test_' . bin2hex(random_bytes(4));
        @mkdir($this->storageDir, 0775, true);

        $this->mailer = new MailService(null, true);
        $this->ticketModel = new HrSupportTicket($this->pdo);
        $attService = new SupportAttachmentService($this->storageDir, static fn (string $p): bool => is_file($p));
        $notifService = new HrSupportNotificationService(
            new Notification($this->pdo),
            new User($this->pdo),
            $this->ticketModel,
            $this->mailer,
            $this->pdo
        );

        $this->workflow = new HrSupportWorkflowService(
            $this->pdo,
            $this->ticketModel,
            new HrSupportAccessService(),
            $attService,
            $notifService
        );

        $tag = bin2hex(random_bytes(3));
        $this->pdo->prepare("INSERT INTO departments (department_name, description) VALUES (:n, 'hrsup_test')")
            ->execute(['n' => 'HR Sup Dept ' . $tag]);
        $this->deptId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO positions (position_name, department_id, description) VALUES (:n, :d, 'hrsup_test')")
            ->execute(['n' => 'HR Sup Pos ' . $tag, 'd' => $this->deptId]);
        $this->posId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        Session::remove('user');
        Session::destroy();
        AuthorizationService::invalidateCache();

        foreach ($this->tempFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }

        if (isset($this->pdo)) {
            if ($this->ticketIds !== []) {
                $in = implode(',', array_map('intval', $this->ticketIds));
                $this->pdo->exec("DELETE FROM notifications WHERE entity_id IN ({$in}) AND event_type LIKE 'hr_support.%'");
                $this->pdo->exec("DELETE FROM hr_support_tickets WHERE id IN ({$in})");
            }
            if ($this->userIds !== []) {
                $uIn = implode(',', array_map('intval', $this->userIds));
                $this->pdo->exec("DELETE FROM activity_logs WHERE user_id IN ({$uIn})");
                $this->pdo->exec("DELETE FROM notifications WHERE user_id IN ({$uIn})");
                $this->pdo->exec("DELETE FROM users WHERE id IN ({$uIn})");
            }
            if ($this->empIds !== []) {
                $eIn = implode(',', array_map('intval', $this->empIds));
                $this->pdo->exec("DELETE FROM employees WHERE id IN ({$eIn})");
            }
            if ($this->posId > 0) {
                $this->pdo->prepare('DELETE FROM positions WHERE id = :id')->execute(['id' => $this->posId]);
            }
            if ($this->deptId > 0) {
                $this->pdo->prepare('DELETE FROM departments WHERE id = :id')->execute(['id' => $this->deptId]);
            }
        }

        if ($this->storageDir !== '' && is_dir($this->storageDir)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->storageDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $entry) {
                $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
            }
            @rmdir($this->storageDir);
        }
    }

    public function test_full_lifecycle_metrics_internal_note_isolation_and_redacted_emails(): void
    {
        $hr1 = $this->createTestUser('hr', 'hr_one');
        $hr2 = $this->createTestUser('hr', 'hr_two');
        $admin1 = $this->createTestUser('admin', 'admin_one');
        $emp1 = $this->createTestUser('employee', 'emp_one');

        // 1. Admin & Employee are strictly forbidden from creating HR support tickets
        foreach ([$admin1, $emp1] as $unauthorized) {
            try {
                $this->workflow->createTicket($unauthorized, [
                    'subject'     => 'Unauthorized ticket attempt',
                    'category'    => 'System Error',
                    'priority'    => 'High',
                    'description' => 'Testing server-side role restriction enforcement.',
                ]);
                $this->fail('Expected 403 for non-HR role ' . $unauthorized['role']);
            } catch (RuntimeException $e) {
                $this->assertSame(403, (int)$e->getCode());
            }
        }

        // 2. HR #1 creates ticket with a valid PNG screenshot
        $pngTmp = $this->createMinimalPng();
        $secretDescription = 'CONFIDENTIAL_ERR_BODY_DO_NOT_EMAIL_PayrollSyncFailedOnBatch99';
        $ticket = $this->workflow->createTicket($hr1, [
            'subject'     => 'Leave approval queue throws timeout',
            'category'    => 'Leave Management Issue',
            'priority'    => 'Critical',
            'description' => $secretDescription,
        ], [
            'name'     => ['error-screen.png'],
            'type'     => ['image/png'],
            'tmp_name' => [$pngTmp],
            'error'    => [UPLOAD_ERR_OK],
            'size'     => [(int)filesize($pngTmp)],
        ]);
        $this->ticketIds[] = (int)$ticket['id'];

        $ticketRef = (string)$ticket['ticket_ref'];
        $this->assertMatchesRegularExpression('/^HR-SUP-\d{4}-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/', $ticketRef);
        $this->assertSame('Critical', $ticket['priority']);
        $this->assertSame('Submitted', $ticket['status']);

        // Verify live Admin Dashboard metrics reflect the Submitted Critical ticket
        $stats = $this->ticketModel->getAdminDashboardStats();
        $this->assertGreaterThanOrEqual(1, $stats['total_open']);
        $this->assertGreaterThanOrEqual(1, $stats['newly_submitted']);
        $this->assertGreaterThanOrEqual(1, $stats['critical_and_high']);

        // Verify email sent to Admin contains ticket_ref + authenticated link, NEVER secretDescription
        $mails = $this->mailer->getSentMails();
        $adminMails = array_values(array_filter($mails, static fn (array $m): bool => ($m['to_address'] ?? '') === $admin1['email']));
        $this->assertNotEmpty($adminMails);
        $this->assertStringContainsString($ticketRef, (string)$adminMails[0]['html_body']);
        $this->assertStringContainsString('/admin/support/tickets/' . $ticketRef, (string)$adminMails[0]['html_body']);
        $this->assertStringNotContainsString($secretDescription, (string)$adminMails[0]['html_body']);
        $this->assertStringNotContainsString($secretDescription, (string)$adminMails[0]['alt_body']);

        // 3. Ownership isolation: HR #2 cannot inspect, reply, confirm, or download HR #1's ticket
        try {
            $this->workflow->getTicketDetailsForHr($hr2, $ticketRef);
            $this->fail('HR #2 should not view HR #1 ticket');
        } catch (RuntimeException $e) {
            $this->assertSame(403, (int)$e->getCode());
        }

        // Non-guessable ref enforcement: numeric ID returns 404
        try {
            $this->workflow->getTicketDetailsForHr($hr1, (string)$ticket['id']);
            $this->fail('Numeric internal database ID should not resolve as ticket_ref');
        } catch (RuntimeException $e) {
            $this->assertSame(404, (int)$e->getCode());
        }

        // 4. Admin cannot assign an HR user as ticket assignee
        try {
            $this->workflow->assignAdmin($admin1, $ticketRef, (int)$hr2['id']);
            $this->fail('Expected rejection assigning non-admin user');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(422, (int)$e->getCode());
        }

        $this->mailer->clearSentMails();
        $assignedTicket = $this->workflow->assignAdmin($admin1, $ticketRef, (int)$admin1['id'], 'Assigned to systems admin');
        $this->assertSame((int)$admin1['id'], (int)$assignedTicket['assigned_admin_id']);
        $this->assertNotEmpty($this->mailer->getSentMails(), 'HR must be notified on assignment change');

        // 5. Admin posts a private internal note + internal PNG attachment
        $secretNote = 'TOP_SECRET_ADMIN_INTERNAL_NOTE_DB_REPLICA_LAG_XYZ789';
        $internalPng = $this->createMinimalPng();
        $this->mailer->clearSentMails();
        $this->workflow->addAdminMessage(
            $admin1,
            $ticketRef,
            $secretNote,
            'internal_note',
            [
                'name'     => ['internal-diag.png'],
                'type'     => ['image/png'],
                'tmp_name' => [$internalPng],
                'error'    => [UPLOAD_ERR_OK],
                'size'     => [(int)filesize($internalPng)],
            ]
        );
        // Internal notes must NEVER notify HR or dispatch emails to HR
        $this->assertSame([], $this->mailer->getSentMails());

        // Verify Admin sees internal note & internal attachment
        $adminBundle = $this->workflow->getTicketDetailsForAdmin($admin1, $ticketRef);
        $adminBodies = implode("\n", array_column($adminBundle['messages'], 'body'));
        $this->assertStringContainsString($secretNote, $adminBodies);

        // Verify HR NEVER receives internal note or internal attachment in backend payload OR JSON controller response
        $hrBundle = $this->workflow->getTicketDetailsForHr($hr1, $ticketRef);
        $hrBodies = implode("\n", array_column($hrBundle['messages'], 'body'));
        $this->assertStringNotContainsString($secretNote, $hrBodies);
        foreach ($hrBundle['attachments'] as $hrAtt) {
            $this->assertSame(0, (int)$hrAtt['is_internal_only']);
            $this->assertNotSame('internal-diag.png', $hrAtt['original_filename']);
        }

        // Verify JSON controller endpoint enforces backend omission even if query tampered
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        Session::set('user', $hr1);
        $hrController = new HrAdminSupportController($this->pdo, $this->workflow, false);
        ob_start();
        $hrController->show($ticketRef);
        $jsonOut = (string)ob_get_clean();
        unset($_SERVER['HTTP_ACCEPT']);
        $this->assertStringNotContainsString($secretNote, $jsonOut);
        $this->assertStringNotContainsString('internal-diag.png', $jsonOut);

        // Verify HR is blocked (403) from downloading the internal-only attachment directly
        $internalAttRows = array_values(array_filter(
            $adminBundle['attachments'],
            static fn (array $a): bool => (int)$a['is_internal_only'] === 1
        ));
        $this->assertNotEmpty($internalAttRows);
        $internalAttId = (int)$internalAttRows[0]['id'];

        try {
            $this->workflow->authorizeAttachmentDownload($hr1, $internalAttId, 'hr');
            $this->fail('HR must receive 403 when attempting to download Admin-only internal attachment');
        } catch (RuntimeException $e) {
            $this->assertSame(403, (int)$e->getCode());
        }

        // Audit logs must record internal note event WITHOUT leaking secretNote text
        $auditText = (string)$this->pdo->query(
            "SELECT CONCAT(COALESCE(description,''), ' ', COALESCE(new_values,'')) FROM activity_logs WHERE entity_id = " . (int)$ticket['id'] . " AND action = 'HR_SUPPORT_INTERNAL_NOTE_ADDED' ORDER BY id DESC LIMIT 1"
        )->fetchColumn();
        $this->assertNotSame('', $auditText);
        $this->assertStringNotContainsString($secretNote, $auditText);

        // 6. Admin requests info -> status Waiting for HR -> HR replies -> advances back to In Progress
        $this->workflow->addAdminMessage($admin1, $ticketRef, 'Please share exact batch ID.', 'info_request');
        $afterReq = $this->ticketModel->findByRef($ticketRef);
        $this->assertSame('Waiting for HR', $afterReq['status']);

        $this->workflow->addHrReply($hr1, $ticketRef, 'Batch ID is PAY-2026-11.');
        $afterReply = $this->ticketModel->findByRef($ticketRef);
        $this->assertSame('In Progress', $afterReply['status']);

        // 7. Admin resolves -> HR reopens -> Admin resolves again -> HR confirms closure -> Closed blocked for HR reopen
        $this->workflow->updateTicketTriage($admin1, $ticketRef, [
            'priority'           => 'High',
            'status'             => 'Resolved',
            'resolution_summary' => 'Increased worker memory limit and reindexed leave queue.',
        ]);
        $resolvedTicket = $this->ticketModel->findByRef($ticketRef);
        $this->assertSame('Resolved', $resolvedTicket['status']);
        $this->assertSame('High', $resolvedTicket['priority']);
        $this->assertNotEmpty($resolvedTicket['resolved_at']);

        // HR reopens within 14-day window
        $reopenedTicket = $this->workflow->requestHrReopen($hr1, $ticketRef, 'Still observing one intermittent timeout on exports.');
        $this->assertSame('Under Review', $reopenedTicket['status']);
        $this->assertSame(1, (int)$reopenedTicket['reopen_count']);

        // Admin marks Resolved again, HR confirms resolution -> Closed
        $this->workflow->updateTicketTriage($admin1, $ticketRef, ['status' => 'Resolved']);
        $closedTicket = $this->workflow->confirmHrResolution($hr1, $ticketRef, 'Verified exports succeed consistently.');
        $this->assertSame('Closed', $closedTicket['status']);
        $this->assertNotEmpty($closedTicket['closed_at']);

        // Closed ticket cannot be reopened by HR directly (409)
        try {
            $this->workflow->requestHrReopen($hr1, $ticketRef, 'Trying to reopen closed ticket directly.');
            $this->fail('Expected 409 when HR attempts to reopen a Closed ticket');
        } catch (RuntimeException $e) {
            $this->assertSame(409, (int)$e->getCode());
        }

        // Only Admin may reopen a Closed ticket
        $adminReopened = $this->workflow->updateTicketTriage($admin1, $ticketRef, [
            'status'  => 'In Progress',
            'remarks' => 'Admin reopening for regression verification.',
        ]);
        $this->assertSame('In Progress', $adminReopened['status']);
        $this->assertSame(2, (int)$adminReopened['reopen_count']);

        // 8. Admin Inbox search/filtering & expired 14-day HR reopen rejection
        $inboxFiltered = $this->ticketModel->findForAdminInbox([
            'search'            => $ticketRef,
            'status'            => 'In Progress',
            'priority'          => 'High',
            'category'          => 'Leave Management Issue',
            'assigned_admin_id' => (string)$admin1['id'],
        ]);
        $this->assertCount(1, $inboxFiltered);
        $this->assertSame($ticketRef, $inboxFiltered[0]['ticket_ref']);

        $this->workflow->updateTicketTriage($admin1, $ticketRef, ['status' => 'Resolved']);
        $this->pdo->prepare('UPDATE hr_support_tickets SET resolved_at = DATE_SUB(NOW(), INTERVAL 20 DAY) WHERE id = :id')
            ->execute(['id' => (int)$ticket['id']]);
        try {
            $this->workflow->requestHrReopen($hr1, $ticketRef, 'Trying to reopen expired ticket after 20 days.');
            $this->fail('Expected 409 when reopen window expired');
        } catch (RuntimeException $e) {
            $this->assertSame(409, (int)$e->getCode());
        }
    }

    public function test_rate_limit_and_admin_controller_json_inbox_and_least_privilege_separation(): void
    {
        // Verify migration SQL is 100% idempotent when re-executed on an already-migrated schema (CI compatibility)
        $migrationSql = (string)file_get_contents(
            dirname(__DIR__, 2) . '/database/migrations/2026_11_20_create_hr_to_admin_support_module.sql'
        );
        \Core\MigrationRunner::executeSql($this->pdo, $migrationSql);

        $hr1 = $this->createTestUser('hr', 'hr_rl');
        $admin1 = $this->createTestUser('admin', 'adm_rl');

        $t = $this->workflow->createTicket($hr1, [
            'subject'     => 'Seeded ticket for filter & controller verification',
            'category'    => 'Dashboard or UI Issue',
            'priority'    => 'Medium',
            'description' => 'Verifying controller JSON search & role gating boundaries.',
        ]);
        $this->ticketIds[] = (int)$t['id'];

        // AdminSupportController JSON inbox returns KPIs & created ticket
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        Session::set('user', $admin1);
        $adminCtrl = new AdminSupportController($this->pdo, $this->workflow, false);
        ob_start();
        $adminCtrl->index();
        $adminJson = json_decode((string)ob_get_clean(), true);
        $this->assertIsArray($adminJson);
        $this->assertSame('success', $adminJson['status']);
        $this->assertArrayHasKey('total_open', $adminJson['stats']);

        // HR cannot access AdminSupportController::index (403)
        Session::set('user', $hr1);
        $adminCtrlForbidden = new AdminSupportController($this->pdo, $this->workflow, false);
        ob_start();
        $adminCtrlForbidden->index();
        $forbiddenJson = json_decode((string)ob_get_clean(), true);
        unset($_SERVER['HTTP_ACCEPT']);
        $this->assertSame('error', $forbiddenJson['status']);

        // Simulate 10 created events within rate window -> 11th must trigger 429
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_logs (event_id, user_id, actor_role, action, category, description, status, ip_address, created_at)
            VALUES (:eid, :uid, 'hr', 'HR_SUPPORT_TICKET_CREATED', 'support', 'Rate test', 'SUCCESS', '127.0.0.1', NOW())
        ");
        for ($i = 0; $i < 10; $i++) {
            $stmt->execute([
                'eid' => 'evt_rl_' . bin2hex(random_bytes(5)),
                'uid' => (int)$hr1['id'],
            ]);
        }

        try {
            $this->workflow->createTicket($hr1, [
                'subject'     => 'Exceeded quota submission',
                'category'    => 'Other',
                'priority'    => 'Low',
                'description' => 'Should be throttled by DB rolling rate limit.',
            ]);
            $this->fail('Expected 429 rate-limit exception');
        } catch (RuntimeException $e) {
            $this->assertSame(429, (int)$e->getCode());
        }
    }

    public function test_malicious_attachments_and_notification_failure_resilience(): void
    {
        $hr1 = $this->createTestUser('hr', 'hr_resil');
        $this->createTestUser('admin', 'admin_resil');

        // 1. Disguised PHP script upload is rejected and DB ticket is NOT created
        $phpTmp = tempnam(sys_get_temp_dir(), 'evil_');
        file_put_contents($phpTmp, '<?php echo "shell"; ?>');
        $this->tempFiles[] = $phpTmp;

        $beforeCount = (int)$this->pdo->query('SELECT COUNT(*) FROM hr_support_tickets')->fetchColumn();
        try {
            $this->workflow->createTicket($hr1, [
                'subject'     => 'Valid subject for malicious upload check',
                'category'    => 'System Error',
                'priority'    => 'Medium',
                'description' => 'Attempting to attach an executable php payload.',
            ], [
                'name'     => ['shell.php.png'],
                'type'     => ['image/png'],
                'tmp_name' => [$phpTmp],
                'error'    => [UPLOAD_ERR_OK],
                'size'     => [(int)filesize($phpTmp)],
            ]);
            $this->fail('Expected malicious attachment rejection');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('prohibited', strtolower($e->getMessage()));
        }
        $afterCount = (int)$this->pdo->query('SELECT COUNT(*) FROM hr_support_tickets')->fetchColumn();
        $this->assertSame($beforeCount, $afterCount);

        // 2. Notification / SMTP exception MUST NOT roll back a committed ticket transaction
        $throwingMailer = new class extends MailService {
            public function sendMail(string $toAddress, string $toName, string $subject, string $htmlBody, ?string $altBody = null): bool
            {
                throw new RuntimeException('Simulated SMTP outage');
            }
        };

        $attService = new SupportAttachmentService($this->storageDir, static fn (string $p): bool => is_file($p));
        $resilientWorkflow = new HrSupportWorkflowService(
            $this->pdo,
            $this->ticketModel,
            new HrSupportAccessService(),
            $attService,
            new HrSupportNotificationService(
                new Notification($this->pdo),
                new User($this->pdo),
                $this->ticketModel,
                $throwingMailer,
                $this->pdo
            )
        );

        $created = $resilientWorkflow->createTicket($hr1, [
            'subject'     => 'Ticket survives SMTP outage',
            'category'    => 'Notification or Email Issue',
            'priority'    => 'High',
            'description' => 'SMTP transport is unavailable right now but this ticket must still commit cleanly.',
        ]);
        $this->ticketIds[] = (int)$created['id'];

        $persisted = $this->ticketModel->findByRef((string)$created['ticket_ref']);
        $this->assertNotNull($persisted);
        $this->assertSame('Submitted', $persisted['status']);
    }

    /**
     * @return array<string, mixed>
     */
    private function createTestUser(string $role, string $prefix): array
    {
        $suffix = bin2hex(random_bytes(3));
        $empId = null;

        if ($role !== 'admin') {
            $this->pdo->prepare("
                INSERT INTO employees (employee_no, first_name, last_name, email, birth_date, gender, department_id, position_id, employment_status, date_hired)
                VALUES (:no, :fn, 'Tester', :em, '1994-05-10', 'Other', :dept, :pos, 'Active', CURDATE())
            ")->execute([
                'no'   => 'EMP-' . strtoupper($suffix),
                'fn'   => ucfirst($prefix),
                'em'   => "{$prefix}.{$suffix}@nexora.local",
                'dept' => $this->deptId,
                'pos'  => $this->posId,
            ]);
            $empId = (int)$this->pdo->lastInsertId();
            $this->empIds[] = $empId;
        }

        $roleIdStmt = $this->pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $roleIdStmt->execute(['slug' => $role]);
        $roleId = $roleIdStmt->fetchColumn();
        $roleId = $roleId !== false ? (int)$roleId : null;

        $email = "{$prefix}.{$suffix}@nexora.local";
        $username = "{$prefix}_{$suffix}";
        $this->pdo->prepare("
            INSERT INTO users (employee_id, username, email, password, role, role_id, status)
            VALUES (:eid, :un, :em, :pw, :role, :rid, 'Active')
        ")->execute([
            'eid'  => $empId,
            'un'   => $username,
            'em'   => $email,
            'pw'   => password_hash('TestPass123!', PASSWORD_BCRYPT),
            'role' => $role,
            'rid'  => $roleId,
        ]);
        $userId = (int)$this->pdo->lastInsertId();
        $this->userIds[] = $userId;

        return [
            'id'          => $userId,
            'employee_id' => $empId,
            'username'    => $username,
            'email'       => $email,
            'role'        => $role,
            'role_id'     => $roleId,
            'status'      => 'Active',
        ];
    }

    private function createMinimalPng(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'png_');
        // Genuine 1x1 transparent PNG binary (works without ext-gd loaded in PHP CLI)
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
        file_put_contents($tmp, $pngBytes !== false ? $pngBytes : '');
        $this->tempFiles[] = $tmp;
        return $tmp;
    }
}
