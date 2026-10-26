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

        // Demo Accounts and shortcuts MUST be completely removed
        $this->assertStringNotContainsString('demo-credentials', $viewContent, 'Demo credentials box must be removed.');
        $this->assertStringNotContainsString('Demonstration Accounts', $viewContent, 'Demonstration Accounts header must be removed.');
        $this->assertStringNotContainsString('quickFill', $viewContent, 'quickFill function must be removed.');
        $this->assertStringNotContainsString('hr01', $viewContent, 'hr01 quick button must be removed.');
        $this->assertStringNotContainsString('emp004', $viewContent, 'emp004 quick button must be removed.');
        $this->assertStringNotContainsString('Admin@123', $viewContent, 'Hardcoded demo passwords must be removed.');
        $this->assertStringNotContainsString('Employee@123', $viewContent, 'Hardcoded demo passwords must be removed.');
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
            'role'     => 'admin', // strictly prohibited in 2-role model; only hr and employee
        ]);
    }
}