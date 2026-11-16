<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\SupportTicketHandler;
use App\Realtime\EventAuthorizer;
use App\Realtime\Identity;
use App\Services\SupportAccessService;
use App\Services\SupportAttachmentService;
use App\Services\SupportWorkflowService;
use Core\Database;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

class SupportConfidentialitySecurityTest extends TestCase
{
    private PDO $pdo;
    private SupportWorkflowService $workflow;
    private SupportTicket $ticketModel;
    private SupportTicketHandler $handlerModel;
    private SupportAccessService $access;

    private int $deptId = 0;
    private int $posId = 0;

    /** @var array<int, int> */
    private array $empIds = [];
    /** @var array<int, int> */
    private array $userIds = [];
    /** @var array<int, int> */
    private array $ticketIds = [];
    /** @var array<int, string> */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database is unavailable.');
        }
        $this->pdo = $db->getConnection();

        $storageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexora_conf_store_' . bin2hex(random_bytes(3));
        $uploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexora_conf_up_' . bin2hex(random_bytes(3));
        @mkdir($storageDir, 0775, true);
        @mkdir($uploadDir, 0775, true);
        $this->tempDirs = [$storageDir, $uploadDir];

        $this->ticketModel = new SupportTicket($this->pdo);
        $this->handlerModel = new SupportTicketHandler($this->pdo);
        $this->access = new SupportAccessService();
        $attService = new SupportAttachmentService($storageDir, static fn (string $p): bool => is_file($p));
        $this->workflow = new SupportWorkflowService(
            $this->pdo,
            $this->ticketModel,
            $this->handlerModel,
            $this->access,
            $attService
        );

        $tag = bin2hex(random_bytes(3));
        $this->pdo->prepare("INSERT INTO departments (department_name, description) VALUES (:n, 'sup_sec_test')")
            ->execute(['n' => 'Sup Sec Dept ' . $tag]);
        $this->deptId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO positions (position_name, department_id, description) VALUES (:n, :d, 'sup_sec_test')")
            ->execute(['n' => 'Sup Sec Pos ' . $tag, 'd' => $this->deptId]);
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

        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * @return array<string, mixed>
     */
    private function createUser(string $role, bool $designateConfidential = false): array
    {
        $suffix = bin2hex(random_bytes(4));
        $empNo = 'TSEC-' . strtoupper($suffix);
        $email = "sec_{$suffix}@example.test";

        $this->pdo->prepare(
            "INSERT INTO employees (employee_no, first_name, last_name, email, birth_date, gender, department_id, position_id, date_hired, employment_status)
             VALUES (:no, :fn, 'Security', :em, '1991-03-20', 'Female', :dept, :pos, '2023-06-01', 'Active')"
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

        $username = 'sec_' . $role . '_' . $suffix;
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

        if ($designateConfidential) {
            $this->handlerModel->upsertHandlerDesignation($userId, true, 'Test designated handler', $userId);
        }

        return [
            'id'          => $userId,
            'employee_id' => $empId,
            'username'    => $username,
            'role'        => $role,
            'role_id'     => $roleId,
            'status'      => 'Active',
        ];
    }

    public function test_confidential_complaints_are_isolated_from_non_designated_hr_and_public_notes(): void
    {
        $emp = $this->createUser('employee');
        $hrAuthorized = $this->createUser('hr', true);
        $hrStandard = $this->createUser('hr', false);
        $admin = $this->createUser('admin', false);

        // Attach confidential evidence PDF on creation
        $tmpPdf = $this->tempDirs[1] . DIRECTORY_SEPARATOR . 'complaint_evidence.pdf';
        file_put_contents($tmpPdf, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");

        $secretSubject = 'SECRET_SUBJECT_HARASSMENT_771';
        $secretNarrative = 'SECRET_NARRATIVE_HOSTILE_INCIDENT_882 occurred at Building C';

        $complaint = $this->workflow->createSubmission($emp, [
            'request_type'            => 'complaint',
            'complaint_category'      => 'Discrimination or Harassment',
            'incident_date'           => date('Y-m-d', strtotime('-2 days')),
            'priority'                => 'Urgent',
            'subject'                 => $secretSubject,
            'description'             => $secretNarrative,
            'identity_acknowledgment' => '1',
        ], [
            'name'     => ['evidence.pdf'],
            'tmp_name' => [$tmpPdf],
            'error'    => [UPLOAD_ERR_OK],
            'size'     => [filesize($tmpPdf)],
        ]);
        $ticketId = (int)$complaint['id'];
        $this->ticketIds[] = $ticketId;

        $attachments = $this->ticketModel->getAttachments($ticketId, true);
        $this->assertCount(1, $attachments);
        $attWithTicket = $this->ticketModel->findAttachmentWithTicket((int)$attachments[0]['id']);
        $this->assertNotNull($attWithTicket);

        // 1. Designated HR can access inbox row, confidential counter, ticket detail, and attachment
        $authCtx = $this->access->buildViewerContext($hrAuthorized, $this->handlerModel, $this->pdo);
        $authInboxIds = array_column($this->ticketModel->findForHrInbox($authCtx), 'id');
        $authStats = $this->ticketModel->getHrOverviewStats($authCtx);
        $this->assertContains($ticketId, array_map('intval', $authInboxIds));
        $this->assertTrue($authStats['show_confidential_metric']);
        $this->assertGreaterThanOrEqual(1, $authStats['confidential_attention']);
        $this->assertTrue($this->access->canViewTicketAsHr($hrAuthorized, $complaint, $this->handlerModel, $this->pdo));
        $this->assertTrue($this->access->canDownloadAttachment($hrAuthorized, $attWithTicket, $this->handlerModel, $this->pdo, 'hr'));

        // 2. Non-designated HR (same 'hr' role!) MUST be completely blocked
        $stdCtx = $this->access->buildViewerContext($hrStandard, $this->handlerModel, $this->pdo);
        $stdInboxIds = array_column($this->ticketModel->findForHrInbox($stdCtx), 'id');
        $stdStats = $this->ticketModel->getHrOverviewStats($stdCtx);
        $this->assertNotContains($ticketId, array_map('intval', $stdInboxIds));
        $this->assertFalse($stdStats['show_confidential_metric']);
        $this->assertSame(0, $stdStats['confidential_attention']);
        $this->assertFalse($this->access->canViewTicketAsHr($hrStandard, $complaint, $this->handlerModel, $this->pdo));
        $this->assertFalse($this->access->canDownloadAttachment($hrStandard, $attWithTicket, $this->handlerModel, $this->pdo, 'hr'));

        // Non-designated HR received ZERO notifications about the complaint
        $stdNotifCount = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM notifications WHERE user_id = " . (int)$hrStandard['id'] . " AND entity_id = {$ticketId}"
        )->fetchColumn();
        $this->assertSame(0, $stdNotifCount);

        // Assigning confidential complaint to non-designated HR must fail
        try {
            $this->workflow->assignHandler($hrAuthorized, $ticketId, (int)$hrStandard['id'], 'Should reject');
            $this->fail('Expected non-designated handler assignment rejection');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('confidential', strtolower($e->getMessage()));
        }

        // 3. Internal HR note & internal-only attachment never leak to employee
        $internalFile = $this->tempDirs[1] . DIRECTORY_SEPARATOR . 'internal_memo.txt';
        file_put_contents($internalFile, 'Internal investigator notes for confidential case.');
        $this->workflow->hrRespond($hrAuthorized, $ticketId, [
            'response_mode' => 'internal_note',
            'body'          => 'PRIVATE_HR_MEMO_XYZ: interview scheduled with witness.',
        ], [
            'name'     => ['internal_memo.txt'],
            'tmp_name' => [$internalFile],
            'error'    => [UPLOAD_ERR_OK],
            'size'     => [filesize($internalFile)],
        ]);

        $employeeMessages = $this->ticketModel->getMessages($ticketId, false);
        $hrMessages = $this->ticketModel->getMessages($ticketId, true);
        $this->assertCount(1, $employeeMessages);
        $this->assertCount(2, $hrMessages);
        $this->assertStringNotContainsString('PRIVATE_HR_MEMO_XYZ', json_encode($employeeMessages) ?: '');

        $allAtts = $this->ticketModel->getAttachments($ticketId, true);
        $empAtts = $this->ticketModel->getAttachments($ticketId, false);
        $this->assertCount(2, $allAtts);
        $this->assertCount(1, $empAtts);
        $internalAttRow = $this->ticketModel->findAttachmentWithTicket((int)$allAtts[1]['id']);
        $this->assertNotNull($internalAttRow);
        $this->assertFalse($this->access->canDownloadAttachment($emp, $internalAttRow, $this->handlerModel, $this->pdo, 'employee'));

        // 4. Privacy guarantees across notifications, activity_logs, and dashboard preview
        $allNotifs = $this->pdo->query("SELECT title, message FROM notifications WHERE entity_id = {$ticketId}")
            ->fetchAll(PDO::FETCH_ASSOC);
        $notifBlob = json_encode($allNotifs) ?: '';
        $this->assertStringNotContainsString($secretSubject, $notifBlob);
        $this->assertStringNotContainsString($secretNarrative, $notifBlob);

        $auditStmt = $this->pdo->prepare("SELECT description, new_values FROM activity_logs WHERE entity_type = 'support_tickets' AND entity_id = :eid");
        $auditStmt->execute(['eid' => $ticketId]);
        $auditBlob = json_encode($auditStmt->fetchAll(PDO::FETCH_ASSOC)) ?: '';
        $this->assertStringNotContainsString($secretSubject, $auditBlob);
        $this->assertStringNotContainsString($secretNarrative, $auditBlob);

        $maskedDash = $this->access->maskForDashboard($complaint);
        $this->assertSame('Confidential Workplace Concern', $maskedDash['display_subject']);
        $this->assertArrayNotHasKey('description', $maskedDash);

        // 5. Governance grant/revoke works dynamically under Admin support.manage_handlers
        $this->workflow->updateHandlerDesignation($admin, (int)$hrStandard['id'], true, 'Temporary panel member');
        $this->assertTrue($this->access->canViewTicketAsHr($hrStandard, $complaint, $this->handlerModel, $this->pdo));
        $this->workflow->updateHandlerDesignation($admin, (int)$hrStandard['id'], false, 'Review complete');
        $this->assertFalse($this->access->canViewTicketAsHr($hrStandard, $complaint, $this->handlerModel, $this->pdo));
    }

    public function test_conflict_of_interest_blocks_subject_across_all_roles_and_triggers_escalation(): void
    {
        $emp = $this->createUser('employee');
        $conflictedHr = $this->createUser('hr', true);
        $conflictedAdmin = $this->createUser('admin', false);
        $unconflictedAdmin = $this->createUser('admin', false);

        // Temporarily deactivate other seeded HR handlers (e.g. hr01) during this isolated assertion and restore in finally
        $existingActiveHrIds = $this->pdo->query(
            "SELECT sth.user_id FROM support_ticket_handlers sth INNER JOIN users u ON sth.user_id = u.id
             WHERE u.role = 'hr' AND sth.is_active = 1 AND sth.user_id != " . (int)$conflictedHr['id']
        )->fetchAll(PDO::FETCH_COLUMN);

        if ($existingActiveHrIds !== []) {
            $idList = implode(',', array_map('intval', $existingActiveHrIds));
            $this->pdo->exec("UPDATE support_ticket_handlers SET is_active = 0 WHERE user_id IN ({$idList})");
        }

        try {
            // Cannot submit complaint naming yourself
            try {
                $this->workflow->createSubmission($emp, [
                    'request_type'            => 'complaint',
                    'complaint_category'      => 'Workplace Conduct',
                    'subject_employee_id'     => $emp['employee_id'],
                    'subject'                 => 'Self-subject test rejection',
                    'description'             => 'Attempting to name submitter employee as complaint subject.',
                    'identity_acknowledgment' => '1',
                ]);
                $this->fail('Expected self-subject COI rejection');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('yourself', strtolower($e->getMessage()));
            }

            // Submit complaint naming the ONLY active designated HR handler -> must auto-escalate!
            $complaint = $this->workflow->createSubmission($emp, [
                'request_type'            => 'complaint',
                'complaint_category'      => 'Unprofessional Behavior',
                'subject_employee_id'     => $conflictedHr['employee_id'],
                'priority'                => 'Urgent',
                'subject'                 => 'COI_ESCALATION_TEST_SUBJECT',
                'description'             => 'Formal complaint involving the designated HR handler requiring independent oversight.',
                'identity_acknowledgment' => '1',
            ]);
            $ticketId = (int)$complaint['id'];
            $this->ticketIds[] = $ticketId;

            $this->assertSame(1, (int)$complaint['escalated_flag']);
            $this->assertStringContainsString('No non-conflicted authorized HR', (string)$complaint['escalation_reason']);

            // Conflicted HR handler is strictly excluded from inbox, detail, stats, and notifications
            $coiCtx = $this->access->buildViewerContext($conflictedHr, $this->handlerModel, $this->pdo);
            $coiInbox = array_column($this->ticketModel->findForHrInbox($coiCtx), 'id');
            $this->assertNotContains($ticketId, array_map('intval', $coiInbox));
            $this->assertFalse($this->access->canViewTicketAsHr($conflictedHr, $complaint, $this->handlerModel, $this->pdo));
            $coiNotifs = (int)$this->pdo->query(
                "SELECT COUNT(*) FROM notifications WHERE user_id = " . (int)$conflictedHr['id'] . " AND entity_id = {$ticketId}"
            )->fetchColumn();
            $this->assertSame(0, $coiNotifs);

            // Real-time EventAuthorizer ALSO blocks delivery on support_coi_blocked = 1
            $authorizer = new EventAuthorizer();
            $rtIdentity = new Identity((int)$conflictedHr['id'], 'hr', (int)$conflictedHr['employee_id'], str_repeat('a', 32), time() + 300);
            $this->assertFalse($authorizer->canDeliver($rtIdentity, [
                'user_id'                         => (int)$conflictedHr['id'],
                'event_type'                      => EventAuthorizer::SUPPORT_COMPLAINT_ALERT,
                'recipient_role'                  => 'hr',
                'recipient_status'                => 'Active',
                'owner_matches'                   => 0,
                'support_coi_blocked'             => 1,
                'support_confidential_authorized' => 1,
            ]));

            // Non-conflicted Admin CAN view the escalated complaint
            $this->assertTrue($this->access->canViewTicketAsHr($unconflictedAdmin, $complaint, $this->handlerModel, $this->pdo));

            // Assignment to conflicted HR must be rejected
            try {
                $this->workflow->assignHandler($unconflictedAdmin, $ticketId, (int)$conflictedHr['id'], 'Try assigning conflicted HR');
                $this->fail('Expected COI assignment block');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('conflict of interest', strtolower($e->getMessage()));
            }

            // Non-escalated complaint naming an Admin still goes to available non-conflicted HR handler
            $adminComplaint = $this->workflow->createSubmission($emp, [
                'request_type'            => 'complaint',
                'complaint_category'      => 'Workplace Policy Concern',
                'subject_employee_id'     => $conflictedAdmin['employee_id'],
                'priority'                => 'High',
                'subject'                 => 'Admin COI non-escalated test',
                'description'             => 'Complaint where specific Admin account is named while an unconflicted HR handler is active.',
                'identity_acknowledgment' => '1',
            ]);
            $this->ticketIds[] = (int)$adminComplaint['id'];
            $this->assertSame(0, (int)$adminComplaint['escalated_flag']);
            $this->assertTrue($this->access->canViewTicketAsHr($conflictedHr, $adminComplaint, $this->handlerModel, $this->pdo));
            $this->assertFalse($this->access->canViewTicketAsHr($conflictedAdmin, $adminComplaint, $this->handlerModel, $this->pdo));

            // Even on an escalated complaint, an Admin who IS the subject remains blocked while an unconflicted Admin has oversight
            $this->pdo->exec("UPDATE support_ticket_handlers SET is_active = 0 WHERE user_id = " . (int)$conflictedHr['id']);
            $escalatedAdminComplaint = $this->workflow->createSubmission($emp, [
                'request_type'            => 'complaint',
                'complaint_category'      => 'Workplace Policy Concern',
                'subject_employee_id'     => $conflictedAdmin['employee_id'],
                'priority'                => 'High',
                'subject'                 => 'Admin COI escalated boundary test',
                'description'             => 'Escalated complaint where specific Admin account is named as complaint subject.',
                'identity_acknowledgment' => '1',
            ]);
            $this->ticketIds[] = (int)$escalatedAdminComplaint['id'];
            $this->assertSame(1, (int)$escalatedAdminComplaint['escalated_flag']);
            $this->assertFalse($this->access->canViewTicketAsHr($conflictedAdmin, $escalatedAdminComplaint, $this->handlerModel, $this->pdo));
            $this->assertTrue($this->access->canViewTicketAsHr($unconflictedAdmin, $escalatedAdminComplaint, $this->handlerModel, $this->pdo));
        } finally {
            if ($existingActiveHrIds !== []) {
                $idList = implode(',', array_map('intval', $existingActiveHrIds));
                $this->pdo->exec("UPDATE support_ticket_handlers SET is_active = 1 WHERE user_id IN ({$idList})");
            }
        }
    }
}
