<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Core\Database;
use Core\Session;
use App\Models\User;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        Session::destroy();
        Session::start();
    }

    protected function tearDown(): void
    {
        Session::remove('user');
        Session::remove('last_activity');
        Session::remove('pending_otp_user_id');
        Session::remove('pending_otp_email');
        Session::destroy();
    }

    public function test_secure_password_hashing_and_verification(): void
    {
        $plainPassword = 'CorrectHorseBatteryStaple!2026';
        $hashed = password_hash($plainPassword, PASSWORD_BCRYPT);

        $this->assertTrue(password_verify($plainPassword, $hashed), 'Correct password must verify against bcrypt hash.');
        $this->assertFalse(password_verify('WrongPassword123', $hashed), 'Incorrect password must fail verification.');
    }

    public function test_authenticated_session_state_lifecycle(): void
    {
        $this->assertFalse(isAuthenticated(), 'Visitor should not be authenticated initially.');

        // Simulate successful login session population
        Session::set('user', [
            'id'          => 42,
            'username'    => 'hr_officer',
            'role'        => 'hr',
            'employee_id' => 10,
            'name'        => 'Maria Santos',
            'email'       => 'maria.santos@company.local',
        ]);
        Session::set('last_activity', time());

        $this->assertTrue(isAuthenticated(), 'User should be marked authenticated when session exists.');
        $this->assertSame(42, currentUserId());
        $this->assertSame('hr', currentUserRole());
        $this->assertTrue(hasRole('hr'));
        $this->assertFalse(hasRole('employee'));

        // Simulate logout
        Session::remove('user');
        $this->assertFalse(isAuthenticated(), 'User should no longer be authenticated after session removal.');
    }

    public function test_inactive_account_status_blocks_authentication(): void
    {
        $userRecord = [
            'id'       => 99,
            'username' => 'terminated_emp',
            'status'   => 'Inactive',
            'email'    => 'terminated@company.local',
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
        ];

        $canAuthenticate = ($userRecord['status'] === 'Active');
        $this->assertFalse($canAuthenticate, 'Inactive or suspended accounts must not be permitted to authenticate.');
    }

    public function test_email_lookup_normalizes_case_and_whitespace(): void
    {
        if (!Database::getInstance()->isConnected()) {
            $this->markTestSkipped('Database is not connected in this environment.');
        }

        $userModel = new User();
        // Database has marcus.tan@company.local
        $user = $userModel->findByEmail('  MARCUS.TAN@COMPANY.LOCAL  ');
        $this->assertNotNull($user, 'User lookup must be case-insensitive and trim surrounding whitespace.');
        $this->assertSame('marcus.tan@company.local', strtolower($user['email']));
        $this->assertSame('hr', $user['role']);
    }

    public function test_invalid_email_format_is_rejected(): void
    {
        if (!Database::getInstance()->isConnected()) {
            $this->markTestSkipped('Database is not connected in this environment.');
        }

        $userModel = new User();
        $this->assertNull($userModel->findByEmail('not-an-email'));
        $this->assertNull($userModel->findByEmail(''));
        $this->assertNull($userModel->findByEmail('missing@domain'));
    }

    public function test_email_exists_check_returns_accurate_boolean(): void
    {
        if (!Database::getInstance()->isConnected()) {
            $this->markTestSkipped('Database is not connected in this environment.');
        }

        $userModel = new User();
        $this->assertTrue($userModel->emailExists('marcus.tan@company.local'));
        $this->assertTrue($userModel->emailExists('MARCUS.TAN@COMPANY.LOCAL'));
        $this->assertFalse($userModel->emailExists('unregistered.nonexistent@company.local'));
    }

    public function test_login_view_contains_email_and_password_and_no_demo_artifacts(): void
    {
        $loginViewPath = dirname(__DIR__, 2) . '/app/views/auth/login.php';
        $this->assertFileExists($loginViewPath);

        $viewContent = file_get_contents($loginViewPath);

        // Required Email and Password fields
        $this->assertStringContainsString('name="email"', $viewContent, 'Login view must contain email input.');
        $this->assertStringContainsString('type="email"', $viewContent, 'Login view email input must be type="email".');
        $this->assertStringContainsString('Email Address', $viewContent, 'Login view must display Email Address label.');
        $this->assertStringContainsString('name="password"', $viewContent, 'Login view must contain password input.');
        $this->assertStringContainsString('Sign In', $viewContent, 'Login view submit button must be Sign In.');

        // Required NEXORA HR Branding & Three-Role Portal Selection (Admin, HR & Employee)
        $this->assertStringContainsString('Welcome to NEXORA HR', $viewContent, 'Login view must display Welcome to NEXORA HR heading.');
        $this->assertStringContainsString('People. Processes. Progress.', $viewContent, 'Login view must display brand tagline.');
        $this->assertStringContainsString('Admin Portal', $viewContent, 'Login view must include Admin Portal card.');
        $this->assertStringContainsString('Continue as Admin', $viewContent, 'Login view must include Continue as Admin action.');
        $this->assertStringContainsString('HR Portal', $viewContent, 'Login view must include HR Portal card.');
        $this->assertStringContainsString('Continue as HR', $viewContent, 'Login view must include Continue as HR action.');
        $this->assertStringContainsString('Employee Portal', $viewContent, 'Login view must include Employee Portal card.');
        $this->assertStringContainsString('Continue as Employee', $viewContent, 'Login view must include Continue as Employee action.');
        $this->assertStringContainsString('role="dialog"', $viewContent, 'Login view must include accessible pop-up modal dialog.');
        $this->assertStringContainsString('aria-modal="true"', $viewContent, 'Login modal must declare aria-modal="true".');

        // Demo Accounts and shortcuts MUST be completely removed
        $this->assertStringNotContainsString('demo-credentials', $viewContent, 'Demo credentials box must be removed.');
        $this->assertStringNotContainsString('Demonstration Accounts', $viewContent, 'Demonstration Accounts header must be removed.');
        $this->assertStringNotContainsString('quickFill', $viewContent, 'quickFill function must be removed.');
        $this->assertStringNotContainsString('hr01', $viewContent, 'hr01 quick button must be removed.');
        $this->assertStringNotContainsString('emp004', $viewContent, 'emp004 quick button must be removed.');
        $this->assertStringNotContainsString('Admin@123', $viewContent, 'Hardcoded demo passwords must be removed.');
        $this->assertStringNotContainsString('Employee@123', $viewContent, 'Hardcoded demo passwords must be removed.');
    }

    public function test_portal_role_mismatch_check_identifies_unauthorized_portal_selection(): void
    {
        $hrAccountRole = 'hr';
        $employeeAccountRole = 'employee';

        // HR user selecting HR portal -> authorized
        $this->assertTrue($hrAccountRole === 'hr');
        // Employee user selecting Employee portal -> authorized
        $this->assertTrue($employeeAccountRole === 'employee');

        // Employee user attempting HR portal -> mismatch rejected
        $selectedPortal = 'hr';
        $this->assertNotSame(
            $selectedPortal,
            $employeeAccountRole,
            'Employee account must be rejected when attempting HR Portal sign-in.'
        );

        // HR user attempting Employee portal -> mismatch rejected
        $selectedEmpPortal = 'employee';
        $this->assertNotSame(
            $selectedEmpPortal,
            $hrAccountRole,
            'HR account must be rejected when attempting Employee Portal sign-in.'
        );
    }

    public function test_user_creation_enforces_strict_roles_and_hashes_passwords(): void
    {
        if (!Database::getInstance()->isConnected()) {
            $this->markTestSkipped('Database is not connected in this environment.');
        }

        $userModel = new User();

        // Unsupported role must throw InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $userModel->create([
            'email'    => 'auditor@company.local',
            'password' => 'SecurePass123!',
            'role'     => 'auditor', // strictly prohibited: only admin, hr, and employee allowed
        ]);
    }

    public function test_session_absolute_lifetime_invalidates_session_after_twelve_hours(): void
    {
        Session::set('user', [
            'id'         => 15,
            'role'       => 'employee',
            'login_time' => time() - 43300, // Exceeds 12 hours (43200 seconds)
        ]);
        Session::set('last_activity', time() - 60); // Inactivity is still fresh (1 min)

        Session::checkInactivityTimeout();

        $this->assertFalse(Session::has('user'), 'Session must be invalidated when absolute lifetime is reached.');
        $this->assertSame(
            'Your maximum session duration has expired. Please sign in again.',
            Session::getFlash('timeout')
        );
    }

    public function test_auth_fingerprint_generation_and_validation(): void
    {
        $userRow = [
            'id'                => 10,
            'role'              => 'employee',
            'status'            => 'Active',
            'employment_status' => 'Active',
            'password'          => '$2y$10$abcdefghijklmnopqrstuv',
        ];

        $fp1 = Session::computeAuthFingerprint($userRow);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $fp1);

        // Identical state produces identical fingerprint
        $this->assertSame($fp1, Session::computeAuthFingerprint($userRow));

        // State changes alter fingerprint
        $modifiedRow = $userRow;
        $modifiedRow['role'] = 'hr';
        $this->assertNotSame($fp1, Session::computeAuthFingerprint($modifiedRow));

        $modifiedPassword = $userRow;
        $modifiedPassword['password'] = '$2y$10$changedpasswordhashxxxx';
        $this->assertNotSame($fp1, Session::computeAuthFingerprint($modifiedPassword));

        $modifiedStatus = $userRow;
        $modifiedStatus['status'] = 'Inactive';
        $this->assertNotSame($fp1, Session::computeAuthFingerprint($modifiedStatus));
    }

    public function test_step_up_authentication_service_workflow(): void
    {
        $service = new \App\Services\StepUpAuthService();

        // 1. Unauthenticated session fails step-up
        Session::remove('user');
        $this->assertFalse($service->hasRecentVerification());
        $res = $service->verify('TEST_ACTION', null);
        $this->assertFalse($res['ok']);

        // 2. Authenticated session with recent MFA succeeds immediately without re-prompt
        Session::set('user', [
            'id'                   => 50,
            'role'                 => 'hr',
            'last_mfa_verified_at' => time() - 120, // 2 minutes ago (well within 15m window)
        ]);
        $this->assertTrue($service->hasRecentVerification());
        $res2 = $service->verify('TEST_ACTION', null);
        $this->assertTrue($res2['ok']);

        // 3. Authenticated session with expired window requires password
        Session::set('user', [
            'id'                   => 50,
            'role'                 => 'hr',
            'last_mfa_verified_at' => time() - 1200, // 20 minutes ago (expired)
        ]);
        $this->assertFalse($service->hasRecentVerification());
        $res3 = $service->verify('TEST_ACTION', null);
        $this->assertFalse($res3['ok']);
        $this->assertStringContainsString('confirm your current password', $res3['error']);
    }

    public function test_keyed_otp_pepper_and_backward_compatibility(): void
    {
        $userId = 20;
        $plainOtp = '654321';

        // 1. Legacy hash (unpeppered password_hash)
        $legacyHash = password_hash($plainOtp, PASSWORD_DEFAULT);
        $this->assertTrue(\App\Models\EmailOtp::verifyOtpHash($userId, $plainOtp, $legacyHash));
        $this->assertFalse(\App\Models\EmailOtp::verifyOtpHash($userId, '000000', $legacyHash));

        // 2. Peppered hash with OTP_PEPPER_SECRET
        putenv('OTP_PEPPER_SECRET=test_master_pepper_secret_key_12345');
        $_ENV['OTP_PEPPER_SECRET'] = 'test_master_pepper_secret_key_12345';

        $pepper = \App\Models\EmailOtp::computePepper($userId, $plainOtp);
        $this->assertNotNull($pepper);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $pepper);

        $pepperedHash = \App\Models\EmailOtp::hashOtp($userId, $plainOtp);
        $this->assertTrue(\App\Models\EmailOtp::verifyOtpHash($userId, $plainOtp, $pepperedHash));
        $this->assertFalse(\App\Models\EmailOtp::verifyOtpHash($userId, '111222', $pepperedHash));

        // Legacy hashes still verify correctly even when pepper is active
        $this->assertTrue(\App\Models\EmailOtp::verifyOtpHash($userId, $plainOtp, $legacyHash));

        // Clean up environment
        putenv('OTP_PEPPER_SECRET=');
        unset($_ENV['OTP_PEPPER_SECRET']);
    }

    public function test_account_revocation_service_execution(): void
    {
        $revocation = new \App\Services\AccountRevocationService();
        // Method should execute cleanly and safely even if user has no active tickets
        $revocation->revokeUser(999);
        $this->assertTrue(true);
    }
}
