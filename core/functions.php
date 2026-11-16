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

if (!function_exists('absolute_url')) {
    /**
     * Build an absolute http(s) URL suitable for outbound emails and external links.
     *
     * @param string $path Relative route path
     * @param array<string, mixed> $query Optional query parameters
     * @return string
     */
    function absolute_url(string $path = '', array $query = []): string
    {
        $resolved = url($path);
        if (!preg_match('#^https?://#i', $resolved)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $rawHost = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost:8080'));
            $host = preg_match('/^[a-zA-Z0-9.\-:\[\]]+$/', $rawHost) ? $rawHost : 'localhost:8080';
            $resolved = $scheme . '://' . $host . '/' . ltrim($resolved, '/');
        }

        if ($query !== []) {
            $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            $resolved .= (str_contains($resolved, '?') ? '&' : '?') . $qs;
        }

        return $resolved;
    }
}

if (!function_exists('asset')) {
    /**
     * Generate an asset URL with automatic cache-busting filemtime query parameter.
     */
    function asset(string $path): string
    {
        $clean = ltrim($path, '/');
        $urlPath = url('assets/' . $clean);
        if (!str_contains($clean, '?')) {
            $diskPath = __DIR__ . '/../public/assets/' . $clean;
            if (is_file($diskPath)) {
                $mtime = @filemtime($diskPath);
                if ($mtime !== false) {
                    return $urlPath . '?v=' . $mtime;
                }
            }
        }
        return $urlPath;
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

if (!function_exists('auth_user')) {
    /**
     * Alias for currentUser() to retrieve the authenticated user record.
     */
    function auth_user(): ?array
    {
        return currentUser();
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve previously submitted form input value or default fallback.
     */
    function old(string $key, mixed $default = ''): mixed
    {
        $oldInput = \Core\Session::get('_old_input', []);
        if (is_array($oldInput) && array_key_exists($key, $oldInput)) {
            return $oldInput[$key];
        }
        return $_POST[$key] ?? $default;
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

if (!function_exists('icon')) {
    /**
     * Render a clean, accessible inline stroke SVG icon from the enterprise icon set.
     */
    function icon(string $name, int $size = 18, string $class = ''): string
    {
        $paths = [
            'dashboard'      => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
            'user'           => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'users'          => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'calendar'       => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
            'calendar-check' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/>',
            'clock'          => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'file-text'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/>',
            'file-plus'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>',
            'plus'           => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
            'plus-circle'    => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
            'send'           => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
            'bell'           => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
            'bell-off'       => '<path d="M13.73 21a2 2 0 0 1-3.46 0"/><path d="M18.63 13A17.89 17.89 0 0 1 18 8"/><path d="M6.26 6.26A5.86 5.86 0 0 0 6 8c0 7-3 9-3 9h14"/><path d="M18 8a6 6 0 0 0-9.33-5"/><line x1="1" y1="1" x2="23" y2="23"/>',
            'log-out'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
            'check'          => '<polyline points="20 6 9 17 4 12"/>',
            'check-circle'   => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
            'x'              => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
            'trash'          => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
            'x-circle'       => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
            'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            'alert-circle'   => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
            'info'           => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
            'arrow-left'     => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
            'arrow-right'    => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
            'chevron-right'  => '<polyline points="9 18 15 12 9 6"/>',
            'briefcase'      => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
            'building'       => '<rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
            'mail'           => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
            'phone'          => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'map-pin'        => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
            'shield'         => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'search'         => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
            'sun'            => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
            'moon'           => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
            'menu'             => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
            'sidebar'          => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><path d="m15 15-3-3 3-3"/>',
            'panel-left-close' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><path d="m15 15-3-3 3-3"/>',
            'panel-left-open'  => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><path d="m13 9 3 3-3 3"/>',
            'eye'              => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
            'layers'           => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
            'balances'         => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
            'peso'             => '<path d="M8 19V5h5a4 4 0 0 1 0 8H8"/><line x1="5" y1="7.5" x2="19" y2="7.5"/><line x1="5" y1="10.5" x2="19" y2="10.5"/>',
            'bar-chart'        => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
            'activity'         => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
            'lock'             => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'megaphone'        => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
            'pin'              => '<line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/>',
            'paperclip'        => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
            'archive'          => '<rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
            'edit'             => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
            'settings'         => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        ];

        $inner = $paths[$name] ?? $paths['info'];
        $cls = trim('svg-icon ' . $class);
        return sprintf(
            '<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
            htmlspecialchars($cls, ENT_QUOTES, 'UTF-8'),
            $size,
            $size,
            $inner
        );
    }
}



