<?php
/**
 * Authentication Middleware
 * Employee HRIS & Leave Management System
 */

namespace Core;

class AuthMiddleware extends Middleware
{
    /**
     * Ensure the user has an active, authenticated session and valid live account state.
     */
    public function handle(): bool
    {
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

        return true;
    }
}
