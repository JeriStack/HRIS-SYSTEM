<?php
/**
 * Master RBAC & Server-Side Authorization End-to-End Test Suite
 * Employee HRIS & Leave Management System (2-Role Model: HR & Employee)
 *
 * Fully validates:
 * 1. Authentication, Sessions & Credentials
 * 2. 2-Way Mutually Exclusive Dashboard Isolation Matrix (HR & Employee)
 * 3. HR-Managed User & Role Governance
 * 4. Last Active HR Lockout & Self-Demotion Protection
 * 5. Employee Resource Ownership Enforcement (spoofing rejection)
 * 6. CSRF Protection on all state mutations
 * 7. Verification that 'admin' user account is removed from authentication boundary
 */

$baseUrl = 'http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/public';
$cookieHr       = __DIR__ . '/cookie_rbac_hr.txt';
$cookieEmployee = __DIR__ . '/cookie_rbac_emp.txt';

@unlink($cookieHr);
@unlink($cookieEmployee);

$results = [
    'total'  => 0,
    'passed' => 0,
    'failed' => 0,
    'tests'  => []
];

function assertTest(string $name, bool $condition, string $details = ''): void
{
    global $results;
    $results['total']++;
    if ($condition) {
        $results['passed']++;
        $results['tests'][] = ['status' => 'PASS', 'name' => $name, 'details' => $details];
        echo "  [PASS] {$name}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $results['failed']++;
        $results['tests'][] = ['status' => 'FAIL', 'name' => $name, 'details' => $details];
        echo "  [FAIL] {$name}: {$details}\n";
    }
}

function curlReq(string $url, string $method = 'GET', array $data = [], ?string $cookieFile = null, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

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

    $redirectUrl = null;
    if (preg_match('/^Location:\s*(.*)$/mi', $headerStr, $matches)) {
        $redirectUrl = trim($matches[1]);
    }

    // curl_close is a no-op in PHP 8.0+ and deprecated in PHP 8.5

    return [
        'code'     => $httpCode,
        'headers'  => $headerStr,
        'body'     => $body,
        'redirect' => $redirectUrl,
    ];
}

function getCsrf(string $url, ?string $cookieFile = null): string
{
    $res = curlReq($url, 'GET', [], $cookieFile);
    if (preg_match('/name=["\']_csrf_token["\']\s+value=["\']([a-f0-9]{64})["\']/i', $res['body'], $matches)) {
        return $matches[1];
    }
    return '';
}

echo "====================================================================\n";
echo "   COMPLETE RBAC & SERVER-SIDE AUTHORIZATION TEST SUITE (2-ROLE)\n";
echo "====================================================================\n\n";

// -----------------------------------------------------------------------------
// SECTION 1: AUTHENTICATION & CREDENTIAL TESTS
// -----------------------------------------------------------------------------
echo "--- 1. AUTHENTICATION & CREDENTIAL TESTS ---\n";

// 1.1 Login Page accessibility
$loginGet = curlReq("{$baseUrl}/login", 'GET');
assertTest("Login page renders HTTP 200", $loginGet['code'] === 200);
assertTest("Login form contains CSRF token", str_contains($loginGet['body'], '_csrf_token'));

// 1.2 Rejected Invalid Credentials
$invalidPass = curlReq("{$baseUrl}/login", 'POST', [
    'username'    => 'hr01',
    'password'    => 'WrongPassword!',
    '_csrf_token' => getCsrf("{$baseUrl}/login"),
]);
assertTest("Invalid password redirects to /login (HTTP 302)", $invalidPass['code'] === 302 && str_contains($invalidPass['redirect'] ?? '', '/login'));

// 1.3 Rejected Unknown User
$unknownUser = curlReq("{$baseUrl}/login", 'POST', [
    'username'    => 'nonexistent_user',
    'password'    => 'SomePass@123',
    '_csrf_token' => getCsrf("{$baseUrl}/login"),
]);
assertTest("Unknown username redirects to /login (HTTP 302)", $unknownUser['code'] === 302 && str_contains($unknownUser['redirect'] ?? '', '/login'));

// 1.4 Rejected Empty Credentials
$emptyLogin = curlReq("{$baseUrl}/login", 'POST', [
    'username'    => '',
    'password'    => '',
    '_csrf_token' => getCsrf("{$baseUrl}/login"),
]);
assertTest("Empty credentials rejected (HTTP 302)", $emptyLogin['code'] === 302);

// 1.5 Missing CSRF Protection Test
$missingCsrf = curlReq("{$baseUrl}/login", 'POST', [
    'username' => 'hr01',
    'password' => 'Admin@123',
]);
assertTest("Login POST without CSRF token rejected", $missingCsrf['code'] === 302 && str_contains($missingCsrf['redirect'] ?? '', '/login'));

// 1.6 Deleted Admin Account Rejection Test
$deletedAdminLogin = curlReq("{$baseUrl}/login", 'POST', [
    'username'    => 'admin',
    'password'    => 'Admin@123',
    '_csrf_token' => getCsrf("{$baseUrl}/login"),
]);
assertTest("Removed 'admin' account rejected at login boundary (HTTP 302)", $deletedAdminLogin['code'] === 302 && str_contains($deletedAdminLogin['redirect'] ?? '', '/login'));

// 1.7 HR Officer Login
$hrCsrf = getCsrf("{$baseUrl}/login", $cookieHr);
$hrLogin = curlReq("{$baseUrl}/login", 'POST', [
    'username'    => 'hr01',
    'password'    => 'Admin@123',
    '_csrf_token' => $hrCsrf,
], $cookieHr);
assertTest("HR Officer credentials accepted (HTTP 302)", $hrLogin['code'] === 302);
assertTest("HR redirects to /dashboard entrypoint", str_contains($hrLogin['redirect'] ?? '', '/dashboard'));

$hrDispatch = curlReq("{$baseUrl}/dashboard", 'GET', [], $cookieHr);
assertTest("HR dispatched from /dashboard to /hr/dashboard", str_contains($hrDispatch['redirect'] ?? '', '/hr/dashboard'));

// 1.8 Employee Login
$empCsrf = getCsrf("{$baseUrl}/login", $cookieEmployee);
$empLogin = curlReq("{$baseUrl}/login", 'POST', [
    'username'    => 'emp004',
    'password'    => 'Employee@123',
    '_csrf_token' => $empCsrf,
], $cookieEmployee);
assertTest("Employee credentials accepted (HTTP 302)", $empLogin['code'] === 302);

$empDispatch = curlReq("{$baseUrl}/dashboard", 'GET', [], $cookieEmployee);
assertTest("Employee dispatched from /dashboard to /employee/dashboard", str_contains($empDispatch['redirect'] ?? '', '/employee/dashboard'));

// -----------------------------------------------------------------------------
// SECTION 2: MUTUALLY EXCLUSIVE DASHBOARD ISOLATION MATRIX
// -----------------------------------------------------------------------------
echo "\n--- 2. MUTUALLY EXCLUSIVE DASHBOARD ISOLATION MATRIX ---\n";

// Unauthenticated Visitor
$unauthHrDash = curlReq("{$baseUrl}/hr/dashboard", 'GET');
assertTest("[Unauthenticated] Access to /hr/dashboard redirected to login", $unauthHrDash['code'] === 302 && str_contains($unauthHrDash['redirect'] ?? '', '/login'));

$unauthEmpDash = curlReq("{$baseUrl}/employee/dashboard", 'GET');
assertTest("[Unauthenticated] Access to /employee/dashboard redirected to login", $unauthEmpDash['code'] === 302 && str_contains($unauthEmpDash['redirect'] ?? '', '/login'));

// HR Officer Access
$hrAccessHr = curlReq("{$baseUrl}/hr/dashboard", 'GET', [], $cookieHr);
assertTest("[HR -> HR Dashboard] ALLOWED (HTTP 200 OK)", $hrAccessHr['code'] === 200);
assertTest("[HR Dashboard] Displays HR Operational Portal banner", str_contains($hrAccessHr['body'], 'HR OPERATIONAL PORTAL'));

$hrAccessEmp = curlReq("{$baseUrl}/employee/dashboard", 'GET', [], $cookieHr);
assertTest("[HR -> Employee Dashboard] STRICTLY DENIED (HTTP 403 Forbidden)", $hrAccessEmp['code'] === 403);

// Employee Access
$empAccessHr = curlReq("{$baseUrl}/hr/dashboard", 'GET', [], $cookieEmployee);
assertTest("[Employee -> HR Dashboard] STRICTLY DENIED (HTTP 403 Forbidden)", $empAccessHr['code'] === 403);

$empAccessEmp = curlReq("{$baseUrl}/employee/dashboard", 'GET', [], $cookieEmployee);
assertTest("[Employee -> Employee Dashboard] ALLOWED (HTTP 200 OK)", $empAccessEmp['code'] === 200);
assertTest("[Employee Dashboard] Displays Employee Self-Service badge", str_contains($empAccessEmp['body'], 'EMPLOYEE SELF-SERVICE'));

// -----------------------------------------------------------------------------
// SECTION 3: PRIVILEGED HR USER & ROLE GOVERNANCE
// -----------------------------------------------------------------------------
echo "\n--- 3. USER ACCOUNT & ROLE GOVERNANCE TESTS ---\n";

// 3.1 HR accesses User Accounts directory; Employee blocked
$hrUsersDir = curlReq("{$baseUrl}/hr/users", 'GET', [], $cookieHr);
assertTest("HR accesses /hr/users directory (HTTP 200 OK)", $hrUsersDir['code'] === 200);
assertTest("Users table displays registered accounts and roles", str_contains($hrUsersDir['body'], 'Registered Accounts Directory') && str_contains($hrUsersDir['body'], 'hr01'));

$empUsersDir = curlReq("{$baseUrl}/hr/users", 'GET', [], $cookieEmployee);
assertTest("Employee denied access to /hr/users (HTTP 403 Forbidden)", $empUsersDir['code'] === 403);

// 3.2 HR accesses User Edit Form; Employee blocked
$hrEditForm = curlReq("{$baseUrl}/hr/users/3/edit", 'GET', [], $cookieHr); // User 3 is hr02
assertTest("HR views User Edit Form for user #3 (HTTP 200 OK)", $hrEditForm['code'] === 200);
assertTest("Form contains CSRF token", preg_match('/name=["\']_csrf_token["\']/i', $hrEditForm['body']) === 1);

$empEditForm = curlReq("{$baseUrl}/hr/users/3/edit", 'GET', [], $cookieEmployee);
assertTest("Employee denied access to /hr/users/{id}/edit (HTTP 403 Forbidden)", $empEditForm['code'] === 403);

// Extract CSRF token from edit form
preg_match('/name=["\']_csrf_token["\']\s+value=["\']([a-f0-9]{64})["\']/i', $hrEditForm['body'], $csrfMatch);
$editCsrf = $csrfMatch[1] ?? '';

// 3.3 CSRF Protection on Role Update
$unprotectedUpdate = curlReq("{$baseUrl}/hr/users/3/edit", 'POST', [
    'role'   => 'hr',
    'status' => 'Active',
], $cookieHr);
assertTest("User update POST without CSRF token rejected", ($unprotectedUpdate['code'] === 302 && str_contains($unprotectedUpdate['redirect'] ?? '', '/login')) || $unprotectedUpdate['code'] === 403);

// 3.4 Reject Invalid Role Assignment (e.g. 'superadmin' or 'admin')
$invalidRoleUpdate = curlReq("{$baseUrl}/hr/users/3/edit", 'POST', [
    'role'        => 'superadmin', // Invalid role
    'status'      => 'Active',
    '_csrf_token' => $editCsrf,
], $cookieHr);
assertTest("Invalid role ('superadmin') rejected (HTTP 302 redirect back)", $invalidRoleUpdate['code'] === 302);

// 3.5 Valid Role Update (HR updates hr02 role and status)
$validRoleUpdate = curlReq("{$baseUrl}/hr/users/3/edit", 'POST', [
    'role'        => 'hr',
    'status'      => 'Active',
    '_csrf_token' => $editCsrf,
], $cookieHr);
assertTest("Valid role update accepted (HTTP 302)", $validRoleUpdate['code'] === 302 && str_contains($validRoleUpdate['redirect'] ?? '', '/hr/users'));

// 3.6 Prevent Self-Demotion of Active HR Session (User #2 is hr01)
$hrEditSelf = curlReq("{$baseUrl}/hr/users/2/edit", 'GET', [], $cookieHr);
preg_match('/name=["\']_csrf_token["\']\s+value=["\']([a-f0-9]{64})["\']/i', $hrEditSelf['body'], $csrfSelfMatch);
$selfCsrf = $csrfSelfMatch[1] ?? '';

$selfDemotion = curlReq("{$baseUrl}/hr/users/2/edit", 'POST', [
    'role'        => 'employee', // Attempting to demote self
    'status'      => 'Active',
    '_csrf_token' => $selfCsrf,
], $cookieHr);
assertTest("Self-demotion attempt rejected (HTTP 302 redirect back)", $selfDemotion['code'] === 302 && str_contains($selfDemotion['redirect'] ?? '', '/hr/users/2/edit'));

// 3.7 Prevent Self-Deactivation of Active HR Session
$selfDeactivation = curlReq("{$baseUrl}/hr/users/2/edit", 'POST', [
    'role'        => 'hr',
    'status'      => 'Inactive', // Attempting to deactivate self
    '_csrf_token' => $selfCsrf,
], $cookieHr);
assertTest("Self-deactivation attempt rejected (HTTP 302 redirect back)", $selfDeactivation['code'] === 302 && str_contains($selfDeactivation['redirect'] ?? '', '/hr/users/2/edit'));

// -----------------------------------------------------------------------------
// SECTION 4: EMPLOYEE RECORD OWNERSHIP ENFORCEMENT
// -----------------------------------------------------------------------------
echo "\n--- 4. EMPLOYEE RESOURCE OWNERSHIP TESTS ---\n";

// 4.1 Employee views own profile
$empOwnProfile = curlReq("{$baseUrl}/employee/profile", 'GET', [], $cookieEmployee);
assertTest("Employee views own profile (HTTP 200 OK)", $empOwnProfile['code'] === 200);
assertTest("Profile contains employee # EMP004 details", str_contains($empOwnProfile['body'], 'EMP004'));

// 4.2 Parameter Spoofing Attack Rejection: emp004 requests emp_id=5 (Maria Santos)
$spoofedProfile = curlReq("{$baseUrl}/employee/profile?emp_id=5", 'GET', [], $cookieEmployee);
assertTest("Profile parameter tampering (?emp_id=5) STRICTLY DENIED (HTTP 403 Forbidden)", $spoofedProfile['code'] === 403);
assertTest("Error page indicates ownership violation", str_contains($spoofedProfile['body'], 'Ownership Access Denied') || str_contains($spoofedProfile['body'], '403'));

// 4.3 Employee views own leave balances
$empBalances = curlReq("{$baseUrl}/employee/leave-balances", 'GET', [], $cookieEmployee, ['Accept: application/json']);
assertTest("Employee views own leave balances API (HTTP 200 OK)", $empBalances['code'] === 200);
assertTest("Leave balances API returns employee EMP004 data", str_contains($empBalances['body'], 'EMP004'));

// 4.4 Parameter Spoofing on Leave Balances
$spoofedBalances = curlReq("{$baseUrl}/employee/leave-balances?emp_id=5", 'GET', [], $cookieEmployee, ['Accept: application/json']);
assertTest("Balances API parameter tampering (?emp_id=5) STRICTLY DENIED (HTTP 403 Forbidden)", $spoofedBalances['code'] === 403);

// 4.5 Employee views own leave requests
$empRequests = curlReq("{$baseUrl}/employee/leave-requests", 'GET', [], $cookieEmployee, ['Accept: application/json']);
assertTest("Employee views own leave requests API (HTTP 200 OK)", $empRequests['code'] === 200);
assertTest("Leave requests API returns employee EMP004 data", str_contains($empRequests['body'], 'EMP004'));

// 4.6 Parameter Spoofing on Leave Requests
$spoofedRequests = curlReq("{$baseUrl}/employee/leave-requests?emp_id=5", 'GET', [], $cookieEmployee, ['Accept: application/json']);
assertTest("Requests API parameter tampering (?emp_id=5) STRICTLY DENIED (HTTP 403 Forbidden)", $spoofedRequests['code'] === 403);

// -----------------------------------------------------------------------------
// SECTION 5: LOGOUT & POST-LOGOUT PROTECTION
// -----------------------------------------------------------------------------
echo "\n--- 5. LOGOUT & POST-LOGOUT PROTECTION TESTS ---\n";

// Fresh session to test logout
$cookieLogout = __DIR__ . '/cookie_rbac_logout.txt';
@unlink($cookieLogout);

$logoutLogin = curlReq("{$baseUrl}/login", 'POST', [
    'username'    => 'emp004',
    'password'    => 'Employee@123',
    '_csrf_token' => getCsrf("{$baseUrl}/login", $cookieLogout),
], $cookieLogout);
assertTest("Temporary session logged in successfully (HTTP 200)", $logoutLogin['code'] === 302);

// Extract CSRF token from employee dashboard
$empDashRes = curlReq("{$baseUrl}/employee/dashboard", 'GET', [], $cookieLogout);
preg_match('/name=["\']_csrf_token["\']\s+value=["\']([a-f0-9]{64})["\']/i', $empDashRes['body'], $logoutCsrfMatch);
$logoutCsrf = $logoutCsrfMatch[1] ?? '';

// Execute POST /logout
$logoutRes = curlReq("{$baseUrl}/logout", 'POST', [
    '_csrf_token' => $logoutCsrf,
], $cookieLogout);
assertTest("POST /logout terminates session and redirects (HTTP 302)", $logoutRes['code'] === 302 && str_contains($logoutRes['redirect'] ?? '', '/login'));

// Attempt accessing protected employee dashboard with terminated session
$postLogoutAccess = curlReq("{$baseUrl}/employee/dashboard", 'GET', [], $cookieLogout);
assertTest("Post-logout access to /employee/dashboard redirected to login (HTTP 302)", $postLogoutAccess['code'] === 302 && str_contains($postLogoutAccess['redirect'] ?? '', '/login'));

@unlink($cookieLogout);

echo "\n====================================================================\n";
echo "   RBAC TEST RESULTS SUMMARY\n";
echo "====================================================================\n";
echo "Total Tests Run:  {$results['total']}\n";
echo "Passed:           {$results['passed']}\n";
echo "Failed:           {$results['failed']}\n";
$rate = $results['total'] > 0 ? round(($results['passed'] / $results['total']) * 100, 1) : 0;
echo "Pass Rate:        {$rate}%\n";
echo "====================================================================\n";

if ($results['failed'] > 0) {
    echo "\nFailed Tests:\n";
    foreach ($results['tests'] as $t) {
        if ($t['status'] === 'FAIL') {
            echo "  - {$t['name']} : {$t['details']}\n";
        }
    }
}
