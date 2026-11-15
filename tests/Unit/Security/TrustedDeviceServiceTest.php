<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserAuthSecurityState;
use App\Services\AuthRiskService;
use App\Services\AuthSecurityNotificationService;
use App\Services\TrustedDeviceService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Smart OTP cryptographic token parsing, hashing,
 * fixed role-based expirations, atomic rotation, fail-closed replay detection,
 * and risk-based step-up rules.
 */
class TrustedDeviceServiceTest extends TestCase
{
    private function defaultConfig(array $overrides = []): array
    {
        return array_merge([
            'cookie_name'             => 'nexora_td',
            'cookie_path'             => '/',
            'cookie_samesite'         => 'Lax',
            'max_trust_days'          => 90,
            'trust_days_by_role'      => [
                'employee' => 30,
                'hr'       => 7,
                'admin'    => 7,
            ],
            'max_devices_per_user'    => 10,
            'fixed_expiration'        => true,
            'rotate_on_use'           => true,
            'revoked_retention_days'  => 90,
            'stricter_hr_risk_checks' => true,
        ], $overrides);
    }

    public function test_parse_token_accepts_only_strict_hex_selector_and_validator(): void
    {
        $selector = str_repeat('a', 32);
        $validator = str_repeat('b', 64);

        $parsed = TrustedDeviceService::parseToken("{$selector}.{$validator}");
        $this->assertNotNull($parsed);
        $this->assertSame($selector, $parsed['selector']);
        $this->assertSame($validator, $parsed['validator']);

        $this->assertNull(TrustedDeviceService::parseToken(null));
        $this->assertNull(TrustedDeviceService::parseToken(''));
        $this->assertNull(TrustedDeviceService::parseToken('user_id=5'));
        $this->assertNull(TrustedDeviceService::parseToken(str_repeat('a', 31) . '.' . $validator));
        $this->assertNull(TrustedDeviceService::parseToken($selector . '.' . str_repeat('z', 64)));
        $this->assertNull(TrustedDeviceService::parseToken($selector . '.' . $validator . "\n"));
    }

    public function test_trust_days_for_role_are_bounded_and_never_indefinite(): void
    {
        $service = new TrustedDeviceService(
            $this->createStub(TrustedDevice::class),
            $this->createStub(AuthRiskService::class),
            $this->createStub(AuthSecurityNotificationService::class),
            $this->createStub(User::class),
            $this->defaultConfig()
        );

        $this->assertSame(30, $service->trustDaysForRole('employee'));
        $this->assertSame(7, $service->trustDaysForRole('hr'));
        $this->assertSame(7, $service->trustDaysForRole('admin'));
        $this->assertSame(7, $service->trustDaysForRole('HR'));

        $clampedService = new TrustedDeviceService(
            $this->createStub(TrustedDevice::class),
            $this->createStub(AuthRiskService::class),
            $this->createStub(AuthSecurityNotificationService::class),
            $this->createStub(User::class),
            $this->defaultConfig([
                'max_trust_days'     => 45,
                'trust_days_by_role' => ['employee' => 9999, 'hr' => 0],
            ])
        );

        $this->assertSame(45, $clampedService->trustDaysForRole('employee'));
        $this->assertSame(1, $clampedService->trustDaysForRole('hr'));
    }

    public function test_register_browser_generates_unique_high_entropy_cookie_without_exposing_raw_validator(): void
    {
        $devicesMock = $this->createMock(TrustedDevice::class);
        $riskStub = $this->createStub(AuthRiskService::class);
        $notifierMock = $this->createMock(AuthSecurityNotificationService::class);
        $usersStub = $this->createStub(User::class);

        $capturedTokenHash = null;
        $devicesMock->expects($this->once())
            ->method('createDevice')
            ->willReturnCallback(function (
                int $userId,
                string $selector,
                string $tokenHash,
                string $deviceName,
                string $browserName,
                string $osName,
                ?string $userAgent,
                ?string $ipAddress,
                int $trustDays
            ) use (&$capturedTokenHash): array {
                $capturedTokenHash = $tokenHash;
                $this->assertSame(42, $userId);
                $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $selector);
                $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $tokenHash);
                $this->assertSame(30, $trustDays);
                $this->assertSame('Google Chrome on Windows', $deviceName);

                return [
                    'id'            => 101,
                    'expires_at'    => '2026-12-16 04:00:00',
                    'expires_at_ts' => time() + (30 * 86400),
                ];
            });

        $devicesMock->expects($this->once())->method('evictOldestActiveOverLimit')->with(42, 10);
        $notifierMock->expects($this->once())->method('notifyDeviceRegistered');

        $service = new TrustedDeviceService(
            $devicesMock,
            $riskStub,
            $notifierMock,
            $usersStub,
            $this->defaultConfig()
        );

        $result = $service->registerBrowserAfterOtp(
            ['id' => 42, 'role' => 'employee', 'email' => 'emp@company.local'],
            null,
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36',
            '203.0.113.10'
        );

        $this->assertSame(101, $result['device_id']);
        $this->assertSame(30, $result['trust_days']);
        $this->assertNotNull(TrustedDeviceService::parseToken($result['raw_token']));
        [, $rawValidator] = explode('.', $result['raw_token'], 2);
        $this->assertNotSame($rawValidator, $capturedTokenHash, 'Stored validator must be hashed, never plaintext.');
        $this->assertSame($service->hashValidator($rawValidator), $capturedTokenHash);

        $cookieMeta = $service->getLastCookieAction();
        $this->assertNotNull($cookieMeta);
        $this->assertSame('set', $cookieMeta['action']);
        $this->assertSame('nexora_td', $cookieMeta['name']);
        $this->assertTrue($cookieMeta['options']['httponly']);
        $this->assertSame('Lax', $cookieMeta['options']['samesite']);
        $this->assertSame('', $cookieMeta['options']['domain']);
        $this->assertArrayNotHasKey('value', $cookieMeta, 'Raw token must not be retained in cookie metadata.');
    }

    public function test_validator_mismatch_triggers_replay_revocation_and_security_alert(): void
    {
        $selector = bin2hex(random_bytes(16));
        $realValidator = bin2hex(random_bytes(32));
        $staleValidator = bin2hex(random_bytes(32));

        $devicesMock = $this->createMock(TrustedDevice::class);
        $riskMock = $this->createMock(AuthRiskService::class);
        $notifierStub = $this->createStub(AuthSecurityNotificationService::class);
        $usersStub = $this->createStub(User::class);

        $riskMock->expects($this->once())->method('flagSuspiciousEvent')
            ->with(15, 'validator_mismatch_suspected_replay');

        $service = new TrustedDeviceService(
            $devicesMock,
            $riskMock,
            $notifierStub,
            $usersStub,
            $this->defaultConfig()
        );

        $riskMock->expects($this->once())->method('evaluateLoginRisk')
            ->willReturn(['require_otp' => false, 'reason' => null, 'revoke_trusted' => false]);

        $devicesMock->expects($this->once())
            ->method('findBySelector')
            ->with($selector)
            ->willReturn([
                'id'            => 77,
                'user_id'       => 15,
                'selector'      => $selector,
                'token_hash'    => $service->hashValidator($realValidator),
                'device_name'   => 'Google Chrome on Windows',
                'browser_name'  => 'Google Chrome',
                'os_name'       => 'Windows',
                'last_ip'       => '198.51.100.5',
                'expires_at'    => '2026-12-01 00:00:00',
                'expires_at_ts' => time() + 86400,
                'is_expired'    => 0,
                'revoked_at'    => null,
            ]);

        $devicesMock->expects($this->once())
            ->method('markAnomalyAndRevoke')
            ->with(77, 'validator_mismatch_suspected_replay')
            ->willReturn(true);

        $res = $service->evaluateAndRotateOnLogin(
            ['id' => 15, 'role' => 'employee', 'email' => 'emp15@company.local'],
            "{$selector}.{$staleValidator}",
            'Chrome/126.0',
            '198.51.100.5'
        );

        $this->assertFalse($res['trusted']);
        $this->assertTrue($res['require_otp']);
        $this->assertSame('token_mismatch', $res['reason']);
        $this->assertSame('validator_mismatch_suspected_replay', $res['step_up_reason']);
        $this->assertTrue($res['clear_cookie']);
    }

    public function test_concurrent_rotation_conflict_fails_closed_to_otp(): void
    {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));

        $devicesMock = $this->createMock(TrustedDevice::class);
        $riskStub = $this->createStub(AuthRiskService::class);
        $notifierStub = $this->createStub(AuthSecurityNotificationService::class);
        $usersStub = $this->createStub(User::class);

        $service = new TrustedDeviceService(
            $devicesMock,
            $riskStub,
            $notifierStub,
            $usersStub,
            $this->defaultConfig()
        );

        $riskStub->method('evaluateLoginRisk')
            ->willReturn(['require_otp' => false, 'reason' => null, 'revoke_trusted' => false]);

        $devicesMock->expects($this->once())->method('findBySelector')->willReturn([
            'id'            => 88,
            'user_id'       => 16,
            'selector'      => $selector,
            'token_hash'    => $service->hashValidator($validator),
            'device_name'   => 'Mozilla Firefox on Linux',
            'browser_name'  => 'Mozilla Firefox',
            'os_name'       => 'Linux',
            'last_ip'       => '203.0.113.20',
            'expires_at'    => '2026-12-01 00:00:00',
            'expires_at_ts' => time() + 86400,
            'is_expired'    => 0,
            'revoked_at'    => null,
        ]);

        // Atomic compare-and-swap fails (parallel login already consumed this validator)
        $devicesMock->expects($this->once())
            ->method('rotateValidatorAtomically')
            ->willReturn(false);

        $res = $service->evaluateAndRotateOnLogin(
            ['id' => 16, 'role' => 'employee'],
            "{$selector}.{$validator}",
            'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0',
            '203.0.113.20'
        );

        $this->assertFalse($res['trusted']);
        $this->assertTrue($res['require_otp']);
        $this->assertSame('rotation_conflict', $res['reason']);
        $this->assertSame('concurrent_token_rotation_conflict', $res['step_up_reason']);
    }

    public function test_list_devices_for_display_sets_current_flag_and_strips_selector(): void
    {
        $selectorA = bin2hex(random_bytes(16));
        $selectorB = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));

        $devicesStub = $this->createStub(TrustedDevice::class);
        $devicesStub->method('listForUser')->willReturn([
            ['id' => 1, 'selector' => $selectorA, 'device_name' => 'Google Chrome on Windows', 'device_status' => 'active'],
            ['id' => 2, 'selector' => $selectorB, 'device_name' => 'Apple Safari on macOS', 'device_status' => 'active'],
        ]);

        $service = new TrustedDeviceService(
            $devicesStub,
            $this->createStub(AuthRiskService::class),
            $this->createStub(AuthSecurityNotificationService::class),
            $this->createStub(User::class),
            $this->defaultConfig()
        );

        $rows = $service->listDevicesForDisplay(5, "{$selectorA}.{$validator}");
        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]['is_current_device']);
        $this->assertFalse($rows[1]['is_current_device']);
        $this->assertArrayNotHasKey('selector', $rows[0]);
        $this->assertArrayNotHasKey('selector', $rows[1]);
    }

    public function test_risk_service_never_steps_up_solely_for_ip_change_but_flags_multi_signal_hr_shift(): void
    {
        $stateStub = $this->createStub(UserAuthSecurityState::class);
        $stateStub->method('getRequiredStepUpReason')->willReturn(null);

        $risk = new AuthRiskService($stateStub, $this->defaultConfig());

        $device = [
            'browser_name' => 'Google Chrome',
            'os_name'      => 'Windows',
            'last_ip'      => '198.51.100.12',
        ];

        $chromeWinUa = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/126.0.0.0 Safari/537.36';

        // Roaming employee or HR changing IP across networks must NOT be locked out or forced to OTP
        $empIpShift = $risk->evaluateLoginRisk(10, 'employee', $device, $chromeWinUa, '203.0.113.88');
        $this->assertFalse($empIpShift['require_otp']);

        $hrIpOnlyShift = $risk->evaluateLoginRisk(2, 'hr', $device, $chromeWinUa, '203.0.113.88');
        $this->assertFalse($hrIpOnlyShift['require_otp'], 'HR IP address change alone must not trigger step-up.');

        // Correlated browser + OS + network shift on privileged HR account DOES require OTP
        $firefoxMacUa = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:127.0) Gecko/20100101 Firefox/127.0';
        $hrMultiShift = $risk->evaluateLoginRisk(2, 'hr', $device, $firefoxMacUa, '203.0.113.88');
        $this->assertTrue($hrMultiShift['require_otp']);
        $this->assertSame('privileged_multi_signal_context_shift', $hrMultiShift['reason']);
    }

    public function test_parse_user_agent_formats_common_browsers_and_platforms(): void
    {
        $edgeWin = TrustedDeviceService::parseUserAgent(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125.0.0.0 Safari/537.36 Edg/125.0.0.0'
        );
        $this->assertSame('Microsoft Edge', $edgeWin['browser_name']);
        $this->assertSame('Windows', $edgeWin['os_name']);
        $this->assertSame('Microsoft Edge on Windows', $edgeWin['device_name']);

        $safariIos = TrustedDeviceService::parseUserAgent(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Version/17.5 Mobile/15E148 Safari/604.1'
        );
        $this->assertSame('Apple Safari', $safariIos['browser_name']);
        $this->assertSame('iOS', $safariIos['os_name']);

        $blank = TrustedDeviceService::parseUserAgent('');
        $this->assertSame('Web Browser', $blank['device_name']);
    }
}
