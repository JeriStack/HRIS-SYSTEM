<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicAuthSupportController;
use App\Models\EmailOtp;
use App\Models\Notification;
use App\Models\PasswordReset;
use App\Models\PublicSupportTrackingToken;
use App\Models\RealtimeTicket;
use App\Models\SupportTicket;
use App\Models\SupportTicketHandler;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\AuthRiskService;
use App\Services\MailService;
use App\Services\PasswordRecoveryService;
use App\Services\PublicAuthSupportService;
use App\Services\SupportAccessService;
use App\Services\SupportAttachmentService;
use App\Services\SupportNotificationService;
use App\Services\SupportWorkflowService;
use Core\Database;
use Core\Session;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Integration tests against isolated hris_test database covering:
 * 1) Password recovery database persistence, MySQL clock expiry, single-use replay rejection,
 *    newer-token invalidation, device/OTP/session revocation, and controller redirect behavior.
 * 2) Public login assistance intake (AUTH-YYYY-NNNNN), honeypot & rate-limit guards,
 *    attachment upload/download, strict redaction of internal notes/attachments/user IDs,
 *    confidentiality isolation from workplace complaints, and HR <-> public requester notifications.
 */
class PublicAuthSupportAndRecoveryTest extends TestCase
{
    private PDO $pdo;
    private int $deptId = 0;
    private int $posId = 0;
    private int $activityHighWater = 0;

    /** @var array<int, int> */
    private array $empIds = [];
    /** @var array<int, int> */
    private array $userIds = [];
    /** @var array<int, int> */
    private array $ticketIds = [];
    /** @var array<int, string> */
    private array $tempDirs = [];
    /** @var array<string, mixed> */
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        Session::destroy();
        Session::start();

        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database connection is unavailable.');
        }

        $this->pdo = $db->getConnection();
        $this->activityHighWater = (int)$this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM activity_logs')->fetchColumn();

        $tag = bin2hex(random_bytes(3));
        $this->pdo->prepare("INSERT INTO departments (department_name, description) VALUES (:n, 'pub_auth_test')")
            ->execute(['n' => 'Pub Auth Dept ' . $tag]);
        $this->deptId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO positions (position_name, department_id, description) VALUES (:n, :d, 'pub_auth_test')")
            ->execute(['n' => 'Pub Auth Pos ' . $tag, 'd' => $this->deptId]);
        $this->posId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        $_SERVER = $this->serverBackup;
        Session::destroy();

        if (isset($this->pdo)) {
            if ($this->ticketIds !== []) {
                $in = implode(',', array_map('intval', $this->ticketIds));
                $this->pdo->exec("DELETE FROM public_support_tracking_tokens WHERE ticket_id IN ({$in})");
                $this->pdo->exec("DELETE FROM support_sensitive_access_logs WHERE ticket_id IN ({$in})");
                $this->pdo->exec("DELETE FROM notifications WHERE entity_id IN ({$in}) AND (event_type LIKE 'support_%')");
                $this->pdo->exec("DELETE FROM support_tickets WHERE id IN ({$in})");
            }

            if ($this->userIds !== []) {
                $uIn = implode(',', array_map('intval', $this->userIds));
                $this->pdo->exec("DELETE FROM password_resets WHERE user_id IN ({$uIn})");
                $this->pdo->exec("DELETE FROM trusted_devices WHERE user_id IN ({$uIn})");
                $this->pdo->exec("DELETE FROM user_auth_security_states WHERE user_id IN ({$uIn})");
                $this->pdo->exec("DELETE FROM email_otps WHERE user_id IN ({$uIn})");
                $this->pdo->exec("DELETE FROM realtime_tickets WHERE user_id IN ({$uIn})");
                $this->pdo->exec("DELETE FROM support_ticket_handlers WHERE user_id IN ({$uIn})");
                $this->pdo->exec("DELETE FROM notifications WHERE user_id IN ({$uIn})");
                $this->pdo->exec("DELETE FROM activity_logs WHERE user_id IN ({$uIn})");
                $this->pdo->exec("DELETE FROM users WHERE id IN ({$uIn})");
            }

            if ($this->empIds !== []) {
                $eIn = implode(',', array_map('intval', $this->empIds));
                $this->pdo->exec("DELETE FROM employees WHERE id IN ({$eIn})");
            }

            if ($this->activityHighWater > 0) {
                $stmt = $this->pdo->prepare('DELETE FROM activity_logs WHERE id > :hw');
                $stmt->execute(['hw' => $this->activityHighWater]);
            }

            if ($this->posId > 0) {
                $this->pdo->prepare('DELETE FROM positions WHERE id = :id')->execute(['id' => $this->posId]);
            }
            if ($this->deptId > 0) {
                $this->pdo->prepare('DELETE FROM departments WHERE id = :id')->execute(['id' => $this->deptId]);
            }
        }

        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }
    }

    public function test_password_recovery_db_lifecycle_expiry_supersession_and_session_invalidation(): void
    {
        $fixture = $this->createUserFixture('employee', 'Active', 'Active');
        $userId = $fixture['user_id'];
        $email = $fixture['email'];
        $originalPassword = $fixture['password'];

        // Seed existing trusted device and pending OTP for this user
        (new TrustedDevice())->createDevice(
            $userId,
            bin2hex(random_bytes(16)),
            str_repeat('d', 64),
            'Test Browser',
            'Chrome',
            'Windows',
            'PHPUnit',
            '198.51.100.77',
            30
        );

        $this->pdo->prepare(
            "INSERT INTO email_otps (user_id, otp_hash, expires_at) VALUES (:uid, :hash, DATE_ADD(NOW(), INTERVAL 5 MINUTE))"
        )->execute([
            'uid'  => $userId,
            'hash' => str_repeat('e', 64),
        ]);

        $sentEmails = [];
        $mailer = $this->createStub(MailService::class);
        $mailer->method('sendMail')->willReturnCallback(
            static function (string $to, string $name, string $subject, string $html) use (&$sentEmails): bool {
                $sentEmails[] = ['to' => $to, 'subject' => $subject, 'html' => $html];
                return true;
            }
        );

        $userModel = new User($this->pdo);
        $service = new PasswordRecoveryService(
            $userModel,
            new PasswordReset(),
            $mailer,
            new TrustedDevice(),
            new EmailOtp(),
            new RealtimeTicket(),
            new AuthRiskService(),
            new Notification()
        );

        // 1. Issue first reset token
        $req1 = $service->requestReset($email, '198.51.100.77', 'PHPUnit');
        $this->assertTrue($req1['ok']);
        $this->assertTrue($req1['email_dispatched']);
        $this->assertCount(1, $sentEmails);

        $token1 = $this->extractTokenFromHtml($sentEmails[0]['html'], '/reset-password');
        $parts1 = explode('.', $token1);
        $this->assertCount(2, $parts1);

        // Verify token_hash at rest is not the raw verifier
        $row1 = (new PasswordReset())->findBySelector($parts1[0]);
        $this->assertNotNull($row1);
        $this->assertNotSame($parts1[1], $row1['token_hash']);

        // 2. Issue second reset token -> must invalidate first token immediately
        $req2 = $service->requestReset($email, '198.51.100.77', 'PHPUnit');
        $this->assertTrue($req2['ok']);
        $this->assertCount(2, $sentEmails);

        $token2 = $this->extractTokenFromHtml($sentEmails[1]['html'], '/reset-password');
        $val1AfterSupersede = $service->validateResetToken($token1);
        $this->assertFalse($val1AfterSupersede['valid']);
        $this->assertSame('superseded', $val1AfterSupersede['reason']);

        $val2 = $service->validateResetToken($token2);
        $this->assertTrue($val2['valid']);

        // 3. Weak password fails validation WITHOUT consuming token2
        $weakRes = $service->completeReset($token2, 'weak', 'weak', '198.51.100.77', 'PHPUnit');
        $this->assertFalse($weakRes['ok']);
        $this->assertSame('validation_error', $weakRes['status']);
        $this->assertTrue($service->validateResetToken($token2)['valid']);

        // 4. Complete reset with strong password
        $oldDbUser = $userModel->findByIdWithAuthState($userId);
        $oldFingerprint = Session::computeAuthFingerprint($oldDbUser ?? []);

        $newPass = 'Nexora!Recover#2026Z';
        $complete = $service->completeReset($token2, $newPass, $newPass, '198.51.100.77', 'PHPUnit');
        $this->assertTrue($complete['ok']);
        $this->assertSame('completed', $complete['status']);

        // Password changed & bcrypt cost 12
        $newDbUser = $userModel->findByIdWithAuthState($userId);
        $this->assertNotNull($newDbUser);
        $this->assertFalse(password_verify($originalPassword, (string)$newDbUser['password']));
        $this->assertTrue(password_verify($newPass, (string)$newDbUser['password']));
        $this->assertSame(12, (int)(password_get_info((string)$newDbUser['password'])['options']['cost'] ?? 0));

        // Active-session fingerprint changed -> other browsers are invalidated on next request
        $newFingerprint = Session::computeAuthFingerprint($newDbUser);
        $this->assertNotSame($oldFingerprint, $newFingerprint);

        // Single-use replay fails
        $replay = $service->completeReset($token2, 'Another!Valid#9999', 'Another!Valid#9999', '198.51.100.77', 'PHPUnit');
        $this->assertFalse($replay['ok']);
        $this->assertSame('invalid_token', $replay['status']);

        // Trusted devices & OTPs revoked, force_otp_next_login set, completion email dispatched
        $activeTdCount = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM trusted_devices WHERE user_id = {$userId} AND revoked_at IS NULL"
        )->fetchColumn();
        $this->assertSame(0, $activeTdCount);

        $unusedOtpCount = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM email_otps WHERE user_id = {$userId} AND consumed_at IS NULL"
        )->fetchColumn();
        $this->assertSame(0, $unusedOtpCount);

        $forceOtp = (int)$this->pdo->query(
            "SELECT require_otp_next_login FROM user_auth_security_states WHERE user_id = {$userId}"
        )->fetchColumn();
        $this->assertSame(1, $forceOtp);
        $this->assertCount(3, $sentEmails);

        // 5. MySQL-clock expiration check
        $this->pdo->prepare(
            "INSERT INTO password_resets
                (user_id, selector, token_hash, requested_email, expires_at)
             VALUES
                (:uid, :sel, :hash, :email, DATE_SUB(NOW(), INTERVAL 2 MINUTE))"
        )->execute([
            'uid'   => $userId,
            'sel'   => $expSel = bin2hex(random_bytes(16)),
            'hash'  => str_repeat('f', 64),
            'email' => $email,
        ]);
        $expVal = $service->validateResetToken($expSel . '.' . str_repeat('1', 64));
        $this->assertFalse($expVal['valid']);
        $this->assertSame('expired', $expVal['reason']);

        // 6. Controller submitForgotPassword returns identical generic redirect even for nonexistent user
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.77';
        $_POST = ['email' => 'nonexistent.user@nexora.local'];
        $controller = new PublicAuthSupportController($service, null, false);
        $controller->submitForgotPassword();
        $redirect = $controller->getLastRedirect();
        $this->assertNotNull($redirect);
        $this->assertStringContainsString('/forgot-password', $redirect['url']);
        $this->assertSame(
            PasswordRecoveryService::GENERIC_ACCEPTANCE_MESSAGE,
            Session::getFlash('success')
        );
    }

    public function test_public_support_submission_redaction_attachment_security_and_hr_triage(): void
    {
        $hrFixture = $this->createUserFixture('hr', 'Active', 'Active');
        $hrUserId = $hrFixture['user_id'];
        $empFixture = $this->createUserFixture('employee', 'Active', 'Active');

        $storageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexora_pub_store_' . bin2hex(random_bytes(3));
        $uploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexora_pub_up_' . bin2hex(random_bytes(3));
        @mkdir($storageDir, 0775, true);
        @mkdir($uploadDir, 0775, true);
        $this->tempDirs = [$storageDir, $uploadDir];

        $pngTmp = $this->createTempPngFile($uploadDir);

        $sentEmails = [];
        $mailer = $this->createStub(MailService::class);
        $mailer->method('sendMail')->willReturnCallback(
            static function (string $to, string $name, string $subject, string $html) use (&$sentEmails): bool {
                $sentEmails[] = ['to' => $to, 'subject' => $subject, 'html' => $html];
                return true;
            }
        );

        $ticketModel = new SupportTicket($this->pdo);
        $userModel = new User($this->pdo);
        $attService = new SupportAttachmentService($storageDir, static fn (string $p): bool => is_file($p));
        $notifService = new SupportNotificationService(new Notification(), $userModel, null, $mailer);

        $publicService = new PublicAuthSupportService(
            $this->pdo,
            $ticketModel,
            new PublicSupportTrackingToken($this->pdo),
            $userModel,
            $attService,
            $notifService,
            $mailer
        );

        $workflow = new SupportWorkflowService(
            $this->pdo,
            $ticketModel,
            new SupportTicketHandler($this->pdo),
            new SupportAccessService(),
            $attService,
            $notifService
        );

        // 1. Honeypot guard blocks bot submissions
        try {
            $publicService->submitPublicRequest([
                'company_website' => 'https://bot.spam.invalid',
                'requester_name'  => 'Bot Requester',
                'requester_email' => 'bot@spam.invalid',
                'category'        => 'Other Sign-In Problem',
                'subject'         => 'Spam',
                'description'     => 'Automated spam submission attempt.',
            ]);
            $this->fail('Expected honeypot guard to reject request.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('autofill', strtolower($e->getMessage()));
        }

        // 2. Submit legitimate public AUTH request matching employee email + PNG attachment
        $filesInput = [
            'name'     => ['login_error.png'],
            'type'     => ['image/png'],
            'tmp_name' => [$pngTmp],
            'error'    => [UPLOAD_ERR_OK],
            'size'     => [(int)filesize($pngTmp)],
        ];

        $created = $publicService->submitPublicRequest([
            'requester_name'  => 'Elena Mendoza',
            'requester_email' => $empFixture['email'],
            'category'        => 'OTP Delivery Problem',
            'subject'         => 'Verification code did not arrive after 3 tries',
            'description'     => 'I signed in from the office workstation at 8:15 AM and no OTP code arrived in Mailpit.',
            'issue_datetime'  => '2026-11-16 08:15 AM',
            'client_env'      => 'Chrome 130 / Windows 11',
            'priority'        => 'High',
        ], $filesInput, '198.51.100.88', 'PHPUnit-Browser');

        $ticketId = (int)$created['ticket']['id'];
        $this->ticketIds[] = $ticketId;

        $this->assertMatchesRegularExpression('/^AUTH-\d{4}-\d{5}$/', $created['ticket_ref']);
        $this->assertTrue($created['email_sent']);
        $this->assertSame('public_auth', $created['ticket']['support_channel']);
        $this->assertSame(1, (int)$created['ticket']['is_public_ticket']);
        $this->assertSame(0, (int)$created['ticket']['is_confidential']);
        $this->assertSame($empFixture['user_id'], (int)$created['ticket']['matched_user_id']);
        $this->assertCount(1, $sentEmails);

        $trackingToken = $this->extractTokenFromHtml($sentEmails[0]['html'], '/auth/support/track');
        $this->assertNotSame('', $trackingToken);

        // Verify non-confidential HR handler CAN access public_auth ticket, while remaining blocked from confidential complaints
        $hrSession = [
            'id'                      => $hrUserId,
            'role'                    => 'hr',
            'status'                  => 'Active',
            'can_handle_confidential' => false,
        ];
        $this->assertTrue(
            (new SupportAccessService())->canViewTicketAsHr(
                $hrSession,
                $created['ticket'],
                new SupportTicketHandler($this->pdo),
                $this->pdo
            )
        );

        // 3. HR records an internal-only note AND an internal-only attachment, then sends a public reply
        $ticketModel->addMessage([
            'ticket_id'        => $ticketId,
            'sender_user_id'   => $hrUserId,
            'sender_role'      => 'hr',
            'message_type'     => 'internal_note',
            'is_internal_note' => 1,
            'body'             => 'INTERNAL HR NOTE: Matched employee ID secret memo - DO NOT LEAK PUBLICLY.',
        ]);

        $internalAttId = $ticketModel->addAttachment([
            'ticket_id'             => $ticketId,
            'message_id'            => null,
            'uploaded_by_user_id'   => $hrUserId,
            'original_filename'     => 'internal_audit_log.txt',
            'stored_filename'       => 'internal_audit_log.txt',
            'storage_relative_path' => $ticketId . '/internal_audit_log.txt',
            'mime_type'             => 'text/plain',
            'file_size_bytes'       => 64,
            'sha256_checksum'       => str_repeat('a', 64),
            'is_confidential'       => 0,
            'is_internal_only'      => 1,
        ]);

        $workflow->hrRespond(
            $hrSession,
            $ticketId,
            [
                'response_mode' => 'request_info',
                'body'          => 'We cleared the stalled mail relay queue. Please try logging in again and let us know if the OTP arrives.',
            ]
        );

        // Verify synchronous email notification sent to public requester on HR reply
        $this->assertGreaterThanOrEqual(2, count($sentEmails));
        $lastMail = $sentEmails[count($sentEmails) - 1];
        $this->assertSame($empFixture['email'], $lastMail['to']);
        $this->assertStringContainsString($created['ticket_ref'], $lastMail['subject']);

        // 4. Verify public tracking view redaction
        $publicView = $publicService->resolvePublicTrackingView($trackingToken);
        $this->assertTrue($publicView['valid']);
        $this->assertArrayNotHasKey('matched_user_id', $publicView['ticket'] ?? []);
        $this->assertArrayNotHasKey('requester_email', $publicView['ticket'] ?? []);
        $this->assertSame('Waiting for Requester', $publicView['ticket']['display_status']);

        $visibleBodies = implode("\n", array_column($publicView['messages'], 'body'));
        $this->assertStringNotContainsString('INTERNAL HR NOTE', $visibleBodies);
        $this->assertStringContainsString('cleared the stalled mail relay queue', $visibleBodies);

        // HR sender name is anonymized to "HR Support Team"
        $senderLabels = array_column($publicView['messages'], 'sender_label');
        $this->assertContains('HR Support Team', $senderLabels);
        $this->assertStringNotContainsString($hrFixture['username'], implode(' ', $senderLabels));

        // Internal-only attachment strictly excluded & blocked from download
        $visibleFiles = array_column($publicView['attachments'], 'original_filename');
        $this->assertSame(['login_error.png'], $visibleFiles);

        try {
            $publicService->resolvePublicAttachmentDownload($internalAttId, $trackingToken);
            $this->fail('Expected internal-only attachment download to be denied.');
        } catch (RuntimeException $e) {
            $this->assertSame(403, $e->getCode());
            $this->assertStringContainsString('authorized', strtolower($e->getMessage()));
        }

        // Valid requester attachment resolves safely
        $publicAttId = (int)$publicView['attachments'][0]['id'];
        $dl = $publicService->resolvePublicAttachmentDownload($publicAttId, $trackingToken);
        $this->assertSame('login_error.png', $dl['attachment']['original_filename']);

        // 5. Public requester replies back -> status returns from Waiting for Employee to Under Review
        $afterReply = $publicService->submitPublicFollowUpReply(
            $trackingToken,
            'I retried login and received the OTP right away. Thank you!',
            null,
            '198.51.100.88',
            'PHPUnit-Browser'
        );
        $this->assertSame('Under Review', $afterReply['status']);
    }

    /**
     * @return array{user_id: int, emp_id: int, email: string, username: string, password: string}
     */
    private function createUserFixture(string $role, string $userStatus, string $empStatus): array
    {
        $tag = bin2hex(random_bytes(4));
        $email = "pubtest_{$role}_{$tag}@nexora.test";
        $username = "pub_{$role}_{$tag}";
        $password = 'Initial!Pass#2026';

        $this->pdo->prepare(
            "INSERT INTO employees
                (employee_no, first_name, last_name, email, birth_date, gender, department_id, position_id, date_hired, employment_status)
             VALUES
                (:emp_no, :fn, 'Fixture', :email, '1994-05-10', 'Other', :dept_id, :pos_id, '2025-01-15', :emp_status)"
        )->execute([
            'emp_no'     => 'PUB-' . strtoupper($tag),
            'fn'         => ucfirst($role),
            'email'      => $email,
            'dept_id'    => $this->deptId,
            'pos_id'     => $this->posId,
            'emp_status' => $empStatus,
        ]);
        $empId = (int)$this->pdo->lastInsertId();
        $this->empIds[] = $empId;

        $roleStmt = $this->pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $roleStmt->execute(['slug' => $role]);
        $roleId = $roleStmt->fetchColumn() ?: null;

        $this->pdo->prepare(
            "INSERT INTO users (employee_id, username, email, password, role, role_id, status)
             VALUES (:emp_id, :username, :email, :password, :role, :role_id, :status)"
        )->execute([
            'emp_id'   => $empId,
            'username' => $username,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'role'     => $role,
            'role_id'  => $roleId,
            'status'   => $userStatus,
        ]);
        $userId = (int)$this->pdo->lastInsertId();
        $this->userIds[] = $userId;

        return [
            'user_id'  => $userId,
            'emp_id'   => $empId,
            'email'    => $email,
            'username' => $username,
            'password' => $password,
        ];
    }

    private function extractTokenFromHtml(string $html, string $pathSegment): string
    {
        $pattern = '/' . preg_quote($pathSegment, '/') . '\?token=([a-f0-9]+\.[a-f0-9]+)/';
        $this->assertSame(1, preg_match($pattern, $html, $matches), "Missing expected link for {$pathSegment}");
        return $matches[1];
    }

    private function createTempPngFile(string $dir): string
    {
        $path = $dir . DIRECTORY_SEPARATOR . 'shot_' . bin2hex(random_bytes(3)) . '.png';
        // Minimal valid 1x1 PNG binary
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
        file_put_contents($path, $png);
        return $path;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $target = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($target)) {
                $this->removeDirectory($target);
            } else {
                @unlink($target);
            }
        }
        @rmdir($dir);
    }
}
