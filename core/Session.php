<?php
/**
 * Core Session & State Security Manager
 * Employee HRIS & Leave Management System
 */

namespace Core;

class Session
{
    private static bool $started = false;

    /**
     * Start and configure a secure PHP session.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            // 1. Enforce strict session cookie security
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');

            // X-Forwarded-Proto is honoured only when the TCP peer is a trusted reverse proxy
            $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $fromTrustedProxy = $remote !== ''
                && function_exists('trusted_proxy_ranges')
                && \App\Realtime\IpRange::matchesAny($remote, trusted_proxy_ranges());
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
                || ($fromTrustedProxy && strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

            session_set_cookie_params([
                'lifetime' => 0, // Expire when browser closes; server enforces inactivity & absolute timeouts
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_start();
        }

        self::$started = true;

        // 2. Enforce inactivity timeout, absolute lifetime, and CSRF initialization
        self::checkInactivityTimeout();
    }

    /**
     * Enforce inactivity timeout, absolute session lifetime, and initialize CSRF token if absent.
     */
    public static function checkInactivityTimeout(): void
    {
        if (isset($_SESSION['user'])) {
            $now = time();
            $timeoutSeconds = (int)config('config.session_lifetime', 1800); // 30 minutes default
            $absoluteSeconds = (int)config('config.session_absolute_lifetime', 43200); // 12 hours default

            $lastActivity = isset($_SESSION['last_activity']) ? (int)$_SESSION['last_activity'] : null;
            $loginTime = (int)($_SESSION['user']['login_time'] ?? $_SESSION['login_time'] ?? 0);

            $inactivityExpired = ($lastActivity !== null && ($now - $lastActivity) > $timeoutSeconds);
            $absoluteExpired = ($loginTime > 0 && $absoluteSeconds > 0 && ($now - $loginTime) > $absoluteSeconds);

            if ($inactivityExpired || $absoluteExpired) {
                if (class_exists(\App\Services\RealtimeTicketService::class)) {
                    \App\Services\RealtimeTicketService::revokeCurrentSession();
                }

                unset($_SESSION['user'], $_SESSION['_realtime_ref'], $_SESSION['last_activity'], $_SESSION['login_time']);

                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }

                $message = $absoluteExpired
                    ? 'Your maximum session duration has expired. Please sign in again.'
                    : 'Your session has expired due to 30 minutes of inactivity. Please sign in again.';
                self::flash('timeout', $message);
            } elseif ($lastActivity !== null && !self::isBackgroundRequest()) {
                // Refresh activity timestamp (user-initiated requests only)
                $_SESSION['last_activity'] = $now;
            }
        }

        // 3. Initialize cryptographically secure CSRF token if absent
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Compute a deterministic, non-reversible security fingerprint over critical account state
     * (user ID, normalized role, account status, employment status, and password hash).
     * Any change to password, role, or active status invalidates the fingerprint.
     */
    public static function computeAuthFingerprint(array $userRow): string
    {
        $id = (int)($userRow['id'] ?? 0);
        $role = strtolower(trim((string)($userRow['role'] ?? '')));
        $status = trim((string)($userRow['status'] ?? ''));
        $empStatus = trim((string)($userRow['employment_status'] ?? 'Active'));
        if ($empStatus === '') {
            $empStatus = 'Active';
        }
        $passwordHash = (string)($userRow['password'] ?? '');

        return hash('sha256', "{$id}|{$role}|{$status}|{$empStatus}|{$passwordHash}");
    }

    /**
     * Re-verify the currently authenticated session against live server-side account state.
     * Immediately invalidates the session if the user was deactivated, their employee record
     * was deactivated, their role changed, or their password hash changed.
     */
    public static function verifyAuthenticatedAccountState(): bool
    {
        self::start();

        if (empty($_SESSION['user']) || empty($_SESSION['user']['id'])) {
            return false;
        }

        $sessionUser = $_SESSION['user'];
        $userId = (int)$sessionUser['id'];
        $sessionRole = strtolower(trim((string)($sessionUser['role'] ?? '')));

        // Strictly enforce the 2-role model (HR and Employee only)
        if ($sessionRole !== 'hr' && $sessionRole !== 'employee') {
            self::invalidateAuthenticatedUser('Your session was terminated due to an unauthorized role assignment.');
            return false;
        }

        try {
            if (class_exists(\Core\Database::class) && \Core\Database::getInstance()->isConnected()) {
                $userModel = new \App\Models\User();
                $dbUser = $userModel->findByIdWithAuthState($userId);

                if (!$dbUser) {
                    if (!empty($sessionUser['auth_fingerprint'])) {
                        self::invalidateAuthenticatedUser('Your user account is no longer available. Please sign in again.');
                        return false;
                    }
                    return true;
                }

                $dbRole = strtolower(trim((string)($dbUser['role'] ?? '')));
                $dbStatus = trim((string)($dbUser['status'] ?? ''));
                $empStatus = !empty($dbUser['employee_id']) && isset($dbUser['employment_status'])
                    ? trim((string)$dbUser['employment_status'])
                    : 'Active';

                $isStatusActive = ($dbStatus === 'Active' && $empStatus === 'Active');
                $isRoleValid = ($dbRole === 'hr' || $dbRole === 'employee') && ($dbRole === $sessionRole);
                $isFingerprintValid = empty($sessionUser['auth_fingerprint'])
                    || hash_equals((string)$sessionUser['auth_fingerprint'], self::computeAuthFingerprint($dbUser));

                if (!$isStatusActive || !$isRoleValid || !$isFingerprintValid) {
                    self::invalidateAuthenticatedUser('Your session is no longer valid due to an account or security update. Please sign in again.');
                    return false;
                }
            }
        } catch (\Throwable $e) {
            error_log('[Session::verifyAuthenticatedAccountState] ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Invalidate the authenticated user state, revoke real-time tickets, and rotate session ID.
     */
    public static function invalidateAuthenticatedUser(?string $flashError = null): void
    {
        if (class_exists(\App\Services\RealtimeTicketService::class)) {
            \App\Services\RealtimeTicketService::revokeCurrentSession();
        }

        unset($_SESSION['user'], $_SESSION['_realtime_ref'], $_SESSION['last_activity'], $_SESSION['login_time']);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        if ($flashError !== null && $flashError !== '') {
            self::flash('error', $flashError);
        }
    }

    /**
     * Background requests (real-time ticket refresh, notification history
     * sync) send "X-HRIS-Background: 1" so they do not extend the inactivity
     * timeout. The header can only withhold a refresh, never grant access.
     */
    public static function isBackgroundRequest(): bool
    {
        return ($_SERVER['HTTP_X_HRIS_BACKGROUND'] ?? '') === '1';
    }

    /**
     * Regenerate session ID to prevent session fixation attacks.
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return session_regenerate_id($deleteOldSession);
        }
        return false;
    }

    /**
     * Destroy active session and invalidate cookie.
     */
    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies') && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'] ?? '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$started = false;
    }

    /**
     * Store a value in the session.
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Retrieve a value from the session.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Determine if a session key exists.
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a key from the session.
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Store a one-time flash message.
     */
    public static function flash(string $key, mixed $value): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Retrieve and clear a one-time flash message.
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::start();
        if (isset($_SESSION['_flash'][$key])) {
            $value = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $value;
        }
        return $default;
    }

    /**
     * Check if a flash message exists.
     */
    public static function hasFlash(string $key): bool
    {
        self::start();
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Retrieve the active CSRF token.
     */
    public static function csrfToken(): string
    {
        self::start();
        return $_SESSION['_csrf_token'] ?? '';
    }

    /**
     * Verify a submitted CSRF token using timing-safe comparison.
     */
    public static function verifyCsrfToken(?string $token): bool
    {
        self::start();
        if (empty($token) || empty($_SESSION['_csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $token);
    }
}
