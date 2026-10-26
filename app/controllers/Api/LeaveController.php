<?php
/**
 * API Leave Management Controller
 * Handles Leave Types & Balances, Employee Leave Requests, and HR Approvals
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers\Api;

use App\Models\Leave;
use App\Models\Employee;
use App\Models\User;
use App\Models\Notification;
use App\Services\LeaveCalculator;
use App\Services\LeaveNotificationService;
use OpenApi\Attributes as OA;

class LeaveController extends ApiController
{
    private Leave $leaveModel;
    private Employee $employeeModel;
    private User $userModel;
    private Notification $notificationModel;
    private LeaveNotificationService $leaveNotifications;

    public function __construct(
        ?Leave $leaveModel = null,
        ?Employee $employeeModel = null,
        ?User $userModel = null,
        ?Notification $notificationModel = null,
        ?LeaveNotificationService $leaveNotifications = null
    ) {
        $this->leaveModel         = $leaveModel ?? new Leave();
        $this->employeeModel     = $employeeModel ?? new Employee();
        $this->userModel         = $userModel ?? new User();
        $this->notificationModel = $notificationModel ?? new Notification();
        $this->leaveNotifications = $leaveNotifications ?? new LeaveNotificationService($this->notificationModel, $this->userModel);
    }

    // =========================================================================
    // GROUP: Leave Types and Balances
    // =========================================================================

    #[OA\Get(
        path: "/api/v1/leave-types",
        summary: "List active leave categories",
        description: "Retrieves all currently active leave policy types (e.g. Vacation Leave, Sick Leave, Emergency Leave) along with default day allowances.",
        tags: ["Leave Types and Balances"],
        security: [["cookieAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of active leave types",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Active leave types retrieved successfully."),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "leave_name", type: "string", example: "Vacation Leave"),
                                    new OA\Property(property: "description", type: "string", example: "Annual earned recreational leave"),
                                    new OA\Property(property: "default_days", type: "integer", example: 15),
                                    new OA\Property(property: "status", type: "string", example: "Active")
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function types(): void
    {
        $this->requireAuth();
        $types = $this->leaveModel->getLeaveTypes();
        $this->success($types, 'Active leave types retrieved successfully.');
    }

    #[OA\Get(
        path: "/api/v1/hr/leave-types",
        summary: "List all leave categories with usage statistics",
        description: "Retrieves all leave types (both Active and Inactive) including counts of associated leave requests and balance records. Restricted to HR accounts.",
        tags: ["Leave Types and Balances"],
        security: [["cookieAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "All leave types with statistics",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "data", type: "array", items: new OA\Items())
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role")
        ]
    )]
    public function hrTypes(): void
    {
        $this->requireRole('hr');
        $types = $this->leaveModel->getAllLeaveTypes();
        $this->success($types, 'All leave types retrieved successfully.');
    }

    #[OA\Post(
        path: "/api/v1/hr/leave-types",
        summary: "Create new leave policy classification",
        description: "Configures a new leave type policy with default annual day quota. Restricted to HR accounts.",
        tags: ["Leave Types and Balances"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Leave policy parameters",
            content: new OA\JsonContent(
                required: ["leave_name", "default_days"],
                properties: [
                    new OA\Property(property: "leave_name", type: "string", example: "Bereavement Leave"),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "Leave allocated for death of immediate family members"),
                    new OA\Property(property: "default_days", type: "integer", example: 5),
                    new OA\Property(property: "status", type: "string", enum: ["Active", "Inactive"], example: "Active")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Leave policy category created successfully"),
            new OA\Response(response: 400, description: "Validation failure (duplicate name or invalid quota)"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role or CSRF token failure")
        ]
    )]
    public function storeType(): void
    {
        $currentUser = $this->requireRole('hr');
        $input = $this->getJsonInput();

        $name = trim((string)($input['leave_name'] ?? ''));
        $days = (int)($input['default_days'] ?? 0);

        if ($name === '') {
            $this->error('Leave type name is required.', 400);
        }

        if ($days <= 0 || $days > 365) {
            $this->error('Default days quota must be between 1 and 365.', 400);
        }

        if (!$this->leaveModel->isLeaveTypeNameUnique($name)) {
            $this->error("A leave classification named '{$name}' already exists.", 400);
        }

        $id = $this->leaveModel->createLeaveType([
            'leave_name'   => $name,
            'description'  => !empty($input['description']) ? trim((string)$input['description']) : null,
            'default_days' => $days,
            'status'       => in_array($input['status'] ?? 'Active', ['Active', 'Inactive'], true) ? $input['status'] : 'Active',
        ]);

        $this->userModel->logActivity(
            (int)$currentUser['id'],
            'LEAVE_TYPE_CREATED',
            "Created leave type: {$name} ({$days} days)",
            'LEAVE_TYPE',
            (string)$id
        );

        $this->respond([
            'status'  => 'success',
            'code'    => 201,
            'message' => 'Leave policy category created successfully.',
            'data'    => ['id' => $id, 'leave_name' => $name],
        ], 201);
    }

    #[OA\Get(
        path: "/api/v1/leave-balances/my",
        summary: "Retrieve personal leave quotas and balances",
        description: "Returns the authenticated employee's allocated, used, and remaining leave quotas for the current or specified calendar year.",
        tags: ["Leave Types and Balances"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "year",
                in: "query",
                description: "Calendar year (default current year)",
                required: false,
                schema: new OA\Schema(type: "integer", example: 2026)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Employee leave balances",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "Leave balances retrieved successfully."),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "leave_name", type: "string", example: "Vacation Leave"),
                                    new OA\Property(property: "year", type: "integer", example: 2026),
                                    new OA\Property(property: "allocated_days", type: "integer", example: 15),
                                    new OA\Property(property: "used_days", type: "integer", example: 3),
                                    new OA\Property(property: "remaining_days", type: "integer", example: 12)
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: No employee record associated with account")
        ]
    )]
    public function myBalances(): void
    {
        $user = $this->requireEmployeeUser();
        $empId = (int)$user['employee_id'];
        $year = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

        $balances = $this->leaveModel->getBalancesByEmployee($empId, $year);
        $this->success($balances, 'Leave balances retrieved successfully.');
    }

    #[OA\Get(
        path: "/api/v1/hr/leave-balances",
        summary: "Retrieve workforce leave balances",
        description: "Returns leave balances across all employees and departments for administrative oversight and reporting. Restricted to HR accounts.",
        tags: ["Leave Types and Balances"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(name: "year", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "department_id", in: "query", schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Workforce leave balances retrieved"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role")
        ]
    )]
    public function hrBalances(): void
    {
        $this->requireRole('hr');
        $year = !empty($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $deptId = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;

        $balances = $this->leaveModel->getAllBalances($year, $deptId);
        $this->success($balances, 'Workforce leave balances retrieved successfully.');
    }

    #[OA\Post(
        path: "/api/v1/hr/leave-balances/adjust",
        summary: "Adjust employee leave balance quota",
        description: "Modifies an employee's allocated leave days for a specific leave category and year, automatically recalculating remaining days. Restricted to HR accounts.",
        tags: ["Leave Types and Balances"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Leave adjustment parameters",
            content: new OA\JsonContent(
                required: ["employee_id", "leave_type_id", "year", "allocated_days", "reason"],
                properties: [
                    new OA\Property(property: "employee_id", type: "integer", example: 5),
                    new OA\Property(property: "leave_type_id", type: "integer", example: 1),
                    new OA\Property(property: "year", type: "integer", example: 2026),
                    new OA\Property(property: "allocated_days", type: "integer", example: 20),
                    new OA\Property(property: "reason", type: "string", example: "Policy tenure bonus quota addition")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Leave balance adjusted successfully"),
            new OA\Response(response: 400, description: "Validation failure"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role or CSRF failure")
        ]
    )]
    public function adjustBalance(): void
    {
        $currentUser = $this->requireRole('hr');
        $input = $this->getJsonInput();

        $empId         = (int)($input['employee_id'] ?? 0);
        $leaveTypeId   = (int)($input['leave_type_id'] ?? 0);
        $year          = (int)($input['year'] ?? date('Y'));
        $allocatedDays = isset($input['allocated_days']) ? (int)$input['allocated_days'] : -1;
        $reason        = trim((string)($input['reason'] ?? ''));

        if ($empId <= 0 || $leaveTypeId <= 0 || $allocatedDays < 0 || empty($reason)) {
            $this->error('Valid employee ID, leave type ID, year, non-negative allocated days, and reason are required.', 400);
        }

        $ok = $this->leaveModel->adjustBalance($empId, $leaveTypeId, $year, $allocatedDays);
        if (!$ok) {
            $this->error('Failed to adjust leave balance quota.', 500);
        }

        $this->userModel->logActivity(
            (int)$currentUser['id'],
            'LEAVE_BALANCE_ADJUSTED',
            "Adjusted leave quota for Employee #{$empId}, Type #{$leaveTypeId}, Year {$year} to {$allocatedDays} days. Reason: {$reason}",
            'LEAVE_BALANCE',
            (string)$empId
        );

        $this->success(null, "Leave balance adjusted to {$allocatedDays} days successfully.");
    }

    // =========================================================================
    // GROUP: Leave Requests
    // =========================================================================

    #[OA\Get(
        path: "/api/v1/leaves/my",
        summary: "List personal leave application history",
        description: "Returns the authenticated employee's leave applications. Strictly enforces ownership boundaries; employees can never see other employees' applications.",
        tags: ["Leave Requests"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "status",
                in: "query",
                description: "Filter by request status",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["Pending", "Approved", "Rejected", "Cancelled"])
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Personal leave applications",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "data", type: "array", items: new OA\Items())
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: No employee record associated with account")
        ]
    )]
    public function myLeaves(): void
    {
        $user = $this->requireEmployeeUser();
        $empId = (int)$user['employee_id'];
        $status = !empty($_GET['status']) ? trim((string)$_GET['status']) : null;

        $leaves = $this->leaveModel->getAllRequests($status, $empId);
        $this->success($leaves, 'Personal leave history retrieved successfully.');
    }

    #[OA\Post(
        path: "/api/v1/leaves",
        summary: "Submit new leave application",
        description: "Files a new leave request. Automatically calculates eligible working days (Mon-Fri), verifies that dates do not overlap with existing pending/approved requests, and validates that sufficient leave quota remains.",
        tags: ["Leave Requests"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Leave application payload",
            content: new OA\JsonContent(
                required: ["leave_type_id", "start_date", "end_date", "reason"],
                properties: [
                    new OA\Property(property: "leave_type_id", type: "integer", example: 1),
                    new OA\Property(property: "start_date", type: "string", format: "date", example: "2026-11-10"),
                    new OA\Property(property: "end_date", type: "string", format: "date", example: "2026-11-14"),
                    new OA\Property(property: "reason", type: "string", example: "Family vacation")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Leave application submitted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "success"),
                        new OA\Property(property: "code", type: "integer", example: 201),
                        new OA\Property(property: "message", type: "string", example: "Leave application submitted successfully."),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer", example: 8),
                                new OA\Property(property: "number_of_days", type: "integer", example: 5),
                                new OA\Property(property: "status", type: "string", example: "Pending")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: "Validation failure (overlapping request, insufficient balance, or zero working days)"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: No employee profile or CSRF validation failure")
        ]
    )]
    public function apply(): void
    {
        $user = $this->requireEmployeeUser();
        $empId = (int)$user['employee_id'];
        $input = $this->getJsonInput();

        $leaveTypeId = (int)($input['leave_type_id'] ?? 0);
        $startDate   = trim((string)($input['start_date'] ?? ''));
        $endDate     = trim((string)($input['end_date'] ?? ''));
        $reason      = trim((string)($input['reason'] ?? ''));

        if (!$leaveTypeId || empty($startDate) || empty($endDate) || empty($reason)) {
            $this->error('All fields (leave_type_id, start_date, end_date, and reason) are required.', 400);
        }

        $leaveType = $this->leaveModel->getLeaveTypeById($leaveTypeId);
        if (!$leaveType || $leaveType['status'] !== 'Active') {
            $this->error('Selected leave classification is either inactive or does not exist.', 400);
        }

        $rangeValidation = LeaveCalculator::validateDateRange($startDate, $endDate);
        if (!$rangeValidation['valid']) {
            $this->error($rangeValidation['error'] ?? 'Invalid date range specified.', 400);
        }

        $days = (int)$rangeValidation['working_days'];
        if ($days === 0) {
            $this->error('Selected date range contains 0 working days (e.g. falls entirely on weekends).', 400);
        }

        // Overlap detection
        $overlapping = $this->leaveModel->getOverlappingRequest($empId, $startDate, $endDate);
        if ($overlapping) {
            $this->error("Date range overlaps with existing {$overlapping['status']} leave request #{$overlapping['id']} ({$overlapping['leave_name']}).", 400);
        }

        // Quota verification
        $year = (int)date('Y', strtotime($startDate));
        $balance = $this->leaveModel->getBalance($empId, $leaveTypeId, $year);
        $remaining = $balance ? (int)$balance['remaining_days'] : 0;
        $pendingDays = $this->leaveModel->getPendingDays($empId, $leaveTypeId, $year);

        if ($remaining <= 0 || ($days + $pendingDays) > $remaining) {
            $available = max(0, $remaining - $pendingDays);
            $this->error("Insufficient leave balance quota. Requested: {$days} days; Available: {$available} days.", 400);
        }

        // Leave request + HR notifications commit atomically (real-time outbox)
        try {
            $requestId = $this->leaveModel->createRequest([
                'employee_id'    => $empId,
                'leave_type_id'  => $leaveTypeId,
                'start_date'     => $startDate,
                'end_date'       => $endDate,
                'number_of_days' => $days,
                'reason'         => $reason,
            ], function (int $newId) use ($user, $leaveType, $days): void {
                $this->leaveNotifications->leaveSubmitted(
                    $newId,
                    "{$user['name']} submitted a {$leaveType['leave_name']} application for {$days} working days."
                );
            });
        } catch (\Throwable $e) {
            error_log('[Api\\LeaveController::apply] Leave submission failed: ' . $e->getMessage());
            $this->error('Unable to submit leave application at this time.', 500);
        }

        $this->userModel->logActivity(
            (int)$user['id'],
            'LEAVE_REQUESTED',
            "Submitted {$leaveType['leave_name']} request for {$days} days ({$startDate} to {$endDate})",
            'LEAVE_REQUEST',
            (string)$requestId
        );

        $this->respond([
            'status'  => 'success',
            'code'    => 201,
            'message' => 'Leave application submitted successfully.',
            'data'    => [
                'id'             => $requestId,
                'number_of_days' => $days,
                'status'         => 'Pending',
            ],
        ], 201);
    }

    #[OA\Get(
        path: "/api/v1/leaves/{id}",
        summary: "Retrieve leave request details",
        description: "Returns details of a specific leave request. Strictly restricted to the employee who submitted it or HR personnel.",
        tags: ["Leave Requests"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Leave request details"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Not permitted to view another employee's request"),
            new OA\Response(response: 404, description: "Leave request not found")
        ]
    )]
    public function show(int $id): void
    {
        $user = $this->requireAuth();
        $request = $this->leaveModel->getRequestById($id);

        if (!$request) {
            $this->error('Leave request not found.', 404);
        }

        // Ownership enforcement: Employee can ONLY view own request
        $isOwner = (!empty($user['employee_id']) && (int)$request['employee_id'] === (int)$user['employee_id']);
        $isHr = (strtolower((string)($user['role'] ?? '')) === 'hr');

        if (!$isOwner && !$isHr) {
            $this->error("Forbidden: You are not authorized to view another employee's leave request.", 403);
        }

        $this->success($request, 'Leave request retrieved successfully.');
    }

    #[OA\Post(
        path: "/api/v1/leaves/{id}/cancel",
        summary: "Cancel pending leave application",
        description: "Allows an employee to cancel their own pending leave application before it has been processed. Employees cannot cancel requests belonging to other employees or requests that are already Approved/Rejected.",
        tags: ["Leave Requests"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Leave request cancelled successfully"),
            new OA\Response(response: 400, description: "Request cannot be cancelled (not in Pending status)"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Not permitted to cancel another employee's request"),
            new OA\Response(response: 404, description: "Leave request not found")
        ]
    )]
    public function cancel(int $id): void
    {
        $user = $this->requireEmployeeUser();
        $empId = (int)$user['employee_id'];

        $request = $this->leaveModel->getRequestById($id);
        if (!$request) {
            $this->error('Leave request not found.', 404);
        }

        if ((int)$request['employee_id'] !== $empId) {
            $this->error("Forbidden: You are not authorized to cancel another employee's leave request.", 403);
        }

        if ($request['status'] !== 'Pending') {
            $this->error("Only leave requests in Pending status can be cancelled. Current status is '{$request['status']}'.", 400);
        }

        $ok = $this->leaveModel->cancelRequest($id, $empId);
        if (!$ok) {
            $this->error('Failed to cancel leave request.', 500);
        }

        $this->userModel->logActivity(
            (int)$user['id'],
            'LEAVE_CANCELLED',
            "Cancelled leave request #{$id} ({$request['leave_name']})",
            'LEAVE_REQUEST',
            (string)$id
        );

        $this->success(null, "Leave request #{$id} has been cancelled successfully.");
    }

    // =========================================================================
    // GROUP: HR Approvals
    // =========================================================================

    #[OA\Get(
        path: "/api/v1/hr/leaves",
        summary: "Review workforce leave applications",
        description: "Retrieves submitted leave requests across the workforce for review and adjudication. Allows filtering by status, employee, department, and date range. Restricted to HR accounts.",
        tags: ["HR Approvals"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["Pending", "Approved", "Rejected", "Cancelled"])),
            new OA\Parameter(name: "employee_id", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "department_id", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "start_date", in: "query", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "limit", in: "query", schema: new OA\Schema(type: "integer", default: 50))
        ],
        responses: [
            new OA\Response(response: 200, description: "Workforce leave requests retrieved"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role")
        ]
    )]
    public function hrLeaves(): void
    {
        $this->requireRole('hr');

        $status    = !empty($_GET['status']) ? trim((string)$_GET['status']) : null;
        $empId     = !empty($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
        $deptId    = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;
        $startDate = !empty($_GET['start_date']) ? trim((string)$_GET['start_date']) : null;
        $endDate   = !empty($_GET['end_date']) ? trim((string)$_GET['end_date']) : null;
        $limit     = min(200, max(1, (int)($_GET['limit'] ?? 50)));

        $requests = $this->leaveModel->getAllRequests($status, $empId, $limit, $startDate, $endDate, $deptId);
        $this->success($requests, 'Workforce leave requests retrieved successfully.');
    }

    #[OA\Post(
        path: "/api/v1/hr/leaves/{id}/approve",
        summary: "Approve leave application",
        description: "Executes atomic approval of a pending leave request, automatically deducting allocated quota from the employee's balance and re-verifying that no overlapping approval has occurred. Restricted to HR accounts.",
        tags: ["HR Approvals"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Leave request approved successfully"),
            new OA\Response(response: 400, description: "Request cannot be approved (already processed or overlap detected)"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role or CSRF failure"),
            new OA\Response(response: 404, description: "Leave request not found")
        ]
    )]
    public function approve(int $id): void
    {
        $currentUser = $this->requireRole('hr');

        // Execute full atomic approval transaction (status, balance, activity log, persistent notification)
        $result = $this->leaveModel->approveRequest(
            $id,
            (int)$currentUser['id'],
            function (array $req): void {
                if (!$this->leaveNotifications->leaveDecided(
                    $req,
                    'Approved',
                    "Your {$req['leave_name']} request for {$req['start_date']} to {$req['end_date']} has been approved."
                )) {
                    throw new \RuntimeException('Failed to create approval notification for employee.');
                }
            }
        );

        if (!$result['ok']) {
            $this->error($result['message'], $result['http_status']);
        }

        $this->success($result['data'] ?? null, "Leave request #{$id} approved successfully.");
    }

    #[OA\Post(
        path: "/api/v1/hr/leaves/{id}/reject",
        summary: "Reject leave application",
        description: "Rejects a pending leave application and records the mandatory or optional administrative rejection rationale. Preserves employee leave balance quotas. Restricted to HR accounts.",
        tags: ["HR Approvals"],
        security: [
            ["cookieAuth" => []],
            ["csrfToken" => []]
        ],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            description: "Rejection rationale",
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "rejection_reason", type: "string", example: "Operational staffing requirements during peak period")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Leave request rejected"),
            new OA\Response(response: 400, description: "Request cannot be rejected (not in Pending status)"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden: Restricted to HR role"),
            new OA\Response(response: 404, description: "Leave request not found")
        ]
    )]
    public function reject(int $id): void
    {
        $currentUser = $this->requireRole('hr');
        $input = $this->getJsonInput();
        $reason = trim((string)($input['rejection_reason'] ?? ''));

        $result = $this->leaveModel->rejectRequest(
            $id,
            (int)$currentUser['id'],
            $reason,
            function (array $req) use ($reason): void {
                if (!$this->leaveNotifications->leaveDecided(
                    $req,
                    'Rejected',
                    "Your {$req['leave_name']} request was rejected." . ($reason ? " Reason: {$reason}" : "")
                )) {
                    throw new \RuntimeException('Failed to create rejection notification for employee.');
                }
            }
        );

        if (!$result['ok']) {
            $this->error($result['message'], $result['http_status']);
        }

        $this->success(null, "Leave request #{$id} rejected.");
    }
}
