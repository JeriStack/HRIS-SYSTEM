<?php

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for Group 1 request-security helpers (core/functions.php):
 * client IP resolution behind the Nginx proxy (SEC-02), email masking and hashed
 * login identifiers for audit logs (LOG-01), and safe 500 responses (ARCH-01 / API-01).
 */
class RequestSecurityHelpersTest extends TestCase
{
    private const DOCKER_PROXIES = ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'];

    public function test_client_ip_uses_x_real_ip_when_peer_is_trusted_proxy(): void
    {
        $server = ['REMOTE_ADDR' => '172.18.0.8', 'HTTP_X_REAL_IP' => '203.0.113.9'];

        $this->assertSame('203.0.113.9', client_ip($server, self::DOCKER_PROXIES));
    }

    public function test_client_ip_ignores_forwarded_headers_from_untrusted_peer(): void
    {
        $server = [
            'REMOTE_ADDR'          => '203.0.113.50',
            'HTTP_X_REAL_IP'       => '1.2.3.4',
            'HTTP_X_FORWARDED_FOR' => '5.6.7.8',
        ];

        $this->assertSame('203.0.113.50', client_ip($server, self::DOCKER_PROXIES), 'A direct client must not be able to spoof its IP.');
    }

    public function test_client_ip_falls_back_to_rightmost_forwarded_for_entry(): void
    {
        $server = ['REMOTE_ADDR' => '172.18.0.8', 'HTTP_X_FORWARDED_FOR' => '9.9.9.9, 198.51.100.7'];

        $this->assertSame('198.51.100.7', client_ip($server, self::DOCKER_PROXIES));
    }

    public function test_client_ip_rejects_malformed_header_values(): void
    {
        $server = ['REMOTE_ADDR' => '172.18.0.8', 'HTTP_X_REAL_IP' => "not-an-ip'; DROP TABLE users;--"];

        $this->assertSame('172.18.0.8', client_ip($server, self::DOCKER_PROXIES));
    }

    public function test_client_ip_returns_null_without_valid_peer(): void
    {
        $this->assertNull(client_ip([], self::DOCKER_PROXIES));
        $this->assertNull(client_ip(['REMOTE_ADDR' => 'garbage'], self::DOCKER_PROXIES));
    }

    public function test_distinct_clients_behind_same_proxy_resolve_to_distinct_ips(): void
    {
        $a = client_ip(['REMOTE_ADDR' => '172.18.0.8', 'HTTP_X_REAL_IP' => '198.51.100.1'], self::DOCKER_PROXIES);
        $b = client_ip(['REMOTE_ADDR' => '172.18.0.8', 'HTTP_X_REAL_IP' => '198.51.100.2'], self::DOCKER_PROXIES);

        $this->assertNotSame($a, $b, 'Per-IP rate limiting must not collapse every client into the proxy address.');
    }

    public function test_mask_email_hides_local_part_and_rejects_invalid_input(): void
    {
        $this->assertSame('m****s@company.local', mask_email('maria.santos@company.local'));
        $this->assertSame('a*@x.test', mask_email('ab@x.test'));
        $this->assertSame('[invalid-email]', mask_email('<script>alert(1)</script>'));
        $this->assertSame('[invalid-email]', mask_email(null));
    }

    public function test_login_identifier_key_is_deterministic_case_insensitive_and_non_reversible(): void
    {
        $key = login_identifier_key('Maria.Santos@Company.local');

        $this->assertSame($key, login_identifier_key('  maria.santos@company.local '));
        $this->assertStringStartsWith('eh:', $key);
        $this->assertStringNotContainsString('maria', $key);
        $this->assertLessThanOrEqual(50, strlen($key), 'Must fit activity_logs.entity_id VARCHAR(50).');
        $this->assertNotSame($key, login_identifier_key('other@company.local'));
    }

    public function test_server_error_payload_hides_details_outside_development(): void
    {
        $e = new \RuntimeException('SQLSTATE secret detail /var/www/html/app/models/User.php');

        $html = server_error_payload($e, false, false);
        $this->assertStringContainsString('500 Internal Error', $html['body']);
        $this->assertStringNotContainsString('SQLSTATE', $html['body']);
        $this->assertStringNotContainsString('User.php', $html['body']);

        $json = server_error_payload($e, true, false);
        $this->assertSame('application/json; charset=UTF-8', $json['content_type']);
        $decoded = json_decode($json['body'], true);
        $this->assertSame(['status' => 'error', 'code' => 500, 'message' => 'Internal server error.'], $decoded);
    }

    public function test_server_error_payload_includes_details_only_when_requested(): void
    {
        $e = new \RuntimeException('debuggable failure');

        $decoded = json_decode(server_error_payload($e, true, true)['body'], true);
        $this->assertSame('debuggable failure', $decoded['debug']['message']);
        $this->assertStringContainsString('debuggable failure', server_error_payload($e, false, true)['body']);
    }

    public function test_server_error_payload_escapes_exception_message_in_html(): void
    {
        $e = new \RuntimeException('<img src=x onerror=alert(1)>');

        $body = server_error_payload($e, false, true)['body'];
        $this->assertStringNotContainsString('<img src=x', $body);
        $this->assertStringContainsString('&lt;img src=x', $body);
    }

    public function test_request_wants_json_for_api_paths_and_json_accept(): void
    {
        $this->assertTrue(request_wants_json(['REQUEST_URI' => '/api/v1/employees']));
        $this->assertTrue(request_wants_json(['REQUEST_URI' => '/leaves', 'HTTP_ACCEPT' => 'application/json']));
        $this->assertFalse(request_wants_json(['REQUEST_URI' => '/hr/dashboard', 'HTTP_ACCEPT' => 'text/html']));
    }

    public function test_unset_app_env_defaults_to_production(): void
    {
        $previous = getenv('APP_ENV');
        putenv('APP_ENV');

        try {
            $config = require dirname(__DIR__, 3) . '/config/config.php';
            $this->assertSame('production', $config['app_env'], 'An unset APP_ENV must fail safe to production.');
        } finally {
            putenv($previous === false ? 'APP_ENV' : "APP_ENV={$previous}");
        }
    }
}
