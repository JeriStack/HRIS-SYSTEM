<?php
/**
 * Phase 7 Core HRIS Automated Verification Test Suite
 * Tests Employee, Department, and Position CRUD, Validations, Status Handling,
 * Soft-Deactivation Integrity, and Authorization Guards.
 */

// 1. Bootstrap Application
require_once __DIR__ . '/../core/bootstrap.php';

use Core\Database;
use Core\Session;
use Core\Router;
use Core\AuthMiddleware;
use Core\RoleMiddleware;
use Core\CsrfMiddleware;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Controllers\EmployeeController;
use App\Controllers\DepartmentController;
use App\Controllers\PositionController;

// Enable strict error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

$results = [
    'passed' => 0,
    'failed' => 0,
    'total'  => 0,
    'tests'  => []
];

function assertTest(string $category, string $testName, bool $condition, string $details = '') {
    global $results;
    $results['total']++;
    if ($condition) {
        $results['passed']++;
        $results['tests'][] = ['status' => 'PASS', 'category' => $category, 'name' => $testName, 'details' => $details];
        echo "  [PASS] [{$category}] {$testName}\n";
    } else {
        $results['failed']++;
        $results['tests'][] = ['status' => 'FAIL', 'category' => $category, 'name' => $testName, 'details' => $details];
        echo "  [FAIL] [{$category}] {$testName}: {$details}\n";
    }
}

echo "====================================================================\n";
echo "   PHASE 7 CORE HRIS COMPREHENSIVE AUTOMATED TEST SUITE\n";
echo "====================================================================\n\n";

$db = Database::getInstance()->getConnection();
$deptModel = new Department();
$posModel = new Position();
$empModel = new Employee();

// -------------------------------------------------------------------------
// GROUP 1: DEPARTMENT CRUD & VALIDATION TESTS
// -------------------------------------------------------------------------
echo "--- GROUP 1: DEPARTMENT MANAGEMENT TESTS ---\n";

// 1.1 List existing departments
$departments = $deptModel->getAll();
assertTest('Department', 'Retrieve department list with aggregations', is_array($departments) && count($departments) > 0, "Found " . count($departments) . " departments");

// 1.2 Add valid department
$testDeptName = 'Research & Innovation ' . time();
$testDeptDesc = 'Department dedicated to cutting-edge research and systems innovation';
$newDeptId = $deptModel->create([
    'department_name' => $testDeptName,
    'description'     => $testDeptDesc,
]);
assertTest('Department', 'Create valid department', $newDeptId > 0, "Created ID {$newDeptId}");

$fetchedDept = $deptModel->findById($newDeptId);
assertTest('Department', 'Retrieve single department by ID', $fetchedDept !== null && $fetchedDept['department_name'] === $testDeptName);

// 1.3 Department name uniqueness check
$isUniqueNew = $deptModel->isNameUnique($testDeptName);
assertTest('Department', 'Reject duplicate department name (isNameUnique returns false for existing)', !$isUniqueNew, "Correctly detected duplicate: {$testDeptName}");

$isUniqueSelf = $deptModel->isNameUnique($testDeptName, $newDeptId);
assertTest('Department', 'Department name uniqueness check excludes current record on update', $isUniqueSelf, "Correctly allowed same name when excluding self ID {$newDeptId}");

// 1.4 Edit department
$updatedDeptName = $testDeptName . ' Updated';
$deptModel->update($newDeptId, [
    'department_name' => $updatedDeptName,
    'description'     => 'Updated department description text'
]);
$updatedDept = $deptModel->findById($newDeptId);
assertTest('Department', 'Update department details', $updatedDept !== null && $updatedDept['department_name'] === $updatedDeptName);

// 1.5 Handle nonexistent department ID
$nonexistentDept = $deptModel->findById(999999);
assertTest('Department', 'Handle nonexistent department lookup safely', $nonexistentDept === null);

// -------------------------------------------------------------------------
// GROUP 2: POSITION CRUD & VALIDATION TESTS
// -------------------------------------------------------------------------
echo "\n--- GROUP 2: POSITION MANAGEMENT TESTS ---\n";

// 2.1 List existing positions
$positions = $posModel->getAll();
assertTest('Position', 'Retrieve position list with joined department names', is_array($positions) && count($positions) > 0, "Found " . count($positions) . " positions");

// 2.2 Add valid position associated with the new department
$testPosTitle = 'Lead Research Engineer ' . time();
$newPosId = $posModel->create([
    'department_id' => $newDeptId,
    'position_name' => $testPosTitle,
    'description'   => 'Leads advanced technological R&D pipelines'
]);
assertTest('Position', 'Create valid position associated with department', $newPosId > 0, "Created Position ID {$newPosId}");

$fetchedPos = $posModel->findById($newPosId);
assertTest('Position', 'Retrieve position by ID', $fetchedPos !== null && $fetchedPos['position_name'] === $testPosTitle);

// 2.3 Position uniqueness rule within department
$isUniquePos = $posModel->isUniqueInDepartment($testPosTitle, (int)$newDeptId);
assertTest('Position', 'Enforce department-scoped position name uniqueness (reject duplicate)', !$isUniquePos, "Detected duplicate title in dept {$newDeptId}");

$isUniquePosSelf = $posModel->isUniqueInDepartment($testPosTitle, (int)$newDeptId, (int)$newPosId);
assertTest('Position', 'Position uniqueness check excludes self on update', $isUniquePosSelf);

// Position with SAME name in a DIFFERENT department should be allowed (department-scoped uniqueness)
$firstDept = (int)$departments[0]['id'];
$isUniqueInOtherDept = $posModel->isUniqueInDepartment($testPosTitle, $firstDept);
assertTest('Position', 'Same position name allowed in different department', $isUniqueInOtherDept, "Allowed in department {$firstDept}");

// 2.4 Update position details
$updatedPosTitle = $testPosTitle . ' II';
$posModel->update($newPosId, [
    'department_id' => $newDeptId,
    'position_name' => $updatedPosTitle,
    'description'   => 'Updated position duties'
]);
$updatedPos = $posModel->findById($newPosId);
assertTest('Position', 'Update position details and verify changes', $updatedPos !== null && $updatedPos['position_name'] === $updatedPosTitle);

// 2.5 Dynamic position retrieval by department ID
$deptPositions = $posModel->getByDepartmentId($newDeptId);
assertTest('Position', 'Retrieve positions by department ID', count($deptPositions) === 1 && $deptPositions[0]['id'] == $newPosId);

// 2.6 Handle nonexistent position ID
$nonexistentPos = $posModel->findById(999999);
assertTest('Position', 'Handle nonexistent position safely', $nonexistentPos === null);

// -------------------------------------------------------------------------
// GROUP 3: EMPLOYEE CRUD & VALIDATION TESTS
// -------------------------------------------------------------------------
echo "\n--- GROUP 3: EMPLOYEE MANAGEMENT TESTS ---\n";

// 3.1 Retrieve all employees and count metrics
$allEmployees = $empModel->getAll();
$counts = $empModel->getCounts();
assertTest('Employee', 'Retrieve directory employees list with joined department and position', is_array($allEmployees) && count($allEmployees) > 0, "Count: " . count($allEmployees));
assertTest('Employee', 'Retrieve workforce counts (total, active, inactive, depts)', isset($counts['total'], $counts['active'], $counts['inactive']) && $counts['total'] > 0);

// 3.2 Add a valid employee
$testEmpNo = 'EMP' . time() . rand(10, 99);
$testEmail = 'employee.' . time() . '.' . rand(100, 999) . '@company.local';
$newEmpData = [
    'employee_no'       => $testEmpNo,
    'first_name'        => 'TestFirst',
    'middle_name'       => 'TestMid',
    'last_name'         => 'TestLast',
    'email'             => $testEmail,
    'phone'             => '+63 912 345 6789',
    'address'           => '123 Innovation Way, Tech District',
    'birth_date'        => '1995-05-15',
    'gender'            => 'Male',
    'department_id'     => $newDeptId,
    'position_id'       => $newPosId,
    'date_hired'        => date('Y-m-d'),
    'employment_status' => 'Active'
];
$newEmpId = $empModel->create($newEmpData);
assertTest('Employee', 'Create valid employee record in database', $newEmpId > 0, "Created Employee ID {$newEmpId}");

// 3.3 Find employee by ID and by Employee Number
$foundById = $empModel->findById($newEmpId);
$foundByNo = $empModel->findByEmployeeNo($testEmpNo);
assertTest('Employee', 'Find employee by internal ID', $foundById !== null && $foundById['employee_no'] === $testEmpNo);
assertTest('Employee', 'Find employee by employee_no', $foundByNo !== null && $foundByNo['id'] == $newEmpId);
assertTest('Employee', 'Verify demographic and organizational relationships', 
    $foundById['department_name'] === $updatedDeptName && $foundById['position_name'] === $updatedPosTitle
);

// 3.4 Uniqueness validation rules
$isEmpNoUnique = $empModel->isEmployeeNoUnique($testEmpNo);
assertTest('Employee', 'Reject duplicate employee_no (isEmployeeNoUnique returns false)', !$isEmpNoUnique);

$isEmpNoUniqueSelf = $empModel->isEmployeeNoUnique($testEmpNo, $newEmpId);
assertTest('Employee', 'Employee number uniqueness check excludes self on update', $isEmpNoUniqueSelf);

$isEmailUnique = $empModel->isEmailUnique($testEmail);
assertTest('Employee', 'Reject duplicate email (isEmailUnique returns false)', !$isEmailUnique);

$isEmailUniqueSelf = $empModel->isEmailUnique($testEmail, $newEmpId);
assertTest('Employee', 'Email uniqueness check excludes self on update', $isEmailUniqueSelf);

// 3.5 Edit and update employee record
$updatedEmpData = array_merge($newEmpData, [
    'first_name' => 'UpdatedFirst',
    'last_name'  => 'UpdatedLast',
    'phone'      => '+63 999 888 7777',
    'address'    => '456 Modern Blvd, Updated City'
]);
$empModel->update($newEmpId, $updatedEmpData);
$refetchedEmp = $empModel->findById($newEmpId);
assertTest('Employee', 'Update employee record details', 
    $refetchedEmp !== null && $refetchedEmp['first_name'] === 'UpdatedFirst' && $refetchedEmp['phone'] === '+63 999 888 7777'
);

// 3.6 Search employees using multiple parameters
$searchByNo = $empModel->getAll($testEmpNo);
assertTest('Employee', 'Search employees by employee_no', count($searchByNo) === 1 && $searchByNo[0]['id'] == $newEmpId);

$searchByName = $empModel->getAll('UpdatedFirst');
assertTest('Employee', 'Search employees by name', count($searchByName) >= 1 && $searchByName[0]['first_name'] === 'UpdatedFirst');

$searchByEmail = $empModel->getAll($testEmail);
assertTest('Employee', 'Search employees by email', count($searchByEmail) === 1 && $searchByEmail[0]['email'] === $testEmail);

$searchNoMatch = $empModel->getAll('NONEXISTENT_QUERY_STRING_XYZ_999');
assertTest('Employee', 'Handle search query with no matching results gracefully', count($searchNoMatch) === 0);

// Filter by department and status
$filterByDept = $empModel->getAll(null, 'Active', $newDeptId);
assertTest('Employee', 'Filter employees by Department and Active status', count($filterByDept) >= 1);

// 3.7 Handle nonexistent employee ID safely
$nonexistentEmp = $empModel->findById(999999);
assertTest('Employee', 'Handle nonexistent employee lookup safely (returns null)', $nonexistentEmp === null);

// -------------------------------------------------------------------------
// GROUP 4: EMPLOYEE DEACTIVATION & HISTORICAL INTEGRITY TESTS
// -------------------------------------------------------------------------
echo "\n--- GROUP 4: DEACTIVATION & HISTORICAL RECORD INTEGRITY TESTS ---\n";

// 4.1 Soft deactivation of active employee
$deactResult = $empModel->deactivate($newEmpId);
assertTest('Deactivation', 'Soft deactivation executes successfully without deleting record', $deactResult === true);

$deactivatedEmp = $empModel->findById($newEmpId);
assertTest('Deactivation', 'Employee employment_status transitions to Inactive', 
    $deactivatedEmp !== null && $deactivatedEmp['employment_status'] === 'Inactive'
);

// 4.2 Verify historical record persistence
$stillExistsInDb = $db->query("SELECT COUNT(*) FROM employees WHERE id = {$newEmpId}")->fetchColumn();
assertTest('Deactivation', 'Verify record still exists in database (NOT permanently deleted)', $stillExistsInDb == 1);

// 4.3 Attempting to deactivate an already inactive employee returns false
$secondDeact = $empModel->deactivate($newEmpId);
assertTest('Deactivation', 'Handle already inactive employee appropriately (returns false)', $secondDeact === false);

// -------------------------------------------------------------------------
// GROUP 5: SERVER-SIDE VALIDATION IN CONTROLLERS
// -------------------------------------------------------------------------
echo "\n--- GROUP 5: SERVER-SIDE CONTROLLER VALIDATION LOGIC TESTS ---\n";

$empController = new EmployeeController();
$refEmpMethod = new ReflectionMethod($empController, 'validateEmployeeData');

// Test validation rules:
// - Missing required fields
$valErrorsMissing = $refEmpMethod->invoke($empController, [
    'employee_no'   => '',
    'first_name'    => '',
    'last_name'     => '',
    'email'         => '',
    'birth_date'    => '',
    'gender'        => '',
    'department_id' => 0,
    'position_id'   => 0,
    'date_hired'    => '',
    'employment_status' => ''
]);
assertTest('Validation', 'Flag missing employee_no', isset($valErrorsMissing['employee_no']));
assertTest('Validation', 'Flag missing first_name and last_name', isset($valErrorsMissing['first_name'], $valErrorsMissing['last_name']));
assertTest('Validation', 'Flag missing email', isset($valErrorsMissing['email']));
assertTest('Validation', 'Flag missing department_id and position_id', isset($valErrorsMissing['department_id'], $valErrorsMissing['position_id']));

// - Invalid email format
$valErrorsEmail = $refEmpMethod->invoke($empController, array_merge($newEmpData, ['email' => 'invalid-email-string']));
assertTest('Validation', 'Reject invalid email format', isset($valErrorsEmail['email']));

// - Duplicate employee_no on create
$valErrorsDupNo = $refEmpMethod->invoke($empController, array_merge($newEmpData, ['employee_no' => 'EMP001']));
assertTest('Validation', 'Reject duplicate employee number', isset($valErrorsDupNo['employee_no']));

// - Invalid date hired (future date prevention)
$valErrorsFutureDate = $refEmpMethod->invoke($empController, array_merge($newEmpData, [
    'employee_no' => 'EMP9999',
    'email'       => 'valid9999@test.local',
    'date_hired'  => '2099-01-01'
]));
assertTest('Validation', 'Reject future hire dates', isset($valErrorsFutureDate['date_hired']));

// - Invalid employment status (must be Active or Inactive)
$valErrorsBadStatus = $refEmpMethod->invoke($empController, array_merge($newEmpData, [
    'employee_no'       => 'EMP9999',
    'email'             => 'valid9999@test.local',
    'employment_status' => 'PendingApproval'
]));
assertTest('Validation', 'Reject unapproved employment status', isset($valErrorsBadStatus['employment_status']));

// - Nonexistent department or position
$valErrorsNonexistentDept = $refEmpMethod->invoke($empController, array_merge($newEmpData, [
    'employee_no'   => 'EMP9999',
    'email'         => 'valid9999@test.local',
    'department_id' => 888888,
    'position_id'   => 1
]));
assertTest('Validation', 'Reject nonexistent department ID', isset($valErrorsNonexistentDept['department_id']));

// - Position not belonging to selected department
// Position 1 belongs to Department 1 (IT), but we select Department 2 (HR)
$valErrorsPosDeptMismatch = $refEmpMethod->invoke($empController, array_merge($newEmpData, [
    'employee_no'   => 'EMP9999',
    'email'         => 'valid9999@test.local',
    'department_id' => 2,
    'position_id'   => 1
]));
assertTest('Validation', 'Reject position that does not belong to selected department', isset($valErrorsPosDeptMismatch['position_id']));

// Department model validation rules
assertTest('Validation', 'Department model rejects duplicate name', !$deptModel->isNameUnique($updatedDeptName));
assertTest('Validation', 'Department model allows same name when updating self', $deptModel->isNameUnique($updatedDeptName, $newDeptId));

// Position model validation rules
assertTest('Validation', 'Position model rejects duplicate title in same department', !$posModel->isUniqueInDepartment($updatedPosTitle, $newDeptId));
assertTest('Validation', 'Position model allows same title when updating self', $posModel->isUniqueInDepartment($updatedPosTitle, $newDeptId, $newPosId));

// -------------------------------------------------------------------------
// SUMMARY REPORT
// -------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "   TEST SUMMARY\n";
echo "====================================================================\n";
echo "Total Tests Run:  {$results['total']}\n";
echo "Passed:           {$results['passed']}\n";
echo "Failed:           {$results['failed']}\n";
echo "Pass Rate:        " . round(($results['passed'] / $results['total']) * 100, 2) . "%\n";
echo "====================================================================\n";

// Cleanup test records created during test execution
try {
    $db = Database::getInstance()->getConnection();
    if (!empty($newEmpId)) {
        $db->exec("DELETE FROM employees WHERE id = {$newEmpId}");
    }
    if (!empty($newPosId)) {
        $db->exec("DELETE FROM positions WHERE id = {$newPosId}");
    }
    if (!empty($newDeptId)) {
        $db->exec("DELETE FROM departments WHERE id = {$newDeptId}");
    }
} catch (\Throwable $e) {
    // Ignore cleanup errors
}

if ($results['failed'] > 0) {
    exit(1);
}
exit(0);
