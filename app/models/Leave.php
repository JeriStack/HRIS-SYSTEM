<?php
/**
 * Leave Management Model
 * Handles leave requests, transaction-safe approvals, leave balances, leave types, and overlap detection
 * Employee HRIS & Leave Management System - Phase 8 & Phase 9
 */

namespace App\Models;

use Core\Database;
use App\Services\LeaveCalculator;
use App\Services\LeavePolicyEngine;
use App\Services\LeaveLedgerService;
use App\Services\ConfidentialLeaveService;
use App\Realtime\EventAuthorizer;
use PDO;
use Exception;
use RuntimeException;

class Leave
{
    private PDO $pdo;
    private ?string $lastError = null;
    private ?string $lastErrorCode = null;
    /** @var array<string, bool> */
    private array $schemaCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    private function hasTable(string $table): bool
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $key = "tbl:{$driver}:{$table}";
        if (array_key_exists($key, $this->schemaCache)) {
            return $this->schemaCache[$key];
        }
        try {
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :t");
                $stmt->execute(['t' => $table]);
            } else {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
                $stmt->execute(['t' => $table]);
            }
            $this->schemaCache[$key] = ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable) {
            $this->schemaCache[$key] = false;
        }
        return $this->schemaCache[$key];
    }

    private function hasTableColumn(string $table, string $column): bool
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $key = "col:{$driver}:{$table}.{$column}";
        if (array_key_exists($key, $this->schemaCache)) {
            return $this->schemaCache[$key];
        }
        try {
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->query("PRAGMA table_info(`{$table}`)");
                $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $found = false;
                foreach ($cols as $c) {
                    if (strcasecmp((string)($c['name'] ?? ''), $column) === 0) {
                        $found = true;
                        break;
                    }
                }
                $this->schemaCache[$key] = $found;
            } else {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
                $stmt->execute(['t' => $table, 'c' => $column]);
                $this->schemaCache[$key] = ((int)$stmt->fetchColumn()) > 0;
            }
        } catch (\Throwable) {
            $this->schemaCache[$key] = false;
        }
        return $this->schemaCache[$key];
    }

    /**
     * Get the last safe user-facing error message from a failed operation.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Get the last machine-readable error code from a failed operation.
     */
    public function getLastErrorCode(): ?string
    {
        return $this->lastErrorCode;
    }

    /**
     * Retrieve leave requests with joined employee, department, and leave type data.
     * Supports filtering by status, employee, date range, and department.
     */
    public function getAllRequests(
        ?string $status = null, 
        ?int $empId = null, 
        ?int $limit = 100,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $deptId = null
    ): array {
        $hasPhCols = $this->hasTableColumn('leave_types', 'leave_code');
        $extraCols = $hasPhCols
            ? ", lt.leave_code, lt.classification, lt.benefit_category, lt.entitlement_model, lt.legal_reference, lt.default_unit, lt.is_paid AS type_is_paid, lt.is_confidential AS type_is_confidential"
            : "";

        $sql = "
            SELECT lr.*, 
                   e.employee_no, e.first_name, e.last_name, e.email,
                   d.department_name,
                   lt.leave_name, lt.description as leave_description{$extraCols},
                   u.username as reviewer_username
            FROM leave_requests lr
            INNER JOIN employees e ON lr.employee_id = e.id
            INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN users u ON lr.approved_by = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($status && in_array($status, ['Pending', 'Approved', 'Rejected', 'Cancelled'], true)) {
            $sql .= " AND lr.status = :status";
            $params['status'] = $status;
        }

        if ($empId) {
            $sql .= " AND lr.employee_id = :emp_id";
            $params['emp_id'] = $empId;
        }

        if ($startDate) {
            $sql .= " AND lr.end_date >= :start_filter";
            $params['start_filter'] = $startDate;
        }

        if ($endDate) {
            $sql .= " AND lr.start_date <= :end_filter";
            $params['end_filter'] = $endDate;
        }

        if ($deptId) {
            $sql .= " AND e.department_id = :dept_id";
            $params['dept_id'] = $deptId;
        }

        $sql .= " ORDER BY lr.id DESC";

        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count pending leave requests submitted by a specific employee.
     */
    public function countPendingByEmployee(int $employeeId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM leave_requests
            WHERE employee_id = :emp_id AND status = 'Pending'
        ");
        $stmt->execute(['emp_id' => $employeeId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Retrieve the most recent leave requests belonging to a single employee.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentByEmployee(int $employeeId, int $limit = 3): array
    {
        if ($employeeId <= 0) {
            return [];
        }

        $safeLimit = max(1, min(20, $limit));
        $hasPhCols = $this->hasTableColumn('leave_types', 'leave_code')
            && $this->hasTableColumn('leave_requests', 'is_confidential');
        $extra = $hasPhCols
            ? ", lr.entitlement_unit, lr.is_paid, lr.is_confidential, lr.info_request_status, lt.leave_code, lt.entitlement_model, lt.is_confidential AS type_is_confidential"
            : "";

        $stmt = $this->pdo->prepare("
            SELECT lr.id, lr.start_date, lr.end_date, lr.number_of_days, lr.status,
                   lr.reason, lr.rejection_reason, lr.created_at, lr.approved_at, lt.leave_name{$extra}
            FROM leave_requests lr
            INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = :emp_id
            ORDER BY lr.created_at DESC, lr.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':emp_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve upcoming Approved leave requests for a single employee sorted by nearest start date.
     * Excludes Pending, Rejected, Cancelled, and past requests.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUpcomingApprovedByEmployee(int $employeeId, ?string $fromDate = null, int $limit = 3): array
    {
        if ($employeeId <= 0) {
            return [];
        }

        $safeDate = ($fromDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) === 1)
            ? $fromDate
            : date('Y-m-d');
        $safeLimit = max(1, min(20, $limit));
        $hasPhCols = $this->hasTableColumn('leave_types', 'leave_code')
            && $this->hasTableColumn('leave_requests', 'is_confidential');
        $extra = $hasPhCols
            ? ", lr.entitlement_unit, lr.is_paid, lr.is_confidential, lt.leave_code, lt.is_confidential AS type_is_confidential"
            : "";

        $stmt = $this->pdo->prepare("
            SELECT lr.id, lr.start_date, lr.end_date, lr.number_of_days, lr.status,
                   lr.approved_at, lr.created_at, lt.leave_name{$extra}
            FROM leave_requests lr
            INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = :emp_id
              AND lr.status = 'Approved'
              AND lr.start_date >= :from_date
            ORDER BY lr.start_date ASC, lr.id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':emp_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':from_date', $safeDate, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count all pending leave requests across the organization (for HR/Admin review queue).
     */
    public function countPendingAll(): int
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(*)
            FROM leave_requests
            WHERE status = 'Pending'
        ");
        return $stmt ? (int)$stmt->fetchColumn() : 0;
    }

    /**
     * Find a single leave request by ID with complete relationship data.
     */
    public function getRequestById(int $id): ?array
    {
        $hasPhCols = $this->hasTableColumn('leave_types', 'leave_code');
        $extra = $hasPhCols
            ? ", lt.leave_code, lt.classification, lt.benefit_category, lt.entitlement_model, lt.legal_reference, lt.default_unit, lt.is_paid AS type_is_paid, lt.is_confidential AS type_is_confidential, e.date_hired, e.gender, e.civil_status"
            : "";
        $policyJoin = $this->hasTable('leave_policy_versions')
            ? " LEFT JOIN leave_policy_versions lpv ON lpv.id = lr.policy_version_id "
            : "";
        $policySelect = $this->hasTable('leave_policy_versions')
            ? ", lpv.policy_code, lpv.version_number AS policy_version_number, lpv.benefit_tier, lpv.document_requirements"
            : "";

        $stmt = $this->pdo->prepare("
            SELECT lr.*, 
                   e.employee_no, e.first_name, e.last_name, e.email,
                   d.department_name,
                   lt.leave_name, lt.description as leave_description{$extra}{$policySelect},
                   u.username as reviewer_username
            FROM leave_requests lr
            INNER JOIN employees e ON lr.employee_id = e.id
            INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN users u ON lr.approved_by = u.id
            {$policyJoin}
            WHERE lr.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get all active leave types (for employee dropdowns).
     */
    public function getLeaveTypes(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM leave_types WHERE status = 'Active' ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all leave types (both Active and Inactive) for HR administration with usage statistics.
     */
    public function getAllLeaveTypes(?string $status = null): array
    {
        $sql = "
            SELECT lt.*, 
                   (SELECT COUNT(*) FROM leave_requests WHERE leave_type_id = lt.id) as request_count,
                   (SELECT COUNT(*) FROM leave_balances WHERE leave_type_id = lt.id) as balance_count
            FROM leave_types lt
            WHERE 1=1
        ";
        $params = [];
        if ($status && in_array($status, ['Active', 'Inactive'], true)) {
            $sql .= " AND lt.status = :status";
            $params['status'] = $status;
        }
        $sql .= " ORDER BY lt.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find leave type by ID.
     */
    public function getLeaveTypeById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM leave_types WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Check if a leave type name is unique across records (excluding self on update).
     */
    public function isLeaveTypeNameUnique(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM leave_types WHERE LOWER(leave_name) = LOWER(:name)";
        $params = ['name' => trim($name)];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() === false;
    }

    /**
     * Create a new leave type configuration.
     */
    public function createLeaveType(array $data): int
    {
        $hasExtended = $this->hasTableColumn('leave_types', 'leave_code')
            && $this->hasTableColumn('leave_types', 'entitlement_model');

        $name = trim((string)($data['leave_name'] ?? ''));
        $desc = !empty($data['description']) ? trim((string)$data['description']) : null;
        $days = (int)($data['default_days'] ?? 15);
        $rawStatus = (string)($data['status'] ?? 'Active');
        $status = in_array($rawStatus, ['Active', 'Inactive'], true) ? $rawStatus : 'Active';

        if (!$hasExtended) {
            $stmt = $this->pdo->prepare("
                INSERT INTO leave_types (leave_name, description, default_days, status)
                VALUES (:name, :desc, :days, :status)
            ");
            $stmt->execute([
                'name'   => $name,
                'desc'   => $desc,
                'days'   => $days,
                'status' => $status,
            ]);
            return (int)$this->pdo->lastInsertId();
        }

        $rawCode = strtoupper(trim((string)($data['leave_code'] ?? '')));
        $leaveCode = $rawCode !== '' ? preg_replace('/[^A-Z0-9_]/', '_', $rawCode) : null;
        $rawClassification = (string)($data['classification'] ?? 'Company');
        $classification = in_array($rawClassification, ['Statutory', 'Company'], true)
            ? $rawClassification
            : 'Company';
        $rawModel = (string)($data['entitlement_model'] ?? 'annual');
        $entitlementModel = in_array($rawModel, ['annual', 'event_based', 'unpaid'], true)
            ? $rawModel
            : 'annual';
        $allowedCategories = ['StatutoryAnnual', 'StatutoryEvent', 'CompanyAnnual', 'CompanyEvent', 'Unpaid'];
        $defaultCategory = $entitlementModel === 'unpaid'
            ? 'Unpaid'
            : ($classification === 'Statutory'
                ? ($entitlementModel === 'event_based' ? 'StatutoryEvent' : 'StatutoryAnnual')
                : ($entitlementModel === 'event_based' ? 'CompanyEvent' : 'CompanyAnnual'));
        $rawCategory = (string)($data['benefit_category'] ?? '');
        $benefitCategory = in_array($rawCategory, $allowedCategories, true)
            ? $rawCategory
            : $defaultCategory;
        $rawUnit = (string)($data['default_unit'] ?? 'working_days');
        $defaultUnit = in_array($rawUnit, ['working_days', 'calendar_days'], true)
            ? $rawUnit
            : 'working_days';
        $isPaid = $entitlementModel === 'unpaid' ? 0 : (isset($data['is_paid']) ? (!empty($data['is_paid']) ? 1 : 0) : 1);
        $isConfidential = !empty($data['is_confidential']) ? 1 : 0;
        $statutoryProtected = !empty($data['statutory_protected']) || $classification === 'Statutory' ? 1 : 0;
        $legalRef = !empty($data['legal_reference']) ? trim((string)$data['legal_reference']) : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO leave_types (
                leave_name, leave_code, description, classification, benefit_category,
                entitlement_model, legal_reference, default_days, default_unit,
                is_paid, is_confidential, statutory_protected, status
            ) VALUES (
                :name, :code, :desc, :classification, :category,
                :model, :legal_ref, :days, :unit,
                :is_paid, :is_conf, :stat_prot, :status
            )
        ");
        $stmt->execute([
            'name'           => $name,
            'code'           => $leaveCode,
            'desc'           => $desc,
            'classification' => $classification,
            'category'       => $benefitCategory,
            'model'          => $entitlementModel,
            'legal_ref'      => $legalRef,
            'days'           => $days,
            'unit'           => $defaultUnit,
            'is_paid'        => $isPaid,
            'is_conf'        => $isConfidential,
            'stat_prot'      => $statutoryProtected,
            'status'         => $status,
        ]);
        $typeId = (int)$this->pdo->lastInsertId();

        if ($typeId > 0 && $this->hasTable('leave_policy_versions')) {
            $actorId = !empty($data['actor_user_id']) ? (int)$data['actor_user_id'] : null;
            $this->createPolicyVersion($typeId, array_merge($data, [
                'leave_code'        => $leaveCode,
                'classification'    => $classification,
                'entitlement_model' => $entitlementModel,
                'legal_reference'   => $legalRef,
                'entitlement_days'  => $data['entitlement_days'] ?? $days,
                'entitlement_unit'  => $data['entitlement_unit'] ?? $defaultUnit,
                'is_paid'           => $isPaid,
                'is_confidential'   => $isConfidential,
                'approval_status'   => 'Active',
                'change_notes'      => $data['change_notes'] ?? 'Initial leave policy v1 created.',
            ]), $actorId, true);
        }

        return $typeId;
    }

    /**
     * Update an existing leave type configuration (preserving historical records
     * via policy versions when policy governance is enabled).
     */
    public function updateLeaveType(int $id, array $data): bool
    {
        $hasExtended = $this->hasTableColumn('leave_types', 'leave_code')
            && $this->hasTableColumn('leave_types', 'entitlement_model');

        $name = trim((string)($data['leave_name'] ?? ''));
        $desc = !empty($data['description']) ? trim((string)$data['description']) : null;
        $days = (int)($data['default_days'] ?? 15);
        $rawStatus = (string)($data['status'] ?? 'Active');
        $status = in_array($rawStatus, ['Active', 'Inactive'], true) ? $rawStatus : 'Active';

        if (!$hasExtended) {
            $stmt = $this->pdo->prepare("
                UPDATE leave_types
                SET leave_name = :name,
                    description = :desc,
                    default_days = :days,
                    status = :status
                WHERE id = :id
            ");
            return $stmt->execute([
                'name'   => $name,
                'desc'   => $desc,
                'days'   => $days,
                'status' => $status,
                'id'     => $id,
            ]);
        }

        $current = $this->getLeaveTypeById($id);
        if (!$current) {
            return false;
        }

        $rawCode = array_key_exists('leave_code', $data)
            ? strtoupper(trim((string)$data['leave_code']))
            : strtoupper(trim((string)($current['leave_code'] ?? '')));
        $leaveCode = $rawCode !== '' ? preg_replace('/[^A-Z0-9_]/', '_', $rawCode) : null;
        $rawClassification = (string)($data['classification'] ?? ($current['classification'] ?? 'Company'));
        $classification = in_array($rawClassification, ['Statutory', 'Company'], true)
            ? $rawClassification
            : 'Company';
        $rawModel = (string)($data['entitlement_model'] ?? ($current['entitlement_model'] ?? 'annual'));
        $entitlementModel = in_array($rawModel, ['annual', 'event_based', 'unpaid'], true)
            ? $rawModel
            : 'annual';
        $allowedCategories = ['StatutoryAnnual', 'StatutoryEvent', 'CompanyAnnual', 'CompanyEvent', 'Unpaid'];
        $rawCategory = (string)($data['benefit_category'] ?? ($current['benefit_category'] ?? ''));
        $benefitCategory = in_array($rawCategory, $allowedCategories, true)
            ? $rawCategory
            : (string)($current['benefit_category'] ?? 'CompanyAnnual');
        $rawUnit = (string)($data['default_unit'] ?? ($current['default_unit'] ?? 'working_days'));
        $defaultUnit = in_array($rawUnit, ['working_days', 'calendar_days'], true)
            ? $rawUnit
            : 'working_days';
        $isPaid = $entitlementModel === 'unpaid'
            ? 0
            : (array_key_exists('is_paid', $data) ? (!empty($data['is_paid']) ? 1 : 0) : (int)($current['is_paid'] ?? 1));
        $isConfidential = array_key_exists('is_confidential', $data)
            ? (!empty($data['is_confidential']) ? 1 : 0)
            : (int)($current['is_confidential'] ?? 0);
        $statutoryProtected = array_key_exists('statutory_protected', $data)
            ? (!empty($data['statutory_protected']) ? 1 : 0)
            : (int)($current['statutory_protected'] ?? 0);
        $legalRef = array_key_exists('legal_reference', $data)
            ? (!empty($data['legal_reference']) ? trim((string)$data['legal_reference']) : null)
            : ($current['legal_reference'] ?? null);

        $stmt = $this->pdo->prepare("
            UPDATE leave_types
            SET leave_name = :name,
                leave_code = :code,
                description = :desc,
                classification = :classification,
                benefit_category = :category,
                entitlement_model = :model,
                legal_reference = :legal_ref,
                default_days = :days,
                default_unit = :unit,
                is_paid = :is_paid,
                is_confidential = :is_conf,
                statutory_protected = :stat_prot,
                status = :status
            WHERE id = :id
        ");
        $ok = $stmt->execute([
            'name'           => $name,
            'code'           => $leaveCode,
            'desc'           => $desc,
            'classification' => $classification,
            'category'       => $benefitCategory,
            'model'          => $entitlementModel,
            'legal_ref'      => $legalRef,
            'days'           => $days,
            'unit'           => $defaultUnit,
            'is_paid'        => $isPaid,
            'is_conf'        => $isConfidential,
            'stat_prot'      => $statutoryProtected,
            'status'         => $status,
            'id'             => $id,
        ]);

        if ($ok && !empty($data['create_new_policy_version']) && $this->hasTable('leave_policy_versions')) {
            $actorId = !empty($data['actor_user_id']) ? (int)$data['actor_user_id'] : null;
            $activateNow = !empty($data['activate_immediately']) || (($data['policy_approval_status'] ?? '') === 'Active');
            $this->createPolicyVersion($id, array_merge($data, [
                'leave_code'        => $leaveCode,
                'classification'    => $classification,
                'entitlement_model' => $entitlementModel,
                'legal_reference'   => $legalRef,
                'entitlement_days'  => $data['entitlement_days'] ?? $days,
                'entitlement_unit'  => $data['entitlement_unit'] ?? $defaultUnit,
                'is_paid'           => $isPaid,
                'is_confidential'   => $isConfidential,
                'approval_status'   => $activateNow ? 'Active' : ($data['policy_approval_status'] ?? 'Draft'),
            ]), $actorId, $activateNow);
        }

        return $ok;
    }

    /**
     * Toggle leave type status between Active and Inactive (Soft deactivate/activate).
     * Preserves all historical leave requests and existing balances.
     */
    public function toggleLeaveTypeStatus(int $id): bool
    {
        $type = $this->getLeaveTypeById($id);
        if (!$type) {
            return false;
        }
        $newStatus = ($type['status'] === 'Active') ? 'Inactive' : 'Active';
        $stmt = $this->pdo->prepare("UPDATE leave_types SET status = :status WHERE id = :id");
        return $stmt->execute(['status' => $newStatus, 'id' => $id]);
    }

    /**
     * Detect overlapping leave requests for the same employee.
     * Evaluates inclusive dates: (new_start <= existing_end AND new_end >= existing_start).
     * Considers Pending and Approved requests; ignores Rejected and Cancelled.
     */
    public function getOverlappingRequest(
        int $employeeId, 
        string $startDate, 
        string $endDate, 
        ?int $excludeRequestId = null
    ): ?array {
        $sql = "
            SELECT lr.*, lt.leave_name
            FROM leave_requests lr
            INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = :emp_id
              AND lr.status IN ('Pending', 'Approved')
              AND lr.start_date <= :new_end
              AND lr.end_date >= :new_start
        ";
        $params = [
            'emp_id'    => $employeeId,
            'new_start' => $startDate,
            'new_end'   => $endDate,
        ];

        if ($excludeRequestId) {
            $sql .= " AND lr.id != :exclude_id";
            $params['exclude_id'] = $excludeRequestId;
        }

        $sql .= " ORDER BY lr.id DESC LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Check if an overlapping leave request exists for an employee.
     */
    public function hasOverlap(
        int $employeeId, 
        string $startDate, 
        string $endDate, 
        ?int $excludeRequestId = null
    ): bool {
        return $this->getOverlappingRequest($employeeId, $startDate, $endDate, $excludeRequestId) !== null;
    }

    /**
     * Get single leave balance record.
     */
    public function getBalance(int $empId, int $leaveTypeId, int $year): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM leave_balances
            WHERE employee_id = :emp_id AND leave_type_id = :lt_id AND year = :year
            LIMIT 1
        ");
        $stmt->execute(['emp_id' => $empId, 'lt_id' => $leaveTypeId, 'year' => $year]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get leave balances for a specific employee and year.
     */
    public function getBalancesByEmployee(int $empId, ?int $year = null): array
    {
        $year = $year ?: (int)date('Y');
        $hasExtended = $this->hasTableColumn('leave_types', 'leave_code')
            && $this->hasTableColumn('leave_balances', 'converted_days');

        $extraCols = $hasExtended
            ? ", lt.leave_code, lt.classification, lt.benefit_category, lt.entitlement_model, lt.legal_reference, lt.default_unit, lt.is_paid, lt.is_confidential, lt.statutory_protected, lt.active_policy_version_id"
            : "";

        $stmt = $this->pdo->prepare("
            SELECT lb.*, lt.leave_name, lt.description{$extraCols}
            FROM leave_balances lb
            INNER JOIN leave_types lt ON lb.leave_type_id = lt.id
            WHERE lb.employee_id = :emp_id AND lb.year = :year
            ORDER BY lt.id ASC
        ");
        $stmt->execute(['emp_id' => $empId, 'year' => $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending requested days for a specific leave type and year (for reservation checks).
     */
    public function getPendingDays(int $employeeId, int $leaveTypeId, int $year): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(number_of_days), 0) as total_pending
            FROM leave_requests
            WHERE employee_id = :emp_id
              AND leave_type_id = :lt_id
              AND YEAR(start_date) = :year
              AND status = 'Pending'
        ");
        $stmt->execute([
            'emp_id' => $employeeId,
            'lt_id'  => $leaveTypeId,
            'year'   => $year,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total_pending'] ?? 0);
    }

    /**
     * Initialize missing balance quotas for an employee for a specific year.
     * Inserts default quotas from active leave types.
     */
    /**
     * Initialize annual leave balances for an employee in a given year.
     * Respects Philippine policy classifications: skips event-based/unpaid types,
     * checks SIL 1-year + VL offset rules and Solo Parent SPIC verification,
     * and records an immutable ledger entry when supported.
     */
    public function initializeBalancesForEmployee(int $employeeId, int $year, ?int $actorUserId = null): int
    {
        $leaveTypes = $this->getLeaveTypes();
        $hasExtendedTypes = $this->hasTableColumn('leave_types', 'entitlement_model');
        $hasPolicyCols = $this->hasTableColumn('leave_balances', 'policy_version_id');
        $hasLedger = $this->hasTable('leave_balance_transactions');

        $employee = null;
        if ($hasExtendedTypes) {
            $eStmt = $this->pdo->prepare("SELECT * FROM employees WHERE id = :id LIMIT 1");
            $eStmt->execute(['id' => $employeeId]);
            $employee = $eStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $policyEngine = $hasExtendedTypes ? new \App\Services\LeavePolicyEngine($this->pdo) : null;
        $ledger = $hasLedger ? new \App\Services\LeaveLedgerService($this->pdo) : null;
        $asOfDate = sprintf('%04d-01-01', $year);

        $count = 0;
        foreach ($leaveTypes as $lt) {
            $ltId = (int)$lt['id'];
            $existing = $this->getBalance($employeeId, $ltId, $year);
            if ($existing) {
                continue;
            }

            $days = (int)$lt['default_days'];
            $policyVersionId = null;

            if ($policyEngine !== null) {
                $typePolicy = $policyEngine->resolveLeaveTypeWithPolicy($ltId, $asOfDate) ?? $lt;
                $model = (string)($typePolicy['entitlement_model'] ?? 'annual');
                if ($model !== 'annual') {
                    continue;
                }
                $policyVersionId = !empty($typePolicy['policy_version_id']) ? (int)$typePolicy['policy_version_id'] : null;

                if ($employee !== null) {
                    $elig = $policyEngine->evaluateEmployeeTypeEligibility($employee, $typePolicy, $year, $asOfDate);
                    $code = strtoupper(trim((string)($typePolicy['leave_code'] ?? '')));
                    if (in_array($code, ['SIL', 'SOLO_PARENT'], true) && empty($elig['eligible'])) {
                        continue;
                    }
                    $days = max(0, (int)($elig['entitled_days'] ?? $typePolicy['entitlement_days'] ?? $days));
                }
            }

            if ($hasPolicyCols) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO leave_balances (
                        employee_id, leave_type_id, year, allocated_days, used_days, remaining_days,
                        policy_version_id, eligibility_status
                    ) VALUES (
                        :emp_id, :lt_id, :year, :alloc, 0, :rem,
                        :pv_id, 'Available'
                    )
                ");
                $stmt->execute([
                    'emp_id' => $employeeId,
                    'lt_id'  => $ltId,
                    'year'   => $year,
                    'alloc'  => $days,
                    'rem'    => $days,
                    'pv_id'  => $policyVersionId,
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days)
                    VALUES (:emp_id, :lt_id, :year, :alloc, 0, :rem)
                ");
                $stmt->execute([
                    'emp_id' => $employeeId,
                    'lt_id'  => $ltId,
                    'year'   => $year,
                    'alloc'  => $days,
                    'rem'    => $days,
                ]);
            }

            $balanceId = (int)$this->pdo->lastInsertId();
            if ($ledger !== null && $balanceId > 0) {
                $ledger->record([
                    'employee_id'       => $employeeId,
                    'leave_type_id'     => $ltId,
                    'leave_balance_id'  => $balanceId,
                    'policy_version_id' => $policyVersionId,
                    'balance_year'      => $year,
                    'transaction_type'  => 'initial_allocation',
                    'days_delta'        => (float)$days,
                    'balance_before'    => 0.0,
                    'balance_after'     => (float)$days,
                    'remarks'           => "Initial {$year} annual leave allocation for {$lt['leave_name']}.",
                    'actor_user_id'     => $actorUserId,
                ]);
            }

            $count++;
        }
        return $count;
    }

    /**
     * Adjust an employee's allocated leave days for a specific leave type and year.
     * Keeps the stored balance invariant remaining_days = allocated_days - used_days - converted - expired.
     *
     * @return bool True on success; false when the allocation is below committed days (see lastError).
     */
    public function adjustBalance(
        int $employeeId,
        int $leaveTypeId,
        int $year,
        int $allocatedDays,
        ?int $actorUserId = null,
        ?string $remarks = null
    ): bool {
        $this->lastError = null;
        $this->lastErrorCode = null;

        if ($allocatedDays < 0) {
            $this->lastError = 'Allocated days cannot be negative.';
            $this->lastErrorCode = 'invalid_allocation';
            return false;
        }

        $hasExtendedBal = $this->hasTableColumn('leave_balances', 'converted_days');
        $hasLedger = $this->hasTable('leave_balance_transactions');
        $ledger = $hasLedger ? new \App\Services\LeaveLedgerService($this->pdo) : null;

        $existing = $this->getBalance($employeeId, $leaveTypeId, $year);
        if ($existing) {
            $used = (int)$existing['used_days'];
            $converted = $hasExtendedBal ? (float)($existing['converted_days'] ?? 0) : 0.0;
            $expired = $hasExtendedBal ? (float)($existing['expired_days'] ?? 0) : 0.0;
            $committed = $used + (int)round($converted + $expired);

            if ($allocatedDays < $committed) {
                $this->lastError = ($converted > 0 || $expired > 0)
                    ? "Allocation ({$allocatedDays}) cannot be less than used/converted/expired days ({$committed})."
                    : "Allocation ({$allocatedDays}) cannot be less than days already used ({$used}).";
                $this->lastErrorCode = 'allocation_below_used';
                return false;
            }
            $prevRem = (float)$existing['remaining_days'];
            $newRemaining = $allocatedDays - $committed;
            $stmt = $this->pdo->prepare("
                UPDATE leave_balances
                SET allocated_days = :alloc,
                    remaining_days = :rem
                WHERE id = :id
            ");
            $ok = $stmt->execute([
                'alloc' => $allocatedDays,
                'rem'   => $newRemaining,
                'id'    => (int)$existing['id'],
            ]);

            if ($ok && $ledger !== null && abs((float)$newRemaining - $prevRem) > 0.001) {
                $ledger->record([
                    'employee_id'      => $employeeId,
                    'leave_type_id'    => $leaveTypeId,
                    'leave_balance_id' => (int)$existing['id'],
                    'balance_year'     => $year,
                    'transaction_type' => 'adjustment',
                    'days_delta'       => round((float)$newRemaining - $prevRem, 2),
                    'balance_before'   => $prevRem,
                    'balance_after'    => (float)$newRemaining,
                    'remarks'          => $remarks ?: "HR balance allocation updated from {$existing['allocated_days']} to {$allocatedDays} day(s).",
                    'actor_user_id'    => $actorUserId,
                ]);
            }

            return $ok;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days)
            VALUES (:emp_id, :lt_id, :year, :alloc, 0, :rem)
        ");
        $ok = $stmt->execute([
            'emp_id' => $employeeId,
            'lt_id'  => $leaveTypeId,
            'year'   => $year,
            'alloc'  => $allocatedDays,
            'rem'    => $allocatedDays,
        ]);
        $balanceId = (int)$this->pdo->lastInsertId();

        if ($ok && $ledger !== null && $balanceId > 0) {
            $ledger->record([
                'employee_id'      => $employeeId,
                'leave_type_id'    => $leaveTypeId,
                'leave_balance_id' => $balanceId,
                'balance_year'     => $year,
                'transaction_type' => 'adjustment',
                'days_delta'       => (float)$allocatedDays,
                'balance_before'   => 0.0,
                'balance_after'    => (float)$allocatedDays,
                'remarks'          => $remarks ?: "HR initialized custom balance allocation of {$allocatedDays} day(s).",
                'actor_user_id'    => $actorUserId,
            ]);
        }

        return $ok;
    }

    /**
     * Get all workforce balances across departments.
     */
    public function getAllBalances(?int $year = null, ?int $deptId = null): array
    {
        $year = $year ?: (int)date('Y');
        $hasExtended = $this->hasTableColumn('leave_types', 'leave_code')
            && $this->hasTableColumn('leave_balances', 'converted_days');
        $extraCols = $hasExtended
            ? ", lt.leave_code, lt.classification, lt.benefit_category, lt.entitlement_model, lt.default_unit, lt.is_paid"
            : "";

        $sql = "
            SELECT lb.*, e.employee_no, e.first_name, e.last_name, d.department_name, lt.leave_name{$extraCols}
            FROM leave_balances lb
            INNER JOIN employees e ON lb.employee_id = e.id
            INNER JOIN leave_types lt ON lb.leave_type_id = lt.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE lb.year = :year
        ";
        $params = ['year' => $year];

        if ($deptId) {
            $sql .= " AND e.department_id = :dept_id";
            $params['dept_id'] = $deptId;
        }

        $sql .= " ORDER BY e.last_name ASC, lt.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new leave request with authoritative calculated days.
     *
     * When $withinTransaction is given, the insert and the callback (e.g. writing
     * HR notifications) run in one transaction: both commit or neither does.
     *
     * @param callable(int $requestId): void|null $withinTransaction
     * @throws \Throwable when the transactional path fails (after rollback)
     */
    public function createRequest(array $data, ?callable $withinTransaction = null): int
    {
        if ($withinTransaction === null) {
            return $this->insertRequest($data);
        }

        $this->pdo->beginTransaction();
        try {
            $requestId = $this->insertRequest($data);
            $withinTransaction($requestId);
            $this->pdo->commit();
            return $requestId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Insert a Pending leave request row.
     */
    private function insertRequest(array $data): int
    {
        $hasExtended = $this->hasTableColumn('leave_requests', 'policy_version_id')
            && $this->hasTableColumn('leave_requests', 'entitlement_unit');

        if (!$hasExtended) {
            $stmt = $this->pdo->prepare("
                INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, number_of_days, reason, status)
                VALUES (:employee_id, :leave_type_id, :start_date, :end_date, :number_of_days, :reason, 'Pending')
            ");
            $stmt->execute([
                'employee_id'    => (int)$data['employee_id'],
                'leave_type_id'  => (int)$data['leave_type_id'],
                'start_date'     => $data['start_date'],
                'end_date'       => $data['end_date'],
                'number_of_days' => (int)$data['number_of_days'],
                'reason'         => trim((string)$data['reason']),
            ]);

            return (int)$this->pdo->lastInsertId();
        }

        $metaJson = null;
        if (!empty($data['application_metadata_json'])) {
            $metaJson = is_string($data['application_metadata_json'])
                ? $data['application_metadata_json']
                : json_encode($data['application_metadata_json'], JSON_UNESCAPED_UNICODE);
        } elseif (!empty($data['metadata']) && is_array($data['metadata'])) {
            $metaJson = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO leave_requests (
                employee_id, leave_type_id, policy_version_id, event_entitlement_id,
                start_date, end_date, number_of_days, entitlement_unit,
                is_paid, is_confidential, reason, application_metadata_json, status
            ) VALUES (
                :employee_id, :leave_type_id, :policy_version_id, :event_entitlement_id,
                :start_date, :end_date, :number_of_days, :entitlement_unit,
                :is_paid, :is_confidential, :reason, :meta_json, 'Pending'
            )
        ");
        $entitlementUnit = (string)($data['entitlement_unit'] ?? 'working_days');
        if (!in_array($entitlementUnit, ['working_days', 'calendar_days'], true)) {
            $entitlementUnit = 'working_days';
        }

        $stmt->execute([
            'employee_id'          => (int)$data['employee_id'],
            'leave_type_id'        => (int)$data['leave_type_id'],
            'policy_version_id'    => !empty($data['policy_version_id']) ? (int)$data['policy_version_id'] : null,
            'event_entitlement_id' => !empty($data['event_entitlement_id']) ? (int)$data['event_entitlement_id'] : null,
            'start_date'           => $data['start_date'],
            'end_date'             => $data['end_date'],
            'number_of_days'       => (int)$data['number_of_days'],
            'entitlement_unit'     => $entitlementUnit,
            'is_paid'              => isset($data['is_paid']) ? (!empty($data['is_paid']) ? 1 : 0) : 1,
            'is_confidential'      => !empty($data['is_confidential']) ? 1 : 0,
            'reason'               => trim((string)$data['reason']),
            'meta_json'            => $metaJson,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Approve a leave request in a single atomic ACID database transaction.
     *
     * Transaction sequence:
     *  1. Verify & lock the acting HR user account (Active, role = 'hr').
     *  2. Load & lock the leave request row (SELECT ... FOR UPDATE).
     *  3. Verify the request exists and its status is still 'Pending'.
     *  4. Load & lock the employee row; verify Active status & prevent self-approval.
     *  5. Load & lock the leave type row; verify Active status.
     *  6. Revalidate request dates, working day duration, leave-year boundary, and overlap.
     *  7. Lock & re-check the applicable leave balance row (remaining_days >= number_of_days).
     *  8. Update leave_requests status to 'Approved' (assert rowCount() === 1).
     *  9. Update leave_balances.used_days (assert rowCount() === 1).
     * 10. Update leave_balances.remaining_days and verify balance invariant.
     * 11. Create the required activity_logs audit record.
     * 12. Create the required persistent notification record for the employee.
     * 13. Commit the transaction.
     * 14. Trigger optional post-commit notification delivery (failures logged, never roll back).
     *
     * @param int $id Leave request ID
     * @param int $reviewerUserId Authenticated HR user ID
     * @param callable(array $request, string $status): void|null $withinTransaction Optional pre-commit callback
     * @param callable(string $step, array $context): void|null $stepHook Optional hook invoked after each write step
     * @param callable(array $result): void|null $postCommit Optional post-commit delivery callback
     * @return array{ok: bool, code: string, http_status: int, message: string, data?: array}
     */
    public function approveRequest(
        int $id,
        int $reviewerUserId,
        ?callable $withinTransaction = null,
        ?callable $stepHook = null,
        ?callable $postCommit = null
    ): array {
        $this->lastError = null;
        $this->lastErrorCode = null;

        if ($id <= 0) {
            return $this->failResult('request_not_found', 404, "Leave request #{$id} does not exist.");
        }

        if ($reviewerUserId <= 0) {
            return $this->failResult('unauthorized_reviewer', 403, 'A valid authenticated HR reviewer is required.');
        }

        if ($this->pdo->inTransaction()) {
            return $this->failResult('nested_transaction', 500, 'Cannot start leave approval inside an already active database transaction.');
        }

        try {
            if (!$this->pdo->beginTransaction()) {
                return $this->failResult('transaction_start_failed', 500, 'Unable to start database transaction.');
            }
        } catch (\Throwable $e) {
            error_log('[Leave::approveRequest] Failed to begin transaction: ' . $e->getMessage());
            return $this->failResult('transaction_start_failed', 500, 'Unable to start database transaction.');
        }

        try {
            // 1. Verify and lock acting HR reviewer account
            $stmtReviewer = $this->pdo->prepare("
                SELECT id, employee_id, username, role, status
                FROM users
                WHERE id = :uid
                LIMIT 1
                FOR UPDATE
            ");
            $stmtReviewer->execute(['uid' => $reviewerUserId]);
            $reviewer = $stmtReviewer->fetch(PDO::FETCH_ASSOC);

            if (
                !$reviewer
                || ($reviewer['status'] ?? '') !== 'Active'
                || !in_array(strtolower((string)($reviewer['role'] ?? '')), ['admin', 'hr'], true)
            ) {
                $this->safeRollBack();
                return $this->failResult('unauthorized_reviewer', 403, 'Approving user is not an active Administrator or HR account.');
            }

            // 2. Load and lock the leave request row
            $stmtReq = $this->pdo->prepare("
                SELECT *
                FROM leave_requests
                WHERE id = :id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtReq->execute(['id' => $id]);
            $lockedRequest = $stmtReq->fetch(PDO::FETCH_ASSOC);

            if (!$lockedRequest) {
                $this->safeRollBack();
                return $this->failResult('request_not_found', 404, "Leave request #{$id} does not exist.");
            }

            // 3. Verify request status is still Pending
            if (($lockedRequest['status'] ?? '') !== 'Pending') {
                $currentStatus = (string)($lockedRequest['status'] ?? 'Unknown');
                $this->safeRollBack();
                return $this->failResult(
                    'not_pending',
                    400,
                    "Cannot approve request #{$id}: Request has already been processed (Current Status: {$currentStatus})."
                );
            }

            $empId = (int)$lockedRequest['employee_id'];
            $leaveTypeId = (int)$lockedRequest['leave_type_id'];
            $days = (int)$lockedRequest['number_of_days'];
            $startDate = (string)$lockedRequest['start_date'];
            $endDate = (string)$lockedRequest['end_date'];

            // Segregation of duties: prevent self-approval
            if (!empty($reviewer['employee_id']) && (int)$reviewer['employee_id'] === $empId) {
                $this->safeRollBack();
                return $this->failResult('self_approval_forbidden', 403, 'HR personnel cannot approve their own leave request.');
            }

            // 4. Load and lock employee record; verify active eligibility
            $stmtEmp = $this->pdo->prepare("
                SELECT *
                FROM employees
                WHERE id = :emp_id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtEmp->execute(['emp_id' => $empId]);
            $employee = $stmtEmp->fetch(PDO::FETCH_ASSOC);

            if (!$employee || ($employee['employment_status'] ?? '') !== 'Active') {
                $this->safeRollBack();
                return $this->failResult(
                    'employee_ineligible',
                    400,
                    "Cannot approve request #{$id}: Employee record is inactive or ineligible."
                );
            }

            // Verify employee's linked user account exists and is Active (required for persistent notification)
            $stmtOwnerUser = $this->pdo->prepare("
                SELECT id, username, status
                FROM users
                WHERE employee_id = :emp_id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtOwnerUser->execute(['emp_id' => $empId]);
            $ownerUser = $stmtOwnerUser->fetch(PDO::FETCH_ASSOC);

            if ($ownerUser && ($ownerUser['status'] ?? '') !== 'Active') {
                $this->safeRollBack();
                return $this->failResult(
                    'employee_ineligible',
                    400,
                    "Cannot approve request #{$id}: Employee user account is inactive."
                );
            }

            // 5. Load and lock leave type; verify Active status
            $stmtType = $this->pdo->prepare("
                SELECT *
                FROM leave_types
                WHERE id = :lt_id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtType->execute(['lt_id' => $leaveTypeId]);
            $leaveType = $stmtType->fetch(PDO::FETCH_ASSOC);

            if (!$leaveType || ($leaveType['status'] ?? '') !== 'Active') {
                $this->safeRollBack();
                return $this->failResult(
                    'leave_type_invalid',
                    400,
                    "Cannot approve request #{$id}: Leave classification is invalid or inactive."
                );
            }

            $leaveCode = strtoupper(trim((string)($leaveType['leave_code'] ?? '')));
            $entitlementModel = (string)($leaveType['entitlement_model'] ?? 'annual');
            $unit = (string)($lockedRequest['entitlement_unit'] ?? ($leaveType['default_unit'] ?? 'working_days'));
            if (!in_array($unit, ['working_days', 'calendar_days'], true)) {
                $unit = 'working_days';
            }
            $isConfidential = !empty($lockedRequest['is_confidential'])
                || \App\Services\ConfidentialLeaveService::isConfidentialType($leaveType);

            // 6. Revalidate dates, duration (working vs calendar), leave-year boundaries, and overlap
            $rangeValidation = LeaveCalculator::validateDateRange($startDate, $endDate);
            if (!$rangeValidation['valid']) {
                $this->safeRollBack();
                return $this->failResult(
                    'invalid_dates',
                    400,
                    $rangeValidation['error'] ?? 'Leave request contains an invalid date range.'
                );
            }

            $expectedDays = $unit === 'calendar_days'
                ? LeaveCalculator::calculateCalendarDays($startDate, $endDate)
                : (int)$rangeValidation['working_days'];

            if ($expectedDays <= 0) {
                $this->safeRollBack();
                return $this->failResult(
                    'invalid_dates',
                    400,
                    'Leave request contains zero countable leave days.'
                );
            }

            if ($days <= 0 || $days !== $expectedDays) {
                $unitLabel = $unit === 'calendar_days' ? 'calendar days' : 'working days';
                $this->safeRollBack();
                return $this->failResult(
                    'invalid_duration',
                    400,
                    "Cannot approve request #{$id}: Recorded duration ({$days} days) does not match calculated {$unitLabel} ({$expectedDays} days)."
                );
            }

            $startYear = (int)substr($startDate, 0, 4);
            $endYear = (int)substr($endDate, 0, 4);
            $allowsCrossYear = ($leaveCode === 'MATERNITY')
                || ($unit === 'calendar_days' && $entitlementModel === 'event_based');

            if (!$allowsCrossYear && $startYear !== $endYear) {
                $this->safeRollBack();
                return $this->failResult(
                    'cross_year_boundary',
                    400,
                    "Cannot approve request #{$id}: Leave request spans multiple leave years ({$startYear} to {$endYear})."
                );
            }
            $year = $startYear;

            // Lock and check for overlapping Approved requests for the same employee
            $stmtOverlap = $this->pdo->prepare("
                SELECT id, start_date, end_date, status
                FROM leave_requests
                WHERE employee_id = :emp_id
                  AND id != :exclude_id
                  AND status = 'Approved'
                  AND start_date <= :new_end
                  AND end_date >= :new_start
                LIMIT 1
                FOR UPDATE
            ");
            $stmtOverlap->execute([
                'emp_id'     => $empId,
                'exclude_id' => $id,
                'new_start'  => $startDate,
                'new_end'    => $endDate,
            ]);
            $overlappingApproved = $stmtOverlap->fetch(PDO::FETCH_ASSOC);
            if ($overlappingApproved) {
                $this->safeRollBack();
                return $this->failResult(
                    'date_overlap',
                    400,
                    "Cannot approve request #{$id}: Schedule overlaps with already approved leave request #{$overlappingApproved['id']}."
                );
            }

            // Enrich request array for downstream callbacks/notifications
            $enrichedRequest = array_merge($lockedRequest, [
                'employee_no'       => $employee['employee_no'],
                'first_name'        => $employee['first_name'],
                'last_name'         => $employee['last_name'],
                'email'             => $employee['email'],
                'leave_name'        => $leaveType['leave_name'],
                'leave_code'        => $leaveType['leave_code'] ?? null,
                'is_confidential'   => $isConfidential ? 1 : 0,
                'leave_description' => $leaveType['description'],
                'reviewer_username' => $reviewer['username'],
            ]);

            $hasExtendedBal = $this->hasTableColumn('leave_balances', 'converted_days');
            $hasLedger = $this->hasTable('leave_balance_transactions');
            $ledger = $hasLedger ? new \App\Services\LeaveLedgerService($this->pdo) : null;
            $policyVersionId = !empty($lockedRequest['policy_version_id'])
                ? (int)$lockedRequest['policy_version_id']
                : (!empty($leaveType['active_policy_version_id']) ? (int)$leaveType['active_policy_version_id'] : null);

            $balanceId = null;
            $usedDaysResult = 0;
            $remainingDaysResult = 0;

            if ($entitlementModel === 'unpaid' || $leaveCode === 'LWOP') {
                // Unpaid leave: approve without deducting paid credits
                $stmtUpdateReq = $this->pdo->prepare("
                    UPDATE leave_requests
                    SET status = 'Approved',
                        approved_by = :approved_by,
                        approved_at = NOW(),
                        rejection_reason = NULL
                    WHERE id = :id AND status = 'Pending'
                ");
                $stmtUpdateReq->execute([
                    'approved_by' => $reviewerUserId,
                    'id'          => $id,
                ]);

                if ($stmtUpdateReq->rowCount() !== 1) {
                    throw new RuntimeException('Failed to update unpaid leave request status to Approved.');
                }

                if ($stepHook !== null) {
                    $stepHook('after_request_status_update', ['request_id' => $id, 'balance_id' => 0]);
                }

                if ($ledger !== null) {
                    $ledger->record([
                        'employee_id'       => $empId,
                        'leave_type_id'     => $leaveTypeId,
                        'leave_request_id'  => $id,
                        'policy_version_id' => $policyVersionId,
                        'balance_year'      => $year,
                        'transaction_type'  => 'unpaid_recorded',
                        'days_delta'        => (float)$days,
                        'balance_before'    => 0.0,
                        'balance_after'     => 0.0,
                        'remarks'           => "Approved unpaid leave ({$days} day(s), no paid credit deduction).",
                        'is_confidential'   => $isConfidential ? 1 : 0,
                        'actor_user_id'     => $reviewerUserId,
                    ]);
                }
            } elseif ($entitlementModel === 'event_based') {
                // Event-based statutory/company benefits (Maternity, Paternity, SLW, VAWC, Bereavement)
                $eventEntId = !empty($lockedRequest['event_entitlement_id']) ? (int)$lockedRequest['event_entitlement_id'] : null;

                if ($eventEntId === null && $this->hasTable('leave_event_entitlements')) {
                    $policyEngine = new \App\Services\LeavePolicyEngine($this->pdo);
                    $meta = !empty($lockedRequest['application_metadata_json'])
                        ? (json_decode((string)$lockedRequest['application_metadata_json'], true) ?: [])
                        : [];
                    $resolvedFiling = $policyEngine->validateFiling($empId, $leaveTypeId, $startDate, $endDate, $meta, true, false);
                    $eventEntId = $policyEngine->ensureEventEntitlementForRequest($empId, $leaveTypeId, $startDate, $endDate, $resolvedFiling, $reviewerUserId);
                    if ($eventEntId !== null) {
                        $this->pdo->prepare("UPDATE leave_requests SET event_entitlement_id = :eid WHERE id = :id")
                            ->execute(['eid' => $eventEntId, 'id' => $id]);
                    }
                }

                $eventUsedAfter = $days;
                $eventRemAfter = 0;

                if ($eventEntId !== null && $this->hasTable('leave_event_entitlements')) {
                    $evStmt = $this->pdo->prepare("SELECT * FROM leave_event_entitlements WHERE id = :eid LIMIT 1 FOR UPDATE");
                    $evStmt->execute(['eid' => $eventEntId]);
                    $eventRow = $evStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$eventRow) {
                        $this->safeRollBack();
                        return $this->failResult('balance_not_found', 400, "Referenced event entitlement #{$eventEntId} does not exist.");
                    }

                    $evRem = (float)$eventRow['remaining_days'];
                    if ($evRem + 0.001 < (float)$days) {
                        $this->safeRollBack();
                        return $this->failResult(
                            'insufficient_balance',
                            400,
                            "Insufficient event entitlement: Request requires {$days} day(s), but only {$evRem} day(s) remain."
                        );
                    }

                    $eventUsedAfter = (float)$eventRow['used_days'] + (float)$days;
                    $eventRemAfter = max(0.0, $evRem - (float)$days);
                    $newStatus = $eventRemAfter <= 0.001 ? 'Completed' : 'Verified';

                    $this->pdo->prepare("
                        UPDATE leave_event_entitlements
                        SET used_days = used_days + :days_used,
                            pending_days = GREATEST(0, pending_days - :days_pend),
                            remaining_days = remaining_days - :days_rem,
                            verification_status = :vstatus,
                            verified_by = COALESCE(verified_by, :verifier),
                            verified_at = COALESCE(verified_at, NOW())
                        WHERE id = :eid
                    ")->execute([
                        'days_used' => (float)$days,
                        'days_pend' => (float)$days,
                        'days_rem'  => (float)$days,
                        'vstatus'   => $newStatus,
                        'verifier'  => $reviewerUserId,
                        'eid'       => $eventEntId,
                    ]);
                }

                $stmtUpdateReq = $this->pdo->prepare("
                    UPDATE leave_requests
                    SET status = 'Approved',
                        approved_by = :approved_by,
                        approved_at = NOW(),
                        rejection_reason = NULL
                    WHERE id = :id AND status = 'Pending'
                ");
                $stmtUpdateReq->execute([
                    'approved_by' => $reviewerUserId,
                    'id'          => $id,
                ]);
                if ($stmtUpdateReq->rowCount() !== 1) {
                    throw new RuntimeException('Failed to update event leave request status to Approved.');
                }

                if ($stepHook !== null) {
                    $stepHook('after_request_status_update', ['request_id' => $id, 'balance_id' => 0, 'event_entitlement_id' => $eventEntId ?? 0]);
                }

                if ($ledger !== null) {
                    $ledger->record([
                        'employee_id'          => $empId,
                        'leave_type_id'        => $leaveTypeId,
                        'leave_request_id'     => $id,
                        'event_entitlement_id' => $eventEntId,
                        'policy_version_id'    => $policyVersionId,
                        'balance_year'         => $year,
                        'transaction_type'     => 'event_allocation',
                        'days_delta'           => -(float)$days,
                        'balance_before'       => (float)($eventRemAfter + $days),
                        'balance_after'        => (float)$eventRemAfter,
                        'remarks'              => $isConfidential
                            ? 'Approved statutory event leave deduction (details restricted).'
                            : "Approved event-based leave deduction ({$days} day(s) of {$leaveType['leave_name']}).",
                        'is_confidential'      => $isConfidential ? 1 : 0,
                        'actor_user_id'        => $reviewerUserId,
                    ]);
                }

                $usedDaysResult = (int)round($eventUsedAfter);
                $remainingDaysResult = (int)round($eventRemAfter);
            } else {
                // 7. Annual balance model: lock & re-check leave_balances
                $stmtLockBal = $this->pdo->prepare("
                    SELECT *
                    FROM leave_balances
                    WHERE employee_id = :emp_id AND leave_type_id = :lt_id AND year = :year
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmtLockBal->execute([
                    'emp_id' => $empId,
                    'lt_id'  => $leaveTypeId,
                    'year'   => $year,
                ]);
                $balance = $stmtLockBal->fetch(PDO::FETCH_ASSOC);

                if (!$balance) {
                    $stmtYearCount = $this->pdo->prepare("
                        SELECT COUNT(*) FROM leave_balances WHERE employee_id = :emp_id AND year = :year
                    ");
                    $stmtYearCount->execute(['emp_id' => $empId, 'year' => $year]);
                    $existingYearCount = (int)$stmtYearCount->fetchColumn();

                    $canAutoInit = $existingYearCount === 0 && (int)$leaveType['default_days'] > 0;
                    if ($canAutoInit && in_array($leaveCode, ['SIL', 'SOLO_PARENT'], true) && $this->hasTable('leave_policy_versions')) {
                        $policyEngine = new \App\Services\LeavePolicyEngine($this->pdo);
                        $typePolicy = $policyEngine->resolveLeaveTypeWithPolicy($leaveTypeId, $startDate) ?? $leaveType;
                        $elig = $policyEngine->evaluateEmployeeTypeEligibility($employee, $typePolicy, $year, $startDate);
                        $canAutoInit = !empty($elig['eligible']);
                    }

                    if ($canAutoInit) {
                        $defaultDays = (int)$leaveType['default_days'];
                        $stmtInitBal = $this->pdo->prepare("
                            INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days)
                            VALUES (:emp_id, :lt_id, :year, :alloc, 0, :rem)
                        ");
                        $stmtInitBal->execute([
                            'emp_id' => $empId,
                            'lt_id'  => $leaveTypeId,
                            'year'   => $year,
                            'alloc'  => $defaultDays,
                            'rem'    => $defaultDays,
                        ]);

                        $stmtLockBal->execute([
                            'emp_id' => $empId,
                            'lt_id'  => $leaveTypeId,
                            'year'   => $year,
                        ]);
                        $balance = $stmtLockBal->fetch(PDO::FETCH_ASSOC);
                    }
                }

                if (!$balance) {
                    $this->safeRollBack();
                    return $this->failResult(
                        'balance_not_found',
                        400,
                        "Cannot approve request #{$id}: No leave balance account exists for {$leaveType['leave_name']} in {$year}."
                    );
                }

                $balanceId = (int)$balance['id'];
                $allocatedDays = (int)$balance['allocated_days'];
                $previousUsedDays = (int)$balance['used_days'];
                $previousRemainingDays = (int)$balance['remaining_days'];
                $convertedDays = $hasExtendedBal ? (float)($balance['converted_days'] ?? 0) : 0.0;
                $expiredDays = $hasExtendedBal ? (float)($balance['expired_days'] ?? 0) : 0.0;
                $committedOffset = (int)round($convertedDays + $expiredDays);

                if (
                    $previousRemainingDays < $days
                    || ($allocatedDays - $previousUsedDays - $committedOffset) < $days
                    || ($previousUsedDays + $committedOffset + $days) > $allocatedDays
                ) {
                    $this->safeRollBack();
                    return $this->failResult(
                        'insufficient_balance',
                        400,
                        "Insufficient leave balance: Request requires {$days} day(s), but only {$previousRemainingDays} day(s) remain."
                    );
                }

                // 8. Update the leave request status to Approved
                $stmtUpdateReq = $this->pdo->prepare("
                    UPDATE leave_requests
                    SET status = 'Approved',
                        approved_by = :approved_by,
                        approved_at = NOW(),
                        rejection_reason = NULL
                    WHERE id = :id AND status = 'Pending'
                ");
                $stmtUpdateReq->execute([
                    'approved_by' => $reviewerUserId,
                    'id'          => $id,
                ]);

                if ($stmtUpdateReq->rowCount() !== 1) {
                    throw new RuntimeException('Failed to update leave request status to Approved.');
                }

                if ($stepHook !== null) {
                    $stepHook('after_request_status_update', ['request_id' => $id, 'balance_id' => $balanceId]);
                }

                // 9. Record used leave days
                $usedGuardSql = $hasExtendedBal
                    ? "(allocated_days - (used_days + :days_check + COALESCE(converted_days, 0) + COALESCE(expired_days, 0))) >= 0"
                    : "(allocated_days - (used_days + :days_check)) >= 0";

                $stmtUpdateUsed = $this->pdo->prepare("
                    UPDATE leave_balances
                    SET used_days = used_days + :days
                    WHERE id = :balance_id
                      AND used_days = :expected_used
                      AND {$usedGuardSql}
                ");
                $stmtUpdateUsed->execute([
                    'days'          => $days,
                    'balance_id'    => $balanceId,
                    'expected_used' => $previousUsedDays,
                    'days_check'    => $days,
                ]);

                if ($stmtUpdateUsed->rowCount() !== 1) {
                    throw new RuntimeException('Failed to record used leave days in leave_balances.');
                }

                if ($stepHook !== null) {
                    $stepHook('after_used_days_update', ['request_id' => $id, 'balance_id' => $balanceId]);
                }

                // 10. Update the remaining leave balance
                $remInvariantSql = $hasExtendedBal
                    ? "(remaining_days - :days_calc) = (allocated_days - used_days - COALESCE(converted_days, 0) - COALESCE(expired_days, 0))"
                    : "(remaining_days - :days_calc) = (allocated_days - used_days)";

                $stmtUpdateRem = $this->pdo->prepare("
                    UPDATE leave_balances
                    SET remaining_days = remaining_days - :days
                    WHERE id = :balance_id
                      AND remaining_days = :expected_rem
                      AND remaining_days >= :days_check
                      AND {$remInvariantSql}
                ");
                $stmtUpdateRem->execute([
                    'days'         => $days,
                    'balance_id'   => $balanceId,
                    'expected_rem' => $previousRemainingDays,
                    'days_check'   => $days,
                    'days_calc'    => $days,
                ]);

                if ($stmtUpdateRem->rowCount() !== 1) {
                    throw new RuntimeException('Failed to update remaining leave balance in leave_balances.');
                }

                // Verify updated balance invariants inside the transaction
                $stmtVerifyBal = $this->pdo->prepare("
                    SELECT allocated_days, used_days, remaining_days
                    FROM leave_balances
                    WHERE id = :balance_id
                    LIMIT 1
                ");
                $stmtVerifyBal->execute(['balance_id' => $balanceId]);
                $updatedBalance = $stmtVerifyBal->fetch(PDO::FETCH_ASSOC);

                if (
                    !$updatedBalance
                    || (int)$updatedBalance['used_days'] !== ($previousUsedDays + $days)
                    || (int)$updatedBalance['remaining_days'] !== ($previousRemainingDays - $days)
                    || (int)$updatedBalance['remaining_days'] < 0
                ) {
                    throw new RuntimeException('Leave balance invariant verification failed after update.');
                }

                if ($stepHook !== null) {
                    $stepHook('after_remaining_balance_update', ['request_id' => $id, 'balance_id' => $balanceId]);
                }

                if ($ledger !== null) {
                    $ledger->record([
                        'employee_id'       => $empId,
                        'leave_type_id'     => $leaveTypeId,
                        'leave_balance_id'  => $balanceId,
                        'leave_request_id'  => $id,
                        'policy_version_id' => $policyVersionId,
                        'balance_year'      => $year,
                        'transaction_type'  => 'approved_deduction',
                        'days_delta'        => -(float)$days,
                        'balance_before'    => (float)$previousRemainingDays,
                        'balance_after'     => (float)$updatedBalance['remaining_days'],
                        'remarks'           => $isConfidential
                            ? 'Approved leave balance deduction (confidential details restricted).'
                            : "Approved leave deduction ({$days} day(s) of {$leaveType['leave_name']}).",
                        'is_confidential'   => $isConfidential ? 1 : 0,
                        'actor_user_id'     => $reviewerUserId,
                    ]);
                }

                $usedDaysResult = (int)$updatedBalance['used_days'];
                $remainingDaysResult = (int)$updatedBalance['remaining_days'];
            }

            // 11. Create required activity log inside the transaction (masked for confidential types)
            $ip = client_ip() ?? '127.0.0.1';
            $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255);
            $defaultLogDescription = "Approved leave request #{$id} (Pending -> Approved, {$days} days of {$leaveType['leave_name']}) for {$employee['first_name']} {$employee['last_name']} (Employee #{$empId})";
            $logDescription = \App\Services\ConfidentialLeaveService::safeActivityDescription($isConfidential, $id, 'Approved', $defaultLogDescription);

            $stmtLog = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, entity_type, entity_id, ip_address, user_agent, created_at)
                VALUES (:user_id, 'LEAVE_APPROVED', :description, 'LeaveRequest', :entity_id, :ip_address, :user_agent, NOW())
            ");
            $stmtLog->execute([
                'user_id'     => $reviewerUserId,
                'description' => $logDescription,
                'entity_id'   => (string)$id,
                'ip_address'  => $ip,
                'user_agent'  => $agent,
            ]);

            $activityLogId = (int)$this->pdo->lastInsertId();
            if ($stmtLog->rowCount() !== 1 || $activityLogId <= 0) {
                throw new RuntimeException('Failed to create activity log entry for leave approval.');
            }

            if ($stepHook !== null) {
                $stepHook('after_activity_log_insert', ['request_id' => $id, 'activity_log_id' => $activityLogId]);
            }

            // 12. Create required persistent notification inside the transaction (masked for confidential types)
            $stmtNotifBefore = $this->pdo->prepare("
                SELECT COUNT(*) FROM notifications
                WHERE entity_id = :req_id AND event_type = :evt
            ");
            $stmtNotifBefore->execute([
                'req_id' => $id,
                'evt'    => EventAuthorizer::LEAVE_APPROVED,
            ]);
            $notifCountBefore = (int)$stmtNotifBefore->fetchColumn();

            if ($withinTransaction !== null) {
                $withinTransaction($enrichedRequest, 'Approved');
            }

            $stmtNotifAfter = $this->pdo->prepare("
                SELECT id, user_id FROM notifications
                WHERE entity_id = :req_id AND event_type = :evt
                ORDER BY id DESC
            ");
            $stmtNotifAfter->execute([
                'req_id' => $id,
                'evt'    => EventAuthorizer::LEAVE_APPROVED,
            ]);
            $notifRowsAfter = $stmtNotifAfter->fetchAll(PDO::FETCH_ASSOC);

            if (count($notifRowsAfter) > $notifCountBefore) {
                $notificationId = (int)$notifRowsAfter[0]['id'];
                if ($ownerUser && (int)$notifRowsAfter[0]['user_id'] !== (int)$ownerUser['id']) {
                    throw new RuntimeException('Approval notification was written for a non-owning user.');
                }
            } else {
                if (!$ownerUser || ($ownerUser['status'] ?? '') !== 'Active') {
                    throw new RuntimeException('Cannot create approval notification: Employee does not have an active user account.');
                }

                $defaultNotifMessage = "Your leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT)
                    . " ({$days} days for {$leaveType['leave_name']}, {$startDate} to {$endDate}) was approved by HR.";
                $notifTitle = \App\Services\ConfidentialLeaveService::safeNotificationTitle($isConfidential, 'Leave Request Approved');
                $notifMessage = \App\Services\ConfidentialLeaveService::safeNotificationMessage($isConfidential, $id, $defaultNotifMessage);

                $stmtNotif = $this->pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, event_type, entity_id, is_read, created_at)
                    VALUES (:user_id, :title, :message, 'success', :event_type, :entity_id, 0, NOW())
                ");
                $stmtNotif->execute([
                    'user_id'    => (int)$ownerUser['id'],
                    'title'      => $notifTitle,
                    'message'    => $notifMessage,
                    'event_type' => EventAuthorizer::LEAVE_APPROVED,
                    'entity_id'  => $id,
                ]);

                $notificationId = (int)$this->pdo->lastInsertId();
                if ($stmtNotif->rowCount() !== 1 || $notificationId <= 0) {
                    throw new RuntimeException('Failed to create persistent notification for leave approval.');
                }
            }

            if ($stepHook !== null) {
                $stepHook('after_notification_insert', ['request_id' => $id, 'notification_id' => $notificationId]);
            }

            // 13. Commit transaction after all required operations succeed
            if (!$this->pdo->commit()) {
                throw new RuntimeException('Database transaction commit returned false.');
            }

            $resultData = [
                'request_id'       => $id,
                'employee_id'      => $empId,
                'leave_type_id'    => $leaveTypeId,
                'year'             => $year,
                'approved_days'    => $days,
                'used_days'        => $usedDaysResult,
                'remaining_days'   => $remainingDaysResult,
                'activity_log_id'  => $activityLogId,
                'notification_id'  => $notificationId,
                'approved_by'      => $reviewerUserId,
                'is_confidential'  => $isConfidential,
            ];

            // 14. Post-commit notification delivery (never rolls back committed approval)
            if ($postCommit !== null) {
                try {
                    $postCommit($resultData);
                } catch (\Throwable $deliveryError) {
                    error_log('[Leave::approveRequest] Post-commit notification delivery failed for request #' . $id . ': ' . $deliveryError->getMessage());
                }
            }

            return [
                'ok'          => true,
                'code'        => 'approved',
                'http_status' => 200,
                'message'     => "Leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " approved and employee balance updated.",
                'data'        => $resultData,
            ];
        } catch (\Throwable $e) {
            $this->safeRollBack();
            error_log('Leave status update error: ' . $e->getMessage());
            return $this->failResult(
                'transaction_failed',
                500,
                "Failed to approve leave request #{$id} due to a processing error. No changes were saved."
            );
        }
    }

    /**
     * Reject a pending leave request in an atomic transaction.
     * Persists rejection reason, audit log, and employee notification without deducting leave balance.
     *
     * @param callable(array $request, string $status): void|null $withinTransaction
     * @return array{ok: bool, code: string, http_status: int, message: string, data?: array}
     */
    public function rejectRequest(
        int $id,
        int $reviewerUserId,
        ?string $rejectionReason = null,
        ?callable $withinTransaction = null
    ): array {
        $this->lastError = null;
        $this->lastErrorCode = null;

        if ($id <= 0) {
            return $this->failResult('request_not_found', 404, "Leave request #{$id} does not exist.");
        }

        if ($reviewerUserId <= 0) {
            return $this->failResult('unauthorized_reviewer', 403, 'A valid authenticated HR reviewer is required.');
        }

        if ($this->pdo->inTransaction()) {
            return $this->failResult('nested_transaction', 500, 'Cannot start leave rejection inside an already active database transaction.');
        }

        try {
            if (!$this->pdo->beginTransaction()) {
                return $this->failResult('transaction_start_failed', 500, 'Unable to start database transaction.');
            }
        } catch (\Throwable $e) {
            error_log('[Leave::rejectRequest] Failed to begin transaction: ' . $e->getMessage());
            return $this->failResult('transaction_start_failed', 500, 'Unable to start database transaction.');
        }

        try {
            $stmtReviewer = $this->pdo->prepare("
                SELECT id, employee_id, username, role, status
                FROM users
                WHERE id = :uid
                LIMIT 1
                FOR UPDATE
            ");
            $stmtReviewer->execute(['uid' => $reviewerUserId]);
            $reviewer = $stmtReviewer->fetch(PDO::FETCH_ASSOC);

            if (
                !$reviewer
                || ($reviewer['status'] ?? '') !== 'Active'
                || !in_array(strtolower((string)($reviewer['role'] ?? '')), ['admin', 'hr'], true)
            ) {
                $this->safeRollBack();
                return $this->failResult('unauthorized_reviewer', 403, 'Reviewing user is not an active Administrator or HR account.');
            }

            $ltSelect = $this->hasTableColumn('leave_types', 'leave_code')
                ? "lt.leave_name, lt.leave_code, lt.is_confidential AS type_confidential, lt.description AS leave_description"
                : "lt.leave_name, lt.description AS leave_description";

            $stmtReq = $this->pdo->prepare("
                SELECT lr.*, e.employee_no, e.first_name, e.last_name, e.email, {$ltSelect}
                FROM leave_requests lr
                INNER JOIN employees e ON lr.employee_id = e.id
                INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
                WHERE lr.id = :id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtReq->execute(['id' => $id]);
            $request = $stmtReq->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                $this->safeRollBack();
                return $this->failResult('request_not_found', 404, "Leave request #{$id} does not exist.");
            }

            if (($request['status'] ?? '') !== 'Pending') {
                $currentStatus = (string)($request['status'] ?? 'Unknown');
                $this->safeRollBack();
                return $this->failResult(
                    'not_pending',
                    400,
                    "Cannot reject request #{$id}: Request has already been processed (Current Status: {$currentStatus})."
                );
            }

            $cleanReason = (!empty($rejectionReason) && trim($rejectionReason) !== '')
                ? trim($rejectionReason)
                : null;

            $stmt = $this->pdo->prepare("
                UPDATE leave_requests
                SET status = 'Rejected',
                    approved_by = :approved_by,
                    approved_at = NOW(),
                    rejection_reason = :rejection_reason
                WHERE id = :id AND status = 'Pending'
            ");
            $stmt->execute([
                'approved_by'      => $reviewerUserId,
                'rejection_reason' => $cleanReason,
                'id'               => $id,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Failed to update leave request status to Rejected.');
            }

            if (!empty($request['event_entitlement_id']) && $this->hasTable('leave_event_entitlements')) {
                $this->pdo->prepare("
                    UPDATE leave_event_entitlements
                    SET pending_days = GREATEST(0, pending_days - :days)
                    WHERE id = :eid
                ")->execute([
                    'days' => (float)($request['number_of_days'] ?? 0),
                    'eid'  => (int)$request['event_entitlement_id'],
                ]);
            }

            $isConfidential = \App\Services\ConfidentialLeaveService::isConfidentialRequest($request);
            $ip = client_ip() ?? '127.0.0.1';
            $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255);
            $reasonDesc = $cleanReason ?? 'No reason specified';
            $defaultLogDescription = "Rejected leave request #{$id} for {$request['first_name']} {$request['last_name']}. Reason: {$reasonDesc}";
            $logDescription = \App\Services\ConfidentialLeaveService::safeActivityDescription($isConfidential, $id, 'Rejected', $defaultLogDescription);

            $stmtLog = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, entity_type, entity_id, ip_address, user_agent, created_at)
                VALUES (:user_id, 'LEAVE_REJECTED', :description, 'LeaveRequest', :entity_id, :ip_address, :user_agent, NOW())
            ");
            $stmtLog->execute([
                'user_id'     => $reviewerUserId,
                'description' => $logDescription,
                'entity_id'   => (string)$id,
                'ip_address'  => $ip,
                'user_agent'  => $agent,
            ]);

            if ($stmtLog->rowCount() !== 1) {
                throw new RuntimeException('Failed to create activity log entry for leave rejection.');
            }

            $stmtNotifBefore = $this->pdo->prepare("
                SELECT COUNT(*) FROM notifications
                WHERE entity_id = :req_id AND event_type = :evt
            ");
            $stmtNotifBefore->execute([
                'req_id' => $id,
                'evt'    => EventAuthorizer::LEAVE_REJECTED,
            ]);
            $notifCountBefore = (int)$stmtNotifBefore->fetchColumn();

            if ($withinTransaction !== null) {
                $withinTransaction($request, 'Rejected');
            }

            $stmtNotifAfter = $this->pdo->prepare("
                SELECT COUNT(*) FROM notifications
                WHERE entity_id = :req_id AND event_type = :evt
            ");
            $stmtNotifAfter->execute([
                'req_id' => $id,
                'evt'    => EventAuthorizer::LEAVE_REJECTED,
            ]);
            $notifCountAfter = (int)$stmtNotifAfter->fetchColumn();

            if ($notifCountAfter <= $notifCountBefore) {
                $stmtOwnerUser = $this->pdo->prepare("
                    SELECT id FROM users WHERE employee_id = :emp_id AND status = 'Active' LIMIT 1
                ");
                $stmtOwnerUser->execute(['emp_id' => (int)$request['employee_id']]);
                $ownerUserId = (int)$stmtOwnerUser->fetchColumn();

                if ($ownerUserId > 0) {
                    $defaultNotifMessage = "Your leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT)
                        . " was rejected." . ($cleanReason ? " Reason: {$cleanReason}" : '');
                    $notifTitle = \App\Services\ConfidentialLeaveService::safeNotificationTitle($isConfidential, 'Leave Request Rejected');
                    $notifMessage = \App\Services\ConfidentialLeaveService::safeNotificationMessage($isConfidential, $id, $defaultNotifMessage);

                    $stmtNotif = $this->pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type, event_type, entity_id, is_read, created_at)
                        VALUES (:user_id, :title, :message, 'warning', :event_type, :entity_id, 0, NOW())
                    ");
                    $stmtNotif->execute([
                        'user_id'    => $ownerUserId,
                        'title'      => $notifTitle,
                        'message'    => $notifMessage,
                        'event_type' => EventAuthorizer::LEAVE_REJECTED,
                        'entity_id'  => $id,
                    ]);
                }
            }

            if (!$this->pdo->commit()) {
                throw new RuntimeException('Database transaction commit returned false.');
            }

            return [
                'ok'          => true,
                'code'        => 'rejected',
                'http_status' => 200,
                'message'     => "Leave request #LR-" . str_pad((string)$id, 3, '0', STR_PAD_LEFT) . " was rejected. Entitlement remains untouched.",
            ];
        } catch (\Throwable $e) {
            $this->safeRollBack();
            error_log('Leave status update error: ' . $e->getMessage());
            return $this->failResult('transaction_failed', 500, "Failed to reject leave request #{$id}.");
        }
    }

    /**
     * Update request status (Approve or Reject) in an atomic transaction.
     * Delegates to approveRequest() or rejectRequest() so all callers get
     * full row locking, balance re-verification, audit logging, and notifications.
     *
     * @param callable(array $request, string $status): void|null $withinTransaction
     *        Runs before COMMIT; throwing rolls back the whole decision.
     */
    public function updateRequestStatus(
        int $id, 
        string $status, 
        int $reviewerUserId, 
        ?string $rejectionReason = null,
        ?callable $withinTransaction = null
    ): bool {
        if ($status === 'Approved') {
            $res = $this->approveRequest($id, $reviewerUserId, $withinTransaction);
            return $res['ok'];
        }

        if ($status === 'Rejected') {
            $res = $this->rejectRequest($id, $reviewerUserId, $rejectionReason, $withinTransaction);
            return $res['ok'];
        }

        $this->failResult('invalid_status', 400, "Unsupported leave status transition '{$status}'.");
        return false;
    }

    /**
     * Safely roll back an active transaction without masking the original error.
     */
    private function safeRollBack(): void
    {
        try {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } catch (\Throwable $rbError) {
            error_log('[Leave::safeRollBack] Rollback error: ' . $rbError->getMessage());
        }
    }

    /**
     * Build a standardized failure result array and record lastError / lastErrorCode.
     *
     * @return array{ok: false, code: string, http_status: int, message: string}
     */
    private function failResult(string $code, int $httpStatus, string $message): array
    {
        $this->lastErrorCode = $code;
        $this->lastError = $message;

        return [
            'ok'          => false,
            'code'        => $code,
            'http_status' => $httpStatus,
            'message'     => $message,
        ];
    }

    /**
     * Cancel an employee's own pending leave request.
     * Rejects attempts to cancel non-pending or other employees' requests.
     */
    public function cancelRequest(int $id, int $empId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE leave_requests
            SET status = 'Cancelled'
            WHERE id = :id AND employee_id = :emp_id AND status = 'Pending'
        ");
        $stmt->execute(['id' => $id, 'emp_id' => $empId]);
        $cancelled = $stmt->rowCount() > 0;

        if ($cancelled && $this->hasTable('leave_event_entitlements') && $this->hasTableColumn('leave_requests', 'event_entitlement_id')) {
            $req = $this->pdo->prepare("SELECT event_entitlement_id, number_of_days FROM leave_requests WHERE id = :id LIMIT 1");
            $req->execute(['id' => $id]);
            $row = $req->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['event_entitlement_id'])) {
                $this->pdo->prepare("
                    UPDATE leave_event_entitlements
                    SET pending_days = GREATEST(0, pending_days - :days)
                    WHERE id = :eid
                ")->execute([
                    'days' => (float)($row['number_of_days'] ?? 0),
                    'eid'  => (int)$row['event_entitlement_id'],
                ]);
            }
        }

        return $cancelled;
    }

    /**
     * Load all leave types merged with active policy configuration.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTypesWithActivePolicies(bool $activeOnly = false, ?string $asOfDate = null): array
    {
        $engine = new \App\Services\LeavePolicyEngine($this->pdo);
        return $engine->getAllTypesWithActivePolicies($activeOnly, $asOfDate);
    }

    /**
     * List all policy versions for a specific leave type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPoliciesForType(int $leaveTypeId): array
    {
        if (!$this->hasTable('leave_policy_versions')) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT lpv.*,
                   cu.username AS created_by_username,
                   au.username AS approved_by_username
            FROM leave_policy_versions lpv
            LEFT JOIN users cu ON lpv.created_by = cu.id
            LEFT JOIN users au ON lpv.approved_by = au.id
            WHERE lpv.leave_type_id = :lt_id
            ORDER BY lpv.version_number DESC, lpv.id DESC
        ");
        $stmt->execute(['lt_id' => $leaveTypeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new non-retroactive policy version (Draft, PendingReview, or Active).
     *
     * @param array<string, mixed> $data
     */
    public function createPolicyVersion(
        int $leaveTypeId,
        array $data,
        ?int $actorUserId = null,
        bool $activate = false
    ): int {
        if (!$this->hasTable('leave_policy_versions')) {
            return 0;
        }

        $type = $this->getLeaveTypeById($leaveTypeId);
        if (!$type) {
            return 0;
        }

        $maxStmt = $this->pdo->prepare("SELECT COALESCE(MAX(version_number), 0) FROM leave_policy_versions WHERE leave_type_id = :lt_id");
        $maxStmt->execute(['lt_id' => $leaveTypeId]);
        $nextVersion = ((int)$maxStmt->fetchColumn()) + 1;

        $code = strtoupper(trim((string)($data['leave_code'] ?? ($type['leave_code'] ?? ('TYPE_' . $leaveTypeId)))));
        $policyCode = substr(preg_replace('/[^A-Z0-9_]/', '_', $code) . '-V' . $nextVersion, 0, 50);

        $rawApprovalStatus = (string)($data['approval_status'] ?? 'Draft');
        $approvalStatus = $activate
            ? 'Active'
            : (in_array($rawApprovalStatus, ['Draft', 'PendingReview', 'Active', 'Superseded', 'Archived'], true)
                ? $rawApprovalStatus
                : 'Draft');

        $effFrom = !empty($data['effective_from']) ? (string)$data['effective_from'] : date('Y-m-d');
        $prevDay = date('Y-m-d', strtotime($effFrom . ' -1 day'));

        if ($approvalStatus === 'Active') {
            $this->pdo->prepare("
                UPDATE leave_policy_versions
                SET approval_status = 'Superseded',
                    effective_to = COALESCE(effective_to, :prev_day)
                WHERE leave_type_id = :lt_id AND approval_status = 'Active'
            ")->execute([
                'prev_day' => $prevDay,
                'lt_id'    => $leaveTypeId,
            ]);
        }

        $rawModel = (string)($data['entitlement_model'] ?? ($type['entitlement_model'] ?? 'annual'));
        $model = in_array($rawModel, ['annual', 'event_based', 'unpaid'], true) ? $rawModel : 'annual';
        $rawUnit = (string)($data['entitlement_unit'] ?? ($type['default_unit'] ?? 'working_days'));
        $unit = in_array($rawUnit, ['working_days', 'calendar_days'], true) ? $rawUnit : 'working_days';
        $rawClassif = (string)($data['classification'] ?? ($type['classification'] ?? 'Company'));
        $classif = in_array($rawClassif, ['Statutory', 'Company'], true) ? $rawClassif : 'Company';
        $rawTier = (string)($data['benefit_tier'] ?? '');
        $benefitTier = in_array($rawTier, ['statutory_minimum', 'employer_enhanced', 'company_benefit', 'unpaid'], true)
            ? $rawTier
            : ($model === 'unpaid' ? 'unpaid' : ($classif === 'Statutory' ? 'statutory_minimum' : 'company_benefit'));
        $rawSector = (string)($data['sector_scope'] ?? 'all');
        $sectorScope = in_array($rawSector, ['all', 'private', 'public'], true) ? $rawSector : 'all';
        $rulesJson = null;
        if (!empty($data['rules_json'])) {
            $rulesJson = is_string($data['rules_json']) ? $data['rules_json'] : json_encode($data['rules_json'], JSON_UNESCAPED_UNICODE);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO leave_policy_versions (
                leave_type_id, version_number, policy_code, effective_from, effective_to,
                approval_status, classification, entitlement_model, benefit_tier, sector_scope,
                legal_reference, entitlement_days, entitlement_unit, is_paid, allocation_frequency,
                carry_over_allowed, max_carry_over_days, cash_convertible, cash_conversion_max_days,
                min_service_months, service_window_months, counts_as_sil_equivalent, stack_with_equivalent,
                max_events_lifetime, max_extension_days, requires_documentation, document_requirements,
                requires_qualification, qualification_type, is_confidential, rules_json, change_notes,
                created_by, approved_by, approved_at
            ) VALUES (
                :lt_id, :ver, :pcode, :eff_from, :eff_to,
                :status, :classif, :model, :tier, :sector,
                :legal, :edays, :eunit, :paid, :freq,
                :carry, :max_carry, :cash, :max_cash,
                :min_svc, :win_svc, :sil_eq, :stack_eq,
                :max_ev, :max_ext, :req_doc, :doc_req,
                :req_qual, :qual_type, :is_conf, :rules_json, :notes,
                :cby, :aby, :aat
            )
        ");

        $effFrom = !empty($data['effective_from']) ? (string)$data['effective_from'] : date('Y-m-d');
        $effTo = !empty($data['effective_to']) ? (string)$data['effective_to'] : null;
        $days = (float)($data['entitlement_days'] ?? ($type['default_days'] ?? 0));

        $stmt->execute([
            'lt_id'     => $leaveTypeId,
            'ver'       => $nextVersion,
            'pcode'     => $policyCode,
            'eff_from'  => $effFrom,
            'eff_to'    => $effTo,
            'status'    => $approvalStatus,
            'classif'   => $classif,
            'model'     => $model,
            'tier'      => $benefitTier,
            'sector'    => $sectorScope,
            'legal'     => !empty($data['legal_reference']) ? trim((string)$data['legal_reference']) : ($type['legal_reference'] ?? null),
            'edays'     => $days,
            'eunit'     => $unit,
            'paid'      => $model === 'unpaid' ? 0 : (!isset($data['is_paid']) || !empty($data['is_paid']) ? 1 : 0),
            'freq'      => $model === 'event_based' ? 'per_event' : ($model === 'unpaid' ? 'unlimited_unpaid' : 'annual'),
            'carry'     => !empty($data['carry_over_allowed']) ? 1 : 0,
            'max_carry' => (float)($data['max_carry_over_days'] ?? 0),
            'cash'      => !empty($data['cash_convertible']) ? 1 : 0,
            'max_cash'  => (float)($data['cash_conversion_max_days'] ?? 0),
            'min_svc'   => max(0, (int)($data['min_service_months'] ?? 0)),
            'win_svc'   => !empty($data['service_window_months']) ? (int)$data['service_window_months'] : null,
            'sil_eq'    => !empty($data['counts_as_sil_equivalent']) ? 1 : 0,
            'stack_eq'  => !empty($data['stack_with_equivalent']) ? 1 : 0,
            'max_ev'    => !empty($data['max_events_lifetime']) ? (int)$data['max_events_lifetime'] : null,
            'max_ext'   => (float)($data['max_extension_days'] ?? 0),
            'req_doc'   => !empty($data['requires_documentation']) ? 1 : 0,
            'doc_req'   => !empty($data['document_requirements']) ? trim((string)$data['document_requirements']) : null,
            'req_qual'  => !empty($data['requires_qualification']) ? 1 : 0,
            'qual_type' => !empty($data['qualification_type']) ? trim((string)$data['qualification_type']) : null,
            'is_conf'   => !empty($data['is_confidential']) || !empty($type['is_confidential']) ? 1 : 0,
            'rules_json' => $rulesJson,
            'notes'     => !empty($data['change_notes']) ? trim((string)$data['change_notes']) : null,
            'cby'       => $actorUserId,
            'aby'       => $approvalStatus === 'Active' ? $actorUserId : null,
            'aat'       => $approvalStatus === 'Active' ? date('Y-m-d H:i:s') : null,
        ]);

        $policyId = (int)$this->pdo->lastInsertId();
        if ($policyId > 0 && $approvalStatus === 'Active' && $this->hasTableColumn('leave_types', 'active_policy_version_id')) {
            $this->pdo->prepare("
                UPDATE leave_types
                SET active_policy_version_id = :pid,
                    default_days = :days,
                    default_unit = :unit,
                    is_paid = :paid,
                    is_confidential = :conf
                WHERE id = :lt_id
            ")->execute([
                'pid'   => $policyId,
                'days'  => (int)round($days),
                'unit'  => $unit,
                'paid'  => $model === 'unpaid' ? 0 : (!isset($data['is_paid']) || !empty($data['is_paid']) ? 1 : 0),
                'conf'  => !empty($data['is_confidential']) || !empty($type['is_confidential']) ? 1 : 0,
                'lt_id' => $leaveTypeId,
            ]);
        }

        return $policyId;
    }

    /**
     * Approve & activate an existing policy version (superseding prior active version
     * cleanly without mutating historical balances or submitted leave requests).
     */
    public function activatePolicyVersion(int $policyId, int $reviewerUserId): bool
    {
        if (!$this->hasTable('leave_policy_versions')) {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM leave_policy_versions WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $policyId]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$policy) {
            return false;
        }

        $ltId = (int)$policy['leave_type_id'];
        $prevDay = date('Y-m-d', strtotime((string)$policy['effective_from'] . ' -1 day'));
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("
                UPDATE leave_policy_versions
                SET approval_status = 'Superseded',
                    effective_to = COALESCE(effective_to, :prev_day)
                WHERE leave_type_id = :lt_id AND id != :pid AND approval_status = 'Active'
            ")->execute([
                'prev_day' => $prevDay,
                'lt_id'    => $ltId,
                'pid'      => $policyId,
            ]);

            $this->pdo->prepare("
                UPDATE leave_policy_versions
                SET approval_status = 'Active',
                    approved_by = :aby,
                    approved_at = NOW()
                WHERE id = :pid
            ")->execute([
                'aby' => $reviewerUserId,
                'pid' => $policyId,
            ]);

            if ($this->hasTableColumn('leave_types', 'active_policy_version_id')) {
                $this->pdo->prepare("
                    UPDATE leave_types
                    SET active_policy_version_id = :pid,
                        default_days = :days,
                        default_unit = :unit,
                        is_paid = :paid,
                        is_confidential = :conf
                    WHERE id = :lt_id
                ")->execute([
                    'pid'   => $policyId,
                    'days'  => (int)round((float)$policy['entitlement_days']),
                    'unit'  => $policy['entitlement_unit'],
                    'paid'  => (int)$policy['is_paid'],
                    'conf'  => (int)$policy['is_confidential'],
                    'lt_id' => $ltId,
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->safeRollBack();
            error_log('[Leave::activatePolicyVersion] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all statutory leave qualification records for an employee.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQualificationsByEmployee(int $employeeId): array
    {
        if (!$this->hasTable('employee_leave_qualifications')) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT eq.*, u.username AS verifier_username
            FROM employee_leave_qualifications eq
            LEFT JOIN users u ON eq.verified_by = u.id
            WHERE eq.employee_id = :emp_id
            ORDER BY eq.id DESC
        ");
        $stmt->execute(['emp_id' => $employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List employee qualification records for HR verification.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllQualificationsForHr(?string $status = null, ?string $qtype = null): array
    {
        if (!$this->hasTable('employee_leave_qualifications')) {
            return [];
        }

        $sql = "
            SELECT eq.*,
                   e.employee_no, e.first_name, e.last_name, e.gender, e.date_hired,
                   d.department_name,
                   u.username AS verifier_username
            FROM employee_leave_qualifications eq
            INNER JOIN employees e ON eq.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN users u ON eq.verified_by = u.id
            WHERE 1=1
        ";
        $params = [];

        if ($status !== null && $status !== '') {
            $sql .= " AND eq.verification_status = :status";
            $params['status'] = $status;
        }
        if ($qtype !== null && $qtype !== '') {
            $sql .= " AND eq.qualification_type = :qtype";
            $params['qtype'] = $qtype;
        }

        $sql .= " ORDER BY CASE eq.verification_status WHEN 'PendingVerification' THEN 1 WHEN 'Verified' THEN 2 WHEN 'Expired' THEN 3 WHEN 'Revoked' THEN 4 ELSE 5 END ASC, eq.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create or update a statutory leave qualification record (SPIC, Paternity Marriage, SIL Exclusion).
     *
     * @param array<string, mixed> $data
     */
    public function upsertQualification(array $data, ?int $actorUserId = null): int
    {
        if (!$this->hasTable('employee_leave_qualifications')) {
            return 0;
        }

        $allowedTypes = ['solo_parent_spic', 'paternity_marriage', 'sil_exclusion', 'maternity_eligibility', 'slw_eligibility', 'vawc_protection'];
        $rawQtype = (string)($data['qualification_type'] ?? '');
        $qtype = in_array($rawQtype, $allowedTypes, true) ? $rawQtype : 'solo_parent_spic';
        $allowedStatuses = ['PendingVerification', 'Verified', 'Expired', 'Revoked', 'Rejected'];
        $rawStatus = (string)($data['verification_status'] ?? 'Verified');
        $status = in_array($rawStatus, $allowedStatuses, true) ? $rawStatus : 'Verified';

        $id = !empty($data['id']) ? (int)$data['id'] : 0;
        $verifiedBy = $status === 'Verified' ? $actorUserId : null;
        $verifiedAt = $status === 'Verified' ? date('Y-m-d H:i:s') : null;

        if ($id > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE employee_leave_qualifications
                SET qualification_type = :qtype,
                    verification_status = :vstatus,
                    reference_no = :ref_no,
                    valid_from = :vfrom,
                    valid_to = :vto,
                    sil_exempt_override = :sil_ex,
                    exclusion_reason = :ex_reason,
                    supporting_notes = :notes,
                    verified_by = COALESCE(:vby, verified_by),
                    verified_at = COALESCE(:vat, verified_at)
                WHERE id = :id
            ");
            $stmt->execute([
                'qtype'     => $qtype,
                'vstatus'   => $status,
                'ref_no'    => !empty($data['reference_no']) ? substr(trim((string)$data['reference_no']), 0, 100) : null,
                'vfrom'     => !empty($data['valid_from']) ? (string)$data['valid_from'] : null,
                'vto'       => !empty($data['valid_to']) ? (string)$data['valid_to'] : null,
                'sil_ex'    => !empty($data['sil_exempt_override']) ? 1 : 0,
                'ex_reason' => !empty($data['exclusion_reason']) ? substr(trim((string)$data['exclusion_reason']), 0, 150) : null,
                'notes'     => !empty($data['supporting_notes']) ? trim((string)$data['supporting_notes']) : null,
                'vby'       => $verifiedBy,
                'vat'       => $verifiedAt,
                'id'        => $id,
            ]);
            return $id;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO employee_leave_qualifications (
                employee_id, qualification_type, verification_status, reference_no,
                valid_from, valid_to, sil_exempt_override, exclusion_reason,
                supporting_notes, verified_by, verified_at
            ) VALUES (
                :emp_id, :qtype, :vstatus, :ref_no,
                :vfrom, :vto, :sil_ex, :ex_reason,
                :notes, :vby, :vat
            )
        ");
        $stmt->execute([
            'emp_id'    => (int)$data['employee_id'],
            'qtype'     => $qtype,
            'vstatus'   => $status,
            'ref_no'    => !empty($data['reference_no']) ? substr(trim((string)$data['reference_no']), 0, 100) : null,
            'vfrom'     => !empty($data['valid_from']) ? (string)$data['valid_from'] : null,
            'vto'       => !empty($data['valid_to']) ? (string)$data['valid_to'] : null,
            'sil_ex'    => !empty($data['sil_exempt_override']) ? 1 : 0,
            'ex_reason' => !empty($data['exclusion_reason']) ? substr(trim((string)$data['exclusion_reason']), 0, 150) : null,
            'notes'     => !empty($data['supporting_notes']) ? trim((string)$data['supporting_notes']) : null,
            'vby'       => $verifiedBy,
            'vat'       => $verifiedAt,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Load event-based leave entitlements (Maternity, Paternity, SLW, VAWC, Bereavement) for an employee.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEventEntitlementsByEmployee(int $employeeId): array
    {
        if (!$this->hasTable('leave_event_entitlements')) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT lee.*, lt.leave_name, lt.leave_code, lt.classification, lt.benefit_category, lt.legal_reference
            FROM leave_event_entitlements lee
            INNER JOIN leave_types lt ON lee.leave_type_id = lt.id
            WHERE lee.employee_id = :emp_id
            ORDER BY lee.event_date DESC, lee.id DESC
        ");
        $stmt->execute(['emp_id' => $employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Request additional information / clarification from the filing employee without rejecting.
     *
     * @return array{ok: bool, message: string}
     */
    public function requestAdditionalInfo(int $requestId, int $hrUserId, string $note): array
    {
        $cleanNote = trim($note);
        if ($cleanNote === '') {
            return ['ok' => false, 'message' => 'Please enter a specific clarification message for the employee.'];
        }
        if (!$this->hasTableColumn('leave_requests', 'info_request_status')) {
            return ['ok' => false, 'message' => 'Clarification columns require database migration.'];
        }

        $req = $this->getRequestById($requestId);
        if (!$req || ($req['status'] ?? '') !== 'Pending') {
            return ['ok' => false, 'message' => 'Additional information can only be requested on Pending leave requests.'];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE leave_requests
                SET info_request_status = 'Requested',
                    hr_info_request_note = :note,
                    info_requested_by = :uid,
                    info_requested_at = NOW()
                WHERE id = :id AND status = 'Pending'
            ");
            $stmt->execute([
                'note' => $cleanNote,
                'uid'  => $hrUserId,
                'id'   => $requestId,
            ]);

            $isConf = \App\Services\ConfidentialLeaveService::isConfidentialRequest($req);
            $stmtUser = $this->pdo->prepare("SELECT id FROM users WHERE employee_id = :emp_id AND status = 'Active' LIMIT 1");
            $stmtUser->execute(['emp_id' => (int)$req['employee_id']]);
            $ownerUserId = (int)$stmtUser->fetchColumn();

            if ($ownerUserId > 0) {
                $defaultMsg = "HR requested additional information on your leave request #LR-"
                    . str_pad((string)$requestId, 3, '0', STR_PAD_LEFT) . ": {$cleanNote}";
                $title = \App\Services\ConfidentialLeaveService::safeNotificationTitle($isConf, 'Leave Clarification Requested');
                $msg = \App\Services\ConfidentialLeaveService::safeNotificationMessage($isConf, $requestId, $defaultMsg);

                $this->pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, event_type, entity_id, is_read, created_at)
                    VALUES (:uid, :title, :msg, 'info', 'leave.info_requested', :eid, 0, NOW())
                ")->execute([
                    'uid'   => $ownerUserId,
                    'title' => $title,
                    'msg'   => $msg,
                    'eid'   => $requestId,
                ]);
            }

            $this->pdo->commit();
            return ['ok' => true, 'message' => 'Additional information request sent to employee.'];
        } catch (\Throwable $e) {
            $this->safeRollBack();
            error_log('[Leave::requestAdditionalInfo] ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Failed to send additional information request.'];
        }
    }

    /**
     * Record the employee's clarification response on a Pending leave request.
     *
     * @return array{ok: bool, message: string}
     */
    public function respondToInfoRequest(int $requestId, int $employeeId, string $responseNote, ?int $actorUserId = null): array
    {
        $cleanNote = trim($responseNote);
        if ($cleanNote === '') {
            return ['ok' => false, 'message' => 'Please enter your clarification response before submitting.'];
        }
        if (!$this->hasTableColumn('leave_requests', 'info_request_status')) {
            return ['ok' => false, 'message' => 'Clarification columns require database migration.'];
        }

        $req = $this->getRequestById($requestId);
        if (!$req || (int)$req['employee_id'] !== $employeeId || ($req['status'] ?? '') !== 'Pending') {
            return ['ok' => false, 'message' => 'Pending leave request not found or unauthorized.'];
        }

        $stmt = $this->pdo->prepare("
            UPDATE leave_requests
            SET info_request_status = 'Responded',
                employee_info_response = :resp,
                info_responded_at = NOW()
            WHERE id = :id AND employee_id = :emp_id AND status = 'Pending'
        ");
        $ok = $stmt->execute([
            'resp'   => $cleanNote,
            'id'     => $requestId,
            'emp_id' => $employeeId,
        ]);

        return $ok
            ? ['ok' => true, 'message' => 'Your clarification response has been recorded for HR review.']
            : ['ok' => false, 'message' => 'Unable to save clarification response.'];
    }

    /**
     * Summarize approved and pending unpaid leave (LWOP) days for an employee.
     *
     * @return array{approved_days: int, pending_days: int, request_count: int}
     */
    public function getUnpaidLeaveSummaryByEmployee(int $employeeId, ?int $year = null): array
    {
        $year = $year ?: (int)date('Y');
        if (!$this->hasTableColumn('leave_requests', 'is_paid') && !$this->hasTableColumn('leave_types', 'entitlement_model')) {
            return ['approved_days' => 0, 'pending_days' => 0, 'request_count' => 0];
        }

        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN lr.status = 'Approved' THEN lr.number_of_days ELSE 0 END), 0) AS approved_days,
                COALESCE(SUM(CASE WHEN lr.status = 'Pending' THEN lr.number_of_days ELSE 0 END), 0) AS pending_days,
                COUNT(*) AS request_count
            FROM leave_requests lr
            INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = :emp_id
              AND (lr.is_paid = 0 OR lt.entitlement_model = 'unpaid')
              AND lr.start_date BETWEEN :ystart AND :yend
              AND lr.status IN ('Approved', 'Pending')
        ");
        $stmt->execute([
            'emp_id' => $employeeId,
            'ystart' => $yearStart,
            'yend'   => $yearEnd,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'approved_days' => (int)($row['approved_days'] ?? 0),
            'pending_days'  => (int)($row['pending_days'] ?? 0),
            'request_count' => (int)($row['request_count'] ?? 0),
        ];
    }
}
