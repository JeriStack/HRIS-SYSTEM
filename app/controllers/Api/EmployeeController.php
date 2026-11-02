<?php
/**
 * API Employee Management Controller
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers\Api;

use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Services\AccountRevocationService;
use App\Services\StepUpAuthService;
use OpenApi\Attributes as OA;

class EmployeeController extends ApiController
{
    private Employee $employeeModel;
    private Department $departmentModel;
    private Position $positionModel;
    private User $userModel;

    public function __construct(
        ?Employee $employeeModel = null,
        ?Department $departmentModel = null,
        ?Position $positionModel = null,
        ?User $userModel = null
    ) {
        $this->employeeModel   = $employeeModel ?? new Employee();
        $this->departmentModel = $departmentModel ?? new Department();
        $this->positionModel   = $positionModel ?? new Position();
        $this->userModel       = $userModel ?? new User();
    }

    #[OA\Get(
        path: "/api/v1/employees",
        summary: "List workforce employees",
        description: "Retrieves the workforce directory. Allows filtering by search term (name, employee number, email), employment status, and department ID. Restricted to HR accounts.",
        tags: ["Employee Management"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Search term matching employee number, first name, last name, or email",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "status",
                in: "query",
                description: "Filter by employment status (Active, Inactive, Terminated)",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["Active", "Inactive", "Terminated"])
            ),
            new OA\Parameter(
                name: "department_id",
                in: "query",
                description: "Filter by organizational department ID",
                required: false,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Workforce directory retrieved",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Employees retrieved successfully."),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "employee_no", type: "string", example: "EMP-2026-001"),
                                    new OA\Property(property: "first_name", type: "string", example: "Alex"),
                                    new OA\Property(property: "last_name", type: "string", example: "Smith"),
                                    new OA\Property(property: "email", type: "string", example: "alex.smith@example.com"),
                                    new OA\Property(property: "department_name", type: "string", example: "Information Technology"),
                                    new OA\Property(property: "position_name", type: "string", example: "Systems Architect"),
                                    new OA\Property(property: "employment_status", type: "string", example: "Active"),
                                    new OA\Property(property: "date_hired", type: "string", example: "2024-01-15")
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role")
        ]
    )]
    public function index(): void
    {
        $this->requireRole('hr');

        $search = !empty($_GET['search']) ? trim((string)$_GET['search']) : (!empty($_GET['q']) ? trim((string)$_GET['q']) : null);
        $status = !empty($_GET['status']) ? trim((string)$_GET['status']) : null;
        $deptId = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : (!empty($_GET['dept']) ? (int)$_GET['dept'] : null);

        $employees = $this->employeeModel->getAll($search, $status, $deptId);

        $this->success($employees, 'Employees retrieved successfully.');
    }

    #[OA\Get(
        path: "/api/v1/employees/{id}",
        summary: "Retrieve single employee profile",
        description: "Returns comprehensive employee profile details, departmental alignment, and associated user account state. Restricted to HR accounts.",
        tags: ["Employee Management"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Unique employee database ID",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Employee record found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Employee retrieved successfully."),
                        new OA\Property(property: "data", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role"),
            new OA\Response(response: 404, description: "Employee not found")
        ]
    )]
    public function show(int $id): void
    {
        $this->requireRole('hr');

        $employee = $this->employeeModel->findById($id);
        if (!$employee) {
            $this->error('Employee record not found.', 404);
        }

        $this->success($employee, 'Employee retrieved successfully.');
    }

    #[OA\Post(
        path: "/api/v1/employees",
        summary: "Register new employee",
        description: "Creates a new workforce record in the enterprise registry. Enforces unique employee numbers, valid registered emails, and foreign key alignment for department and position. Restricted to HR accounts.",
        tags: ["Employee Management"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Employee registration data",
            content: new OA\JsonContent(
                required: ["employee_no", "first_name", "last_name", "email", "birth_date", "gender", "department_id", "position_id", "date_hired"],
                properties: [
                    new OA\Property(property: "employee_no", type: "string", example: "EMP-2026-101"),
                    new OA\Property(property: "first_name", type: "string", example: "John"),
                    new OA\Property(property: "middle_name", type: "string", nullable: true, example: "Robert"),
                    new OA\Property(property: "last_name", type: "string", example: "Williams"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john.williams@example.com"),
                    new OA\Property(property: "phone", type: "string", nullable: true, example: "+1-555-0199"),
                    new OA\Property(property: "address", type: "string", nullable: true, example: "123 Technology Way"),
                    new OA\Property(property: "birth_date", type: "string", format: "date", example: "1992-05-14"),
                    new OA\Property(property: "gender", type: "string", enum: ["Male", "Female", "Other"], example: "Male"),
                    new OA\Property(property: "department_id", type: "integer", example: 1),
                    new OA\Property(property: "position_id", type: "integer", example: 2),
                    new OA\Property(property: "date_hired", type: "string", format: "date", example: "2026-02-01"),
                    new OA\Property(property: "employment_status", type: "string", enum: ["Active", "Inactive"], example: "Active")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Employee created successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 201),
                        new OA\Property(property: "message", type: "string", example: "Employee created successfully."),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 12),
                                new OA\Property(property: "employee_no", type: "string", example: "EMP-2026-101")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Validation failure (duplicate employee number, email, or invalid dates)"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role or CSRF token failure")
        ]
    )]
    public function store(): void
    {
        $currentUser = $this->requireRole('hr');
        $input = \App\Services\EmployeeValidator::sanitize($this->getJsonInput());

        $errors = $this->validateEmployeeData($input);
        if (!empty($errors)) {
            $this->error('Employee validation failed.', 400, $errors);
        }

        $id = $this->employeeModel->create($input);

        $fullName = trim("{$input['first_name']} {$input['last_name']}");
        $this->userModel->logActivity(
            (int)$currentUser['id'],
            'EMPLOYEE_CREATED',
            "Created employee record: {$fullName} ({$input['employee_no']})",
            'EMPLOYEE',
            (string)$id
        );

        $this->respond([
            'status'  => 'success',
            'code'    => 201,
            'message' => 'Employee created successfully.',
            'data'    => [
                'id'          => $id,
                'employee_no' => $input['employee_no'],
            ],
        ], 201);
    }

    #[OA\Put(
        path: "/api/v1/employees/{id}",
        summary: "Update existing employee record",
        description: "Updates an existing workforce profile. Enforces uniqueness excluding current record. Restricted to HR accounts.",
        tags: ["Employee Management"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Unique employee ID",
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Updated employee data",
            content: new OA\JsonContent(
                required: ["employee_no", "first_name", "last_name", "email", "birth_date", "gender", "department_id", "position_id", "date_hired"],
                properties: [
                    new OA\Property(property: "employee_no", type: "string", example: "EMP-2026-101"),
                    new OA\Property(property: "first_name", type: "string", example: "John"),
                    new OA\Property(property: "middle_name", type: "string", nullable: true, example: "Robert"),
                    new OA\Property(property: "last_name", type: "string", example: "Williams"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "john.williams@example.com"),
                    new OA\Property(property: "phone", type: "string", nullable: true, example: "+1-555-0199"),
                    new OA\Property(property: "address", type: "string", nullable: true, example: "123 Technology Way"),
                    new OA\Property(property: "birth_date", type: "string", format: "date", example: "1992-05-14"),
                    new OA\Property(property: "gender", type: "string", enum: ["Male", "Female", "Other"], example: "Male"),
                    new OA\Property(property: "department_id", type: "integer", example: 1),
                    new OA\Property(property: "position_id", type: "integer", example: 2),
                    new OA\Property(property: "date_hired", type: "string", format: "date", example: "2026-02-01"),
                    new OA\Property(property: "employment_status", type: "string", enum: ["Active", "Inactive"], example: "Active")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Employee record updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Employee updated successfully.")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Validation failure"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role"),
            new OA\Response(response: 404, description: "Employee not found")
        ]
    )]
    public function update(int $id): void
    {
        $currentUser = $this->requireRole('hr');
        $existing = $this->employeeModel->findById($id);
        if (!$existing) {
            $this->error('Employee record not found.', 404);
        }

        $raw = $this->getJsonInput();
        $input = \App\Services\EmployeeValidator::sanitize($raw);
        $errors = $this->validateEmployeeData($input, $id);
        if (!empty($errors)) {
            $this->error('Employee validation failed.', 400, $errors);
        }

        $newStatus = (string)($input['employment_status'] ?? 'Active');
        if ($existing['employment_status'] === 'Active' && $newStatus !== 'Active') {
            // Read the step-up password from the raw body; sanitize() returns only employee fields.
            $stepUp = (new StepUpAuthService($this->userModel))->verify('EMPLOYEE_DEACTIVATE', $raw[StepUpAuthService::FIELD] ?? null);
            if (!$stepUp['ok']) {
                $this->error((string)$stepUp['error'], 403, ['step_up_required' => true]);
            }
        }

        $this->employeeModel->update($id, $input);
        if ($newStatus !== 'Active') {
            $this->revokeLinkedAccount($id);
        }

        $fullName = trim("{$input['first_name']} {$input['last_name']}");
        $this->userModel->logActivity(
            (int)$currentUser['id'],
            'EMPLOYEE_UPDATED',
            "Updated employee record: {$fullName} ({$input['employee_no']})",
            'EMPLOYEE',
            (string)$id
        );

        $this->success(null, 'Employee updated successfully.');
    }

    #[OA\Post(
        path: "/api/v1/employees/{id}/deactivate",
        summary: "Deactivate employee record",
        description: "Sets employee status to 'Inactive' and deactivates any associated login user account. Historical records and leave trails are strictly preserved. Restricted to HR accounts.",
        tags: ["Employee Management"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Unique employee ID",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Employee deactivated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Employee has been placed on Inactive status.")
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Employee is already inactive"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role"),
            new OA\Response(response: 404, description: "Employee not found")
        ]
    )]
    public function deactivate(int $id): void
    {
        $currentUser = $this->requireRole('hr');
        $employee = $this->employeeModel->findById($id);

        if (!$employee) {
            $this->error('Employee record not found.', 404);
        }

        if ($employee['employment_status'] === 'Inactive') {
            $this->error("Employee {$employee['employee_no']} is already inactive.", 400);
        }

        $stepUp = (new StepUpAuthService($this->userModel))->verify('EMPLOYEE_DEACTIVATE', $this->getJsonInput()[StepUpAuthService::FIELD] ?? null);
        if (!$stepUp['ok']) {
            $this->error((string)$stepUp['error'], 403, ['step_up_required' => true]);
        }

        $this->employeeModel->deactivate($id);
        $this->revokeLinkedAccount($id);

        $empName = trim("{$employee['first_name']} {$employee['last_name']}");
        $this->userModel->logActivity(
            (int)$currentUser['id'],
            'EMPLOYEE_DEACTIVATED',
            "Deactivated employee: {$empName} ({$employee['employee_no']})",
            'EMPLOYEE',
            (string)$id
        );

        $this->success(null, "Employee {$empName} ({$employee['employee_no']}) has been placed on Inactive status.");
    }

    #[OA\Post(
        path: "/api/v1/employees/{id}/reactivate",
        summary: "Reactivate employee record",
        description: "Restores an Inactive employee to Active and re-enables their linked login account (identity and history preserved; login still requires MFA). Restricted to HR accounts.",
        tags: ["Employee Management"],
        security: [["cookieAuth" => []], ["csrfToken" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "Unique employee ID", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Employee reactivated successfully"),
            new OA\Response(response: 400, description: "Employee is already active"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role"),
            new OA\Response(response: 404, description: "Employee not found")
        ]
    )]
    public function reactivate(int $id): void
    {
        $currentUser = $this->requireRole('hr');
        $employee = $this->employeeModel->findById($id);

        if (!$employee) {
            $this->error('Employee record not found.', 404);
        }

        if ($employee['employment_status'] === 'Active') {
            $this->error("Employee {$employee['employee_no']} is already active.", 400);
        }

        $stepUp = (new StepUpAuthService($this->userModel))->verify('EMPLOYEE_REACTIVATE', $this->getJsonInput()[StepUpAuthService::FIELD] ?? null);
        if (!$stepUp['ok']) {
            $this->error((string)$stepUp['error'], 403, ['step_up_required' => true]);
        }

        if (!$this->employeeModel->activate($id)) {
            $this->error('Unable to reactivate this employee.', 500);
        }

        $empName = trim("{$employee['first_name']} {$employee['last_name']}");
        $this->userModel->logActivity(
            (int)$currentUser['id'],
            'EMPLOYEE_REACTIVATED',
            "Reactivated employee: {$empName} ({$employee['employee_no']}); linked login account restored.",
            'EMPLOYEE',
            (string)$id
        );

        $this->success(null, "Employee {$empName} ({$employee['employee_no']}) has been reactivated.");
    }

    /**
     * Revoke real-time tickets and pending OTP challenges of the employee's linked login account.
     */
    private function revokeLinkedAccount(int $employeeId): void
    {
        $linkedUser = $this->userModel->findByEmployeeId($employeeId);
        if ($linkedUser && !empty($linkedUser['id'])) {
            (new AccountRevocationService())->revokeUser((int)$linkedUser['id']);
        }
    }

    /**
     * Validate a (pre-sanitized) employee payload using the shared EmployeeValidator,
     * so the API enforces exactly the same rules as the web form: required fields,
     * length limits, strict date formats and bounds, gender/status whitelists,
     * uniqueness (excluding self on update), and position-belongs-to-department.
     *
     * @param array $data Sanitized payload (see EmployeeValidator::sanitize)
     */
    private function validateEmployeeData(array $data, ?int $excludeId = null): array
    {
        return \App\Services\EmployeeValidator::validate(
            $data,
            fn (string $empNo): bool => $this->employeeModel->isEmployeeNoUnique($empNo, $excludeId),
            fn (string $email): bool => $this->employeeModel->isEmailUnique($email, $excludeId),
            fn (int $positionId, int $departmentId): bool => $this->positionBelongsToDepartment($positionId, $departmentId)
        );
    }

    /**
     * Whether a position exists and belongs to the given department.
     */
    private function positionBelongsToDepartment(int $positionId, int $departmentId): bool
    {
        $position = $this->positionModel->findById($positionId);
        return $position !== null && (int)$position['department_id'] === $departmentId;
    }
}
