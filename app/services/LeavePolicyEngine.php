<?php

/**
 * Philippine Statutory & Company Leave Policy Engine
 * Centralizes leave classification, policy version resolution, service-tenure
 * checks, Service Incentive Leave (Art. 95) offsetting/exclusions, Solo Parent
 * SPIC verification (RA 8972 / RA 11861), Maternity scenarios (RA 11210),
 * Paternity delivery cap & marital proof (RA 8187), Special Leave for Women
 * (RA 9710), VAWC confidentiality/protection orders (RA 9262), Bereavement,
 * and unpaid leave (LWOP) rules.
 */

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PDO;
use Throwable;

final class LeavePolicyEngine
{
    public const CODE_VL          = 'VL';
    public const CODE_SL          = 'SL';
    public const CODE_EL          = 'EL';
    public const CODE_SIL         = 'SIL';
    public const CODE_SOLO_PARENT = 'SOLO_PARENT';
    public const CODE_MATERNITY   = 'MATERNITY';
    public const CODE_PATERNITY   = 'PATERNITY';
    public const CODE_SLW         = 'SLW';
    public const CODE_VAWC        = 'VAWC';
    public const CODE_BEREAVEMENT = 'BEREAVEMENT';
    public const CODE_LWOP        = 'LWOP';

    public const STATUS_AVAILABLE            = 'Available';
    public const STATUS_LOW_BALANCE          = 'Low Balance';
    public const STATUS_FULLY_USED           = 'Fully Used';
    public const STATUS_NOT_ALLOCATED        = 'Not Allocated';
    public const STATUS_PENDING_VERIFICATION = 'Pending Verification';
    public const STATUS_UNAVAILABLE          = 'Unavailable';
    public const STATUS_CREDITED_VIA_VL      = 'Satisfied via VL';
    public const STATUS_EVENT_BASED          = 'Event-Based';
    public const STATUS_UNPAID               = 'Unpaid Tracking';

    /** @var array<string, bool> */
    private static array $tableCache = [];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /**
     * Load a leave type merged with its active policy version for the given date.
     *
     * @return array<string, mixed>|null
     */
    public function resolveLeaveTypeWithPolicy(int $leaveTypeId, ?string $asOfDate = null): ?array
    {
        $date = $asOfDate ?? date('Y-m-d');
        $hasPolicyTable = self::tableExists($this->pdo, 'leave_policy_versions');

        $stmt = $this->pdo->prepare("SELECT * FROM leave_types WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $leaveTypeId]);
        $lt = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lt) {
            return null;
        }

        $policy = null;
        if ($hasPolicyTable) {
            if (!empty($lt['active_policy_version_id'])) {
                $pStmt = $this->pdo->prepare("
                    SELECT *
                    FROM leave_policy_versions
                    WHERE id = :pid AND approval_status = 'Active'
                      AND effective_from <= :as_of
                      AND (effective_to IS NULL OR effective_to >= :as_of2)
                    LIMIT 1
                ");
                $pStmt->execute([
                    'pid'    => (int)$lt['active_policy_version_id'],
                    'as_of'  => $date,
                    'as_of2' => $date,
                ]);
                $policy = $pStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if ($policy === null) {
                $pStmt = $this->pdo->prepare("
                    SELECT *
                    FROM leave_policy_versions
                    WHERE leave_type_id = :lt_id
                      AND approval_status = 'Active'
                      AND effective_from <= :as_of
                      AND (effective_to IS NULL OR effective_to >= :as_of2)
                    ORDER BY version_number DESC, id DESC
                    LIMIT 1
                ");
                $pStmt->execute(['lt_id' => $leaveTypeId, 'as_of' => $date, 'as_of2' => $date]);
                $policy = $pStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        }

        return $this->mergeTypeAndPolicy($lt, $policy);
    }

    /**
     * Load all active (or all) leave types enriched with their active policy rules.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllTypesWithActivePolicies(bool $activeOnly = true, ?string $asOfDate = null): array
    {
        $sql = "SELECT id FROM leave_types" . ($activeOnly ? " WHERE status = 'Active'" : '') . " ORDER BY id ASC";
        $ids = $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        $result = [];
        foreach ($ids as $id) {
            $resolved = $this->resolveLeaveTypeWithPolicy((int)$id, $asOfDate);
            if ($resolved !== null) {
                $result[] = $resolved;
            }
        }
        return $result;
    }

    /**
     * Completed calendar months of continuous service from hire date to reference date.
     */
    public static function calculateCompletedServiceMonths(?string $dateHired, ?string $asOfDate = null): int
    {
        $rawHire = trim((string)$dateHired);
        if ($rawHire === '') {
            return 0;
        }

        try {
            $hire = new DateTimeImmutable($rawHire);
            $ref = new DateTimeImmutable($asOfDate ?? date('Y-m-d'));
        } catch (Throwable) {
            return 0;
        }

        if ($ref < $hire) {
            return 0;
        }

        $diff = $hire->diff($ref);
        return ($diff->y * 12) + $diff->m;
    }

    /**
     * Continuous aggregate service months within the preceding 12-month window (RA 9710 Sec. 18).
     */
    public static function calculateServiceMonthsInLast12Months(?string $dateHired, ?string $asOfDate = null): int
    {
        $rawHire = trim((string)$dateHired);
        if ($rawHire === '') {
            return 0;
        }

        try {
            $hire = new DateTimeImmutable($rawHire);
            $ref = new DateTimeImmutable($asOfDate ?? date('Y-m-d'));
            if ($ref < $hire) {
                return 0;
            }
            $windowStart = $ref->modify('-12 months');
            $effectiveStart = $hire > $windowStart ? $hire : $windowStart;
            $diff = $effectiveStart->diff($ref);
            return ($diff->y * 12) + $diff->m;
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Determine whether an employee qualifies for Service Incentive Leave (Art. 95)
     * or is statutorily exempt / already covered by >= 5 paid Vacation Leave days.
     *
     * @param array<string, mixed> $employee
     * @param array<string, mixed> $silPolicy
     * @return array<string, mixed>
     */
    public function evaluateSilEligibility(array $employee, array $silPolicy, int $year, ?string $asOfDate = null): array
    {
        $employeeId = (int)($employee['id'] ?? 0);
        $minMonths = max(12, (int)($silPolicy['min_service_months'] ?? 12));
        $serviceMonths = self::calculateCompletedServiceMonths($employee['date_hired'] ?? null, $asOfDate);

        // 1. Explicit statutory exclusion recorded in employee_leave_qualifications (e.g., Managerial / Field Personnel)
        $exclusion = $this->findLatestQualification($employeeId, 'sil_exclusion');
        if ($exclusion !== null && ($exclusion['verification_status'] ?? '') === 'Verified' && !empty($exclusion['sil_exempt_override'])) {
            $reason = trim((string)($exclusion['exclusion_reason'] ?? 'Statutorily exempt classification under Labor Code Art. 95'));
            return [
                'eligible'         => false,
                'status'           => self::STATUS_UNAVAILABLE,
                'credited_via_vl'  => false,
                'entitled_days'    => 0,
                'reason'           => 'Excluded from SIL (' . $reason . ').',
            ];
        }

        // 2. Minimum 1 year of service check
        if ($serviceMonths < $minMonths) {
            return [
                'eligible'         => false,
                'status'           => self::STATUS_UNAVAILABLE,
                'credited_via_vl'  => false,
                'entitled_days'    => 0,
                'reason'           => sprintf(
                    'Requires at least %d months of service (currently %d month%s).',
                    $minMonths,
                    $serviceMonths,
                    $serviceMonths === 1 ? '' : 's'
                ),
            ];
        }

        // 3. Statutory exemption check: qualifying paid Vacation Leave >= 5 days satisfies SIL
        $stackWithEquivalent = !empty($silPolicy['stack_with_equivalent']);
        if (!$stackWithEquivalent && $employeeId > 0) {
            $vlInfo = $this->findQualifyingEquivalentVacation($employeeId, $year);
            if ($vlInfo !== null) {
                return [
                    'eligible'         => false,
                    'status'           => self::STATUS_CREDITED_VIA_VL,
                    'credited_via_vl'  => true,
                    'entitled_days'    => 0,
                    'reason'           => sprintf(
                        'Satisfied via %s (%d paid days >= 5-day Article 95 statutory minimum; no duplicate +5 stacking).',
                        $vlInfo['leave_name'],
                        (int)$vlInfo['effective_days']
                    ),
                ];
            }
        }

        return [
            'eligible'         => true,
            'status'           => self::STATUS_AVAILABLE,
            'credited_via_vl'  => false,
            'entitled_days'    => (int)round((float)($silPolicy['entitlement_days'] ?? 5)),
            'reason'           => 'Eligible for Service Incentive Leave under Labor Code Art. 95.',
        ];
    }

    /**
     * Determine whether an employee qualifies for Parental Leave for Solo Parents (RA 8972 / RA 11861).
     *
     * @param array<string, mixed> $employee
     * @param array<string, mixed> $spPolicy
     * @return array<string, mixed>
     */
    public function evaluateSoloParentEligibility(array $employee, array $spPolicy, ?string $asOfDate = null): array
    {
        $employeeId = (int)($employee['id'] ?? 0);
        $date = $asOfDate ?? date('Y-m-d');
        $minMonths = max(6, (int)($spPolicy['min_service_months'] ?? 6));
        $serviceMonths = self::calculateCompletedServiceMonths($employee['date_hired'] ?? null, $date);

        if ($serviceMonths < $minMonths) {
            return [
                'eligible'      => false,
                'status'        => self::STATUS_UNAVAILABLE,
                'entitled_days' => 0,
                'spic_record'   => null,
                'reason'        => sprintf(
                    'Requires at least %d months of service under RA 8972/RA 11861 (currently %d month%s).',
                    $minMonths,
                    $serviceMonths,
                    $serviceMonths === 1 ? '' : 's'
                ),
            ];
        }

        $spic = $this->findLatestQualification($employeeId, 'solo_parent_spic');
        if ($spic === null) {
            return [
                'eligible'      => false,
                'status'        => self::STATUS_PENDING_VERIFICATION,
                'entitled_days' => 0,
                'spic_record'   => null,
                'reason'        => 'Verified Solo Parent Identification Card (SPIC) is required before applying.',
            ];
        }

        $vStatus = (string)($spic['verification_status'] ?? 'PendingVerification');
        $validFrom = !empty($spic['valid_from']) ? (string)$spic['valid_from'] : null;
        $validTo = !empty($spic['valid_to']) ? (string)$spic['valid_to'] : null;

        if ($vStatus === 'Verified') {
            if ($validFrom !== null && $date < $validFrom) {
                return [
                    'eligible'      => false,
                    'status'        => self::STATUS_PENDING_VERIFICATION,
                    'entitled_days' => 0,
                    'spic_record'   => $spic,
                    'reason'        => 'SPIC validity begins on ' . $validFrom . '.',
                ];
            }
            if ($validTo !== null && $date > $validTo) {
                return [
                    'eligible'      => false,
                    'status'        => self::STATUS_UNAVAILABLE,
                    'entitled_days' => 0,
                    'spic_record'   => $spic,
                    'reason'        => 'Your Solo Parent ID Card (SPIC) expired on ' . $validTo . '. Please submit a renewed SPIC to HR.',
                ];
            }

            return [
                'eligible'      => true,
                'status'        => self::STATUS_AVAILABLE,
                'entitled_days' => (int)round((float)($spPolicy['entitlement_days'] ?? 7)),
                'spic_record'   => $spic,
                'reason'        => 'Active verified SPIC on file (' . ($spic['reference_no'] ?: 'Verified') . ').',
            ];
        }

        if ($vStatus === 'Expired') {
            return [
                'eligible'      => false,
                'status'        => self::STATUS_UNAVAILABLE,
                'entitled_days' => 0,
                'spic_record'   => $spic,
                'reason'        => 'SPIC is marked Expired. Submit an active renewal for HR verification.',
            ];
        }

        return [
            'eligible'      => false,
            'status'        => $vStatus === 'Rejected' || $vStatus === 'Revoked' ? self::STATUS_UNAVAILABLE : self::STATUS_PENDING_VERIFICATION,
            'entitled_days' => 0,
            'spic_record'   => $spic,
            'reason'        => 'SPIC verification status: ' . $vStatus . '. HR verification is required before claiming.',
        ];
    }

    /**
     * Evaluate overall employee eligibility for any configured leave type.
     *
     * @param array<string, mixed> $employee
     * @param array<string, mixed> $typePolicy
     * @return array<string, mixed>
     */
    public function evaluateEmployeeTypeEligibility(
        array $employee,
        array $typePolicy,
        int $year,
        ?string $asOfDate = null
    ): array {
        $code = strtoupper(trim((string)($typePolicy['leave_code'] ?? '')));
        $model = (string)($typePolicy['entitlement_model'] ?? 'annual');
        $gender = (string)($employee['gender'] ?? '');
        $date = $asOfDate ?? date('Y-m-d');
        $serviceMonths = self::calculateCompletedServiceMonths($employee['date_hired'] ?? null, $date);

        if ($model === 'unpaid' || $code === self::CODE_LWOP) {
            return [
                'eligible'         => true,
                'status'           => self::STATUS_UNPAID,
                'entitled_days'    => 0,
                'entitlement_unit' => (string)($typePolicy['entitlement_unit'] ?? 'working_days'),
                'reason'           => 'Authorized unpaid leave tracked separately with zero paid credit deduction.',
            ];
        }

        if ($code === self::CODE_SIL) {
            $res = $this->evaluateSilEligibility($employee, $typePolicy, $year, $date);
            $res['entitlement_unit'] = (string)($typePolicy['entitlement_unit'] ?? 'working_days');
            return $res;
        }

        if ($code === self::CODE_SOLO_PARENT) {
            $res = $this->evaluateSoloParentEligibility($employee, $typePolicy, $date);
            $res['entitlement_unit'] = (string)($typePolicy['entitlement_unit'] ?? 'working_days');
            return $res;
        }

        if ($code === self::CODE_MATERNITY) {
            if ($gender === 'Male') {
                return [
                    'eligible'         => true,
                    'status'           => self::STATUS_EVENT_BASED,
                    'entitled_days'    => 7,
                    'entitlement_unit' => 'calendar_days',
                    'reason'           => 'Eligible only as recipient of up to 7 allocated RA 11210 father-transfer days (distinct from RA 8187 Paternity).',
                ];
            }
            return [
                'eligible'         => true,
                'status'           => self::STATUS_EVENT_BASED,
                'entitled_days'    => (int)round((float)($typePolicy['entitlement_days'] ?? 105)),
                'entitlement_unit' => 'calendar_days',
                'reason'           => 'RA 11210 event benefit: 105d live birth (120d solo parent), 60d miscarriage/ETP, optional 30d unpaid extension.',
            ];
        }

        if ($code === self::CODE_PATERNITY) {
            if ($gender === 'Female') {
                return [
                    'eligible'         => false,
                    'status'           => self::STATUS_UNAVAILABLE,
                    'entitled_days'    => 0,
                    'entitlement_unit' => (string)($typePolicy['entitlement_unit'] ?? 'working_days'),
                    'reason'           => 'RA 8187 Paternity Leave applies to married male employees.',
                ];
            }
            $employeeId = (int)($employee['id'] ?? 0);
            $usedEvents = $this->countPaternityDeliveries($employeeId, (int)$typePolicy['id']);
            $maxEvents = (int)($typePolicy['max_events_lifetime'] ?? 4);
            if ($usedEvents >= $maxEvents) {
                return [
                    'eligible'         => false,
                    'status'           => self::STATUS_UNAVAILABLE,
                    'entitled_days'    => 0,
                    'entitlement_unit' => (string)($typePolicy['entitlement_unit'] ?? 'working_days'),
                    'reason'           => sprintf('Maximum statutory cap of %d Paternity Leave deliveries already reached.', $maxEvents),
                ];
            }
            $qual = $this->findLatestQualification($employeeId, 'paternity_marriage');
            $qualStatus = $qual['verification_status'] ?? 'PendingVerification';
            return [
                'eligible'         => true,
                'status'           => $qualStatus === 'Verified' ? self::STATUS_EVENT_BASED : self::STATUS_PENDING_VERIFICATION,
                'entitled_days'    => (int)round((float)($typePolicy['entitlement_days'] ?? 7)),
                'entitlement_unit' => (string)($typePolicy['entitlement_unit'] ?? 'working_days'),
                'reason'           => sprintf(
                    '%d of %d deliveries remaining under RA 8187 (%s).',
                    max(0, $maxEvents - $usedEvents),
                    $maxEvents,
                    $qualStatus === 'Verified' ? 'Marital record verified' : 'Marital proof pending HR verification'
                ),
            ];
        }

        if ($code === self::CODE_SLW) {
            if ($gender === 'Male') {
                return [
                    'eligible'         => false,
                    'status'           => self::STATUS_UNAVAILABLE,
                    'entitled_days'    => 0,
                    'entitlement_unit' => 'calendar_days',
                    'reason'           => 'RA 9710 Special Leave for Women applies exclusively to female employees.',
                ];
            }
            $aggMonths = self::calculateServiceMonthsInLast12Months($employee['date_hired'] ?? null, $date);
            if ($aggMonths < 6) {
                return [
                    'eligible'         => false,
                    'status'           => self::STATUS_UNAVAILABLE,
                    'entitled_days'    => 0,
                    'entitlement_unit' => 'calendar_days',
                    'reason'           => sprintf(
                        'Requires at least 6 months continuous aggregate service in the preceding 12 months (currently %d month%s).',
                        $aggMonths,
                        $aggMonths === 1 ? '' : 's'
                    ),
                ];
            }
            return [
                'eligible'         => true,
                'status'           => self::STATUS_EVENT_BASED,
                'entitled_days'    => 60,
                'entitlement_unit' => 'calendar_days',
                'reason'           => 'Eligible up to 60 calendar days for gynecological surgery with physician certification (confidential).',
            ];
        }

        if ($code === self::CODE_VAWC) {
            return [
                'eligible'         => true,
                'status'           => self::STATUS_EVENT_BASED,
                'entitled_days'    => (int)round((float)($typePolicy['entitlement_days'] ?? 10)),
                'entitlement_unit' => (string)($typePolicy['entitlement_unit'] ?? 'working_days'),
                'reason'           => 'RA 9262: Up to 10 paid days (extendable via court/barangay protection order) under strict confidentiality.',
            ];
        }

        if ($code === self::CODE_BEREAVEMENT) {
            return [
                'eligible'         => true,
                'status'           => self::STATUS_EVENT_BASED,
                'entitled_days'    => (int)round((float)($typePolicy['entitlement_days'] ?? 3)),
                'entitlement_unit' => (string)($typePolicy['entitlement_unit'] ?? 'working_days'),
                'reason'           => 'Event-based company benefit upon immediate family bereavement.',
            ];
        }

        $minMonths = (int)($typePolicy['min_service_months'] ?? 0);
        if ($minMonths > 0 && $serviceMonths < $minMonths) {
            return [
                'eligible'         => false,
                'status'           => self::STATUS_UNAVAILABLE,
                'entitled_days'    => 0,
                'entitlement_unit' => (string)($typePolicy['entitlement_unit'] ?? 'working_days'),
                'reason'           => sprintf('Requires %d months of service (currently %d).', $minMonths, $serviceMonths),
            ];
        }

        return [
            'eligible'         => true,
            'status'           => $model === 'event_based' ? self::STATUS_EVENT_BASED : self::STATUS_AVAILABLE,
            'entitled_days'    => (int)round((float)($typePolicy['entitlement_days'] ?? ($typePolicy['default_days'] ?? 0))),
            'entitlement_unit' => (string)($typePolicy['entitlement_unit'] ?? 'working_days'),
            'reason'           => 'Active company leave policy.',
        ];
    }

    /**
     * Validate statutory, service, unit, cross-year, event cap, and document rules
     * for a prospective leave filing.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed> Resolved filing attributes (unit, days, policy_version_id, is_paid, is_confidential, allows_cross_year, event_entitlement_id, metadata)
     */
    public function validateFiling(
        int $employeeId,
        int $leaveTypeId,
        string $startDate,
        string $endDate,
        array $metadata = [],
        bool $hasUploadedDoc = false,
        bool $enforceStrictEventMetadata = true
    ): array {
        $empStmt = $this->pdo->prepare("SELECT * FROM employees WHERE id = :id LIMIT 1");
        $empStmt->execute(['id' => $employeeId]);
        $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee || ($employee['employment_status'] ?? '') !== 'Active') {
            throw new DomainException('Active employee record not found.');
        }

        $typePolicy = $this->resolveLeaveTypeWithPolicy($leaveTypeId, $startDate);
        if (!$typePolicy || ($typePolicy['status'] ?? '') !== 'Active') {
            throw new DomainException('Selected leave type is inactive or unavailable.');
        }

        $code = strtoupper(trim((string)($typePolicy['leave_code'] ?? '')));
        $model = (string)($typePolicy['entitlement_model'] ?? 'annual');
        $unit = (string)($typePolicy['entitlement_unit'] ?? 'working_days');
        $isPaid = !empty($typePolicy['is_paid']) ? 1 : 0;
        $isConfidential = !empty($typePolicy['is_confidential']) || in_array($code, ['VAWC', 'SLW'], true);

        $startDt = new DateTimeImmutable($startDate);
        $endDt = new DateTimeImmutable($endDate);
        if ($endDt < $startDt) {
            throw new InvalidArgumentException('Start date must be on or before end date.');
        }

        $days = $unit === 'calendar_days'
            ? ((int)$startDt->diff($endDt)->days + 1)
            : LeaveCalculator::calculateWorkingDays($startDate, $endDate);
        if ($days <= 0) {
            throw new InvalidArgumentException('Selected range contains zero countable leave days (weekends are excluded for working-day leaves).');
        }

        $startYear = (int)$startDt->format('Y');
        $endYear = (int)$endDt->format('Y');
        $rules = $typePolicy['rules_decoded'] ?? [];
        $allowsCrossYear = ($code === self::CODE_MATERNITY)
            || ($unit === 'calendar_days' && $model === 'event_based')
            || !empty($rules['allow_cross_year']);

        if (!$allowsCrossYear && $startYear !== $endYear) {
            throw new DomainException(sprintf(
                'Leave requests for %s cannot span across calendar years (%d to %d). Please submit separate requests per year.',
                $typePolicy['leave_name'],
                $startYear,
                $endYear
            ));
        }

        $eligibility = $this->evaluateEmployeeTypeEligibility($employee, $typePolicy, $startYear, $startDate);

        // Enforce category-specific rules
        if ($code === self::CODE_SIL) {
            if (!$eligibility['eligible']) {
                throw new DomainException((string)$eligibility['reason']);
            }
        } elseif ($code === self::CODE_SOLO_PARENT) {
            if (!$eligibility['eligible']) {
                throw new DomainException((string)$eligibility['reason']);
            }
            if ($days > (int)$eligibility['entitled_days']) {
                throw new DomainException(sprintf('Solo Parent Leave is capped at %d working days per year.', (int)$eligibility['entitled_days']));
            }
        } elseif ($code === self::CODE_SLW) {
            if (!$eligibility['eligible']) {
                throw new DomainException((string)$eligibility['reason']);
            }
            $maxSlw = (int)($rules['max_days'] ?? $typePolicy['entitlement_days'] ?? 60);
            if ($days > $maxSlw) {
                throw new DomainException(sprintf('Special Leave for Women cannot exceed %d calendar days per gynecological surgery event.', $maxSlw));
            }
            if ($enforceStrictEventMetadata && !$hasUploadedDoc && empty($metadata['has_physician_cert'])) {
                throw new DomainException('A certified gynecological physician medical certificate must be uploaded for Special Leave for Women.');
            }
        } elseif ($code === self::CODE_VAWC) {
            $baseDays = (int)($rules['base_days'] ?? $typePolicy['entitlement_days'] ?? 10);
            $extDays = (int)($metadata['vawc_extension_days'] ?? 0);
            $hasProtectionOrder = !empty($metadata['protection_order_ref']);
            if ($extDays > 0 && !$hasProtectionOrder) {
                throw new DomainException('A Protection Order reference (BPO/TPO/PPO) is required to extend VAWC Leave beyond 10 days.');
            }
            $maxTotal = $baseDays + ($hasProtectionOrder ? max(0, $extDays) : 0);
            if ($days > $maxTotal) {
                throw new DomainException(sprintf(
                    'Requested VAWC days (%d) exceed the authorized %d day(s) (10 base + %d protection order extension).',
                    $days,
                    $maxTotal,
                    $hasProtectionOrder ? $extDays : 0
                ));
            }
            if ($enforceStrictEventMetadata && !$hasUploadedDoc && empty($metadata['protection_order_ref']) && empty($metadata['certification_ref'])) {
                throw new DomainException('VAWC Leave requires supporting BPO/TPO/PPO, barangay/prosecutor, or medical certification.');
            }
        } elseif ($code === self::CODE_MATERNITY) {
            $scenario = (string)($metadata['maternity_scenario'] ?? 'live_childbirth');
            $gender = (string)($employee['gender'] ?? '');
            if ($gender === 'Male' && $scenario !== 'father_transfer_allocation') {
                throw new DomainException('Male employees may only apply under Maternity Leave (RA 11210) for allocated father-transfer days (up to 7 days).');
            }
            if ($scenario === 'father_transfer_allocation') {
                $transferMax = (int)($rules['father_transfer_max_days'] ?? 7);
                if ($days > $transferMax) {
                    throw new DomainException("RA 11210 allocated father leave transfer cannot exceed {$transferMax} calendar days.");
                }
                if ($enforceStrictEventMetadata && trim((string)($metadata['mother_allocation_ref'] ?? '')) === '') {
                    throw new DomainException('Please specify the maternal allocation notice or spouse reference for RA 11210 father leave transfer.');
                }
            } elseif ($scenario === 'miscarriage_etp') {
                if (!empty($metadata['transfer_to_father_days']) && (int)$metadata['transfer_to_father_days'] > 0) {
                    throw new DomainException('Allocation of up to 7 maternity leave credits to the child\'s father is not available for miscarriage or emergency termination of pregnancy under RA 11210.');
                }
                if ($days > 60) {
                    throw new DomainException('Maternity Leave for miscarriage or emergency termination of pregnancy is capped at 60 calendar days under RA 11210.');
                }
            } elseif ($scenario === 'solo_parent_childbirth') {
                $spCheck = $this->evaluateSoloParentEligibility($employee, ['min_service_months' => 0, 'entitlement_days' => 15], $startDate);
                if (empty($spCheck['eligible']) && ($spCheck['spic_record']['verification_status'] ?? '') !== 'Verified') {
                    throw new DomainException('The +15 day Solo Parent Maternity extension (120 days total) requires a verified Solo Parent Identification Card (SPIC).');
                }
                $unpaidExt = min(30, max(0, (int)($metadata['unpaid_extension_days'] ?? 0)));
                $transferOut = min(7, max(0, (int)($metadata['transfer_to_father_days'] ?? 0)));
                $netCap = 120 + $unpaidExt - $transferOut;
                if ($days > $netCap) {
                    throw new DomainException("Requested duration ({$days}d) exceeds eligible solo-parent maternity window ({$netCap}d net).");
                }
            } else {
                $unpaidExt = min(30, max(0, (int)($metadata['unpaid_extension_days'] ?? 0)));
                $transferOut = min(7, max(0, (int)($metadata['transfer_to_father_days'] ?? 0)));
                $netCap = 105 + $unpaidExt - $transferOut;
                if ($days > $netCap) {
                    throw new DomainException("Requested duration ({$days}d) exceeds RA 11210 entitlement ({$netCap}d net after optional extension/father transfer).");
                }
            }
        } elseif ($code === self::CODE_PATERNITY) {
            if (($employee['gender'] ?? '') === 'Female') {
                throw new DomainException('RA 8187 Paternity Leave is reserved for married male employees.');
            }
            $usedDeliveries = $this->countPaternityDeliveries($employeeId, $leaveTypeId);
            $maxDeliveries = (int)($typePolicy['max_events_lifetime'] ?? 4);
            if ($usedDeliveries >= $maxDeliveries) {
                throw new DomainException("Maximum RA 8187 lifetime cap of {$maxDeliveries} deliveries has been reached.");
            }
            if ($days > 7) {
                throw new DomainException('RA 8187 Paternity Leave is capped at 7 days per eligible delivery.');
            }
            $deliverySeq = isset($metadata['delivery_sequence']) ? (int)$metadata['delivery_sequence'] : ($usedDeliveries + 1);
            if ($deliverySeq < 1 || $deliverySeq > $maxDeliveries) {
                throw new DomainException("Paternity delivery sequence must be between 1 and {$maxDeliveries}.");
            }
            if ($this->hasDuplicatePaternityDelivery($employeeId, $leaveTypeId, $deliverySeq)) {
                throw new DomainException("A Paternity Leave request or entitlement already exists for delivery #{$deliverySeq}.");
            }
            if ($enforceStrictEventMetadata && trim((string)($metadata['spouse_name'] ?? '')) === '') {
                throw new DomainException('Lawful spouse name is required when filing Paternity Leave under RA 8187.');
            }
        } elseif ($code === self::CODE_BEREAVEMENT) {
            $maxBer = (int)round((float)($typePolicy['entitlement_days'] ?? 3));
            if ($days > $maxBer) {
                throw new DomainException("Bereavement Leave cannot exceed {$maxBer} working days per event.");
            }
            $allowedRels = $rules['allowed_relationships'] ?? ['Spouse', 'Child', 'Parent', 'Sibling', 'Grandparent', 'Parent-in-Law'];
            $rel = trim((string)($metadata['relationship_type'] ?? ''));
            if ($enforceStrictEventMetadata && ($rel === '' || !in_array($rel, $allowedRels, true))) {
                throw new DomainException('Please select an eligible immediate family relationship (' . implode(', ', $allowedRels) . ').');
            }
        }

        // Preserve any pre-attached event_entitlement_id if provided, else leave null (controller/usecase binds/creates it)
        $eventEntitlementId = !empty($metadata['event_entitlement_id']) ? (int)$metadata['event_entitlement_id'] : null;

        return [
            'type_policy'          => $typePolicy,
            'leave_code'           => $code,
            'entitlement_model'    => $model,
            'entitlement_unit'     => $unit,
            'number_of_days'       => $days,
            'is_paid'              => $isPaid,
            'is_confidential'      => $isConfidential ? 1 : 0,
            'allows_cross_year'    => $allowsCrossYear,
            'policy_version_id'    => $typePolicy['policy_version_id'] ?? ($typePolicy['active_policy_version_id'] ?? null),
            'event_entitlement_id' => $eventEntitlementId,
            'eligibility'          => $eligibility,
            'metadata'             => $metadata,
        ];
    }

    /**
     * Ensure an employee has a matching leave_event_entitlements row for event-based filings.
     *
     * @param array<string, mixed> $resolvedFiling
     */
    public function ensureEventEntitlementForRequest(
        int $employeeId,
        int $leaveTypeId,
        string $startDate,
        string $endDate,
        array $resolvedFiling,
        ?int $actorUserId = null
    ): ?int {
        if (($resolvedFiling['entitlement_model'] ?? 'annual') !== 'event_based') {
            return null;
        }
        if (!self::tableExists($this->pdo, 'leave_event_entitlements')) {
            return null;
        }
        if (!empty($resolvedFiling['event_entitlement_id'])) {
            return (int)$resolvedFiling['event_entitlement_id'];
        }

        $code = strtoupper((string)($resolvedFiling['leave_code'] ?? 'EVENT'));
        $meta = is_array($resolvedFiling['metadata'] ?? null) ? $resolvedFiling['metadata'] : [];
        $typePolicy = $resolvedFiling['type_policy'] ?? [];
        $days = (int)($resolvedFiling['number_of_days'] ?? 1);

        $scenario = match ($code) {
            self::CODE_MATERNITY   => (string)($meta['maternity_scenario'] ?? 'live_childbirth'),
            self::CODE_PATERNITY   => 'spouse_delivery',
            self::CODE_SLW         => 'gynecological_surgery',
            self::CODE_VAWC        => 'vawc_incident',
            self::CODE_BEREAVEMENT => 'bereavement_immediate_family',
            default                => 'company_event',
        };

        $entitledDays = (float)$days;
        if ($code === self::CODE_MATERNITY) {
            $entitledDays = match ($scenario) {
                'solo_parent_childbirth'     => 120.0,
                'miscarriage_etp'            => 60.0,
                'father_transfer_allocation' => 7.0,
                default                      => 105.0,
            };
        } elseif ($code === self::CODE_PATERNITY) {
            $entitledDays = 7.0;
        } elseif ($code === self::CODE_SLW) {
            $entitledDays = 60.0;
        } elseif ($code === self::CODE_VAWC) {
            $entitledDays = 10.0 + max(0.0, (float)($meta['vawc_extension_days'] ?? 0));
        } else {
            $entitledDays = max((float)$days, (float)($typePolicy['entitlement_days'] ?? $days));
        }

        $unpaidExt = $code === self::CODE_MATERNITY ? (float)min(30, max(0, (int)($meta['unpaid_extension_days'] ?? 0))) : 0.0;
        $transferOut = $code === self::CODE_MATERNITY ? (float)min(7, max(0, (int)($meta['transfer_to_father_days'] ?? 0))) : 0.0;

        $deliverySeq = null;
        if ($code === self::CODE_PATERNITY) {
            $deliverySeq = isset($meta['delivery_sequence'])
                ? (int)$meta['delivery_sequence']
                : ($this->countPaternityDeliveries($employeeId, $leaveTypeId) + 1);
        }

        $eventCode = sprintf('%s-%s-%s', $code ?: 'EVT', date('Ymd', strtotime($startDate)), strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)));
        $eventDate = (string)($meta['event_date'] ?? $startDate);
        $verificationStatus = 'PendingVerification';
        if ($code === self::CODE_PATERNITY) {
            $qual = $this->findLatestQualification($employeeId, 'paternity_marriage');
            if ($qual !== null && ($qual['verification_status'] ?? '') === 'Verified') {
                $verificationStatus = 'Verified';
            }
        } elseif (in_array($code, [self::CODE_BEREAVEMENT, self::CODE_VAWC, self::CODE_SLW, self::CODE_MATERNITY], true)) {
            $verificationStatus = 'Active';
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO leave_event_entitlements (
                employee_id, leave_type_id, policy_version_id, event_code, event_scenario,
                event_date, delivery_sequence, spouse_name, relationship_type,
                entitled_days, transferred_out_days, unpaid_extension_days,
                used_days, pending_days, remaining_days, valid_from, valid_to,
                verification_status, is_confidential, metadata_json, created_by
            ) VALUES (
                :emp_id, :lt_id, :pv_id, :ecode, :scenario,
                :edate, :dseq, :spouse, :rel,
                :entitled, :tout, :uext,
                0.00, :pending, :rem, :vfrom, :vto,
                :vstatus, :conf, :meta, :created_by
            )
        ");

        $netAvailable = max(0.0, $entitledDays + $unpaidExt - $transferOut);
        $stmt->execute([
            'emp_id'     => $employeeId,
            'lt_id'      => $leaveTypeId,
            'pv_id'      => $resolvedFiling['policy_version_id'] ?? null,
            'ecode'      => $eventCode,
            'scenario'   => $scenario,
            'edate'      => $eventDate,
            'dseq'       => $deliverySeq,
            'spouse'     => isset($meta['spouse_name']) ? substr(trim((string)$meta['spouse_name']), 0, 120) : null,
            'rel'        => isset($meta['relationship_type']) ? substr(trim((string)$meta['relationship_type']), 0, 60) : null,
            'entitled'   => $entitledDays,
            'tout'       => $transferOut,
            'uext'       => $unpaidExt,
            'pending'    => (float)$days,
            'rem'        => $netAvailable,
            'vfrom'      => $startDate,
            'vto'        => $endDate,
            'vstatus'    => $verificationStatus,
            'conf'       => !empty($resolvedFiling['is_confidential']) ? 1 : 0,
            'meta'       => !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_by' => $actorUserId,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Find an employee's most recent qualification row of a specific type.
     *
     * @return array<string, mixed>|null
     */
    public function findLatestQualification(int $employeeId, string $qualificationType): ?array
    {
        if ($employeeId <= 0 || !self::tableExists($this->pdo, 'employee_leave_qualifications')) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM employee_leave_qualifications
            WHERE employee_id = :emp_id AND qualification_type = :qtype
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(['emp_id' => $employeeId, 'qtype' => $qualificationType]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Count used/active Paternity Leave deliveries (RA 8187 limit = 4).
     */
    public function countPaternityDeliveries(int $employeeId, int $leaveTypeId): int
    {
        if ($employeeId <= 0) {
            return 0;
        }

        if (self::tableExists($this->pdo, 'leave_event_entitlements')) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM leave_event_entitlements
                WHERE employee_id = :emp_id AND leave_type_id = :lt_id
                  AND verification_status IN ('Verified', 'Active', 'Completed', 'PendingVerification')
            ");
            $stmt->execute(['emp_id' => $employeeId, 'lt_id' => $leaveTypeId]);
            $evCount = (int)$stmt->fetchColumn();
            if ($evCount > 0) {
                return $evCount;
            }
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM leave_requests
            WHERE employee_id = :emp_id AND leave_type_id = :lt_id
              AND status IN ('Approved', 'Pending')
        ");
        $stmt->execute(['emp_id' => $employeeId, 'lt_id' => $leaveTypeId]);
        return (int)$stmt->fetchColumn();
    }

    private function hasDuplicatePaternityDelivery(int $employeeId, int $leaveTypeId, int $deliverySequence): bool
    {
        if (!self::tableExists($this->pdo, 'leave_event_entitlements')) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM leave_event_entitlements
            WHERE employee_id = :emp_id AND leave_type_id = :lt_id
              AND delivery_sequence = :dseq
              AND verification_status IN ('Verified', 'Active', 'Completed', 'PendingVerification')
        ");
        $stmt->execute([
            'emp_id' => $employeeId,
            'lt_id'  => $leaveTypeId,
            'dseq'   => $deliverySequence,
        ]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Check whether employee has an active paid Vacation Leave >= 5 days counting as SIL equivalent.
     *
     * @return array<string, mixed>|null
     */
    private function findQualifyingEquivalentVacation(int $employeeId, int $year): ?array
    {
        if (self::tableExists($this->pdo, 'leave_policy_versions')) {
            $stmt = $this->pdo->prepare("
                SELECT lt.id, lt.leave_name, lt.default_days,
                       COALESCE(lb.allocated_days, lt.default_days) AS effective_days
                FROM leave_types lt
                LEFT JOIN leave_policy_versions lpv ON lpv.id = lt.active_policy_version_id
                LEFT JOIN leave_balances lb ON lb.leave_type_id = lt.id AND lb.employee_id = :emp_id AND lb.year = :yr
                WHERE lt.status = 'Active'
                  AND lt.is_paid = 1
                  AND (COALESCE(lpv.counts_as_sil_equivalent, 0) = 1 OR lt.leave_code = 'VL')
                  AND COALESCE(lb.allocated_days, lt.default_days) >= 5
                LIMIT 1
            ");
            $stmt->execute(['emp_id' => $employeeId, 'yr' => $year]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        return null;
    }

    /**
     * Merge base leave_types row with its active leave_policy_versions row.
     *
     * @param array<string, mixed> $lt
     * @param array<string, mixed>|null $policy
     * @return array<string, mixed>
     */
    private function mergeTypeAndPolicy(array $lt, ?array $policy): array
    {
        $code = strtoupper(trim((string)($lt['leave_code'] ?? '')));
        $model = (string)($policy['entitlement_model'] ?? ($lt['entitlement_model'] ?? 'annual'));
        $unit = (string)($policy['entitlement_unit'] ?? ($lt['default_unit'] ?? 'working_days'));
        $isConfidential = !empty($policy['is_confidential']) || !empty($lt['is_confidential']) || in_array($code, ['VAWC', 'SLW'], true);
        $rulesRaw = $policy['rules_json'] ?? null;
        $rulesDecoded = is_string($rulesRaw) && $rulesRaw !== '' ? (json_decode($rulesRaw, true) ?: []) : (is_array($rulesRaw) ? $rulesRaw : []);

        return [
            'id'                       => (int)$lt['id'],
            'leave_name'               => (string)$lt['leave_name'],
            'leave_code'               => $code !== '' ? $code : null,
            'description'              => (string)($lt['description'] ?? ''),
            'status'                   => (string)($lt['status'] ?? 'Active'),
            'classification'           => (string)($policy['classification'] ?? ($lt['classification'] ?? 'Company')),
            'benefit_category'         => (string)($lt['benefit_category'] ?? 'CompanyAnnual'),
            'entitlement_model'        => $model,
            'benefit_tier'             => (string)($policy['benefit_tier'] ?? ($model === 'unpaid' ? 'unpaid' : 'company_benefit')),
            'sector_scope'             => (string)($policy['sector_scope'] ?? 'all'),
            'legal_reference'          => (string)($policy['legal_reference'] ?? ($lt['legal_reference'] ?? '')),
            'default_days'             => (int)($lt['default_days'] ?? 0),
            'entitlement_days'         => (float)($policy['entitlement_days'] ?? ($lt['default_days'] ?? 0)),
            'entitlement_unit'         => $unit,
            'default_unit'             => $unit,
            'is_paid'                  => isset($policy['is_paid']) ? (int)$policy['is_paid'] : (int)($lt['is_paid'] ?? 1),
            'is_confidential'          => $isConfidential ? 1 : 0,
            'statutory_protected'      => (int)($lt['statutory_protected'] ?? 0),
            'policy_version_id'        => isset($policy['id']) ? (int)$policy['id'] : (isset($lt['active_policy_version_id']) ? (int)$lt['active_policy_version_id'] : null),
            'version_number'           => isset($policy['version_number']) ? (int)$policy['version_number'] : 1,
            'policy_code'              => (string)($policy['policy_code'] ?? ''),
            'effective_from'           => (string)($policy['effective_from'] ?? '2025-01-01'),
            'effective_to'             => $policy['effective_to'] ?? null,
            'carry_over_allowed'       => (int)($policy['carry_over_allowed'] ?? 0),
            'max_carry_over_days'      => (float)($policy['max_carry_over_days'] ?? 0),
            'cash_convertible'         => (int)($policy['cash_convertible'] ?? ($code === 'SIL' ? 1 : 0)),
            'cash_conversion_max_days' => (float)($policy['cash_conversion_max_days'] ?? 0),
            'min_service_months'       => (int)($policy['min_service_months'] ?? 0),
            'service_window_months'    => isset($policy['service_window_months']) ? (int)$policy['service_window_months'] : null,
            'counts_as_sil_equivalent' => (int)($policy['counts_as_sil_equivalent'] ?? ($code === 'VL' ? 1 : 0)),
            'stack_with_equivalent'    => (int)($policy['stack_with_equivalent'] ?? 0),
            'max_events_lifetime'      => isset($policy['max_events_lifetime']) ? (int)$policy['max_events_lifetime'] : ($code === 'PATERNITY' ? 4 : null),
            'max_extension_days'       => (float)($policy['max_extension_days'] ?? 0),
            'requires_documentation'   => (int)($policy['requires_documentation'] ?? 0),
            'document_requirements'    => (string)($policy['document_requirements'] ?? ''),
            'requires_qualification'   => (int)($policy['requires_qualification'] ?? 0),
            'qualification_type'       => $policy['qualification_type'] ?? null,
            'rules_decoded'            => $rulesDecoded,
        ];
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $key = $driver . ':' . $table;
        if (array_key_exists($key, self::$tableCache)) {
            return self::$tableCache[$key];
        }

        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :t");
                $stmt->execute(['t' => $table]);
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
                $stmt->execute(['t' => $table]);
            }
            $exists = ((int)$stmt->fetchColumn()) > 0;
        } catch (Throwable) {
            $exists = false;
        }

        self::$tableCache[$key] = $exists;
        return $exists;
    }

    public static function resetCache(): void
    {
        self::$tableCache = [];
    }
}
