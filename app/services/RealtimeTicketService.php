<?php
/**
 * Real-time Ticket Issuance Service (HTTP application side)
 * Employee HRIS & Leave Management System
 *
 * Bridges the existing session + email-OTP authentication to the WebSocket
 * service. Only a fully authenticated session (post-OTP `user` payload) whose
 * account is still Active with an unchanged role can obtain a ticket.
 */

namespace App\Services;

use App\Models\RealtimeTicket;
use App\Models\User;
use App\Realtime\RealtimeConfig;
use Core\Session;
use Throwable;

class RealtimeTicketService
{
    public const SESSION_REF_KEY = '_realtime_ref';

    private ?RealtimeTicket $tickets;
    private ?User $users;
    private RealtimeConfig $config;

    public function __construct(?RealtimeTicket $tickets = null, ?User $users = null, ?RealtimeConfig $config = null)
    {
        $this->tickets = $tickets;
        $this->users = $users;
        $this->config = $config ?? RealtimeConfig::fromEnvironment();
    }

    /**
     * @return array{ok:true, ticket:string, expires_in:int, url:string}|array{ok:false, status:int, error:string}
     */
    public function issueForCurrentSession(): array
    {
        if (!$this->config->enabled) {
            return ['ok' => false, 'status' => 503, 'error' => 'Real-time updates are disabled.'];
        }

        $sessionUser = currentUser();
        $userId = (int)($sessionUser['id'] ?? 0);
        if ($userId <= 0) {
            return ['ok' => false, 'status' => 401, 'error' => 'Authentication required.'];
        }

        $sessionRole = strtolower((string)($sessionUser['role'] ?? ''));
        if (!in_array($sessionRole, ['hr', 'employee'], true)) {
            return ['ok' => false, 'status' => 403, 'error' => 'Unsupported role.'];
        }

        // Re-verify against the database: deactivated or re-roled accounts get nothing
        $record = $this->users()->findById($userId);
        if (!$record || ($record['status'] ?? '') !== 'Active' || strtolower((string)$record['role']) !== $sessionRole) {
            return ['ok' => false, 'status' => 403, 'error' => 'Account state changed. Please sign in again.'];
        }

        // Ticket cannot outlive the HTTP session's inactivity deadline
        $lastActivity = (int)Session::get('last_activity', 0);
        if ($lastActivity <= 0) {
            return ['ok' => false, 'status' => 401, 'error' => 'Authentication required.'];
        }
        $sessionExpiresAt = $lastActivity + $this->config->sessionLifetime;
        if ($sessionExpiresAt <= time()) {
            return ['ok' => false, 'status' => 401, 'error' => 'Session expired.'];
        }

        $sessionRef = (string)Session::get(self::SESSION_REF_KEY, '');
        if (!preg_match('/^[a-f0-9]{32}$/D', $sessionRef)) {
            $sessionRef = bin2hex(random_bytes(16));
            Session::set(self::SESSION_REF_KEY, $sessionRef);
        }

        if ($this->tickets()->countRecentForSession($sessionRef, 60) >= $this->config->ticketsPerMinute) {
            return ['ok' => false, 'status' => 429, 'error' => 'Too many connection attempts. Please wait a moment.'];
        }

        $ticket = $this->tickets()->issue(
            $userId,
            $sessionRef,
            $sessionExpiresAt,
            $this->config->ticketTtl,
            $_SERVER['REMOTE_ADDR'] ?? null
        );

        return [
            'ok'         => true,
            'ticket'     => $ticket,
            'expires_in' => $this->config->ticketTtl,
            'url'        => $this->config->browserUrl((string)config('config.base_url', '')),
        ];
    }

    /**
     * Revoke all WebSocket tickets of the current browser session (logout).
     * Never blocks logout: failures are logged without sensitive data.
     */
    public static function revokeCurrentSession(): void
    {
        $sessionRef = $_SESSION[self::SESSION_REF_KEY] ?? null;
        if (!is_string($sessionRef) || !preg_match('/^[a-f0-9]{32}$/D', $sessionRef)) {
            return;
        }

        try {
            (new RealtimeTicket())->revokeSession($sessionRef);
        } catch (Throwable $e) {
            error_log('[Realtime] Ticket revocation failed: ' . get_class($e));
        }
    }

    private function tickets(): RealtimeTicket
    {
        return $this->tickets ??= new RealtimeTicket();
    }

    private function users(): User
    {
        return $this->users ??= new User();
    }
}
