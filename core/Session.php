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

            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

            session_set_cookie_params([
                'lifetime' => 0, // Expire when browser closes; server enforces inactivity timeout
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_start();
        }

        self::$started = true;

        // 2. Enforce inactivity timeout and CSRF initialization
        self::checkInactivityTimeout();
    }

    /**
     * Enforce inactivity timeout and initialize CSRF token if absent.
     */
    public static function checkInactivityTimeout(): void
    {
        if (isset($_SESSION['user']) && isset($_SESSION['last_activity'])) {
            $timeoutSeconds = (int)config('config.session_lifetime', 1800); // 30 minutes default
            $inactiveDuration = time() - (int)$_SESSION['last_activity'];

            if ($inactiveDuration > $timeoutSeconds) {
                // Invalidate session due to inactivity
                unset($_SESSION['user']);
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }
                self::flash('timeout', 'Your session has expired due to 30 minutes of inactivity. Please sign in again.');
            } else {
                // Refresh activity timestamp
                $_SESSION['last_activity'] = time();
            }
        }

        // 3. Initialize cryptographically secure CSRF token if absent
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
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

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
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
