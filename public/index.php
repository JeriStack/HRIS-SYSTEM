<?php
/**
 * Public Front Controller Entry Point
 * Employee HRIS & Leave Management System
 */

// 1. Load Bootstrap Sequence (Autoloader, Core Functions, Timezone, Error Reporting)
require_once __DIR__ . '/../core/bootstrap.php';

use Core\Router;
use App\Controllers\HomeController;

try {
    // 2. Initialize Core Router
    $router = new Router();

    // 3. Register Application Foundation Routes
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/health', [HomeController::class, 'health']);
    $router->get('/status', [HomeController::class, 'health']);

    // Foundation convenience redirects to Prototype views
    $router->get('/prototype', function () {
        redirect('prototype/index.html');
    });
    $router->get('/login', function () {
        redirect('prototype/login.html');
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
