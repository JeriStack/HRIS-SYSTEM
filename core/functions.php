<?php
/**
 * Global Core Helper Functions
 * Employee HRIS & Leave Management System
 */

if (!function_exists('config')) {
    /**
     * Retrieve configuration settings by dot notation or top-level key.
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $configs = [];

        $parts = explode('.', $key);
        $file = $parts[0];

        if (!isset($configs[$file])) {
            $filePath = __DIR__ . '/../config/' . $file . '.php';
            if (file_exists($filePath)) {
                $configs[$file] = require $filePath;
            } else {
                $configs[$file] = [];
            }
        }

        if (count($parts) === 1) {
            return $configs[$file] ?? $default;
        }

        $current = $configs[$file];
        for ($i = 1; $i < count($parts); $i++) {
            $segment = $parts[$i];
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                return $default;
            }
        }

        return $current;
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML output safely using htmlspecialchars.
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * Generate a relative or absolute URL based on the application base path.
     */
    function url(string $path = ''): string
    {
        $baseUrl = rtrim((string)config('config.base_url', ''), '/');
        $cleanPath = ltrim($path, '/');
        return $cleanPath ? $baseUrl . '/' . $cleanPath : $baseUrl;
    }
}

if (!function_exists('asset')) {
    /**
     * Generate an asset URL.
     */
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('redirect')) {
    /**
     * Perform an HTTP redirect and terminate script execution.
     */
    function redirect(string $url, int $statusCode = 302): void
    {
        // If not a full URL with scheme, build application URL
        if (!preg_match('#^https?://#i', $url)) {
            $url = url($url);
        }

        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }
}

if (!function_exists('json_response')) {
    /**
     * Output a JSON response with status code and headers.
     */
    function json_response(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('session')) {
    /**
     * Session helper to get or set session values.
     */
    function session(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_SESSION ?? [];
        }
        return \Core\Session::get($key, $default);
    }
}

if (!function_exists('auth')) {
    /**
     * Retrieve authenticated user payload from session.
     */
    function auth(): ?array
    {
        return \Core\Session::get('user');
    }
}

if (!function_exists('isAuthenticated')) {
    /**
     * Determine if current visitor is authenticated.
     */
    function isAuthenticated(): bool
    {
        $user = \Core\Session::get('user');
        return !empty($user['id']);
    }
}

if (!function_exists('currentUser')) {
    /**
     * Retrieve the current authenticated user record.
     */
    function currentUser(): ?array
    {
        return \Core\Session::get('user');
    }
}

if (!function_exists('currentUserId')) {
    /**
     * Retrieve current user ID.
     */
    function currentUserId(): ?int
    {
        $user = \Core\Session::get('user');
        return isset($user['id']) ? (int)$user['id'] : null;
    }
}

if (!function_exists('currentUserRole')) {
    /**
     * Retrieve current user role ('admin', 'hr', 'employee').
     */
    function currentUserRole(): ?string
    {
        $user = \Core\Session::get('user');
        return $user['role'] ?? null;
    }
}

if (!function_exists('hasRole')) {
    /**
     * Check if authenticated user possesses any of the given roles.
     */
    function hasRole(array|string $roles): bool
    {
        $currentRole = currentUserRole();
        if (!$currentRole) {
            return false;
        }

        $roles = (array)$roles;
        $normalized = array_map('strtolower', $roles);
        return in_array(strtolower($currentRole), $normalized, true);
    }
}

if (!function_exists('can')) {
    /**
     * Check if authenticated user possesses authorization for a named capability.
     */
    function can(string $capability): bool
    {
        return \Core\Permission::can(currentUserRole(), $capability);
    }
}


if (!function_exists('csrf_token')) {
    /**
     * Retrieve current CSRF token from active session.
     */
    function csrf_token(): string
    {
        return \Core\Session::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate hidden HTML input tag containing active CSRF token.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Verify submitted CSRF token against session token.
     */
    function verify_csrf_token(?string $token): bool
    {
        return \Core\Session::verifyCsrfToken($token);
    }
}

