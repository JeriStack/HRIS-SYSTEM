<?php
/**
 * Phase 8 Complete Leave Management Automated Test Suite
 * Comprehensive verification of Leave Types, Calculation, Filing, Overlap Detection,
 * Approval, Rejection, Cancellation, Balance Updates, RBAC Security, and End-to-End Workflow.
 *
 * Employee HRIS & Leave Management System
 */

require_once __DIR__ . '/../core/bootstrap.php';

use Core\Database;
use App\Models\Leave;
use App\Models\Employee;
use App\Models\User;
use App\Models\Notification;
use App\Models\ActivityLog;
use App\Services\LeaveCalculator;

$pdo = Database::getInstance()->getConnection();
$leaveModel = new Leave();
$employeeModel = new Employee();
$userModel = new User();

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assertTest(string $description, bool $condition, string $detail = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "  [PASS] {$description}\n";
    } else {
        $failedTests++;
        echo "  [FAIL] {$description}" . ($detail ? " - {$detail}" : '') . "\n";
    }
}

echo "====================================================================\n";
echo "   PHASE 8 LEAVE MANAGEMENT COMPREHENSIVE AUTOMATED TEST SUITE\n";
echo "====================================================================\n\n";

// --- HARNESS SETUP ---
// 1. Find an HR user
$hrUser = $userModel->findByUsername('hr_user') ?: $userModel->findByUsername('admin');
if (!$hrUser) {
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'hr' LIMIT 1");
    $hrUser = $stmt->fetch(PDO::FETCH_ASSOC);
}
$hrUserId = (int)$hrUser['id'];

// 2. Find an active Employee
$stmtEmp = $pdo->query("
    SELECT e.*, u.id as user_id, u.username 
    FROM employees e
    INNER JOIN users u ON u.employee_id = e.id
    WHERE e.employment_status = 'Active' AND u.role = 'employee'
    LIMIT 1
");
$testEmployee = $stmtEmp->fetch(PDO::FETCH_ASSOC);
if (!$testEmployee) {
    echo "ERROR: Active test employee user not found.\n";
    exit(1);
}
$testEmpId = (int)$testEmployee['id'];
$testUserId = (int)$testEmployee['user_id'];

// 3. Find another distinct employee for cross-ownership testing
$stmtEmp2 = $pdo->prepare("
    SELECT e.*, u.id as user_id, u.username 
    FROM employees e
    INNER JOIN users u ON u.employee_id = e.id
    WHERE e.employment_status = 'Active' AND u.role = 'employee' AND e.id != :id
    LIMIT 1
");
$stmtEmp2->execute(['id' => $testEmpId]);
$otherEmployee = $stmtEmp2->fetch(PDO::FETCH_ASSOC);
$otherEmpId = (int)($otherEmployee['id'] ?? 0);
$otherUserId = (int)($otherEmployee['user_id'] ?? 0);

// Cleanup any lingering artifacts from previous aborted test runs in proper foreign-key hierarchy
$pdo->exec("DELETE FROM leave_requests WHERE reason LIKE 'PHASE8_TEST_%'");
$pdo->exec("DELETE FROM leave_balances WHERE leave_type_id IN (SELECT id FROM leave_types WHERE leave_name LIKE 'P8_TEST_%')");
$pdo->exec("DELETE FROM leave_types WHERE leave_name LIKE 'P8_TEST_%'");

// ====================================================================
// GROUP A: LEAVE TYPE MANAGEMENT TESTS
// ====================================================================
echo "--- GROUP A: LEAVE TYPE MANAGEMENT TESTS ---\n";

// A1: Create valid leave type
$testTypeName = 'P8_TEST_Sabbatical_' . time();
$createdTypeId = $leaveModel->createLeaveType([
    'leave_name'   => $testTypeName,
    'description'  => 'Extended professional research leave',
    'default_days' => 20,
    'status'       => 'Active',
]);
assertTest('[LeaveType] Create valid leave classification record', $createdTypeId > 0);

// A2: Retrieve created leave type by ID
$fetchedType = $leaveModel->getLeaveTypeById($createdTypeId);
assertTest('[LeaveType] Retrieve leave classification by ID', $fetchedType !== null && $fetchedType['leave_name'] === $testTypeName);
assertTest('[LeaveType] Initial status is Active', ($fetchedType['status'] ?? '') === 'Active');
assertTest('[LeaveType] Default annual allowance matches input', (int)($fetchedType['default_days'] ?? 0) === 20);

// A3: Enforce Unique Name
$isUnique = $leaveModel->isLeaveTypeNameUnique($testTypeName);
assertTest('[LeaveType] Reject duplicate classification name', $isUnique === false);

// A4: Allow same name when excluding current ID (Update self)
$isUniqueSelf = $leaveModel->isLeaveTypeNameUnique($testTypeName, $createdTypeId);
assertTest('[LeaveType] Name uniqueness check excludes self on update', $isUniqueSelf === true);

// A5: Update leave classification
$updateOk = $leaveModel->updateLeaveType($createdTypeId, [
    'leave_name'   => $testTypeName . '_Renamed',
    'description'  => 'Updated description',
    'default_days' => 25,
    'status'       => 'Active',
]);
$updatedType = $leaveModel->getLeaveTypeById($createdTypeId);
assertTest('[LeaveType] Update leave classification details', $updateOk && $updatedType['leave_name'] === $testTypeName . '_Renamed' && (int)$updatedType['default_days'] === 25);

// A6: Soft Toggle Status (Deactivate / Activate)
$toggleOk = $leaveModel->toggleLeaveTypeStatus($createdTypeId);
$deactivatedType = $leaveModel->getLeaveTypeById($createdTypeId);
assertTest('[LeaveType] Toggle status deactivates active leave type', $toggleOk && $deactivatedType['status'] === 'Inactive');

// A7: Inactive leave types excluded from active employee list
$activeTypes = $leaveModel->getLeaveTypes();
$foundInActive = false;
foreach ($activeTypes as $at) {
    if ((int)$at['id'] === $createdTypeId) {
        $foundInActive = true;
        break;
    }
}
assertTest('[LeaveType] Inactive leave types are excluded from employee filing list', $foundInActive === false);

// Reactivate for further testing
$leaveModel->toggleLeaveTypeStatus($createdTypeId);
$reactivatedType = $leaveModel->getLeaveTypeById($createdTypeId);
assertTest('[LeaveType] Toggle status reactivates inactive leave type', $reactivatedType['status'] === 'Active');

echo "\n";

// ====================================================================
// GROUP B: LEAVE-DAY CALCULATION TESTS
// ====================================================================
echo "--- GROUP B: LEAVE-DAY CALCULATION TESTS ---\n";

// B1: Same-day valid request (Monday)
$resB1 = LeaveCalculator::validateDateRange('2026-10-12', '2026-10-12');
assertTest('[LeaveCalculator] Same-day valid request (Monday) = 1 working day', $resB1['valid'] && $resB1['working_days'] === 1);

// B2: Full Monday to Friday week
$resB2 = LeaveCalculator::validateDateRange('2026-10-12', '2026-10-16');
assertTest('[LeaveCalculator] Full standard week (Mon to Fri) = 5 working days', $resB2['valid'] && $resB2['working_days'] === 5);

// B3: Friday to Monday range (Fri, Sat, Sun, Mon)
$resB3 = LeaveCalculator::validateDateRange('2026-10-16', '2026-10-19');
assertTest('[LeaveCalculator] Friday to Monday range = 2 working days (weekends excluded)', $resB3['valid'] && $resB3['working_days'] === 2);

// B4: Weekend-only range (Saturday to Sunday)
$resB4 = LeaveCalculator::validateDateRange('2026-10-17', '2026-10-18');
assertTest('[LeaveCalculator] Weekend-only range = 0 working days', $resB4['valid'] && $resB4['working_days'] === 0 && $resB4['total_days'] === 2);

// B5: End date before start date
$resB5 = LeaveCalculator::validateDateRange('2026-10-20', '2026-10-15');
assertTest('[LeaveCalculator] Reject chronological violation (End date before Start date)', !$resB5['valid'] && str_contains($resB5['error'] ?? '', 'cannot precede'));

// B6: Invalid date format
$resB6 = LeaveCalculator::validateDateRange('2026-02-30', '2026-03-01');
assertTest('[LeaveCalculator] Reject invalid calendar date (2026-02-30)', !$resB6['valid']);

// B7: Month & Year boundary (Dec 31 to Jan 2)
$resB7 = LeaveCalculator::validateDateRange('2025-12-31', '2026-01-02');
assertTest('[LeaveCalculator] Handle month and year transition boundary', $resB7['valid'] && $resB7['working_days'] === 3);

// B8: Leap year calculation (Feb 28 to Mar 1 on 2024 leap year)
$resB8 = LeaveCalculator::validateDateRange('2024-02-28', '2024-03-01');
assertTest('[LeaveCalculator] Handle leap year 29-day February dates accurately', $resB8['valid'] && $resB8['working_days'] === 3);

echo "\n";

// ====================================================================
// GROUP C: LEAVE FILING & OVERLAP DETECTION TESTS
// ====================================================================
echo "--- GROUP C: LEAVE FILING & OVERLAP DETECTION TESTS ---\n";

// Set up balance quota for test employee on test leave type
$testYear = (int)date('Y');
$leaveModel->adjustBalance($testEmpId, $createdTypeId, $testYear, 25);
$initialBal = $leaveModel->getBalance($testEmpId, $createdTypeId, $testYear);
assertTest('[Balance] Initialized balance of 25 days for test employee', $initialBal !== null && (int)$initialBal['remaining_days'] === 25);

// Use unseeded December 2026 window to test cleanly:
// Window 1: 2026-12-07 (Mon) to 2026-12-09 (Wed) = 3 days
$req1Start = '2026-12-07';
$req1End   = '2026-12-09';
$days1 = LeaveCalculator::calculateWorkingDays($req1Start, $req1End); // 3

$req1Id = $leaveModel->createRequest([
    'employee_id'    => $testEmpId,
    'leave_type_id'  => $createdTypeId,
    'start_date'     => $req1Start,
    'end_date'       => $req1End,
    'number_of_days' => $days1,
    'reason'         => 'PHASE8_TEST_Valid leave request filing',
]);
assertTest('[Filing] Submit valid leave request with calculated days', $req1Id > 0);

$req1Record = $leaveModel->getRequestById($req1Id);
assertTest('[Filing] Authoritative initial status is Pending', ($req1Record['status'] ?? '') === 'Pending');
assertTest('[Filing] Stored number_of_days matches server calculation (3)', (int)$req1Record['number_of_days'] === 3);

// C2: Balance before approval is unchanged (used_days = 0, remaining_days = 25)
$balAfterFiling = $leaveModel->getBalance($testEmpId, $createdTypeId, $testYear);
assertTest('[Filing] Filing a request does NOT deduct used_days prior to approval', (int)$balAfterFiling['used_days'] === 0 && (int)$balAfterFiling['remaining_days'] === 25);

// C3: Overlap Detection: Exact duplicate dates
$hasOverlapExact = $leaveModel->hasOverlap($testEmpId, $req1Start, $req1End);
assertTest('[Overlap] Detect exact duplicate date overlap against Pending request', $hasOverlapExact === true);

// C4: Overlap Detection: Partial overlap (starts during req1: 2026-12-08 to 2026-12-10)
$hasOverlapPartial = $leaveModel->hasOverlap($testEmpId, '2026-12-08', '2026-12-10');
assertTest('[Overlap] Detect partial start overlap (new_start <= existing_end)', $hasOverlapPartial === true);

// C5: Overlap Detection: Enclosing range (2026-12-06 to 2026-12-10)
$hasOverlapEnclosing = $leaveModel->hasOverlap($testEmpId, '2026-12-06', '2026-12-10');
assertTest('[Overlap] Detect enclosing date range overlap', $hasOverlapEnclosing === true);

// C6: Overlap Detection: Non-overlapping request (2026-12-14 to 2026-12-16)
$hasOverlapNone = $leaveModel->hasOverlap($testEmpId, '2026-12-14', '2026-12-16');
assertTest('[Overlap] Allow non-overlapping date range', $hasOverlapNone === false);

// C7: Overlap check excludes self when updating/re-checking
$hasOverlapSelf = $leaveModel->hasOverlap($testEmpId, $req1Start, $req1End, $req1Id);
assertTest('[Overlap] Overlap detection excludes current request ID on self-check', $hasOverlapSelf === false);

// C8: Different employee does NOT conflict with test employee's dates
if ($otherEmpId > 0) {
    // Verify otherEmpId has no requests in this December 2026 range
    $hasOverlapOther = $leaveModel->hasOverlap($otherEmpId, $req1Start, $req1End);
    assertTest('[Overlap] Overlap detection is strictly scoped to the same employee', $hasOverlapOther === false);
}

echo "\n";

// ====================================================================
// GROUP D: APPROVAL & BALANCE UPDATE TESTS
// ====================================================================
echo "--- GROUP D: APPROVAL & BALANCE UPDATE TESTS ---\n";

// D1: Authorized HR Approves Pending request #req1Id
$approveOk = $leaveModel->updateRequestStatus($req1Id, 'Approved', $hrUserId);
assertTest('[Approval] HR approves valid pending leave request', $approveOk === true);

$req1Approved = $leaveModel->getRequestById($req1Id);
assertTest('[Approval] Request status transitioned to Approved', ($req1Approved['status'] ?? '') === 'Approved');
assertTest('[Approval] Approver ID and timestamp recorded', (int)$req1Approved['approved_by'] === $hrUserId && !empty($req1Approved['approved_at']));

// D2: Balance is updated correctly: used_days = 3, remaining_days = 25 - 3 = 22
$balAfterApproval = $leaveModel->getBalance($testEmpId, $createdTypeId, $testYear);
assertTest('[Balance] Approval updates used_days correctly (+3)', (int)$balAfterApproval['used_days'] === 3);
assertTest('[Balance] Approval updates remaining_days correctly (22)', (int)$balAfterApproval['remaining_days'] === 22);

// D3: Prevent duplicate approval / terminal state overwrite
$reApprove = $leaveModel->updateRequestStatus($req1Id, 'Approved', $hrUserId);
assertTest('[Approval] Prevent repeated approval of already approved request', $reApprove === false);

// Balance must still be 3 used, 22 remaining (NO duplicate deduction)
$balAfterReApprove = $leaveModel->getBalance($testEmpId, $createdTypeId, $testYear);
assertTest('[Balance] Repeated approval attempt does NOT duplicate balance deduction', (int)$balAfterReApprove['used_days'] === 3 && (int)$balAfterReApprove['remaining_days'] === 22);

// D4: Overlap Detection against Approved request
$hasOverlapApproved = $leaveModel->hasOverlap($testEmpId, $req1Start, $req1End);
assertTest('[Overlap] Overlapping approved requests are detected and blocked', $hasOverlapApproved === true);

echo "\n";

// ====================================================================
// GROUP E: REJECTION TESTS
// ====================================================================
echo "--- GROUP E: REJECTION TESTS ---\n";

// Create a second request to test rejection (2026-12-14 to 2026-12-15 = 2 days)
$req2Start = '2026-12-14';
$req2End   = '2026-12-15';
$days2 = LeaveCalculator::calculateWorkingDays($req2Start, $req2End); // 2

$req2Id = $leaveModel->createRequest([
    'employee_id'    => $testEmpId,
    'leave_type_id'  => $createdTypeId,
    'start_date'     => $req2Start,
    'end_date'       => $req2End,
    'number_of_days' => $days2,
    'reason'         => 'PHASE8_TEST_Request to be rejected',
]);
assertTest('[Rejection] Create second request for rejection test', $req2Id > 0);

// Reject with reason
$rejectionReason = 'Critical operational deployment scheduled during this window.';
$rejectOk = $leaveModel->updateRequestStatus($req2Id, 'Rejected', $hrUserId, $rejectionReason);
assertTest('[Rejection] HR rejects pending request with reason', $rejectOk === true);

$req2Record = $leaveModel->getRequestById($req2Id);
assertTest('[Rejection] Status transitioned to Rejected', ($req2Record['status'] ?? '') === 'Rejected');
assertTest('[Rejection] Rejection reason is persisted in database', ($req2Record['rejection_reason'] ?? '') === $rejectionReason);

// E2: Rejection must NOT deduct balance
$balAfterReject = $leaveModel->getBalance($testEmpId, $createdTypeId, $testYear);
assertTest('[Balance] Rejection does NOT consume leave balance entitlement (remains 3 used, 22 remaining)', (int)$balAfterReject['used_days'] === 3 && (int)$balAfterReject['remaining_days'] === 22);

// E3: Rejected request must NOT block new filing for the same dates
$hasOverlapRejected = $leaveModel->hasOverlap($testEmpId, $req2Start, $req2End);
assertTest('[Overlap] Rejected request does NOT block new valid filing for the same dates', $hasOverlapRejected === false);

echo "\n";

// ====================================================================
// GROUP F: CANCELLATION TESTS
// ====================================================================
echo "--- GROUP F: CANCELLATION TESTS ---\n";

// Create a third request for cancellation testing (2026-12-21 to 2026-12-22 = 2 days)
$req3Start = '2026-12-21';
$req3End   = '2026-12-22';
$days3 = LeaveCalculator::calculateWorkingDays($req3Start, $req3End); // 2

$req3Id = $leaveModel->createRequest([
    'employee_id'    => $testEmpId,
    'leave_type_id'  => $createdTypeId,
    'start_date'     => $req3Start,
    'end_date'       => $req3End,
    'number_of_days' => $days3,
    'reason'         => 'PHASE8_TEST_Request to be cancelled by employee',
]);
assertTest('[Cancellation] Create third request for cancellation testing', $req3Id > 0);

// F1: Employee cancels their OWN pending request
$cancelOk = $leaveModel->cancelRequest($req3Id, $testEmpId);
assertTest('[Cancellation] Employee successfully cancels own pending request', $cancelOk === true);

$req3Record = $leaveModel->getRequestById($req3Id);
assertTest('[Cancellation] Status transitioned to Cancelled', ($req3Record['status'] ?? '') === 'Cancelled');

// F2: Cancelled request remains in database history (NOT deleted)
assertTest('[Cancellation] Cancelled request is preserved in historical records', $req3Record !== null);

// F3: Cancellation does NOT deduct or refund unconsumed balance
$balAfterCancel = $leaveModel->getBalance($testEmpId, $createdTypeId, $testYear);
assertTest('[Balance] Cancellation of pending request maintains balance integrity (3 used, 22 remaining)', (int)$balAfterCancel['used_days'] === 3 && (int)$balAfterCancel['remaining_days'] === 22);

// F4: Cancelled request does NOT block future filings for those dates
$hasOverlapCancelled = $leaveModel->hasOverlap($testEmpId, $req3Start, $req3End);
assertTest('[Overlap] Cancelled request does NOT block future filings for the same dates', $hasOverlapCancelled === false);

// F5: Another employee CANNOT cancel this request
$req4Id = $leaveModel->createRequest([
    'employee_id'    => $testEmpId,
    'leave_type_id'  => $createdTypeId,
    'start_date'     => '2026-12-28',
    'end_date'       => '2026-12-29',
    'number_of_days' => 2,
    'reason'         => 'PHASE8_TEST_Request to test cross-employee cancel denial',
]);
if ($otherEmpId > 0) {
    $cancelOther = $leaveModel->cancelRequest($req4Id, $otherEmpId);
    assertTest('[Security] Cross-employee cancellation attempt is strictly rejected', $cancelOther === false);
    $req4Check = $leaveModel->getRequestById($req4Id);
    assertTest('[Security] Request remains in Pending state after cross-cancellation attempt', ($req4Check['status'] ?? '') === 'Pending');
}

// F6: Cannot cancel already Approved request
$cancelApproved = $leaveModel->cancelRequest($req1Id, $testEmpId);
assertTest('[Cancellation] Cannot cancel an already Approved leave request', $cancelApproved === false);

echo "\n";

// ====================================================================
// GROUP G: LEAVE HISTORY & QUERY FILTERING TESTS
// ====================================================================
echo "--- GROUP G: LEAVE HISTORY & QUERY FILTERING TESTS ---\n";

// G1: Retrieve all requests for employee
$empRequests = $leaveModel->getAllRequests(null, $testEmpId);
assertTest('[History] Retrieve employee personal request history', count($empRequests) >= 3);

// Verify all returned records belong to test employee
$allBelong = true;
foreach ($empRequests as $r) {
    if ((int)$r['employee_id'] !== $testEmpId) {
        $allBelong = false;
        break;
    }
}
assertTest('[History] Employee history strictly isolated to own employee_id', $allBelong === true);

// G2: Filter requests by status: Approved
$approvedReqs = $leaveModel->getAllRequests('Approved', $testEmpId);
$allApproved = true;
foreach ($approvedReqs as $r) {
    if ($r['status'] !== 'Approved') {
        $allApproved = false;
        break;
    }
}
assertTest('[History] Filter history by Approved status', count($approvedReqs) >= 1 && $allApproved === true);

// G3: Filter requests by status: Rejected
$rejectedReqs = $leaveModel->getAllRequests('Rejected', $testEmpId);
assertTest('[History] Filter history by Rejected status', count($rejectedReqs) >= 1);

// G4: Filter requests by status: Cancelled
$cancelledReqs = $leaveModel->getAllRequests('Cancelled', $testEmpId);
assertTest('[History] Filter history by Cancelled status', count($cancelledReqs) >= 1);

echo "\n";

// ====================================================================
// GROUP H: NOTIFICATION & ACTIVITY AUDIT INTEGRATION
// ====================================================================
echo "--- GROUP H: NOTIFICATION & ACTIVITY AUDIT INTEGRATION ---\n";

$notifModel = new Notification();
$activityLog = new ActivityLog();

// H1: Create in-system notification for employee
$notifId = $notifModel->create(
    $testUserId,
    'Leave Request Approved',
    "Your leave request #LR-{$req1Id} has been approved by HR.",
    'success'
);
assertTest('[Notification] Create in-system notification for leave decision event', $notifId > 0);

// H2: Employee can retrieve own notification
$userNotifs = $notifModel->getByUser($testUserId);
$foundNotif = false;
foreach ($userNotifs as $n) {
    if ((int)$n['id'] === $notifId) {
        $foundNotif = true;
        break;
    }
}
assertTest('[Notification] Notification appears in recipient employee notifications list', $foundNotif === true);

// H3: Activity log audit entry
$logId = $activityLog->log(
    $hrUserId,
    'LEAVE_APPROVED',
    "Approved leave request #{$req1Id} for employee #{$testEmpId}",
    'LeaveRequest',
    (string)$req1Id
);
assertTest('[ActivityLog] Consequential leave approval action logged to immutable audit trail', $logId > 0);

echo "\n";

// ====================================================================
// CLEANUP TEST HARNESS ARTIFACTS
// ====================================================================
$pdo->exec("DELETE FROM notifications WHERE id = {$notifId}");
$pdo->exec("DELETE FROM activity_logs WHERE id = {$logId}");
$pdo->exec("DELETE FROM leave_requests WHERE reason LIKE 'PHASE8_TEST_%'");
$pdo->exec("DELETE FROM leave_balances WHERE leave_type_id = {$createdTypeId}");
$pdo->exec("DELETE FROM leave_types WHERE id = {$createdTypeId}");

// ====================================================================
// FINAL SUMMARY
// ====================================================================
echo "====================================================================\n";
echo "   PHASE 8 AUTOMATED TEST SUITE SUMMARY\n";
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
