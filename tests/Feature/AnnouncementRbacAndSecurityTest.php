<?php

/**
 * Announcement RBAC, Ownership, Attachment Security & Realtime Authorization Tests (Phase 13.3 & 13.4)
 * Employee HRIS & Leave Management System (NEXORA HR)
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Announcement\Adapters\AnnouncementControllerAdapter;
use App\Controllers\EmployeeAnnouncementController;
use App\Controllers\HrAnnouncementController;
use App\Infrastructure\Persistence\Announcement\MySqlAnnouncementRepository;
use App\Realtime\EventAuthorizer;
use App\Realtime\Identity;
use App\Realtime\NotificationPayload;
use App\Services\AnnouncementAttachmentService;
use Core\AuthorizationService;
use Core\Database;
use Core\Permission;
use Core\PermissionRegistry;
use Core\Session;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AnnouncementRbacAndSecurityTest extends TestCase
{
    private PDO $pdo;
    private MySqlAnnouncementRepository $repository;
    private AnnouncementControllerAdapter $adapter;
    private AnnouncementAttachmentService $attachmentService;
    private string $storageDir = '';

    private int $deptAId = 0;
    private int $deptBId = 0;
    private int $posAId = 0;
    private int $posBId = 0;
    /** @var list<int> */
    private array $empIds = [];
    /** @var list<int> */
    private array $userIds = [];
    /** @var list<int> */
    private array $announcementIds = [];
    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        Session::destroy();
        Session::start();
        AuthorizationService::invalidateCache();

        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database is unavailable.');
        }
        $this->pdo = $db->getConnection();

        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'announcements'");
        if (!$tableCheck || $tableCheck->fetchColumn() === false) {
            $this->markTestSkipped('announcements table is not migrated yet.');
        }

        $this->storageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexora_ann_sec_' . bin2hex(random_bytes(4));
        @mkdir($this->storageDir, 0775, true);

        $this->attachmentService = new AnnouncementAttachmentService(
            $this->storageDir,
            static fn (string $p): bool => is_file($p)
        );
        $this->repository = new MySqlAnnouncementRepository($this->pdo);
        $this->adapter = new AnnouncementControllerAdapter(
            pdo: $this->pdo,
            repository: $this->repository,
            attachmentService: $this->attachmentService
        );

        $tag = bin2hex(random_bytes(3));
        $this->pdo->prepare('INSERT INTO departments (department_name, description) VALUES (?, ?)')->execute(["SecDeptA_{$tag}", 'Dept A']);
        $this->deptAId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare('INSERT INTO departments (department_name, description) VALUES (?, ?)')->execute(["SecDeptB_{$tag}", 'Dept B']);
        $this->deptBId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare('INSERT INTO positions (department_id, position_name, description) VALUES (?, ?, ?)')->execute([$this->deptAId, "SecPosA_{$tag}", 'Pos A']);
        $this->posAId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare('INSERT INTO positions (department_id, position_name, description) VALUES (?, ?, ?)')->execute([$this->deptBId, "SecPosB_{$tag}", 'Pos B']);
        $this->posBId = (int)$this->pdo->lastInsertId();

        // Seed HR1, HR2, Admin, EmpA, EmpB
        [$hr1EmpId, $hr1UserId] = $this->seedAccount("SECHR1_{$tag}", 'hr', $this->deptAId, $this->posAId);
        [$hr2EmpId, $hr2UserId] = $this->seedAccount("SECHR2_{$tag}", 'hr', $this->deptAId, $this->posAId);
        [$admEmpId, $admUserId] = $this->seedAccount("SECADM_{$tag}", 'admin', $this->deptAId, $this->posAId);
        [$empAId, $empAUserId] = $this->seedAccount("SECEMPA_{$tag}", 'employee', $this->deptAId, $this->posAId);
        [$empBId, $empBUserId] = $this->seedAccount("SECEMPB_{$tag}", 'employee', $this->deptBId, $this->posBId);

        $this->empIds = [$hr1EmpId, $hr2EmpId, $admEmpId, $empAId, $empBId];
        $this->userIds = [$hr1UserId, $hr2UserId, $admUserId, $empAUserId, $empBUserId];
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        if (isset($this->pdo)) {
            if (!empty($this->announcementIds)) {
                $in = implode(',', array_map('intval', $this->announcementIds));
                $this->pdo->exec("DELETE FROM notifications WHERE entity_id IN ({$in}) AND event_type LIKE 'announcement.%'");
                $this->pdo->exec("DELETE FROM announcements WHERE id IN ({$in})");
            }
            if (!empty($this->userIds)) {
                $inU = implode(',', array_map('intval', $this->userIds));
                $this->pdo->exec("DELETE FROM notifications WHERE user_id IN ({$inU})");
                $this->pdo->exec("DELETE FROM activity_logs WHERE user_id IN ({$inU})");
                $this->pdo->exec("DELETE FROM users WHERE id IN ({$inU})");
            }
            if (!empty($this->empIds)) {
                $inE = implode(',', array_map('intval', $this->empIds));
                $this->pdo->exec("DELETE FROM employees WHERE id IN ({$inE})");
            }
            if ($this->posAId > 0 || $this->posBId > 0) {
                $this->pdo->exec("DELETE FROM positions WHERE id IN ({$this->posAId}, {$this->posBId})");
            }
            if ($this->deptAId > 0 || $this->deptBId > 0) {
                $this->pdo->exec("DELETE FROM departments WHERE id IN ({$this->deptAId}, {$this->deptBId})");
            }
        }
        Session::destroy();
        parent::tearDown();
    }

    public function test_three_role_least_privilege_announcement_permissions(): void
    {
        // HR holds default announcement creation/management permissions (own-scoped for edit/stats)
        $this->assertTrue(PermissionRegistry::defaultRoleHas('hr', 'announcements.view'));
        $this->assertTrue(PermissionRegistry::defaultRoleHas('hr', 'announcements.create'));
        $this->assertTrue(PermissionRegistry::defaultRoleHas('hr', 'announcements.edit_own'));
        $this->assertTrue(PermissionRegistry::defaultRoleHas('hr', 'announcements.publish'));
        $this->assertTrue(PermissionRegistry::defaultRoleHas('hr', 'announcements.schedule'));
        $this->assertTrue(PermissionRegistry::defaultRoleHas('hr', 'announcements.archive'));
        $this->assertTrue(PermissionRegistry::defaultRoleHas('hr', 'announcements.delete_draft'));
        $this->assertTrue(PermissionRegistry::defaultRoleHas('hr', 'announcements.stats_own'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('hr', 'announcements.manage_all'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('hr', 'announcements.stats_all'));

        // Employee holds only announcements.feed
        $this->assertTrue(PermissionRegistry::defaultRoleHas('employee', 'announcements.feed'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('employee', 'announcements.view'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('employee', 'announcements.create'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('employee', 'announcements.publish'));

        // Admin does NOT automatically receive unrestricted HR announcement permissions by default
        $this->assertFalse(PermissionRegistry::defaultRoleHas('admin', 'announcements.view'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('admin', 'announcements.create'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('admin', 'announcements.publish'));
        $this->assertFalse(PermissionRegistry::defaultRoleHas('admin', 'announcements.manage_all'));
    }

    public function test_ownership_policy_blocks_other_hr_from_modifying_or_viewing_unauthorized_stats(): void
    {
        $hr1UserId = $this->userIds[0];
        $hr2UserId = $this->userIds[1];
        $empAUserId = $this->userIds[3];

        // Employee cannot create an announcement
        $empAttempt = $this->adapter->create($empAUserId, 'employee', [
            'title'        => 'Unauthorized Employee Post',
            'content'      => 'Employees cannot create announcements.',
            'category'     => 'General Announcement',
            'priority'     => 'Normal',
            'target_type'  => 'all',
            'publish_mode' => 'publish',
        ]);
        $this->assertFalse($empAttempt->success);
        $this->assertSame(403, $empAttempt->statusCode);

        // HR1 creates an announcement
        $created = $this->adapter->create($hr1UserId, 'hr', [
            'title'        => 'HR1 Authored Policy Announcement',
            'content'      => 'Official policy authored by HR Officer 1.',
            'category'     => 'HR Policy Updates',
            'priority'     => 'Important',
            'target_type'  => 'all',
            'publish_mode' => 'draft',
        ]);
        $this->assertTrue($created->success);
        $aid = (int)$created->data['id'];
        $this->announcementIds[] = $aid;

        // HR2 (without announcements.manage_all) cannot edit, publish, archive, or delete HR1's announcement
        $updByHr2 = $this->adapter->update($aid, $hr2UserId, 'hr', false, [
            'title'        => 'Tampered by HR2',
            'content'      => 'Tampered content.',
            'category'     => 'Other',
            'priority'     => 'Normal',
            'target_type'  => 'all',
            'publish_mode' => 'draft',
        ]);
        $this->assertFalse($updByHr2->success);
        $this->assertSame(403, $updByHr2->statusCode);

        $pubByHr2 = $this->adapter->publish($aid, $hr2UserId, false);
        $this->assertFalse($pubByHr2->success);
        $this->assertSame(403, $pubByHr2->statusCode);

        $delByHr2 = $this->adapter->deleteDraft($aid, $hr2UserId, false);
        $this->assertFalse($delByHr2->success);
        $this->assertSame(403, $delByHr2->statusCode);

        // HR2 (without announcements.stats_all) cannot view read statistics on HR1's announcement
        $entity = $this->repository->findById($aid);
        $this->assertNotNull($entity);
        $this->assertTrue($this->adapter->canViewAnnouncementStats($entity, $hr1UserId, false));
        $this->assertFalse($this->adapter->canViewAnnouncementStats($entity, $hr2UserId, false));
        $this->assertTrue($this->adapter->canViewAnnouncementStats($entity, $hr2UserId, true));
    }

    public function test_invalid_department_id_targeting_and_malicious_file_uploads_are_rejected(): void
    {
        $hr1UserId = $this->userIds[0];

        // Arbitrary non-existent department ID (999999) is rejected server-side
        $badDept = $this->adapter->create($hr1UserId, 'hr', [
            'title'          => 'Invalid Dept Announcement',
            'content'        => 'Targeting a fabricated department ID.',
            'category'       => 'General Announcement',
            'priority'       => 'Normal',
            'target_type'    => 'department',
            'department_ids' => [999999],
            'publish_mode'   => 'draft',
        ]);
        $this->assertFalse($badDept->success);
        $this->assertSame(400, $badDept->statusCode);

        // Double-extension PHP script disguised as PDF is rejected
        $maliciousTmp = tempnam(sys_get_temp_dir(), 'ann_mal_') . '.pdf';
        file_put_contents($maliciousTmp, "<?php system('id'); ?>");
        $this->tempFiles[] = $maliciousTmp;

        $this->expectException(RuntimeException::class);
        $this->attachmentService->validateBatch([[
            'name'     => 'exploit.php.pdf',
            'type'     => 'application/pdf',
            'tmp_name' => $maliciousTmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => (int)filesize($maliciousTmp),
        ]]);
    }

    public function test_attachment_download_enforces_employee_audience_authorization(): void
    {
        $hr1UserId = $this->userIds[0];
        $empAId = $this->empIds[3];
        $empAUserId = $this->userIds[3];
        $empBId = $this->empIds[4];
        $empBUserId = $this->userIds[4];

        $pdfTmp = tempnam(sys_get_temp_dir(), 'ann_ok_') . '.pdf';
        file_put_contents($pdfTmp, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
        $this->tempFiles[] = $pdfTmp;

        $res = $this->adapter->create(
            authorUserId: $hr1UserId,
            actorRole: 'hr',
            input: [
                'title'          => 'Dept A Confidential Memo With Attachment',
                'content'        => 'Attached is the Department A schedule.',
                'category'       => 'Office Operations',
                'priority'       => 'Important',
                'target_type'    => 'department',
                'department_ids' => [$this->deptAId],
                'publish_mode'   => 'publish',
            ],
            filesInput: [
                'name'     => ['dept_a_schedule.pdf'],
                'type'     => ['application/pdf'],
                'tmp_name' => [$pdfTmp],
                'error'    => [UPLOAD_ERR_OK],
                'size'     => [(int)filesize($pdfTmp)],
            ]
        );

        $this->assertTrue($res->success, $res->message);
        $aid = (int)$res->data['id'];
        $this->announcementIds[] = $aid;

        $attachments = $this->repository->findAttachmentsArrayForAnnouncement($aid);
        $this->assertCount(1, $attachments);
        $attId = (int)$attachments[0]['id'];

        // Dept B employee attempting to download Dept A's attachment gets 403 Forbidden
        Session::set('user', [
            'id'          => $empBUserId,
            'employee_id' => $empBId,
            'role'        => 'employee',
            'status'      => 'Active',
        ]);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $empCtrl = new EmployeeAnnouncementController($this->pdo, $this->adapter, false);
        ob_start();
        $empCtrl->downloadAttachment($aid, $attId);
        $outB = (string)ob_get_clean();
        $decodedB = json_decode($outB, true);
        $this->assertSame('error', $decodedB['status'] ?? '');
        $this->assertSame(403, http_response_code());

        // Dept A employee can download the attachment
        Session::set('user', [
            'id'          => $empAUserId,
            'employee_id' => $empAId,
            'role'        => 'employee',
            'status'      => 'Active',
        ]);
        unset($_SERVER['HTTP_ACCEPT']);
        ob_start();
        $empCtrl->downloadAttachment($aid, $attId);
        $streamedBytes = (string)ob_get_clean();
        $this->assertStringStartsWith('%PDF-1.4', $streamedBytes);
    }

    public function test_websocket_event_authorizer_and_payload_for_announcements(): void
    {
        $authorizer = new EventAuthorizer();
        $empIdentity = new Identity(
            userId: 10,
            role: 'employee',
            employeeId: 4,
            sessionRef: 'sess_1',
            sessionExpiresAt: time() + 1800
        );
        $hrIdentity = new Identity(
            userId: 2,
            role: 'hr',
            employeeId: 2,
            sessionRef: 'sess_2',
            sessionExpiresAt: time() + 1800
        );

        $targetedRow = [
            'id'                    => 500,
            'user_id'               => 10,
            'event_type'            => EventAuthorizer::ANNOUNCEMENT_URGENT,
            'entity_id'             => 12,
            'recipient_role'        => 'employee',
            'recipient_status'      => 'Active',
            'announcement_targeted' => 1,
            'created_ts'            => time(),
        ];

        $this->assertTrue($authorizer->canDeliver($empIdentity, $targetedRow));
        $this->assertFalse($authorizer->canDeliver($hrIdentity, $targetedRow));

        $untargetedRow = $targetedRow;
        $untargetedRow['announcement_targeted'] = 0;
        $this->assertFalse($authorizer->canDeliver($empIdentity, $untargetedRow), 'Untargeted employee must never receive WS announcement event.');

        $payload = NotificationPayload::fromRow($targetedRow);
        $this->assertNotNull($payload);
        $this->assertSame('announcement.urgent', $payload['event']);
        $this->assertSame('ANN-0012', $payload['data']['reference']);
        $this->assertSame('employee/announcements/12', $payload['data']['link']);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function seedAccount(string $code, string $role, int $deptId, int $posId): array
    {
        $email = strtolower($code) . '@nexora.local';
        $this->pdo->prepare("
            INSERT INTO employees (employee_no, first_name, last_name, email, birth_date, gender, department_id, position_id, date_hired, employment_status)
            VALUES (?, ?, 'Tester', ?, '1994-05-10', 'Other', ?, ?, '2025-01-10', 'Active')
        ")->execute([$code, $code, $email, $deptId, $posId]);
        $empId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("
            INSERT INTO users (employee_id, username, email, password, role, status)
            VALUES (?, ?, ?, ?, ?, 'Active')
        ")->execute([$empId, strtolower($code), $email, password_hash('TestPass123!', PASSWORD_BCRYPT), $role]);
        $userId = (int)$this->pdo->lastInsertId();

        return [$empId, $userId];
    }
}
