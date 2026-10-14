<?php
/**
 * Phase 7 Core HRIS End-to-End HTTP Integration Test Suite
 * Executes live HTTP requests against Apache testing real routes,
 * authentication, role guards, CSRF, forms, redirects, views, and activity logging.
 */

$baseUrl = 'http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/public';
$cookieFileAdmin = __DIR__ . '/cookie_hr_admin.txt';
$cookieFileEmp   = __DIR__ . '/cookie_hr_emp.txt';

@unlink($cookieFileAdmin);
@unlink($cookieFileEmp);

$httpResults = [
    'passed' => 0,
    'failed' => 0,
    'total'  => 0,
    'tests'  => []
];

function assertHttpTest(string $testName, bool $condition, string $details = '') {
    global $httpResults;
    $httpResults['total']++;
    if ($condition) {
        $httpResults['passed']++;
        $httpResults['tests'][] = ['status' => 'PASS', 'name' => $testName, 'details' => $details];
        echo "  [PASS] {$testName}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $httpResults['failed']++;
        $httpResults['tests'][] = ['status' => 'FAIL', 'name' => $testName, 'details' => $details];
        echo "  [FAIL] {$testName}: {$details}\n";
    }
}

function makeRequest(string $url, string $method = 'GET', array $data = [], ?string $cookieFile = null, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Keep false to inspect redirects

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headerStr = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    return [
        'code'    => $httpCode,
        'headers' => $headerStr,
        'body'    => $body,
    ];
}

function extractCsrfToken(string $html): ?string
{
    if (preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', $html, $matches)) {
        return $matches[1];
    }
    return null;
}

echo "====================================================================\n";
echo "   PHASE 7 CORE HRIS LIVE HTTP END-TO-END INTEGRATION TEST SUITE\n";
echo "====================================================================\n\n";

// -------------------------------------------------------------------------
// 1. UNAUTHENTICATED ACCESS SECURITY GUARDS
// -------------------------------------------------------------------------
echo "--- 1. UNAUTHENTICATED ACCESS GUARDS ---\n";

$unauth1 = makeRequest($baseUrl . '/hr/employees');
assertHttpTest('Unauth access to /hr/employees redirects to login', $unauth1['code'] === 302 && str_contains($unauth1['headers'], 'login'), "HTTP {$unauth1['code']}");

$unauth2 = makeRequest($baseUrl . '/hr/employees/create');
assertHttpTest('Unauth access to /hr/employees/create redirects to login', $unauth2['code'] === 302 && str_contains($unauth2['headers'], 'login'), "HTTP {$unauth2['code']}");

$unauth3 = makeRequest($baseUrl . '/hr/departments');
assertHttpTest('Unauth access to /hr/departments redirects to login', $unauth3['code'] === 302 && str_contains($unauth3['headers'], 'login'), "HTTP {$unauth3['code']}");

$unauth4 = makeRequest($baseUrl . '/hr/positions');
assertHttpTest('Unauth access to /hr/positions redirects to login', $unauth4['code'] === 302 && str_contains($unauth4['headers'], 'login'), "HTTP {$unauth4['code']}");

// -------------------------------------------------------------------------
// 2. EMPLOYEE ROLE ACCESS GUARDS (ROLE-BASED AUTHORIZATION)
// -------------------------------------------------------------------------
echo "\n--- 2. EMPLOYEE ROLE RESTRICTION GUARDS ---\n";

// Log in as employee (emp004 is David Cruz)
$loginPageEmp = makeRequest($baseUrl . '/login', 'GET', [], $cookieFileEmp);
$csrfEmp = extractCsrfToken($loginPageEmp['body']);
$empLogin = makeRequest($baseUrl . '/login', 'POST', [
    '_csrf_token' => $csrfEmp,
    'username'    => 'emp004',
    'password'    => 'Employee@123'
], $cookieFileEmp);
assertHttpTest('Employee login successful', $empLogin['code'] === 302, "HTTP {$empLogin['code']}");

// Employee tries to view HR employee directory
$empDir = makeRequest($baseUrl . '/hr/employees', 'GET', [], $cookieFileEmp);
assertHttpTest('Employee denied access to /hr/employees (HTTP 403 Forbidden)', $empDir['code'] === 403, "HTTP {$empDir['code']}");

// Employee tries to access department management
$empDept = makeRequest($baseUrl . '/hr/departments', 'GET', [], $cookieFileEmp);
assertHttpTest('Employee denied access to /hr/departments (HTTP 403 Forbidden)', $empDept['code'] === 403, "HTTP {$empDept['code']}");

// Employee tries to access position management
$empPos = makeRequest($baseUrl . '/hr/positions', 'GET', [], $cookieFileEmp);
assertHttpTest('Employee denied access to /hr/positions (HTTP 403 Forbidden)', $empPos['code'] === 403, "HTTP {$empPos['code']}");

// Employee tries to execute employee deactivation
$empDeact = makeRequest($baseUrl . '/hr/employees/1/deactivate', 'POST', ['_csrf_token' => 'dummy'], $cookieFileEmp);
assertHttpTest('Employee denied state-changing POST /hr/employees/{id}/deactivate (HTTP 403)', $empDeact['code'] === 403, "HTTP {$empDeact['code']}");

// -------------------------------------------------------------------------
// 3. HR / ADMIN ROLE ACCESS & OPERATIONS
// -------------------------------------------------------------------------
echo "\n--- 3. HR / ADMIN CORE HRIS CRUD OPERATIONS ---\n";

// Log in as HR
$loginPageAdmin = makeRequest($baseUrl . '/login', 'GET', [], $cookieFileAdmin);
$csrfAdmin = extractCsrfToken($loginPageAdmin['body']);
$adminLogin = makeRequest($baseUrl . '/login', 'POST', [
    '_csrf_token' => $csrfAdmin,
    'username'    => 'hr01',
    'password'    => 'Admin@123'
], $cookieFileAdmin);
assertHttpTest('HR login successful', $adminLogin['code'] === 302, "HTTP {$adminLogin['code']}");

// 3.1 HR views Employee Directory
$hrDir = makeRequest($baseUrl . '/hr/employees', 'GET', [], $cookieFileAdmin);
assertHttpTest('HR views Employee Directory (HTTP 200 OK)', $hrDir['code'] === 200 && str_contains($hrDir['body'], 'Employees Directory'), "HTTP {$hrDir['code']}");
assertHttpTest('Employee Directory contains Search & Add actions', str_contains($hrDir['body'], 'Search by Employee ID') && str_contains($hrDir['body'], 'Add New Employee'));

// 3.2 HR views Department Management
$hrDepts = makeRequest($baseUrl . '/hr/departments', 'GET', [], $cookieFileAdmin);
assertHttpTest('HR views Department Directory (HTTP 200 OK)', $hrDepts['code'] === 200 && str_contains($hrDepts['body'], 'Departments Directory'), "HTTP {$hrDepts['code']}");

// 3.3 HR views Position Management
$hrPositions = makeRequest($baseUrl . '/hr/positions', 'GET', [], $cookieFileAdmin);
assertHttpTest('HR views Position Directory (HTTP 200 OK)', $hrPositions['code'] === 200 && str_contains($hrPositions['body'], 'Job Positions Directory'), "HTTP {$hrPositions['code']}");

// 3.4 Position dynamic API
$apiPos = makeRequest($baseUrl . '/hr/api/positions-by-department/1', 'GET', [], $cookieFileAdmin);
assertHttpTest('Dynamic API /hr/api/positions-by-department/{id} returns 200 JSON', $apiPos['code'] === 200 && str_contains($apiPos['headers'], 'application/json'));

// 3.5 HR opens Add Employee form & extracts CSRF
$hrCreateEmpPage = makeRequest($baseUrl . '/hr/employees/create', 'GET', [], $cookieFileAdmin);
assertHttpTest('HR views Add Employee Form (HTTP 200 OK)', $hrCreateEmpPage['code'] === 200 && str_contains($hrCreateEmpPage['body'], 'Register New Employee'));
$formCsrf = extractCsrfToken($hrCreateEmpPage['body']);
assertHttpTest('Form contains valid CSRF token', !empty($formCsrf));

// 3.6 POST without CSRF token is rejected
$noCsrfPost = makeRequest($baseUrl . '/hr/employees', 'POST', [
    'employee_no' => 'EMP999'
], $cookieFileAdmin, ['Accept: application/json']);
assertHttpTest('POST /hr/employees without CSRF token returns 403 Forbidden', $noCsrfPost['code'] === 403);

// 3.7 Add Employee with valid CSRF token
$newEmpNo = 'EMP' . rand(10000, 99999);
$newEmpEmail = 'live.http.' . time() . '.' . rand(100, 999) . '@company.local';
$createEmpPost = makeRequest($baseUrl . '/hr/employees', 'POST', [
    '_csrf_token'       => $formCsrf,
    'employee_no'       => $newEmpNo,
    'first_name'        => 'HTTPTestFirst',
    'middle_name'       => 'HTTPMid',
    'last_name'         => 'HTTPTestLast',
    'email'             => $newEmpEmail,
    'phone'             => '+63 900 123 4567',
    'address'           => '777 Automated Ave, Cyber City',
    'birth_date'        => '1992-08-20',
    'gender'            => 'Female',
    'department_id'     => '1',
    'position_id'       => '1',
    'date_hired'        => date('Y-m-d'),
    'employment_status' => 'Active'
], $cookieFileAdmin);
assertHttpTest('Create Employee redirects to profile (HTTP 302)', $createEmpPost['code'] === 302 && str_contains($createEmpPost['headers'], '/hr/employees/'));

// Extract newly created ID from Location header
preg_match('#/hr/employees/(\d+)#', $createEmpPost['headers'], $idMatches);
$createdEmpId = !empty($idMatches[1]) ? (int)$idMatches[1] : 0;
assertHttpTest('Resolved created Employee ID from redirect header', $createdEmpId > 0, "Employee ID: {$createdEmpId}");

// 3.8 View created Employee Profile
$profilePage = makeRequest($baseUrl . "/hr/employees/{$createdEmpId}", 'GET', [], $cookieFileAdmin);
assertHttpTest('View Employee Profile returns 200 OK', $profilePage['code'] === 200);
assertHttpTest('Profile displays demographic and employment info', 
    str_contains($profilePage['body'], 'HTTPTestFirst HTTPTestLast') && 
    str_contains($profilePage['body'], $newEmpNo) && 
    str_contains($profilePage['body'], $newEmpEmail)
);

// 3.9 Edit Employee Record
$editPage = makeRequest($baseUrl . "/hr/employees/{$createdEmpId}/edit", 'GET', [], $cookieFileAdmin);
assertHttpTest('View Edit Employee Form returns 200 OK', $editPage['code'] === 200 && str_contains($editPage['body'], 'Edit Employee'));
$editCsrf = extractCsrfToken($editPage['body']);

$updatePost = makeRequest($baseUrl . "/hr/employees/{$createdEmpId}/edit", 'POST', [
    '_csrf_token'       => $editCsrf,
    'employee_no'       => $newEmpNo,
    'first_name'        => 'HTTPTestFirstMod',
    'middle_name'       => 'HTTPMid',
    'last_name'         => 'HTTPTestLastMod',
    'email'             => $newEmpEmail,
    'phone'             => '+63 911 222 3333',
    'address'           => '888 Updated Ave, Cyber City',
    'birth_date'        => '1992-08-20',
    'gender'            => 'Female',
    'department_id'     => '1',
    'position_id'       => '1',
    'date_hired'        => date('Y-m-d'),
    'employment_status' => 'Active'
], $cookieFileAdmin);
assertHttpTest('Update Employee redirects to profile (HTTP 302)', $updatePost['code'] === 302 && str_contains($updatePost['headers'], "/hr/employees/{$createdEmpId}"));

// Verify updated profile
$updatedProfile = makeRequest($baseUrl . "/hr/employees/{$createdEmpId}", 'GET', [], $cookieFileAdmin);
assertHttpTest('Profile displays updated employee name', str_contains($updatedProfile['body'], 'HTTPTestFirstMod HTTPTestLastMod'));

// 3.10 Soft-Deactivate Employee
$deactPage = makeRequest($baseUrl . "/hr/employees", 'GET', [], $cookieFileAdmin);
$deactCsrf = extractCsrfToken($deactPage['body']);

$deactPost = makeRequest($baseUrl . "/hr/employees/{$createdEmpId}/deactivate", 'POST', [
    '_csrf_token' => $deactCsrf
], $cookieFileAdmin);
assertHttpTest('POST /hr/employees/{id}/deactivate redirects back (HTTP 302)', $deactPost['code'] === 302);

// Re-check profile: must now display Inactive badge
$deactivatedProfile = makeRequest($baseUrl . "/hr/employees/{$createdEmpId}", 'GET', [], $cookieFileAdmin);
assertHttpTest('Profile displays Inactive status badge', str_contains($deactivatedProfile['body'], 'Inactive'));

// 3.11 Search in Employee Directory
$searchReq = makeRequest($baseUrl . "/hr/employees?q=" . urlencode($newEmpNo), 'GET', [], $cookieFileAdmin);
assertHttpTest('Search finds newly created employee by employee_no', str_contains($searchReq['body'], $newEmpNo));

// 3.12 Department Management: Create Department
$deptFormPage = makeRequest($baseUrl . "/hr/departments/create", 'GET', [], $cookieFileAdmin);
$deptCsrf = extractCsrfToken($deptFormPage['body']);
$testDeptHttp = "HTTP Dept " . time();
$createDeptPost = makeRequest($baseUrl . "/hr/departments", 'POST', [
    '_csrf_token'     => $deptCsrf,
    'department_name' => $testDeptHttp,
    'description'     => 'Created via automated HTTP test'
], $cookieFileAdmin);
assertHttpTest('Create Department redirects to list (HTTP 302)', $createDeptPost['code'] === 302);

// Verify department in list
$deptListPage = makeRequest($baseUrl . "/hr/departments", 'GET', [], $cookieFileAdmin);
assertHttpTest('Department list displays newly created department', str_contains($deptListPage['body'], $testDeptHttp));

// Find created department ID from DB
require_once __DIR__ . '/../core/bootstrap.php';
$db = \Core\Database::getInstance()->getConnection();
$httpDeptId = (int)$db->query("SELECT id FROM departments WHERE department_name = " . $db->quote($testDeptHttp))->fetchColumn();
assertHttpTest('Resolved created Department ID from database', $httpDeptId > 0, "Dept ID: {$httpDeptId}");

// Edit Department
$deptEditPage = makeRequest($baseUrl . "/hr/departments/{$httpDeptId}/edit", 'GET', [], $cookieFileAdmin);
$deptEditCsrf = extractCsrfToken($deptEditPage['body']);
$testDeptHttpMod = $testDeptHttp . " Mod";
$editDeptPost = makeRequest($baseUrl . "/hr/departments/{$httpDeptId}/edit", 'POST', [
    '_csrf_token'     => $deptEditCsrf,
    'department_name' => $testDeptHttpMod,
    'description'     => 'Updated via automated HTTP test'
], $cookieFileAdmin);
assertHttpTest('Update Department redirects to list (HTTP 302)', $editDeptPost['code'] === 302);

// 3.13 Position Management: Create Position
$posFormPage = makeRequest($baseUrl . "/hr/positions/create", 'GET', [], $cookieFileAdmin);
$posCsrf = extractCsrfToken($posFormPage['body']);
$testPosHttp = "HTTP Role " . time();
$createPosPost = makeRequest($baseUrl . "/hr/positions", 'POST', [
    '_csrf_token'   => $posCsrf,
    'department_id' => $httpDeptId,
    'position_name' => $testPosHttp,
    'description'   => 'Created via automated HTTP test'
], $cookieFileAdmin);
assertHttpTest('Create Position redirects to list (HTTP 302)', $createPosPost['code'] === 302);

// Verify position in list
$posListPage = makeRequest($baseUrl . "/hr/positions", 'GET', [], $cookieFileAdmin);
assertHttpTest('Position list displays newly created position', str_contains($posListPage['body'], $testPosHttp));

// Find created position ID from DB
$httpPosId = (int)$db->query("SELECT id FROM positions WHERE position_name = " . $db->quote($testPosHttp))->fetchColumn();
assertHttpTest('Resolved created Position ID from database', $httpPosId > 0, "Position ID: {$httpPosId}");

// Edit Position
$posEditPage = makeRequest($baseUrl . "/hr/positions/{$httpPosId}/edit", 'GET', [], $cookieFileAdmin);
$posEditCsrf = extractCsrfToken($posEditPage['body']);
$testPosHttpMod = $testPosHttp . " Mod";
$editPosPost = makeRequest($baseUrl . "/hr/positions/{$httpPosId}/edit", 'POST', [
    '_csrf_token'   => $posEditCsrf,
    'department_id' => $httpDeptId,
    'position_name' => $testPosHttpMod,
    'description'   => 'Updated via automated HTTP test'
], $cookieFileAdmin);
assertHttpTest('Update Position redirects to list (HTTP 302)', $editPosPost['code'] === 302);

// 3.14 Audit Log Verification
$recentLogs = $db->query("SELECT action, entity_type FROM activity_logs ORDER BY id DESC LIMIT 10")->fetchAll();
$actionsLogged = array_column($recentLogs, 'action');
assertHttpTest('Audit log recorded EMPLOYEE_CREATED', in_array('EMPLOYEE_CREATED', $actionsLogged, true));
assertHttpTest('Audit log recorded EMPLOYEE_UPDATED', in_array('EMPLOYEE_UPDATED', $actionsLogged, true));
assertHttpTest('Audit log recorded EMPLOYEE_DEACTIVATED', in_array('EMPLOYEE_DEACTIVATED', $actionsLogged, true));
assertHttpTest('Audit log recorded DEPARTMENT_CREATED', in_array('DEPARTMENT_CREATED', $actionsLogged, true));
assertHttpTest('Audit log recorded DEPARTMENT_UPDATED', in_array('DEPARTMENT_UPDATED', $actionsLogged, true));
assertHttpTest('Audit log recorded POSITION_CREATED', in_array('POSITION_CREATED', $actionsLogged, true));
assertHttpTest('Audit log recorded POSITION_UPDATED', in_array('POSITION_UPDATED', $actionsLogged, true));

@unlink($cookieFileAdmin);
@unlink($cookieFileEmp);

if ($httpResults['failed'] > 0) {
    exit(1);
}
exit(0);
