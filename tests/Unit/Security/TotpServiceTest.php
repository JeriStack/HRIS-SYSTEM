<?php

/**
 * TOTP Service Unit Tests
 * HRIS-SYSTEM — Test Suite (Phase 2: TOTP Authentication)
 */

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Services\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    private TotpService $totpService;

    protected function setUp(): void
    {
        $this->totpService = new TotpService('test_master_encryption_key_32_bytes_long!');
    }

    public function test_secret_generation_and_base32_format(): void
    {
        $secret = $this->totpService->generateSecret(20);
        $this->assertNotEmpty($secret);
        $this->assertMatchesRegularExpression('/^[A-Z2-7=]+$/', $secret);
    }

    public function test_authenticated_secret_encryption_and_decryption(): void
    {
        $secret = $this->totpService->generateSecret(20);
        $encrypted = $this->totpService->encryptSecret($secret);

        $this->assertNotSame($secret, $encrypted);
        $decrypted = $this->totpService->decryptSecret($encrypted);
        $this->assertSame($secret, $decrypted);
    }

    public function test_decryption_fails_on_tampered_payload(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->totpService->decryptSecret('invalid_short_payload');
    }

    public function test_totp_code_generation_and_verification(): void
    {
        $secret = $this->totpService->generateSecret(20);
        $timestamp = 1761900000; // Fixed test epoch

        $code = $this->totpService->getTotpCode($secret, $timestamp);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);

        // Verify valid code within current window
        $result = $this->totpService->verifyCode($secret, $code, null, 1, $timestamp);
        $this->assertTrue($result['valid']);
        $this->assertNotNull($result['matched_timestep']);

        // Verify invalid code fails
        $badResult = $this->totpService->verifyCode($secret, '000000', null, 1, $timestamp);
        $this->assertFalse($badResult['valid']);
    }

    public function test_replay_attack_protection_rejects_used_timestep(): void
    {
        $secret = $this->totpService->generateSecret(20);
        $timestamp = 1761900000;
        $currentTimestep = (int)floor($timestamp / 30);

        $code = $this->totpService->getTotpCode($secret, $timestamp);

        // Verification with previous timestep set to current timestep must fail (replay attack)
        $replayResult = $this->totpService->verifyCode($secret, $code, $currentTimestep, 1, $timestamp);
        $this->assertFalse($replayResult['valid'], 'Replaying the same TOTP within the same timestep must be blocked.');
    }

    public function test_single_use_recovery_codes_lifecycle(): void
    {
        $codes = $this->totpService->generateRecoveryCodes(8);
        $this->assertCount(8, $codes['plain']);
        $this->assertCount(8, $codes['hashed']);

        $firstPlain = $codes['plain'][0];

        // Consume first code
        $result = $this->totpService->verifyAndConsumeRecoveryCode($firstPlain, $codes['hashed']);
        $this->assertTrue($result['valid']);
        $this->assertCount(7, $result['remaining_hashes']);

        // Attempting to reuse consumed code must fail
        $reuseResult = $this->totpService->verifyAndConsumeRecoveryCode($firstPlain, $result['remaining_hashes']);
        $this->assertFalse($reuseResult['valid'], 'Consumed recovery codes cannot be reused.');
    }

    public function test_otp_auth_uri_generation(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $uri = $this->totpService->getOtpAuthUri('employee@hris.local', $secret, 'HRIS System');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=HRIS%20System', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }
}
