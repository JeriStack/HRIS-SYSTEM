<?php

declare(strict_types=1);

namespace Tests\Unit\Leave;

use App\Controllers\DashboardController;
use App\Controllers\LeaveController;
use App\Models\Leave;
use App\Services\ConfidentialLeaveService;
use App\Services\LeaveLedgerService;
use App\Services\LeavePolicyEngine;
use Core\Database;
use Core\Session;
use DomainException;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive verification of Philippine statutory & company leave rules:
 * SIL (Art. 95), Solo Parent (RA 8972/11861), Maternity (RA 11210), Paternity (RA 8187),
 * Special Leave for Women (RA 9710), VAWC (RA 9262), Unpaid Leave (LWOP),
 * SIL cash conversions (non-payroll reference), and non-retroactive policy versioning.
 */
class PhilippineLeavePolicyTest extends TestCase
{
    private PDO $pdo;
    private LeavePolicyEngine $engine;
    private Leave $leaveModel;
    private LeaveLedgerService $ledger;

    private int $testYear = 2094;
    private int $deptId = 0;
    private int $posId = 0;
    private int $hrUserId = 0;

    /** @var int[] */
    private array $empIds = [];
    /** @var int[] */
    private array $userIds = [];
    /** @var int[] */
    private array $typeIds = [];
    /** @var int[] */
    private array $policyIds = [];

    protected function setUp(): void
    {
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database connection is required for Philippine leave policy checks.');
        }
        $this->pdo = $db->getConnection();
        LeavePolicyEngine::resetCache();
        ConfidentialLeaveService::resetCache();

        $this->engine = new LeavePolicyEngine($this->pdo);
        $this->leaveModel = new Leave($this->pdo);
        $this->ledger = new LeaveLedgerService($this->pdo);

        $this->pdo->prepare("INSERT INTO departments (department_name, description) VALUES (:n, 'ph_policy_test')")
            ->execute(['n' => 'PH Policy Dept ' . bin2hex(random_bytes(3))]);
        $this->deptId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO positions (position_name, department_id, description) VALUES (:n, :d, 'ph_policy_test')")
            ->execute(['n' => 'PH Policy Pos ' . bin2hex(random_bytes(3)), 'd' => $this->deptId]);
        $this->posId = (int)$this->pdo->lastInsertId();

        $this->hrUserId = (int)($this->pdo->query(
            "SELECT id FROM users WHERE role IN ('admin', 'hr') AND status = 'Active' ORDER BY id ASC LIMIT 1"
        )->fetchColumn() ?: 0);
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

    private function createEmployee(string $gender, string $dateHired): int
    {
        $sfx = bin2hex(random_bytes(4));
        $this->pdo->prepare("
            INSERT INTO employees (employee_no, first_name, last_name, email, birth_date, gender, department_id, position_id, date_hired, employment_status)
            VALUES (:no, 'Test', 'Employee', :email, '1992-05-15', :gender, :d, :p, :hired, 'Active')
        ")->execute([
            'no'     => "PH-{$sfx}",
            'email'  => "ph-{$sfx}@test.local",
            'gender' => $gender,
            'd'      => $this->deptId,
            'p'      => $this->posId,
            'hired'  => $dateHired,
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $this->empIds[] = $id;
        return $id;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createTypeWithPolicy(string $code, string $name, array $overrides = [], bool $forceCreate = false): int
    {
        if (!$forceCreate && $code !== '') {
            $stmt = $this->pdo->prepare("SELECT id FROM leave_types WHERE leave_code = :code LIMIT 1");
            $stmt->execute(['code' => $code]);
            $existingId = (int)$stmt->fetchColumn();
            if ($existingId > 0) {
                return $existingId;
            }
        }

        $sfx = bin2hex(random_bytes(2));
        $data = array_merge([
            'leave_name'               => "{$name} {$sfx}",
            'leave_code'               => $forceCreate ? "{$code}_{$sfx}" : $code,
            'description'              => 'Test classification',
            'classification'           => 'Statutory',
            'benefit_category'         => 'StatutoryAnnual',
            'entitlement_model'        => 'annual',
            'default_days'             => 5,
            'default_unit'             => 'working_days',
            'is_paid'                  => 1,
            'is_confidential'          => 0,
            'counts_as_sil_equivalent' => 0,
            'cash_convertible'         => 0,
            'status'                   => 'Active',
        ], $overrides);

        $typeId = $this->leaveModel->createLeaveType($data);
        $this->typeIds[] = $typeId;

        $policyId = $this->leaveModel->createPolicyVersion($typeId, array_merge($data, [
            'entitlement_days' => (float)$data['default_days'],
            'entitlement_unit' => $data['default_unit'],
            'effective_from'   => '2020-01-01',
        ]), $this->hrUserId ?: null, true);
        if ($policyId > 0) {
            $this->policyIds[] = $policyId;
        }

        return $typeId;
    }

    public function test_sil_service_threshold_vl_offset_exclusion_and_conversion(): void
    {
        $juniorEmpId = $this->createEmployee('Female', '2094-06-01');
        $seniorEmpId = $this->createEmployee('Male', '2092-01-01');
        $exemptEmpId = $this->createEmployee('Male', '2090-01-01');

        $silTypeId = $this->createTypeWithPolicy('SIL', 'SIL Test', [
            'min_service_months'    => 12,
            'stack_with_equivalent' => 0,
            'cash_convertible'      => 1,
            'cash_conversion_max_days' => 5.0,
        ]);

        // Seed <12 mos vs >=12 mos evaluation (assuming active VL exists in seed, non-stacking offsets to VL)
        $empStmt = $this->pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $empStmt->execute([$juniorEmpId]);
        $juniorEmp = $empStmt->fetch(PDO::FETCH_ASSOC);

        $silPolicy = $this->engine->resolveLeaveTypeWithPolicy($silTypeId, '2094-11-01');
        $this->assertNotNull($silPolicy);

        $juniorRes = $this->engine->evaluateSilEligibility($juniorEmp, $silPolicy, $this->testYear, '2094-11-01');
        $this->assertFalse($juniorRes['eligible']);
        $this->assertSame(LeavePolicyEngine::STATUS_UNAVAILABLE, $juniorRes['status']);
        $this->assertStringContainsString('12 months', (string)$juniorRes['reason']);

        // Senior with qualifying VL (>=5d seeded) -> Satisfied via VL, no duplicate +5 stacking
        $empStmt->execute([$seniorEmpId]);
        $seniorEmp = $empStmt->fetch(PDO::FETCH_ASSOC);
        $seniorRes = $this->engine->evaluateSilEligibility($seniorEmp, $silPolicy, $this->testYear, '2094-11-01');
        $this->assertFalse($seniorRes['eligible']);
        $this->assertTrue($seniorRes['credited_via_vl']);
        $this->assertSame(0, $seniorRes['entitled_days']);

        // Statutory exclusion override (Managerial / Field Personnel) blocks SIL
        $this->leaveModel->upsertQualification([
            'employee_id'         => $exemptEmpId,
            'qualification_type'  => 'sil_exclusion',
            'verification_status' => 'Verified',
            'sil_exempt_override' => 1,
            'exclusion_reason'    => 'Field Personnel',
        ], $this->hrUserId ?: null);
        $empStmt->execute([$exemptEmpId]);
        $exemptEmp = $empStmt->fetch(PDO::FETCH_ASSOC);
        $exemptRes = $this->engine->evaluateSilEligibility($exemptEmp, $silPolicy, $this->testYear, '2094-11-01');
        $this->assertFalse($exemptRes['eligible']);
        $this->assertStringContainsString('Excluded from SIL', (string)$exemptRes['reason']);

        // Direct SIL cash conversion (when stack_with_equivalent / standalone SIL balance applies)
        $this->pdo->prepare("
            INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, converted_days, remaining_days, sil_credited_from_vl)
            VALUES (:eid, :ltid, :yr, 5, 1, 0, 4, 0)
        ")->execute(['eid' => $seniorEmpId, 'ltid' => $silTypeId, 'yr' => $this->testYear]);

        // Over-conversion must fail
        try {
            $this->ledger->recordSilConversion($seniorEmpId, $silTypeId, $this->testYear, 5, 'annual_unused', 'MEMO-001', 'Too many days');
            $this->fail('Expected conversion > remaining to throw DomainException.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('available for conversion', $e->getMessage());
        }

        // Valid annual_unused + separation conversions succeed and record reference without payroll calculation
        $res1 = $this->ledger->recordSilConversion($seniorEmpId, $silTypeId, $this->testYear, 2, 'annual_unused', 'SIL-2094-01', 'Annual unused commutation', $this->hrUserId ?: null);
        $this->assertSame(2, $res1['days_converted']);
        $this->assertSame(2, $res1['remaining_after']);
        $this->assertSame('annual_unused', $res1['conversion_scope']);

        $res2 = $this->ledger->recordSilConversion($seniorEmpId, $silTypeId, $this->testYear, 2, 'separation', 'FINAL-SEP-01', 'Separation SIL commutation', $this->hrUserId ?: null);
        $this->assertSame(0, $res2['remaining_after']);
        $this->assertSame('separation', $res2['conversion_scope']);

        $bal = $this->leaveModel->getBalance($seniorEmpId, $silTypeId, $this->testYear);
        $this->assertSame(4, (int)$bal['converted_days']);
        $this->assertSame(0, (int)$bal['remaining_days']);
    }

    public function test_solo_parent_requires_six_months_service_and_valid_spic(): void
    {
        $newEmpId = $this->createEmployee('Female', '2094-08-01');
        $soloEmpId = $this->createEmployee('Female', '2093-01-01');
        $spTypeId = $this->createTypeWithPolicy('SOLO_PARENT', 'Solo Parent Leave Test', [
            'default_days'       => 7,
            'min_service_months' => 6,
            'requires_qualification' => 1,
            'qualification_type' => 'solo_parent_spic',
        ]);
        $spPolicy = $this->engine->resolveLeaveTypeWithPolicy($spTypeId, '2094-11-01');
        $this->assertNotNull($spPolicy);

        $empStmt = $this->pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $empStmt->execute([$newEmpId]);
        $newEmp = $empStmt->fetch(PDO::FETCH_ASSOC);

        $resShort = $this->engine->evaluateSoloParentEligibility($newEmp, $spPolicy, '2094-11-01');
        $this->assertFalse($resShort['eligible']);
        $this->assertStringContainsString('6 months', (string)$resShort['reason']);

        $empStmt->execute([$soloEmpId]);
        $soloEmp = $empStmt->fetch(PDO::FETCH_ASSOC);

        // Without SPIC -> blocked
        $resNoSpic = $this->engine->evaluateSoloParentEligibility($soloEmp, $spPolicy, '2094-11-01');
        $this->assertFalse($resNoSpic['eligible']);
        $this->assertSame(LeavePolicyEngine::STATUS_PENDING_VERIFICATION, $resNoSpic['status']);

        // Expired SPIC -> blocked
        $this->leaveModel->upsertQualification([
            'employee_id'         => $soloEmpId,
            'qualification_type'  => 'solo_parent_spic',
            'verification_status' => 'Verified',
            'reference_no'        => 'SPIC-EXP-01',
            'valid_from'          => '2093-01-01',
            'valid_to'            => '2094-10-01',
        ], $this->hrUserId ?: null);

        $resExpired = $this->engine->evaluateSoloParentEligibility($soloEmp, $spPolicy, '2094-11-01');
        $this->assertFalse($resExpired['eligible']);
        $this->assertStringContainsString('expired', strtolower((string)$resExpired['reason']));

        // Valid renewed SPIC -> 7 working days allowed
        $this->leaveModel->upsertQualification([
            'employee_id'         => $soloEmpId,
            'qualification_type'  => 'solo_parent_spic',
            'verification_status' => 'Verified',
            'reference_no'        => 'SPIC-ACT-02',
            'valid_from'          => '2094-10-15',
            'valid_to'            => '2095-10-14',
        ], $this->hrUserId ?: null);

        $resValid = $this->engine->evaluateSoloParentEligibility($soloEmp, $spPolicy, '2094-11-01');
        $this->assertTrue($resValid['eligible']);
        $this->assertSame(7, $resValid['entitled_days']);

        // Cannot exceed 7 working days
        $this->expectException(DomainException::class);
        $this->engine->validateFiling($soloEmpId, $spTypeId, '2094-11-04', '2094-11-15', [], true);
    }

    public function test_maternity_calendar_days_cross_year_scenarios_extensions_and_transfer(): void
    {
        $femaleId = $this->createEmployee('Female', '2093-01-01');
        $maleId = $this->createEmployee('Male', '2093-01-01');

        $matTypeId = $this->createTypeWithPolicy('MATERNITY', 'Maternity Test', [
            'benefit_category'  => 'StatutoryEvent',
            'entitlement_model' => 'event_based',
            'default_days'      => 105,
            'default_unit'      => 'calendar_days',
            'rules_json'        => json_encode(['allow_cross_year' => true, 'father_transfer_max_days' => 7]),
        ]);

        // Cross-year Dec 20, 2094 to Mar 29, 2095 = 100 calendar days -> allowed!
        $resolved = $this->engine->validateFiling($femaleId, $matTypeId, '2094-12-20', '2095-03-29', [
            'maternity_scenario' => 'live_childbirth',
        ], true);
        $this->assertSame('calendar_days', $resolved['entitlement_unit']);
        $this->assertTrue($resolved['allows_cross_year']);
        $this->assertSame(100, $resolved['number_of_days']);

        // Transferring 7 days to father drops mother net cap to 98 -> requesting 100 must throw!
        try {
            $this->engine->validateFiling($femaleId, $matTypeId, '2094-12-20', '2095-03-29', [
                'maternity_scenario'      => 'live_childbirth',
                'transfer_to_father_days' => 7,
            ], true);
            $this->fail('Expected net cap 98 to reject 100-day request.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('98d', $e->getMessage());
        }

        // Optional +30d unpaid extension expands live childbirth cap cleanly to 135d
        $extResolved = $this->engine->validateFiling($femaleId, $matTypeId, '2095-01-01', '2095-05-05', [
            'maternity_scenario'    => 'live_childbirth',
            'unpaid_extension_days' => 30,
        ], true);
        $this->assertSame(125, $extResolved['number_of_days']);

        // Solo parent 120d scenario fails without verified SPIC
        try {
            $this->engine->validateFiling($femaleId, $matTypeId, '2095-06-01', '2095-09-25', [
                'maternity_scenario' => 'solo_parent_childbirth',
            ], true);
            $this->fail('Expected missing SPIC to reject 120-day solo parent maternity.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('SPIC', $e->getMessage());
        }

        // Miscarriage blocks father transfer and caps at 60d
        try {
            $this->engine->validateFiling($femaleId, $matTypeId, '2095-06-01', '2095-06-15', [
                'maternity_scenario'      => 'miscarriage_etp',
                'transfer_to_father_days' => 5,
            ], true);
            $this->fail('Expected miscarriage_etp to block father credit transfer.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('miscarriage', strtolower($e->getMessage()));
        }

        // Male employee may ONLY apply under father_transfer_allocation up to 7 calendar days
        $fatherTransfer = $this->engine->validateFiling($maleId, $matTypeId, '2095-01-10', '2095-01-16', [
            'maternity_scenario'    => 'father_transfer_allocation',
            'mother_allocation_ref' => 'MAT-NOTICE-2095',
        ], true);
        $this->assertSame(7, $fatherTransfer['number_of_days']);

        $this->expectException(DomainException::class);
        $this->engine->validateFiling($maleId, $matTypeId, '2095-01-10', '2095-01-17', [
            'maternity_scenario'    => 'father_transfer_allocation',
            'mother_allocation_ref' => 'MAT-NOTICE-2095',
        ], true);
    }

    public function test_paternity_delivery_cap_and_duplicate_delivery_guard(): void
    {
        $maleId = $this->createEmployee('Male', '2093-01-01');
        $femaleId = $this->createEmployee('Female', '2093-01-01');

        $patTypeId = $this->createTypeWithPolicy('PATERNITY', 'Paternity Test', [
            'benefit_category'    => 'StatutoryEvent',
            'entitlement_model'   => 'event_based',
            'default_days'        => 7,
            'max_events_lifetime' => 4,
        ]);

        // Female cannot file RA 8187
        try {
            $this->engine->validateFiling($femaleId, $patTypeId, '2094-11-04', '2094-11-08', ['spouse_name' => 'Test Spouse']);
            $this->fail('Expected female employee to be rejected from RA 8187.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('male', strtolower($e->getMessage()));
        }

        // Seed 3 previous deliveries (1, 2, 3) -> duplicate #2 rejected, #4 allowed
        for ($d = 1; $d <= 3; $d++) {
            $this->pdo->prepare("
                INSERT INTO leave_event_entitlements (employee_id, leave_type_id, event_code, event_scenario, event_date, delivery_sequence, entitled_days, remaining_days, valid_from, verification_status)
                VALUES (:eid, :ltid, :ec, 'spouse_delivery', '2093-05-01', :seq, 7, 0, '2093-05-01', 'Completed')
            ")->execute(['eid' => $maleId, 'ltid' => $patTypeId, 'ec' => "PAT-{$d}", 'seq' => $d]);
        }

        try {
            $this->engine->validateFiling($maleId, $patTypeId, '2094-11-04', '2094-11-08', [
                'spouse_name'       => 'Maria Santos',
                'delivery_sequence' => 2,
            ], true);
            $this->fail('Expected duplicate delivery #2 to throw DomainException.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('#2', $e->getMessage());
        }

        // Delivery #4 succeeds for 5 working days (Thu Nov 4 .. Wed Nov 10, 2094)
        $patOk = $this->engine->validateFiling($maleId, $patTypeId, '2094-11-04', '2094-11-10', [
            'spouse_name'       => 'Maria Santos',
            'delivery_sequence' => 4,
        ], true);
        $this->assertSame(5, $patOk['number_of_days']);

        // Adding 4th delivery row pushes count to 4 -> next filing blocked
        $this->pdo->prepare("
            INSERT INTO leave_event_entitlements (employee_id, leave_type_id, event_code, event_scenario, event_date, delivery_sequence, entitled_days, remaining_days, valid_from, verification_status)
            VALUES (:eid, :ltid, 'PAT-4', 'spouse_delivery', '2094-11-04', 4, 7, 0, '2094-11-04', 'Completed')
        ")->execute(['eid' => $maleId, 'ltid' => $patTypeId]);

        $this->expectException(DomainException::class);
        $this->engine->validateFiling($maleId, $patTypeId, '2095-02-04', '2095-02-06', [
            'spouse_name' => 'Maria Santos',
        ], true);
    }

    public function test_slw_vawc_lwop_confidential_masking_and_policy_versioning(): void
    {
        $newFemaleId = $this->createEmployee('Female', '2094-09-01');
        $eligibleFemaleId = $this->createEmployee('Female', '2093-01-01');
        $maleId = $this->createEmployee('Male', '2093-01-01');

        $slwTypeId = $this->createTypeWithPolicy('SLW', 'SLW Test', [
            'benefit_category'  => 'StatutoryEvent',
            'entitlement_model' => 'event_based',
            'default_days'      => 60,
            'default_unit'      => 'calendar_days',
            'is_confidential'   => 1,
        ]);

        // Male rejected for SLW
        try {
            $this->engine->validateFiling($maleId, $slwTypeId, '2094-11-01', '2094-11-10', ['has_physician_cert' => true], true);
            $this->fail('Expected male to be excluded from SLW.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('female', strtolower($e->getMessage()));
        }

        // Female <6 mos in last 12 mos rejected
        try {
            $this->engine->validateFiling($newFemaleId, $slwTypeId, '2094-11-01', '2094-11-10', ['has_physician_cert' => true], true);
            $this->fail('Expected <6 months aggregate service to fail SLW.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('6 months', $e->getMessage());
        }

        // Female >=6 mos requires medical cert and caps at 60 calendar days
        $slwOk = $this->engine->validateFiling($eligibleFemaleId, $slwTypeId, '2094-12-15', '2095-01-15', ['has_physician_cert' => true], true);
        $this->assertSame(1, $slwOk['is_confidential']);
        $this->assertSame('calendar_days', $slwOk['entitlement_unit']);

        // VAWC RA 9262: 10 working days + protection order extension + confidential masking
        $vawcTypeId = $this->createTypeWithPolicy('VAWC', 'VAWC Test', [
            'benefit_category'  => 'StatutoryEvent',
            'entitlement_model' => 'event_based',
            'default_days'      => 10,
            'default_unit'      => 'working_days',
            'is_confidential'   => 1,
        ]);

        try {
            $this->engine->validateFiling($eligibleFemaleId, $vawcTypeId, '2094-11-04', '2094-11-20', [
                'certification_ref' => 'BRGY-001',
            ], true);
            $this->fail('Expected >10 VAWC working days without Protection Order to throw.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('exceed', strtolower($e->getMessage()));
        }

        $vawcExt = $this->engine->validateFiling($eligibleFemaleId, $vawcTypeId, '2094-11-04', '2094-11-20', [
            'protection_order_ref' => 'BPO-2094-77',
            'vawc_extension_days'  => 5,
        ], true);
        $this->assertSame(1, $vawcExt['is_confidential']);

        // Masking redacts sensitive details for unrelated/non-cleared viewers
        $rawRow = [
            'id'              => 999,
            'employee_id'     => $eligibleFemaleId,
            'leave_type_id'   => $vawcTypeId,
            'leave_code'      => 'VAWC',
            'leave_name'      => 'VAWC Leave (RA 9262)',
            'is_confidential' => 1,
            'reason'          => 'Sensitive Barangay BPO court hearing details',
            'hr_info_request_note' => 'Provide updated BPO docket',
        ];
        $unrelatedViewer = ['id' => 999999, 'role' => 'employee', 'employee_id' => $maleId];
        $masked = ConfidentialLeaveService::maskRequestForViewer($rawRow, $unrelatedViewer, $this->pdo);
        $this->assertTrue($masked['is_redacted']);
        $this->assertSame('Confidential Statutory Leave', $masked['leave_name']);
        $this->assertStringNotContainsString('Barangay BPO', $masked['reason']);
        $this->assertSame('Confidential Leave Application Update', ConfidentialLeaveService::safeNotificationTitle(true, 'New VAWC Filing'));
        $this->assertStringNotContainsString('hearing', ConfidentialLeaveService::safeNotificationMessage(true, 999, 'hearing details'));
        $this->assertStringNotContainsString('hearing', ConfidentialLeaveService::safeActivityDescription(true, 999, 'Filed', 'hearing details'));

        // LWOP unpaid check
        $lwopTypeId = $this->createTypeWithPolicy('LWOP', 'LWOP Test', [
            'classification'    => 'Company',
            'benefit_category'  => 'Unpaid',
            'entitlement_model' => 'unpaid',
            'default_days'      => 0,
            'is_paid'           => 0,
        ]);
        $lwopResolved = $this->engine->validateFiling($eligibleFemaleId, $lwopTypeId, '2094-11-04', '2094-11-06', [], false);
        $this->assertSame(0, $lwopResolved['is_paid']);
        $this->assertSame('unpaid', $lwopResolved['entitlement_model']);

        // Non-retroactive Policy Versioning: Draft -> Active supersedes v1 while preserving v1 id
        $vlTypeId = $this->createTypeWithPolicy('VER_VL', 'Versioned VL Test', [
            'classification' => 'Company',
            'benefit_category' => 'CompanyAnnual',
            'default_days'   => 15,
        ], true);
        $v1Row = $this->engine->resolveLeaveTypeWithPolicy($vlTypeId, '2094-06-01');
        $v1Id = (int)$v1Row['policy_version_id'];

        $v2Id = $this->leaveModel->createPolicyVersion($vlTypeId, [
            'effective_from'   => '2095-01-01',
            'approval_status'  => 'PendingReview',
            'entitlement_days' => 18,
            'change_notes'     => 'Enhanced annual VL',
        ], $this->hrUserId ?: null, false);
        $this->assertGreaterThan($v1Id, $v2Id);

        // Before activation, v1 remains active
        $stillV1 = $this->engine->resolveLeaveTypeWithPolicy($vlTypeId, '2095-02-01');
        $this->assertSame($v1Id, (int)$stillV1['policy_version_id']);

        $activated = $this->leaveModel->activatePolicyVersion($v2Id, $this->hrUserId ?: 1);
        $this->assertTrue($activated);

        $nowV2 = $this->engine->resolveLeaveTypeWithPolicy($vlTypeId, '2095-02-01');
        $this->assertSame($v2Id, (int)$nowV2['policy_version_id']);
        $this->assertSame(18, (int)$nowV2['entitlement_days']);

        $v1Status = $this->pdo->query("SELECT approval_status FROM leave_policy_versions WHERE id = {$v1Id}")->fetchColumn();
        $this->assertSame('Superseded', $v1Status);
    }

    public function test_employee_views_hide_inapplicable_benefits_while_preserving_policy_reference(): void
    {
        $vlTypeId = $this->createTypeWithPolicy('VL', 'Vacation Leave Test', [
            'classification'           => 'Company',
            'benefit_category'         => 'CompanyAnnual',
            'default_days'             => 15,
            'counts_as_sil_equivalent' => 1,
        ]);
        $silTypeId = $this->createTypeWithPolicy('SIL', 'Service Incentive Leave Test', [
            'classification'        => 'Statutory',
            'benefit_category'      => 'StatutoryAnnual',
            'default_days'          => 5,
            'min_service_months'    => 12,
            'stack_with_equivalent' => 0,
        ]);
        $spTypeId = $this->createTypeWithPolicy('SOLO_PARENT', 'Solo Parent Leave Test', [
            'classification'         => 'Statutory',
            'benefit_category'       => 'StatutoryAnnual',
            'default_days'           => 7,
            'min_service_months'     => 6,
            'requires_qualification' => 1,
            'qualification_type'     => 'solo_parent_spic',
        ]);
        $slwTypeId = $this->createTypeWithPolicy('SLW', 'Special Leave for Women Test', [
            'classification'         => 'Statutory',
            'benefit_category'       => 'StatutoryEvent',
            'entitlement_model'      => 'event_based',
            'default_days'           => 60,
            'default_unit'           => 'calendar_days',
            'min_service_months'     => 6,
            'service_window_months'  => 12,
            'is_confidential'        => 1,
        ]);

        $year = (int)date('Y');
        $maleId = $this->createEmployee('Male', '2020-01-01');
        $femaleId = $this->createEmployee('Female', '2020-01-01');

        // Seed VL (eligible) + a zero-usage CreditedViaVL SIL row for the male employee
        $this->pdo->prepare("
            INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days, eligibility_status, sil_credited_from_vl)
            VALUES (?, ?, ?, 15, 0, 15, 'Available', 0),
                   (?, ?, ?, 0, 0, 0, 'CreditedViaVL', 1)
        ")->execute([$maleId, $vlTypeId, $year, $maleId, $silTypeId, $year]);

        // Seed a verified SPIC qualification for the female employee
        $this->pdo->prepare("
            INSERT INTO employee_leave_qualifications (employee_id, qualification_type, reference_no, verification_status, valid_from, valid_to)
            VALUES (?, 'solo_parent_spic', 'SPIC-TEST-OK', 'Verified', '2020-01-01', '2099-12-31')
        ")->execute([$femaleId]);
        $this->pdo->prepare("
            INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days, eligibility_status)
            VALUES (?, ?, ?, 15, 0, 15, 'Available'),
                   (?, ?, ?, 7, 0, 7, 'Available')
        ")->execute([$femaleId, $vlTypeId, $year, $femaleId, $spTypeId, $year]);

        $prevUser = Session::get('user');
        $prevServer = $_SERVER;
        $prevGet = $_GET;

        try {
            $_SERVER['HTTP_ACCEPT'] = 'text/html';
            $_GET = [];

            $dashCapture = [];
            $dashCtrl = new class ($this->pdo, $dashCapture) extends DashboardController {
                /** @var array<string, mixed> */
                private array $store;

                /** @param array<string, mixed> $store */
                public function __construct(PDO $pdo, array &$store)
                {
                    parent::__construct($pdo);
                    $this->store = &$store;
                }

                protected function view(string $viewPath, array $data = [], ?string $layout = 'main'): void
                {
                    $this->store = ['view' => $viewPath, 'data' => $data];
                }
            };

            $applyCapture = [];
            $applyCtrl = new class ($this->leaveModel, $applyCapture) extends LeaveController {
                /** @var array<string, mixed> */
                private array $store;

                /** @param array<string, mixed> $store */
                public function __construct(Leave $model, array &$store)
                {
                    parent::__construct($model);
                    $this->store = &$store;
                }

                protected function view(string $viewPath, array $data = [], ?string $layout = 'main'): void
                {
                    $this->store = ['view' => $viewPath, 'data' => $data];
                }
            };

            // 1. Male employee with VL >= 5d and no SPIC
            Session::set('user', ['id' => 999001, 'role' => 'employee', 'employee_id' => $maleId, 'employee_no' => 'EMP-M']);

            $dashCtrl->employeeDashboard();
            $dashBalIds = array_map(static fn(array $r): int => (int)$r['leave_type_id'], $dashCapture['data']['balances']);
            $this->assertContains($vlTypeId, $dashBalIds);
            $this->assertNotContains($silTypeId, $dashBalIds);

            $dashCtrl->employeeLeaveBalances();
            $maleAnnualIds = array_map(static fn(array $e): int => (int)$e['type']['id'], $dashCapture['data']['annualBenefits']);
            $maleEventIds = array_map(static fn(array $e): int => (int)$e['type']['id'], $dashCapture['data']['statutoryEvents']);
            $malePolicyRefIds = array_map(static fn(array $p): int => (int)$p['id'], $dashCapture['data']['allTypePolicies']);

            $this->assertContains($vlTypeId, $maleAnnualIds);
            $this->assertNotContains($silTypeId, $maleAnnualIds);
            $this->assertNotContains($spTypeId, $maleAnnualIds);
            $this->assertNotContains($slwTypeId, $maleEventIds);

            // Bottom Policy Reference table retains all active rules
            $this->assertContains($silTypeId, $malePolicyRefIds);
            $this->assertContains($spTypeId, $malePolicyRefIds);
            $this->assertContains($slwTypeId, $malePolicyRefIds);

            $applyCtrl->apply();
            $maleApplyIds = array_map(static fn(array $t): int => (int)$t['id'], $applyCapture['data']['leaveTypes']);
            $this->assertContains($vlTypeId, $maleApplyIds);
            $this->assertNotContains($silTypeId, $maleApplyIds);
            $this->assertNotContains($spTypeId, $maleApplyIds);
            $this->assertNotContains($slwTypeId, $maleApplyIds);

            // 2. Female employee with >= 6m tenure and verified SPIC still sees Solo Parent & SLW
            Session::set('user', ['id' => 999002, 'role' => 'employee', 'employee_id' => $femaleId, 'employee_no' => 'EMP-F']);

            $dashCtrl->employeeLeaveBalances();
            $femaleAnnualIds = array_map(static fn(array $e): int => (int)$e['type']['id'], $dashCapture['data']['annualBenefits']);
            $femaleEventIds = array_map(static fn(array $e): int => (int)$e['type']['id'], $dashCapture['data']['statutoryEvents']);
            $this->assertContains($spTypeId, $femaleAnnualIds);
            $this->assertContains($slwTypeId, $femaleEventIds);

            $applyCtrl->apply();
            $femaleApplyIds = array_map(static fn(array $t): int => (int)$t['id'], $applyCapture['data']['leaveTypes']);
            $this->assertContains($spTypeId, $femaleApplyIds);
            $this->assertContains($slwTypeId, $femaleApplyIds);
        } finally {
            if ($prevUser === null) {
                unset($_SESSION['user']);
            } else {
                Session::set('user', $prevUser);
            }
            $_SERVER = $prevServer;
            $_GET = $prevGet;
        }
    }
}
