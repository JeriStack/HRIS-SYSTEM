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

            // If not found in $_POST or header, check JSON request body
            if ($token === null) {
                $rawBody = file_get_contents('php://input');
                if (!empty($rawBody)) {
                    $json = json_decode($rawBody, true);
                    if (is_array($json) && isset($json['_csrf_token'])) {
                        $token = (string)$json['_csrf_token'];
                    }
                }
            }

            if (!verify_csrf_token($token)) {
                $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
                    || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');

                if ($isJson) {
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
