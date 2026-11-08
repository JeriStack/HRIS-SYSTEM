<?php
/**
 * Department Controller
 * Administrative management of organizational units
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\Department;
use App\Models\User;

class DepartmentController extends Controller
{
    private Department $departmentModel;
    private User $userModel;

    public function __construct()
    {
        $this->departmentModel = new Department();
        $this->userModel = new User();
    }

    /**
     * Display department directory listing with employee and position metrics.
     * Route: GET /hr/departments
     */
    public function index(): void
    {
        $departments = $this->departmentModel->getAll();

        $this->view('departments.index', [
            'pageTitle'   => 'Departments Management',
            'departments' => $departments,
        ], 'hr');
    }

    /**
     * Display department creation form.
     * Route: GET /hr/departments/create
     */
    public function create(): void
    {
        $this->view('departments.create', [
            'pageTitle' => 'Add New Department',
            'errors'    => Session::getFlash('errors', []),
            'old'       => Session::getFlash('old', []),
        ], 'hr');
    }

    /**
     * Process department creation request.
     * Route: POST /hr/departments
     */
    public function store(): void
    {
        $name = trim((string)($_POST['department_name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        $errors = [];

        // Validation
        if ($name === '') {
            $errors['department_name'] = 'Department name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors['department_name'] = 'Department name cannot exceed 100 characters.';
        } elseif (!$this->departmentModel->isNameUnique($name)) {
            $errors['department_name'] = 'A department with this name already exists.';
        }

        if (mb_strlen($description) > 1000) {
            $errors['description'] = 'Description cannot exceed 1000 characters.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect('/hr/departments/create');
            return;
        }

        // Insert into database
        $deptId = $this->departmentModel->create([
            'department_name' => $name,
            'description'     => $description,
        ]);

        // Audit Logging
        $this->userModel->logActivity(
            currentUserId(),
            'DEPARTMENT_CREATED',
            "Created department: {$name} (ID: {$deptId})",
            'DEPARTMENT',
            (string)$deptId,
            null,
            [
                'department_name' => $name,
                'description'     => $description,
            ],
            'SUCCESS'
        );

        Session::flash('success', "Department '{$name}' was created successfully.");
        $this->redirect('/hr/departments');
    }

    /**
     * Display department edit form.
     * Route: GET /hr/departments/{id}/edit
     */
    public function edit(int $id): void
    {
        $department = $this->departmentModel->findById($id);

        if (!$department) {
            Session::flash('error', 'The requested department was not found.');
            $this->redirect('/hr/departments');
            return;
        }

        $this->view('departments.edit', [
            'pageTitle'  => 'Edit Department',
            'department' => $department,
            'errors'     => Session::getFlash('errors', []),
            'old'        => Session::getFlash('old', []),
        ], 'hr');
    }

    /**
     * Process department update request.
     * Route: POST /hr/departments/{id}/edit
     */
    public function update(int $id): void
    {
        $department = $this->departmentModel->findById($id);

        if (!$department) {
            Session::flash('error', 'The requested department was not found.');
            $this->redirect('/hr/departments');
            return;
        }

        $name = trim((string)($_POST['department_name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        $errors = [];

        // Validation
        if ($name === '') {
            $errors['department_name'] = 'Department name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors['department_name'] = 'Department name cannot exceed 100 characters.';
        } elseif (!$this->departmentModel->isNameUnique($name, $id)) {
            $errors['department_name'] = 'A department with this name already exists.';
        }

        if (mb_strlen($description) > 1000) {
            $errors['description'] = 'Description cannot exceed 1000 characters.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect("/hr/departments/{$id}/edit");
            return;
        }

        // Update database record
        $this->departmentModel->update($id, [
            'department_name' => $name,
            'description'     => $description,
        ]);

        // Audit Logging
        $this->userModel->logActivity(
            currentUserId(),
            'DEPARTMENT_UPDATED',
            "Updated department: {$name} (ID: {$id})",
            'DEPARTMENT',
            (string)$id,
            [
                'department_name' => $department['department_name'] ?? null,
                'description'     => $department['description'] ?? null,
            ],
            [
                'department_name' => $name,
                'description'     => $description,
            ],
            'SUCCESS'
        );

        Session::flash('success', "Department '{$name}' was updated successfully.");
        $this->redirect('/hr/departments');
    }
}
