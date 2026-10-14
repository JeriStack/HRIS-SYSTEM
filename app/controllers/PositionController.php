<?php
/**
 * Position Controller
 * Administrative management of job classifications
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\Position;
use App\Models\Department;
use App\Models\User;

class PositionController extends Controller
{
    private Position $positionModel;
    private Department $departmentModel;
    private User $userModel;

    public function __construct()
    {
        $this->positionModel = new Position();
        $this->departmentModel = new Department();
        $this->userModel = new User();
    }

    /**
     * Display position classifications directory.
     * Route: GET /hr/positions
     */
    public function index(): void
    {
        $deptFilter = !empty($_GET['dept_id']) ? (int)$_GET['dept_id'] : null;
        $positions = $this->positionModel->getAll($deptFilter);
        $departments = $this->departmentModel->getAll();

        $this->view('positions.index', [
            'pageTitle'       => 'Job Positions Management',
            'positions'       => $positions,
            'departments'     => $departments,
            'selectedDeptId'  => $deptFilter,
        ], 'hr');
    }

    /**
     * JSON endpoint to fetch positions by department for dynamic form selection.
     * Route: GET /hr/api/positions-by-department/{id}
     */
    public function getByDepartment(int $deptId): void
    {
        $positions = $this->positionModel->getByDepartmentId($deptId);

        $this->json([
            'status'    => 'success',
            'positions' => $positions,
        ]);
    }

    /**
     * Display position creation form.
     * Route: GET /hr/positions/create
     */
    public function create(): void
    {
        $departments = $this->departmentModel->getAll();

        $this->view('positions.create', [
            'pageTitle'   => 'Add New Job Position',
            'departments' => $departments,
            'errors'      => Session::getFlash('errors', []),
            'old'         => Session::getFlash('old', []),
        ], 'hr');
    }

    /**
     * Process position creation request.
     * Route: POST /hr/positions
     */
    public function store(): void
    {
        $name = trim((string)($_POST['position_name'] ?? ''));
        $deptId = (int)($_POST['department_id'] ?? 0);
        $description = trim((string)($_POST['description'] ?? ''));

        $errors = [];

        // Department validation
        $dept = $this->departmentModel->findById($deptId);
        if (!$dept) {
            $errors['department_id'] = 'Please select a valid, existing department.';
        }

        // Position name validation
        if ($name === '') {
            $errors['position_name'] = 'Position title is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors['position_name'] = 'Position title cannot exceed 100 characters.';
        } elseif ($dept && !$this->positionModel->isUniqueInDepartment($name, $deptId)) {
            $errors['position_name'] = "The position '{$name}' already exists in the {$dept['department_name']} department.";
        }

        if (mb_strlen($description) > 1000) {
            $errors['description'] = 'Description cannot exceed 1000 characters.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect('/hr/positions/create');
            return;
        }

        // Insert into database
        $positionId = $this->positionModel->create([
            'position_name' => $name,
            'department_id' => $deptId,
            'description'   => $description,
        ]);

        // Audit Logging
        $this->userModel->logActivity(
            currentUserId(),
            'POSITION_CREATED',
            "Created position: {$name} in department ID {$deptId} (Position ID: {$positionId})",
            'POSITION',
            (string)$positionId
        );

        Session::flash('success', "Position '{$name}' was created successfully.");
        $this->redirect('/hr/positions');
    }

    /**
     * Display position edit form.
     * Route: GET /hr/positions/{id}/edit
     */
    public function edit(int $id): void
    {
        $position = $this->positionModel->findById($id);

        if (!$position) {
            Session::flash('error', 'The requested position was not found.');
            $this->redirect('/hr/positions');
            return;
        }

        $departments = $this->departmentModel->getAll();

        $this->view('positions.edit', [
            'pageTitle'   => 'Edit Job Position',
            'position'    => $position,
            'departments' => $departments,
            'errors'      => Session::getFlash('errors', []),
            'old'         => Session::getFlash('old', []),
        ], 'hr');
    }

    /**
     * Process position update request.
     * Route: POST /hr/positions/{id}/edit
     */
    public function update(int $id): void
    {
        $position = $this->positionModel->findById($id);

        if (!$position) {
            Session::flash('error', 'The requested position was not found.');
            $this->redirect('/hr/positions');
            return;
        }

        $name = trim((string)($_POST['position_name'] ?? ''));
        $deptId = (int)($_POST['department_id'] ?? 0);
        $description = trim((string)($_POST['description'] ?? ''));

        $errors = [];

        // Department validation
        $dept = $this->departmentModel->findById($deptId);
        if (!$dept) {
            $errors['department_id'] = 'Please select a valid, existing department.';
        }

        // Position name validation
        if ($name === '') {
            $errors['position_name'] = 'Position title is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors['position_name'] = 'Position title cannot exceed 100 characters.';
        } elseif ($dept && !$this->positionModel->isUniqueInDepartment($name, $deptId, $id)) {
            $errors['position_name'] = "The position '{$name}' already exists in the {$dept['department_name']} department.";
        }

        if (mb_strlen($description) > 1000) {
            $errors['description'] = 'Description cannot exceed 1000 characters.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            $this->redirect("/hr/positions/{$id}/edit");
            return;
        }

        // Update database record
        $this->positionModel->update($id, [
            'position_name' => $name,
            'department_id' => $deptId,
            'description'   => $description,
        ]);

        // Audit Logging
        $this->userModel->logActivity(
            currentUserId(),
            'POSITION_UPDATED',
            "Updated position: {$name} in department ID {$deptId} (Position ID: {$id})",
            'POSITION',
            (string)$id
        );

        Session::flash('success', "Position '{$name}' was updated successfully.");
        $this->redirect('/hr/positions');
    }
}
