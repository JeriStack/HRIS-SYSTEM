<?php

/**
 * Account Revocation Service
 * Employee HRIS & Leave Management System
 *
 * Revokes out-of-band credentials (WebSocket tickets and pending email OTP
 * challenges) for a user whose account state changed (deactivation, role
 * change, employee deactivation). Active PHP sessions are terminated on their
 * next request by Core\Session::verifyAuthenticatedAccountState().
 */

namespace App\Services;

use App\Models\EmailOtp;
use App\Models\RealtimeTicket;
use App\Models\TrustedDevice;
use App\Models\UserAuthSecurityState;
use Throwable;

class AccountRevocationService
{
    private ?RealtimeTicket $tickets;
    private ?EmailOtp $otps;
    private ?TrustedDevice $trustedDevices;
    private ?UserAuthSecurityState $securityStates;

    public function __construct(
        ?RealtimeTicket $tickets = null,
        ?EmailOtp $otps = null,
        ?TrustedDevice $trustedDevices = null,
        ?UserAuthSecurityState $securityStates = null
    ) {
        $this->tickets = $tickets;
        $this->otps = $otps;
        $this->trustedDevices = $trustedDevices;
        $this->securityStates = $securityStates;
    }

    /**
     * Revoke all real-time tickets, pending OTP challenges, and trusted devices of a user,
     * and flag next login for mandatory Email OTP verification.
     * Never throws: failures are logged without sensitive data so the primary
     * administrative operation is not rolled back by a revocation side effect.
     */
    public function revokeUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            ($this->tickets ??= new RealtimeTicket())->revokeAllForUser($userId);
        } catch (Throwable $e) {
            error_log('[AccountRevocation] Ticket revocation failed: ' . get_class($e));
        }

        try {
            ($this->otps ??= new EmailOtp())->invalidateUserChallenges($userId);
        } catch (Throwable $e) {
            error_log('[AccountRevocation] OTP invalidation failed: ' . get_class($e));
        }

        try {
            ($this->trustedDevices ??= new TrustedDevice())->revokeAllForUser($userId, 'account_state_or_role_changed');
        } catch (Throwable $e) {
            error_log('[AccountRevocation] Trusted-device revocation failed: ' . get_class($e));
        }

        try {
            ($this->securityStates ??= new UserAuthSecurityState())->flagStepUpRequired($userId, 'account_role_or_status_changed', 'last_suspicious_event_at');
        } catch (Throwable $e) {
            error_log('[AccountRevocation] Security state flag failed: ' . get_class($e));
        }
    }
}
