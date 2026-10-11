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
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\EmployeeController;
use App\Controllers\DepartmentController;
use App\Controllers\PositionController;

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
    $router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

    // Role-Directed Dashboard Entry Point
    $router->get('/dashboard', [AuthController::class, 'dashboard'], [AuthMiddleware::class]);

    // Protected Administrator & HR Middleware Instance
    $hrRole = new RoleMiddleware(['admin', 'hr']);

    $router->get('/hr/dashboard', [DashboardController::class, 'hrDashboard'], [AuthMiddleware::class, $hrRole]);

    // Phase 7: Employee Management Routes
    $router->get('/hr/employees', [EmployeeController::class, 'index'], [AuthMiddleware::class, $hrRole]);
    $router->get('/hr/employees/create', [EmployeeController::class, 'create'], [AuthMiddleware::class, $hrRole]);
    $router->post('/hr/employees', [EmployeeController::class, 'store'], [AuthMiddleware::class, $hrRole, CsrfMiddleware::class]);
    $router->get('/hr/employees/{id}', [EmployeeController::class, 'show'], [AuthMiddleware::class, $hrRole]);
    $router->get('/hr/employees/{id}/edit', [EmployeeController::class, 'edit'], [AuthMiddleware::class, $hrRole]);
    $router->post('/hr/employees/{id}/edit', [EmployeeController::class, 'update'], [AuthMiddleware::class, $hrRole, CsrfMiddleware::class]);
    $router->post('/hr/employees/{id}/deactivate', [EmployeeController::class, 'deactivate'], [AuthMiddleware::class, $hrRole, CsrfMiddleware::class]);

    // Phase 7: Department Management Routes
    $router->get('/hr/departments', [DepartmentController::class, 'index'], [AuthMiddleware::class, $hrRole]);
    $router->get('/hr/departments/create', [DepartmentController::class, 'create'], [AuthMiddleware::class, $hrRole]);
    $router->post('/hr/departments', [DepartmentController::class, 'store'], [AuthMiddleware::class, $hrRole, CsrfMiddleware::class]);
    $router->get('/hr/departments/{id}/edit', [DepartmentController::class, 'edit'], [AuthMiddleware::class, $hrRole]);
    $router->post('/hr/departments/{id}/edit', [DepartmentController::class, 'update'], [AuthMiddleware::class, $hrRole, CsrfMiddleware::class]);

    // Phase 7: Position Management Routes
    $router->get('/hr/positions', [PositionController::class, 'index'], [AuthMiddleware::class, $hrRole]);
    $router->get('/hr/positions/create', [PositionController::class, 'create'], [AuthMiddleware::class, $hrRole]);
    $router->post('/hr/positions', [PositionController::class, 'store'], [AuthMiddleware::class, $hrRole, CsrfMiddleware::class]);
    $router->get('/hr/positions/{id}/edit', [PositionController::class, 'edit'], [AuthMiddleware::class, $hrRole]);
    $router->post('/hr/positions/{id}/edit', [PositionController::class, 'update'], [AuthMiddleware::class, $hrRole, CsrfMiddleware::class]);
    $router->get('/hr/api/positions-by-department/{id}', [PositionController::class, 'getByDepartment'], [AuthMiddleware::class, $hrRole]);

    // Protected Employee Self-Service Routes (Enforces Ownership & Role)
    $router->get('/employee/dashboard', [DashboardController::class, 'employeeDashboard'], [AuthMiddleware::class, new RoleMiddleware(['employee'])]);
    $router->get('/employee/profile', [DashboardController::class, 'employeeProfile'], [AuthMiddleware::class, new RoleMiddleware(['employee'])]);
    $router->get('/employee/leave-balances', [DashboardController::class, 'employeeLeaveBalances'], [AuthMiddleware::class, new RoleMiddleware(['employee'])]);
    $router->get('/employee/leave-requests', [DashboardController::class, 'employeeLeaveRequests'], [AuthMiddleware::class, new RoleMiddleware(['employee'])]);

    // Static Prototype Reference
    $router->get('/prototype', function () {
        redirect('prototype/index.html');
    });

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
