<?php
/**
 * Named-Permission Authorization Middleware
 * Employee HRIS & Leave Management System
 */

namespace Core;

class PermissionMiddleware extends Middleware
{
    private string $capability;

    /**
     * @param string $capability Named permission token (e.g. 'manage_users', 'hr_dashboard')
     */
    public function __construct(string $capability)
    {
        $this->capability = $capability;
    }

    /**
     * Verify authenticated user has required capability under centralized RBAC policy.
     */
    public function handle(): bool
    {
        $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');

        // 1. Must be authenticated with valid live account state
        if (!isAuthenticated() || !Session::verifyAuthenticatedAccountState()) {
            if ($isJson) {
                json_response([
                    'status'  => 'error',
                    'code'    => 401,
                    'message' => 'Unauthenticated: Sign in is required to access this resource.'
                ], 401);
            }

            if (!Session::hasFlash('error') && !Session::hasFlash('timeout')) {
                Session::flash('error', 'Authentication required. Please sign in to access this page.');
            }
            redirect('/login');
            return false;
        }

        // 2. Validate capability against centralized permission policy
        $userRole = currentUserRole();

        if (!Permission::can($userRole, $this->capability)) {
            http_response_code(403);

            if ($isJson) {
                json_response([
                    'status'  => 'error',
                    'code'    => 403,
                    'message' => "Forbidden: You do not have permission for '{$this->capability}'."
                ], 403);
            }

            if (getenv('APP_ENV') !== 'testing' && (!defined('PHPUNIT_RUNNING') || !PHPUNIT_RUNNING)) {
                $errorView = __DIR__ . '/../app/views/errors/403.php';
                if (file_exists($errorView)) {
                    require $errorView;
                } else {
                    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>403 Forbidden</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h1>403 Forbidden</h1><p>You do not have permission to perform this operation.</p><p><a href="' . url('/dashboard') . '">Return to Dashboard</a></p></body></html>';
                }
                exit;
            }

            return false;
        }

        return true;
    }
}
