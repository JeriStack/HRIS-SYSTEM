<?php

/**
 * HrAnnouncementController
 * HR Announcements Management System (Phase 3, 4, 6, 9, 10, 12)
 *
 * Provides paginated, searchable, filterable, sortable announcement management,
 * draft creation/editing/deletion, immediate & scheduled publication, schedule cancellation,
 * archiving, read statistics, and authorized attachment downloads.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Application\Announcement\Adapters\AnnouncementControllerAdapter;
use App\Application\Announcement\Adapters\AnnouncementOperationResult;
use App\Infrastructure\Persistence\Announcement\MySqlAnnouncementRepository;
use App\Queue\QueueWorker;
use Core\Controller;
use Core\Database;
use Core\Permission;
use Core\Session;
use PDO;
use Throwable;

class HrAnnouncementController extends Controller
{
    protected PDO $pdo;
    protected AnnouncementControllerAdapter $adapter;
    protected MySqlAnnouncementRepository $repository;
    protected bool $terminateOnRedirect;

    /** @var array{url: string, status: int}|null */
    protected ?array $lastRedirect = null;

    public function __construct(
        ?PDO $pdo = null,
        ?AnnouncementControllerAdapter $adapter = null,
        bool $terminateOnRedirect = true
    ) {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
        $this->adapter = $adapter ?? new AnnouncementControllerAdapter($this->pdo);
        $this->repository = $this->adapter->getRepository();
        $this->terminateOnRedirect = $terminateOnRedirect;
    }

    /**
     * @return array{url: string, status: int}|null
     */
    public function getLastRedirect(): ?array
    {
        return $this->lastRedirect;
    }

    /**
     * HR Announcements Management Dashboard & List (Phase 3.1).
     * Route: GET /hr/announcements
     */
    public function index(): void
    {
        $user = $this->requireAuthorizedStaff('announcements.view');
        if ($user === null) {
            return;
        }

        //Opportunistically process any due scheduled publications / expirations
        try {
            $this->adapter->processDueLifecycleTransitions();
        } catch (Throwable) {
        }

        $userId = (int)$user['id'];
        $canManageAll = $this->userHasPermission($user, 'announcements.manage_all');
        $canViewAllStats = $this->userHasPermission($user, 'announcements.stats_all') || $canManageAll;

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

        $items = [];
        foreach ($paginated['items'] as $row) {
            $isOwner = ((int)($row['author_id'] ?? 0) === $userId);
            $row['can_modify'] = $isOwner || $canManageAll;
            $row['can_view_stats'] = ($isOwner && $this->userHasPermission($user, 'announcements.stats_own')) || $canViewAllStats;
            $items[] = $row;
        }

        if (request_wants_json()) {
            $this->json([
                'status'        => 'success',
                'stats'         => $stats,
                'announcements' => $items,
                'pagination'    => [
                    'total'       => $paginated['total'],
                    'page'        => $paginated['page'],
                    'per_page'    => $paginated['per_page'],
                    'total_pages' => $paginated['total_pages'],
                ],
            ]);
            return;
        }

        $this->view('hr.announcements.index', [
            'pageTitle'     => 'Announcements Management - HR Portal',
            'announcements' => $items,
            'stats'         => $stats,
            'filters'       => $filters,
            'pagination'    => [
                'total'       => $paginated['total'],
                'page'        => $paginated['page'],
                'per_page'    => $paginated['per_page'],
                'total_pages' => $paginated['total_pages'],
            ],
            'categories'    => (array)config('announcements.categories', []),
            'priorities'    => (array)config('announcements.priorities', ['Normal', 'Important', 'Urgent']),
            'statuses'      => (array)config('announcements.statuses', []),
            'canCreate'     => $this->userHasPermission($user, 'announcements.create'),
            'canPublish'    => $this->userHasPermission($user, 'announcements.publish') || $canManageAll,
            'canSchedule'   => $this->userHasPermission($user, 'announcements.schedule') || $canManageAll,
            'canArchive'    => $this->userHasPermission($user, 'announcements.archive') || $canManageAll,
            'canDelete'     => $this->userHasPermission($user, 'announcements.delete_draft') || $canManageAll,
        ], 'hr');
    }

    /**
     * Render Create Announcement Form (Phase 3.2).
     * Route: GET /hr/announcements/create
     */
    public function create(): void
    {
        $user = $this->requireAuthorizedStaff('announcements.create');
        if ($user === null) {
            return;
        }

        $old = Session::getFlash('old_input', []);
        if (!is_array($old)) {
            $old = [];
        }

        $departments = $this->fetchAllDepartments();

        $this->view('hr.announcements.form', [
            'pageTitle'    => 'Create Announcement - HR Portal',
            'mode'         => 'create',
            'announcement' => null,
            'departments'  => $departments,
            'categories'   => (array)config('announcements.categories', []),
            'priorities'   => (array)config('announcements.priorities', ['Normal', 'Important', 'Urgent']),
            'maxFiles'     => (int)config('announcements.max_attachments_per_announcement', 3),
            'maxBytes'     => (int)config('announcements.attachment_max_bytes', 5 * 1024 * 1024),
            'old'          => $old,
            'canPublish'   => $this->userHasPermission($user, 'announcements.publish'),
            'canSchedule'  => $this->userHasPermission($user, 'announcements.schedule'),
        ], 'hr');
    }

    /**
     * Store a new announcement (Draft, Scheduled, or Immediate Publish).
     * Route: POST /hr/announcements
     */
    public function store(): void
    {
        $user = $this->requireAuthorizedStaff('announcements.create');
        if ($user === null) {
            return;
        }

        if (!$this->checkRateLimit((int)$user['id'])) {
            $this->respondFailure(
                AnnouncementOperationResult::fail('Too many announcement operations. Please wait a moment and try again.', 429, 'rate_limited'),
                '/hr/announcements/create',
                true
            );
            return;
        }

        $publishMode = strtolower(trim((string)($_POST['publish_mode'] ?? $_POST['action'] ?? 'draft')));
        if (($publishMode === 'publish' || $publishMode === 'publish_now') && !$this->userHasPermission($user, 'announcements.publish')) {
            $this->forbid('You do not have permission to publish announcements.');
            return;
        }
        if ($publishMode === 'schedule' && !$this->userHasPermission($user, 'announcements.schedule')) {
            $this->forbid('You do not have permission to schedule announcements.');
            return;
        }

        $res = $this->adapter->create(
            authorUserId: (int)$user['id'],
            actorRole: (string)($user['role'] ?? 'hr'),
            input: $_POST,
            filesInput: $_FILES['attachments'] ?? null
        );

        if (!$res->success) {
            $this->respondFailure($res, '/hr/announcements/create', true);
            return;
        }

        if (($res->data['status'] ?? '') === 'Published') {
            $this->drainInitialQueueBatchSilently();
        }

        $newId = (int)($res->data['id'] ?? 0);
        if (request_wants_json()) {
            $this->json([
                'status'  => 'success',
                'message' => $res->message,
                'data'    => $res->data,
            ], 201);
            return;
        }

        Session::flash('success', $res->message);
        $this->navigate($newId > 0 ? '/hr/announcements/' . $newId : '/hr/announcements');
    }

    /**
     * View Announcement Preview, Lifecycle Actions & Read Statistics (Phase 3.3, 3.4, 6.2).
     * Route: GET /hr/announcements/{id}
     */
    public function show(int|string $id): void
    {
        $user = $this->requireAuthorizedStaff('announcements.view');
        if ($user === null) {
            return;
        }

        $aid = (int)$id;
        $userId = (int)$user['id'];
        $canManageAll = $this->userHasPermission($user, 'announcements.manage_all');
        $canViewAllStats = $this->userHasPermission($user, 'announcements.stats_all') || $canManageAll;

        $detail = $this->repository->findDetailForHr($aid);
        if ($detail === null) {
            $this->notFound('Announcement not found.');
            return;
        }

        $isOwner = ((int)($detail['author_id'] ?? 0) === $userId);
        if (($detail['status'] ?? '') === 'Draft' && !$isOwner && !$canManageAll) {
            $this->forbid('You do not have permission to view another HR officer\'s private draft announcement.');
            return;
        }

        $canModify = ($isOwner && $this->userHasPermission($user, 'announcements.edit_own')) || $canManageAll;
        $canViewStats = ($isOwner && $this->userHasPermission($user, 'announcements.stats_own')) || $canViewAllStats;

        $readStats = null;
        $readReceipts = null;
        $receiptFilter = trim((string)($_GET['read_filter'] ?? 'all'));
        $receiptPage = max(1, (int)($_GET['read_page'] ?? 1));

        if ($canViewStats) {
            $readStats = $this->repository->getReadStatistics($aid);
            $readReceipts = $this->repository->paginateReadReceipts($aid, $receiptPage, 25, $receiptFilter);
        }

        if (request_wants_json()) {
            $this->json([
                'status'        => 'success',
                'announcement'  => $detail,
                'can_modify'    => $canModify,
                'can_view_stats' => $canViewStats,
                'statistics'    => $readStats,
                'read_receipts' => $readReceipts,
            ]);
            return;
        }

        $this->view('hr.announcements.show', [
            'pageTitle'     => e((string)$detail['title']) . ' - Announcements Management',
            'announcement'  => $detail,
            'canModify'     => $canModify,
            'canPublish'    => $canModify && ($this->userHasPermission($user, 'announcements.publish') || $canManageAll),
            'canSchedule'   => $canModify && ($this->userHasPermission($user, 'announcements.schedule') || $canManageAll),
            'canArchive'    => $canModify && ($this->userHasPermission($user, 'announcements.archive') || $canManageAll),
            'canDelete'     => $canModify && ($this->userHasPermission($user, 'announcements.delete_draft') || $canManageAll),
            'canViewStats'  => $canViewStats,
            'readStats'     => $readStats,
            'readReceipts'  => $readReceipts,
            'receiptFilter' => $receiptFilter,
        ], 'hr');
    }

    /**
     * Render Edit Announcement Form.
     * Route: GET /hr/announcements/{id}/edit
     */
    public function edit(int|string $id): void
    {
        $user = $this->requireAuthorizedStaff('announcements.edit_own');
        if ($user === null) {
            return;
        }

        $aid = (int)$id;
        $userId = (int)$user['id'];
        $canManageAll = $this->userHasPermission($user, 'announcements.manage_all');

        $detail = $this->repository->findDetailForHr($aid);
        if ($detail === null) {
            $this->notFound('Announcement not found.');
            return;
        }

        $isOwner = ((int)($detail['author_id'] ?? 0) === $userId);
        if (!$isOwner && !$canManageAll) {
            $this->forbid('You are not authorized to edit an announcement created by another HR officer.');
            return;
        }

        if (($detail['status'] ?? '') === 'Archived') {
            Session::flash('error', 'Archived announcements cannot be edited.');
            $this->navigate('/hr/announcements/' . $aid);
            return;
        }

        $old = Session::getFlash('old_input', []);
        if (!is_array($old)) {
            $old = [];
        }

        $this->view('hr.announcements.form', [
            'pageTitle'    => 'Edit Announcement - HR Portal',
            'mode'         => 'edit',
            'announcement' => $detail,
            'departments'  => $this->fetchAllDepartments(),
            'categories'   => (array)config('announcements.categories', []),
            'priorities'   => (array)config('announcements.priorities', ['Normal', 'Important', 'Urgent']),
            'maxFiles'     => (int)config('announcements.max_attachments_per_announcement', 3),
            'maxBytes'     => (int)config('announcements.attachment_max_bytes', 5 * 1024 * 1024),
            'old'          => $old,
            'canPublish'   => $this->userHasPermission($user, 'announcements.publish') || $canManageAll,
            'canSchedule'  => $this->userHasPermission($user, 'announcements.schedule') || $canManageAll,
        ], 'hr');
    }

    /**
     * Update an existing announcement.
     * Route: POST /hr/announcements/{id}/edit
     */
    public function update(int|string $id): void
    {
        $user = $this->requireAuthorizedStaff('announcements.edit_own');
        if ($user === null) {
            return;
        }

        $aid = (int)$id;
        $canManageAll = $this->userHasPermission($user, 'announcements.manage_all');

        $res = $this->adapter->update(
            announcementId: $aid,
            actorUserId: (int)$user['id'],
            actorRole: (string)($user['role'] ?? 'hr'),
            canManageAll: $canManageAll,
            input: $_POST,
            filesInput: $_FILES['attachments'] ?? null
        );

        if (!$res->success) {
            $this->respondFailure($res, '/hr/announcements/' . $aid . '/edit', true);
            return;
        }

        if (($res->data['status'] ?? '') === 'Published') {
            $this->drainInitialQueueBatchSilently();
        }

        if (request_wants_json()) {
            $this->json([
                'status'  => 'success',
                'message' => $res->message,
                'data'    => $res->data,
            ]);
            return;
        }

        Session::flash('success', $res->message);
        $this->navigate('/hr/announcements/' . $aid);
    }

    /**
     * Immediately publish an announcement.
     * Route: POST /hr/announcements/{id}/publish
     */
    public function publish(int|string $id): void
    {
        $user = $this->requireAuthorizedStaff('announcements.publish');
        if ($user === null) {
            return;
        }

        $aid = (int)$id;
        $canManageAll = $this->userHasPermission($user, 'announcements.manage_all');
        $res = $this->adapter->publish($aid, (int)$user['id'], $canManageAll);

        if (!$res->success) {
            $this->respondFailure($res, '/hr/announcements/' . $aid);
            return;
        }

        $this->drainInitialQueueBatchSilently();

        if (request_wants_json()) {
            $this->json(['status' => 'success', 'message' => $res->message, 'data' => $res->data]);
            return;
        }

        Session::flash('success', $res->message);
        $this->navigate('/hr/announcements/' . $aid);
    }

    /**
     * Schedule an announcement for future publication.
     * Route: POST /hr/announcements/{id}/schedule
     */
    public function schedule(int|string $id): void
    {
        $user = $this->requireAuthorizedStaff('announcements.schedule');
        if ($user === null) {
            return;
        }

        $aid = (int)$id;
        $canManageAll = $this->userHasPermission($user, 'announcements.manage_all');
        $scheduledAtRaw = trim((string)($_POST['scheduled_at'] ?? ''));
        $expiresAtRaw = isset($_POST['expires_at']) ? trim((string)$_POST['expires_at']) : null;

        $res = $this->adapter->schedule(
            announcementId: $aid,
            actorUserId: (int)$user['id'],
            scheduledAtRaw: $scheduledAtRaw,
            expiresAtRaw: $expiresAtRaw,
            canManageAll: $canManageAll
        );

        if (!$res->success) {
            $this->respondFailure($res, '/hr/announcements/' . $aid);
            return;
        }

        if (request_wants_json()) {
            $this->json(['status' => 'success', 'message' => $res->message, 'data' => $res->data]);
            return;
        }

        Session::flash('success', $res->message);
        $this->navigate('/hr/announcements/' . $aid);
    }

    /**
     * Cancel a pending scheduled publication and revert to Draft.
     * Route: POST /hr/announcements/{id}/cancel-schedule
     */
    public function cancelSchedule(int|string $id): void
    {
        $user = $this->requireAuthorizedStaff('announcements.schedule');
        if ($user === null) {
            return;
        }

        $aid = (int)$id;
        $canManageAll = $this->userHasPermission($user, 'announcements.manage_all');
        $res = $this->adapter->cancelSchedule($aid, (int)$user['id'], $canManageAll);

        if (!$res->success) {
            $this->respondFailure($res, '/hr/announcements/' . $aid);
            return;
        }

        if (request_wants_json()) {
            $this->json(['status' => 'success', 'message' => $res->message, 'data' => $res->data]);
            return;
        }

        Session::flash('success', $res->message);
        $this->navigate('/hr/announcements/' . $aid);
    }

    /**
     * Archive an announcement.
     * Route: POST /hr/announcements/{id}/archive
     */
    public function archive(int|string $id): void
    {
        $user = $this->requireAuthorizedStaff('announcements.archive');
        if ($user === null) {
            return;
        }

        $aid = (int)$id;
        $canManageAll = $this->userHasPermission($user, 'announcements.manage_all');
        $res = $this->adapter->archive($aid, (int)$user['id'], $canManageAll);

        if (!$res->success) {
            $this->respondFailure($res, '/hr/announcements/' . $aid);
            return;
        }

        if (request_wants_json()) {
            $this->json(['status' => 'success', 'message' => $res->message, 'data' => $res->data]);
            return;
        }

        Session::flash('success', $res->message);
        $this->navigate('/hr/announcements/' . $aid);
    }

    /**
     * Delete an unpublished Draft announcement.
     * Route: POST /hr/announcements/{id}/delete
     */
    public function deleteDraft(int|string $id): void
    {
        $user = $this->requireAuthorizedStaff('announcements.delete_draft');
        if ($user === null) {
            return;
        }

        $aid = (int)$id;
        $canManageAll = $this->userHasPermission($user, 'announcements.manage_all');
        $res = $this->adapter->deleteDraft($aid, (int)$user['id'], $canManageAll);

        if (!$res->success) {
            $this->respondFailure($res, '/hr/announcements/' . $aid);
            return;
        }

        if (request_wants_json()) {
            $this->json(['status' => 'success', 'message' => $res->message, 'data' => $res->data]);
            return;
        }

        Session::flash('success', $res->message);
        $this->navigate('/hr/announcements');
    }

    /**
     * Download an announcement attachment (HR/Admin with announcements.view).
     * Route: GET /hr/announcements/{id}/attachments/{attachmentId}
     */
    public function downloadAttachment(int|string $id, int|string $attachmentId): void
    {
        $user = $this->requireAuthorizedStaff('announcements.view');
        if ($user === null) {
            return;
        }

        $aid = (int)$id;
        $attId = (int)$attachmentId;
        $userId = (int)$user['id'];
        $canManageAll = $this->userHasPermission($user, 'announcements.manage_all');

        $ann = $this->repository->findById($aid);
        if ($ann === null) {
            $this->notFound('Announcement not found.');
            return;
        }

        if ($ann->status()->isDraft() && $ann->authorId() !== $userId && !$canManageAll) {
            $this->forbid('You are not authorized to download attachments from another HR officer\'s draft.');
            return;
        }

        $attRow = $this->repository->findAttachmentById($attId, $aid);
        if ($attRow === null) {
            $this->notFound('Attachment not found.');
            return;
        }

        $filePath = $this->adapter->getAttachmentService()->resolveAuthorizedFilePath((string)$attRow['storage_relative_path']);
        if ($filePath === null) {
            $this->notFound('Attachment file is unavailable.');
            return;
        }

        $safeName = str_replace(['"', "\r", "\n"], '', (string)($attRow['original_filename'] ?? 'attachment'));
        $mimeType = (string)($attRow['mime_type'] ?? 'application/octet-stream');

        if (!headers_sent()) {
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . (string)filesize($filePath));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Content-Disposition: attachment; filename="' . $safeName . '"');
        }

        readfile($filePath);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requireAuthorizedStaff(string $permission): ?array
    {
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return null;
        }

        $role = strtolower((string)($user['role'] ?? ''));
        if (!in_array($role, ['hr', 'admin'], true)) {
            $this->forbid('Only authorized HR personnel may access Announcements Management.');
            return null;
        }

        if (
            !$this->userHasPermission($user, $permission)
            && !$this->userHasPermission($user, 'announcements.manage_all')
        ) {
            $this->forbid('You do not hold the required permission (' . $permission . ') to perform this action.');
            return null;
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function userHasPermission(array $user, string $permission): bool
    {
        $role = strtolower((string)($user['role'] ?? ''));
        return Permission::can($role, $permission);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllDepartments(): array
    {
        $stmt = $this->pdo->query('SELECT id, department_name, description FROM departments ORDER BY department_name ASC');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    private function checkRateLimit(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $window = (int)config('announcements.rate_limit_window_seconds', 900);
        $max = (int)config('announcements.rate_limit_max_mutations', 30);

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM activity_logs
            WHERE user_id = :uid
              AND action LIKE 'ANNOUNCEMENT_%'
              AND created_at >= DATE_SUB(NOW(), INTERVAL :win SECOND)
        ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':win', $window, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$stmt->fetchColumn() < $max;
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

    protected function respondFailure(
        AnnouncementOperationResult $res,
        string $fallbackRedirect,
        bool $flashOldInput = false
    ): void {
        if ($res->statusCode === 404) {
            $this->notFound($res->message);
            return;
        }
        if ($res->statusCode === 403) {
            $this->forbid($res->message);
            return;
        }

        if (request_wants_json()) {
            $this->json(['status' => 'error', 'message' => $res->message, 'error_code' => $res->errorCode], $res->statusCode);
            return;
        }

        if ($flashOldInput) {
            Session::flash('old_input', $_POST);
        }
        Session::flash('error', $res->message);
        $this->navigate($fallbackRedirect);
    }

    protected function forbid(string $message): void
    {
        if (!headers_sent()) {
            http_response_code(403);
        }
        if (request_wants_json()) {
            $this->json(['status' => 'error', 'message' => $message], 403);
            return;
        }
        $this->view('errors.403', [
            'pageTitle' => '403 Forbidden - Access Restricted',
            'message'   => $message,
        ], null);
    }

    protected function notFound(string $message): void
    {
        if (!headers_sent()) {
            http_response_code(404);
        }
        if (request_wants_json()) {
            $this->json(['status' => 'error', 'message' => $message], 404);
            return;
        }
        $this->view('errors.404', [
            'pageTitle' => '404 Not Found',
            'message'   => $message,
        ], null);
    }

    protected function navigate(string $path, int $statusCode = 302): void
    {
        $this->lastRedirect = ['url' => $path, 'status' => $statusCode];
        if ($this->terminateOnRedirect) {
            $this->redirect($path, $statusCode);
        }
    }
}
