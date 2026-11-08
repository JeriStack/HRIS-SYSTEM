<?php

/**
 * Activity Log Model
 * Tracks auditable operations across all application modules
 * Employee HRIS & Leave Management System
 */

namespace App\Models;

use Core\AuditService;
use Core\Database;
use PDO;

class ActivityLog
{
    private PDO $pdo;
    private AuditService $auditService;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo !== null) {
            $this->pdo = $pdo;
        } else {
            $this->pdo = Database::getInstance()->getConnection();
        }
        $this->auditService = AuditService::getInstance($this->pdo);
    }

    /**
     * Retrieve recent logs with associated username and role.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecent(int $limit = 50): array
    {
        return $this->auditService->getFiltered([], $limit, 0);
    }

    /**
     * Search and filter audit activity logs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(?string $search = null, ?string $action = null, int $limit = 100): array
    {
        $filters = [];
        if (!empty($search)) {
            $filters['q'] = $search;
        }
        if (!empty($action)) {
            $filters['action'] = $action;
        }

        return $this->auditService->getFiltered($filters, $limit, 0);
    }

    /**
     * Advanced filtered query supporting pagination, status, role, date ranges.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getFiltered(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->auditService->getFiltered($filters, $limit, $offset);
    }

    /**
     * Count matching filtered logs.
     *
     * @param array<string, mixed> $filters
     */
    public function countFiltered(array $filters = []): int
    {
        return $this->auditService->countFiltered($filters);
    }

    /**
     * Compute database-backed activity statistics.
     *
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return $this->auditService->getStats();
    }

    /**
     * Retrieve resource-specific audit history (e.g. Employee #5).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getResourceHistory(string $entityType, string $entityId, int $limit = 20): array
    {
        return $this->auditService->getResourceHistory($entityType, $entityId, $limit);
    }

    /**
     * Get distinct actions recorded in the audit trail.
     *
     * @return array<int, string>
     */
    public function getDistinctActions(): array
    {
        return $this->auditService->getDistinctActions();
    }

    /**
     * Export audit trail to formula-injection safe CSV.
     *
     * @param array<string, mixed> $filters
     */
    public function exportCsv(array $filters = []): string
    {
        return $this->auditService->exportCsv($filters);
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
     * Record a new audit log entry with optional before/after value diffing and status.
     *
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function log(
        ?int $userId,
        string $action,
        string $description,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $status = 'SUCCESS',
        ?string $category = null
    ): bool {
        $eventId = $this->auditService->record([
            'actor_user_id' => $userId,
            'action'        => $action,
            'description'   => $description,
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'old_values'    => $oldValues,
            'new_values'    => $newValues,
            'status'        => $status,
            'category'      => $category,
        ]);

        return !empty($eventId);
    }
}
