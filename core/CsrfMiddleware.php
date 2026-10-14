<?php
/**
 * Cross-Site Request Forgery (CSRF) Verification Middleware
 * Protects state-changing HTTP requests (POST, PUT, DELETE, PATCH)
 * Employee HRIS & Leave Management System
 */

namespace Core;

class CsrfMiddleware extends Middleware
{
    public function handle(): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

            if (!verify_csrf_token($token)) {
                if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                    json_response([
                        'status'  => 'error',
                        'code'    => 403,
                        'message' => 'Security token expired or invalid (CSRF validation failure).'
                    ], 403);
                }

                Session::flash('error', 'Security token expired or invalid. Please try submitting again.');
                redirect('/login');
                return false;
            }
        }

        return true;
    }
}
