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
        if (getenv('APP_ENV') === 'testing' || (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING)) {
            return;
        }
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
        if (getenv('APP_ENV') === 'testing' || (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING)) {
            return;
        }
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
     * Retrieve current user role ('admin', 'hr', or 'employee').
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


if (!function_exists('trusted_proxy_ranges')) {
    /**
     * Reverse-proxy peers whose X-Real-IP / X-Forwarded-For headers are trusted.
     * Configurable through TRUSTED_PROXIES (comma-separated IPs or CIDR blocks).
     *
     * @return string[]
     */
    function trusted_proxy_ranges(): array
    {
        $raw = getenv('TRUSTED_PROXIES');
        if ($raw === false || trim($raw) === '') {
            $raw = '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16';
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn(string $r): bool => $r !== ''));
    }
}

if (!function_exists('client_ip')) {
    /**
     * Resolve the real client IP address.
     *
     * Forwarded headers are honoured only when the direct TCP peer (REMOTE_ADDR) is a
     * trusted reverse proxy (e.g. the Nginx container). A client connecting directly can
     * never spoof its address through X-Real-IP / X-Forwarded-For.
     *
     * @param array|null $server Server parameters (defaults to $_SERVER)
     * @param string[]|null $trustedProxies Trusted proxy ranges (defaults to trusted_proxy_ranges())
     */
    function client_ip(?array $server = null, ?array $trustedProxies = null): ?string
    {
        $server ??= $_SERVER;
        $remote = trim((string)($server['REMOTE_ADDR'] ?? ''));
        if ($remote === '' || filter_var($remote, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $trustedProxies ??= trusted_proxy_ranges();
        if (!\App\Realtime\IpRange::matchesAny($remote, $trustedProxies)) {
            return $remote;
        }

        $realIp = trim((string)($server['HTTP_X_REAL_IP'] ?? ''));
        if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP) !== false) {
            return $realIp;
        }

        // Fall back to the right-most X-Forwarded-For entry (the one appended by our proxy)
        $forwarded = trim((string)($server['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            $parts = array_map('trim', explode(',', $forwarded));
            $last = (string)end($parts);
            if ($last !== '' && filter_var($last, FILTER_VALIDATE_IP) !== false) {
                return $last;
            }
        }

        return $remote;
    }
}

if (!function_exists('mask_email')) {
    /**
     * Mask an email address for logs and UI (e.g. m****n@company.local).
     * Invalid input returns a fixed placeholder so arbitrary attacker strings are never stored.
     */
    function mask_email(?string $email): string
    {
        $email = trim((string)$email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return '[invalid-email]';
        }

        [$name, $domain] = explode('@', $email, 2);
        $len = strlen($name);
        $maskedName = $len <= 2
            ? substr($name, 0, 1) . '*'
            : substr($name, 0, 1) . str_repeat('*', min(4, $len - 2)) . substr($name, -1);

        return $maskedName . '@' . strtolower($domain);
    }
}

if (!function_exists('login_identifier_key')) {
    /**
     * Non-reversible, deterministic key for a submitted login identifier.
     * Used as activity_logs.entity_id for failed attempts against unknown accounts so
     * login rate limiting works without persisting attacker-supplied email addresses.
     * Output length (43 chars) fits activity_logs.entity_id VARCHAR(50).
     */
    function login_identifier_key(string $email): string
    {
        return 'eh:' . substr(hash('sha256', 'nexora-login|' . strtolower(trim($email))), 0, 40);
    }
}

if (!function_exists('server_error_payload')) {
    /**
     * Build the response for an unhandled server error without emitting it.
     *
     * JSON envelope for API/JSON requests, HTML page otherwise. Exception details
     * (message, file, trace) are included only when $showDetails is true, which callers
     * must restrict to APP_ENV=development.
     *
     * @return array{content_type: string, body: string}
     */
    function server_error_payload(?\Throwable $e, bool $wantsJson, bool $showDetails): array
    {
        if ($wantsJson) {
            $payload = [
                'status'  => 'error',
                'code'    => 500,
                'message' => 'Internal server error.',
            ];
            if ($showDetails && $e !== null) {
                $payload['debug'] = [
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ];
            }

            return [
                'content_type' => 'application/json; charset=UTF-8',
                'body'         => (string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
        }

        $appName = e((string)config('config.app_name', 'NEXORA HR'));
        $details = '';
        if ($showDetails && $e !== null) {
            $details = '<div style="text-align:left;margin-top:1.5rem;">'
                . '<p><strong>Message:</strong> ' . e($e->getMessage()) . '</p>'
                . '<p><strong>File:</strong> ' . e($e->getFile()) . ' (Line ' . (int)$e->getLine() . ')</p>'
                . '<pre style="background:#F8FAFC;border:1px solid #E2E8F0;padding:1rem;border-radius:6px;overflow-x:auto;font-size:0.8rem;">'
                . e($e->getTraceAsString()) . '</pre></div>';
        }

        $body = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>500 Internal Error - ' . $appName . '</title>'
            . '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#F8FAFC;color:#0F172A;'
            . 'display:flex;align-items:center;justify-content:center;min-height:80vh;margin:0;padding:1rem;text-align:center;}'
            . '.card{background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:2.5rem;max-width:' . ($details !== '' ? '900px' : '480px') . ';}'
            . 'a{display:inline-block;background:#2563EB;color:#FFFFFF;text-decoration:none;padding:10px 20px;border-radius:6px;font-weight:600;}</style>'
            . '</head><body><div class="card"><h1 style="color:#B91C1C;">500 Internal Error</h1>'
            . '<p style="color:#475569;">An unexpected error occurred while processing your request. Please try again later.</p>'
            . '<a href="' . e(url('/')) . '">Return to Home</a>'
            . $details
            . '</div></body></html>';

        return ['content_type' => 'text/html; charset=UTF-8', 'body' => $body];
    }
}

if (!function_exists('request_wants_json')) {
    /**
     * Whether the current request expects a JSON response (API path or JSON Accept header).
     */
    function request_wants_json(?array $server = null): bool
    {
        $server ??= $_SERVER;
        return str_contains((string)($server['REQUEST_URI'] ?? ''), '/api/')
            || str_contains((string)($server['HTTP_ACCEPT'] ?? ''), 'application/json');
    }
}

if (!function_exists('render_server_error')) {
    /**
     * Log and emit a safe 500 response for an unhandled error.
     */
    function render_server_error(?\Throwable $e): void
    {
        if ($e !== null) {
            error_log('[Application Error] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }

        $showDetails = config('config.app_env', 'production') === 'development';
        $response = server_error_payload($e, request_wants_json(), $showDetails);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: ' . $response['content_type']);
        }
        echo $response['body'];
    }
}

if (!function_exists('can')) {
    /**
     * Determine if the currently authenticated user possesses a given permission.
     */
    function can(string $permission): bool
    {
        return \Core\Permission::can(currentUser(), $permission);
    }
}

if (!function_exists('has_role')) {
    /**
     * Determine if the currently authenticated user possesses a given role.
     */
    function has_role(string $role): bool
    {
        return \Core\Permission::hasRole(currentUser(), $role);
    }
}

if (!function_exists('dispatch_job')) {
    /**
     * Dispatch an asynchronous background job to the MySQL queue.
     *
     * @param array<string, mixed> $payload
     */
    function dispatch_job(string $jobType, array $payload = [], string $queue = 'default', int $delay = 0): int
    {
        return \App\Queue\QueueService::getInstance()->push($jobType, $payload, $queue, $delay);
    }
}

if (!function_exists('render_skeleton')) {
    /**
     * Render a reusable, accessible skeleton loading placeholder.
     *
     * @param string $component Component name (stat_card, table, card, chart, list, profile, form)
     * @param array<string, mixed> $options Rendering configuration options
     * @return string Safe HTML skeleton markup with accessibility attributes
     */
    function render_skeleton(string $component, array $options = []): string
    {
        $safeComponent = preg_replace('/[^a-zA-Z0-9_-]/', '', $component);
        $filePath = __DIR__ . '/../app/views/partials/skeletons/' . $safeComponent . '.php';

        if (!file_exists($filePath)) {
            return '';
        }

        extract($options, EXTR_SKIP);

        $loadingText = isset($options['loading_text']) ? (string)$options['loading_text'] : 'Loading content...';
        $isRaw = !empty($options['raw']);

        ob_start();
        include $filePath;
        $html = ob_get_clean() ?: '';

        if ($isRaw) {
            return $html;
        }

        return '<div class="skeleton-container" role="status" aria-busy="true">' .
               '<span class="sr-only">' . htmlspecialchars($loadingText, ENT_QUOTES, 'UTF-8') . '</span>' .
               $html .
               '</div>';
    }
}



