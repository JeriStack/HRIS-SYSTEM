<?php
/**
 * Authentication Middleware
 * Employee HRIS & Leave Management System
 */

namespace Core;

class AuthMiddleware extends Middleware
{
    /**
     * Ensure the user has an active, authenticated session.
     */
    public function handle(): bool
    {
        if (!isAuthenticated()) {
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                json_response([
                    'status'  => 'error',
                    'code'    => 401,
                    'message' => 'Unauthenticated: Sign in is required to access this resource.'
                ], 401);
            }

            Session::flash('error', 'Authentication required. Please sign in to access this page.');
            redirect('/login');
            return false;
        }

        return true;
    }
}
