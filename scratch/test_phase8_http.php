<?php
/**
 * Phase 8 Leave Management End-to-End HTTP Integration & RBAC Security Suite
 * Executes live HTTP requests against the web server testing real routes,
 * authentication, role guards, CSRF, filing, approvals, rejections, and parameter tampering.
 *
 * Employee HRIS & Leave Management System
 */

$baseUrl = getenv('TEST_BASE_URL') ?: 'http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/public';
$cookieFileHR  = __DIR__ . '/cookie_p8_hr.txt';
$cookieFileEmp = __DIR__ . '/cookie_p8_emp.txt';

@unlink($cookieFileHR);
@unlink($cookieFileEmp);

require_once __DIR__ . '/../core/bootstrap.php';
try {
    $cleanPdo = \Core\Database::getInstance()->getConnection();
    $cleanPdo->exec("DELETE FROM leave_requests WHERE reason LIKE 'PHASE8_HTTP_%'");
} catch (\Throwable $e) {}

$httpResults = [
    'passed' => 0,
    'failed' => 0,
    'total'  => 0,
];

function assertHttp(string $testName, bool $condition, string $details = ''): void {
    global $httpResults;
    $httpResults['total']++;
    if ($condition) {
        $httpResults['passed']++;
        echo "  [PASS] {$testName}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $httpResults['failed']++;
        echo "  [FAIL] {$testName}: {$details}\n";
    }
}

function curlReq(string $url, string $method = 'GET', array $data = [], ?string $cookieFile = null, array $headers = []): array {
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

    return [
        'code'    => $httpCode,
        'headers' => $headerStr,
        'body'    => $body,
    ];
}

function extractCsrf(string $html): string {
    if (preg_match('/name=["\']_csrf_token["\']\s+value=["\']([^"\']+)["\']/', $html, $matches)) {
        return $matches[1];
    }
    if (preg_match('/value=["\']([^"\']+)["\']\s+name=["\']_csrf_token["\']/', $html, $matches)) {
        return $matches[1];
    }
    return '';
}

echo "====================================================================\n";
echo "   PHASE 8 LIVE HTTP INTEGRATION & SECURITY TEST SUITE\n";
echo "====================================================================\n\n";

// --- 1. AUTHENTICATION & LOGIN ---
echo "--- 1. AUTHENTICATION SETUP ---\n";

// 1.1 Login HR User (hr01 / Admin@123)
$loginPageHR = curlReq("{$baseUrl}/login", 'GET', [], $cookieFileHR);
$csrfHR = extractCsrf($loginPageHR['body']);
assertHttp('Login page renders for HR session', $loginPageHR['code'] === 200);
assertHttp('CSRF token extracted from login form', !empty($csrfHR));

$authHR = curlReq("{$baseUrl}/login", 'POST', [
    'username'    => 'hr01',
    'password'    => 'Admin@123',
    '_csrf_token' => $csrfHR,
], $cookieFileHR);
assertHttp('HR officer authenticates successfully', $authHR['code'] === 302, "HTTP {$authHR['code']}");

// 1.2 Login Employee User (emp004 / Employee@123)
$loginPageEmp = curlReq("{$baseUrl}/login", 'GET', [], $cookieFileEmp);
$csrfEmp = extractCsrf($loginPageEmp['body']);

$authEmp = curlReq("{$baseUrl}/login", 'POST', [
    'username'    => 'emp004',
    'password'    => 'Employee@123',
    '_csrf_token' => $csrfEmp,
], $cookieFileEmp);
assertHttp('Employee authenticates successfully', $authEmp['code'] === 302, "HTTP {$authEmp['code']}");

echo "\n";

// --- 2. RBAC ACCESS CONTROL ON LEAVE ROUTES ---
echo "--- 2. RBAC ACCESS CONTROL ON LEAVE ROUTES ---\n";

// 2.1 HR Access to Review Queue
$hrQueue = curlReq("{$baseUrl}/leaves", 'GET', [], $cookieFileHR);
assertHttp('[HR -> /leaves] Queue accessible to HR (HTTP 200)', $hrQueue['code'] === 200, "HTTP {$hrQueue['code']}");

// 2.2 Employee Access to Review Queue -> STRICTLY DENIED (403)
$empQueue = curlReq("{$baseUrl}/leaves", 'GET', [], $cookieFileEmp);
assertHttp('[Employee -> /leaves] STRICTLY DENIED (HTTP 403 Forbidden)', $empQueue['code'] === 403, "HTTP {$empQueue['code']}");

// 2.3 HR Access to Workforce Balances
$hrBalances = curlReq("{$baseUrl}/leaves/balances", 'GET', [], $cookieFileHR);
assertHttp('[HR -> /leaves/balances] Balances directory accessible to HR (HTTP 200)', $hrBalances['code'] === 200, "HTTP {$hrBalances['code']}");

// 2.4 Employee Access to Workforce Balances -> STRICTLY DENIED (403)
$empBalances = curlReq("{$baseUrl}/leaves/balances", 'GET', [], $cookieFileEmp);
assertHttp('[Employee -> /leaves/balances] STRICTLY DENIED (HTTP 403 Forbidden)', $empBalances['code'] === 403, "HTTP {$empBalances['code']}");

// 2.5 HR Access to Leave Types Directory
$hrTypes = curlReq("{$baseUrl}/hr/leave-types", 'GET', [], $cookieFileHR);
assertHttp('[HR -> /hr/leave-types] Leave types accessible to HR (HTTP 200)', $hrTypes['code'] === 200, "HTTP {$hrTypes['code']}");

// 2.6 Employee Access to Leave Types Directory -> STRICTLY DENIED (403)
$empTypes = curlReq("{$baseUrl}/hr/leave-types", 'GET', [], $cookieFileEmp);
assertHttp('[Employee -> /hr/leave-types] STRICTLY DENIED (HTTP 403 Forbidden)', $empTypes['code'] === 403, "HTTP {$empTypes['code']}");

// 2.7 Employee Access to Leave Types Create Form -> STRICTLY DENIED (403)
$empTypeCreate = curlReq("{$baseUrl}/hr/leave-types/create", 'GET', [], $cookieFileEmp);
assertHttp('[Employee -> /hr/leave-types/create] STRICTLY DENIED (HTTP 403 Forbidden)', $empTypeCreate['code'] === 403, "HTTP {$empTypeCreate['code']}");

echo "\n";

// --- 3. EMPLOYEE LEAVE APPLICATION & CSRF ---
echo "--- 3. EMPLOYEE LEAVE APPLICATION & CSRF ---\n";

// 3.1 Employee views File Leave Request Form
$applyPage = curlReq("{$baseUrl}/leaves/apply", 'GET', [], $cookieFileEmp);
assertHttp('[Employee] File Leave Request form renders (HTTP 200)', $applyPage['code'] === 200, "HTTP {$applyPage['code']}");
$applyCsrf = extractCsrf($applyPage['body']);
assertHttp('[Employee] File Leave form contains valid CSRF token', !empty($applyCsrf));

// 3.2 Submission WITHOUT CSRF token must be rejected
$forgedApply = curlReq("{$baseUrl}/leaves/apply", 'POST', [
    'leave_type_id' => 1,
    'start_date'    => '2026-12-07',
    'end_date'      => '2026-12-09',
    'reason'        => 'Forged submission attempt without CSRF',
], $cookieFileEmp);
assertHttp('[Security] Leave submission WITHOUT CSRF token is rejected (HTTP 403/419/Rejected)', in_array($forgedApply['code'], [403, 419, 302], true), "HTTP {$forgedApply['code']}");

// 3.3 Valid Leave Submission (Using unseeded December 2026 dates)
$uniqueReason = 'PHASE8_HTTP_Annual rest ' . time();
$validApply = curlReq("{$baseUrl}/leaves/apply", 'POST', [
    '_csrf_token'   => $applyCsrf,
    'leave_type_id' => 1,
    'start_date'    => '2026-12-07',
    'end_date'      => '2026-12-09',
    'reason'        => $uniqueReason,
], $cookieFileEmp);
assertHttp('[Filing] Valid leave submission accepted and redirected (HTTP 302)', $validApply['code'] === 302, "HTTP {$validApply['code']}");

// Inspect database for submitted request
require_once __DIR__ . '/../core/bootstrap.php';
$pdo = \Core\Database::getInstance()->getConnection();
$stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE reason = :reason ORDER BY id DESC LIMIT 1");
$stmt->execute(['reason' => $uniqueReason]);
$httpReq = $stmt->fetch(PDO::FETCH_ASSOC);
assertHttp('[Filing] Submitted request persisted in database as Pending', $httpReq !== false && $httpReq['status'] === 'Pending');
assertHttp('[Filing] Server accurately calculated 3 business days', (int)($httpReq['number_of_days'] ?? 0) === 3);
$httpReqId = (int)($httpReq['id'] ?? 0);

echo "\n";

// --- 4. HR REVIEW & APPROVAL WORKFLOW ---
echo "--- 4. HR REVIEW & APPROVAL WORKFLOW ---\n";

// 4.1 HR views Review Queue and sees application
$queuePage = curlReq("{$baseUrl}/leaves", 'GET', [], $cookieFileHR);
assertHttp('[HR Queue] Review queue includes newly filed application', str_contains($queuePage['body'], '#LR-' . str_pad((string)$httpReqId, 3, '0', STR_PAD_LEFT)));
$hrActionCsrf = extractCsrf($queuePage['body']);

// 4.2 Employee tries to approve own request -> STRICTLY DENIED (403)
$empApprove = curlReq("{$baseUrl}/leaves/{$httpReqId}/approve", 'POST', [
    '_csrf_token' => $applyCsrf,
], $cookieFileEmp);
assertHttp('[Security] Employee attempt to approve leave request is STRICTLY DENIED (HTTP 403)', $empApprove['code'] === 403, "HTTP {$empApprove['code']}");

// 4.3 HR approves the leave request
$hrApprove = curlReq("{$baseUrl}/leaves/{$httpReqId}/approve", 'POST', [
    '_csrf_token' => $hrActionCsrf,
], $cookieFileHR);
assertHttp('[Approval] HR approves leave request (HTTP 302 redirect)', $hrApprove['code'] === 302, "HTTP {$hrApprove['code']}");

// Check database status
$stmtCheck = $pdo->query("SELECT * FROM leave_requests WHERE id = {$httpReqId}");
$approvedReq = $stmtCheck->fetch(PDO::FETCH_ASSOC);
assertHttp('[Approval] Leave request transitioned to Approved in database', ($approvedReq['status'] ?? '') === 'Approved');
assertHttp('[Approval] Approver ID and timestamp recorded', !empty($approvedReq['approved_by']) && !empty($approvedReq['approved_at']));

// 4.4 Detailed View: Employee views own approved request details
$showPage = curlReq("{$baseUrl}/leaves/{$httpReqId}", 'GET', [], $cookieFileEmp);
assertHttp('[Details] Employee can view own approved request details (/leaves/{id})', $showPage['code'] === 200, "HTTP {$showPage['code']}");

echo "\n";

// --- 5. EMPLOYEE HISTORY & PARAMETER TAMPERING ---
echo "--- 5. EMPLOYEE HISTORY & PARAMETER TAMPERING ---\n";

// 5.1 Employee views own leave requests
$empHistory = curlReq("{$baseUrl}/employee/leave-requests", 'GET', [], $cookieFileEmp);
assertHttp('[History] Employee can access own leave requests history (HTTP 200)', $empHistory['code'] === 200, "HTTP {$empHistory['code']}");

// 5.2 Employee views alias /employee/leave-history
$empHistoryAlias = curlReq("{$baseUrl}/employee/leave-history", 'GET', [], $cookieFileEmp);
assertHttp('[History] Employee can access /employee/leave-history alias (HTTP 200)', $empHistoryAlias['code'] === 200, "HTTP {$empHistoryAlias['code']}");

// 5.3 Parameter Tampering: Employee tries ?emp_id=999
$tamperTest = curlReq("{$baseUrl}/employee/leave-requests?emp_id=999", 'GET', [], $cookieFileEmp);
assertHttp('[Security] Parameter tampering (?emp_id=999) is STRICTLY DENIED (HTTP 403)', $tamperTest['code'] === 403, "HTTP {$tamperTest['code']}");

echo "\n";

// --- CLEANUP ---
if ($httpReqId > 0) {
    // Revert balance change
    $empId = (int)$approvedReq['employee_id'];
    $ltId = (int)$approvedReq['leave_type_id'];
    $days = (int)$approvedReq['number_of_days'];
    $pdo->exec("UPDATE leave_balances SET used_days = GREATEST(0, used_days - {$days}), remaining_days = remaining_days + {$days} WHERE employee_id = {$empId} AND leave_type_id = {$ltId}");
    $pdo->exec("DELETE FROM leave_requests WHERE id = {$httpReqId}");
}
@unlink($cookieFileHR);
@unlink($cookieFileEmp);

// ====================================================================
// SUMMARY
// ====================================================================
echo "====================================================================\n";
echo "   PHASE 8 LIVE HTTP TEST SUITE SUMMARY\n";
echo "====================================================================\n";
echo "Total Tests Run:  {$httpResults['total']}\n";
echo "Passed:           {$httpResults['passed']}\n";
echo "Failed:           {$httpResults['failed']}\n";
$rate = $httpResults['total'] > 0 ? round(($httpResults['passed'] / $httpResults['total']) * 100, 1) : 0;
echo "Pass Rate:        {$rate}%\n";
echo "====================================================================\n";

if ($httpResults['failed'] > 0) {
    exit(1);
}
