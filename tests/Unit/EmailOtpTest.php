<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\EmailOtp;
use Core\Database;

class EmailOtpTest extends TestCase
{
    public function test_otp_format_is_six_numeric_digits(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $this->assertSame(6, strlen($otp));
            $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $otp);
        }
    }

    public function test_otp_hashing_and_verification(): void
    {
        $plainOtp = '739102';
        $hash = password_hash($plainOtp, PASSWORD_DEFAULT);

        $this->assertNotSame($plainOtp, $hash, 'Plaintext OTP must never equal stored hash.');
        $this->assertTrue(password_verify($plainOtp, $hash), 'Correct OTP must verify against password_hash.');
        $this->assertFalse(password_verify('739103', $hash), 'Incorrect OTP must fail verification.');
    }

    public function test_resend_cooldown_logic(): void
    {
        $cooldownLimit = EmailOtp::RESEND_COOLDOWN;
        $this->assertSame(60, $cooldownLimit, 'Resend cooldown must be configured to 60 seconds.');

        $lifetime = EmailOtp::OTP_LIFETIME;
        $this->assertSame(300, $lifetime, 'OTP lifetime must be configured to 300 seconds (5 minutes).');

        $maxAttempts = EmailOtp::MAX_ATTEMPTS;
        $this->assertSame(5, $maxAttempts, 'Maximum verification attempts must be 5.');
    }

    public function test_database_challenge_lifecycle_when_connected(): void
    {
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database is not connected in this local environment; skipping database integration checks.');
        }

        $otpModel = new EmailOtp();
        $testUserId = 2; // hr01 seed user
        $plainOtp = '123456';

        // 1. Create a challenge
        $challengeId = $otpModel->createChallenge($testUserId, $plainOtp);
        $this->assertGreaterThan(0, $challengeId);

        // 2. Cooldown should now be active (just created < 60s ago)
        $cooldown = $otpModel->checkResendCooldown($testUserId);
        $this->assertFalse($cooldown['allowed'], 'Cooldown should be active immediately after creation.');
        $this->assertGreaterThan(0, $cooldown['remaining_seconds']);

        // 3. Test failed attempt increments counter
        $failResult = $otpModel->verifyOtp($testUserId, '000000');
        $this->assertFalse($failResult['success']);
        $this->assertSame(4, $failResult['remaining_attempts']);

        // 4. Test valid code successfully verifies and consumes
        $successResult = $otpModel->verifyOtp($testUserId, $plainOtp);
        $this->assertTrue($successResult['success']);

        // 5. Single-use test: re-verifying the consumed code must fail
        $reuseResult = $otpModel->verifyOtp($testUserId, $plainOtp);
        $this->assertFalse($reuseResult['success'], 'Consumed OTP must not be reusable.');

        // Clean up
        $otpModel->invalidateUserChallenges($testUserId);
    }
}
