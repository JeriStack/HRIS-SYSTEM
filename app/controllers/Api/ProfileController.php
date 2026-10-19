<?php
/**
 * API Profile & In-System Notifications Controller
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers\Api;

use App\Models\User;
use App\Models\Employee;
use App\Models\Notification;
use OpenApi\Attributes as OA;

class ProfileController extends ApiController
{
    private User $userModel;
    private Employee $employeeModel;
    private Notification $notificationModel;

    public function __construct(
        ?User $userModel = null,
        ?Employee $employeeModel = null,
        ?Notification $notificationModel = null
    ) {
        $this->userModel         = $userModel ?? new User();
        $this->employeeModel     = $employeeModel ?? new Employee();
        $this->notificationModel = $notificationModel ?? new Notification();
    }

    #[OA\Get(
        path: "/api/v1/profile",
        summary: "Retrieve authenticated user's profile",
        description: "Returns the currently authenticated user's account details, role ('hr' or 'employee'), and linked workforce employee record if applicable.",
        tags: ["Account and Profile"],
        security: [["cookieAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Authenticated user profile",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "User profile retrieved successfully."),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 2),
                                new OA\Property(property: "username", type: "string", example: "jane.doe"),
                                new OA\Property(property: "email", type: "string", example: "jane.doe@example.com"),
                                new OA\Property(property: "role", type: "string", example: "employee"),
                                new OA\Property(property: "status", type: "string", example: "Active"),
                                new OA\Property(
                                    property: "employee",
                                    type: "object",
                                    nullable: true,
                                    properties: [
                                        new OA\Property(property: "id", type: "integer", example: 5),
                                        new OA\Property(property: "employee_no", type: "string", example: "EMP-2026-005"),
                                        new OA\Property(property: "first_name", type: "string", example: "Jane"),
                                        new OA\Property(property: "last_name", type: "string", example: "Doe"),
                                        new OA\Property(property: "department_name", type: "string", example: "Engineering"),
                                        new OA\Property(property: "position_name", type: "string", example: "Software Engineer"),
                                        new OA\Property(property: "employment_status", type: "string", example: "Active")
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "Unauthenticated request",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "error"),
                        new OA\Property(property: "code", type: "integer", example: 401),
                        new OA\Property(property: "message", type: "string", example: "Unauthenticated: Sign in is required to access this resource.")
                    ]
                )
            )
        ]
    )]
    public function profile(): void
    {
        $sessionUser = $this->requireAuth();
        $user = $this->userModel->findById((int)$sessionUser['id']);

        if (!$user) {
            $this->error('User account not found.', 404);
        }

        $employeeData = null;
        if (!empty($user['employee_id'])) {
            $employee = $this->employeeModel->findById((int)$user['employee_id']);
            if ($employee) {
                $employeeData = [
                    'id'                => (int)$employee['id'],
                    'employee_no'       => $employee['employee_no'],
                    'first_name'        => $employee['first_name'],
                    'middle_name'       => $employee['middle_name'],
                    'last_name'         => $employee['last_name'],
                    'email'             => $employee['email'],
                    'phone'             => $employee['phone'],
                    'department_id'     => !empty($employee['department_id']) ? (int)$employee['department_id'] : null,
                    'department_name'   => $employee['department_name'] ?? null,
                    'position_id'       => !empty($employee['position_id']) ? (int)$employee['position_id'] : null,
                    'position_name'     => $employee['position_name'] ?? null,
                    'employment_status' => $employee['employment_status'],
                    'date_hired'        => $employee['date_hired'],
                ];
            }
        }

        $this->success([
            'id'         => (int)$user['id'],
            'username'   => $user['username'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'status'     => $user['status'],
            'created_at' => $user['created_at'],
            'employee'   => $employeeData,
        ], 'User profile retrieved successfully.');
    }

    #[OA\Get(
        path: "/api/v1/notifications",
        summary: "List personal in-system notifications",
        description: "Retrieves recent notifications and unread alert counts for the current authenticated user.",
        tags: ["Account and Profile"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "limit",
                in: "query",
                description: "Maximum number of notifications to return (default 20, max 100)",
                required: false,
                schema: new OA\Schema(type: "integer", default: 20)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of user notifications",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Notifications retrieved successfully."),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "unread_count", type: "integer", example: 2),
                                new OA\Property(
                                    property: "notifications",
                                    type: "array",
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: "id", type: "integer", example: 10),
                                            new OA\Property(property: "title", type: "string", example: "Leave Request Approved"),
                                            new OA\Property(property: "message", type: "string", example: "Your Vacation Leave application for Nov 10-14 has been approved."),
                                            new OA\Property(property: "type", type: "string", example: "success"),
                                            new OA\Property(property: "is_read", type: "integer", example: 0),
                                            new OA\Property(property: "created_at", type: "string", example: "2026-10-18 14:30:00")
                                        ]
                                    )
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated request")
        ]
    )]
    public function notifications(): void
    {
        $sessionUser = $this->requireAuth();
        $userId = (int)$sessionUser['id'];

        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $notifications = $this->notificationModel->getByUser($userId, $limit);
        $unreadCount = $this->notificationModel->getUnreadCount($userId);

        $this->success([
            'unread_count'  => $unreadCount,
            'notifications' => $notifications,
        ], 'Notifications retrieved successfully.');
    }

    #[OA\Post(
        path: "/api/v1/notifications/{id}/read",
        summary: "Mark notification as read",
        description: "Marks a specific notification item as read for the authenticated user.",
        tags: ["Account and Profile"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Unique notification ID",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Notification marked as read",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Notification marked as read.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated request"),
            new OA\Response(response: 403, description: "CSRF verification failed")
        ]
    )]
    public function markRead(int $id): void
    {
        $sessionUser = $this->requireAuth();
        $this->notificationModel->markAsRead($id, (int)$sessionUser['id']);
        $this->success(null, 'Notification marked as read.');
    }

    #[OA\Post(
        path: "/api/v1/notifications/mark-all-read",
        summary: "Mark all notifications as read",
        description: "Marks all pending unread notifications as read for the authenticated user.",
        tags: ["Account and Profile"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "All notifications marked as read",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "All notifications marked as read.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated request"),
            new OA\Response(response: 403, description: "CSRF verification failed")
        ]
    )]
    public function markAllRead(): void
    {
        $sessionUser = $this->requireAuth();
        $this->notificationModel->markAllAsRead((int)$sessionUser['id']);
        $this->success(null, 'All notifications marked as read.');
    }
}
