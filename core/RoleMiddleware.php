<?php
/**
 * Role-Based Access Control (RBAC) Middleware
 * Employee HRIS & Leave Management System
 */

namespace Core;

class RoleMiddleware extends Middleware
{
    private array $allowedRoles;

    /**
     * @param array|string $roles Allowed role or list of allowed roles (strictly 'hr' or 'employee')
     */
    public function __construct(array|string $roles)
    {
        $this->allowedRoles = (array)$roles;
    }

    /**
     * Verify authenticated user's role against approved permissions.
     */
    public function handle(): bool
    {
        // 1. Must be authenticated with valid live account state
        if (!isAuthenticated() || !Session::verifyAuthenticatedAccountState()) {
            $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
                || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');

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

        // 2. Must possess an authorized role
        $userRole = currentUserRole();
        $normalizedAllowed = array_map('strtolower', $this->allowedRoles);

        if (!$userRole || !in_array(strtolower($userRole), $normalizedAllowed, true)) {
            http_response_code(403);

            $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
                || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');

            if ($isJson) {
                json_response([
                    'status'  => 'error',
                    'code'    => 403,
                    'message' => 'Forbidden: You do not possess the required role permissions to access this resource.'
                ], 403);
            }

            $errorView = __DIR__ . '/../app/views/errors/403.php';
            if (file_exists($errorView)) {
                require $errorView;
            } else {
                echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>403 Forbidden</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h1>403 Forbidden</h1><p>You do not have permission to access this area.</p><p><a href="' . url('/dashboard') . '">Return to Dashboard</a></p></body></html>';
            }
            return false;
        }

        return true;
    }
}
