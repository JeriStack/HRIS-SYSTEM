<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Models\EmailOtp;
use App\Models\Notification;
use App\Models\PasswordReset;
use App\Models\RealtimeTicket;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\AuthRiskService;
use App\Services\MailService;
use App\Services\PasswordPolicy;
use App\Services\PasswordRecoveryService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PasswordPolicy, anti-enumeration request throttling,
 * CSPRNG token issuance/validation, atomic single-use reset completion,
 * and post-recovery device/session/OTP revocation.
 */
class PasswordRecoveryServiceTest extends TestCase
{
    public function test_password_policy_enforces_bcrypt_cost_12_and_strict_complexity(): void
    {
        $hash = PasswordPolicy::hash('Strong!Pass#2026');
        $info = password_get_info($hash);

        $this->assertSame(PASSWORD_BCRYPT, $info['algo']);
        $this->assertSame(12, (int)($info['options']['cost'] ?? 0));
        $this->assertFalse(PasswordPolicy::needsRehash($hash));

        $legacyCost10 = password_hash('LegacyPass!123', PASSWORD_BCRYPT, ['cost' => 10]);
        $this->assertTrue(PasswordPolicy::needsRehash($legacyCost10));

        // Too short (< 8 characters)
        $this->assertNotEmpty(PasswordPolicy::validateForRecovery('Short!1', 'Short!1'));
        // Mismatch
        $this->assertNotEmpty(PasswordPolicy::validateForRecovery('Strong!Pass#2026', 'Different!Pass#2026'));
        // Missing symbol
        $this->assertNotEmpty(PasswordPolicy::validateForRecovery('StrongPass2026A', 'StrongPass2026A'));
        // Missing uppercase
        $this->assertNotEmpty(PasswordPolicy::validateForRecovery('strong!pass#2026', 'strong!pass#2026'));
        // Contains email local-part
        $this->assertNotEmpty(PasswordPolicy::validateForRecovery(
            'jdelacruz!2026A',
            'jdelacruz!2026A',
            ['email' => 'jdelacruz@nexora.local', 'username' => 'juan_hr']
        ));
        // Contains username
        $this->assertNotEmpty(PasswordPolicy::validateForRecovery(
            'My_juan_hr!2026A',
            'My_juan_hr!2026A',
            ['email' => 'juan@nexora.local', 'username' => 'juan_hr']
        ));

        // Valid strong password passes
        $this->assertSame([], PasswordPolicy::validateForRecovery(
            'Nexora!Vault#9281',
            'Nexora!Vault#9281',
            ['email' => 'juan@nexora.local', 'username' => 'juan_hr']
        ));
    }

    public function test_request_reset_enforces_anti_enumeration_for_unknown_and_ineligible_accounts(): void
    {
        $users = $this->createStub(User::class);
        $users->method('findByEmail')->willReturnMap([
            ['missing@nexora.local', null],
            ['inactive@nexora.local', [
                'id'                => 10,
                'email'             => 'inactive@nexora.local',
                'role'              => 'employee',
                'status'            => 'Inactive',
                'employment_status' => 'Active',
            ]],
            ['terminated@nexora.local', [
                'id'                => 11,
                'employee_id'       => 111,
                'email'             => 'terminated@nexora.local',
                'role'              => 'hr',
                'status'            => 'Active',
                'employment_status' => 'Terminated',
            ]],
        ]);

        $resets = $this->createMock(PasswordReset::class);
        $resets->method('countRecentRequestsForEmailKey')->willReturn(['count' => 0, 'retry_after' => 0]);
        $resets->method('countRecentRequestsForIp')->willReturn(['count' => 0, 'retry_after' => 0]);
        $resets->expects($this->never())->method('createToken');

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->never())->method('sendMail');

        $service = $this->buildService($users, $resets, $mailer);

        // Malformed email rejects before hitting lookup/email dispatch
        $invalidFormat = $service->requestReset('not-an-email', '198.51.100.10', 'PHPUnit');
        $this->assertFalse($invalidFormat['ok']);
        $this->assertSame('validation_error', $invalidFormat['status']);

        $probeEmails = [
            'missing@nexora.local',
            'inactive@nexora.local',
            'terminated@nexora.local',
        ];
        foreach ($probeEmails as $probeEmail) {
            $result = $service->requestReset($probeEmail, '198.51.100.10', 'PHPUnit');
            $this->assertTrue($result['ok']);
            $this->assertSame('accepted', $result['status']);
            $this->assertSame(PasswordRecoveryService::GENERIC_ACCEPTANCE_MESSAGE, $result['message']);
            $this->assertFalse($result['email_dispatched']);
        }
    }

    public function test_request_reset_throttles_without_disclosure_when_email_or_ip_exceeds_limit(): void
    {
        $users = $this->createStub(User::class);
        $users->method('findByEmail')->willReturn([
            'id'                => 21,
            'email'             => 'active@nexora.local',
            'role'              => 'employee',
            'status'            => 'Active',
            'employment_status' => 'Active',
        ]);

        // Per-email limit reached (>= 3)
        $resetsByEmail = $this->createMock(PasswordReset::class);
        $resetsByEmail->method('countRecentRequestsForEmailKey')->willReturn(['count' => 3, 'retry_after' => 600]);
        $resetsByEmail->method('countRecentRequestsForIp')->willReturn(['count' => 1, 'retry_after' => 600]);
        $resetsByEmail->expects($this->never())->method('createToken');

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->never())->method('sendMail');

        $emailThrottled = $this->buildService($users, $resetsByEmail, $mailer)
            ->requestReset('active@nexora.local', '203.0.113.5', 'PHPUnit');
        $this->assertTrue($emailThrottled['ok']);
        $this->assertSame('throttled', $emailThrottled['status']);
        $this->assertSame(PasswordRecoveryService::GENERIC_ACCEPTANCE_MESSAGE, $emailThrottled['message']);
        $this->assertFalse($emailThrottled['email_dispatched']);

        // Per-IP limit reached (>= 10)
        $resetsByIp = $this->createMock(PasswordReset::class);
        $resetsByIp->method('countRecentRequestsForEmailKey')->willReturn(['count' => 0, 'retry_after' => 0]);
        $resetsByIp->method('countRecentRequestsForIp')->willReturn(['count' => 10, 'retry_after' => 600]);
        $resetsByIp->expects($this->never())->method('createToken');

        $ipThrottled = $this->buildService($users, $resetsByIp, $mailer)
            ->requestReset('active@nexora.local', '203.0.113.5', 'PHPUnit');
        $this->assertTrue($ipThrottled['ok']);
        $this->assertSame('throttled', $ipThrottled['status']);
        $this->assertSame(PasswordRecoveryService::GENERIC_ACCEPTANCE_MESSAGE, $ipThrottled['message']);
        $this->assertFalse($ipThrottled['email_dispatched']);
    }

    public function test_request_reset_issues_hmac_hashed_token_invalidates_prior_and_emails_url(): void
    {
        $users = $this->createStub(User::class);
        $users->method('findByEmail')->willReturn([
            'id'                => 35,
            'email'             => 'hr.lead@nexora.local',
            'username'          => 'hrlead',
            'first_name'        => 'Clara',
            'last_name'         => 'Reyes',
            'role'              => 'hr',
            'status'            => 'Active',
            'employment_status' => 'Active',
        ]);

        $capturedSelector = '';
        $capturedHash = '';
        $capturedTtl = 0;

        $resets = $this->createMock(PasswordReset::class);
        $resets->method('countRecentRequestsForEmailKey')->willReturn(['count' => 0, 'retry_after' => 0]);
        $resets->method('countRecentRequestsForIp')->willReturn(['count' => 0, 'retry_after' => 0]);
        $resets->method('runInTransaction')->willReturnCallback(static fn (callable $cb) => $cb());
        $resets->expects($this->once())->method('invalidateActiveForUser')->with(35)->willReturn(1);
        $resets->expects($this->once())
            ->method('createToken')
            ->willReturnCallback(
                static function (
                    int $userId,
                    string $selector,
                    string $tokenHash,
                    string $requestedEmail,
                    ?string $ip,
                    ?string $ua,
                    int $ttlMinutes
                ) use (
                    &$capturedSelector,
                    &$capturedHash,
                    &$capturedTtl
                ): int {
                    $capturedSelector = $selector;
                    $capturedHash = $tokenHash;
                    $capturedTtl = $ttlMinutes;
                    return 501;
                }
            );

        $capturedEmailBody = '';
        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->once())
            ->method('sendMail')
            ->willReturnCallback(static function (
                string $toAddress,
                string $toName,
                string $subject,
                string $htmlBody
            ) use (&$capturedEmailBody): bool {
                $capturedEmailBody = $htmlBody;
                return true;
            });

        $result = $this->buildService($users, $resets, $mailer)
            ->requestReset('HR.LEAD@nexora.local', '203.0.113.15', 'PHPUnit');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['email_dispatched']);
        $this->assertSame(15, $capturedTtl);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $capturedSelector);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $capturedHash);
        $this->assertStringContainsString('/reset-password?token=' . $capturedSelector . '.', $capturedEmailBody);

        // Verify email link's raw verifier is NOT stored directly in token_hash
        $matched = preg_match('/token=' . preg_quote($capturedSelector, '/') . '\.([a-f0-9]{64})/', $capturedEmailBody, $m);
        $this->assertSame(1, $matched);
        $this->assertNotSame($m[1], $capturedHash);
    }

    public function test_validate_and_complete_reset_rejects_expired_used_superseded_or_tampered_tokens(): void
    {
        $selector = str_repeat('a', 32);
        $verifier = str_repeat('b', 64);
        $service = $this->buildService(
            $this->createStub(User::class),
            $this->createStub(PasswordReset::class),
            $this->createStub(MailService::class)
        );

        // Malformed format
        $this->assertFalse($service->validateResetToken('bad-token')['valid']);
        $this->assertFalse($service->validateResetToken('short.hex')['valid']);

        // Not found
        $notFoundResets = $this->createStub(PasswordReset::class);
        $notFoundResets->method('findBySelector')->willReturn(null);
        $resNotFound = $this->buildService($this->createStub(User::class), $notFoundResets, $this->createStub(MailService::class))
            ->validateResetToken($selector . '.' . $verifier);
        $this->assertFalse($resNotFound['valid']);
        $this->assertSame('not_found', $resNotFound['reason']);

        // Consumed
        $usedResets = $this->createStub(PasswordReset::class);
        $usedResets->method('findBySelector')->willReturn([
            'id'             => 1,
            'user_id'        => 42,
            'selector'       => $selector,
            'token_hash'     => str_repeat('c', 64),
            'consumed_at'    => '2026-11-16 00:00:00',
            'invalidated_at' => null,
            'is_expired'     => 0,
        ]);
        $resUsed = $this->buildService($this->createStub(User::class), $usedResets, $this->createStub(MailService::class))
            ->validateResetToken($selector . '.' . $verifier);
        $this->assertSame('already_used', $resUsed['reason']);

        // Superseded
        $supResets = $this->createStub(PasswordReset::class);
        $supResets->method('findBySelector')->willReturn([
            'id'             => 2,
            'user_id'        => 42,
            'selector'       => $selector,
            'token_hash'     => str_repeat('c', 64),
            'consumed_at'    => null,
            'invalidated_at' => '2026-11-16 00:01:00',
            'is_expired'     => 0,
        ]);
        $resSup = $this->buildService($this->createStub(User::class), $supResets, $this->createStub(MailService::class))
            ->validateResetToken($selector . '.' . $verifier);
        $this->assertSame('superseded', $resSup['reason']);

        // Expired
        $expResets = $this->createStub(PasswordReset::class);
        $expResets->method('findBySelector')->willReturn([
            'id'             => 3,
            'user_id'        => 42,
            'selector'       => $selector,
            'token_hash'     => str_repeat('c', 64),
            'consumed_at'    => null,
            'invalidated_at' => null,
            'is_expired'     => 1,
        ]);
        $resExp = $this->buildService($this->createStub(User::class), $expResets, $this->createStub(MailService::class))
            ->validateResetToken($selector . '.' . $verifier);
        $this->assertSame('expired', $resExp['reason']);

        // Tampered verifier (hash mismatch)
        $mismatchResets = $this->createStub(PasswordReset::class);
        $mismatchResets->method('findBySelector')->willReturn([
            'id'             => 4,
            'user_id'        => 42,
            'selector'       => $selector,
            'token_hash'     => str_repeat('0', 64),
            'consumed_at'    => null,
            'invalidated_at' => null,
            'is_expired'     => 0,
        ]);
        $resMismatch = $this->buildService($this->createStub(User::class), $mismatchResets, $this->createStub(MailService::class))
            ->validateResetToken($selector . '.' . $verifier);
        $this->assertSame('hash_mismatch', $resMismatch['reason']);
    }

    public function test_complete_reset_upgrades_hash_and_revokes_devices_ws_otps_and_session(): void
    {
        $userRow = [
            'id'                => 88,
            'email'             => 'emp.recovery@nexora.local',
            'username'          => 'emp_rec',
            'first_name'        => 'Marco',
            'last_name'         => 'Santos',
            'role'              => 'employee',
            'status'            => 'Active',
            'employment_status' => 'Active',
        ];

        $storedSelector = '';
        $storedHash = '';
        $resetUrl = '';

        $users = $this->createMock(User::class);
        $users->method('findByEmail')->willReturn($userRow);
        $users->expects($this->once())->method('findByIdWithAuthState')->with(88)->willReturn($userRow);

        $updatedHash = '';
        $users->expects($this->once())
            ->method('updatePasswordHash')
            ->with(88, $this->callback(static function (string $hash) use (&$updatedHash): bool {
                $updatedHash = $hash;
                $info = password_get_info($hash);
                return $info['algo'] === PASSWORD_BCRYPT && ($info['options']['cost'] ?? 0) === 12;
            }))
            ->willReturn(true);

        $resets = $this->createMock(PasswordReset::class);
        $resets->method('countRecentRequestsForEmailKey')->willReturn(['count' => 0, 'retry_after' => 0]);
        $resets->method('countRecentRequestsForIp')->willReturn(['count' => 0, 'retry_after' => 0]);
        $resets->method('runInTransaction')->willReturnCallback(static fn (callable $cb) => $cb());
        $resets->method('createToken')
            ->willReturnCallback(
                static function (
                    int $uid,
                    string $selector,
                    string $hash
                ) use (
                    &$storedSelector,
                    &$storedHash
                ): int {
                    $storedSelector = $selector;
                    $storedHash = $hash;
                    return 701;
                }
            );
        $resets->method('findBySelector')->willReturnCallback(
            static function (string $selector) use (
                &$storedSelector,
                &$storedHash
            ): ?array {
                if ($selector !== $storedSelector) {
                    return null;
                }
                return [
                    'id'             => 701,
                    'user_id'        => 88,
                    'selector'       => $storedSelector,
                    'token_hash'     => $storedHash,
                    'consumed_at'    => null,
                    'invalidated_at' => null,
                    'is_expired'     => 0,
                ];
            }
        );
        $resets->expects($this->once())->method('consumeAtomically')->with(701)->willReturn(true);
        $resets->expects($this->exactly(2))->method('invalidateActiveForUser')->with(88)->willReturn(1);

        $mailer = $this->createMock(MailService::class);
        $mailer->expects($this->exactly(2))
            ->method('sendMail')
            ->willReturnCallback(static function (
                string $toAddress,
                string $toName,
                string $subject,
                string $htmlBody
            ) use (&$resetUrl): bool {
                if (preg_match('/https?:\/\/[^\s"\']+token=([a-f0-9]{32}\.[a-f0-9]{64})/', $htmlBody, $m)) {
                    $resetUrl = $m[1];
                }
                return true;
            });

        $trustedDevices = $this->createMock(TrustedDevice::class);
        $trustedDevices->expects($this->once())
            ->method('revokeAllForUser')
            ->with(88, 'password_reset_completed')
            ->willReturn(2);

        $emailOtps = $this->createMock(EmailOtp::class);
        $emailOtps->expects($this->once())
            ->method('invalidateUserChallenges')
            ->with(88)
            ->willReturn(true);

        $realtimeTickets = $this->createMock(RealtimeTicket::class);
        $realtimeTickets->expects($this->once())
            ->method('revokeAllForUser')
            ->with(88)
            ->willReturn(1);

        $riskService = $this->createMock(AuthRiskService::class);
        $riskService->expects($this->once())
            ->method('flagPasswordChanged')
            ->with(88);

        $notifications = $this->createMock(Notification::class);
        $notifications->expects($this->once())->method('create');

        $service = new PasswordRecoveryService(
            $users,
            $resets,
            $mailer,
            $trustedDevices,
            $emailOtps,
            $realtimeTickets,
            $riskService,
            $notifications
        );

        $req = $service->requestReset('emp.recovery@nexora.local', '203.0.113.40', 'PHPUnit');
        $this->assertTrue($req['ok']);
        $this->assertNotSame('', $resetUrl);

        $complete = $service->completeReset(
            $resetUrl,
            'Vault!Guard#7731',
            'Vault!Guard#7731',
            '203.0.113.40',
            'PHPUnit'
        );

        $this->assertTrue($complete['ok']);
        $this->assertSame('completed', $complete['status']);
        $this->assertTrue(password_verify('Vault!Guard#7731', $updatedHash));
    }

    private function buildService(
        User $users,
        PasswordReset $resets,
        MailService $mailer
    ): PasswordRecoveryService {
        return new PasswordRecoveryService(
            $users,
            $resets,
            $mailer,
            $this->createStub(TrustedDevice::class),
            $this->createStub(EmailOtp::class),
            $this->createStub(RealtimeTicket::class),
            $this->createStub(AuthRiskService::class),
            $this->createStub(Notification::class)
        );
    }
}
