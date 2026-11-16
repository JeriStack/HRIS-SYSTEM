<?php

/**
 * AnnouncementRepositoryInterface
 * HRIS-SYSTEM — Domain-Driven Design (Announcement Domain)
 */

declare(strict_types=1);

namespace App\Domain\Announcement\Repositories;

use App\Domain\Announcement\Entities\Announcement;
use App\Domain\Announcement\Entities\AnnouncementReadReceipt;
use DateTimeImmutable;

interface AnnouncementRepositoryInterface
{
    public function findById(int $id): ?Announcement;

    public function findByIdForUpdate(int $id): ?Announcement;

    public function save(Announcement $announcement): int;

    public function deleteDraft(int $id): bool;

    /**
     * Validate that all supplied department IDs exist in the departments table.
     *
     * @param list<int> $departmentIds
     * @return list<int> Validated existing department IDs
     */
    public function validateDepartmentIds(array $departmentIds): array;

    /**
     * Record a unique read receipt for an employee and announcement (idempotent).
     *
     * @return array{receipt: AnnouncementReadReceipt, newly_created: bool}
     */
    public function recordReadReceipt(int $announcementId, int $employeeId, DateTimeImmutable $readAt): array;

    public function hasEmployeeRead(int $announcementId, int $employeeId): bool;

    /**
     * Find IDs of Scheduled announcements whose scheduled_at <= $now.
     *
     * @return list<int>
     */
    public function findDueScheduledIds(DateTimeImmutable $now, int $limit = 50): array;

    /**
     * Find IDs of Published announcements whose expires_at IS NOT NULL AND expires_at <= $now.
     *
     * @return list<int>
     */
    public function findDueExpiredIds(DateTimeImmutable $now, int $limit = 50): array;
}
