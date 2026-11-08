<?php
/**
 * API Position Management Controller
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers\Api;

use App\Models\Position;
use App\Models\Department;
use App\Models\User;
use OpenApi\Attributes as OA;

class PositionController extends ApiController
{
    private Position $positionModel;
    private Department $departmentModel;
    private User $userModel;

    public function __construct(
        ?Position $positionModel = null,
        ?Department $departmentModel = null,
        ?User $userModel = null
    ) {
        $this->positionModel   = $positionModel ?? new Position();
        $this->departmentModel = $departmentModel ?? new Department();
        $this->userModel       = $userModel ?? new User();
    }

    #[OA\Get(
        path: "/api/v1/positions",
        summary: "List job positions",
        description: "Retrieves all defined job classifications. Supports filtering by department ID.",
        tags: ["Position Management"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "department_id",
                in: "query",
                description: "Filter positions by department ID",
                required: false,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of job positions",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Positions retrieved successfully."),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 2),
                                    new OA\Property(property: "position_name", type: "string", example: "Senior Systems Engineer"),
                                    new OA\Property(property: "department_id", type: "integer", example: 1),
                                    new OA\Property(property: "department_name", type: "string", example: "Information Technology"),
                                    new OA\Property(property: "active_employee_count", type: "integer", example: 3)
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
        $deptId = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : (!empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : null);
        $positions = $this->positionModel->getAll($deptId);
        $this->success($positions, 'Positions retrieved successfully.');
    }

    #[OA\Get(
        path: "/api/v1/positions/{id}",
        summary: "Retrieve single position details",
        description: "Returns position classification details, departmental alignment, and active headcount.",
        tags: ["Position Management"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Unique position ID",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Position found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Position retrieved successfully."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Position not found")
        ]
    )]
    public function show(int $id): void
    {
        $this->requireAuth();
        $position = $this->positionModel->findById($id);
        if (!$position) {
            $this->error('Position not found.', 404);
        }
        $this->success($position, 'Position retrieved successfully.');
    }

    #[OA\Post(
        path: "/api/v1/positions",
        summary: "Create job position",
        description: "Creates a new position classification aligned to an existing department. Enforces uniqueness within the department. Restricted to HR accounts.",
        tags: ["Position Management"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Position creation payload",
            content: new OA\JsonContent(
                required: ["position_name", "department_id"],
                properties: [
                    new OA\Property(property: "position_name", type: "string", example: "DevOps Engineer"),
                    new OA\Property(property: "department_id", type: "integer", example: 1),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "CI/CD and cloud infrastructure operations")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Position created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 201),
                        new OA\Property(property: "message", type: "string", example: "Position created successfully."),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 15),
                                new OA\Property(property: "position_name", type: "string", example: "DevOps Engineer")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Validation failure (duplicate position in department or invalid department ID)"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role or CSRF token failure")
        ]
    )]
    public function store(): void
    {
        $currentUser = $this->requireRole(['admin', 'hr']);
        $input = $this->getJsonInput();

        $name = trim((string)($input['position_name'] ?? ''));
        $deptId = (int)($input['department_id'] ?? 0);

        if ($name === '') {
            $this->error('Position name is required.', 400);
        }

        if (mb_strlen($name) > 100) {
            $this->error('Position name cannot exceed 100 characters.', 400);
        }

        $dept = $this->departmentModel->findById($deptId);
        if (!$dept) {
            $this->error('Please select a valid department.', 400);
        }

        if (!$this->positionModel->isUniqueInDepartment($name, $deptId)) {
            $this->error("A position named '{$name}' already exists in this department.", 400);
        }

        $id = $this->positionModel->create([
            'position_name' => $name,
            'department_id' => $deptId,
            'description'   => !empty($input['description']) ? trim((string)$input['description']) : null,
        ]);

        $this->userModel->logActivity(
            (int)$currentUser['id'],
            'POSITION_CREATED',
            "Created position: {$name} in {$dept['department_name']}",
            'POSITION',
            (string)$id
        );

        $this->respond([
            'status'  => 'success',
            'code'    => 201,
            'message' => 'Position created successfully.',
            'data'    => [
                'id'            => $id,
                'position_name' => $name,
            ],
        ], 201);
    }

    #[OA\Put(
        path: "/api/v1/positions/{id}",
        summary: "Update job position",
        description: "Updates position classification details and departmental assignment. Restricted to HR accounts.",
        tags: ["Position Management"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Unique position ID",
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Updated position payload",
            content: new OA\JsonContent(
                required: ["position_name", "department_id"],
                properties: [
                    new OA\Property(property: "position_name", type: "string", example: "Senior DevOps Engineer"),
                    new OA\Property(property: "department_id", type: "integer", example: 1),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "Lead CI/CD pipeline automation and infrastructure")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Position updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Position updated successfully.")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Validation failure"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role"),
            new OA\Response(response: 404, description: "Position not found")
        ]
    )]
    public function update(int $id): void
    {
        $currentUser = $this->requireRole(['admin', 'hr']);
        $pos = $this->positionModel->findById($id);
        if (!$pos) {
            $this->error('Position not found.', 404);
        }

        $input = $this->getJsonInput();
        $name = trim((string)($input['position_name'] ?? ''));
        $deptId = (int)($input['department_id'] ?? 0);

        if ($name === '') {
            $this->error('Position name is required.', 400);
        }

        if (mb_strlen($name) > 100) {
            $this->error('Position name cannot exceed 100 characters.', 400);
        }

        $dept = $this->departmentModel->findById($deptId);
        if (!$dept) {
            $this->error('Please select a valid department.', 400);
        }

        if (!$this->positionModel->isUniqueInDepartment($name, $deptId, $id)) {
            $this->error("A position named '{$name}' already exists in this department.", 400);
        }

        // Block moving a position to another department while employees still hold it,
        // which would leave those employees violating the position-belongs-to-department rule.
        if ((int)$pos['department_id'] !== $deptId) {
            $dependentCount = $this->positionModel->getDependentEmployeeCount($id);
            if ($dependentCount > 0) {
                $this->error(
                    "Cannot move this position to another department while {$dependentCount} employee(s) still hold it. Reassign those employees first.",
                    409
                );
            }
        }

        $this->positionModel->update($id, [
            'position_name' => $name,
            'department_id' => $deptId,
            'description'   => !empty($input['description']) ? trim((string)$input['description']) : null,
        ]);

        $this->userModel->logActivity(
            (int)$currentUser['id'],
            'POSITION_UPDATED',
            "Updated position: {$name}",
            'POSITION',
            (string)$id
        );

        $this->success(null, 'Position updated successfully.');
    }

    #[OA\Get(
        path: "/hr/api/positions-by-department/{id}",
        summary: "Dynamic positions feed by department (Existing UI feed)",
        description: "Existing operational background endpoint returning JSON positions belonging to a specified department ID for dynamic dropdown rendering.",
        tags: ["Position Management"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Department ID",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Positions array retrieved",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(
                            property: "positions",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 3),
                                    new OA\Property(property: "position_name", type: "string", example: "QA Engineer"),
                                    new OA\Property(property: "department_id", type: "integer", example: 1)
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to workforce accounts")
        ]
    )]
    public function positionsByDepartmentLegacy(int $id): void
    {
        $this->requireRole(['admin', 'hr']);
        $positions = $this->positionModel->getByDepartmentId($id);
        $this->respond([
            'status'    => 'success',
            'positions' => $positions,
        ]);
    }
}
