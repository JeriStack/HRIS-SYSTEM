<?php
/**
 * WebSocket Close Codes used by the HRIS real-time service.
 * 4000-4999 are reserved for application use by RFC 6455.
 * The browser client (public/assets/js/realtime.js) keys reconnect logic off these.
 */

namespace App\Realtime;

final class CloseCodes
{
    public const NORMAL            = 1000;
    public const POLICY_VIOLATION  = 1008;
    public const MESSAGE_TOO_BIG   = 1009;
    public const SERVER_ERROR      = 1011;

    public const SESSION_EXPIRED   = 4001; // Re-ticket (session may still be alive)
    public const REAUTH_REQUIRED   = 4002; // Role changed; re-ticket
    public const SESSION_REVOKED   = 4003; // Logged out / deactivated; do not reconnect
    public const AUTH_FAILED       = 4401; // Invalid, expired, or reused ticket
    public const AUTH_TIMEOUT      = 4408; // No auth message within the timeout
    public const TOO_MANY          = 4429; // Connection limit for this user reached
}
