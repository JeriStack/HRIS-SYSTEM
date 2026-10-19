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
        $input = $this->getJsonInput();

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

        $input = $this->getJsonInput();
        $errors = $this->validateEmployeeData($input, $id);
        if (!empty($errors)) {
            $this->error('Employee validation failed.', 400, $errors);
        }

        $this->employeeModel->update($id, $input);

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

        $this->employeeModel->deactivate($id);

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

    private function validateEmployeeData(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        $empNo     = trim((string)($data['employee_no'] ?? ''));
        $firstName = trim((string)($data['first_name'] ?? ''));
        $lastName  = trim((string)($data['last_name'] ?? ''));
        $email     = trim((string)($data['email'] ?? ''));
        $birthDate = trim((string)($data['birth_date'] ?? ''));
        $gender    = trim((string)($data['gender'] ?? ''));
        $deptId    = (int)($data['department_id'] ?? 0);
        $posId     = (int)($data['position_id'] ?? 0);
        $dateHired = trim((string)($data['date_hired'] ?? ''));

        if ($empNo === '') {
            $errors['employee_no'] = 'Employee number is required.';
        } elseif (!$this->employeeModel->isEmployeeNoUnique($empNo, $excludeId)) {
            $errors['employee_no'] = "Employee number '{$empNo}' is already taken.";
        }

        if ($firstName === '') {
            $errors['first_name'] = 'First name is required.';
        }

        if ($lastName === '') {
            $errors['last_name'] = 'Last name is required.';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email address is required.';
        } elseif (!$this->employeeModel->isEmailUnique($email, $excludeId)) {
            $errors['email'] = "Email address '{$email}' is already registered.";
        }

        if ($birthDate === '') {
            $errors['birth_date'] = 'Birth date is required.';
        }

        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $errors['gender'] = 'Valid gender option is required.';
        }

        $dept = $this->departmentModel->findById($deptId);
        if (!$dept) {
            $errors['department_id'] = 'Valid department is required.';
        }

        $pos = $this->positionModel->findById($posId);
        if (!$pos) {
            $errors['position_id'] = 'Valid position is required.';
        } elseif ($dept && (int)$pos['department_id'] !== $deptId) {
            $errors['position_id'] = 'Selected position does not belong to the selected department.';
        }

        if ($dateHired === '') {
            $errors['date_hired'] = 'Date hired is required.';
        }

        return $errors;
    }
}
