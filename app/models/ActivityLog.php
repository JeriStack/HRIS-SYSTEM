<?php
/**
 * Activity Log Model
 * Tracks auditable operations across all application modules
 * Employee HRIS & Leave Management System
 */

namespace App\Models;

use Core\Database;
use PDO;

class ActivityLog
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Retrieve recent logs with associated username and role.
     */
    public function getRecent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.username, u.role
            FROM activity_logs a
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Search and filter audit activity logs.
     */
    public function getAll(?string $search = null, ?string $action = null, int $limit = 100): array
    {
        $sql = "
            SELECT a.*, u.username, u.role
            FROM activity_logs a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($action)) {
            $sql .= " AND a.action = :action";
            $params['action'] = $action;
        }

        if (!empty($search)) {
            $sql .= " AND (a.description LIKE :q OR u.username LIKE :q OR a.ip_address LIKE :q)";
            $params['q'] = "%{$search}%";
        }

        $sql .= " ORDER BY a.id DESC LIMIT " . (int)$limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Whether an audit row already exists for a given action on a specific entity.
     * Supports idempotent event handlers so a replayed domain event does not write
     * a duplicate audit entry on top of the one the controller already recorded.
     */
    public function existsForEntity(string $action, string $entityType, string $entityId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM activity_logs
             WHERE action = :action AND entity_type = :entity_type AND entity_id = :entity_id
             LIMIT 1"
        );
        $stmt->execute([
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Record a new audit log entry.
     */
    public function log(?int $userId, string $action, string $description, ?string $entityType = null, ?string $entityId = null): bool
    {
        $ip = client_ip() ?? '127.0.0.1';
        $agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255);

        $stmt = $this->pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, entity_type, entity_id, ip_address, user_agent)
            VALUES (:user_id, :action, :description, :entity_type, :entity_id, :ip_address, :user_agent)
        ");
        return $stmt->execute([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'ip_address'  => $ip,
            'user_agent'  => $agent,
        ]);
    }
}
