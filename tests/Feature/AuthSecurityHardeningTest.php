<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StepUpAuthService;
use Core\Database;
use Core\Permission;
use Core\Session;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Group 1 regression tests (authentication & security hardening).
 *
 * Runs only against the isolated hris_test database (enforced by tests/bootstrap.php).
 * Every activity_logs row created here is removed in tearDown.
 */
class AuthSecurityHardeningTest extends TestCase
{
    private ?PDO $pdo = null;
    private int $activityLogHighWater = 0;
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        Session::destroy();
        Session::start();

        if (Database::getInstance()->isConnected()) {
            $this->pdo = Database::getInstance()->getConnection();
            $this->activityLogHighWater = (int)$this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM activity_logs')->fetchColumn();
        }
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare('DELETE FROM activity_logs WHERE id > :hw');
            $stmt->execute(['hw' => $this->activityLogHighWater]);
        }

        $_SERVER = $this->serverBackup;
        Session::remove('user');
        Session::destroy();
    }

    private function requireDatabase(): PDO
    {
        if ($this->pdo === null) {
            $this->markTestSkipped('Database connection is not available in this environment.');
        }
        return $this->pdo;
    }

    private function insertActivity(?int $userId, string $action, string $entityId, string $ip): void
    {
        $stmt = $this->requireDatabase()->prepare(
            "INSERT INTO activity_logs (user_id, action, description, entity_type, entity_id, ip_address, user_agent, created_at)
             VALUES (:user_id, :action, 'Group 1 regression fixture', 'USER', :entity_id, :ip, 'phpunit', NOW())"
        );
        $stmt->execute(['user_id' => $userId, 'action' => $action, 'entity_id' => $entityId, 'ip' => $ip]);
    }

    private function anyActiveUserId(): int
    {
        $id = (int)$this->requireDatabase()->query("SELECT id FROM users WHERE status = 'Active' ORDER BY id LIMIT 1")->fetchColumn();
        if ($id <= 0) {
            $this->markTestSkipped('No active user fixture exists in hris_test.');
        }
        return $id;
    }

    // ---------------------------------------------------------------------
    // LOG-01 + SEC-02: login rate limiting without persisting raw emails
    // ---------------------------------------------------------------------

    public function test_unknown_account_failures_are_rate_limited_by_hashed_identifier(): void
    {
        $email = 'nobody-' . bin2hex(random_bytes(4)) . '@example.test';
        $max = (int)config('config.login_rate_limit_max_account', 5);

        for ($i = 0; $i < $max; $i++) {
            $this->insertActivity(null, 'AUTH_FAILED', login_identifier_key($email), '198.51.100.' . (10 + $i));
        }

        $result = (new User())->checkLoginRateLimit($email, '198.51.100.200');

        $this->assertTrue($result['limited'], 'Hashed-identifier failures must count toward the per-account limit.');
        $this->assertGreaterThan(0, $result['retry_after']);
    }

    public function test_ip_limit_is_scoped_to_real_client_ip_not_proxy(): void
    {
        $maxIp = (int)config('config.login_rate_limit_max_ip', 15);
        $attackerIp = '198.51.100.66';

        for ($i = 0; $i < $maxIp; $i++) {
            $this->insertActivity(null, 'AUTH_FAILED', login_identifier_key("victim{$i}@example.test"), $attackerIp);
        }

        $users = new User();
        $this->assertTrue($users->checkLoginRateLimit('fresh-a@example.test', $attackerIp)['limited'], 'The abusive IP must be limited.');
        $this->assertFalse(
            $users->checkLoginRateLimit('fresh-b@example.test', '198.51.100.67')['limited'],
            'A different real client IP must not inherit another client\'s lockout.'
        );
    }

    public function test_activity_log_records_real_client_ip_behind_trusted_proxy(): void
    {
        $pdo = $this->requireDatabase();
        $_SERVER['REMOTE_ADDR'] = '172.18.0.8';
        $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.77';

        (new User())->logActivity(null, 'AUTH_FAILED', 'Group 1 IP fixture', 'USER', login_identifier_key('ip-fixture@example.test'));

        $ip = $pdo->query("SELECT ip_address FROM activity_logs WHERE id > {$this->activityLogHighWater} ORDER BY id DESC LIMIT 1")->fetchColumn();
        $this->assertSame('203.0.113.77', $ip);
    }

    public function test_auth_controllers_never_interpolate_raw_emails_into_audit_logs(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['app/controllers/AuthController.php', 'app/controllers/Api/AuthController.php'] as $file) {
            $source = (string)file_get_contents("{$root}/{$file}");
            $this->assertDoesNotMatchRegularExpression('/\{\$(email|recipientEmail)\}/', $source, "{$file} must mask emails before logging.");
            $this->assertStringNotContainsString("'USER', \$email", $source, "{$file} must not store raw emails as entity_id.");
            $this->assertStringNotContainsString("\$_SERVER['REMOTE_ADDR']", $source, "{$file} must resolve the client IP through client_ip().");
        }
    }

    // ---------------------------------------------------------------------
    // SEC-05: step-up password guessing is throttled
    // ---------------------------------------------------------------------

    public function test_step_up_locks_after_repeated_failures_without_checking_password(): void
    {
        $userId = $this->anyActiveUserId();
        Session::set('user', ['id' => $userId, 'role' => 'hr', 'last_mfa_verified_at' => time() - 3600]);

        for ($i = 0; $i < StepUpAuthService::MAX_FAILURES; $i++) {
            $this->insertActivity($userId, 'STEP_UP_FAILED', (string)$userId, '198.51.100.90');
        }

        $result = (new StepUpAuthService())->verify('TEST_OPERATION', 'any-password-even-correct');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Too many incorrect password confirmations', (string)$result['error']);

        $locked = (int)$this->requireDatabase()
            ->query("SELECT COUNT(*) FROM activity_logs WHERE id > {$this->activityLogHighWater} AND action = 'STEP_UP_LOCKED' AND user_id = {$userId}")
            ->fetchColumn();
        $this->assertSame(1, $locked, 'A lockout must be audited.');
    }

    public function test_step_up_below_threshold_still_requests_password(): void
    {
        $userId = $this->anyActiveUserId();
        Session::set('user', ['id' => $userId, 'role' => 'hr', 'last_mfa_verified_at' => time() - 3600]);

        $this->insertActivity($userId, 'STEP_UP_FAILED', (string)$userId, '198.51.100.91');

        $result = (new StepUpAuthService())->verify('TEST_OPERATION', null);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('confirm your current password', (string)$result['error']);
    }

    // ---------------------------------------------------------------------
    // SEC-01: deactivation forms render the step-up field when required
    // ---------------------------------------------------------------------

    private function renderStepUpPartial(array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__, 2) . '/app/views/partials/step_up_field.php';
        return (string)ob_get_clean();
    }

    public function test_step_up_field_renders_required_password_input_when_window_elapsed(): void
    {
        $html = $this->renderStepUpPartial([
            'stepUpRequired' => true,
            'stepUpFieldId'  => 'deactivate_current_password',
            'stepUpOptional' => false,
        ]);

        $this->assertStringContainsString('name="current_password"', $html);
        $this->assertStringContainsString('id="deactivate_current_password"', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*\brequired\b/', $html);
        $this->assertStringContainsString('<label for="deactivate_current_password"', $html);
    }

    public function test_step_up_field_is_hidden_within_recent_verification_window(): void
    {
        $this->assertSame('', trim($this->renderStepUpPartial(['stepUpRequired' => false])));
    }

    public function test_employee_forms_include_the_step_up_partial(): void
    {
        $root = dirname(__DIR__, 2) . '/app/views/employees';
        foreach (['index.php', 'show.php', 'edit.php'] as $view) {
            $this->assertStringContainsString(
                "partials/step_up_field.php",
                (string)file_get_contents("{$root}/{$view}"),
                "employees/{$view} must render the step-up password field."
            );
        }
    }

    // ---------------------------------------------------------------------
    // ARCH-05: logout is POST + CSRF only
    // ---------------------------------------------------------------------

    public function test_logout_is_not_routed_for_get_requests(): void
    {
        $routes = (string)file_get_contents(dirname(__DIR__, 2) . '/public/index.php');

        $this->assertDoesNotMatchRegularExpression('/\$router->get\(\s*[\'"]\/logout[\'"]/', $routes, 'GET /logout must not be routed.');
        $this->assertMatchesRegularExpression(
            '/\$router->post\(\s*[\'"]\/logout[\'"].*CsrfMiddleware::class/',
            $routes,
            'POST /logout must be protected by CsrfMiddleware.'
        );
    }

    public function test_logout_forms_in_views_use_post_with_csrf(): void
    {
        $root = dirname(__DIR__, 2) . '/app/views';
        foreach (['partials/sidebar.php', 'dashboard/hr_employees.php'] as $view) {
            $source = (string)file_get_contents("{$root}/{$view}");
            $this->assertMatchesRegularExpression('/<form method="POST" action="<\?= url\(\'\/logout\'\) \?>"/', $source, "{$view} must post to /logout.");
            $this->assertStringContainsString('csrf_field()', $source);
            $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/logout/', $source, "{$view} must not link to GET /logout.");
        }

        // Top navbar must never expose a GET /logout link either.
        $navbarSource = (string)file_get_contents("{$root}/partials/navbar.php");
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/logout/', $navbarSource, 'partials/navbar.php must not link to GET /logout.');
    }

    // ---------------------------------------------------------------------
    // ARCH-07: Three-role capability matrix authorization
    // ---------------------------------------------------------------------

    public function test_three_role_permission_matrix_authorizes_roles_appropriately(): void
    {
        $capabilities = Permission::getAllCapabilities();

        $this->assertArrayHasKey('admin_dashboard', $capabilities);
        $this->assertTrue(Permission::can('admin', 'admin_dashboard'));
        $this->assertTrue(Permission::can('admin', 'manage_users'));
        $this->assertTrue(Permission::can('admin', 'assign_roles'));
        $this->assertFalse(Permission::can('admin', 'hr_dashboard'));
        $this->assertFalse(Permission::can('hr', 'admin_dashboard'));
        $this->assertFalse(Permission::can('hr', 'manage_users'));
        $this->assertFalse(Permission::can('employee', 'admin_dashboard'));
    }
}
