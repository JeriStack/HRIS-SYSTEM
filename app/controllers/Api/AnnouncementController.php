<?php

/**
 * REST API v1 — HR Announcements Management Controller
 * Employee HRIS & Leave Management System (NEXORA HR)
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Application\Announcement\Adapters\AnnouncementControllerAdapter;
use App\Infrastructure\Persistence\Announcement\MySqlAnnouncementRepository;
use App\Queue\QueueWorker;
use Core\Database;
use Core\Permission;
use OpenApi\Attributes as OA;
use PDO;
use Throwable;

class AnnouncementController extends ApiController
{
    private PDO $pdo;
    private AnnouncementControllerAdapter $adapter;
    private MySqlAnnouncementRepository $repository;

    public function __construct(
        ?PDO $pdo = null,
        ?AnnouncementControllerAdapter $adapter = null
    ) {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
        $this->adapter = $adapter ?? new AnnouncementControllerAdapter($this->pdo);
        $this->repository = $this->adapter->getRepository();
    }

    #[OA\Get(
        path: "/api/v1/announcements",
        summary: "List HR announcements with filtering, sorting, and pagination",
        description: "Returns a paginated list of announcements with summary KPI counts and read statistics. Requires HR role or explicit `announcements.view` permission.",
        tags: ["Announcements Management"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "category", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "priority", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["Normal", "Important", "Urgent"])),
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["Draft", "Scheduled", "Published", "Archived", "Expired"])),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1)),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 10))
        ],
        responses: [
            new OA\Response(response: 200, description: "Paginated announcements list"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden")
        ]
    )]
    public function index(): void
    {
        $user = $this->requireStaffPermission('announcements.view');
        $userId = (int)$user['id'];
        $canManageAll = $this->hasPerm($user, 'announcements.manage_all');

        $filters = [
            'search'    => trim((string)($_GET['search'] ?? $_GET['q'] ?? '')),
            'category'  => trim((string)($_GET['category'] ?? '')),
            'priority'  => trim((string)($_GET['priority'] ?? '')),
            'status'    => trim((string)($_GET['status'] ?? '')),
            'date_from' => trim((string)($_GET['date_from'] ?? '')),
            'date_to'   => trim((string)($_GET['date_to'] ?? '')),
            'sort'      => trim((string)($_GET['sort'] ?? 'newest')),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(50, (int)($_GET['per_page'] ?? config('announcements.hr_per_page_default', 10))));

        $stats = $this->repository->getHrSummaryStats($userId, $canManageAll);
        $paginated = $this->repository->paginateForHr($userId, $canManageAll, $filters, $page, $perPage);

        $this->success([
            'stats'         => $stats,
            'announcements' => $paginated['items'],
            'pagination'    => [
                'total'       => $paginated['total'],
                'page'        => $paginated['page'],
                'per_page'    => $paginated['per_page'],
                'total_pages' => $paginated['total_pages'],
            ],
        ], 'Announcements retrieved successfully.');
    }

    #[OA\Post(
        path: "/api/v1/announcements",
        summary: "Create a new announcement (Draft, Scheduled, or Published)",
        description: "Creates a new announcement with validated audience targeting and optional attachments.",
        tags: ["Announcements Management"],
        security: [["cookieAuth" => [], "csrfToken" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["title", "content", "category", "priority", "target_type"],
                properties: [
                    new OA\Property(property: "title", type: "string", example: "2026 Annual Benefits Enrollment"),
                    new OA\Property(property: "content", type: "string", example: "Open enrollment starts next Monday for all active staff."),
                    new OA\Property(property: "category", type: "string", example: "Leave and Benefits"),
                    new OA\Property(property: "priority", type: "string", enum: ["Normal", "Important", "Urgent"], example: "Important"),
                    new OA\Property(property: "target_type", type: "string", enum: ["all", "department"], example: "all"),
                    new OA\Property(property: "department_ids", type: "array", items: new OA\Items(type: "integer")),
                    new OA\Property(property: "publish_mode", type: "string", enum: ["draft", "publish", "schedule"], example: "draft"),
                    new OA\Property(property: "scheduled_at", type: "string", nullable: true, example: "2026-12-01 08:00:00"),
                    new OA\Property(property: "expires_at", type: "string", nullable: true, example: "2026-12-31 18:00:00"),
                    new OA\Property(property: "is_pinned", type: "boolean", example: false)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Announcement created"),
            new OA\Response(response: 400, description: "Validation error"),
            new OA\Response(response: 403, description: "Forbidden")
        ]
    )]
    public function store(): void
    {
        $user = $this->requireStaffPermission('announcements.create');
        $input = $this->getJsonInput();

        $publishMode = strtolower(trim((string)($input['publish_mode'] ?? $input['action'] ?? 'draft')));
        if (($publishMode === 'publish' || $publishMode === 'publish_now') && !$this->hasPerm($user, 'announcements.publish')) {
            $this->error('Forbidden: You do not have permission to publish announcements.', 403);
        }
        if ($publishMode === 'schedule' && !$this->hasPerm($user, 'announcements.schedule')) {
            $this->error('Forbidden: You do not have permission to schedule announcements.', 403);
        }

        $res = $this->adapter->create(
            authorUserId: (int)$user['id'],
            actorRole: (string)($user['role'] ?? 'hr'),
            input: $input,
            filesInput: $_FILES['attachments'] ?? null
        );

        if (!$res->success) {
            $this->error($res->message, $res->statusCode);
        }

        if (($res->data['status'] ?? '') === 'Published') {
            $this->drainInitialQueueBatchSilently();
        }

        $this->success($res->data, $res->message, 201);
    }

    #[OA\Get(
        path: "/api/v1/announcements/{id}",
        summary: "Retrieve announcement details",
        tags: ["Announcements Management"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Announcement details"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not found")
        ]
    )]
    public function show(int|string $id): void
    {
        $user = $this->requireStaffPermission('announcements.view');
        $aid = (int)$id;
        $userId = (int)$user['id'];
        $canManageAll = $this->hasPerm($user, 'announcements.manage_all');

        $detail = $this->repository->findDetailForHr($aid);
        if ($detail === null) {
            $this->error('Announcement not found.', 404);
        }

        $isOwner = ((int)($detail['author_id'] ?? 0) === $userId);
        if (($detail['status'] ?? '') === 'Draft' && !$isOwner && !$canManageAll) {
            $this->error('Forbidden: You cannot view another HR officer\'s private draft.', 403);
        }

        $this->success($detail, 'Announcement retrieved.');
    }

    #[OA\Put(
        path: "/api/v1/announcements/{id}",
        summary: "Update an announcement",
        tags: ["Announcements Management"],
        security: [["cookieAuth" => [], "csrfToken" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Announcement updated"),
            new OA\Response(response: 400, description: "Validation error"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not found")
        ]
    )]
    public function update(int|string $id): void
    {
        $user = $this->requireStaffPermission('announcements.edit_own');
        $aid = (int)$id;
        $input = $this->getJsonInput();
        $canManageAll = $this->hasPerm($user, 'announcements.manage_all');

        $res = $this->adapter->update(
            announcementId: $aid,
            actorUserId: (int)$user['id'],
            actorRole: (string)($user['role'] ?? 'hr'),
            canManageAll: $canManageAll,
            input: $input,
            filesInput: $_FILES['attachments'] ?? null
        );

        if (!$res->success) {
            $this->error($res->message, $res->statusCode);
        }

        if (($res->data['status'] ?? '') === 'Published') {
            $this->drainInitialQueueBatchSilently();
        }

        $this->success($res->data, $res->message, 200);
    }

    #[OA\Post(
        path: "/api/v1/announcements/{id}/publish",
        summary: "Publish an announcement immediately",
        tags: ["Announcements Management"],
        security: [["cookieAuth" => [], "csrfToken" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Announcement published"),
            new OA\Response(response: 400, description: "Invalid lifecycle transition"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not found")
        ]
    )]
    public function publish(int|string $id): void
    {
        $user = $this->requireStaffPermission('announcements.publish');
        $aid = (int)$id;
        $canManageAll = $this->hasPerm($user, 'announcements.manage_all');

        $res = $this->adapter->publish($aid, (int)$user['id'], $canManageAll);
        if (!$res->success) {
            $this->error($res->message, $res->statusCode);
        }

        $this->drainInitialQueueBatchSilently();
        $this->success($res->data, $res->message, 200);
    }

    #[OA\Post(
        path: "/api/v1/announcements/{id}/schedule",
        summary: "Schedule an announcement for future publication",
        tags: ["Announcements Management"],
        security: [["cookieAuth" => [], "csrfToken" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Announcement scheduled"),
            new OA\Response(response: 400, description: "Validation or transition error"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not found")
        ]
    )]
    public function schedule(int|string $id): void
    {
        $user = $this->requireStaffPermission('announcements.schedule');
        $aid = (int)$id;
        $input = $this->getJsonInput();
        $canManageAll = $this->hasPerm($user, 'announcements.manage_all');

        $res = $this->adapter->schedule(
            announcementId: $aid,
            actorUserId: (int)$user['id'],
            scheduledAtRaw: (string)($input['scheduled_at'] ?? ''),
            expiresAtRaw: isset($input['expires_at']) ? (string)$input['expires_at'] : null,
            canManageAll: $canManageAll
        );

        if (!$res->success) {
            $this->error($res->message, $res->statusCode);
        }

        $this->success($res->data, $res->message, 200);
    }

    #[OA\Post(
        path: "/api/v1/announcements/{id}/archive",
        summary: "Archive an announcement",
        tags: ["Announcements Management"],
        security: [["cookieAuth" => [], "csrfToken" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Announcement archived"),
            new OA\Response(response: 400, description: "Invalid transition"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not found")
        ]
    )]
    public function archive(int|string $id): void
    {
        $user = $this->requireStaffPermission('announcements.archive');
        $aid = (int)$id;
        $canManageAll = $this->hasPerm($user, 'announcements.manage_all');

        $res = $this->adapter->archive($aid, (int)$user['id'], $canManageAll);
        if (!$res->success) {
            $this->error($res->message, $res->statusCode);
        }

        $this->success($res->data, $res->message, 200);
    }

    #[OA\Get(
        path: "/api/v1/announcements/{id}/statistics",
        summary: "Retrieve announcement read statistics",
        tags: ["Announcements Management"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Announcement read statistics"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not found")
        ]
    )]
    public function statistics(int|string $id): void
    {
        $user = $this->requireStaffPermission('announcements.stats_own');
        $aid = (int)$id;
        $userId = (int)$user['id'];
        $canViewAll = $this->hasPerm($user, 'announcements.stats_all') || $this->hasPerm($user, 'announcements.manage_all');

        $ann = $this->repository->findById($aid);
        if ($ann === null) {
            $this->error('Announcement not found.', 404);
        }

        if (!$this->adapter->canViewAnnouncementStats($ann, $userId, $canViewAll)) {
            $this->error('Forbidden: You are not authorized to view read statistics for another HR officer\'s announcement.', 403);
        }

        $stats = $this->repository->getReadStatistics($aid);
        $this->success($stats, 'Announcement statistics retrieved.');
    }

    #[OA\Get(
        path: "/api/v1/announcements/{id}/reads",
        summary: "Retrieve paginated employee read receipts for an announcement",
        tags: ["Announcements Management"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "filter", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["all", "read", "unread"])),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: "Paginated read receipts"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not found")
        ]
    )]
    public function reads(int|string $id): void
    {
        $user = $this->requireStaffPermission('announcements.stats_own');
        $aid = (int)$id;
        $userId = (int)$user['id'];
        $canViewAll = $this->hasPerm($user, 'announcements.stats_all') || $this->hasPerm($user, 'announcements.manage_all');

        $ann = $this->repository->findById($aid);
        if ($ann === null) {
            $this->error('Announcement not found.', 404);
        }

        if (!$this->adapter->canViewAnnouncementStats($ann, $userId, $canViewAll)) {
            $this->error('Forbidden: You are not authorized to view read receipts for another HR officer\'s announcement.', 403);
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 25)));
        $filter = trim((string)($_GET['filter'] ?? 'all'));

        $receipts = $this->repository->paginateReadReceipts($aid, $page, $perPage, $filter);
        $this->success($receipts, 'Read receipts retrieved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function requireStaffPermission(string $permission): array
    {
        $user = $this->requireRole(['hr', 'admin']);
        if (
            !$this->hasPerm($user, $permission)
            && !$this->hasPerm($user, 'announcements.manage_all')
            && !($permission === 'announcements.stats_own' && $this->hasPerm($user, 'announcements.stats_all'))
        ) {
            $this->error('Forbidden: You do not hold the required permission (' . $permission . ') to access this resource.', 403);
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function hasPerm(array $user, string $permission): bool
    {
        $role = strtolower((string)($user['role'] ?? ''));
        return Permission::can($role, $permission);
    }

    private function drainInitialQueueBatchSilently(): void
    {
        ob_start();
        try {
            $worker = new QueueWorker();
            $worker->run(['once' => true, 'limit' => 2]);
        } catch (Throwable) {
        } finally {
            ob_end_clean();
        }
    }
}
