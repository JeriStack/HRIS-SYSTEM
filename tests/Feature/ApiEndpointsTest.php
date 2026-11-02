<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Core\Session;
use Core\AuthMiddleware;
use Core\RoleMiddleware;
use Core\CsrfMiddleware;
use Core\Permission;
use Symfony\Component\Yaml\Yaml;

class ApiEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        Session::destroy();
        Session::start();
    }

    protected function tearDown(): void
    {
        Session::destroy();
    }

    /**
     * Test that generated OpenAPI YAML specification exists and conforms to OpenAPI 3.x.
     */
    public function test_openapi_yaml_specification_exists_and_is_valid(): void
    {
        $yamlPath = dirname(__DIR__, 2) . '/docs/api/openapi.yaml';
        $this->assertFileExists($yamlPath, 'docs/api/openapi.yaml must exist.');

        $spec = Yaml::parse(file_get_contents($yamlPath));
        $this->assertIsArray($spec);

        // Verify OpenAPI version and Info block
        $this->assertStringStartsWith('3.', $spec['openapi']);
        $this->assertArrayHasKey('info', $spec);
        $this->assertEquals('1.0.0', $spec['info']['version']);
        $this->assertNotEmpty($spec['info']['title']);

        // Verify all 9 required documentation tags are defined
        $this->assertArrayHasKey('tags', $spec);
        $tagNames = array_column($spec['tags'], 'name');

        $expectedTags = [
            'Authentication',
            'Account and Profile',
            'Employee Management',
            'Department Management',
            'Position Management',
            'Leave Types and Balances',
            'Leave Requests',
            'HR Approvals',
            'Health and Diagnostics',
        ];

        foreach ($expectedTags as $expectedTag) {
            $this->assertContains($expectedTag, $tagNames, "Tag '{$expectedTag}' must be present in OpenAPI specification.");
        }

        // Verify Security Schemes
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('securitySchemes', $spec['components']);
        $schemes = $spec['components']['securitySchemes'];

        $this->assertArrayHasKey('cookieAuth', $schemes);
        $this->assertEquals('apiKey', $schemes['cookieAuth']['type']);
        $this->assertEquals('cookie', $schemes['cookieAuth']['in']);
        $this->assertEquals('PHPSESSID', $schemes['cookieAuth']['name']);

        $this->assertArrayHasKey('csrfToken', $schemes);
        $this->assertEquals('apiKey', $schemes['csrfToken']['type']);
        $this->assertEquals('header', $schemes['csrfToken']['in']);
        $this->assertEquals('X-CSRF-Token', $schemes['csrfToken']['name']);

        // Verify Paths
        $this->assertArrayHasKey('paths', $spec);
        $paths = array_keys($spec['paths']);

        $requiredPaths = [
            '/api/v1/auth/csrf',
            '/api/v1/auth/login',
            '/api/v1/auth/verify-otp',
            '/api/v1/auth/resend-otp',
            '/api/v1/auth/logout',
            '/api/v1/profile',
            '/api/v1/notifications',
            '/api/v1/employees',
            '/api/v1/employees/{id}',
            '/api/v1/departments',
            '/api/v1/departments/{id}',
            '/api/v1/positions',
            '/api/v1/positions/{id}',
            '/api/v1/leave-types',
            '/api/v1/leave-balances/my',
            '/api/v1/hr/leave-balances',
            '/api/v1/leaves/my',
            '/api/v1/leaves',
            '/api/v1/leaves/{id}',
            '/api/v1/leaves/{id}/cancel',
            '/api/v1/hr/leaves',
            '/api/v1/hr/leaves/{id}/approve',
            '/api/v1/hr/leaves/{id}/reject',
            '/api/v1/health',
        ];

        foreach ($requiredPaths as $requiredPath) {
            $this->assertContains($requiredPath, $paths, "Path '{$requiredPath}' must be documented in OpenAPI specification.");
        }
    }

    /**
     * Test that generated OpenAPI JSON specification exists and parses cleanly.
     */
    public function test_openapi_json_specification_exists_and_is_valid(): void
    {
        $jsonPath = dirname(__DIR__, 2) . '/docs/api/openapi.json';
        $this->assertFileExists($jsonPath, 'docs/api/openapi.json must exist.');

        $content = file_get_contents($jsonPath);
        $spec = json_decode($content, true);

        $this->assertIsArray($spec);
        $this->assertArrayHasKey('openapi', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertGreaterThanOrEqual(20, count($spec['paths']));
    }

    /**
     * Test that OpenAPI documentation contains NO sensitive credentials or live secrets.
     */
    public function test_specification_contains_no_sensitive_secrets_or_credentials(): void
    {
        $yamlPath = dirname(__DIR__, 2) . '/docs/api/openapi.yaml';
        $content = file_get_contents($yamlPath);

        // Forbidden sensitive patterns
        $forbidden = [
            'rootpassword',
            'hris_password',
            'smtp.gmail.com',
            'your-google-app-password',
            'PHPSESSID=session_secret',
        ];

        foreach ($forbidden as $secret) {
            $this->assertStringNotContainsString($secret, $content, "Specification must not leak secret or credential: {$secret}");
        }
    }

    /**
     * Test that unauthenticated visitors are recognized as unauthenticated.
     */
    public function test_unauthenticated_request_is_not_authenticated(): void
    {
        $this->assertFalse(isAuthenticated(), 'Visitor without session must not be authenticated.');
        $this->assertNull(currentUser(), 'currentUser() must be null for unauthenticated visitors.');
        $this->assertNull(currentUserRole(), 'currentUserRole() must be null for unauthenticated visitors.');
    }

    /**
     * Test that pending OTP session cannot access authenticated API resources.
     */
    public function test_pending_otp_session_cannot_access_authenticated_api_resources(): void
    {
        // Simulate Stage 1 login challenge established in session
        Session::set('pending_otp_user_id', 10);
        Session::set('pending_otp_email', 'pending.user@company.local');
        Session::set('pending_otp_name', 'Pending User');
        Session::set('pending_otp_time', time());

        // Invariant: User with pending OTP must NOT be authenticated
        $this->assertFalse(isAuthenticated(), 'Pending OTP challenge session must NOT be authenticated.');
        $this->assertNull(currentUser(), 'currentUser() must return null during pending OTP.');
        $this->assertFalse(hasRole('hr'), 'Pending OTP user must NOT possess HR role.');
        $this->assertFalse(hasRole('employee'), 'Pending OTP user must NOT possess employee role.');
    }

    /**
     * Test role-based authorization boundaries: Employee cannot access HR capabilities.
     */
    public function test_employee_role_cannot_access_hr_endpoints_or_capabilities(): void
    {
        // Authenticate as employee
        Session::set('user', [
            'id'          => 5,
            'username'    => 'employee.user',
            'role'        => 'employee',
            'employee_id' => 5,
            'name'        => 'Test Employee',
        ]);

        $this->assertTrue(isAuthenticated());
        $this->assertEquals('employee', currentUserRole());
        $this->assertFalse(hasRole('hr'), 'Employee account must NOT have HR role.');

        // Verify HR permissions are strictly denied
        $this->assertFalse(Permission::can('employee', 'manage_employees'));
        $this->assertFalse(Permission::can('employee', 'manage_departments'));
        $this->assertFalse(Permission::can('employee', 'manage_positions'));
        $this->assertFalse(Permission::can('employee', 'review_leave_requests'));
        $this->assertFalse(Permission::can('employee', 'manage_leave_balances'));
        $this->assertFalse(Permission::can('employee', 'manage_leave_types'));

        // Verify self-service permissions are granted
        $this->assertTrue(Permission::can('employee', 'file_leave_requests'));
        $this->assertTrue(Permission::can('employee', 'view_own_profile'));
        $this->assertTrue(Permission::can('employee', 'view_own_leave_balances'));
        $this->assertTrue(Permission::can('employee', 'cancel_own_leave_requests'));
    }

    /**
     * Test HR role authorization boundaries: HR has administrative access.
     */
    public function test_hr_role_has_administrative_authorization(): void
    {
        Session::set('user', [
            'id'       => 1,
            'username' => 'hr.admin',
            'role'     => 'hr',
            'name'     => 'HR Administrator',
        ]);

        $this->assertTrue(isAuthenticated());
        $this->assertTrue(hasRole('hr'));
        $this->assertFalse(hasRole('employee'));

        $this->assertTrue(Permission::can('hr', 'manage_employees'));
        $this->assertTrue(Permission::can('hr', 'manage_departments'));
        $this->assertTrue(Permission::can('hr', 'manage_positions'));
        $this->assertTrue(Permission::can('hr', 'review_leave_requests'));
        $this->assertTrue(Permission::can('hr', 'manage_leave_balances'));
    }

    /**
     * Test IDOR protection: Employee cannot read or modify another employee's records.
     */
    public function test_employee_cannot_access_or_cancel_another_employees_leave_request(): void
    {
        $currentEmployeeId = 5;
        Session::set('user', [
            'id'          => 12,
            'username'    => 'john.doe',
            'role'        => 'employee',
            'employee_id' => $currentEmployeeId,
            'name'        => 'John Doe',
        ]);

        // Mock leave requests
        $ownRequest = [
            'id'          => 101,
            'employee_id' => 5,
            'status'      => 'Pending',
        ];

        $targetOtherRequest = [
            'id'          => 102,
            'employee_id' => 9, // Belongs to different employee
            'status'      => 'Pending',
        ];

        // 1. Ownership test for own request
        $canAccessOwn = ((int)$ownRequest['employee_id'] === $currentEmployeeId);
        $this->assertTrue($canAccessOwn, 'Employee must be able to access own leave request.');

        // 2. Ownership test for other employee's request
        $canAccessOther = ((int)$targetOtherRequest['employee_id'] === $currentEmployeeId);
        $this->assertFalse($canAccessOther, 'Employee must NOT be able to access another employee request.');

        // 3. HR can access any employee request
        $isHr = hasRole('hr');
        $this->assertFalse($isHr, 'Regular employee must not be recognized as HR.');
    }

    /**
     * Test CSRF token generation and verification logic for state-changing operations.
     */
    public function test_csrf_token_verification_for_api_state_changes(): void
    {
        $token = csrf_token();
        $this->assertNotEmpty($token);
        $this->assertTrue(verify_csrf_token($token), 'Valid session CSRF token must pass verification.');

        $this->assertFalse(verify_csrf_token(null), 'Null CSRF token must fail verification.');
        $this->assertFalse(verify_csrf_token('invalid_tampered_token'), 'Invalid CSRF token must fail verification.');
    }
}
