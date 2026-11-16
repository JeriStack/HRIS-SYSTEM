<?php

/**
 * Employment History Model
 * Preserves official chronological career progression milestones per employee
 * (distinct from system activity audit logs).
 */

namespace App\Models;

use Core\Model;
use DateTimeImmutable;
use PDO;

class EmploymentHistory extends Model
{
    public const EVENT_INITIAL_HIRING     = 'Initial Hiring';
    public const EVENT_PROMOTION          = 'Promotion';
    public const EVENT_DEPT_TRANSFER      = 'Department Transfer';
    public const EVENT_POSITION_CHANGE    = 'Position Change';
    public const EVENT_STATUS_CHANGE      = 'Employment Status Change';
    public const EVENT_TYPE_CHANGE        = 'Employment Type Change';
    public const EVENT_OTHER              = 'Other';

    public const VALID_EVENT_TYPES = [
        self::EVENT_INITIAL_HIRING,
        self::EVENT_PROMOTION,
        self::EVENT_DEPT_TRANSFER,
        self::EVENT_POSITION_CHANGE,
        self::EVENT_STATUS_CHANGE,
        self::EVENT_TYPE_CHANGE,
        self::EVENT_OTHER,
    ];

    public function __construct(?PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            parent::__construct();
        }
    }

    /**
     * Retrieve chronological employment history for one employee (newest milestone first).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByEmployee(int $employeeId): array
    {
        $sql = "
            SELECT
                eh.id,
                eh.employee_id,
                eh.event_type,
                eh.effective_date,
                eh.previous_department_id,
                eh.new_department_id,
                eh.previous_position_id,
                eh.new_position_id,
                eh.employment_type,
                eh.description,
                eh.recorded_by,
                eh.created_at,
                pd.department_name AS previous_department_name,
                nd.department_name AS new_department_name,
                pp.position_name AS previous_position_name,
                np.position_name AS new_position_name,
                u.username AS recorded_by_username,
                re.first_name AS recorded_by_first_name,
                re.last_name AS recorded_by_last_name
            FROM employment_history eh
            LEFT JOIN departments pd ON eh.previous_department_id = pd.id
            LEFT JOIN departments nd ON eh.new_department_id = nd.id
            LEFT JOIN positions pp ON eh.previous_position_id = pp.id
            LEFT JOIN positions np ON eh.new_position_id = np.id
            LEFT JOIN users u ON eh.recorded_by = u.id
            LEFT JOIN employees re ON u.employee_id = re.id
            WHERE eh.employee_id = :emp_id
            ORDER BY eh.effective_date DESC, eh.id DESC
        ";

        return $this->fetchAll($sql, ['emp_id' => $employeeId]);
    }

    /**
     * Retrieve the most recent recorded Promotion milestone for an employee, if any exists.
     */
    public function getLastPromotion(int $employeeId): ?array
    {
        $sql = "
            SELECT
                eh.*,
                np.position_name AS new_position_name,
                nd.department_name AS new_department_name
            FROM employment_history eh
            LEFT JOIN positions np ON eh.new_position_id = np.id
            LEFT JOIN departments nd ON eh.new_department_id = nd.id
            WHERE eh.employee_id = :emp_id
              AND eh.event_type = :event_type
            ORDER BY eh.effective_date DESC, eh.id DESC
            LIMIT 1
        ";

        return $this->fetchOne($sql, [
            'emp_id'     => $employeeId,
            'event_type' => self::EVENT_PROMOTION,
        ]);
    }

    /**
     * Append an immutable employment history milestone record.
     * Prior milestones are never overwritten when recording transitions.
     *
     * @param array<string, mixed> $data
     */
    public function recordEvent(array $data): int
    {
        $eventType = trim((string)($data['event_type'] ?? self::EVENT_OTHER));
        if (!in_array($eventType, self::VALID_EVENT_TYPES, true)) {
            $eventType = self::EVENT_OTHER;
        }

        $effectiveDate = trim((string)($data['effective_date'] ?? date('Y-m-d')));
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveDate);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $effectiveDate) {
            $effectiveDate = date('Y-m-d');
        }

        $now = date('Y-m-d H:i:s');

        $sql = "
            INSERT INTO employment_history (
                employee_id,
                event_type,
                effective_date,
                previous_department_id,
                new_department_id,
                previous_position_id,
                new_position_id,
                employment_type,
                description,
                recorded_by,
                created_at
            ) VALUES (
                :employee_id,
                :event_type,
                :effective_date,
                :prev_dept_id,
                :new_dept_id,
                :prev_pos_id,
                :new_pos_id,
                :employment_type,
                :description,
                :recorded_by,
                :created_at
            )
        ";

        $this->execute($sql, [
            'employee_id'     => (int)$data['employee_id'],
            'event_type'      => $eventType,
            'effective_date'  => $effectiveDate,
            'prev_dept_id'    => !empty($data['previous_department_id']) ? (int)$data['previous_department_id'] : null,
            'new_dept_id'     => !empty($data['new_department_id']) ? (int)$data['new_department_id'] : null,
            'prev_pos_id'     => !empty($data['previous_position_id']) ? (int)$data['previous_position_id'] : null,
            'new_pos_id'      => !empty($data['new_position_id']) ? (int)$data['new_position_id'] : null,
            'employment_type' => !empty($data['employment_type']) ? trim((string)$data['employment_type']) : null,
            'description'     => !empty($data['description']) ? trim((string)$data['description']) : null,
            'recorded_by'     => !empty($data['recorded_by']) ? (int)$data['recorded_by'] : null,
            'created_at'      => $now,
        ]);

        return (int)$this->lastInsertId();
    }

    /**
     * Validate HR manual employment history submission payload.
     *
     * @param array<string, mixed> $input
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public static function validateEventInput(array $input): array
    {
        $eventType = trim((string)($input['event_type'] ?? ''));
        $effectiveDate = trim((string)($input['effective_date'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $employmentType = trim((string)($input['employment_type'] ?? ''));

        $errors = [];

        if (!in_array($eventType, self::VALID_EVENT_TYPES, true)) {
            $errors['event_type'] = 'Please select a valid employment event type.';
        }

        if ($effectiveDate === '') {
            $errors['effective_date'] = 'Effective date is required.';
        } else {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveDate);
            if (!$parsed || $parsed->format('Y-m-d') !== $effectiveDate) {
                $errors['effective_date'] = 'Effective date must be a valid YYYY-MM-DD date.';
            }
        }

        if ($description === '') {
            $errors['description'] = 'Please provide a note or reason for this employment milestone.';
        } elseif (mb_strlen($description) > 1000) {
            $errors['description'] = 'Notes cannot exceed 1,000 characters.';
        }

        $validTypes = (array)config('profile.employment_types', ['Regular', 'Probationary', 'Contractual', 'Project-Based', 'Part-Time']);
        if ($employmentType !== '' && !in_array($employmentType, $validTypes, true)) {
            $errors['employment_type'] = 'Please select a valid employment type.';
        }

        return [
            'data' => [
                'event_type'             => $eventType,
                'effective_date'         => $effectiveDate,
                'previous_department_id' => !empty($input['previous_department_id']) ? (int)$input['previous_department_id'] : null,
                'new_department_id'      => !empty($input['new_department_id']) ? (int)$input['new_department_id'] : null,
                'previous_position_id'   => !empty($input['previous_position_id']) ? (int)$input['previous_position_id'] : null,
                'new_position_id'        => !empty($input['new_position_id']) ? (int)$input['new_position_id'] : null,
                'employment_type'        => $employmentType !== '' ? $employmentType : null,
                'description'            => $description,
            ],
            'errors' => $errors,
        ];
    }
}
