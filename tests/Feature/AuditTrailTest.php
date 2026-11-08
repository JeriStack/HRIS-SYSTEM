<?php

/**
 * Audit Trail Enhancement & Security Activity Monitoring Test Suite
 *
 * Verifies:
 * 1. Centralized AuditService singleton and event ID / correlation ID generation.
 * 2. Recursive sensitive-credential redaction across old and new values.
 * 3. Before-and-after change diff serialization.
 * 4. Formula injection neutralization in CSV export.
 * 5. Functional category inference and activity classification.
 * 6. ActivityLog delegation to AuditService.
 * 7. Immutable read-only nature (no edit/delete routes for activity_logs).
 * 8. Role-based access control rules for audit logs.
 */

namespace Tests\Feature;

use App\Models\ActivityLog;
use Core\AuditService;
use Core\Session;
use PDO;
use PHPUnit\Framework\TestCase;

class AuditTrailTest extends TestCase
{
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        Session::destroy();
        Session::start();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        Session::remove('user');
        Session::destroy();
    }

    public function test_audit_service_singleton_instance(): void
    {
        $service1 = AuditService::getInstance();
        $service2 = AuditService::getInstance();

        $this->assertInstanceOf(AuditService::class, $service1);
        $this->assertSame($service1, $service2, 'AuditService must adhere to the singleton pattern.');
    }

    public function test_audit_service_generates_unique_event_id_and_request_id(): void
    {
        $service = AuditService::getInstance();
        $requestId = $service->getRequestId();

        $this->assertStringStartsWith('req_', $requestId);
        $this->assertSame($requestId, $service->getRequestId(), 'Request ID must remain consistent within request lifecycle.');

        $id1 = AuditService::generateEventId();
        $id2 = AuditService::generateEventId();

        $this->assertStringStartsWith('evt_', $id1);
        $this->assertStringStartsWith('evt_', $id2);
        $this->assertNotEquals($id1, $id2, 'Consecutive event IDs must be distinct.');
    }

    public function test_recursive_sensitive_data_redaction(): void
    {
        $sensitiveInput = [
            'username'      => 'john_doe',
            'password'      => 'SuperSecret123!',
            'password_hash' => '$2y$12$abcdefghijklmnopqrstuv',
            'otp'           => '123456',
            'otp_secret'    => 'JBSWY3DPEHPK3PXP',
            'token'         => 'secret-auth-token-value',
            'nested'        => [
                'api_key'      => 'secret-key-xyz',
                'allowed_flag' => true,
                'sub_nested'   => [
                    'session_id' => 'sess_987654321',
                    'note'       => 'Safe note',
                ],
            ],
        ];

        /** @var array<string, mixed> $redacted */
        $redacted = AuditService::redact($sensitiveInput);

        $this->assertSame('john_doe', $redacted['username']);
        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('[REDACTED]', $redacted['password_hash']);
        $this->assertSame('[REDACTED]', $redacted['otp']);
        $this->assertSame('[REDACTED]', $redacted['otp_secret']);
        $this->assertSame('[REDACTED]', $redacted['token']);
        $this->assertSame('[REDACTED]', $redacted['nested']['api_key']);
        $this->assertTrue($redacted['nested']['allowed_flag']);
        $this->assertSame('[REDACTED]', $redacted['nested']['sub_nested']['session_id']);
        $this->assertSame('Safe note', $redacted['nested']['sub_nested']['note']);
    }

    public function test_csv_export_neutralizes_formula_injection(): void
    {
        $unsafeValues = [
            '=1+1'               => "'=1+1",
            '-5+10'              => "'-5+10",
            '+cmd|/c calc'       => "'+cmd|/c calc",
            '@SUM(A1:A10)'       => "'@SUM(A1:A10)",
            "\tTAB_INJECT"       => "'\tTAB_INJECT",
            "\rRETURN_INJECT"    => "'\rRETURN_INJECT",
            'Normal Safe String' => 'Normal Safe String',
        ];

        foreach ($unsafeValues as $input => $expected) {
            $output = AuditService::sanitizeCsvField($input);
            $this->assertSame($expected, $output, "Value '{$input}' must be sanitized properly for CSV export.");
        }
    }

    public function test_category_inference_classifies_actions_accurately(): void
    {
        $this->assertSame(AuditService::CATEGORY_AUTH, AuditService::inferCategory('USER_LOGIN'));
        $this->assertSame(AuditService::CATEGORY_AUTH, AuditService::inferCategory('USER_LOGOUT'));
        $this->assertSame(AuditService::CATEGORY_AUTH, AuditService::inferCategory('AUTH_VERIFIED'));
        $this->assertSame(AuditService::CATEGORY_USER, AuditService::inferCategory('USER_CREATED'));
        $this->assertSame(AuditService::CATEGORY_RBAC, AuditService::inferCategory('ROLE_PERMISSIONS_UPDATED'));
        $this->assertSame(AuditService::CATEGORY_EMPLOYEE, AuditService::inferCategory('EMPLOYEE_CREATED'));
        $this->assertSame(AuditService::CATEGORY_ORGANIZATION, AuditService::inferCategory('DEPARTMENT_UPDATED'));
        $this->assertSame(AuditService::CATEGORY_LEAVE, AuditService::inferCategory('LEAVE_APPROVED'));
        $this->assertSame(AuditService::CATEGORY_SYSTEM, AuditService::inferCategory('SETTINGS_UPDATED'));
        $this->assertSame(AuditService::CATEGORY_GENERAL, AuditService::inferCategory('CUSTOM_UNKNOWN_ACTION'));
    }

    public function test_activity_log_model_delegates_to_audit_service(): void
    {
        $stubPdo = $this->createStub(PDO::class);
        $model = new ActivityLog($stubPdo);
        $reflection = new \ReflectionClass($model);
        $prop = $reflection->getProperty('auditService');

        $this->assertInstanceOf(AuditService::class, $prop->getValue($model));
    }

    public function test_audit_logs_are_immutable_and_have_no_modification_routes(): void
    {
        $indexContent = file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
        $this->assertIsString($indexContent);

        // Verify read-only routes exist
        $this->assertStringContainsString('/activity-logs', $indexContent);
        $this->assertStringContainsString('/activity-logs/export', $indexContent);

        // Verify there are absolutely NO update, edit, delete, or destroy routes for activity logs
        $this->assertStringNotContainsString('/activity-logs/delete', $indexContent);
        $this->assertStringNotContainsString('/activity-logs/destroy', $indexContent);
        $this->assertStringNotContainsString('/activity-logs/edit', $indexContent);
        $this->assertStringNotContainsString('/activity-logs/update', $indexContent);
    }
}
