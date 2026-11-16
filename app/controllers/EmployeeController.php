<?php
/**
 * Employee Controller
 * Core workforce directory, profiles, registration, updates, and soft-deactivation
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use App\Models\Department;
use App\Models\EmergencyContact;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\Notification;
use App\Models\Position;
use App\Models\ProfileCorrectionRequest;
use App\Models\User;
use App\Services\AccountRevocationService;
use App\Services\EmployeeTenureCalculator;
use App\Services\ProfilePictureService;
use App\Services\StepUpAuthService;
use Core\AuditService;
use Core\Controller;
use Core\Database;
use Core\Session;
use PDO;

class EmployeeController extends Controller
{
    private PDO $pdo;
    private Employee $employeeModel;
    private Department $departmentModel;
    private Position $positionModel;
    private User $userModel;
    private EmploymentHistory $historyModel;
    private EmergencyContact $emergencyModel;
    private ProfileCorrectionRequest $correctionModel;
    private bool $terminateOnRedirect;

    public function __construct(?PDO $pdo = null, bool $terminateOnRedirect = true)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
        $this->employeeModel = new Employee($this->pdo);
        $this->departmentModel = new Department($this->pdo);
        $this->positionModel = new Position($this->pdo);
        $this->userModel = new User($this->pdo);
        $this->historyModel = new EmploymentHistory($this->pdo);
        $this->emergencyModel = new EmergencyContact($this->pdo);
        $this->correctionModel = new ProfileCorrectionRequest($this->pdo);
        $this->terminateOnRedirect = $terminateOnRedirect;
    }

    /**
     * Display searchable employee directory with status & department filters.
     * Route: GET /hr/employees
     */
    public function index(): void
    {
        $search = !empty($_GET['q']) ? trim((string)$_GET['q']) : null;
        $status = !empty($_GET['status']) ? trim((string)$_GET['status']) : null;
        $deptId = !empty($_GET['dept']) ? (int)$_GET['dept'] : null;

        // Phase 10: optional custom DSA searching and sorting over the authorized dataset.
        // Every selector is validated against a server-side allowlist; invalid
        // values fall back to default SQL searching/ordering without error.
        $directory     = new \App\Services\EmployeeDirectoryService();
        $searchAlgo    = isset($_GET['search_algo']) ? trim((string)$_GET['search_algo']) : '';
        $searchField   = isset($_GET['search_field']) ? trim((string)$_GET['search_field']) : 'last_name';
        $dsaSearchMeta = null;

        // If custom DSA search is chosen and a query is supplied:
        if ($search !== null && $search !== '' && $searchAlgo !== '') {
            if (
                \App\Services\EmployeeDirectoryService::isValidSearchAlgorithm($searchAlgo)
                && \App\Services\EmployeeDirectoryService::isValidSearchField($searchField)
            ) {
                // Fetch authorized rows filtered by status/department, bypassing SQL text filtering
                $employees = $this->employeeModel->getAll(null, $status, $deptId);
                $searchResult = $directory->search($employees, $searchField, $search, $searchAlgo);
                $employees = $searchResult['employees'];
                $dsaSearchMeta = $searchResult['meta'];
            } else {
                Session::flash('error', 'Invalid search option. Showing default search results.');
                $employees = $this->employeeModel->getAll($search, $status, $deptId);
                $searchAlgo = '';
            }
        } else {
            // Default SQL search
            $employees = $this->employeeModel->getAll($search, $status, $deptId);
        }

        $departments = $this->departmentModel->getAll();
        $counts = $this->employeeModel->getCounts();

        // Phase 10: optional custom DSA sorting over the authorized dataset.
        $sortField = isset($_GET['sort']) ? trim((string)$_GET['sort']) : '';
        $sortDir   = isset($_GET['dir']) ? trim((string)$_GET['dir']) : 'asc';
        $sortAlgo  = isset($_GET['algo']) ? trim((string)$_GET['algo']) : 'quick';
        $dsaMeta   = null;

        if ($sortField !== '') {
            if (
                \App\Services\EmployeeDirectoryService::isValidSortField($sortField)
                && \App\Services\EmployeeDirectoryService::isValidSortAlgorithm($sortAlgo)
                && in_array(strtolower($sortDir), ['asc', 'desc'], true)
            ) {
                $result = $directory->sort($employees, $sortField, $sortDir, $sortAlgo);
                $employees = $result['employees'];
                $dsaMeta = $result['meta'];
            } else {
                Session::flash('error', 'Invalid sort option. Showing the default ordering.');
                $sortField = '';
            }
        }

        $canManageHrRecords = can('employees.update');
        if (!$canManageHrRecords) {
            foreach ($employees as &$empRow) {
                $empRow['phone'] = null;
                $empRow['personal_email'] = null;
                $empRow['address'] = null;
                $empRow['birth_date'] = null;
                $empRow['civil_status'] = null;
            }
            unset($empRow);
        }

        $this->view('employees.index', [
            'pageTitle'          => 'Employees Directory',
            'employees'          => $employees,
            'canManageHrRecords' => $canManageHrRecords,
            'departments'      => $departments,
            'counts'           => $counts,
            'searchQuery'      => $search,
            'selectedStatus'   => $status,
            'selectedDept'     => $deptId,
            'searchAlgo'       => $searchAlgo,
            'searchField'      => $searchField,
            'searchFields'     => \App\Services\EmployeeDirectoryService::searchFields(),
            'searchAlgorithms' => \App\Services\EmployeeDirectoryService::searchAlgorithmChoices(),
            'dsaSearchMeta'    => $dsaSearchMeta,
            'sortField'        => $sortField,
            'sortDir'          => strtolower($sortDir) === 'desc' ? 'desc' : 'asc',
            'sortAlgo'         => $sortAlgo,
            'sortFields'       => \App\Services\EmployeeDirectoryService::sortFields(),
            'sortAlgorithms'   => \App\Services\EmployeeDirectoryService::sortAlgorithmChoices(),
            'dsaMeta'          => $dsaMeta,
            'stepUpRequired'   => $this->stepUpRequired(),
        ], 'hr');
    }

    /**
     * View detailed individual employee profile.
     * Route: GET /hr/employees/{id}
     */
    public function show(int $id): void
    {
        $employee = $this->employeeModel->findById($id);

        if (!$employee) {
            Session::flash('error', 'The requested employee profile was not found.');
            $this->navigate('/hr/employees');
            return;
        }

        $tenure = EmployeeTenureCalculator::calculate($employee['date_hired'] ?? null);
        $avatarUrl = ProfilePictureService::avatarUrl((int)$employee['id'], $employee['profile_picture'] ?? null);
        $canManageHrRecords = can('employees.update');

        $emergencyContacts = [];
        $employmentHistory = [];
        $lastPromotion = null;
        $correctionRequests = [];

        if ($canManageHrRecords) {
            try {
                $emergencyContacts = $this->emergencyModel->getByEmployee($id);
            } catch (\Throwable $e) {
                error_log('[EmployeeController::show] Emergency contacts query skipped: ' . $e->getMessage());
            }

            try {
                $correctionRequests = $this->correctionModel->getByEmployee($id, 20);
            } catch (\Throwable $e) {
                error_log('[EmployeeController::show] Correction requests query skipped: ' . $e->getMessage());
            }
        } else {
            // Least-privilege Admin directory oversight: mask personal contact, residential, and demographic fields.
            $employee['personal_email'] = null;
            $employee['phone'] = null;
            $employee['address'] = null;
            $employee['birth_date'] = null;
            $employee['civil_status'] = null;
        }

        try {
            $employmentHistory = $this->historyModel->getByEmployee($id);
            $lastPromotion = $this->historyModel->getLastPromotion($id);
        } catch (\Throwable $e) {
            error_log('[EmployeeController::show] Employment history query skipped: ' . $e->getMessage());
        }

        $this->view('employees.show', [
            'pageTitle'          => "Profile: {$employee['first_name']} {$employee['last_name']} ({$employee['employee_no']})",
            'employee'           => $employee,
            'avatarUrl'          => $avatarUrl,
            'tenure'             => $tenure,
            'lastPromotion'      => $lastPromotion,
            'emergencyContacts'  => $emergencyContacts,
            'employmentHistory'  => $employmentHistory,
            'correctionRequests' => $correctionRequests,
            'canManageHrRecords' => $canManageHrRecords,
            'departments'        => $this->fetchDepartmentsList(),
            'positions'          => $this->fetchPositionsList(),
            'employmentTypes'    => (array)config('profile.employment_types', []),
            'eventTypes'         => EmploymentHistory::VALID_EVENT_TYPES,
            'historyErrors'      => Session::getFlash('history_form_errors', []),
            'historyOld'         => Session::getFlash('history_form_old', []),
            'stepUpRequired'     => $this->stepUpRequired(),
        ], 'hr');
    }

    /**
     * Display new employee registration form.
     * Route: GET /hr/employees/create
     */
    public function create(): void
    {
        $departments = $this->departmentModel->getAll();
        $positions = $this->positionModel->getAll();

        $this->view('employees.create', [
            'pageTitle'       => 'Add New Employee Record',
            'departments'     => $departments,
            'positions'       => $positions,
            'civilStatuses'   => (array)config('profile.civil_statuses', []),
            'employmentTypes' => (array)config('profile.employment_types', []),
            'errors'          => Session::getFlash('errors', []),
            'old'             => Session::getFlash('old', []),
        ], 'hr');
    }

    /**
     * Process employee registration request.
     * Route: POST /hr/employees
     */
    public function store(): void
    {
        $data = $this->sanitizeInput($_POST);
        $errors = $this->validateEmployeeData($data);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->navigate('/hr/employees/create');
            return;
        }

        // Insert new employee record
        $employeeId = $this->employeeModel->create($data);

        // Record immutable initial hiring milestone in employment history
        try {
            $this->historyModel->recordEvent([
                'employee_id'            => $employeeId,
                'event_type'             => EmploymentHistory::EVENT_INITIAL_HIRING,
                'effective_date'         => $data['date_hired'],
                'previous_department_id' => null,
                'new_department_id'      => !empty($data['department_id']) ? (int)$data['department_id'] : null,
                'previous_position_id'   => null,
                'new_position_id'        => !empty($data['position_id']) ? (int)$data['position_id'] : null,
                'employment_type'        => $data['employment_type'] ?? 'Regular',
                'description'            => 'Initial appointment upon employee onboarding.',
                'recorded_by'            => currentUserId(),
            ]);
        } catch (\Throwable $e) {
            error_log('[EmployeeController::store] Initial hiring history failed: ' . $e->getMessage());
        }

        // Real-time notification for HR workforce views
        try {
            $notificationModel = new \App\Models\Notification();
            $empName = trim("{$data['first_name']} {$data['last_name']}");
            $notificationModel->notifyRole(
                'hr',
                'New Employee Registered',
                "Employee record for {$empName} ({$data['employee_no']}) was registered.",
                'info',
                \App\Realtime\EventAuthorizer::PROFILE_UPDATED,
                $employeeId
            );
        } catch (\Throwable $e) {
            error_log('[EmployeeController] Failed to dispatch HR new employee notification: ' . $e->getMessage());
        }

        // Audit Logging
        $empName = trim("{$data['first_name']} {$data['last_name']}");
        $this->userModel->logActivity(
            currentUserId(),
            'EMPLOYEE_CREATED',
            "Created employee record: {$empName} ({$data['employee_no']})",
            'EMPLOYEE',
            (string)$employeeId,
            null,
            [
                'employee_no'       => $data['employee_no'],
                'name'              => $empName,
                'email'             => $data['email'],
                'department_id'     => $data['department_id'],
                'position_id'       => $data['position_id'],
                'employment_status' => $data['employment_status'],
            ]
        );

        Session::flash('success', "Employee {$empName} ({$data['employee_no']}) was successfully registered.");
        $this->redirect("/hr/employees/{$employeeId}");
    }

    /**
     * Display employee edit form.
     * Route: GET /hr/employees/{id}/edit
     */
    public function edit(int $id): void
    {
        $employee = $this->employeeModel->findById($id);

        if (!$employee) {
            Session::flash('error', 'The requested employee record was not found.');
            $this->redirect('/hr/employees');
            return;
        }

        $departments = $this->departmentModel->getAll();
        $positions = $this->positionModel->getAll();

        $this->view('employees.edit', [
            'pageTitle'       => "Edit Employee Record: {$employee['employee_no']}",
            'employee'        => $employee,
            'departments'     => $departments,
            'positions'       => $positions,
            'civilStatuses'   => (array)config('profile.civil_statuses', []),
            'employmentTypes' => (array)config('profile.employment_types', []),
            'errors'          => Session::getFlash('errors', []),
            'old'             => Session::getFlash('old', []),
            'stepUpRequired'  => $this->stepUpRequired(),
        ], 'hr');
    }

    /**
     * Process employee record update.
     * Route: POST /hr/employees/{id}/edit
     */
    public function update(int $id): void
    {
        $employee = $this->employeeModel->findById($id);

        if (!$employee) {
            Session::flash('error', 'The requested employee record was not found.');
            $this->redirect('/hr/employees');
            return;
        }

        $data = $this->sanitizeInput($_POST);
        $errors = $this->validateEmployeeData($data, $id);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect("/hr/employees/{$id}/edit");
            return;
        }

        // Deactivating through the edit form is a sensitive operation as well
        $isDeactivating = ($employee['employment_status'] === 'Active' && $data['employment_status'] !== 'Active');
        if ($isDeactivating) {
            $stepUp = (new StepUpAuthService($this->userModel))->verify('EMPLOYEE_DEACTIVATE', $_POST[StepUpAuthService::FIELD] ?? null);
            if (!$stepUp['ok']) {
                Session::flash('error', $stepUp['error']);
                Session::flash('old', array_diff_key($_POST, array_flip([StepUpAuthService::FIELD, '_csrf_token'])));
                $this->redirect("/hr/employees/{$id}/edit");
                return;
            }
        }

        // Execute database update
        $this->employeeModel->update($id, $data);
        $this->recordAutomaticHistoryTransitions($id, $employee, $data, $_POST);

        if ($data['employment_status'] !== 'Active') {
            $this->revokeLinkedAccount($id);
        }

        // Find linked user account for this employee and dispatch real-time notification
        $linkedUser = $this->userModel->findByEmployeeId($id);
        if ($linkedUser && !empty($linkedUser['id'])) {
            $oldWorkEmail = strtolower(trim((string)($employee['email'] ?? '')));
            $newWorkEmail = strtolower(trim((string)($data['email'] ?? '')));
            if ($oldWorkEmail !== $newWorkEmail && $newWorkEmail !== '') {
                try {
                    (new \App\Services\AuthRiskService())->flagEmailChanged((int)$linkedUser['id']);
                } catch (\Throwable $e) {
                    error_log('[EmployeeController] Failed to flag email change step-up: ' . $e->getMessage());
                }
            }
        }
        $notificationModel = new Notification($this->pdo);
        if ($linkedUser && !empty($linkedUser['id'])) {
            try {
                $notificationModel->create(
                    (int)$linkedUser['id'],
                    'Profile Updated',
                    'Your employee profile record was updated by Human Resources.',
                    'info',
                    \App\Realtime\EventAuthorizer::PROFILE_UPDATED,
                    $id
                );
            } catch (\Throwable $e) {
                error_log('[EmployeeController] Failed to dispatch profile update notification: ' . $e->getMessage());
            }
        }

        // Notify HR users so HR dashboard and employee directories auto-reload
        try {
            $empName = trim("{$data['first_name']} {$data['last_name']}");
            $notificationModel->notifyRole(
                'hr',
                'Employee Record Updated',
                "Employee record for {$empName} ({$data['employee_no']}) was updated.",
                'info',
                \App\Realtime\EventAuthorizer::PROFILE_UPDATED,
                $id
            );
        } catch (\Throwable $e) {
            error_log('[EmployeeController] Failed to dispatch HR profile update notification: ' . $e->getMessage());
        }

        // Audit Logging with before/after state diffing
        $empName = trim("{$data['first_name']} {$data['last_name']}");
        $trackedFields = [
            'first_name', 'middle_name', 'last_name', 'email', 'personal_email',
            'phone', 'civil_status', 'department_id', 'position_id',
            'employment_type', 'employment_status', 'date_hired', 'address',
        ];
        $oldDiff = [];
        $newDiff = [];
        foreach ($trackedFields as $field) {
            if (array_key_exists($field, $data) && (string)($employee[$field] ?? '') !== (string)($data[$field] ?? '')) {
                $oldDiff[$field] = $employee[$field] ?? null;
                $newDiff[$field] = $data[$field];
            }
        }

        $this->userModel->logActivity(
            currentUserId(),
            'EMPLOYEE_UPDATED',
            "Updated employee record: {$empName} ({$data['employee_no']})",
            'EMPLOYEE',
            (string)$id,
            !empty($oldDiff) ? $oldDiff : null,
            !empty($newDiff) ? $newDiff : null
        );

        Session::flash('success', "Employee record for {$empName} ({$data['employee_no']}) was updated successfully.");
        $this->navigate("/hr/employees/{$id}");
    }

    /**
     * Soft deactivation of an employee record.
     * Route: POST /hr/employees/{id}/deactivate
     */
    public function deactivate(int $id): void
    {
        $employee = $this->employeeModel->findById($id);

        if (!$employee) {
            Session::flash('error', 'The requested employee record was not found.');
            $this->redirect('/hr/employees');
            return;
        }

        if ($employee['employment_status'] === 'Inactive') {
            Session::flash('warning', "Employee {$employee['employee_no']} is already inactive.");
            $this->redirect('/hr/employees');
            return;
        }

        // Sensitive-operation protection: recent MFA or current-password re-verification
        $stepUp = (new StepUpAuthService($this->userModel))->verify('EMPLOYEE_DEACTIVATE', $_POST[StepUpAuthService::FIELD] ?? null);
        if (!$stepUp['ok']) {
            Session::flash('error', $stepUp['error']);
            $this->redirect("/hr/employees/{$id}");
            return;
        }

        // Execute soft deactivation
        $this->employeeModel->deactivate($id);
        $this->revokeLinkedAccount($id);

        // Audit Logging
        $empName = trim("{$employee['first_name']} {$employee['last_name']}");
        $this->userModel->logActivity(
            currentUserId(),
            'EMPLOYEE_DEACTIVATED',
            "Deactivated employee: {$empName} ({$employee['employee_no']})",
            'EMPLOYEE',
            (string)$id,
            ['employment_status' => (string)($employee['employment_status'] ?? 'Active')],
            ['employment_status' => 'Inactive']
        );

        // Notify HR users so HR dashboard metrics update
        try {
            $notificationModel = new \App\Models\Notification();
            $notificationModel->notifyRole(
                'hr',
                'Employee Deactivated',
                "Employee record for {$empName} ({$employee['employee_no']}) was placed on Inactive status.",
                'warning',
                \App\Realtime\EventAuthorizer::PROFILE_UPDATED,
                $id
            );
        } catch (\Throwable $e) {
            error_log('[EmployeeController] Failed to dispatch HR deactivation notification: ' . $e->getMessage());
        }

        Session::flash('success', "Employee {$empName} ({$employee['employee_no']}) has been placed on Inactive status. Historical records were preserved.");
        $this->redirect('/hr/employees');
    }

    /**
     * Reactivate an inactive employee and restore their linked login account.
     * Route: POST /hr/employees/{id}/reactivate
     */
    public function reactivate(int $id): void
    {
        $employee = $this->employeeModel->findById($id);

        if (!$employee) {
            Session::flash('error', 'The requested employee record was not found.');
            $this->redirect('/hr/employees');
            return;
        }

        if ($employee['employment_status'] === 'Active') {
            Session::flash('warning', "Employee {$employee['employee_no']} is already active.");
            $this->redirect("/hr/employees/{$id}");
            return;
        }

        // Reactivation re-enables system access, so it is a sensitive operation too.
        $stepUp = (new StepUpAuthService($this->userModel))->verify('EMPLOYEE_REACTIVATE', $_POST[StepUpAuthService::FIELD] ?? null);
        if (!$stepUp['ok']) {
            Session::flash('error', $stepUp['error']);
            $this->redirect("/hr/employees/{$id}");
            return;
        }

        // Employee::activate restores the employee row and the linked login account in one
        // transaction (decision F.3). The account keeps its identity; login still requires MFA.
        if (!$this->employeeModel->activate($id)) {
            Session::flash('error', 'Unable to reactivate this employee. Please try again.');
            $this->redirect("/hr/employees/{$id}");
            return;
        }

        $empName = trim("{$employee['first_name']} {$employee['last_name']}");
        $this->userModel->logActivity(
            currentUserId(),
            'EMPLOYEE_REACTIVATED',
            "Reactivated employee: {$empName} ({$employee['employee_no']}); linked login account restored.",
            'EMPLOYEE',
            (string)$id
        );

        // Notify HR users and reactivated employee so dashboards reload
        try {
            $notificationModel = new \App\Models\Notification();
            $notificationModel->notifyRole(
                'hr',
                'Employee Reactivated',
                "Employee record for {$empName} ({$employee['employee_no']}) was reactivated.",
                'success',
                \App\Realtime\EventAuthorizer::PROFILE_UPDATED,
                $id
            );
            $linkedUser = $this->userModel->findByEmployeeId($id);
            if ($linkedUser && !empty($linkedUser['id'])) {
                $notificationModel->create(
                    (int)$linkedUser['id'],
                    'Account Reactivated',
                    'Your employee record and account have been reactivated.',
                    'success',
                    \App\Realtime\EventAuthorizer::PROFILE_UPDATED,
                    $id
                );
            }
        } catch (\Throwable $e) {
            error_log('[EmployeeController] Failed to dispatch reactivation notification: ' . $e->getMessage());
        }

        Session::flash('success', "Employee {$empName} ({$employee['employee_no']}) has been reactivated. Their login account was restored.");
        $this->redirect("/hr/employees/{$id}");
    }

    /**
     * Whether sensitive employee operations currently need a password re-confirmation.
     * Used only to decide whether to render the field; the server re-checks on submit.
     */
    private function stepUpRequired(): bool
    {
        return !(new StepUpAuthService($this->userModel))->hasRecentVerification();
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
     * Record a manual HR employment milestone and optionally synchronize current employee assignment.
     * Route: POST /hr/employees/{id}/history
     */
    public function storeHistory(int $id): void
    {
        $employee = $this->employeeModel->findById($id);
        if (!$employee) {
            Session::flash('error', 'The requested employee record was not found.');
            $this->navigate('/hr/employees');
            return;
        }

        $validation = EmploymentHistory::validateEventInput($_POST);
        $data = $validation['data'];
        $errors = $validation['errors'];

        $newDeptId = $data['new_department_id'];
        $newPosId  = $data['new_position_id'];

        if ($newDeptId !== null && !$this->findDepartmentById($newDeptId)) {
            $errors['new_department_id'] = 'Selected department does not exist.';
        }
        if ($newPosId !== null) {
            $pos = $this->findPositionById($newPosId);
            if (!$pos) {
                $errors['new_position_id'] = 'Selected job position does not exist.';
            } elseif ($newDeptId !== null && (int)$pos['department_id'] !== $newDeptId) {
                $errors['new_position_id'] = 'Selected position does not belong to the chosen department.';
            }
        }

        if (!empty($errors)) {
            Session::flash('history_form_errors', $errors);
            Session::flash('history_form_old', $_POST);
            Session::flash('error', 'Please review the employment milestone details.');
            $this->navigate("/hr/employees/{$id}#employment-history");
            return;
        }

        $prevDeptId = $data['previous_department_id'] ?? (!empty($employee['department_id']) ? (int)$employee['department_id'] : null);
        $prevPosId  = $data['previous_position_id'] ?? (!empty($employee['position_id']) ? (int)$employee['position_id'] : null);
        $syncCurrent = !empty($_POST['sync_current_assignment']) && $_POST['sync_current_assignment'] === '1';

        $ownTx = !$this->pdo->inTransaction();
        if ($ownTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $historyId = $this->historyModel->recordEvent([
                'employee_id'            => $id,
                'event_type'             => $data['event_type'],
                'effective_date'         => $data['effective_date'],
                'previous_department_id' => $prevDeptId,
                'new_department_id'      => $newDeptId ?? $prevDeptId,
                'previous_position_id'   => $prevPosId,
                'new_position_id'        => $newPosId ?? $prevPosId,
                'employment_type'        => $data['employment_type'] ?? ($employee['employment_type'] ?? null),
                'description'            => $data['description'],
                'recorded_by'            => currentUserId(),
            ]);

            if ($syncCurrent) {
                if ($newDeptId !== null && $newDeptId !== (int)($employee['department_id'] ?? 0)) {
                    $this->employeeModel->applyCorrectionField($id, 'department_id', $newDeptId);
                }
                if ($newPosId !== null && $newPosId !== (int)($employee['position_id'] ?? 0)) {
                    $this->employeeModel->applyCorrectionField($id, 'position_id', $newPosId);
                }
                if (
                    !empty($data['employment_type'])
                    && $data['employment_type'] !== ($employee['employment_type'] ?? '')
                ) {
                    $this->employeeModel->applyCorrectionField($id, 'employment_type', $data['employment_type']);
                }
            }

            if ($ownTx && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[EmployeeController::storeHistory] Failed: ' . $e->getMessage());
            Session::flash('error', 'Unable to record employment milestone right now.');
            $this->navigate("/hr/employees/{$id}#employment-history");
            return;
        }

        try {
            AuditService::getInstance($this->pdo)->record([
                'actor_user_id' => (int)currentUserId(),
                'action'        => 'EMPLOYEE_HISTORY_RECORDED',
                'category'      => AuditService::CATEGORY_EMPLOYEE,
                'description'   => "Recorded {$data['event_type']} milestone (#{$historyId}) for {$employee['employee_no']}.",
                'entity_type'   => 'EMPLOYMENT_HISTORY',
                'entity_id'     => (string)$historyId,
                'old_values'    => [
                    'department_id' => $employee['department_id'] ?? null,
                    'position_id'   => $employee['position_id'] ?? null,
                ],
                'new_values'    => $data + ['synced_current_record' => $syncCurrent],
            ]);
        } catch (\Throwable $e) {
            error_log('[EmployeeController::storeHistory] Audit log failed: ' . $e->getMessage());
        }

        Session::flash('success', "{$data['event_type']} milestone recorded in employment history.");
        $this->navigate("/hr/employees/{$id}#employment-history");
    }

    /**
     * Display HR review queue for employee profile correction requests.
     * Route: GET /hr/correction-requests
     */
    public function correctionRequests(): void
    {
        $rawStatus = trim((string)($_GET['status'] ?? 'All'));
        $filterStatus = in_array($rawStatus, ProfileCorrectionRequest::VALID_STATUSES, true)
            ? $rawStatus
            : null;

        $requests = [];
        $pendingCount = 0;
        try {
            $requests = $this->correctionModel->getAllForReview($filterStatus);
            $pendingCount = $this->correctionModel->countPending();
        } catch (\Throwable $e) {
            error_log('[EmployeeController::correctionRequests] Query failed: ' . $e->getMessage());
        }

        $this->view('employees.correction_requests', [
            'pageTitle'      => 'Profile Correction Requests',
            'requests'       => $requests,
            'pendingCount'   => $pendingCount,
            'selectedStatus' => $filterStatus ?? 'All',
        ], 'hr');
    }

    /**
     * Approve a pending profile correction request inside an atomic DB transaction.
     * Route: POST /hr/correction-requests/{id}/approve
     */
    public function approveCorrection(int $id): void
    {
        $reason = trim((string)($_POST['decision_reason'] ?? ''));
        $result = $this->correctionModel->review(
            $id,
            ProfileCorrectionRequest::STATUS_APPROVED,
            (int)currentUserId(),
            $reason !== '' ? $reason : null
        );

        if (!$result['ok']) {
            Session::flash('error', (string)($result['error'] ?? 'Unable to approve correction request.'));
        } else {
            Session::flash('success', "Profile correction request #{$id} has been approved and applied.");
        }

        $this->navigate('/hr/correction-requests');
    }

    /**
     * Reject a pending profile correction request with a mandatory reason.
     * Route: POST /hr/correction-requests/{id}/reject
     */
    public function rejectCorrection(int $id): void
    {
        $reason = trim((string)($_POST['decision_reason'] ?? ''));
        $result = $this->correctionModel->review(
            $id,
            ProfileCorrectionRequest::STATUS_REJECTED,
            (int)currentUserId(),
            $reason
        );

        if (!$result['ok']) {
            Session::flash('error', (string)($result['error'] ?? 'Unable to reject correction request.'));
        } else {
            Session::flash('success', "Profile correction request #{$id} has been rejected and the employee was notified.");
        }

        $this->navigate('/hr/correction-requests');
    }

    /**
     * Sanitize input payload.
     */
    private function sanitizeInput(array $raw): array
    {
        return [
            'employee_no'       => trim((string)($raw['employee_no'] ?? '')),
            'first_name'        => trim((string)($raw['first_name'] ?? '')),
            'middle_name'       => trim((string)($raw['middle_name'] ?? '')),
            'last_name'         => trim((string)($raw['last_name'] ?? '')),
            'email'             => trim((string)($raw['email'] ?? '')),
            'personal_email'    => trim((string)($raw['personal_email'] ?? '')),
            'phone'             => trim((string)($raw['phone'] ?? '')),
            'address'           => trim((string)($raw['address'] ?? '')),
            'birth_date'        => trim((string)($raw['birth_date'] ?? '')),
            'gender'            => trim((string)($raw['gender'] ?? '')),
            'civil_status'      => trim((string)($raw['civil_status'] ?? '')),
            'department_id'     => (int)($raw['department_id'] ?? 0),
            'position_id'       => (int)($raw['position_id'] ?? 0),
            'date_hired'        => trim((string)($raw['date_hired'] ?? '')),
            'employment_type'   => trim((string)($raw['employment_type'] ?? 'Regular')) ?: 'Regular',
            'employment_status' => trim((string)($raw['employment_status'] ?? 'Active')),
        ];
    }

    /**
     * Validate employee form data.
     *
     * @param array $data
     * @param int|null $excludeId ID to exclude on update
     * @return array Associative array of field error messages
     */
    private function validateEmployeeData(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        $empNo         = trim((string)($data['employee_no'] ?? ''));
        $firstName     = trim((string)($data['first_name'] ?? ''));
        $middleName    = trim((string)($data['middle_name'] ?? ''));
        $lastName      = trim((string)($data['last_name'] ?? ''));
        $email         = trim((string)($data['email'] ?? ''));
        $personalEmail = trim((string)($data['personal_email'] ?? ''));
        $phone         = trim((string)($data['phone'] ?? ''));
        $birthDate     = trim((string)($data['birth_date'] ?? ''));
        $gender        = trim((string)($data['gender'] ?? ''));
        $civilStatus   = trim((string)($data['civil_status'] ?? ''));
        $deptId        = (int)($data['department_id'] ?? 0);
        $posId         = (int)($data['position_id'] ?? 0);
        $dateHired     = trim((string)($data['date_hired'] ?? ''));
        $empType       = trim((string)($data['employment_type'] ?? 'Regular'));
        $status        = trim((string)($data['employment_status'] ?? 'Active'));

        // 1. Employee Number
        if ($empNo === '') {
            $errors['employee_no'] = 'Employee number is required.';
        } elseif (mb_strlen($empNo) > 30) {
            $errors['employee_no'] = 'Employee number cannot exceed 30 characters.';
        } elseif (!$this->employeeModel->isEmployeeNoUnique($empNo, $excludeId)) {
            $errors['employee_no'] = "Employee number '{$empNo}' is already assigned to another employee.";
        }

        // 2. Names
        if ($firstName === '') {
            $errors['first_name'] = 'First name is required.';
        } elseif (mb_strlen($firstName) > 50) {
            $errors['first_name'] = 'First name cannot exceed 50 characters.';
        }

        if ($middleName !== '' && mb_strlen($middleName) > 50) {
            $errors['middle_name'] = 'Middle name cannot exceed 50 characters.';
        }

        if ($lastName === '') {
            $errors['last_name'] = 'Last name is required.';
        } elseif (mb_strlen($lastName) > 50) {
            $errors['last_name'] = 'Last name cannot exceed 50 characters.';
        }

        // 3. Work & Personal Emails
        if ($email === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 100) {
            $errors['email'] = 'Email cannot exceed 100 characters.';
        } elseif (!$this->employeeModel->isEmailUnique($email, $excludeId)) {
            $errors['email'] = "Email address '{$email}' is already registered to another employee.";
        }

        if ($personalEmail !== '') {
            if (!filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['personal_email'] = 'Please enter a valid personal email address.';
            } elseif (mb_strlen($personalEmail) > 100) {
                $errors['personal_email'] = 'Personal email cannot exceed 100 characters.';
            }
        }

        // 4. Phone & Address
        if ($phone !== '' && mb_strlen($phone) > 25) {
            $errors['phone'] = 'Phone number cannot exceed 25 characters.';
        }

        // 5. Birth Date
        if ($birthDate === '') {
            $errors['birth_date'] = 'Birth date is required.';
        } else {
            $d = \DateTime::createFromFormat('Y-m-d', $birthDate);
            if (!$d || $d->format('Y-m-d') !== $birthDate) {
                $errors['birth_date'] = 'Invalid date format (must be YYYY-MM-DD).';
            } elseif ($d > new \DateTime()) {
                $errors['birth_date'] = 'Birth date cannot be in the future.';
            }
        }

        // 6. Gender & Civil Status
        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $errors['gender'] = 'Please select a valid gender option.';
        }

        $validCivilStatuses = (array)config('profile.civil_statuses', ['Single', 'Married', 'Widowed', 'Separated', 'Divorced']);
        if ($civilStatus !== '' && !in_array($civilStatus, $validCivilStatuses, true)) {
            $errors['civil_status'] = 'Please select a valid civil status option.';
        }

        // 7. Department & Position Validation
        $department = $deptId > 0 ? $this->findDepartmentById($deptId) : null;
        if (!$department) {
            $errors['department_id'] = 'Please select a valid department.';
        }

        $position = $posId > 0 ? $this->findPositionById($posId) : null;
        if (!$position) {
            $errors['position_id'] = 'Please select a valid job position.';
        } elseif ($department && (int)$position['department_id'] !== $deptId) {
            $errors['position_id'] = 'The selected position does not belong to the selected department.';
        }

        // 8. Date Hired & Employment Type
        if ($dateHired === '') {
            $errors['date_hired'] = 'Date hired is required.';
        } else {
            $dh = \DateTime::createFromFormat('Y-m-d', $dateHired);
            if (!$dh || $dh->format('Y-m-d') !== $dateHired) {
                $errors['date_hired'] = 'Invalid date format (must be YYYY-MM-DD).';
            } elseif ($dh > (new \DateTime())->setTime(23, 59, 59)) {
                $errors['date_hired'] = 'Date hired cannot be in the future.';
            }
        }

        $validEmpTypes = (array)config('profile.employment_types', ['Regular', 'Probationary', 'Contractual', 'Project-Based', 'Part-Time']);
        if (!in_array($empType, $validEmpTypes, true)) {
            $errors['employment_type'] = 'Please select a valid employment type.';
        }

        // 9. Employment Status (Strictly Active or Inactive)
        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $errors['employment_status'] = 'Please select a valid employment status (Active or Inactive).';
        }

        return $errors;
    }

    /**
     * Automatically append immutable history records when HR updates organizational fields.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param array<string, mixed> $rawPost
     */
    private function recordAutomaticHistoryTransitions(int $employeeId, array $before, array $after, array $rawPost): void
    {
        $prevDept = !empty($before['department_id']) ? (int)$before['department_id'] : null;
        $newDept  = !empty($after['department_id']) ? (int)$after['department_id'] : null;
        $prevPos  = !empty($before['position_id']) ? (int)$before['position_id'] : null;
        $newPos   = !empty($after['position_id']) ? (int)$after['position_id'] : null;
        $prevType = (string)($before['employment_type'] ?? 'Regular');
        $newType  = (string)($after['employment_type'] ?? 'Regular');
        $prevStat = (string)($before['employment_status'] ?? 'Active');
        $newStat  = (string)($after['employment_status'] ?? 'Active');

        $note = trim((string)($rawPost['transition_note'] ?? ''));
        $isPromotion = !empty($rawPost['is_promotion']) && $rawPost['is_promotion'] === '1';
        $today = date('Y-m-d');
        $actorId = currentUserId();

        try {
            if ($prevPos !== $newPos) {
                $eventType = $isPromotion ? EmploymentHistory::EVENT_PROMOTION : EmploymentHistory::EVENT_POSITION_CHANGE;
                $desc = $note !== ''
                    ? $note
                    : ($isPromotion ? 'Promoted to updated position.' : 'Position updated by Human Resources.');
                $this->historyModel->recordEvent([
                    'employee_id'            => $employeeId,
                    'event_type'             => $eventType,
                    'effective_date'         => $today,
                    'previous_department_id' => $prevDept,
                    'new_department_id'      => $newDept,
                    'previous_position_id'   => $prevPos,
                    'new_position_id'        => $newPos,
                    'employment_type'        => $newType,
                    'description'            => $desc,
                    'recorded_by'            => $actorId,
                ]);
            } elseif ($prevDept !== $newDept) {
                $this->historyModel->recordEvent([
                    'employee_id'            => $employeeId,
                    'event_type'             => EmploymentHistory::EVENT_DEPT_TRANSFER,
                    'effective_date'         => $today,
                    'previous_department_id' => $prevDept,
                    'new_department_id'      => $newDept,
                    'previous_position_id'   => $prevPos,
                    'new_position_id'        => $newPos,
                    'employment_type'        => $newType,
                    'description'            => $note !== '' ? $note : 'Department assignment updated by Human Resources.',
                    'recorded_by'            => $actorId,
                ]);
            }

            if ($prevType !== $newType) {
                $this->historyModel->recordEvent([
                    'employee_id'            => $employeeId,
                    'event_type'             => EmploymentHistory::EVENT_TYPE_CHANGE,
                    'effective_date'         => $today,
                    'previous_department_id' => $newDept,
                    'new_department_id'      => $newDept,
                    'previous_position_id'   => $newPos,
                    'new_position_id'        => $newPos,
                    'employment_type'        => $newType,
                    'description'            => $note !== ''
                        ? $note
                        : "Employment type changed from {$prevType} to {$newType}.",
                    'recorded_by'            => $actorId,
                ]);
            }

            if ($prevStat !== $newStat) {
                $this->historyModel->recordEvent([
                    'employee_id'            => $employeeId,
                    'event_type'             => EmploymentHistory::EVENT_STATUS_CHANGE,
                    'effective_date'         => $today,
                    'previous_department_id' => $newDept,
                    'new_department_id'      => $newDept,
                    'previous_position_id'   => $newPos,
                    'new_position_id'        => $newPos,
                    'employment_type'        => $newType,
                    'description'            => "Employment status changed from {$prevStat} to {$newStat}.",
                    'recorded_by'            => $actorId,
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[EmployeeController::recordAutomaticHistoryTransitions] Failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchDepartmentsList(): array
    {
        try {
            return $this->pdo->query("SELECT id, department_name FROM departments ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPositionsList(): array
    {
        try {
            return $this->pdo->query("SELECT id, position_name, department_id FROM positions ORDER BY position_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function findDepartmentById(int $deptId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, department_name FROM departments WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $deptId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function findPositionById(int $posId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, position_name, department_id FROM positions WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $posId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function navigate(string $url, int $statusCode = 302): void
    {
        if ($this->terminateOnRedirect) {
            $this->redirect($url, $statusCode);
        }
    }
}
