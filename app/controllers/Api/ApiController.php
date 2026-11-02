<?php
/**
 * Base API Controller & OpenAPI Root Specification Definition
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers\Api;

use Core\Controller;
use Core\Session;
use OpenApi\Attributes as OA;

#[OA\OpenApi(
    openapi: "3.0.0",
    info: new OA\Info(
        version: "1.0.0",
        title: "Employee HRIS & Leave Management System REST API",
        description: "Official enterprise REST API specification for the Employee HRIS & Leave Management System. Features role-based access control (strictly 'hr' and 'employee' roles), two-factor email OTP authentication, session cookie security with CSRF mitigation, workforce directory management, and full lifecycle leave request processing."
    ),
    servers: [
        new OA\Server(
            url: "http://localhost:8080",
            description: "Docker Nginx Reverse Proxy / Local Development Server"
        ),
        new OA\Server(
            url: "/",
            description: "Relative Application Root"
        )
    ],
    tags: [
        new OA\Tag(
            name: "Authentication",
            description: "User authentication, 2-factor email OTP challenge, session lifecycle, and CSRF token provisioning"
        ),
        new OA\Tag(
            name: "Account and Profile",
            description: "Current authenticated user profile retrieval and personal in-system notifications"
        ),
        new OA\Tag(
            name: "Employee Management",
            description: "Workforce employee registry, detail retrieval, creation, modification, and deactivation (HR role only)"
        ),
        new OA\Tag(
            name: "Department Management",
            description: "Organizational departments and functional unit administration (HR role only)"
        ),
        new OA\Tag(
            name: "Position Management",
            description: "Job classifications and departmental position alignment"
        ),
        new OA\Tag(
            name: "Leave Types and Balances",
            description: "Leave policy categories, balance quotas, and allocation adjustment management"
        ),
        new OA\Tag(
            name: "Leave Requests",
            description: "Employee self-service leave submission, history tracking, and cancellation"
        ),
        new OA\Tag(
            name: "HR Approvals",
            description: "HR management review, approval, and rejection of employee leave applications"
        ),
        new OA\Tag(
            name: "Health and Diagnostics",
            description: "Operational status verification and health check telemetry"
        )
    ]
)]
#[OA\SecurityScheme(
    securityScheme: "cookieAuth",
    type: "apiKey",
    in: "cookie",
    name: "PHPSESSID",
    description: "Native PHP Session Cookie. Established upon completing primary password login followed by email OTP verification."
)]
#[OA\SecurityScheme(
    securityScheme: "csrfToken",
    type: "apiKey",
    in: "header",
    name: "X-CSRF-Token",
    description: "Cross-Site Request Forgery (CSRF) validation token. Required on state-changing operations (POST, PUT, DELETE, PATCH). Obtainable via GET /api/v1/auth/csrf."
)]
abstract class ApiController extends Controller
{
    /**
     * Retrieve parsed JSON body or fallback to $_POST / $_GET input.
     */
    protected function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST ?: $_GET ?: [];
    }

    /**
     * Respond with standard formatted JSON payload.
     */
    protected function respond(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Respond with standard success format.
     */
    protected function success(mixed $data = null, string $message = 'Operation completed successfully.', int $statusCode = 200): void
    {
        $this->respond([
            'status'  => 'success',
            'code'    => $statusCode,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Respond with standard error format.
     */
    protected function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        $payload = [
            'status'  => 'error',
            'code'    => $statusCode,
            'message' => $message,
        ];
        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }
        $this->respond($payload, $statusCode);
    }

    /**
     * Enforce authentication and return the authenticated user payload.
     */
    protected function requireAuth(): array
    {
        if (!isAuthenticated() || !Session::verifyAuthenticatedAccountState()) {
            $this->error('Unauthenticated: Sign in is required to access this resource.', 401);
        }
        $user = currentUser();
        if (!$user) {
            $this->error('Unauthenticated: Active session not found.', 401);
        }
        return $user;
    }

    /**
     * Enforce role authorization and return the authenticated user payload.
     */
    protected function requireRole(array|string $roles): array
    {
        $user = $this->requireAuth();
        $allowed = array_map('strtolower', (array)$roles);
        $role = strtolower((string)($user['role'] ?? ''));

        if (!in_array($role, $allowed, true)) {
            $this->error('Forbidden: You do not possess the required role permissions to access this resource.', 403);
        }

        return $user;
    }

    /**
     * Enforce that the user is linked to an active employee record.
     */
    protected function requireEmployeeUser(): array
    {
        $user = $this->requireAuth();
        if (empty($user['employee_id'])) {
            $this->error('Forbidden: No employee profile record is associated with this account.', 403);
        }
        return $user;
    }
}
