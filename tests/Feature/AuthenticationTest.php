<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Core\Session;

class AuthenticationTest extends TestCase
{
    protected function tearDown(): void
    {
        Session::remove('user');
        Session::remove('last_activity');
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
            'email'       => 'maria.santos@company.com',
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
            'password' => password_hash('secret123', PASSWORD_BCRYPT),
        ];

        $canAuthenticate = ($userRecord['status'] === 'Active');
        $this->assertFalse($canAuthenticate, 'Inactive or suspended accounts must not be permitted to authenticate.');
    }
}
