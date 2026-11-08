<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\AdminController;
use App\Controllers\Api\LeaveController as ApiLeaveController;
use App\Controllers\AuthController;
use App\Controllers\LeaveController;
use App\Models\User;
use Core\AuthorizationService;
use Core\Database;
use Core\PermissionMiddleware;
use Core\Session;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Full-System Functional Audit Regression Suite
 *
 * Verifies fixes for BUG-001 through BUG-007 discovered during the
 * NEXORA HR full-system functional, security, and database audit.
 */
class SystemAuditFixesTest extends TestCase
{
    /** @var array<string, mixed> */
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

    /**
     * BUG-001: Verify User::update() exists, validates inputs, and AdminController::updateAccount()
     * uses findByIdWithAuthState() + refreshes session fingerprint.
     */
    public function testBug001UserUpdateMethodAndAdminAccountCredentialFlow(): void
    {
        $this->assertTrue(
            method_exists(User::class, 'update'),
            'App\Models\User::update() must exist to support AdminController::updateAccount().'
        );

        $ref = new ReflectionMethod(User::class, 'update');
        $this->assertTrue($ref->isPublic());
        $this->assertCount(2, $ref->getParameters());

        $adminControllerFile = (string)file_get_contents(
            dirname(__DIR__, 2) . '/app/controllers/AdminController.php'
        );
        $this->assertStringContainsString(
            'findByIdWithAuthState($userId)',
            $adminControllerFile,
            'AdminController::updateAccount() must use findByIdWithAuthState() so password hash is available.'
        );
        $this->assertStringContainsString(
            'Session::computeAuthFingerprint($freshUser)',
            $adminControllerFile,
            'AdminController::updateAccount() must refresh session auth_fingerprint after credential changes.'
        );

        if (Database::getInstance()->isConnected()) {
            $userModel = new User();
            $this->assertFalse($userModel->update(0, ['email' => 'valid@company.local']));
            $this->assertFalse($userModel->update(1, []));

            $this->expectException(InvalidArgumentException::class);
            $userModel->update(1, ['email' => 'not-an-email']);
        }
    }

    /**
     * BUG-002: Verify User::findByEmployeeId() includes COALESCE(u.email, e.email) AS email
     * so LeaveNotificationService::leaveDecided() can queue asynchronous decision emails.
     */
    public function testBug002FindByEmployeeIdSelectsEmailForLeaveNotifications(): void
    {
        $userModelSource = (string)file_get_contents(
            dirname(__DIR__, 2) . '/app/models/User.php'
        );

        $ref = new ReflectionMethod(User::class, 'findByEmployeeId');
        $startLine = (int)$ref->getStartLine();
        $endLine = (int)$ref->getEndLine();
        $lines = array_slice(
            explode("\n", $userModelSource),
            $startLine - 1,
            $endLine - $startLine + 1
        );
        $methodBody = implode("\n", $lines);

        $this->assertStringContainsString(
            'COALESCE(u.email, e.email) AS email',
            $methodBody,
            'User::findByEmployeeId() must select COALESCE(u.email, e.email) AS email.'
        );

        if (Database::getInstance()->isConnected()) {
            $userModel = new User();
            $empUser = $userModel->findByEmployeeId(4);
            if ($empUser !== null) {
                $this->assertArrayHasKey('email', $empUser);
                $this->assertNotEmpty($empUser['email']);
            }
        }
    }

    /**
     * BUG-003: Verify User::create() populates role_id from roles table with safe fallback.
     */
    public function testBug003UserCreatePopulatesRoleIdForeignKey(): void
    {
        $userModelSource = (string)file_get_contents(
            dirname(__DIR__, 2) . '/app/models/User.php'
        );

        $ref = new ReflectionMethod(User::class, 'create');
        $startLine = (int)$ref->getStartLine();
        $endLine = (int)$ref->getEndLine();
        $lines = array_slice(
            explode("\n", $userModelSource),
            $startLine - 1,
            $endLine - $startLine + 1
        );
        $methodBody = implode("\n", $lines);

        $this->assertStringContainsString(
            '(SELECT id FROM roles WHERE slug = :role_slug LIMIT 1)',
            $methodBody,
            'User::create() must populate role_id via subquery against roles.slug.'
        );
    }

    /**
     * BUG-004: Verify LeaveController and Api\LeaveController authorize both admin and hr roles.
     */
    public function testBug004LeaveControllersAllowBothAdminAndHrRoles(): void
    {
        $webLeaveSource = (string)file_get_contents(
            dirname(__DIR__, 2) . '/app/controllers/LeaveController.php'
        );

        foreach (['approve', 'processNextQueue', 'reject', 'cancel'] as $methodName) {
            $ref = new ReflectionMethod(LeaveController::class, $methodName);
            $startLine = (int)$ref->getStartLine();
            $endLine = (int)$ref->getEndLine();
            $lines = array_slice(
                explode("\n", $webLeaveSource),
                $startLine - 1,
                $endLine - $startLine + 1
            );
            $methodBody = implode("\n", $lines);

            $this->assertStringContainsString(
                "['admin', 'hr']",
                $methodBody,
                "LeaveController::{$methodName}() must authorize both 'admin' and 'hr' roles."
            );
        }

        $apiLeaveSource = (string)file_get_contents(
            dirname(__DIR__, 2) . '/app/controllers/Api/LeaveController.php'
        );
        $apiShowRef = new ReflectionMethod(ApiLeaveController::class, 'show');
        $apiLines = array_slice(
            explode("\n", $apiLeaveSource),
            (int)$apiShowRef->getStartLine() - 1,
            (int)$apiShowRef->getEndLine() - (int)$apiShowRef->getStartLine() + 1
        );
        $this->assertStringContainsString(
            "['admin', 'hr']",
            implode("\n", $apiLines),
            'Api\LeaveController::show() must authorize both admin and hr roles.'
        );
    }

    /**
     * BUG-005: Verify PermissionMiddleware detects /api/ URI for JSON error responses.
     */
    public function testBug005PermissionMiddlewareDetectsApiUriForJsonResponses(): void
    {
        $source = (string)file_get_contents(
            dirname(__DIR__, 2) . '/core/PermissionMiddleware.php'
        );

        $this->assertStringContainsString(
            "str_contains(\$_SERVER['REQUEST_URI'] ?? '', '/api/')",
            $source,
            'PermissionMiddleware must detect /api/ URIs for JSON error responses.'
        );
    }

    /**
     * BUG-006: Verify AuthController::login() maps 'admin' to 'Admin Portal' on portal mismatch.
     */
    public function testBug006AuthControllerMapsAdminPortalLabelCorrectly(): void
    {
        $source = (string)file_get_contents(
            dirname(__DIR__, 2) . '/app/controllers/AuthController.php'
        );

        $ref = new ReflectionMethod(AuthController::class, 'login');
        $lines = array_slice(
            explode("\n", $source),
            (int)$ref->getStartLine() - 1,
            (int)$ref->getEndLine() - (int)$ref->getStartLine() + 1
        );
        $methodBody = implode("\n", $lines);

        $this->assertStringContainsString(
            "'admin'    => 'Admin Portal'",
            $methodBody,
            'AuthController::login() must map admin role to Admin Portal.'
        );
        $this->assertStringContainsString(
            "'hr'       => 'HR Portal'",
            $methodBody
        );
        $this->assertStringContainsString(
            "'employee' => 'Employee Portal'",
            $methodBody
        );
    }

    /**
     * BUG-007: Verify AuthorizationService::can() uses array_key_exists on PermissionRegistry::ROLES
     * and core/functions.php has only one can() definition.
     */
    public function testBug007AuthorizationServiceRoleValidationAndSingleCanHelper(): void
    {
        $authSource = (string)file_get_contents(
            dirname(__DIR__, 2) . '/core/AuthorizationService.php'
        );
        $this->assertStringContainsString(
            'array_key_exists($role, PermissionRegistry::ROLES)',
            $authSource
        );

        $functionsSource = (string)file_get_contents(
            dirname(__DIR__, 2) . '/core/functions.php'
        );
        $this->assertSame(
            1,
            substr_count($functionsSource, "if (!function_exists('can'))"),
            'core/functions.php must define can() only once.'
        );

        $auth = AuthorizationService::getInstance();
        $this->assertTrue($auth->can('admin', 'users.view'));
        $this->assertTrue($auth->can('hr', 'leave.approve'));
        $this->assertTrue($auth->can('employee', 'leave.file_own'));
        $this->assertFalse($auth->can('invalid_role', 'users.view'));
    }
}

