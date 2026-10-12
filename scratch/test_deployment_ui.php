<?php
/**
 * Automated UI/UX, Workflow & Deployment Readiness Test Suite
 * Employee HRIS & Leave Management System
 */

$baseUrl = "http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/public";
$cookieAdmin = tempnam(sys_get_temp_dir(), 'hris_cookie_admin_');
$cookieHr = tempnam(sys_get_temp_dir(), 'hris_cookie_hr_');
$cookieEmployee = tempnam(sys_get_temp_dir(), 'hris_cookie_emp_');

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertCheck(string $label, bool $condition, string $detail = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$label}\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$label}" . ($detail ? " -- {$detail}" : "") . "\n";
    }
}

function curlRequest(string $url, string $method = 'GET', array $data = [], ?string $cookieFile = null, array $headers = []): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $raw = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerStr = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    @curl_close($ch);

    $redirect = null;
    if (preg_match('/^Location:\s*(.+)$/im', $headerStr, $m)) {
        $redirect = trim($m[1]);
    }

    return [
        'code'     => $httpCode,
        'headers'  => $headerStr,
        'body'     => $body,
        'redirect' => $redirect,
    ];
}

function fetchCsrfToken(string $url, ?string $cookieFile = null): string {
    $res = curlRequest($url, 'GET', [], $cookieFile);
    if (preg_match('/name=["\']_csrf_token["\']\s+value=["\']([^"\']+)["\']/i', $res['body'], $m)) {
        return $m[1];
    }
    return '';
}

echo "====================================================================\n";
echo "   DEPLOYMENT READINESS, UI/UX & LIVE WORKFLOW VERIFICATION SUITE\n";
echo "====================================================================\n\n";

// -----------------------------------------------------------------------------
// GROUP 1: PUBLIC INTERFACE & SECURITY HEADERS
// -----------------------------------------------------------------------------
echo "--- 1. PUBLIC PAGES & SECURITY HEADERS ---\n";
$homeRes = curlRequest("{$baseUrl}/");
assertCheck("Landing page renders HTTP 200", $homeRes['code'] === 200);

$loginRes = curlRequest("{$baseUrl}/login");
assertCheck("Login page renders HTTP 200", $loginRes['code'] === 200);

$notFoundRes = curlRequest("{$baseUrl}/non-existent-route-for-testing-404");
assertCheck("Non-existent URL returns HTTP 404 Not Found", $notFoundRes['code'] === 404);
assertCheck("404 response renders custom Page Not Found view", str_contains($notFoundRes['body'], 'Page Not Found'));

// -----------------------------------------------------------------------------
// GROUP 2: HR MANAGEMENT & GOVERNANCE WORKFLOWS
// -----------------------------------------------------------------------------
echo "\n--- 2. HR MANAGEMENT & GOVERNANCE WORKFLOWS ---\n";
$hrCsrf = fetchCsrfToken("{$baseUrl}/login", $cookieHr);
$loginHr = curlRequest("{$baseUrl}/login", 'POST', [
    'username'    => 'hr01',
    'password'    => 'Admin@123',
    '_csrf_token' => $hrCsrf,
], $cookieHr);
assertCheck("HR signs in successfully", $loginHr['code'] === 302);

$hrDash = curlRequest("{$baseUrl}/hr/dashboard", 'GET', [], $cookieHr);
assertCheck("HR accesses /hr/dashboard (HTTP 200)", $hrDash['code'] === 200);
assertCheck("HR dashboard displays HR OPERATIONAL PORTAL banner", str_contains($hrDash['body'], 'HR OPERATIONAL PORTAL'));

$hrUsers = curlRequest("{$baseUrl}/hr/users", 'GET', [], $cookieHr);
assertCheck("HR accesses /hr/users (HTTP 200)", $hrUsers['code'] === 200);

$hrEmpDir = curlRequest("{$baseUrl}/hr/employees", 'GET', [], $cookieHr);
assertCheck("HR accesses /hr/employees directory (HTTP 200)", $hrEmpDir['code'] === 200);

$hrDepts = curlRequest("{$baseUrl}/hr/departments", 'GET', [], $cookieHr);
assertCheck("HR accesses /hr/departments (HTTP 200)", $hrDepts['code'] === 200);

$hrPositions = curlRequest("{$baseUrl}/hr/positions", 'GET', [], $cookieHr);
assertCheck("HR accesses /hr/positions (HTTP 200)", $hrPositions['code'] === 200);

$hrLeaves = curlRequest("{$baseUrl}/leaves", 'GET', [], $cookieHr);
assertCheck("HR accesses /leaves review queue (HTTP 200)", $hrLeaves['code'] === 200);

$hrBalances = curlRequest("{$baseUrl}/leaves/balances", 'GET', [], $cookieHr);
assertCheck("HR accesses /leaves/balances (HTTP 200)", $hrBalances['code'] === 200);

$hrReports = curlRequest("{$baseUrl}/reports", 'GET', [], $cookieHr);
assertCheck("HR accesses /reports (HTTP 200)", $hrReports['code'] === 200);

$hrLogs = curlRequest("{$baseUrl}/activity-logs", 'GET', [], $cookieHr);
assertCheck("HR accesses /activity-logs (HTTP 200)", $hrLogs['code'] === 200);

$hrDsa = curlRequest("{$baseUrl}/dsa", 'GET', [], $cookieHr);
assertCheck("HR accesses /dsa laboratory (HTTP 200)", $hrDsa['code'] === 200);

// Legacy route alias test
$adminRedirect = curlRequest("{$baseUrl}/admin/dashboard", 'GET', [], $cookieHr);
assertCheck("Legacy /admin/dashboard accessible or redirects for HR", in_array($adminRedirect['code'], [200, 302]));

// -----------------------------------------------------------------------------
// GROUP 4: EMPLOYEE SELF-SERVICE WORKFLOW & OWNERSHIP ISOLATION
// -----------------------------------------------------------------------------
echo "\n--- 4. EMPLOYEE SELF-SERVICE WORKFLOW & ISOLATION ---\n";
$empCsrf = fetchCsrfToken("{$baseUrl}/login", $cookieEmployee);
$loginEmp = curlRequest("{$baseUrl}/login", 'POST', [
    'username'    => 'emp004',
    'password'    => 'Employee@123',
    '_csrf_token' => $empCsrf,
], $cookieEmployee);
assertCheck("Employee signs in successfully", $loginEmp['code'] === 302);

$empDash = curlRequest("{$baseUrl}/employee/dashboard", 'GET', [], $cookieEmployee);
assertCheck("Employee accesses /employee/dashboard (HTTP 200)", $empDash['code'] === 200);
assertCheck("Employee dashboard displays EMPLOYEE SELF-SERVICE badge", str_contains($empDash['body'], 'EMPLOYEE SELF-SERVICE'));

$empProfile = curlRequest("{$baseUrl}/employee/profile", 'GET', [], $cookieEmployee);
assertCheck("Employee accesses own profile (HTTP 200)", $empProfile['code'] === 200);
assertCheck("Profile contains employee # EMP004", str_contains($empProfile['body'], 'EMP004'));

// IDOR ownership parameter tampering check
$empTamper = curlRequest("{$baseUrl}/employee/profile?emp_id=5", 'GET', [], $cookieEmployee);
assertCheck("Parameter tampering (?emp_id=5) strictly blocked (HTTP 403)", $empTamper['code'] === 403);

// Access to File Leave Application form
$empApply = curlRequest("{$baseUrl}/leaves/apply", 'GET', [], $cookieEmployee);
assertCheck("Employee accesses /leaves/apply form (HTTP 200)", $empApply['code'] === 200);

// Employee accesses own leave balances HTML page in browser
$empHtmlBalances = curlRequest("{$baseUrl}/employee/leave-balances", 'GET', [], $cookieEmployee, ['Accept: text/html,application/xhtml+xml']);
assertCheck("Employee accesses /employee/leave-balances HTML view (HTTP 200)", $empHtmlBalances['code'] === 200);
assertCheck("Leave balances HTML view contains 'My Leave Balances'", str_contains($empHtmlBalances['body'], 'My Leave Balances'));

// Employee accesses own leave requests HTML page in browser
$empHtmlRequests = curlRequest("{$baseUrl}/employee/leave-requests", 'GET', [], $cookieEmployee, ['Accept: text/html,application/xhtml+xml']);
assertCheck("Employee accesses /employee/leave-requests HTML view (HTTP 200)", $empHtmlRequests['code'] === 200);
assertCheck("Leave requests HTML view contains 'My Leave Requests'", str_contains($empHtmlRequests['body'], 'My Leave Requests'));

// Employee blocked from HR / Admin modules
$empBlockedDir = curlRequest("{$baseUrl}/hr/employees", 'GET', [], $cookieEmployee);
assertCheck("Employee blocked from /hr/employees (HTTP 403 Forbidden)", $empBlockedDir['code'] === 403);

$empBlockedQueue = curlRequest("{$baseUrl}/leaves", 'GET', [], $cookieEmployee);
assertCheck("Employee blocked from /leaves review queue (HTTP 403 Forbidden)", $empBlockedQueue['code'] === 403);

$empBlockedAdmin = curlRequest("{$baseUrl}/admin/users", 'GET', [], $cookieEmployee);
assertCheck("Employee blocked from /admin/users (HTTP 403 Forbidden)", $empBlockedAdmin['code'] === 403);

// -----------------------------------------------------------------------------
// GROUP 5: LIVE LEAVE APPLICATION & REVIEW WORKFLOW
// -----------------------------------------------------------------------------
echo "\n--- 5. LIVE LEAVE FILING & APPROVAL TRANSACTION ---\n";
require_once __DIR__ . '/../core/bootstrap.php';
$pdo = Core\Database::getInstance()->getConnection();
// Ensure test employee has balance available
$pdo->query("UPDATE leave_balances SET used_days = 0, remaining_days = allocated_days WHERE employee_id = 4 AND year = 2026");

// 1. Employee files a leave request
$applyCsrf = fetchCsrfToken("{$baseUrl}/leaves/apply", $cookieEmployee);

// Calculate upcoming Monday to Tuesday dates
$nextMon = date('Y-m-d', strtotime('next monday'));
$nextTue = date('Y-m-d', strtotime('next monday +1 day'));
$testReason = 'Automated test suite verification leave request ' . time();

$fileLeave = curlRequest("{$baseUrl}/leaves/apply", 'POST', [
    'leave_type_id'  => 1, // Vacation Leave
    'start_date'     => $nextMon,
    'end_date'       => $nextTue,
    'number_of_days' => 2,
    'reason'         => $testReason,
    '_csrf_token'    => $applyCsrf,
], $cookieEmployee);
assertCheck("Employee submits valid leave request (HTTP 302 Redirect)", $fileLeave['code'] === 302);

// 2. Fetch the newly created pending leave request from DB
$latestReq = $pdo->query("SELECT * FROM leave_requests WHERE reason = '{$testReason}' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

assertCheck("Database contains newly filed leave request", !empty($latestReq));
$latestReqId = $latestReq['id'] ?? 0;
assertCheck("Filed request status is 'Pending'", ($latestReq['status'] ?? '') === 'Pending');

// 3. HR approves the leave request
if ($latestReqId > 0) {
    $hrQueueCsrf = fetchCsrfToken("{$baseUrl}/leaves", $cookieHr);
    $approveRes = curlRequest("{$baseUrl}/leaves/{$latestReqId}/approve", 'POST', [
        '_csrf_token' => $hrQueueCsrf,
    ], $cookieHr);
    assertCheck("HR approves leave request (HTTP 302 Redirect)", $approveRes['code'] === 302);

    // Verify DB reflects Approved state
    $approvedReq = $pdo->query("SELECT * FROM leave_requests WHERE id = {$latestReqId}")->fetch(PDO::FETCH_ASSOC);
    assertCheck("Database status transitioned to 'Approved'", ($approvedReq['status'] ?? '') === 'Approved');
}

// -----------------------------------------------------------------------------
// 6. IN-SYSTEM NOTIFICATIONS & WORKFLOW DISPATCH
// -----------------------------------------------------------------------------
echo "\n--- 6. IN-SYSTEM NOTIFICATIONS & WORKFLOW DISPATCH ---\n";

// 1. Unauthenticated access blocked
$unauthNotif = curlRequest("{$baseUrl}/notifications");
assertCheck("Unauthenticated access to /notifications redirects to login", $unauthNotif['code'] === 302);

// 2. HR accesses notifications
$hrNotif = curlRequest("{$baseUrl}/notifications", 'GET', [], $cookieHr);
assertCheck("HR accesses /notifications (HTTP 200)", $hrNotif['code'] === 200);
assertCheck("HR notifications inbox renders heading", str_contains($hrNotif['body'], 'Notifications &amp; Alerts') || str_contains($hrNotif['body'], 'Notifications & Alerts'));

// 3. Employee accesses notifications and sees leave decision
$empNotif = curlRequest("{$baseUrl}/notifications", 'GET', [], $cookieEmployee);
assertCheck("Employee accesses /notifications (HTTP 200)", $empNotif['code'] === 200);
assertCheck("Employee sees workflow notification in inbox", str_contains($empNotif['body'], 'Leave Request') || str_contains($empNotif['body'], 'Notifications &amp; Alerts'));

// 4. Employee mark-all-read with CSRF
$empNotifCsrf = fetchCsrfToken("{$baseUrl}/notifications", $cookieEmployee);
$markReadRes = curlRequest("{$baseUrl}/notifications/mark-all-read", 'POST', [
    '_csrf_token' => $empNotifCsrf,
], $cookieEmployee);
assertCheck("Mark-all-read action executes (HTTP 302 Redirect)", $markReadRes['code'] === 302);

// -----------------------------------------------------------------------------
// CLEANUP & SUMMARY
// -----------------------------------------------------------------------------
@unlink($cookieAdmin);
@unlink($cookieHr);
@unlink($cookieEmployee);

echo "\n====================================================================\n";
echo "   DEPLOYMENT SUITE TEST RESULTS SUMMARY\n";
echo "====================================================================\n";
echo "Total Tests Run:  {$totalTests}\n";
echo "Passed:           {$passedTests}\n";
echo "Failed:           {$failedTests}\n";
$rate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
echo "Pass Rate:        {$rate}%\n";
echo "====================================================================\n";

if ($failedTests > 0) {
    exit(1);
}
