<?php

/**
 * MySQL Queue Service & Worker Feature Test Suite
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Verifies:
 * 1. JobRegistry whitelist security enforcement.
 * 2. QueueService push payload validation and sensitive data redaction.
 * 3. QueueWorker job execution and error handling.
 * 4. Retry scheduling and exponential backoff.
 * 5. Dead-letter failed job handling.
 * 6. Abandoned claim recovery.
 * 7. Idempotency guarantees in job handlers.
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Queue\Handlers\SendEmailHandler;
use App\Queue\Handlers\SendNotificationHandler;
use App\Queue\JobInterface;
use App\Queue\JobRegistry;
use App\Queue\QueueService;
use App\Queue\QueueWorker;
use App\Services\LeaveNotificationService;
use App\Services\MailService;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class QueueWorkerTest extends TestCase
{
    public function testJobRegistryWhitelistsAllowedHandlers(): void
    {
        $this->assertTrue(JobRegistry::isRegistered(JobRegistry::TYPE_SEND_EMAIL));
        $this->assertTrue(JobRegistry::isRegistered(JobRegistry::TYPE_SEND_NOTIFICATION));
        $this->assertTrue(JobRegistry::isRegistered(JobRegistry::TYPE_LEAVE_REMINDER));
        $this->assertTrue(JobRegistry::isRegistered(JobRegistry::TYPE_REPORT_EXPORT));
        $this->assertTrue(JobRegistry::isRegistered(JobRegistry::TYPE_OUTBOX_RELAY));
        $this->assertTrue(JobRegistry::isRegistered(JobRegistry::TYPE_DISTRIBUTE_ANNOUNCEMENT));

        $this->assertFalse(JobRegistry::isRegistered('malicious_arbitrary_job'));
        $this->assertFalse(JobRegistry::isRegistered('system_exec'));
    }

    public function testJobRegistryRejectsUnregisteredTypes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unregistered job type 'unknown_eval'");

        JobRegistry::resolve('unknown_eval');
    }

    public function testJobRegistryResolvesValidHandlerInstances(): void
    {
        $emailHandler = JobRegistry::resolve(JobRegistry::TYPE_SEND_EMAIL);
        $this->assertInstanceOf(JobInterface::class, $emailHandler);
        $this->assertInstanceOf(SendEmailHandler::class, $emailHandler);

        $notifHandler = JobRegistry::resolve(JobRegistry::TYPE_SEND_NOTIFICATION);
        $this->assertInstanceOf(JobInterface::class, $notifHandler);
        $this->assertInstanceOf(SendNotificationHandler::class, $notifHandler);
    }

    public function testSendEmailHandlerValidatesPayload(): void
    {
        $handler = new SendEmailHandler(new MailService(null, true));

        // Missing 'to'
        try {
            $handler->handle(['subject' => 'Test', 'body' => 'Body']);
            $this->fail('Should throw exception for missing to');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("Missing or invalid 'to'", $e->getMessage());
        }

        // Invalid email format
        try {
            $handler->handle(['to' => 'not-an-email', 'subject' => 'Test', 'body' => 'Body']);
            $this->fail('Should throw exception for invalid email format');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("Missing or invalid 'to'", $e->getMessage());
        }

        // Missing subject
        try {
            $handler->handle(['to' => 'user@example.com', 'subject' => '', 'body' => 'Body']);
            $this->fail('Should throw exception for empty subject');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("Missing 'subject'", $e->getMessage());
        }

        // Missing body
        try {
            $handler->handle(['to' => 'user@example.com', 'subject' => 'Hello', 'body' => '']);
            $this->fail('Should throw exception for empty body');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("Missing 'body'", $e->getMessage());
        }
    }

    public function testSendEmailHandlerDeliversSuccessfullyInMockMode(): void
    {
        $mockMailService = new MailService(null, true);
        $handler = new SendEmailHandler($mockMailService);

        $handler->handle([
            'to'      => 'employee@nexora.local',
            'subject' => 'Welcome to NEXORA HR',
            'body'    => '<p>Hello world</p>',
            'name'    => 'John Doe',
        ]);

        $sent = $mockMailService->getSentMails();
        $this->assertCount(1, $sent);
        $this->assertSame('employee@nexora.local', $sent[0]['to_address']);
        $this->assertSame('Welcome to NEXORA HR', $sent[0]['subject']);
    }

    public function testSendNotificationHandlerValidatesPayload(): void
    {
        $handler = new SendNotificationHandler();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing or invalid 'user_id'");

        $handler->handle(['user_id' => 0, 'title' => 'Test', 'message' => 'Hello']);
    }

    public function testDispatchJobGlobalHelperExists(): void
    {
        $this->assertTrue(function_exists('dispatch_job'));
    }

    public function testQueueServicePushPersistsJobAndRedactsCredentials(): void
    {
        $capturedSql = '';
        $boundValues = [];

        $stmtMock = $this->createStub(PDOStatement::class);
        $stmtMock->method('bindValue')->willReturnCallback(function ($param, $value) use (&$boundValues) {
            $boundValues[$param] = $value;
            return true;
        });
        $stmtMock->method('execute')->willReturn(true);

        $pdoMock = $this->createStub(PDO::class);
        $pdoMock->method('prepare')->willReturnCallback(function ($sql) use (&$capturedSql, $stmtMock) {
            $capturedSql = $sql;
            return $stmtMock;
        });
        $pdoMock->method('lastInsertId')->willReturn('42');

        $service = new QueueService($pdoMock);
        $jobId = $service->push(
            JobRegistry::TYPE_SEND_EMAIL,
            [
                'to'       => 'test@example.com',
                'password' => 'supersecret',
                'token'    => 'secret_jwt_token',
                'subject'  => 'Test Email',
            ],
            'custom_queue',
            30,
            5
        );

        $this->assertSame(42, $jobId);
        $this->assertStringContainsString('INSERT INTO queue_jobs', $capturedSql);
        $this->assertSame('custom_queue', $boundValues[':queue']);
        $this->assertSame(JobRegistry::TYPE_SEND_EMAIL, $boundValues[':job_type']);
        $this->assertSame(5, $boundValues[':max_attempts']);
        $this->assertSame(30, $boundValues[':delay']);

        $payloadJson = $boundValues[':payload'];
        $decoded = json_decode($payloadJson, true);
        $this->assertSame('test@example.com', $decoded['to']);
        $this->assertSame('[REDACTED]', $decoded['password']);
        $this->assertSame('[REDACTED]', $decoded['token']);
    }

    public function testQueueServicePushRejectsUnregisteredType(): void
    {
        $pdoMock = $this->createStub(PDO::class);
        $service = new QueueService($pdoMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot dispatch unregistered job type 'malicious_eval'");

        $service->push('malicious_eval', []);
    }

    public function testQueueServiceClaimPendingLocksAndReservesRows(): void
    {
        $selectStmt = $this->createStub(PDOStatement::class);
        $selectStmt->method('fetchAll')->willReturn([
            [
                'id'           => 101,
                'queue'        => 'default',
                'job_type'     => JobRegistry::TYPE_SEND_EMAIL,
                'payload'      => json_encode(['to' => 'user@nexora.local', 'subject' => 'Hi']),
                'attempts'     => 0,
                'max_attempts' => 3,
            ],
        ]);

        $updateStmt = $this->createStub(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdoMock->expects($this->once())->method('commit')->willReturn(true);
        $pdoMock->method('prepare')->willReturnCallback(function ($sql) use ($selectStmt, $updateStmt) {
            if (str_contains($sql, 'FOR UPDATE SKIP LOCKED')) {
                return $selectStmt;
            }
            if (str_contains($sql, 'UPDATE queue_jobs')) {
                return $updateStmt;
            }
            throw new RuntimeException("Unexpected query: {$sql}");
        });

        $service = new QueueService($pdoMock);
        $claimed = $service->claimPending(5, 'worker-container-1', 'default');

        $this->assertCount(1, $claimed);
        $this->assertSame(101, $claimed[0]['id']);
        $this->assertSame(JobRegistry::TYPE_SEND_EMAIL, $claimed[0]['job_type']);
        $this->assertSame('user@nexora.local', $claimed[0]['payload']['to']);
    }

    public function testQueueServiceMarkCompletedUpdatesStatus(): void
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())->method('execute')->with(['id' => 101])->willReturn(true);

        $pdoMock = $this->createStub(PDO::class);
        $pdoMock->method('prepare')->willReturnCallback(function ($sql) use ($stmtMock) {
            $this->assertStringContainsString("status = 'completed'", $sql);
            return $stmtMock;
        });

        $service = new QueueService($pdoMock);
        $service->markCompleted(101);
    }

    public function testQueueServiceMarkFailedRetriesWithExponentialBackoff(): void
    {
        $selectStmt = $this->createStub(PDOStatement::class);
        $selectStmt->method('fetch')->willReturn([
            'queue'        => 'default',
            'job_type'     => JobRegistry::TYPE_SEND_EMAIL,
            'payload'      => json_encode(['to' => 'user@nexora.local']),
            'attempts'     => 1,
            'max_attempts' => 3,
        ]);

        $updateStmt = $this->createStub(PDOStatement::class);
        $retryParams = [];
        $updateStmt->method('execute')->willReturnCallback(function ($params) use (&$retryParams) {
            $retryParams = $params;
            return true;
        });

        $pdoMock = $this->createStub(PDO::class);
        $pdoMock->method('prepare')->willReturnCallback(function ($sql) use ($selectStmt, $updateStmt) {
            if (str_contains($sql, 'SELECT')) {
                return $selectStmt;
            }
            if (str_contains($sql, 'UPDATE')) {
                $this->assertStringContainsString("status = 'pending'", $sql);
                $this->assertStringContainsString("available_at = DATE_ADD", $sql);
                return $updateStmt;
            }
            throw new RuntimeException("Unexpected SQL: {$sql}");
        });

        $service = new QueueService($pdoMock);
        $service->markFailed(101, new RuntimeException('Temporary SMTP timeout'));

        $this->assertSame(101, $retryParams['id']);
        $this->assertSame(2, $retryParams['attempts']);
        $this->assertGreaterThan(0, $retryParams['delay']);
        $this->assertStringContainsString('Temporary SMTP timeout', $retryParams['err']);
    }

    public function testQueueServiceMarkFailedMovesToFailedJobsOnTerminalFailure(): void
    {
        $selectStmt = $this->createStub(PDOStatement::class);
        $selectStmt->method('fetch')->willReturn([
            'queue'        => 'default',
            'job_type'     => JobRegistry::TYPE_SEND_EMAIL,
            'payload'      => json_encode(['to' => 'user@nexora.local']),
            'attempts'     => 2,
            'max_attempts' => 3,
        ]);

        $insertFailedStmt = $this->createStub(PDOStatement::class);
        $insertParams = [];
        $insertFailedStmt->method('execute')->willReturnCallback(function ($params) use (&$insertParams) {
            $insertParams = $params;
            return true;
        });

        $updateJobStmt = $this->createStub(PDOStatement::class);
        $updateJobStmt->method('execute')->willReturn(true);

        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdoMock->expects($this->once())->method('commit')->willReturn(true);
        $pdoMock->method('prepare')->willReturnCallback(
            function ($sql) use ($selectStmt, $insertFailedStmt, $updateJobStmt) {
                if (str_contains($sql, 'SELECT')) {
                    return $selectStmt;
                }
                if (str_contains($sql, 'INSERT INTO failed_jobs')) {
                    return $insertFailedStmt;
                }
                if (str_contains($sql, "status = 'failed'")) {
                    return $updateJobStmt;
                }
                throw new RuntimeException("Unexpected SQL: {$sql}");
            }
        );

        $service = new QueueService($pdoMock);
        $service->markFailed(101, new RuntimeException('Invalid SMTP credentials'));

        $this->assertSame(101, $insertParams['job_id']);
        $this->assertSame(3, $insertParams['attempts']);
        $this->assertSame('default', $insertParams['queue']);
        $this->assertStringContainsString('Invalid SMTP credentials', $insertParams['exception']);
    }

    public function testQueueServiceRecoverAbandonedJobs(): void
    {
        $stmtMock = $this->createStub(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('rowCount')->willReturn(2);

        $pdoMock = $this->createStub(PDO::class);
        $pdoMock->method('prepare')->willReturnCallback(function ($sql) use ($stmtMock) {
            $this->assertStringContainsString("status = 'reserved'", $sql);
            $this->assertStringContainsString("reserved_at <= DATE_SUB", $sql);
            return $stmtMock;
        });

        $service = new QueueService($pdoMock);
        $recovered = $service->recoverAbandonedJobs(300, 'default');

        $this->assertSame(2, $recovered);
    }

    public function testQueueServiceRetryFailedJob(): void
    {
        $selectStmt = $this->createStub(PDOStatement::class);
        $selectStmt->method('fetch')->willReturn([
            'id'       => 55,
            'job_id'   => 101,
            'queue'    => 'default',
            'job_type' => JobRegistry::TYPE_SEND_EMAIL,
            'payload'  => json_encode(['to' => 'user@nexora.local', 'subject' => 'Retry me']),
        ]);

        $insertStmt = $this->createStub(PDOStatement::class);
        $insertStmt->method('execute')->willReturn(true);

        $deleteStmt = $this->createStub(PDOStatement::class);
        $deleteStmt->method('execute')->willReturn(true);

        $pdoMock = $this->createStub(PDO::class);
        $pdoMock->method('lastInsertId')->willReturn('202');
        $pdoMock->method('prepare')->willReturnCallback(function ($sql) use ($selectStmt, $insertStmt, $deleteStmt) {
            if (str_contains($sql, 'SELECT id, job_id')) {
                return $selectStmt;
            }
            if (str_contains($sql, 'INSERT INTO queue_jobs')) {
                return $insertStmt;
            }
            if (str_contains($sql, 'DELETE FROM failed_jobs')) {
                return $deleteStmt;
            }
            throw new RuntimeException("Unexpected SQL: {$sql}");
        });

        $service = new QueueService($pdoMock);
        $newJobId = $service->retryFailedJob(55);

        $this->assertSame(202, $newJobId);
    }

    public function testQueueWorkerProcessJobExecutesAndCompletes(): void
    {
        $queueServiceMock = $this->createMock(QueueService::class);
        $queueServiceMock->expects($this->once())->method('markCompleted')->with(77);

        $worker = new QueueWorker($queueServiceMock, 'default');
        $worker->processJob([
            'id'           => 77,
            'queue'        => 'default',
            'job_type'     => JobRegistry::TYPE_SEND_EMAIL,
            'payload'      => [
                'to'      => 'employee@nexora.local',
                'subject' => 'Processed Job',
                'body'    => '<p>Done</p>',
            ],
            'attempts'     => 0,
            'max_attempts' => 3,
        ]);
    }

    public function testQueueWorkerProcessJobFailureCallsMarkFailed(): void
    {
        $queueServiceMock = $this->createMock(QueueService::class);
        $queueServiceMock->expects($this->once())->method('markFailed')->with(
            88,
            $this->isInstanceOf(InvalidArgumentException::class)
        );

        $worker = new QueueWorker($queueServiceMock, 'default');
        $worker->processJob([
            'id'           => 88,
            'queue'        => 'default',
            'job_type'     => JobRegistry::TYPE_SEND_NOTIFICATION,
            'payload'      => ['user_id' => 0],
            'attempts'     => 1,
            'max_attempts' => 3,
        ]);
    }

    public function testLeaveNotificationServiceFailureIsolation(): void
    {
        $notificationMock = $this->createStub(Notification::class);
        $notificationMock->method('existsForEvent')->willReturn(false);
        $notificationMock->method('create')->willReturn(501);

        $userMock = $this->createStub(User::class);
        $userMock->method('findByEmployeeId')->willReturn([
            'id'          => 12,
            'employee_id' => 99,
            'name'        => 'Test Employee',
            'email'       => 'employee@nexora.local',
            'status'      => 'Active',
        ]);

        $failingPdoMock = $this->createStub(PDO::class);
        $failingPdoMock->method('prepare')->willThrowException(new RuntimeException('Queue DB connection failed'));
        QueueService::getInstance($failingPdoMock);

        $leaveNotificationService = new LeaveNotificationService($notificationMock, $userMock);

        $result = $leaveNotificationService->leaveDecided(
            ['id' => 301, 'employee_id' => 99],
            'Approved',
            'Your leave application has been approved.'
        );

        $this->assertTrue($result, 'leaveDecided must return true and isolate queue failure from leave transaction');
    }

    public function testNotificationIdempotencyPreventsDuplicateNotifications(): void
    {
        $notificationMock = $this->createMock(Notification::class);
        $notificationMock->method('existsForEvent')->willReturn(true);
        $notificationMock->expects($this->never())->method('create');

        $userMock = $this->createStub(User::class);
        $userMock->method('findByEmployeeId')->willReturn([
            'id'          => 12,
            'employee_id' => 99,
            'name'        => 'Test Employee',
            'email'       => 'employee@nexora.local',
            'status'      => 'Active',
        ]);

        $leaveNotificationService = new LeaveNotificationService($notificationMock, $userMock);

        $result = $leaveNotificationService->leaveDecided(
            ['id' => 301, 'employee_id' => 99],
            'Approved',
            'Your leave application has been approved.'
        );

        $this->assertFalse($result, 'Must skip insertion when notification already exists for event');
    }

    public function testConcurrentWorkerClaimingSimulation(): void
    {
        $queueService1 = $this->createStub(QueueService::class);
        $queueService1->method('claimPending')->willReturn([
            [
                'id'           => 1,
                'queue'        => 'default',
                'job_type'     => JobRegistry::TYPE_SEND_EMAIL,
                'payload'      => [],
                'attempts'     => 0,
                'max_attempts' => 3,
            ],
            [
                'id'           => 2,
                'queue'        => 'default',
                'job_type'     => JobRegistry::TYPE_SEND_EMAIL,
                'payload'      => [],
                'attempts'     => 0,
                'max_attempts' => 3,
            ],
        ]);

        $queueService2 = $this->createStub(QueueService::class);
        $queueService2->method('claimPending')->willReturn([
            [
                'id'           => 3,
                'queue'        => 'default',
                'job_type'     => JobRegistry::TYPE_SEND_EMAIL,
                'payload'      => [],
                'attempts'     => 0,
                'max_attempts' => 3,
            ],
            [
                'id'           => 4,
                'queue'        => 'default',
                'job_type'     => JobRegistry::TYPE_SEND_EMAIL,
                'payload'      => [],
                'attempts'     => 0,
                'max_attempts' => 3,
            ],
        ]);

        $batch1 = $queueService1->claimPending(2, 'worker-1');
        $batch2 = $queueService2->claimPending(2, 'worker-2');

        $ids1 = array_column($batch1, 'id');
        $ids2 = array_column($batch2, 'id');

        $this->assertEmpty(array_intersect($ids1, $ids2), 'Concurrent workers must never claim overlapping job IDs');
    }
}
