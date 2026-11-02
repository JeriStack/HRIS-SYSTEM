<?php
/**
 * Employee Controller
 * Core workforce directory, profiles, registration, updates, and soft-deactivation
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Services\AccountRevocationService;
use App\Services\StepUpAuthService;

class EmployeeController extends Controller
{
    private Employee $employeeModel;
    private Department $departmentModel;
    private Position $positionModel;
    private User $userModel;

    public function __construct()
    {
        $this->employeeModel = new Employee();
        $this->departmentModel = new Department();
        $this->positionModel = new Position();
        $this->userModel = new User();
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

        $this->view('employees.index', [
            'pageTitle'        => 'Employees Directory',
            'employees'        => $employees,
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
            $this->redirect('/hr/employees');
            return;
        }

        $this->view('employees.show', [
            'pageTitle'      => "Profile: {$employee['first_name']} {$employee['last_name']} ({$employee['employee_no']})",
            'employee'       => $employee,
            'stepUpRequired' => $this->stepUpRequired(),
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
            'pageTitle'   => 'Add New Employee Record',
            'departments' => $departments,
            'positions'   => $positions,
            'errors'      => Session::getFlash('errors', []),
            'old'         => Session::getFlash('old', []),
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
            $this->redirect('/hr/employees/create');
            return;
        }

        // Insert new employee record
        $employeeId = $this->employeeModel->create($data);

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
            (string)$employeeId
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
            'pageTitle'      => "Edit Employee Record: {$employee['employee_no']}",
            'employee'       => $employee,
            'departments'    => $departments,
            'positions'      => $positions,
            'errors'         => Session::getFlash('errors', []),
            'old'            => Session::getFlash('old', []),
            'stepUpRequired' => $this->stepUpRequired(),
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
        if ($data['employment_status'] !== 'Active') {
            $this->revokeLinkedAccount($id);
        }

        // Find linked user account for this employee and dispatch real-time notification
        $linkedUser = $this->userModel->findByEmployeeId($id);
        $notificationModel = new \App\Models\Notification();
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

        // Audit Logging
        $empName = trim("{$data['first_name']} {$data['last_name']}");
        $this->userModel->logActivity(
            currentUserId(),
            'EMPLOYEE_UPDATED',
            "Updated employee record: {$empName} ({$data['employee_no']})",
            'EMPLOYEE',
            (string)$id
        );

        Session::flash('success', "Employee record for {$empName} ({$data['employee_no']}) was updated successfully.");
        $this->redirect("/hr/employees/{$id}");
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
            (string)$id
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
            'phone'             => trim((string)($raw['phone'] ?? '')),
            'address'           => trim((string)($raw['address'] ?? '')),
            'birth_date'        => trim((string)($raw['birth_date'] ?? '')),
            'gender'            => trim((string)($raw['gender'] ?? '')),
            'department_id'     => (int)($raw['department_id'] ?? 0),
            'position_id'       => (int)($raw['position_id'] ?? 0),
            'date_hired'        => trim((string)($raw['date_hired'] ?? '')),
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

        $empNo      = trim((string)($data['employee_no'] ?? ''));
        $firstName  = trim((string)($data['first_name'] ?? ''));
        $middleName = trim((string)($data['middle_name'] ?? ''));
        $lastName   = trim((string)($data['last_name'] ?? ''));
        $email      = trim((string)($data['email'] ?? ''));
        $phone      = trim((string)($data['phone'] ?? ''));
        $address    = trim((string)($data['address'] ?? ''));
        $birthDate  = trim((string)($data['birth_date'] ?? ''));
        $gender     = trim((string)($data['gender'] ?? ''));
        $deptId     = (int)($data['department_id'] ?? 0);
        $posId      = (int)($data['position_id'] ?? 0);
        $dateHired  = trim((string)($data['date_hired'] ?? ''));
        $status     = trim((string)($data['employment_status'] ?? 'Active'));

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

        // 3. Email
        if ($email === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 100) {
            $errors['email'] = 'Email cannot exceed 100 characters.';
        } elseif (!$this->employeeModel->isEmailUnique($email, $excludeId)) {
            $errors['email'] = "Email address '{$email}' is already registered to another employee.";
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

        // 6. Gender
        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $errors['gender'] = 'Please select a valid gender option.';
        }

        // 7. Department & Position Validation
        $department = $deptId > 0 ? $this->departmentModel->findById($deptId) : null;
        if (!$department) {
            $errors['department_id'] = 'Please select a valid department.';
        }

        $position = $posId > 0 ? $this->positionModel->findById($posId) : null;
        if (!$position) {
            $errors['position_id'] = 'Please select a valid job position.';
        } elseif ($department && (int)$position['department_id'] !== $deptId) {
            $errors['position_id'] = 'The selected position does not belong to the selected department.';
        }

        // 8. Date Hired
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

        // 9. Employment Status (Strictly Active or Inactive)
        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $errors['employment_status'] = 'Please select a valid employment status (Active or Inactive).';
        }

        return $errors;
    }
}
