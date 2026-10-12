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
     * Create a notification for a user
     */
    public function create(int $userId, string $title, string $message, string $type = 'info'): int
    {
        $validTypes = ['info', 'success', 'warning', 'error'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'info';
        }

        $stmt = $this->db->prepare(
            "INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
             VALUES (:user_id, :title, :message, :type, 0, NOW())"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    /**
     * Notify all users with a specific role
     */
    public function notifyRole(string $role, string $title, string $message, string $type = 'info'): void
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE role = :role AND status = 'Active'");
        $stmt->bindValue(':role', $role, PDO::PARAM_STR);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($users as $userId) {
            $this->create((int)$userId, $title, $message, $type);
        }
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
