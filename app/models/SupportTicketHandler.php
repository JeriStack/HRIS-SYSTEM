<?php

/**
 * SupportTicketHandler Model
 * Manages designated confidential complaint handlers within existing HR/Admin roles.
 */

namespace App\Models;

use Core\Model;
use PDO;

class SupportTicketHandler extends Model
{
    public function __construct(?PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            parent::__construct();
        }
    }

    public function getDb(): PDO
    {
        return $this->db;
    }

    /**
     * Check whether a specific user is actively designated to handle confidential complaints.
     */
    public function isAuthorizedConfidentialHandler(int $userId): bool
    {
        $sql = "
            SELECT sth.id
            FROM support_ticket_handlers sth
            INNER JOIN users u ON sth.user_id = u.id
            WHERE sth.user_id = :uid
              AND sth.is_active = 1
              AND sth.can_handle_confidential = 1
              AND u.status = 'Active'
              AND u.role IN ('hr', 'admin')
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Retrieve eligible designated confidential handlers, excluding conflicted parties.
     *
     * @param int|null $excludeEmployeeId Employee ID that must be excluded due to COI
     * @param bool     $hrOnly            Restrict strictly to HR role handlers when checking HR availability
     * @return array<int, array<string, mixed>>
     */
    public function getEligibleConfidentialHandlers(?int $excludeEmployeeId = null, bool $hrOnly = false): array
    {
        $conditions = [
            "sth.is_active = 1",
            "sth.can_handle_confidential = 1",
            "u.status = 'Active'",
            $hrOnly ? "u.role = 'hr'" : "u.role IN ('hr', 'admin')",
        ];
        $params = [];

        if ($excludeEmployeeId !== null && $excludeEmployeeId > 0) {
            $conditions[] = "(u.employee_id IS NULL OR u.employee_id != :exclude_emp_id)";
            $params['exclude_emp_id'] = $excludeEmployeeId;
        }

        $where = implode(' AND ', $conditions);
        $sql = "
            SELECT
                u.id AS user_id,
                u.username,
                u.role,
                u.employee_id,
                e.employee_no,
                e.first_name,
                e.last_name,
                d.department_name,
                sth.designation_notes,
                sth.created_at AS designated_at
            FROM support_ticket_handlers sth
            INNER JOIN users u ON sth.user_id = u.id
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE {$where}
            ORDER BY CASE WHEN u.role = 'hr' THEN 0 ELSE 1 END,
                     e.last_name ASC,
                     e.first_name ASC,
                     u.username ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve standard HR/Admin users eligible for non-confidential support assignment.
     *
     * @param int|null $excludeEmployeeId Employee ID excluded due to COI or self-subject rule
     * @return array<int, array<string, mixed>>
     */
    public function getAssignableStandardHandlers(?int $excludeEmployeeId = null): array
    {
        $conditions = [
            "u.status = 'Active'",
            "u.role IN ('hr', 'admin')",
        ];
        $params = [];

        if ($excludeEmployeeId !== null && $excludeEmployeeId > 0) {
            $conditions[] = "(u.employee_id IS NULL OR u.employee_id != :exclude_emp_id)";
            $params['exclude_emp_id'] = $excludeEmployeeId;
        }

        $where = implode(' AND ', $conditions);
        $sql = "
            SELECT
                u.id AS user_id,
                u.username,
                u.role,
                u.employee_id,
                e.employee_no,
                e.first_name,
                e.last_name,
                d.department_name,
                COALESCE(sth.is_active, 0) AS is_confidential_handler
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN support_ticket_handlers sth ON sth.user_id = u.id AND sth.can_handle_confidential = 1
            WHERE {$where}
            ORDER BY CASE WHEN u.role = 'hr' THEN 0 ELSE 1 END,
                     e.last_name ASC,
                     e.first_name ASC,
                     u.username ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve non-conflicted active Admins for escalated complaint oversight notifications.
     *
     * @param int|null $excludeEmployeeId Employee ID excluded due to COI
     * @return array<int, array<string, mixed>>
     */
    public function getEligibleEscalationAdmins(?int $excludeEmployeeId = null): array
    {
        $conditions = [
            "u.status = 'Active'",
            "u.role = 'admin'",
        ];
        $params = [];

        if ($excludeEmployeeId !== null && $excludeEmployeeId > 0) {
            $conditions[] = "(u.employee_id IS NULL OR u.employee_id != :exclude_emp_id)";
            $params['exclude_emp_id'] = $excludeEmployeeId;
        }

        $where = implode(' AND ', $conditions);
        $sql = "
            SELECT
                u.id AS user_id,
                u.username,
                u.role,
                u.employee_id,
                e.first_name,
                e.last_name
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.id
            WHERE {$where}
            ORDER BY u.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List all HR and Admin accounts with their current confidential handler designation state.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHandlerRosterWithStatus(): array
    {
        $sql = "
            SELECT
                u.id AS user_id,
                u.username,
                u.role,
                u.status AS account_status,
                u.employee_id,
                e.employee_no,
                e.first_name,
                e.last_name,
                d.department_name,
                COALESCE(sth.can_handle_confidential, 0) AS can_handle_confidential,
                COALESCE(sth.is_active, 0) AS is_designated_active,
                sth.designation_notes,
                sth.granted_by_user_id,
                gu.username AS granted_by_username,
                sth.created_at AS designated_at,
                sth.updated_at AS designation_updated_at
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN support_ticket_handlers sth ON sth.user_id = u.id
            LEFT JOIN users gu ON sth.granted_by_user_id = gu.id
            WHERE u.role IN ('hr', 'admin')
            ORDER BY CASE WHEN u.status = 'Active' THEN 0 ELSE 1 END,
                     CASE WHEN u.role = 'hr' THEN 0 ELSE 1 END,
                     e.last_name ASC,
                     e.first_name ASC,
                     u.username ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a target user account for handler validation.
     *
     * @return array<string, mixed>|null
     */
    public function findCandidateUser(int $userId): ?array
    {
        $sql = "
            SELECT
                u.id,
                u.username,
                u.role,
                u.status,
                u.employee_id
            FROM users u
            WHERE u.id = :uid
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Grant or revoke confidential complaint handling designation for a specific user.
     */
    public function upsertHandlerDesignation(
        int $targetUserId,
        bool $active,
        string $notes,
        int $actorUserId
    ): bool {
        $sql = "
            INSERT INTO support_ticket_handlers (
                user_id,
                can_handle_confidential,
                is_active,
                designation_notes,
                granted_by_user_id,
                created_at
            ) VALUES (
                :user_id,
                1,
                :is_active,
                :notes,
                :granted_by,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                can_handle_confidential = 1,
                is_active = VALUES(is_active),
                designation_notes = VALUES(designation_notes),
                granted_by_user_id = VALUES(granted_by_user_id),
                updated_at = NOW()
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id'    => $targetUserId,
            'is_active'  => $active ? 1 : 0,
            'notes'      => $notes !== '' ? mb_substr($notes, 0, 255) : null,
            'granted_by' => $actorUserId,
        ]);
    }
}
