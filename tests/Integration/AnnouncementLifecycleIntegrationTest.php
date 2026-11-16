<?php

/**
 * Announcement Lifecycle, Scheduling, Targeting, Read Receipts & Queue Integration Tests (Phase 13.2)
 * Employee HRIS & Leave Management System (NEXORA HR)
 */

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Announcement\Adapters\AnnouncementControllerAdapter;
use App\Infrastructure\Persistence\Announcement\MySqlAnnouncementRepository;
use App\Models\Notification;
use App\Queue\Handlers\DistributeAnnouncementNotificationsHandler;
use App\Queue\JobRegistry;
use App\Queue\QueueService;
use Core\Database;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class AnnouncementLifecycleIntegrationTest extends TestCase
{
    private PDO $pdo;
    private MySqlAnnouncementRepository $repository;
    private AnnouncementControllerAdapter $adapter;
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

    protected function setUp(): void
    {
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database is unavailable.');
        }
        $this->pdo = $db->getConnection();

        $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'announcements'");
        if (!$tableCheck || $tableCheck->fetchColumn() === false) {
            $this->markTestSkipped('announcements table is not migrated yet.');
        }

        $this->storageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexora_ann_int_' . bin2hex(random_bytes(4));
        @mkdir($this->storageDir, 0775, true);

        $attService = new \App\Services\AnnouncementAttachmentService(
            $this->storageDir,
            static fn (string $p): bool => is_file($p)
        );
        $this->repository = new MySqlAnnouncementRepository($this->pdo);
        $this->adapter = new AnnouncementControllerAdapter(
            pdo: $this->pdo,
            repository: $this->repository,
            attachmentService: $attService
        );

        $tag = bin2hex(random_bytes(3));
        $this->pdo->prepare('INSERT INTO departments (department_name, description) VALUES (?, ?)')->execute(["AnnDeptA_{$tag}", 'Dept A']);
        $this->deptAId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare('INSERT INTO departments (department_name, description) VALUES (?, ?)')->execute(["AnnDeptB_{$tag}", 'Dept B']);
        $this->deptBId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare('INSERT INTO positions (department_id, position_name, description) VALUES (?, ?, ?)')->execute([$this->deptAId, "PosA_{$tag}", 'Pos A']);
        $this->posAId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare('INSERT INTO positions (department_id, position_name, description) VALUES (?, ?, ?)')->execute([$this->deptBId, "PosB_{$tag}", 'Pos B']);
        $this->posBId = (int)$this->pdo->lastInsertId();

        // Create HR author, Dept A employee, Dept B employee
        [$hrEmpId, $hrUserId] = $this->seedAccount("HRANN_{$tag}", 'hr', $this->deptAId, $this->posAId);
        [$empAId, $empAUserId] = $this->seedAccount("EMPA_{$tag}", 'employee', $this->deptAId, $this->posAId);
        [$empBId, $empBUserId] = $this->seedAccount("EMPB_{$tag}", 'employee', $this->deptBId, $this->posBId);

        $this->empIds = [$hrEmpId, $empAId, $empBId];
        $this->userIds = [$hrUserId, $empAUserId, $empBUserId];
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            if (!empty($this->announcementIds)) {
                $in = implode(',', array_map('intval', $this->announcementIds));
                $this->pdo->exec("DELETE FROM notifications WHERE entity_id IN ({$in}) AND event_type LIKE 'announcement.%'");
                $this->pdo->exec("DELETE FROM outbox_events WHERE aggregate_type = 'Announcement' AND aggregate_id IN (" . implode(',', array_map(static fn (int $id): string => "'{$id}'", $this->announcementIds)) . ')');
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
            $this->pdo->exec("DELETE FROM queue_jobs WHERE job_type = 'distribute_announcement_notifications' AND status IN ('pending', 'completed')");
        }

        parent::tearDown();
    }

    public function test_draft_creation_editing_and_no_employee_notification(): void
    {
        $hrUserId = $this->userIds[0];
        $empAId = $this->empIds[1];
        $empAUserId = $this->userIds[1];

        $res = $this->adapter->create($hrUserId, 'hr', [
            'title'        => 'Draft HR Policy Revision 2026',
            'content'      => 'Initial draft of the updated remote work and attendance policy.',
            'category'     => 'HR Policy Updates',
            'priority'     => 'Normal',
            'target_type'  => 'all',
            'publish_mode' => 'draft',
        ]);

        $this->assertTrue($res->success, $res->message);
        $aid = (int)$res->data['id'];
        $this->announcementIds[] = $aid;
        $this->assertSame('Draft', $res->data['status']);

        // Draft must never appear in Employee Feed or trigger employee notifications
        $feed = $this->repository->paginateForEmployee($empAId, $this->deptAId, 'Active');
        $feedIds = array_map(static fn (array $r): int => (int)$r['id'], $feed['items']);
        $this->assertNotContains($aid, $feedIds);

        $notifModel = new Notification($this->pdo);
        $this->assertFalse($notifModel->existsForEvent($empAUserId, 'announcement.published', $aid));

        // Edit Draft
        $upd = $this->adapter->update($aid, $hrUserId, 'hr', false, [
            'title'        => 'Updated Draft HR Policy Revision 2026',
            'content'      => 'Refined draft content before publication.',
            'category'     => 'HR Policy Updates',
            'priority'     => 'Important',
            'target_type'  => 'department',
            'department_ids' => [$this->deptAId],
            'publish_mode' => 'draft',
            'is_pinned'    => 1,
        ]);
        $this->assertTrue($upd->success, $upd->message);

        $detail = $this->repository->findDetailForHr($aid);
        $this->assertNotNull($detail);
        $this->assertSame('Updated Draft HR Policy Revision 2026', $detail['title']);
        $this->assertSame('Important', $detail['priority']);
        $this->assertSame([$this->deptAId], $detail['target_department_ids']);
    }

    public function test_department_targeting_immediate_publication_queue_notifications_and_read_receipts(): void
    {
        $hrUserId = $this->userIds[0];
        $empAId = $this->empIds[1];
        $empAUserId = $this->userIds[1];
        $empBId = $this->empIds[2];
        $empBUserId = $this->userIds[2];

        // Create & immediately publish an Urgent announcement targeted ONLY to Dept A
        $publishClock = new DateTimeImmutable('2026-11-16 09:00:00');
        $res = $this->adapter->create($hrUserId, 'hr', [
            'title'          => 'Dept A Server Room Maintenance',
            'content'        => 'Mandatory maintenance window for Department A systems tonight at 22:00.',
            'category'       => 'Office Operations',
            'priority'       => 'Urgent',
            'target_type'    => 'department',
            'department_ids' => [$this->deptAId],
            'publish_mode'   => 'publish',
            'is_pinned'      => 1,
        ], null, $publishClock);

        $this->assertTrue($res->success, $res->message);
        $aid = (int)$res->data['id'];
        $this->announcementIds[] = $aid;
        $this->assertSame('Published', $res->data['status']);

        // Execute the queued notification distribution handler
        $handler = new DistributeAnnouncementNotificationsHandler(
            $this->repository,
            new Notification($this->pdo),
            new QueueService($this->pdo)
        );
        $handler->handle([
            'announcement_id' => $aid,
            'offset'          => 0,
            'batch_size'      => 100,
        ]);
        // Re-running the handler must be 100% idempotent (zero duplicate notifications)
        $handler->handle([
            'announcement_id' => $aid,
            'offset'          => 0,
            'batch_size'      => 100,
        ]);

        $notifStmtA = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND entity_id = ? AND event_type = 'announcement.urgent'");
        $notifStmtA->execute([$empAUserId, $aid]);
        $this->assertSame(1, (int)$notifStmtA->fetchColumn(), 'Targeted employee in Dept A must receive exactly 1 notification.');

        $notifStmtB = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND entity_id = ?");
        $notifStmtB->execute([$empBUserId, $aid]);
        $this->assertSame(0, (int)$notifStmtB->fetchColumn(), 'Untargeted employee in Dept B must receive 0 notifications.');

        // Dashboard preview for Dept A includes announcement WITHOUT marking it as read
        $previewA = $this->repository->getDashboardPreviewForEmployee($empAId, $this->deptAId, 'Active', 3);
        $previewIds = array_map(static fn (array $r): int => (int)$r['id'], $previewA);
        $this->assertContains($aid, $previewIds);
        $this->assertFalse($this->repository->hasEmployeeRead($aid, $empAId), 'Dashboard preview must never mark announcement as read.');

        // Dept B employee cannot access the announcement or record a read receipt
        $this->assertNull($this->repository->findAccessibleDetailForEmployee($aid, $empBId, $this->deptBId, 'Active'));
        $deniedRead = $this->adapter->recordEmployeeRead($aid, $empBId, $this->deptBId, 'Active');
        $this->assertFalse($deniedRead->success);
        $this->assertSame(403, $deniedRead->statusCode);

        // Dept A employee opens announcement -> records first read receipt idempotently
        $firstReadTime = new DateTimeImmutable('2026-11-16 10:15:00');
        $read1 = $this->adapter->recordEmployeeRead($aid, $empAId, $this->deptAId, 'Active', $firstReadTime);
        $this->assertTrue($read1->success);
        $this->assertTrue($read1->data['newly_recorded']);

        $laterReadTime = new DateTimeImmutable('2026-11-16 11:30:00');
        $read2 = $this->adapter->recordEmployeeRead($aid, $empAId, $this->deptAId, 'Active', $laterReadTime);
        $this->assertTrue($read2->success);
        $this->assertFalse($read2->data['newly_recorded']);
        $this->assertSame('2026-11-16 10:15:00', $read2->data['read_at'], 'Original first read timestamp must be preserved.');

        // Verify HR Read Statistics (Dept A has 2 active employees in this test: $hrEmpId and $empAId; 1 has read = 50.0%)
        $stats = $this->repository->getReadStatistics($aid);
        $this->assertSame(2, $stats['total_targeted']);
        $this->assertSame(1, $stats['total_read']);
        $this->assertSame(1, $stats['total_unread']);
        $this->assertSame(50.0, $stats['read_percentage']);
    }

    public function test_scheduled_publication_and_expiration_cron_processing_idempotency(): void
    {
        $hrUserId = $this->userIds[0];
        $empAId = $this->empIds[1];

        $now = new DateTimeImmutable('2026-11-16 08:00:00');
        $schedAt = '2026-11-16 09:00:00';
        $expAt = '2026-11-16 17:00:00';

        $res = $this->adapter->create($hrUserId, 'hr', [
            'title'        => 'Scheduled Town Hall Reminder',
            'content'      => 'Reminder for today\'s 10 AM town hall session.',
            'category'     => 'Events and Activities',
            'priority'     => 'Important',
            'target_type'  => 'all',
            'publish_mode' => 'schedule',
            'scheduled_at' => $schedAt,
            'expires_at'   => $expAt,
        ], null, $now);

        $this->assertTrue($res->success, $res->message);
        $aid = (int)$res->data['id'];
        $this->announcementIds[] = $aid;
        $this->assertSame('Scheduled', $res->data['status']);

        // Before 09:00, CRON must not publish it
        $earlyRun = $this->adapter->processDueLifecycleTransitions(new DateTimeImmutable('2026-11-16 08:30:00'));
        $this->assertNotContains($aid, $earlyRun['published_ids']);
        $this->assertNull($this->repository->findAccessibleDetailForEmployee($aid, $empAId, $this->deptAId, 'Active'));

        // At 09:05, CRON publishes it
        $dueRun = $this->adapter->processDueLifecycleTransitions(new DateTimeImmutable('2026-11-16 09:05:00'));
        $this->assertContains($aid, $dueRun['published_ids']);
        $annAfterPub = $this->repository->findById($aid);
        $this->assertNotNull($annAfterPub);
        $this->assertTrue($annAfterPub->status()->isPublished());

        // Second CRON execution at 09:06 must be a no-op (no duplicate publication)
        $dupRun = $this->adapter->processDueLifecycleTransitions(new DateTimeImmutable('2026-11-16 09:06:00'));
        $this->assertNotContains($aid, $dupRun['published_ids']);

        // At 17:05 (after expires_at), CRON transitions it to Expired
        $expRun = $this->adapter->processDueLifecycleTransitions(new DateTimeImmutable('2026-11-16 17:05:00'));
        $this->assertContains($aid, $expRun['expired_ids']);
        $annAfterExp = $this->repository->findById($aid);
        $this->assertNotNull($annAfterExp);
        $this->assertTrue($annAfterExp->status()->isExpired());

        // Second expiration CRON run is a no-op
        $dupExpRun = $this->adapter->processDueLifecycleTransitions(new DateTimeImmutable('2026-11-16 17:06:00'));
        $this->assertNotContains($aid, $dupExpRun['expired_ids']);
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
