<?php

/**
 * MySqlAnnouncementRepository
 * HRIS-SYSTEM — Infrastructure Persistence Layer (Announcement Domain)
 *
 * Implements transaction-safe persistence, row-level locking, audience resolution,
 * read-receipt idempotency, and batch-loaded HR/Employee projections without N+1 queries.
 */

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Announcement;

use App\Domain\Announcement\Entities\Announcement;
use App\Domain\Announcement\Entities\AnnouncementAttachment;
use App\Domain\Announcement\Entities\AnnouncementReadReceipt;
use App\Domain\Announcement\Entities\AnnouncementTarget;
use App\Domain\Announcement\Repositories\AnnouncementRepositoryInterface;
use App\Domain\Announcement\ValueObjects\AnnouncementCategory;
use App\Domain\Announcement\ValueObjects\AnnouncementContent;
use App\Domain\Announcement\ValueObjects\AnnouncementPriority;
use App\Domain\Announcement\ValueObjects\AnnouncementPublicationSchedule;
use App\Domain\Announcement\ValueObjects\AnnouncementStatus;
use App\Domain\Announcement\ValueObjects\AnnouncementTitle;
use App\Domain\Announcement\ValueObjects\ExpirationDate;
use App\Domain\Announcement\ValueObjects\PublicationDate;
use Core\Database;
use DateTimeImmutable;
use PDO;

class MySqlAnnouncementRepository implements AnnouncementRepositoryInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    public function findById(int $id): ?Announcement
    {
        return $this->fetchEntityById($id, false);
    }

    public function findByIdForUpdate(int $id): ?Announcement
    {
        return $this->fetchEntityById($id, true);
    }

    private function fetchEntityById(int $id, bool $forUpdate): ?Announcement
    {
        if ($id <= 0) {
            return null;
        }

        $lockSql = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->pdo->prepare("
            SELECT id, title, content, category, priority, status, author_id,
                   published_at, scheduled_at, expires_at, is_pinned,
                   created_at, updated_at, archived_at
            FROM announcements
            WHERE id = :id
            LIMIT 1{$lockSql}
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $targets = $this->loadTargetsForAnnouncement($id);
        if (empty($targets)) {
            $targets = [AnnouncementTarget::allEmployees()];
        }
        $attachments = $this->loadAttachmentsForAnnouncement($id);

        return $this->hydrateEntity($row, $targets, $attachments);
    }

    public function save(Announcement $announcement): int
    {
        $schedule = $announcement->publicationSchedule();
        $publishedAtStr = $schedule->publishedAt()?->toDatabaseString();
        $scheduledAtStr = $schedule->scheduledAt()?->toDatabaseString();
        $expiresAtStr = $schedule->expiresAt()?->toDatabaseString();
        $archivedAtStr = $announcement->archivedAt()?->format('Y-m-d H:i:s');

        if ($announcement->id() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO announcements (
                    title, content, category, priority, status, author_id,
                    published_at, scheduled_at, expires_at, is_pinned,
                    created_at, updated_at, archived_at
                ) VALUES (
                    :title, :content, :category, :priority, :status, :author_id,
                    :published_at, :scheduled_at, :expires_at, :is_pinned,
                    :created_at, :updated_at, :archived_at
                )
            ");
            $stmt->execute([
                'title'        => $announcement->title()->value(),
                'content'      => $announcement->content()->value(),
                'category'     => $announcement->category()->value(),
                'priority'     => $announcement->priority()->value(),
                'status'       => $announcement->status()->value(),
                'author_id'    => $announcement->authorId(),
                'published_at' => $publishedAtStr,
                'scheduled_at' => $scheduledAtStr,
                'expires_at'   => $expiresAtStr,
                'is_pinned'    => $announcement->isPinned() ? 1 : 0,
                'created_at'   => ($announcement->createdAt() ?? new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at'   => $announcement->updatedAt()?->format('Y-m-d H:i:s'),
                'archived_at'  => $archivedAtStr,
            ]);

            $id = (int)$this->pdo->lastInsertId();
            $announcement->assignPersistedId($id);
        } else {
            $id = $announcement->id();
            $stmt = $this->pdo->prepare("
                UPDATE announcements
                SET title = :title,
                    content = :content,
                    category = :category,
                    priority = :priority,
                    status = :status,
                    published_at = :published_at,
                    scheduled_at = :scheduled_at,
                    expires_at = :expires_at,
                    is_pinned = :is_pinned,
                    updated_at = COALESCE(:updated_at, NOW()),
                    archived_at = :archived_at
                WHERE id = :id
            ");
            $stmt->execute([
                'title'        => $announcement->title()->value(),
                'content'      => $announcement->content()->value(),
                'category'     => $announcement->category()->value(),
                'priority'     => $announcement->priority()->value(),
                'status'       => $announcement->status()->value(),
                'published_at' => $publishedAtStr,
                'scheduled_at' => $scheduledAtStr,
                'expires_at'   => $expiresAtStr,
                'is_pinned'    => $announcement->isPinned() ? 1 : 0,
                'updated_at'   => $announcement->updatedAt()?->format('Y-m-d H:i:s'),
                'archived_at'  => $archivedAtStr,
                'id'           => $id,
            ]);
        }

        $this->syncTargets($id, $announcement->targets());

        return $id;
    }

    /**
     * @param list<AnnouncementTarget> $targets
     */
    private function syncTargets(int $announcementId, array $targets): void
    {
        $del = $this->pdo->prepare('DELETE FROM announcement_targets WHERE announcement_id = :aid');
        $del->execute(['aid' => $announcementId]);

        $ins = $this->pdo->prepare("
            INSERT INTO announcement_targets (announcement_id, target_type, department_id, created_at)
            VALUES (:aid, :ttype, :dept_id, NOW())
        ");

        $hasAll = false;
        foreach ($targets as $t) {
            if ($t->isAllEmployees()) {
                $hasAll = true;
                break;
            }
        }

        if ($hasAll) {
            $ins->execute([
                'aid'     => $announcementId,
                'ttype'   => AnnouncementTarget::TYPE_ALL,
                'dept_id' => null,
            ]);
            return;
        }

        $seenDepts = [];
        foreach ($targets as $t) {
            $deptId = $t->departmentId();
            if ($deptId !== null && $deptId > 0 && !isset($seenDepts[$deptId])) {
                $seenDepts[$deptId] = true;
                $ins->execute([
                    'aid'     => $announcementId,
                    'ttype'   => AnnouncementTarget::TYPE_DEPARTMENT,
                    'dept_id' => $deptId,
                ]);
            }
        }
    }

    public function deleteDraft(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM announcements
            WHERE id = :id AND status = 'Draft'
        ");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param list<int> $departmentIds
     * @return list<int>
     */
    public function validateDepartmentIds(array $departmentIds): array
    {
        $cleanIds = array_values(array_unique(array_filter(
            array_map('intval', $departmentIds),
            static fn (int $v): bool => $v > 0
        )));

        if (empty($cleanIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM departments WHERE id IN ({$placeholders}) ORDER BY id ASC");
        $stmt->execute($cleanIds);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return array{receipt: AnnouncementReadReceipt, newly_created: bool}
     */
    public function recordReadReceipt(int $announcementId, int $employeeId, DateTimeImmutable $readAt): array
    {
        $stmtInsert = $this->pdo->prepare("
            INSERT IGNORE INTO announcement_reads (announcement_id, employee_id, read_at)
            VALUES (:aid, :eid, :read_at)
        ");
        $stmtInsert->execute([
            'aid'     => $announcementId,
            'eid'     => $employeeId,
            'read_at' => $readAt->format('Y-m-d H:i:s'),
        ]);
        $newlyCreated = $stmtInsert->rowCount() > 0;

        $stmtSelect = $this->pdo->prepare("
            SELECT id, announcement_id, employee_id, read_at
            FROM announcement_reads
            WHERE announcement_id = :aid AND employee_id = :eid
            LIMIT 1
        ");
        $stmtSelect->execute([
            'aid' => $announcementId,
            'eid' => $employeeId,
        ]);
        $row = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        $persistedReadAt = !empty($row['read_at'])
            ? new DateTimeImmutable((string)$row['read_at'])
            : $readAt;

        return [
            'receipt' => new AnnouncementReadReceipt(
                id: isset($row['id']) ? (int)$row['id'] : null,
                announcementId: $announcementId,
                employeeId: $employeeId,
                readAt: $persistedReadAt
            ),
            'newly_created' => $newlyCreated,
        ];
    }

    public function hasEmployeeRead(int $announcementId, int $employeeId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM announcement_reads
            WHERE announcement_id = :aid AND employee_id = :eid
            LIMIT 1
        ");
        $stmt->execute([
            'aid' => $announcementId,
            'eid' => $employeeId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return list<int>
     */
    public function findDueScheduledIds(DateTimeImmutable $now, int $limit = 50): array
    {
        $batchLimit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM announcements
            WHERE status = 'Scheduled'
              AND scheduled_at IS NOT NULL
              AND scheduled_at <= :now
            ORDER BY scheduled_at ASC, id ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':now', $now->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':lim', $batchLimit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return list<int>
     */
    public function findDueExpiredIds(DateTimeImmutable $now, int $limit = 50): array
    {
        $batchLimit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM announcements
            WHERE status = 'Published'
              AND expires_at IS NOT NULL
              AND expires_at <= :now
            ORDER BY expires_at ASC, id ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':now', $now->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':lim', $batchLimit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Persist validated attachment metadata rows for an announcement.
     *
     * @param array<int, array<string, mixed>> $storedFiles
     * @return list<array<string, mixed>>
     */
    public function addAttachments(int $announcementId, int $uploadedBy, array $storedFiles): array
    {
        if (empty($storedFiles)) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO announcement_attachments (
                announcement_id, original_filename, stored_filename, storage_relative_path,
                mime_type, file_size, sha256_checksum, uploaded_by, created_at
            ) VALUES (
                :aid, :orig, :stored, :rel, :mime, :fsize, :sha, :uid, NOW()
            )
        ");

        $inserted = [];
        foreach ($storedFiles as $f) {
            $stmt->execute([
                'aid'    => $announcementId,
                'orig'   => (string)$f['original_filename'],
                'stored' => (string)$f['stored_filename'],
                'rel'    => (string)$f['storage_relative_path'],
                'mime'   => (string)$f['mime_type'],
                'fsize'  => (int)$f['file_size_bytes'],
                'sha'    => (string)($f['sha256_checksum'] ?? ''),
                'uid'    => $uploadedBy > 0 ? $uploadedBy : null,
            ]);
            $inserted[] = array_merge($f, ['id' => (int)$this->pdo->lastInsertId()]);
        }

        return $inserted;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAttachmentById(int $attachmentId, ?int $announcementId = null): ?array
    {
        if ($attachmentId <= 0) {
            return null;
        }

        $sql = "
            SELECT id, announcement_id, original_filename, stored_filename, storage_relative_path,
                   mime_type, file_size, sha256_checksum, uploaded_by, created_at
            FROM announcement_attachments
            WHERE id = :id
        ";
        $params = ['id' => $attachmentId];
        if ($announcementId !== null && $announcementId > 0) {
            $sql .= ' AND announcement_id = :aid';
            $params['aid'] = $announcementId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null Deleted attachment row if found
     */
    public function deleteAttachment(int $attachmentId, int $announcementId): ?array
    {
        $existing = $this->findAttachmentById($attachmentId, $announcementId);
        if ($existing === null) {
            return null;
        }

        $stmt = $this->pdo->prepare('DELETE FROM announcement_attachments WHERE id = :id AND announcement_id = :aid');
        $stmt->execute(['id' => $attachmentId, 'aid' => $announcementId]);

        return $existing;
    }

    /**
     * Count existing attachments for an announcement.
     */
    public function countAttachments(int $announcementId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM announcement_attachments WHERE announcement_id = :aid');
        $stmt->execute(['aid' => $announcementId]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Retrieve HR summary KPI counts (scoped so private drafts of other authors are hidden unless $canManageAll).
     *
     * @return array{total: int, draft: int, scheduled: int, published: int, archived: int, expired: int}
     */
    public function getHrSummaryStats(int $viewerUserId, bool $canManageAll = false): array
    {
        $visibilityClause = $canManageAll
            ? '1 = 1'
            : "(status != 'Draft' OR author_id = :viewer_uid)";

        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) AS draft,
                SUM(CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END) AS scheduled,
                SUM(CASE WHEN status = 'Published' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN status = 'Archived' THEN 1 ELSE 0 END) AS archived,
                SUM(CASE WHEN status = 'Expired' THEN 1 ELSE 0 END) AS expired
            FROM announcements
            WHERE {$visibilityClause}
        ");
        if (!$canManageAll) {
            $stmt->bindValue(':viewer_uid', $viewerUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total'     => (int)($row['total'] ?? 0),
            'draft'     => (int)($row['draft'] ?? 0),
            'scheduled' => (int)($row['scheduled'] ?? 0),
            'published' => (int)($row['published'] ?? 0),
            'archived'  => (int)($row['archived'] ?? 0),
            'expired'   => (int)($row['expired'] ?? 0),
        ];
    }

    /**
     * Paginated, filterable, sortable HR announcement list without N+1 queries.
     *
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function paginateForHr(
        int $viewerUserId,
        bool $canManageAll,
        array $filters = [],
        int $page = 1,
        int $perPage = 10
    ): array {
        $page = max(1, $page);
        $maxPerPage = (int)config('announcements.hr_per_page_max', 50);
        $perPage = max(1, min($maxPerPage, $perPage));

        $where = [];
        $params = [];

        if (!$canManageAll) {
            $where[] = "(a.status != 'Draft' OR a.author_id = :viewer_uid)";
            $params['viewer_uid'] = $viewerUserId;
        }

        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(a.title LIKE :q_title OR a.content LIKE :q_content OR a.category LIKE :q_cat)';
            $like = '%' . $search . '%';
            $params['q_title'] = $like;
            $params['q_content'] = $like;
            $params['q_cat'] = $like;
        }

        $category = trim((string)($filters['category'] ?? ''));
        if ($category !== '' && in_array($category, AnnouncementCategory::VALID_CATEGORIES, true)) {
            $where[] = 'a.category = :category';
            $params['category'] = $category;
        }

        $priority = trim((string)($filters['priority'] ?? ''));
        if ($priority !== '' && in_array($priority, AnnouncementPriority::VALID_PRIORITIES, true)) {
            $where[] = 'a.priority = :priority';
            $params['priority'] = $priority;
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, AnnouncementStatus::VALID_STATUSES, true)) {
            $where[] = 'a.status = :status';
            $params['status'] = $status;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $where[] = 'DATE(COALESCE(a.published_at, a.scheduled_at, a.created_at)) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $where[] = 'DATE(COALESCE(a.published_at, a.scheduled_at, a.created_at)) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM announcements a {$whereSql}");
        foreach ($params as $k => $v) {
            $countStmt->bindValue(':' . $k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $sort = strtolower(trim((string)($filters['sort'] ?? 'newest')));
        $orderBySql = match ($sort) {
            'oldest'       => 'a.created_at ASC, a.id ASC',
            'priority'     => "FIELD(a.priority, 'Urgent', 'Important', 'Normal') ASC, a.is_pinned DESC, a.created_at DESC",
            'published_at' => 'COALESCE(a.published_at, a.scheduled_at, a.created_at) DESC, a.id DESC',
            'title'        => 'a.title ASC, a.id DESC',
            default        => 'a.is_pinned DESC, a.created_at DESC, a.id DESC',
        };

        $sql = "
            SELECT a.*,
                   u.username AS author_username,
                   TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS author_full_name,
                   (SELECT COUNT(*) FROM announcement_attachments aa WHERE aa.announcement_id = a.id) AS attachment_count,
                   (SELECT COUNT(*) FROM announcement_reads ar WHERE ar.announcement_id = a.id) AS read_count
            FROM announcements a
            LEFT JOIN users u ON u.id = a.author_id
            LEFT JOIN employees e ON e.id = u.employee_id
            {$whereSql}
            ORDER BY {$orderBySql}
            LIMIT :lim OFFSET :off
        ";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $enriched = $this->enrichAnnouncementsWithAudienceAndStats($rows);

        return [
            'items'       => $enriched,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Fetch a single full announcement projection for HR view/edit/preview.
     *
     * @return array<string, mixed>|null
     */
    public function findDetailForHr(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT a.*,
                   u.username AS author_username,
                   TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS author_full_name,
                   (SELECT COUNT(*) FROM announcement_attachments aa WHERE aa.announcement_id = a.id) AS attachment_count,
                   (SELECT COUNT(*) FROM announcement_reads ar WHERE ar.announcement_id = a.id) AS read_count
            FROM announcements a
            LEFT JOIN users u ON u.id = a.author_id
            LEFT JOIN employees e ON e.id = u.employee_id
            WHERE a.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $enrichedList = $this->enrichAnnouncementsWithAudienceAndStats([$row]);
        $item = $enrichedList[0];
        $item['attachments'] = $this->findAttachmentsArrayForAnnouncement($id);

        return $item;
    }

    /**
     * Calculate exact read statistics for an announcement (Phase 6.2).
     * Read percentage = (Employees who read / Total targeted employees) * 100.
     * Safely handles 0-target edge cases.
     *
     * @return array{
     *   announcement_id: int,
     *   total_targeted: int,
     *   total_read: int,
     *   total_unread: int,
     *   read_percentage: float,
     *   audience_label: string,
     *   targets_all: bool,
     *   department_names: list<string>
     * }
     */
    public function getReadStatistics(int $announcementId): array
    {
        $targets = $this->loadTargetsForAnnouncement($announcementId);
        $targetsAll = false;
        $deptIds = [];
        $deptNames = [];

        foreach ($targets as $t) {
            if ($t->isAllEmployees()) {
                $targetsAll = true;
            } elseif ($t->departmentId() !== null) {
                $deptIds[] = $t->departmentId();
                if ($t->departmentName() !== null && $t->departmentName() !== '') {
                    $deptNames[] = $t->departmentName();
                }
            }
        }

        if ($targetsAll || empty($targets)) {
            $targetsAll = true;
            $stmtTarget = $this->pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'Active'");
            $totalTargeted = (int)($stmtTarget ? $stmtTarget->fetchColumn() : 0);

            $stmtRead = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM announcement_reads ar
                INNER JOIN employees e ON e.id = ar.employee_id
                WHERE ar.announcement_id = :aid
                  AND e.employment_status = 'Active'
            ");
            $stmtRead->execute(['aid' => $announcementId]);
            $totalRead = (int)$stmtRead->fetchColumn();
            $audienceLabel = 'All Active Employees';
        } else {
            $deptIds = array_values(array_unique($deptIds));
            $placeholders = implode(',', array_fill(0, count($deptIds), '?'));

            $stmtTarget = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM employees
                WHERE employment_status = 'Active'
                  AND department_id IN ({$placeholders})
            ");
            $stmtTarget->execute($deptIds);
            $totalTargeted = (int)$stmtTarget->fetchColumn();

            $stmtRead = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM announcement_reads ar
                INNER JOIN employees e ON e.id = ar.employee_id
                WHERE ar.announcement_id = ?
                  AND e.employment_status = 'Active'
                  AND e.department_id IN ({$placeholders})
            ");
            $stmtRead->execute(array_merge([$announcementId], $deptIds));
            $totalRead = (int)$stmtRead->fetchColumn();
            $audienceLabel = !empty($deptNames) ? implode(', ', $deptNames) : 'Selected Departments';
        }

        $totalTargeted = max($totalTargeted, $totalRead);
        $totalUnread = max(0, $totalTargeted - $totalRead);
        $readPercentage = $totalTargeted > 0
            ? round(($totalRead / $totalTargeted) * 100, 1)
            : 0.0;

        return [
            'announcement_id'  => $announcementId,
            'total_targeted'   => $totalTargeted,
            'total_read'       => $totalRead,
            'total_unread'     => $totalUnread,
            'read_percentage'  => $readPercentage,
            'audience_label'   => $audienceLabel,
            'targets_all'      => $targetsAll,
            'department_names' => $deptNames,
        ];
    }

    /**
     * Paginate targeted employees and their read status / read timestamp for an announcement (Phase 6.2).
     * Only shows employees included in the announcement audience.
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function paginateReadReceipts(
        int $announcementId,
        int $page = 1,
        int $perPage = 25,
        string $stateFilter = 'all'
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $targets = $this->loadTargetsForAnnouncement($announcementId);

        $targetsAll = false;
        $deptIds = [];
        foreach ($targets as $t) {
            if ($t->isAllEmployees()) {
                $targetsAll = true;
                break;
            }
            if ($t->departmentId() !== null) {
                $deptIds[] = $t->departmentId();
            }
        }

        $where = ["e.employment_status = 'Active'"];
        $params = [$announcementId];

        if (!$targetsAll && !empty($deptIds)) {
            $deptIds = array_values(array_unique($deptIds));
            $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
            $where[] = "e.department_id IN ({$placeholders})";
            $params = array_merge($params, $deptIds);
        }

        if ($stateFilter === 'read') {
            $where[] = 'ar.id IS NOT NULL';
        } elseif ($stateFilter === 'unread') {
            $where[] = 'ar.id IS NULL';
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "
            SELECT COUNT(*)
            FROM employees e
            LEFT JOIN announcement_reads ar
                   ON ar.employee_id = e.id AND ar.announcement_id = ?
            WHERE {$whereSql}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT e.id AS employee_id,
                   e.employee_no,
                   TRIM(CONCAT(e.first_name, ' ', e.last_name)) AS full_name,
                   COALESCE(d.department_name, 'Unassigned') AS department_name,
                   COALESCE(p.position_name, 'Staff') AS position_name,
                   CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END AS is_read,
                   ar.read_at
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN positions p ON p.id = e.position_id
            LEFT JOIN announcement_reads ar
                   ON ar.employee_id = e.id AND ar.announcement_id = ?
            WHERE {$whereSql}
            ORDER BY (ar.read_at IS NULL) ASC, ar.read_at DESC, e.last_name ASC, e.first_name ASC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Paginated Employee Announcement Feed (Phase 5.1).
     * Strictly returns Published, non-expired announcements addressed to the employee.
     *
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function paginateForEmployee(
        int $employeeId,
        ?int $departmentId,
        string $employmentStatus = 'Active',
        array $filters = [],
        int $page = 1,
        int $perPage = 10
    ): array {
        if ($employeeId <= 0 || strcasecmp(trim($employmentStatus), 'Active') !== 0) {
            return [
                'items'       => [],
                'total'       => 0,
                'page'        => 1,
                'per_page'    => $perPage,
                'total_pages' => 1,
            ];
        }

        $page = max(1, $page);
        $maxPerPage = (int)config('announcements.employee_per_page_max', 50);
        $perPage = max(1, min($maxPerPage, $perPage));

        $nowStr = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $where = [
            "a.status = 'Published'",
            'a.published_at IS NOT NULL',
            'a.published_at <= :now_pub',
            '(a.expires_at IS NULL OR a.expires_at > :now_exp)',
            $this->buildEmployeeAudienceExistsSql($departmentId),
        ];
        $params = [
            'emp_id'  => $employeeId,
            'now_pub' => $nowStr,
            'now_exp' => $nowStr,
        ];
        if ($departmentId !== null && $departmentId > 0) {
            $params['dept_id'] = $departmentId;
        }

        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(a.title LIKE :q_title OR a.content LIKE :q_content OR a.category LIKE :q_cat)';
            $like = '%' . $search . '%';
            $params['q_title'] = $like;
            $params['q_content'] = $like;
            $params['q_cat'] = $like;
        }

        $category = trim((string)($filters['category'] ?? ''));
        if ($category !== '' && in_array($category, AnnouncementCategory::VALID_CATEGORIES, true)) {
            $where[] = 'a.category = :category';
            $params['category'] = $category;
        }

        $priority = trim((string)($filters['priority'] ?? ''));
        if ($priority !== '' && in_array($priority, AnnouncementPriority::VALID_PRIORITIES, true)) {
            $where[] = 'a.priority = :priority';
            $params['priority'] = $priority;
        }

        if (!empty($filters['unread_only'])) {
            $where[] = 'ar.id IS NULL';
        }

        if (!empty($filters['pinned_only'])) {
            $where[] = 'a.is_pinned = 1';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countSql = "
            SELECT COUNT(*)
            FROM announcements a
            LEFT JOIN announcement_reads ar
                   ON ar.announcement_id = a.id AND ar.employee_id = :emp_id
            {$whereSql}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue(':' . $k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT a.id, a.title, a.content, a.category, a.priority, a.status,
                   a.author_id, a.published_at, a.expires_at, a.is_pinned, a.created_at,
                   u.username AS author_username,
                   TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS author_full_name,
                   CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END AS is_read,
                   ar.read_at,
                   (SELECT COUNT(*) FROM announcement_attachments aa WHERE aa.announcement_id = a.id) AS attachment_count
            FROM announcements a
            LEFT JOIN users u ON u.id = a.author_id
            LEFT JOIN employees e ON e.id = u.employee_id
            LEFT JOIN announcement_reads ar
                   ON ar.announcement_id = a.id AND ar.employee_id = :emp_id
            {$whereSql}
            ORDER BY a.is_pinned DESC,
                     FIELD(a.priority, 'Urgent', 'Important', 'Normal') ASC,
                     a.published_at DESC,
                     a.id DESC
            LIMIT :lim OFFSET :off
        ";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = $this->enrichEmployeeFeedItems($rows);

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Employee Dashboard Preview (Phase 5.2):
     * Maximum 3 recent targeted announcements prioritizing pinned and Urgent/Important.
     * Does NOT record read receipts.
     *
     * @return list<array<string, mixed>>
     */
    public function getDashboardPreviewForEmployee(
        int $employeeId,
        ?int $departmentId,
        string $employmentStatus = 'Active',
        int $limit = 3
    ): array {
        $maxLimit = max(1, min(3, $limit));
        $res = $this->paginateForEmployee($employeeId, $departmentId, $employmentStatus, [], 1, $maxLimit);

        return array_slice($res['items'], 0, $maxLimit);
    }

    /**
     * Count unread targeted published non-expired announcements for an employee.
     */
    public function countUnreadForEmployee(
        int $employeeId,
        ?int $departmentId,
        string $employmentStatus = 'Active'
    ): int {
        if ($employeeId <= 0 || strcasecmp(trim($employmentStatus), 'Active') !== 0) {
            return 0;
        }

        $nowStr = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $audienceClause = $this->buildEmployeeAudienceExistsSql($departmentId);
        $sql = "
            SELECT COUNT(*)
            FROM announcements a
            LEFT JOIN announcement_reads ar
                   ON ar.announcement_id = a.id AND ar.employee_id = :emp_id
            WHERE a.status = 'Published'
              AND a.published_at IS NOT NULL
              AND a.published_at <= :now_pub
              AND (a.expires_at IS NULL OR a.expires_at > :now_exp)
              AND ar.id IS NULL
              AND {$audienceClause}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':emp_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':now_pub', $nowStr, PDO::PARAM_STR);
        $stmt->bindValue(':now_exp', $nowStr, PDO::PARAM_STR);
        if ($departmentId !== null && $departmentId > 0) {
            $stmt->bindValue(':dept_id', $departmentId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    /**
     * Fetch a single targeted announcement detail for an authorized employee.
     * Returns null if draft, scheduled, archived, expired, or outside the employee's audience.
     *
     * @return array<string, mixed>|null
     */
    public function findAccessibleDetailForEmployee(
        int $announcementId,
        int $employeeId,
        ?int $departmentId,
        string $employmentStatus = 'Active'
    ): ?array {
        if ($announcementId <= 0 || $employeeId <= 0 || strcasecmp(trim($employmentStatus), 'Active') !== 0) {
            return null;
        }

        $nowStr = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $audienceClause = $this->buildEmployeeAudienceExistsSql($departmentId);
        $sql = "
            SELECT a.id, a.title, a.content, a.category, a.priority, a.status,
                   a.author_id, a.published_at, a.expires_at, a.is_pinned, a.created_at,
                   u.username AS author_username,
                   TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS author_full_name,
                   CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END AS is_read,
                   ar.read_at,
                   (SELECT COUNT(*) FROM announcement_attachments aa WHERE aa.announcement_id = a.id) AS attachment_count
            FROM announcements a
            LEFT JOIN users u ON u.id = a.author_id
            LEFT JOIN employees e ON e.id = u.employee_id
            LEFT JOIN announcement_reads ar
                   ON ar.announcement_id = a.id AND ar.employee_id = :emp_id
            WHERE a.id = :aid
              AND a.status = 'Published'
              AND a.published_at IS NOT NULL
              AND a.published_at <= :now_pub
              AND (a.expires_at IS NULL OR a.expires_at > :now_exp)
              AND {$audienceClause}
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':aid', $announcementId, PDO::PARAM_INT);
        $stmt->bindValue(':emp_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':now_pub', $nowStr, PDO::PARAM_STR);
        $stmt->bindValue(':now_exp', $nowStr, PDO::PARAM_STR);
        if ($departmentId !== null && $departmentId > 0) {
            $stmt->bindValue(':dept_id', $departmentId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $items = $this->enrichEmployeeFeedItems([$row]);
        $item = $items[0];
        $item['attachments'] = $this->findAttachmentsArrayForAnnouncement($announcementId);

        return $item;
    }

    /**
     * Batch-fetch active portal user IDs of employees targeted by an announcement.
     * Used by DistributeAnnouncementNotificationsHandler for asynchronous batched notifications.
     *
     * @return list<int>
     */
    public function findTargetedUserIdsBatch(int $announcementId, int $offset = 0, int $limit = 100): array
    {
        $batchLimit = max(1, min(500, $limit));
        $batchOffset = max(0, $offset);

        $targets = $this->loadTargetsForAnnouncement($announcementId);
        $targetsAll = false;
        $deptIds = [];
        foreach ($targets as $t) {
            if ($t->isAllEmployees()) {
                $targetsAll = true;
                break;
            }
            if ($t->departmentId() !== null) {
                $deptIds[] = $t->departmentId();
            }
        }

        if ($targetsAll || empty($targets)) {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT u.id
                FROM users u
                INNER JOIN employees e ON e.id = u.employee_id
                WHERE u.status = 'Active'
                  AND e.employment_status = 'Active'
                ORDER BY u.id ASC
                LIMIT :lim OFFSET :off
            ");
            $stmt->bindValue(':lim', $batchLimit, PDO::PARAM_INT);
            $stmt->bindValue(':off', $batchOffset, PDO::PARAM_INT);
            $stmt->execute();

            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $deptIds = array_values(array_unique($deptIds));
        if (empty($deptIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
        $sql = "
            SELECT DISTINCT u.id
            FROM users u
            INNER JOIN employees e ON e.id = u.employee_id
            WHERE u.status = 'Active'
              AND e.employment_status = 'Active'
              AND e.department_id IN ({$placeholders})
            ORDER BY u.id ASC
            LIMIT {$batchLimit} OFFSET {$batchOffset}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($deptIds);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function buildEmployeeAudienceExistsSql(?int $departmentId): string
    {
        if ($departmentId !== null && $departmentId > 0) {
            return "EXISTS (
                SELECT 1 FROM announcement_targets at
                WHERE at.announcement_id = a.id
                  AND (at.target_type = 'all' OR (at.target_type = 'department' AND at.department_id = :dept_id))
            )";
        }

        return "EXISTS (
            SELECT 1 FROM announcement_targets at
            WHERE at.announcement_id = a.id
              AND at.target_type = 'all'
        )";
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function enrichAnnouncementsWithAudienceAndStats(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $ids = array_map(static fn (array $r): int => (int)$r['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmtTargets = $this->pdo->prepare("
            SELECT at.announcement_id, at.target_type, at.department_id, d.department_name
            FROM announcement_targets at
            LEFT JOIN departments d ON d.id = at.department_id
            WHERE at.announcement_id IN ({$placeholders})
            ORDER BY at.id ASC
        ");
        $stmtTargets->execute($ids);
        $targetRows = $stmtTargets->fetchAll(PDO::FETCH_ASSOC);

        $targetsByAnn = [];
        foreach ($targetRows as $tr) {
            $aid = (int)$tr['announcement_id'];
            $targetsByAnn[$aid][] = $tr;
        }

        // Precompute active employee count per department + total active employees in 1 query
        $deptCounts = [];
        $totalActiveEmployees = 0;
        $stmtEmpCounts = $this->pdo->query("
            SELECT department_id, COUNT(*) AS cnt
            FROM employees
            WHERE employment_status = 'Active'
            GROUP BY department_id
        ");
        if ($stmtEmpCounts) {
            foreach ($stmtEmpCounts->fetchAll(PDO::FETCH_ASSOC) as $er) {
                $cnt = (int)$er['cnt'];
                $totalActiveEmployees += $cnt;
                if ($er['department_id'] !== null) {
                    $deptCounts[(int)$er['department_id']] = $cnt;
                }
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $aid = (int)$row['id'];
            $tList = $targetsByAnn[$aid] ?? [];
            $isAll = false;
            $deptIds = [];
            $deptNames = [];

            foreach ($tList as $t) {
                if (($t['target_type'] ?? '') === 'all') {
                    $isAll = true;
                } elseif (!empty($t['department_id'])) {
                    $did = (int)$t['department_id'];
                    $deptIds[] = $did;
                    if (!empty($t['department_name'])) {
                        $deptNames[] = (string)$t['department_name'];
                    }
                }
            }

            if ($isAll || empty($tList)) {
                $isAll = true;
                $targetedCount = $totalActiveEmployees;
                $audienceLabel = 'All Active Employees';
            } else {
                $deptIds = array_values(array_unique($deptIds));
                $targetedCount = 0;
                foreach ($deptIds as $did) {
                    $targetedCount += ($deptCounts[$did] ?? 0);
                }
                $audienceLabel = !empty($deptNames) ? implode(', ', $deptNames) : 'Selected Departments';
            }

            $readCount = (int)($row['read_count'] ?? 0);
            $targetedCount = max($targetedCount, $readCount);
            $unreadCount = max(0, $targetedCount - $readCount);
            $readPct = $targetedCount > 0 ? round(($readCount / $targetedCount) * 100, 1) : 0.0;

            $authorName = trim((string)($row['author_full_name'] ?? ''));
            if ($authorName === '') {
                $authorName = (string)($row['author_username'] ?? 'HR Officer');
            }

            $row['author_display_name'] = $authorName;
            $row['target_type'] = $isAll ? 'all' : 'department';
            $row['target_department_ids'] = $deptIds;
            $row['target_department_names'] = $deptNames;
            $row['audience_label'] = $audienceLabel;
            $row['targeted_count'] = $targetedCount;
            $row['read_count'] = $readCount;
            $row['unread_count'] = $unreadCount;
            $row['read_percentage'] = $readPct;
            $row['content_preview'] = AnnouncementContent::fromString((string)$row['content'])->preview(160);

            $result[] = $row;
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function enrichEmployeeFeedItems(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $ids = array_map(static fn (array $r): int => (int)$r['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmtTargets = $this->pdo->prepare("
            SELECT at.announcement_id, at.target_type, d.department_name
            FROM announcement_targets at
            LEFT JOIN departments d ON d.id = at.department_id
            WHERE at.announcement_id IN ({$placeholders})
            ORDER BY at.id ASC
        ");
        $stmtTargets->execute($ids);
        $targetRows = $stmtTargets->fetchAll(PDO::FETCH_ASSOC);

        $targetsByAnn = [];
        foreach ($targetRows as $tr) {
            $targetsByAnn[(int)$tr['announcement_id']][] = $tr;
        }

        $result = [];
        foreach ($rows as $row) {
            $aid = (int)$row['id'];
            $tList = $targetsByAnn[$aid] ?? [];
            $isAll = false;
            $deptNames = [];
            foreach ($tList as $t) {
                if (($t['target_type'] ?? '') === 'all') {
                    $isAll = true;
                } elseif (!empty($t['department_name'])) {
                    $deptNames[] = (string)$t['department_name'];
                }
            }

            $authorName = trim((string)($row['author_full_name'] ?? ''));
            if ($authorName === '') {
                $authorName = (string)($row['author_username'] ?? 'Human Resources');
            }

            $row['author_display_name'] = $authorName;
            $row['audience_label'] = ($isAll || empty($deptNames))
                ? 'All Active Employees'
                : implode(', ', $deptNames);
            $row['is_read'] = !empty($row['is_read']);
            $row['is_pinned'] = !empty($row['is_pinned']);
            $row['attachment_count'] = (int)($row['attachment_count'] ?? 0);
            $row['content_preview'] = AnnouncementContent::fromString((string)$row['content'])->preview(180);

            $result[] = $row;
        }

        return $result;
    }

    /**
     * @return list<AnnouncementTarget>
     */
    private function loadTargetsForAnnouncement(int $announcementId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT at.target_type, at.department_id, d.department_name
            FROM announcement_targets at
            LEFT JOIN departments d ON d.id = at.department_id
            WHERE at.announcement_id = :aid
            ORDER BY at.id ASC
        ");
        $stmt->execute(['aid' => $announcementId]);

        $targets = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (($r['target_type'] ?? '') === AnnouncementTarget::TYPE_ALL) {
                $targets[] = AnnouncementTarget::allEmployees();
            } elseif (!empty($r['department_id'])) {
                $targets[] = AnnouncementTarget::forDepartment(
                    (int)$r['department_id'],
                    isset($r['department_name']) ? (string)$r['department_name'] : null
                );
            }
        }

        return $targets;
    }

    /**
     * @return list<AnnouncementAttachment>
     */
    private function loadAttachmentsForAnnouncement(int $announcementId): array
    {
        $rows = $this->findAttachmentsArrayForAnnouncement($announcementId);
        $list = [];
        foreach ($rows as $r) {
            $list[] = new AnnouncementAttachment(
                id: (int)$r['id'],
                announcementId: (int)$r['announcement_id'],
                originalFilename: (string)$r['original_filename'],
                storedFilename: (string)$r['stored_filename'],
                storageRelativePath: (string)$r['storage_relative_path'],
                mimeType: (string)$r['mime_type'],
                fileSize: (int)$r['file_size'],
                sha256Checksum: (string)($r['sha256_checksum'] ?? ''),
                uploadedBy: isset($r['uploaded_by']) ? (int)$r['uploaded_by'] : null,
                createdAt: !empty($r['created_at']) ? new DateTimeImmutable((string)$r['created_at']) : null
            );
        }

        return $list;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAttachmentsArrayForAnnouncement(int $announcementId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, announcement_id, original_filename, stored_filename, storage_relative_path,
                   mime_type, file_size, sha256_checksum, uploaded_by, created_at
            FROM announcement_attachments
            WHERE announcement_id = :aid
            ORDER BY id ASC
        ");
        $stmt->execute(['aid' => $announcementId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<AnnouncementTarget> $targets
     * @param list<AnnouncementAttachment> $attachments
     */
    private function hydrateEntity(array $row, array $targets, array $attachments): Announcement
    {
        $scheduledAt = !empty($row['scheduled_at'])
            ? PublicationDate::fromString((string)$row['scheduled_at'])
            : null;
        $publishedAt = !empty($row['published_at'])
            ? PublicationDate::fromString((string)$row['published_at'])
            : null;
        $expiresAt = !empty($row['expires_at'])
            ? ExpirationDate::fromString((string)$row['expires_at'])
            : null;

        // When reconstituting historical rows (where expires_at may be close to or past published_at),
        // construct schedule safely.
        $schedule = new AnnouncementPublicationSchedule(
            scheduledAt: $scheduledAt,
            publishedAt: $publishedAt,
            expiresAt: $expiresAt
        );

        return Announcement::reconstitute(
            id: (int)$row['id'],
            title: AnnouncementTitle::fromString((string)$row['title']),
            content: AnnouncementContent::fromString((string)$row['content']),
            category: AnnouncementCategory::fromString((string)$row['category']),
            priority: AnnouncementPriority::fromString((string)$row['priority']),
            status: AnnouncementStatus::fromString((string)$row['status']),
            authorId: (int)$row['author_id'],
            schedule: $schedule,
            isPinned: !empty($row['is_pinned']),
            targets: $targets,
            attachments: $attachments,
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null,
            updatedAt: !empty($row['updated_at']) ? new DateTimeImmutable((string)$row['updated_at']) : null,
            archivedAt: !empty($row['archived_at']) ? new DateTimeImmutable((string)$row['archived_at']) : null
        );
    }
}
