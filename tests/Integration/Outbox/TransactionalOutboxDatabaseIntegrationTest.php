<?php

/**
 * Transactional Outbox Real Database Integration Tests
 * HRIS-SYSTEM — Test Suite (Phase 1: Transactional Outbox)
 */

declare(strict_types=1);

namespace Tests\Integration\Outbox;

use App\Domain\Leave\Events\LeaveApprovedEvent;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Outbox\MySqlOutboxRepository;
use Core\Database;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

class TransactionalOutboxDatabaseIntegrationTest extends TestCase
{
    private PDO $pdo;
    private MySqlOutboxRepository $outboxRepo;
    private PdoTransactionManager $txManager;

    protected function setUp(): void
    {
        $this->pdo = Database::getInstance()->getConnection();
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `outbox_events` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `event_id` VARCHAR(36) NOT NULL,
            `event_type` VARCHAR(191) NOT NULL,
            `aggregate_type` VARCHAR(100) NOT NULL,
            `aggregate_id` VARCHAR(100) NOT NULL,
            `payload` JSON NOT NULL,
            `status` ENUM('PENDING', 'PROCESSING', 'PROCESSED', 'FAILED') NOT NULL DEFAULT 'PENDING',
            `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `max_attempts` INT UNSIGNED NOT NULL DEFAULT 5,
            `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            `next_attempt_at` DATETIME(6) NULL DEFAULT CURRENT_TIMESTAMP(6),
            `processed_at` DATETIME(6) NULL,
            `claimed_at` DATETIME(6) NULL,
            `claimed_by` VARCHAR(191) NULL,
            `last_error` TEXT NULL,
            `last_error_code` VARCHAR(100) NULL,
            UNIQUE KEY `uq_outbox_event_id` (`event_id`),
            INDEX `idx_outbox_status_next_attempt` (`status`, `next_attempt_at`),
            INDEX `idx_outbox_claimed` (`status`, `claimed_at`),
            INDEX `idx_outbox_aggregate` (`aggregate_type`, `aggregate_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->outboxRepo = new MySqlOutboxRepository($this->pdo);
        $this->txManager = new PdoTransactionManager($this->pdo);

        // Clean up test events
        $this->pdo->exec("DELETE FROM outbox_events");
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM outbox_events");
    }

    public function test_outbox_event_persists_on_transaction_commit(): void
    {
        $event = new LeaveApprovedEvent(901, 10, 1, 2, 5, new DateTimeImmutable());

        $this->txManager->transactional(function () use ($event) {
            $this->outboxRepo->record($event, 'TestAggregate', '901');
        });

        $stmt = $this->pdo->prepare("SELECT * FROM outbox_events WHERE aggregate_type = 'TestAggregate' AND aggregate_id = '901'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($row);
        $this->assertSame('PENDING', $row['status']);
        $this->assertSame(LeaveApprovedEvent::class, $row['event_type']);
        $this->assertSame(0, (int)$row['attempts']);
    }

    public function test_outbox_event_rolls_back_when_transaction_fails(): void
    {
        $event = new LeaveApprovedEvent(902, 10, 1, 2, 5, new DateTimeImmutable());

        try {
            $this->txManager->transactional(function () use ($event) {
                $this->outboxRepo->record($event, 'TestAggregate', '902');
                throw new \RuntimeException('Simulated failure triggering rollback');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM outbox_events WHERE aggregate_type = 'TestAggregate' AND aggregate_id = '902'");
        $stmt->execute();
        $count = (int)$stmt->fetchColumn();

        $this->assertSame(0, $count, 'Outbox event must roll back atomically when transaction aborts.');
    }

    public function test_concurrent_claim_prevents_duplicate_processing(): void
    {
        $event1 = new LeaveApprovedEvent(903, 10, 1, 2, 5, new DateTimeImmutable());
        $event2 = new LeaveApprovedEvent(904, 10, 1, 2, 5, new DateTimeImmutable());

        $this->outboxRepo->record($event1, 'TestAggregate', '903');
        $this->outboxRepo->record($event2, 'TestAggregate', '904');

        // Worker A claims 1 event
        $claimedA = $this->outboxRepo->claimPending(1, 'worker-alpha');
        $this->assertCount(1, $claimedA);
        $this->assertSame('worker-alpha', $this->fetchClaimedBy((int)$claimedA[0]['id']));

        // Worker B claims remaining pending events without collision
        $claimedB = $this->outboxRepo->claimPending(10, 'worker-beta');
        $this->assertCount(1, $claimedB);
        $this->assertNotSame($claimedA[0]['id'], $claimedB[0]['id'], 'Workers must claim distinct events with SKIP LOCKED');

        // Marking Worker A event processed
        $this->outboxRepo->markProcessed((int)$claimedA[0]['id']);
        $statusA = $this->fetchStatus((int)$claimedA[0]['id']);
        $this->assertSame('PROCESSED', $statusA);

        // Marking Worker B event failed with backoff
        $retryAt = (new DateTimeImmutable())->modify('+60 seconds');
        $this->outboxRepo->markFailed((int)$claimedB[0]['id'], 'Temporary SMTP down', 'SMTP_ERR', $retryAt, false);
        $statusB = $this->fetchStatus((int)$claimedB[0]['id']);
        $this->assertSame('PENDING', $statusB);
    }

    public function test_abandoned_claim_recovery_in_mysql(): void
    {
        $event = new LeaveApprovedEvent(905, 10, 1, 2, 5, new DateTimeImmutable());
        $this->outboxRepo->record($event, 'TestAggregate', '905');

        $claimed = $this->outboxRepo->claimPending(1, 'crashed-daemon');
        $this->assertCount(1, $claimed);
        $eventId = (int)$claimed[0]['id'];

        // Artificially backdate claimed_at to simulate worker death
        $stmt = $this->pdo->prepare("UPDATE outbox_events SET claimed_at = DATE_SUB(NOW(6), INTERVAL 600 SECOND) WHERE id = :id");
        $stmt->execute(['id' => $eventId]);

        $recovered = $this->outboxRepo->recoverAbandonedClaims(300);
        $this->assertGreaterThanOrEqual(1, $recovered);

        $status = $this->fetchStatus($eventId);
        $this->assertSame('PENDING', $status);
        $this->assertNull($this->fetchClaimedBy($eventId));
    }

    private function fetchStatus(int $id): string
    {
        $stmt = $this->pdo->prepare("SELECT status FROM outbox_events WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return (string)$stmt->fetchColumn();
    }

    private function fetchClaimedBy(int $id): ?string
    {
        $stmt = $this->pdo->prepare("SELECT claimed_by FROM outbox_events WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (string)$val : null;
    }
}
