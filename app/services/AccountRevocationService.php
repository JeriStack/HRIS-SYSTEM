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
use Throwable;

class AccountRevocationService
{
    private ?RealtimeTicket $tickets;
    private ?EmailOtp $otps;

    public function __construct(?RealtimeTicket $tickets = null, ?EmailOtp $otps = null)
    {
        $this->tickets = $tickets;
        $this->otps = $otps;
    }

    /**
     * Revoke all real-time tickets and pending OTP challenges of a user.
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
    }
}
