<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Core\Session;
use Core\Permission;
use App\Models\EmailOtp;
use App\Models\User;

class OtpAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        Session::destroy();
        Session::start();
    }

    protected function tearDown(): void
    {
        Session::destroy();
    }

    public function test_correct_credentials_alone_do_not_authenticate_user(): void
    {
        // Simulate Stage 1: Password verified, pending challenge created
        Session::set('pending_otp_user_id', 4);
        Session::set('pending_otp_email', 'david.cruz@company.local');
        Session::set('pending_otp_name', 'David Cruz');
        Session::set('pending_otp_time', time());

        // Crucial security invariant: User MUST NOT be authenticated yet
        $this->assertFalse(isAuthenticated(), 'User with pending OTP must NOT be marked authenticated.');
        $this->assertNull(currentUser(), 'currentUser() must return null during pending OTP challenge.');
        $this->assertNull(currentUserId(), 'currentUserId() must return null during pending OTP challenge.');
        $this->assertNull(currentUserRole(), 'currentUserRole() must return null during pending OTP challenge.');
    }

    public function test_pending_otp_user_is_treated_as_unauthenticated(): void
    {
        Session::set('pending_otp_user_id', 4);
        Session::set('pending_otp_email', 'david.cruz@company.local');

        $this->assertFalse(isAuthenticated());
        $this->assertFalse(hasRole('hr'));
        $this->assertFalse(hasRole('employee'));
        $this->assertNull(currentUserRole());
    }

    public function test_successful_otp_verification_establishes_authenticated_session(): void
    {
        // 1. Setup pending challenge state
        Session::set('pending_otp_user_id', 2);
        Session::set('pending_otp_email', 'marcus.tan@company.local');

        // 2. Simulate successful OTP verification completing
        Session::remove('pending_otp_user_id');
        Session::remove('pending_otp_email');

        Session::set('user', [
            'id'          => 2,
            'username'    => 'hr01',
            'role'        => 'hr',
            'employee_id' => 2,
            'name'        => 'Marcus Tan',
            'email'       => 'marcus.tan@company.local',
            'department'  => 'Human Resources',
            'position'    => 'HR Director',
            'login_time'  => time(),
        ]);
        Session::set('last_activity', time());

        // 3. User is now authenticated
        $this->assertTrue(isAuthenticated(), 'User must be authenticated after completing OTP verification.');
        $this->assertSame(2, currentUserId());
        $this->assertSame('hr', currentUserRole());
        $this->assertTrue(hasRole('hr'));
        $this->assertFalse(hasRole('employee'));
        $this->assertFalse(Session::has('pending_otp_user_id'), 'Pending challenge data must be cleared.');
    }

    public function test_strict_role_based_permissions_for_hr_and_employee_only(): void
    {
        // Employee role validation
        Session::set('user', [
            'id'          => 4,
            'username'    => 'emp004',
            'role'        => 'employee',
            'employee_id' => 4,
            'name'        => 'David Cruz',
            'email'       => 'david.cruz@company.local',
        ]);
        Session::set('last_activity', time());

        $this->assertSame('employee', currentUserRole());
        $this->assertTrue(hasRole('employee'));
        $this->assertFalse(hasRole('hr'));

        // Employee MUST NOT have HR permissions
        $this->assertFalse(Permission::can('employee', 'manage_employees'));
        $this->assertFalse(Permission::can('employee', 'manage_departments'));
        $this->assertFalse(Permission::can('employee', 'review_leave_requests'));

        // Employee MUST have self-service permissions
        $this->assertTrue(Permission::can('employee', 'file_leave_requests'));
        $this->assertTrue(Permission::can('employee', 'view_own_profile'));

        // HR role validation
        Session::set('user', [
            'id'          => 2,
            'username'    => 'hr01',
            'role'        => 'hr',
            'employee_id' => 2,
            'name'        => 'Marcus Tan',
            'email'       => 'marcus.tan@company.local',
        ]);

        $this->assertSame('hr', currentUserRole());
        $this->assertTrue(hasRole('hr'));
        $this->assertFalse(hasRole('employee'));

        // HR MUST have management permissions
        $this->assertTrue(Permission::can('hr', 'manage_employees'));
        $this->assertTrue(Permission::can('hr', 'manage_departments'));
        $this->assertTrue(Permission::can('hr', 'review_leave_requests'));
    }

    public function test_unsupported_or_legacy_role_is_not_granted_dashboard_access(): void
    {
        Session::set('user', [
            'id'       => 999,
            'username' => 'guest_role',
            'role'     => 'auditor', // Unsupported role
        ]);
        Session::set('last_activity', time());

        $this->assertFalse(hasRole('hr'), 'Arbitrary or unsupported role must not have HR privileges.');
        $this->assertFalse(hasRole('employee'), 'Arbitrary or unsupported role must not have Employee privileges.');
        $this->assertFalse(Permission::can('auditor', 'manage_employees'));
        $this->assertFalse(Permission::can('auditor', 'view_own_profile'));
    }

    public function test_otp_challenge_hashes_stored_code_and_prevents_plaintext_exposure(): void
    {
        $otpModel = new EmailOtp();
        $plainOtp = '654321';
        $testUserId = 3; // Sofia Reyes

        $challengeId = $otpModel->createChallenge($testUserId, $plainOtp);
        $this->assertGreaterThan(0, $challengeId);

        // Verify stored challenge has a bcrypt/argon2 hash and not plaintext
        $db = \Core\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT otp_hash FROM email_otps WHERE id = :id");
        $stmt->execute(['id' => $challengeId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($row);
        $this->assertNotSame($plainOtp, $row['otp_hash']);
        $this->assertTrue(password_verify($plainOtp, $row['otp_hash']), 'Stored hash must verify against password_verify.');

        // Clean up
        $otpModel->invalidateUserChallenges($testUserId);
    }

    public function test_otp_exhaustion_after_five_failed_attempts(): void
    {
        $otpModel = new EmailOtp();
        $testUserId = 3;
        $plainOtp = '888999';

        $otpModel->createChallenge($testUserId, $plainOtp);

        // 4 failed attempts: each decrements attempts
        for ($i = 1; $i <= 4; $i++) {
            $res = $otpModel->verifyOtp($testUserId, '000000');
            $this->assertFalse($res['success']);
            $this->assertFalse($res['exhausted']);
            $this->assertSame(5 - $i, $res['remaining_attempts']);
        }

        // 5th failed attempt: exhausts challenge
        $fifthRes = $otpModel->verifyOtp($testUserId, '000000');
        $this->assertFalse($fifthRes['success']);
        $this->assertTrue($fifthRes['exhausted']);
        $this->assertSame(0, $fifthRes['remaining_attempts']);

        // Subsequent attempt with correct code must fail because challenge was consumed/exhausted
        $subsequentRes = $otpModel->verifyOtp($testUserId, $plainOtp);
        $this->assertFalse($subsequentRes['success'], 'Exhausted challenge must reject even correct code.');

        // Clean up
        $otpModel->invalidateUserChallenges($testUserId);
    }

    public function test_otp_single_use_atomically_consumes_challenge(): void
    {
        $otpModel = new EmailOtp();
        $testUserId = 4;
        $plainOtp = '777111';

        $otpModel->createChallenge($testUserId, $plainOtp);

        // First verification with correct OTP succeeds
        $first = $otpModel->verifyOtp($testUserId, $plainOtp);
        $this->assertTrue($first['success']);

        // Second verification with identical code must fail (single-use enforced)
        $second = $otpModel->verifyOtp($testUserId, $plainOtp);
        $this->assertFalse($second['success'], 'Consumed challenge cannot be used a second time.');

        // Clean up
        $otpModel->invalidateUserChallenges($testUserId);
    }
}
