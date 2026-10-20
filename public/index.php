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

    // Authentication Routes
    $router->get('/login', [AuthController::class, 'showLoginForm']);
    $router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
    $router->get('/verify-otp', [AuthController::class, 'showVerifyOtpForm']);
    $router->post('/verify-otp', [AuthController::class, 'verifyOtp'], [CsrfMiddleware::class]);
    $router->post('/verify-otp/resend', [AuthController::class, 'resendOtp'], [CsrfMiddleware::class]);
    $router->post('/verify-otp/cancel', [AuthController::class, 'cancelOtp'], [CsrfMiddleware::class]);
    $router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

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

    // Leave Application, Show & Self-Service (Authenticated)
    $router->get('/leaves/apply', [LeaveController::class, 'apply'], [AuthMiddleware::class]);
    $router->post('/leaves/apply', [LeaveController::class, 'store'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/leaves/{id}', [LeaveController::class, 'show'], [AuthMiddleware::class]);
    $router->post('/leaves/{id}/cancel', [LeaveController::class, 'cancel'], [AuthMiddleware::class, CsrfMiddleware::class]);

    // Operational Reports & Analytics (Administrator & HR Access)
    $router->get('/reports', [ReportController::class, 'index'], [AuthMiddleware::class, $workforceRoles]);

    // System Security Audit Activity Trail (Administrator & HR Access)
    $router->get('/activity-logs', [ActivityLogController::class, 'index'], [AuthMiddleware::class, $workforceRoles]);

    // Data Structures & Algorithms Laboratory (Administrator & HR Access)
    $router->get('/dsa', [DsaController::class, 'playground'], [AuthMiddleware::class, $workforceRoles]);

    // In-System Notifications & Alerts (Authenticated - All Roles)
    $router->get('/notifications', [NotificationController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'], [AuthMiddleware::class, CsrfMiddleware::class]);

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
    http_response_code(500);
    error_log("[Application Error] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

    if (config('config.app_env') === 'development') {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Application Error - HRIS</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #FEF2F2; color: #991B1B; padding: 2rem; margin: 0; }
                .error-box { background: #FFFFFF; border: 1px solid #FECACA; border-radius: 8px; padding: 1.5rem; max-width: 900px; margin: 0 auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
                h1 { font-size: 1.5rem; margin-top: 0; }
                pre { background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1rem; border-radius: 6px; overflow-x: auto; font-size: 0.85rem; color: #0F172A; }
            </style>
        </head>
        <body>
            <div class="error-box">
                <h1>Application Error (500)</h1>
                <p><strong>Message:</strong> <?= e($e->getMessage()) ?></p>
                <p><strong>File:</strong> <?= e($e->getFile()) ?> (Line <?= $e->getLine() ?>)</p>
                <h3>Stack Trace:</h3>
                <pre><?= e($e->getTraceAsString()) ?></pre>
            </div>
        </body>
        </html>
        <?php
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>500 Internal Error - HRIS</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #F8FAFC; color: #0F172A; display: flex; align-items: center; justify-content: center; min-height: 80vh; margin: 0; text-align: center; }
                .card { background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 2.5rem; max-width: 480px; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1 style="color: #B91C1C;">500 Internal Error</h1>
                <p style="color: #64748B;">An unexpected error occurred while processing your request. Please try again later.</p>
                <a href="<?= e(url('/')) ?>" style="display: inline-block; background: #2563EB; color: white; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600;">Return to Home</a>
            </div>
        </body>
        </html>
        <?php
    }
}
