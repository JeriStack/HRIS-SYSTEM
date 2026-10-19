<?php
/**
 * API Department Management Controller
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers\Api;

use App\Models\Department;
use App\Models\User;
use OpenApi\Attributes as OA;

class DepartmentController extends ApiController
{
    private Department $departmentModel;
    private User $userModel;

    public function __construct(
        ?Department $departmentModel = null,
        ?User $userModel = null
    ) {
        $this->departmentModel = $departmentModel ?? new Department();
        $this->userModel       = $userModel ?? new User();
    }

    #[OA\Get(
        path: "/api/v1/departments",
        summary: "List organizational departments",
        description: "Retrieves all departments along with associated job position counts and active employee headcounts. Accessible to authenticated users.",
        tags: ["Department Management"],
        security: [["cookieAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of departments",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Departments retrieved successfully."),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "department_name", type: "string", example: "Information Technology"),
                                    new OA\Property(property: "description", type: "string", example: "Software development and IT infrastructure"),
                                    new OA\Property(property: "position_count", type: "integer", example: 4),
                                    new OA\Property(property: "active_employee_count", type: "integer", example: 8)
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(): void
    {
        $this->requireAuth();
        $departments = $this->departmentModel->getAll();
        $this->success($departments, 'Departments retrieved successfully.');
    }

    #[OA\Get(
        path: "/api/v1/departments/{id}",
        summary: "Retrieve single department details",
        description: "Returns details of an organizational department including associated position and employee counts.",
        tags: ["Department Management"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Unique department ID",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Department found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Department retrieved successfully."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Department not found")
        ]
    )]
    public function show(int $id): void
    {
        $this->requireAuth();
        $department = $this->departmentModel->findById($id);
        if (!$department) {
            $this->error('Department not found.', 404);
        }
        $this->success($department, 'Department retrieved successfully.');
    }

    #[OA\Post(
        path: "/api/v1/departments",
        summary: "Create organizational department",
        description: "Creates a new department. Enforces unique department name validation. Restricted to HR accounts.",
        tags: ["Department Management"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Department data payload",
            content: new OA\JsonContent(
                required: ["department_name"],
                properties: [
                    new OA\Property(property: "department_name", type: "string", example: "Marketing and Communications"),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "Brand management and customer engagement")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Department created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 201),
                        new OA\Property(property: "message", type: "string", example: "Department created successfully."),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 6),
                                new OA\Property(property: "department_name", type: "string", example: "Marketing and Communications")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Validation failure (duplicate department name or missing required fields)"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role or CSRF token failure")
        ]
    )]
    public function store(): void
    {
        $currentUser = $this->requireRole('hr');
        $input = $this->getJsonInput();
        $name = trim((string)($input['department_name'] ?? ''));

        if ($name === '') {
            $this->error('Department name is required.', 400);
        }

        if (mb_strlen($name) > 100) {
            $this->error('Department name cannot exceed 100 characters.', 400);
        }

        if (!$this->departmentModel->isNameUnique($name)) {
            $this->error("Department name '{$name}' is already taken.", 400);
        }

        $id = $this->departmentModel->create([
            'department_name' => $name,
            'description'     => !empty($input['description']) ? trim((string)$input['description']) : null,
        ]);

        $this->userModel->logActivity(
            (int)$currentUser['id'],
            'DEPARTMENT_CREATED',
            "Created department: {$name}",
            'DEPARTMENT',
            (string)$id
        );

        $this->respond([
            'status'  => 'success',
            'code'    => 201,
            'message' => 'Department created successfully.',
            'data'    => [
                'id'              => $id,
                'department_name' => $name,
            ],
        ], 201);
    }

    #[OA\Put(
        path: "/api/v1/departments/{id}",
        summary: "Update organizational department",
        description: "Updates department name and description. Enforces unique name validation excluding self. Restricted to HR accounts.",
        tags: ["Department Management"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Unique department ID",
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Updated department payload",
            content: new OA\JsonContent(
                required: ["department_name"],
                properties: [
                    new OA\Property(property: "department_name", type: "string", example: "Marketing and Communications"),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "Brand management, events, and public relations")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Department updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Department updated successfully.")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Validation failure"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role"),
            new OA\Response(response: 404, description: "Department not found")
        ]
    )]
    public function update(int $id): void
    {
        $currentUser = $this->requireRole('hr');
        $dept = $this->departmentModel->findById($id);
        if (!$dept) {
            $this->error('Department not found.', 404);
        }

        $input = $this->getJsonInput();
        $name = trim((string)($input['department_name'] ?? ''));

        if ($name === '') {
            $this->error('Department name is required.', 400);
        }

        if (mb_strlen($name) > 100) {
            $this->error('Department name cannot exceed 100 characters.', 400);
        }

        if (!$this->departmentModel->isNameUnique($name, $id)) {
            $this->error("Department name '{$name}' is already taken.", 400);
        }

        $this->departmentModel->update($id, [
            'department_name' => $name,
            'description'     => !empty($input['description']) ? trim((string)$input['description']) : null,
        ]);

        $this->userModel->logActivity(
            (int)$currentUser['id'],
            'DEPARTMENT_UPDATED',
            "Updated department: {$name}",
            'DEPARTMENT',
            (string)$id
        );

        $this->success(null, 'Department updated successfully.');
    }
}
