<?php
/**
 * Public Front Controller Entry Point
 * Employee HRIS & Leave Management System
 */

// 1. Load Bootstrap Sequence (Autoloader, Core Functions, Timezone, Error Reporting)
require_once __DIR__ . '/../core/bootstrap.php';

use Core\Router;
use Core\AuthMiddleware;
use Core\RoleMiddleware;
use Core\GuestMiddleware;
use Core\CsrfMiddleware;
use Core\PermissionMiddleware;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\AdminController;
use App\Controllers\UserController;
use App\Controllers\EmployeeController;
use App\Controllers\DepartmentController;
use App\Controllers\PositionController;
use App\Controllers\LeaveController;
use App\Controllers\ReportController;
use App\Controllers\ActivityLogController;
use App\Controllers\DsaController;
use App\Controllers\NotificationController;
use App\Controllers\RealtimeController;
use App\Controllers\Api\AuthController as ApiAuthController;
use App\Controllers\Api\ProfileController as ApiProfileController;
use App\Controllers\Api\EmployeeController as ApiEmployeeController;
use App\Controllers\Api\DepartmentController as ApiDepartmentController;
use App\Controllers\Api\PositionController as ApiPositionController;
use App\Controllers\Api\LeaveController as ApiLeaveController;
use App\Controllers\Api\HealthController as ApiHealthController;
use App\Controllers\Api\DocsController as ApiDocsController;

try {
    // 2. Initialize Core Router
    $router = new Router();

    // 3. Application Public Routes
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/health', [HomeController::class, 'health']);
    $router->get('/status', [HomeController::class, 'health']);
    $router->get('/metrics', [HomeController::class, 'metrics']);

    // Authentication Routes
    $router->get('/login', [AuthController::class, 'showLoginForm']);
    $router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
    $router->get('/verify-otp', [AuthController::class, 'showVerifyOtpForm']);
    $router->post('/verify-otp', [AuthController::class, 'verifyOtp'], [CsrfMiddleware::class]);
    $router->post('/verify-otp/resend', [AuthController::class, 'resendOtp'], [CsrfMiddleware::class]);
    $router->post('/verify-otp/cancel', [AuthController::class, 'cancelOtp'], [CsrfMiddleware::class]);
    // Logout is state-changing: POST + CSRF only. GET /logout is intentionally not routed (405).
    $router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Role-Directed Dashboard Entry Point
    $router->get('/dashboard', [AuthController::class, 'dashboard'], [AuthMiddleware::class]);

    // Role Definition Middlewares (2-Role Model: HR and Employee)
    $hrOnly          = new RoleMiddleware(['hr']);
    $employeeOnly    = new RoleMiddleware(['employee']);
    $workforceRoles  = new RoleMiddleware(['hr']);

    // Dedicated HR Management Dashboard (HR Role Only)
    $router->get('/hr/dashboard', [DashboardController::class, 'hrDashboard'], [AuthMiddleware::class, $hrOnly]);

    // User Accounts & Role Governance Routes (HR Only)
    $router->get('/hr/users', [UserController::class, 'index'], [AuthMiddleware::class, $hrOnly]);
    $router->get('/hr/users/create', [UserController::class, 'create'], [AuthMiddleware::class, $hrOnly]);
    $router->post('/hr/users/create', [UserController::class, 'store'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);
    $router->get('/hr/users/{id}/edit', [UserController::class, 'edit'], [AuthMiddleware::class, $hrOnly]);
    $router->post('/hr/users/{id}/edit', [UserController::class, 'update'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);

    // Backward-Compatibility Aliases for /admin Routes -> Redirect / Service under HR
    $router->get('/admin/dashboard', function () {
        redirect('hr/dashboard');
    }, [AuthMiddleware::class, $hrOnly]);
    $router->get('/admin/users', [UserController::class, 'index'], [AuthMiddleware::class, $hrOnly]);
    $router->get('/admin/users/{id}/edit', [UserController::class, 'edit'], [AuthMiddleware::class, $hrOnly]);
    $router->post('/admin/users/{id}/edit', [UserController::class, 'update'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);

    // Phase 7: Employee Management Routes (Administrator & HR Access)
    $router->get('/hr/employees', [EmployeeController::class, 'index'], [AuthMiddleware::class, $workforceRoles]);
    $router->get('/hr/employees/create', [EmployeeController::class, 'create'], [AuthMiddleware::class, $workforceRoles]);
    $router->post('/hr/employees', [EmployeeController::class, 'store'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);
    $router->get('/hr/employees/{id}', [EmployeeController::class, 'show'], [AuthMiddleware::class, $workforceRoles]);
    $router->get('/hr/employees/{id}/edit', [EmployeeController::class, 'edit'], [AuthMiddleware::class, $workforceRoles]);
    $router->post('/hr/employees/{id}/edit', [EmployeeController::class, 'update'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);
    $router->post('/hr/employees/{id}/deactivate', [EmployeeController::class, 'deactivate'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);
    $router->post('/hr/employees/{id}/reactivate', [EmployeeController::class, 'reactivate'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);

    // Phase 7: Department Management Routes (Administrator & HR Access)
    $router->get('/hr/departments', [DepartmentController::class, 'index'], [AuthMiddleware::class, $workforceRoles]);
    $router->get('/hr/departments/create', [DepartmentController::class, 'create'], [AuthMiddleware::class, $workforceRoles]);
    $router->post('/hr/departments', [DepartmentController::class, 'store'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);
    $router->get('/hr/departments/{id}/edit', [DepartmentController::class, 'edit'], [AuthMiddleware::class, $workforceRoles]);
    $router->post('/hr/departments/{id}/edit', [DepartmentController::class, 'update'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);

    // Phase 7: Position Management Routes (Administrator & HR Access)
    $router->get('/hr/positions', [PositionController::class, 'index'], [AuthMiddleware::class, $workforceRoles]);
    $router->get('/hr/positions/create', [PositionController::class, 'create'], [AuthMiddleware::class, $workforceRoles]);
    $router->post('/hr/positions', [PositionController::class, 'store'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);
    $router->get('/hr/positions/{id}/edit', [PositionController::class, 'edit'], [AuthMiddleware::class, $workforceRoles]);
    $router->post('/hr/positions/{id}/edit', [PositionController::class, 'update'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);
    $router->get('/hr/api/positions-by-department/{id}', [PositionController::class, 'getByDepartment'], [AuthMiddleware::class, $workforceRoles]);

    // Leave Management Routes (Administrator & HR Access)
    $router->get('/leaves', [LeaveController::class, 'index'], [AuthMiddleware::class, $workforceRoles]);
    $router->get('/hr/leave-requests', [LeaveController::class, 'index'], [AuthMiddleware::class, $workforceRoles]);
    $router->get('/hr/leave-requests/pending', function () {
        redirect('/leaves?status=Pending');
    }, [AuthMiddleware::class, $workforceRoles]);
    $router->get('/leaves/balances', [LeaveController::class, 'balances'], [AuthMiddleware::class, $workforceRoles]);
    $router->post('/hr/leaves/balances/adjust', [LeaveController::class, 'adjustBalance'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);
    $router->post('/hr/leaves/balances/initialize', [LeaveController::class, 'initializeBalances'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);
    $router->get('/hr/leave-types', [LeaveController::class, 'types'], [AuthMiddleware::class, $workforceRoles]);
    $router->get('/hr/leave-types/create', [LeaveController::class, 'createType'], [AuthMiddleware::class, $workforceRoles]);
    $router->post('/hr/leave-types', [LeaveController::class, 'storeType'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);
    $router->get('/hr/leave-types/{id}/edit', [LeaveController::class, 'editType'], [AuthMiddleware::class, $workforceRoles]);
    $router->post('/hr/leave-types/{id}/edit', [LeaveController::class, 'updateType'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);
    $router->post('/hr/leave-types/{id}/toggle-status', [LeaveController::class, 'toggleTypeStatus'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);
    $router->post('/leaves/{id}/approve', [LeaveController::class, 'approve'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);
    $router->post('/leaves/{id}/reject', [LeaveController::class, 'reject'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);
    $router->post('/leaves/queue/next', [LeaveController::class, 'processNextQueue'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);

    // Leave Application, Show & Self-Service (Authenticated)
    $router->get('/leaves/apply', [LeaveController::class, 'apply'], [AuthMiddleware::class]);
    $router->post('/leaves/apply', [LeaveController::class, 'store'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/leaves/{id}', [LeaveController::class, 'show'], [AuthMiddleware::class]);
    $router->post('/leaves/{id}/cancel', [LeaveController::class, 'cancel'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Operational Reports & Analytics (Administrator & HR Access)
    $router->get('/reports', [ReportController::class, 'index'], [AuthMiddleware::class, $workforceRoles]);
    $router->get('/reports/employees', [ReportController::class, 'employees'], [AuthMiddleware::class, $workforceRoles]);
    $router->get('/reports/leaves', [ReportController::class, 'leaves'], [AuthMiddleware::class, $workforceRoles]);
    $router->get('/reports/departments', [ReportController::class, 'departments'], [AuthMiddleware::class, $workforceRoles]);

    // System Security Audit Activity Trail (Administrator & HR Access)
    $router->get('/activity-logs', [ActivityLogController::class, 'index'], [AuthMiddleware::class, $workforceRoles]);

    // Data Structures & Algorithms Laboratory (Administrator & HR Access)
    $router->get('/dsa', [DsaController::class, 'playground'], [AuthMiddleware::class, $workforceRoles]);
    $router->get('/dsa/visualizer', [DsaController::class, 'visualizer'], [AuthMiddleware::class, $workforceRoles]);
    $router->post('/dsa/execute', [DsaController::class, 'execute'], [AuthMiddleware::class, $workforceRoles, CsrfMiddleware::class]);

    // In-System Notifications & Alerts (Authenticated - All Roles)
    $router->get('/notifications', [NotificationController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Real-time WebSocket connection ticket (fully authenticated sessions only)
    $router->post('/realtime/ticket', [RealtimeController::class, 'ticket'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Protected Employee Self-Service Routes (Employee Only - Enforces Ownership)
    $router->get('/employee/dashboard', [DashboardController::class, 'employeeDashboard'], [AuthMiddleware::class, $employeeOnly]);
    $router->get('/employee/profile', [DashboardController::class, 'employeeProfile'], [AuthMiddleware::class, $employeeOnly]);
    $router->get('/employee/leave-balances', [DashboardController::class, 'employeeLeaveBalances'], [AuthMiddleware::class, $employeeOnly]);
    $router->get('/employee/leave-requests', [DashboardController::class, 'employeeLeaveRequests'], [AuthMiddleware::class, $employeeOnly]);
    $router->get('/employee/leave-history', [DashboardController::class, 'employeeLeaveRequests'], [AuthMiddleware::class, $employeeOnly]);

    // Static Prototype Reference
    $router->get('/prototype', function () {
        redirect('prototype/index.html');
    });

    // =========================================================================
    // Swagger UI & OpenAPI Specification Routes (Environment Gated)
    // =========================================================================
    $router->get('/docs', [ApiDocsController::class, 'index']);
    $router->get('/api/docs', [ApiDocsController::class, 'index']);
    $router->get('/api/docs/openapi.yaml', [ApiDocsController::class, 'yamlSpec']);
    $router->get('/api/docs/openapi.json', [ApiDocsController::class, 'jsonSpec']);

    // =========================================================================
    // REST API v1 Core Routes
    // =========================================================================

    // Group: Health and Diagnostics
    $router->get('/api/v1/health', [ApiHealthController::class, 'health']);

    // Group: Authentication
    $router->get('/api/v1/auth/csrf', [ApiAuthController::class, 'csrf']);
    $router->post('/api/v1/auth/login', [ApiAuthController::class, 'login']);
    $router->post('/api/v1/auth/verify-otp', [ApiAuthController::class, 'verifyOtp']);
    $router->post('/api/v1/auth/resend-otp', [ApiAuthController::class, 'resendOtp']);
    $router->post('/api/v1/auth/logout', [ApiAuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Group: Account and Profile
    $router->get('/api/v1/profile', [ApiProfileController::class, 'profile'], [AuthMiddleware::class]);
    $router->get('/api/v1/notifications', [ApiProfileController::class, 'notifications'], [AuthMiddleware::class]);
    $router->post('/api/v1/notifications/{id}/read', [ApiProfileController::class, 'markRead'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/api/v1/notifications/mark-all-read', [ApiProfileController::class, 'markAllRead'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Group: Employee Management (HR Role Only)
    $router->get('/api/v1/employees', [ApiEmployeeController::class, 'index'], [AuthMiddleware::class, $hrOnly]);
    $router->get('/api/v1/employees/{id}', [ApiEmployeeController::class, 'show'], [AuthMiddleware::class, $hrOnly]);
    $router->post('/api/v1/employees', [ApiEmployeeController::class, 'store'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);
    $router->put('/api/v1/employees/{id}', [ApiEmployeeController::class, 'update'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);
    $router->post('/api/v1/employees/{id}', [ApiEmployeeController::class, 'update'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);
    $router->post('/api/v1/employees/{id}/deactivate', [ApiEmployeeController::class, 'deactivate'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);
    $router->post('/api/v1/employees/{id}/reactivate', [ApiEmployeeController::class, 'reactivate'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);

    // Group: Department Management
    $router->get('/api/v1/departments', [ApiDepartmentController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/api/v1/departments/{id}', [ApiDepartmentController::class, 'show'], [AuthMiddleware::class]);
    $router->post('/api/v1/departments', [ApiDepartmentController::class, 'store'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);
    $router->put('/api/v1/departments/{id}', [ApiDepartmentController::class, 'update'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);
    $router->post('/api/v1/departments/{id}', [ApiDepartmentController::class, 'update'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);

    // Group: Position Management
    $router->get('/api/v1/positions', [ApiPositionController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/api/v1/positions/{id}', [ApiPositionController::class, 'show'], [AuthMiddleware::class]);
    $router->post('/api/v1/positions', [ApiPositionController::class, 'store'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);
    $router->put('/api/v1/positions/{id}', [ApiPositionController::class, 'update'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);
    $router->post('/api/v1/positions/{id}', [ApiPositionController::class, 'update'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);

    // Group: Leave Types and Balances
    $router->get('/api/v1/leave-types', [ApiLeaveController::class, 'types'], [AuthMiddleware::class]);
    $router->get('/api/v1/hr/leave-types', [ApiLeaveController::class, 'hrTypes'], [AuthMiddleware::class, $hrOnly]);
    $router->post('/api/v1/hr/leave-types', [ApiLeaveController::class, 'storeType'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);
    $router->get('/api/v1/leave-balances/my', [ApiLeaveController::class, 'myBalances'], [AuthMiddleware::class]);
    $router->get('/api/v1/hr/leave-balances', [ApiLeaveController::class, 'hrBalances'], [AuthMiddleware::class, $hrOnly]);
    $router->post('/api/v1/hr/leave-balances/adjust', [ApiLeaveController::class, 'adjustBalance'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);

    // Group: Leave Requests
    $router->get('/api/v1/leaves/my', [ApiLeaveController::class, 'myLeaves'], [AuthMiddleware::class]);
    $router->post('/api/v1/leaves', [ApiLeaveController::class, 'apply'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/api/v1/leaves/{id}', [ApiLeaveController::class, 'show'], [AuthMiddleware::class]);
    $router->post('/api/v1/leaves/{id}/cancel', [ApiLeaveController::class, 'cancel'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Group: HR Approvals
    $router->get('/api/v1/hr/leaves', [ApiLeaveController::class, 'hrLeaves'], [AuthMiddleware::class, $hrOnly]);
    $router->post('/api/v1/hr/leaves/{id}/approve', [ApiLeaveController::class, 'approve'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);
    $router->post('/api/v1/hr/leaves/{id}/reject', [ApiLeaveController::class, 'reject'], [AuthMiddleware::class, $hrOnly, CsrfMiddleware::class]);

    // 4. Dispatch Request through the Router
    $router->dispatch();

} catch (Throwable $e) {
    // 5. Uncaught Application Exception Handling
    // JSON envelope for /api/* and JSON clients, HTML otherwise; details only when APP_ENV=development.
    render_server_error($e);
}
