<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\AccountSecurityController;
use App\Controllers\AdminController;
use App\Controllers\Api\AuthController as ApiAuthController;
use App\Controllers\AuthController;
use App\Models\EmailOtp;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserAuthSecurityState;
use App\Services\AuthRiskService;
use App\Services\AuthSecurityNotificationService;
use App\Services\MailService;
use App\Services\StepUpAuthService;
use App\Services\TrustedDeviceService;
use Core\Database;
use Core\Session;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * End-to-End Integration & Security Tests for Smart OTP Authentication
 * and Trusted Device Management on isolated `hris_test` database.
 */
class SmartOtpAuthenticationTest extends TestCase
{
    private ?PDO $pdo = null;
    private int $empUserId = 0;
    private int $hrUserId = 0;
    private string $empEmail = '';
    private string $hrEmail = '';
    private string $password = 'NexoraTest!2026';
    private int $activityLogHighWater = 0;
    private int $notifHighWater = 0;

    /** @var array<string, mixed> */
    private array $serverBackup = [];
    /** @var array<string, mixed> */
    private array $cookieBackup = [];
    /** @var array<string, mixed> */
    private array $postBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->cookieBackup = $_COOKIE;
        $this->postBackup = $_POST;

        Session::destroy();
        Session::start();

        if (!Database::getInstance()->isConnected()) {
            $this->markTestSkipped('Database connection is not available in this environment.');
        }

        $this->pdo = Database::getInstance()->getConnection();
        $this->activityLogHighWater = (int)$this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM activity_logs')->fetchColumn();
        $this->notifHighWater = (int)$this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM notifications')->fetchColumn();

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $suffix = bin2hex(random_bytes(4));
        $hash = password_hash($this->password, PASSWORD_BCRYPT, ['cost' => 10]);

        $this->empEmail = "smart.emp.{$suffix}@company.test";
        $this->hrEmail = "smart.hr.{$suffix}@company.test";

        $insert = $this->pdo->prepare("
            INSERT INTO users (username, email, password, role, status, created_at)
            VALUES (:username, :email, :password, :role, 'Active', NOW())
        ");

        $insert->execute([
            'username' => "smart_emp_{$suffix}",
            'email'    => $this->empEmail,
            'password' => $hash,
            'role'     => 'employee',
        ]);
        $this->empUserId = (int)$this->pdo->lastInsertId();

        $insert->execute([
            'username' => "smart_hr_{$suffix}",
            'email'    => $this->hrEmail,
            'password' => $hash,
            'role'     => 'hr',
        ]);
        $this->hrUserId = (int)$this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            foreach ([$this->empUserId, $this->hrUserId] as $uid) {
                if ($uid > 0) {
                    $this->pdo->prepare('DELETE FROM trusted_devices WHERE user_id = :u')->execute(['u' => $uid]);
                    $this->pdo->prepare('DELETE FROM user_auth_security_states WHERE user_id = :u')->execute(['u' => $uid]);
                    $this->pdo->prepare('DELETE FROM email_otps WHERE user_id = :u')->execute(['u' => $uid]);
                    $this->pdo->prepare('DELETE FROM users WHERE id = :u')->execute(['u' => $uid]);
                }
            }
            $this->pdo->prepare('DELETE FROM activity_logs WHERE id > :hw')->execute(['hw' => $this->activityLogHighWater]);
            $this->pdo->prepare('DELETE FROM notifications WHERE id > :hw')->execute(['hw' => $this->notifHighWater]);
        }

        $_SERVER = $this->serverBackup;
        $_COOKIE = $this->cookieBackup;
        $_POST = $this->postBackup;

        Session::destroy();
    }

    /**
     * Build a testable AuthController harness plus spy MailService tracking sent OTPs.
     *
     * @return array{controller:AuthController, sentOtps:array<int, string>, getRedirect:callable}
     */
    private function createHarness(): array
    {
        $sentOtps = [];
        $mailSpy = new class ($sentOtps) extends MailService {
            /** @var array<int, string> */
            private array $otps;

            /** @param array<int, string> $otps */
            public function __construct(array &$otps)
            {
                $this->otps = &$otps;
            }

            public function sendOtpEmail(string $recipientEmail, string $recipientName, string $otpCode, int $expiryMinutes = 5): bool
            {
                $this->otps[] = $otpCode;
                return true;
            }

            public function sendSecurityAlertEmail(string $recipientEmail, string $recipientName, string $subject, string $headline, string $bodyHtml, string $actionText): bool
            {
                return true;
            }
        };

        $notifier = new AuthSecurityNotificationService(null, $mailSpy);
        $risk = new AuthRiskService(new UserAuthSecurityState(), config('auth_devices', []));
        $tdService = new TrustedDeviceService(new TrustedDevice(), $risk, $notifier, new User(), config('auth_devices', []));

        $redirectBox = (object)['target' => null];
        $controller = new class (
            new User(),
            new EmailOtp(),
            $mailSpy,
            $tdService,
            $risk,
            $redirectBox
        ) extends AuthController {
            private object $redirectBox;

            public function __construct(
                User $u,
                EmailOtp $o,
                MailService $m,
                TrustedDeviceService $td,
                AuthRiskService $r,
                object $redirectBox
            ) {
                parent::__construct($u, $o, $m, $td, $r);
                $this->redirectBox = $redirectBox;
            }

            protected function redirect(string $url, int $statusCode = 302): void
            {
                $this->redirectBox->target = $url;
                throw new RuntimeException('TEST_REDIRECT:' . $url);
            }
        };

        return [
            'controller'  => $controller,
            'sentOtps'    => &$sentOtps,
            'getRedirect' => static fn(): ?string => $redirectBox->target,
        ];
    }

    private function createSecurityControllerHarness(): AccountSecurityController
    {
        $mailSpy = new class () extends MailService {
            public function sendSecurityAlertEmail(string $recipientEmail, string $recipientName, string $subject, string $headline, string $bodyHtml, string $actionText): bool
            {
                return true;
            }
        };
        $notifier = new AuthSecurityNotificationService(null, $mailSpy);
        $risk = new AuthRiskService(new UserAuthSecurityState(), config('auth_devices', []));
        $tdService = new TrustedDeviceService(new TrustedDevice(), $risk, $notifier, new User(), config('auth_devices', []));

        return new class (new User(), $tdService, $risk) extends AccountSecurityController {
            protected function redirect(string $url, int $statusCode = 302): void
            {
                throw new RuntimeException('TEST_REDIRECT:' . $url);
            }
        };
    }

    private function invokeAndCatchRedirect(callable $fn): string
    {
        try {
            $fn();
        } catch (RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'TEST_REDIRECT:')) {
                return substr($e->getMessage(), strlen('TEST_REDIRECT:'));
            }
            throw $e;
        }
        $this->fail('Expected controller action to trigger redirect.');
    }

    private function submitLogin(AuthController $controller, string $email, string $password): string
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            '_csrf_token' => csrf_token(),
            'email'       => $email,
            'password'    => $password,
        ];
        return $this->invokeAndCatchRedirect(static fn() => $controller->login());
    }

    private function submitVerifyOtp(AuthController $controller, string $otp, bool $rememberDevice): string
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            '_csrf_token' => csrf_token(),
            'otp'         => $otp,
        ];
        if ($rememberDevice) {
            $_POST['remember_device'] = '1';
        }
        return $this->invokeAndCatchRedirect(static fn() => $controller->verifyOtp());
    }

    private function performLogout(AuthController $controller): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_csrf_token' => csrf_token()];
        $this->invokeAndCatchRedirect(static fn() => $controller->logout());
    }

    public function test_untrusted_login_and_unchecked_remember_device_do_not_create_trusted_device(): void
    {
        unset($_COOKIE['nexora_td']);
        $harness = $this->createHarness();
        $ctrl = $harness['controller'];

        $r1 = $this->submitLogin($ctrl, $this->empEmail, $this->password);
        $this->assertSame('/verify-otp', $r1);
        $this->assertFalse(isAuthenticated(), 'Pending OTP state must never authenticate user.');
        $this->assertCount(1, $harness['sentOtps']);

        $r2 = $this->submitVerifyOtp($ctrl, $harness['sentOtps'][0], false);
        $this->assertSame('/dashboard', $r2);
        $this->assertTrue(isAuthenticated());
        $this->assertArrayNotHasKey('nexora_td', $_COOKIE);

        $rows = (new TrustedDevice())->listForUser($this->empUserId);
        $this->assertCount(0, $rows, 'Unchecked remember_device must NOT trust device.');

        // Next login still requires OTP
        $this->performLogout($ctrl);
        $r3 = $this->submitLogin($ctrl, $this->empEmail, $this->password);
        $this->assertSame('/verify-otp', $r3);
        $this->assertCount(2, $harness['sentOtps']);
    }

    public function test_trusted_device_flow_rotates_token_skips_otp_preserves_logout_and_blocks_wrong_password_and_replay(): void
    {
        unset($_COOKIE['nexora_td']);
        $harness = $this->createHarness();
        $ctrl = $harness['controller'];

        // 1. Untrusted login + remember_device checked
        $this->assertSame('/verify-otp', $this->submitLogin($ctrl, $this->empEmail, $this->password));
        $this->assertSame('/dashboard', $this->submitVerifyOtp($ctrl, $harness['sentOtps'][0], true));

        $initialCookie = $_COOKIE['nexora_td'] ?? '';
        $this->assertNotSame('', $initialCookie);
        $rows = (new TrustedDevice())->listForUser($this->empUserId);
        $this->assertCount(1, $rows);
        $this->assertSame(30, (int)$rows[0]['trust_days']);
        $fixedExpires = (string)$rows[0]['expires_at'];

        // 2. Ordinary logout preserves trusted-device cookie & DB record while clearing session
        $this->performLogout($ctrl);
        $this->assertFalse(isAuthenticated());
        $this->assertSame($initialCookie, $_COOKIE['nexora_td'] ?? '');

        // 3. Valid cookie + WRONG password must be REJECTED (never bypasses password!)
        $rWrongPw = $this->submitLogin($ctrl, $this->empEmail, 'WrongPassword999!');
        $this->assertSame('/login', $rWrongPw);
        $this->assertFalse(isAuthenticated());
        $this->assertCount(1, $harness['sentOtps'], 'Wrong password must fail early without sending OTP.');

        // 4. Valid cookie + RIGHT password -> skips OTP, establishes session directly, rotates validator, keeps fixed expiry
        $rTrusted = $this->submitLogin($ctrl, $this->empEmail, $this->password);
        $this->assertSame('/dashboard', $rTrusted);
        $this->assertTrue(isAuthenticated());
        $this->assertCount(1, $harness['sentOtps'], 'Trusted device login must NOT send a new OTP email.');

        $sessionUser = currentUser();
        $this->assertSame('trusted_device', $sessionUser['login_auth_method'] ?? null);
        $this->assertNull($sessionUser['last_mfa_verified_at'] ?? null, 'Trusted-device login must NOT grant recent-MFA bypass for StepUpAuth.');
        $this->assertFalse((new StepUpAuthService())->hasRecentVerification());

        $rotatedCookie = $_COOKIE['nexora_td'] ?? '';
        $this->assertNotSame('', $rotatedCookie);
        $this->assertNotSame($initialCookie, $rotatedCookie, 'Validator must rotate atomically after single use.');

        $rowsAfter = (new TrustedDevice())->listForUser($this->empUserId);
        $this->assertSame($fixedExpires, (string)$rowsAfter[0]['expires_at'], 'Fixed expiration date must not extend on login.');

        // 5. Replaying OLD pre-rotation cookie must fail closed, revoke device, flag step-up, and force OTP
        $this->performLogout($ctrl);
        $_COOKIE['nexora_td'] = $initialCookie;
        $rReplay = $this->submitLogin($ctrl, $this->empEmail, $this->password);
        $this->assertSame('/verify-otp', $rReplay);
        $this->assertFalse(isAuthenticated());
        $this->assertCount(2, $harness['sentOtps'], 'Replay attempt must require Email OTP.');
        $this->assertSame('validator_mismatch_suspected_replay', (new UserAuthSecurityState())->getRequiredStepUpReason($this->empUserId));

        // 6. Audit logs must never contain raw tokens or validators
        $stmt = $this->pdo->prepare('SELECT description FROM activity_logs WHERE user_id = :u');
        $stmt->execute(['u' => $this->empUserId]);
        $allDescriptions = implode("\n", $stmt->fetchAll(PDO::FETCH_COLUMN));
        [, $rawVal1] = explode('.', $initialCookie, 2);
        [, $rawVal2] = explode('.', $rotatedCookie, 2);
        $this->assertStringNotContainsString($rawVal1, $allDescriptions);
        $this->assertStringNotContainsString($rawVal2, $allDescriptions);
    }

    public function test_hr_fixed_7_day_expiration_and_expired_or_cross_user_cookie_fail_closed(): void
    {
        unset($_COOKIE['nexora_td']);
        $harness = $this->createHarness();
        $ctrl = $harness['controller'];

        // HR logs in and trusts browser -> 7 days fixed expiration
        $this->assertSame('/verify-otp', $this->submitLogin($ctrl, $this->hrEmail, $this->password));
        $this->assertSame('/dashboard', $this->submitVerifyOtp($ctrl, $harness['sentOtps'][0], true));
        $hrCookie = $_COOKIE['nexora_td'] ?? '';
        $hrRows = (new TrustedDevice())->listForUser($this->hrUserId);
        $this->assertSame(7, (int)$hrRows[0]['trust_days']);
        $hrDeviceId = (int)$hrRows[0]['id'];

        $this->performLogout($ctrl);

        // Cross-user cookie: Employee logs in carrying HR's cookie -> must NOT bypass OTP
        $_COOKIE['nexora_td'] = $hrCookie;
        $rCross = $this->submitLogin($ctrl, $this->empEmail, $this->password);
        $this->assertSame('/verify-otp', $rCross);
        $this->assertFalse(isAuthenticated());

        // Expired HR device -> forced back to OTP
        $this->pdo->prepare('UPDATE trusted_devices SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE id = :id')
            ->execute(['id' => $hrDeviceId]);
        $_COOKIE['nexora_td'] = $hrCookie;
        $rExpired = $this->submitLogin($ctrl, $this->hrEmail, $this->password);
        $this->assertSame('/verify-otp', $rExpired);
    }

    public function test_self_service_revocation_idor_protection_password_change_and_recovery_require_otp(): void
    {
        unset($_COOKIE['nexora_td']);
        $harness = $this->createHarness();
        $ctrl = $harness['controller'];
        $secCtrl = $this->createSecurityControllerHarness();

        // Register trusted device for Employee
        $this->submitLogin($ctrl, $this->empEmail, $this->password);
        $this->submitVerifyOtp($ctrl, $harness['sentOtps'][0], true);
        $empCookie = $_COOKIE['nexora_td'] ?? '';
        $empDeviceId = (int)((new TrustedDevice())->listForUser($this->empUserId)[0]['id']);
        $this->performLogout($ctrl);

        // Register trusted device for HR and keep HR authenticated
        unset($_COOKIE['nexora_td']);
        $this->submitLogin($ctrl, $this->hrEmail, $this->password);
        $this->submitVerifyOtp($ctrl, $harness['sentOtps'][1], true);

        // IDOR test: HR tries to revoke Employee's trusted device ID -> must NOT succeed!
        $_POST = ['_csrf_token' => csrf_token()];
        $this->invokeAndCatchRedirect(static fn() => $secCtrl->revokeDevice((string)$empDeviceId));
        $empRowStillActive = (new TrustedDevice())->findByIdForUser($empDeviceId, $this->empUserId);
        $this->assertNull($empRowStillActive['revoked_at'] ?? null, 'Cross-user IDOR revocation must be blocked.');

        // Recovery requires valid current_password (wrong password leaves HR device active)
        $_POST = ['_csrf_token' => csrf_token(), 'current_password' => 'WrongPassword!'];
        $rBadRec = $this->invokeAndCatchRedirect(static fn() => $secCtrl->recoverLostDevices());
        $this->assertSame('/account/security#recovery', $rBadRec);
        $hrDevicesBefore = (new TrustedDevice())->listForUser($this->hrUserId);
        $this->assertSame('active', $hrDevicesBefore[0]['device_status']);

        // Valid recovery revokes all HR devices and flags step-up
        $_POST = ['_csrf_token' => csrf_token(), 'current_password' => $this->password];
        $rGoodRec = $this->invokeAndCatchRedirect(static fn() => $secCtrl->recoverLostDevices());
        $this->assertSame('/account/security', $rGoodRec);
        $hrDevicesAfter = (new TrustedDevice())->listForUser($this->hrUserId);
        $this->assertSame('revoked', $hrDevicesAfter[0]['device_status']);
        $this->assertSame('trusted_device_recovery', (new UserAuthSecurityState())->getRequiredStepUpReason($this->hrUserId));

        // Risk-based step-up on Employee (e.g. email changed) triggers OTP even with valid active cookie,
        // and verifying OTP clears the pending step-up requirement.
        $this->performLogout($ctrl);
        (new AuthRiskService())->flagEmailChanged($this->empUserId);
        $_COOKIE['nexora_td'] = $empCookie;
        $this->assertSame('/verify-otp', $this->submitLogin($ctrl, $this->empEmail, $this->password));
        $this->assertSame('/dashboard', $this->submitVerifyOtp($ctrl, $harness['sentOtps'][2], false));
        $this->assertNull((new UserAuthSecurityState())->getRequiredStepUpReason($this->empUserId));
    }

    public function test_api_smart_otp_and_admin_password_change_revoke_trusted_devices(): void
    {
        unset($_COOKIE['nexora_td']);
        $sentOtps = [];
        $mailSpy = new class ($sentOtps) extends MailService {
            /** @var array<int, string> */
            private array $otps;

            /** @param array<int, string> $otps */
            public function __construct(array &$otps)
            {
                $this->otps = &$otps;
            }

            public function sendOtpEmail(
                string $recipientEmail,
                string $recipientName,
                string $otpCode,
                int $expiryMinutes = 5
            ): bool {
                $this->otps[] = $otpCode;
                return true;
            }

            public function sendSecurityAlertEmail(
                string $recipientEmail,
                string $recipientName,
                string $subject,
                string $headline,
                string $bodyHtml,
                string $actionText
            ): bool {
                return true;
            }
        };

        $notifier = new AuthSecurityNotificationService(null, $mailSpy);
        $risk = new AuthRiskService(new UserAuthSecurityState(), config('auth_devices', []));
        $tdService = new TrustedDeviceService(
            new TrustedDevice(),
            $risk,
            $notifier,
            new User(),
            config('auth_devices', [])
        );

        $jsonInput = (object)['data' => []];
        $apiResponse = (object)['payload' => null, 'status' => 0];
        $apiCtrl = new class (
            new User(),
            new EmailOtp(),
            $mailSpy,
            $tdService,
            $risk,
            $jsonInput,
            $apiResponse
        ) extends ApiAuthController {
            private object $jsonInputBox;
            private object $responseBox;

            public function __construct(
                User $u,
                EmailOtp $o,
                MailService $m,
                TrustedDeviceService $td,
                AuthRiskService $r,
                object $jsonInputBox,
                object $responseBox
            ) {
                parent::__construct($u, $o, $m, $td, $r);
                $this->jsonInputBox = $jsonInputBox;
                $this->responseBox = $responseBox;
            }

            protected function getJsonInput(): array
            {
                return $this->jsonInputBox->data;
            }

            protected function respond(mixed $data, int $statusCode = 200): void
            {
                $this->responseBox->payload = $data;
                $this->responseBox->status = $statusCode;
                throw new RuntimeException('TEST_API_RESPOND:' . $statusCode);
            }
        };

        $invokeApi = static function (callable $fn) use ($apiResponse): array {
            try {
                $fn();
            } catch (RuntimeException $e) {
                if (str_starts_with($e->getMessage(), 'TEST_API_RESPOND:')) {
                    return ['status' => $apiResponse->status, 'body' => $apiResponse->payload];
                }
                throw $e;
            }
            throw new RuntimeException('Expected API controller to call respond().');
        };

        // 1. API login without trusted cookie triggers OTP challenge
        $jsonInput->data = ['email' => $this->empEmail, 'password' => $this->password];
        $res1 = $invokeApi(static fn() => $apiCtrl->login());
        $this->assertSame(200, $res1['status']);
        $this->assertSame('otp_required', $res1['body']['data']['step']);
        $this->assertSame(30, $res1['body']['data']['remember_device_days']);
        $this->assertCount(1, $sentOtps);

        // 2. API verifyOtp with remember_device=true trusts device and sets cookie
        $jsonInput->data = ['otp' => $sentOtps[0], 'remember_device' => true];
        $res2 = $invokeApi(static fn() => $apiCtrl->verifyOtp());
        $this->assertSame(200, $res2['status']);
        $this->assertTrue($res2['body']['data']['device_trusted']);
        $this->assertSame(30, $res2['body']['data']['trust_days']);
        $firstCookie = $_COOKIE['nexora_td'] ?? '';
        $this->assertNotSame('', $firstCookie);

        // 3. API logout preserves trusted cookie; next API login authenticates directly with rotation
        $resOut = $invokeApi(static fn() => $apiCtrl->logout());
        $this->assertSame(200, $resOut['status']);
        $_COOKIE['nexora_td'] = $firstCookie;
        $jsonInput->data = ['email' => $this->empEmail, 'password' => $this->password];
        $res3 = $invokeApi(static fn() => $apiCtrl->login());
        $this->assertSame(200, $res3['status']);
        $this->assertSame('authenticated', $res3['body']['data']['step']);
        $this->assertSame('trusted_device', $res3['body']['data']['auth_method']);
        $this->assertCount(1, $sentOtps, 'API trusted-device login must not dispatch another OTP.');
        $rotatedCookie = $_COOKIE['nexora_td'] ?? '';
        $this->assertNotSame($firstCookie, $rotatedCookie);

        // 4. Password change via AdminController revokes trusted devices & requires OTP
        $adminCtrl = new class () extends AdminController {
            protected function redirect(string $url, int $statusCode = 302): void
            {
                throw new RuntimeException('TEST_REDIRECT:' . $url);
            }
        };

        $newPassword = 'NewNexoraPass!2026';
        $_POST = [
            '_csrf_token'      => csrf_token(),
            'email'            => $this->empEmail,
            'current_password' => $this->password,
            'new_password'     => $newPassword,
            'confirm_password' => $newPassword,
        ];
        $rAdmin = $this->invokeAndCatchRedirect(static fn() => $adminCtrl->updateAccount());
        $this->assertSame('/admin/account', $rAdmin);

        $devices = (new TrustedDevice())->listForUser($this->empUserId);
        $this->assertCount(1, $devices);
        $this->assertSame('revoked', $devices[0]['device_status']);
        $this->assertSame('password_changed', $devices[0]['revocation_reason']);
        $this->assertSame('password_changed', (new UserAuthSecurityState())->getRequiredStepUpReason($this->empUserId));

        // 5. Old trusted cookie cannot skip OTP after password change
        $invokeApi(static fn() => $apiCtrl->logout());
        $_COOKIE['nexora_td'] = $rotatedCookie;
        $jsonInput->data = ['email' => $this->empEmail, 'password' => $newPassword];
        $res4 = $invokeApi(static fn() => $apiCtrl->login());
        $this->assertSame('otp_required', $res4['body']['data']['step']);
        $this->assertCount(2, $sentOtps);
    }
}
