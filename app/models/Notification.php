<?php
/**
 * Notification Model
 * Employee HRIS & Leave Management System
 */

namespace App\Models;

use Core\Database;
use PDO;

class Notification
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all notifications for a specific user
     */
    public function getByUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM notifications 
             WHERE user_id = :user_id 
             ORDER BY created_at DESC 
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get unread notification count for a user
     */
    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM notifications 
             WHERE user_id = :user_id AND is_read = 0"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Create a notification for a user.
     *
     * @param string|null $eventType Real-time event key (e.g. 'leave_request.approved'); null for inbox-only
     * @param int|null $entityId Related record id (e.g. leave request id)
     */
    public function create(
        int $userId,
        string $title,
        string $message,
        string $type = 'info',
        ?string $eventType = null,
        ?int $entityId = null
    ): int {
        $validTypes = ['info', 'success', 'warning', 'error'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'info';
        }

        if ($eventType !== null && !preg_match('/^[a-z_]{1,30}\.[a-z_]{1,30}$/D', $eventType)) {
            $eventType = null;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO notifications (user_id, title, message, type, event_type, entity_id, is_read, created_at)
             VALUES (:user_id, :title, :message, :type, :event_type, :entity_id, 0, NOW())"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':event_type', $eventType, $eventType === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', $entityId, $entityId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    /**
     * Notify all active users with a specific role.
     *
     * @return int Number of notifications created
     */
    public function notifyRole(
        string $role,
        string $title,
        string $message,
        string $type = 'info',
        ?string $eventType = null,
        ?int $entityId = null
    ): int {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE role = :role AND status = 'Active'");
        $stmt->bindValue(':role', $role, PDO::PARAM_STR);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($users as $userId) {
            $this->create((int)$userId, $title, $message, $type, $eventType, $entityId);
        }

        return count($users);
    }

    /**
     * Mark a single notification as read
     */
    public function markAsRead(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE notifications 
             SET is_read = 1 
             WHERE id = :id AND user_id = :user_id"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE notifications 
             SET is_read = 1 
             WHERE user_id = :user_id AND is_read = 0"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
