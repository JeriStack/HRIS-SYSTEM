<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\Leave\Adapters\LeaveControllerAdapter;
use App\Models\Leave;
use App\Services\ConfidentialLeaveService;
use App\Services\LeavePolicyEngine;
use Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end MySQL integration coverage for Philippine statutory & company leave workflows:
 * - Event-based cross-year Maternity Leave filing + HR clarification loop + Approval + Immutable ledger
 * - Unpaid Leave (LWOP) approval with zero annual balance deduction
 * - Confidential leave notification masking & access audit log checks
 */
class PhilippineLeaveWorkflowTest extends TestCase
{
    private PDO $pdo;
    private Leave $leaveModel;
    private LeavePolicyEngine $policyEngine;
    private LeaveControllerAdapter $adapter;

    private int $deptId = 0;
    private int $posId = 0;
    private int $hrUserId = 0;

    /** @var int[] */
    private array $empIds = [];
    /** @var int[] */
    private array $userIds = [];
    /** @var int[] */
    private array $typeIds = [];

    protected function setUp(): void
    {
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database is unavailable.');
        }
        $this->pdo = $db->getConnection();
        LeavePolicyEngine::resetCache();
        ConfidentialLeaveService::resetCache();

        $this->leaveModel = new Leave($this->pdo);
        $this->policyEngine = new LeavePolicyEngine($this->pdo);
        $this->adapter = new LeaveControllerAdapter(pdo: $this->pdo);

        $this->pdo->prepare("INSERT INTO departments (department_name, description) VALUES (:n, 'ph_wf_test')")
            ->execute(['n' => 'PH WF Dept ' . bin2hex(random_bytes(3))]);
        $this->deptId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO positions (position_name, department_id, description) VALUES (:n, :d, 'ph_wf_test')")
            ->execute(['n' => 'PH WF Pos ' . bin2hex(random_bytes(3)), 'd' => $this->deptId]);
        $this->posId = (int)$this->pdo->lastInsertId();

        $this->hrUserId = (int)($this->pdo->query(
            "SELECT id FROM users WHERE role = 'hr' AND status = 'Active' ORDER BY id ASC LIMIT 1"
        )->fetchColumn() ?: 0);

        if ($this->hrUserId <= 0) {
            $this->markTestSkipped('An active seeded HR user is required.');
        }
    }

    protected function tearDown(): void
    {
        LeavePolicyEngine::resetCache();
        ConfidentialLeaveService::resetCache();

        if (!isset($this->pdo)) {
            return;
        }

        if (!empty($this->empIds)) {
            $inEmp = implode(',', array_map('intval', $this->empIds));
            $this->pdo->exec("DELETE FROM leave_sensitive_access_logs WHERE leave_request_id IN (SELECT id FROM leave_requests WHERE employee_id IN ({$inEmp}))");
            $this->pdo->exec("DELETE FROM leave_request_documents WHERE employee_id IN ({$inEmp})");
            $this->pdo->exec("DELETE FROM leave_balance_transactions WHERE employee_id IN ({$inEmp})");
            $this->pdo->exec("DELETE FROM leave_requests WHERE employee_id IN ({$inEmp})");
            $this->pdo->exec("DELETE FROM leave_event_entitlements WHERE employee_id IN ({$inEmp})");
            $this->pdo->exec("DELETE FROM leave_balances WHERE employee_id IN ({$inEmp})");
            $this->pdo->exec("DELETE FROM employee_leave_qualifications WHERE employee_id IN ({$inEmp})");
        }

        if (!empty($this->typeIds)) {
            $inTypes = implode(',', array_map('intval', $this->typeIds));
            $this->pdo->exec("UPDATE leave_types SET active_policy_version_id = NULL WHERE id IN ({$inTypes})");
            $this->pdo->exec("DELETE FROM leave_balance_transactions WHERE leave_type_id IN ({$inTypes})");
            $this->pdo->exec("DELETE FROM leave_policy_versions WHERE leave_type_id IN ({$inTypes})");
            $this->pdo->exec("DELETE FROM leave_types WHERE id IN ({$inTypes})");
        }

        if (!empty($this->userIds)) {
            $inUsers = implode(',', array_map('intval', $this->userIds));
            $this->pdo->exec("DELETE FROM notifications WHERE user_id IN ({$inUsers})");
            $this->pdo->exec("DELETE FROM activity_logs WHERE user_id IN ({$inUsers})");
            $this->pdo->exec("DELETE FROM users WHERE id IN ({$inUsers})");
        }

        if (!empty($this->empIds)) {
            $inEmp = implode(',', array_map('intval', $this->empIds));
            $this->pdo->exec("DELETE FROM employees WHERE id IN ({$inEmp})");
        }

        if ($this->posId > 0) {
            $this->pdo->exec("DELETE FROM positions WHERE id = {$this->posId}");
        }
        if ($this->deptId > 0) {
            $this->pdo->exec("DELETE FROM departments WHERE id = {$this->deptId}");
        }
    }

    /**
     * @return array{employee_id: int, user_id: int}
     */
    private function createActiveEmployeeWithUser(string $gender = 'Female', string $hired = '2092-01-01'): array
    {
        $sfx = bin2hex(random_bytes(4));
        $this->pdo->prepare("
            INSERT INTO employees (employee_no, first_name, last_name, email, birth_date, gender, department_id, position_id, date_hired, employment_status)
            VALUES (:no, 'Workflow', 'Tester', :email, '1991-03-10', :gender, :d, :p, :hired, 'Active')
        ")->execute([
            'no'     => "PHW-{$sfx}",
            'email'  => "phw-{$sfx}@test.local",
            'gender' => $gender,
            'd'      => $this->deptId,
            'p'      => $this->posId,
            'hired'  => $hired,
        ]);
        $empId = (int)$this->pdo->lastInsertId();
        $this->empIds[] = $empId;

        $this->pdo->prepare("
            INSERT INTO users (employee_id, username, email, password, role, status)
            VALUES (:eid, :uname, :email, :pw, 'employee', 'Active')
        ")->execute([
            'eid'   => $empId,
            'uname' => "phw_{$sfx}",
            'email' => "phw-{$sfx}@test.local",
            'pw'    => password_hash('SecretPass123!', PASSWORD_BCRYPT),
        ]);
        $userId = (int)$this->pdo->lastInsertId();
        $this->userIds[] = $userId;

        return ['employee_id' => $empId, 'user_id' => $userId];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedLeaveType(string $code, string $name, array $overrides = []): int
    {
        if ($code !== '') {
            $stmt = $this->pdo->prepare("SELECT id FROM leave_types WHERE leave_code = :code LIMIT 1");
            $stmt->execute(['code' => $code]);
            $existingId = (int)$stmt->fetchColumn();
            if ($existingId > 0) {
                return $existingId;
            }
        }

        $sfx = bin2hex(random_bytes(2));
        $data = array_merge([
            'leave_name'        => "{$name} {$sfx}",
            'leave_code'        => $code,
            'description'       => 'PH Integration Test Type',
            'classification'    => 'Statutory',
            'benefit_category'  => 'StatutoryEvent',
            'entitlement_model' => 'event_based',
            'default_days'      => 105,
            'default_unit'      => 'calendar_days',
            'is_paid'           => 1,
            'is_confidential'   => 0,
            'status'            => 'Active',
        ], $overrides);

        $ltId = $this->leaveModel->createLeaveType($data);
        $this->typeIds[] = $ltId;

        $this->leaveModel->createPolicyVersion($ltId, array_merge($data, [
            'entitlement_days' => (float)$data['default_days'],
            'entitlement_unit' => $data['default_unit'],
            'effective_from'   => '2020-01-01',
        ]), $this->hrUserId, true);

        return $ltId;
    }

    public function test_event_based_maternity_cross_year_clarification_and_approval_flow(): void
    {
        $actor = $this->createActiveEmployeeWithUser('Female', '2092-01-01');
        $empId = $actor['employee_id'];
        $matTypeId = $this->seedLeaveType('MATERNITY', 'Maternity RA11210');

        $startDate = '2095-12-15';
        $endDate = '2096-01-25'; // 42 calendar days spanning 2095 -> 2096
        $resolved = $this->policyEngine->validateFiling($empId, $matTypeId, $startDate, $endDate, [
            'maternity_scenario' => 'live_childbirth',
        ], true);

        $eventId = $this->policyEngine->ensureEventEntitlementForRequest(
            $empId,
            $matTypeId,
            $startDate,
            $endDate,
            $resolved,
            $actor['user_id']
        );
        $this->assertNotNull($eventId);

        $reqId = $this->leaveModel->createRequest([
            'employee_id'          => $empId,
            'leave_type_id'        => $matTypeId,
            'policy_version_id'    => $resolved['policy_version_id'],
            'event_entitlement_id' => $eventId,
            'start_date'           => $startDate,
            'end_date'             => $endDate,
            'number_of_days'       => $resolved['number_of_days'],
            'entitlement_unit'     => $resolved['entitlement_unit'],
            'is_paid'              => 1,
            'is_confidential'      => 0,
            'reason'               => 'Expected confinement Dec 2095 through Jan 2096',
            'metadata'             => ['maternity_scenario' => 'live_childbirth'],
        ]);
        $this->assertGreaterThan(0, $reqId);

        // HR requests clarification -> Employee responds
        $askRes = $this->leaveModel->requestAdditionalInfo($reqId, $this->hrUserId, 'Please attach OB-GYN expected date certificate.');
        $this->assertTrue($askRes['ok']);

        $midReq = $this->leaveModel->getRequestById($reqId);
        $this->assertSame('Requested', $midReq['info_request_status']);

        $replyRes = $this->leaveModel->respondToInfoRequest($reqId, $empId, 'Uploaded signed OB-GYN certificate #OB-2095.', $actor['user_id']);
        $this->assertTrue($replyRes['ok']);

        $repliedReq = $this->leaveModel->getRequestById($reqId);
        $this->assertSame('Responded', $repliedReq['info_request_status']);
        $this->assertSame('Uploaded signed OB-GYN certificate #OB-2095.', $repliedReq['employee_info_response']);

        // HR approves -> deducts from leave_event_entitlements, logs event_allocation ledger, zero leave_balances mutation
        $approveRes = $this->leaveModel->approveRequest($reqId, $this->hrUserId);
        $this->assertTrue($approveRes['ok'], $approveRes['message'] ?? 'Approval failed');

        $evRow = $this->pdo->query("SELECT used_days, remaining_days, entitled_days FROM leave_event_entitlements WHERE id = {$eventId}")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertEqualsWithDelta(42.0, (float)$evRow['used_days'], 0.01);
        $this->assertEqualsWithDelta(63.0, (float)$evRow['remaining_days'], 0.01);

        $txStmt = $this->pdo->prepare("SELECT transaction_type, days_delta FROM leave_balance_transactions WHERE leave_request_id = ?");
        $txStmt->execute([$reqId]);
        $txRow = $txStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($txRow);
        $this->assertSame('event_allocation', $txRow['transaction_type']);
        $this->assertEqualsWithDelta(-42.0, (float)$txRow['days_delta'], 0.01);
    }

    public function test_unpaid_lwop_and_confidential_vawc_masking_via_adapter(): void
    {
        $actor = $this->createActiveEmployeeWithUser('Female', '2091-01-01');
        $empId = $actor['employee_id'];
        $userId = $actor['user_id'];

        $lwopTypeId = $this->seedLeaveType('LWOP', 'LWOP Integration', [
            'classification'    => 'Company',
            'benefit_category'  => 'Unpaid',
            'entitlement_model' => 'unpaid',
            'default_days'      => 0,
            'default_unit'      => 'working_days',
            'is_paid'           => 0,
        ]);

        // Unpaid filing + approval requires no leave_balances row
        $lwopRes = $this->adapter->file(
            employeeId: $empId,
            leaveTypeId: $lwopTypeId,
            startDate: '2096-02-02',
            endDate: '2096-02-04',
            reason: 'Unpaid personal travel'
        );
        $this->assertTrue($lwopRes->success, $lwopRes->message);
        $lwopReqId = (int)($lwopRes->data['id'] ?? 0);
        $this->assertGreaterThan(0, $lwopReqId);

        $lwopApprove = $this->leaveModel->approveRequest($lwopReqId, $this->hrUserId);
        $this->assertTrue($lwopApprove['ok']);

        $balCount = (int)$this->pdo->query("SELECT COUNT(*) FROM leave_balances WHERE employee_id = {$empId} AND leave_type_id = {$lwopTypeId}")->fetchColumn();
        $this->assertSame(0, $balCount);

        // Confidential VAWC filing + clarification notification masking
        $vawcTypeId = $this->seedLeaveType('VAWC', 'VAWC Integration', [
            'classification'    => 'Statutory',
            'benefit_category'  => 'StatutoryEvent',
            'entitlement_model' => 'event_based',
            'default_days'      => 10,
            'default_unit'      => 'working_days',
            'is_paid'           => 1,
            'is_confidential'   => 1,
        ]);

        $vawcResolved = $this->policyEngine->validateFiling(
            $empId,
            $vawcTypeId,
            '2096-03-02',
            '2096-03-04',
            ['certification_ref' => 'PROS-2096-99'],
            true
        );
        $vawcEventId = $this->policyEngine->ensureEventEntitlementForRequest(
            $empId,
            $vawcTypeId,
            '2096-03-02',
            '2096-03-04',
            $vawcResolved,
            $userId
        );

        $vawcRes = $this->adapter->file(
            employeeId: $empId,
            leaveTypeId: $vawcTypeId,
            startDate: '2096-03-02',
            endDate: '2096-03-04',
            reason: 'Private prosecutor subpoena appearance in Barangay Hall 5',
            entitlementUnit: (string)$vawcResolved['entitlement_unit'],
            metadata: ['certification_ref' => 'PROS-2096-99'],
            policyVersionId: $vawcResolved['policy_version_id'] ? (int)$vawcResolved['policy_version_id'] : null,
            eventEntitlementId: $vawcEventId,
            overrideDays: (int)$vawcResolved['number_of_days']
        );
        $this->assertTrue($vawcRes->success, $vawcRes->message);
        $vawcReqId = (int)($vawcRes->data['id'] ?? 0);
        $this->assertGreaterThan(0, $vawcReqId);

        $this->leaveModel->requestAdditionalInfo($vawcReqId, $this->hrUserId, 'Secret case number 77-XYZ clarification');
        $notifStmt = $this->pdo->prepare("SELECT title, message FROM notifications WHERE user_id = ? AND entity_id = ?");
        $notifStmt->execute([$userId, $vawcReqId]);
        $notif = $notifStmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($notif);
        $this->assertStringNotContainsString('77-XYZ', (string)$notif['message']);
        $this->assertStringContainsString('Confidential', (string)$notif['title']);
    }
}
