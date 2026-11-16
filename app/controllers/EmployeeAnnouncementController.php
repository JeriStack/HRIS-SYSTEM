<?php

/**
 * EmployeeAnnouncementController
 * Employee Announcement Feed, Details, Read Receipts & Attachment Downloads (Phase 5 & Phase 6)
 *
 * Strictly enforces employee audience targeting (`all` or employee's `department_id`),
 * active employment status, published status, and non-expiration.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Application\Announcement\Adapters\AnnouncementControllerAdapter;
use App\Infrastructure\Persistence\Announcement\MySqlAnnouncementRepository;
use Core\Controller;
use Core\Database;
use Core\Permission;
use PDO;
use Throwable;

class EmployeeAnnouncementController extends Controller
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
     * Employee Announcement Feed (Phase 5.1).
     * Route: GET /employee/announcements
     */
    public function index(): void
    {
        $ctx = $this->resolveAuthorizedEmployeeContext();
        if ($ctx === null) {
            return;
        }

        try {
            $this->adapter->processDueLifecycleTransitions();
        } catch (Throwable) {
        }

        $filters = [
            'search'      => trim((string)($_GET['search'] ?? $_GET['q'] ?? '')),
            'category'    => trim((string)($_GET['category'] ?? '')),
            'priority'    => trim((string)($_GET['priority'] ?? '')),
            'unread_only' => !empty($_GET['unread_only']),
            'pinned_only' => !empty($_GET['pinned_only']),
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(50, (int)($_GET['per_page'] ?? config('announcements.employee_per_page_default', 10))));

        $paginated = $this->repository->paginateForEmployee(
            employeeId: $ctx['employee_id'],
            departmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status'],
            filters: $filters,
            page: $page,
            perPage: $perPage
        );

        $unreadCount = $this->repository->countUnreadForEmployee(
            employeeId: $ctx['employee_id'],
            departmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status']
        );

        if (request_wants_json()) {
            $this->json([
                'status'        => 'success',
                'unread_count'  => $unreadCount,
                'announcements' => $paginated['items'],
                'pagination'    => [
                    'total'       => $paginated['total'],
                    'page'        => $paginated['page'],
                    'per_page'    => $paginated['per_page'],
                    'total_pages' => $paginated['total_pages'],
                ],
            ]);
            return;
        }

        $this->view('employee.announcements.index', [
            'pageTitle'     => 'Company Announcements - NEXORA HR',
            'announcements' => $paginated['items'],
            'unreadCount'   => $unreadCount,
            'filters'       => $filters,
            'pagination'    => [
                'total'       => $paginated['total'],
                'page'        => $paginated['page'],
                'per_page'    => $paginated['per_page'],
                'total_pages' => $paginated['total_pages'],
            ],
            'categories'    => (array)config('announcements.categories', []),
            'priorities'    => (array)config('announcements.priorities', ['Normal', 'Important', 'Urgent']),
        ], 'hr');
    }

    /**
     * Open Full Employee Announcement Details & Record Read Receipt (Phase 5.3 & 6.1).
     * Route: GET /employee/announcements/{id}
     */
    public function show(int|string $id): void
    {
        $ctx = $this->resolveAuthorizedEmployeeContext();
        if ($ctx === null) {
            return;
        }

        $aid = (int)$id;
        $detail = $this->repository->findAccessibleDetailForEmployee(
            announcementId: $aid,
            employeeId: $ctx['employee_id'],
            departmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status']
        );

        if ($detail === null) {
            $this->forbidOrNotFound($aid);
            return;
        }

        // Record idempotent read receipt upon opening full announcement details (Phase 6.1)
        $readResult = $this->adapter->recordEmployeeRead(
            announcementId: $aid,
            employeeId: $ctx['employee_id'],
            employeeDepartmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status']
        );
        if ($readResult->success && is_array($readResult->data)) {
            $detail['is_read'] = true;
            $detail['read_at'] = $readResult->data['read_at'] ?? $detail['read_at'];
        }

        $unreadCount = $this->repository->countUnreadForEmployee(
            employeeId: $ctx['employee_id'],
            departmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status']
        );

        if (request_wants_json()) {
            $this->json([
                'status'       => 'success',
                'announcement' => $detail,
                'unread_count' => $unreadCount,
            ]);
            return;
        }

        $this->view('employee.announcements.show', [
            'pageTitle'    => e((string)$detail['title']) . ' - Company Announcements',
            'announcement' => $detail,
            'unreadCount'  => $unreadCount,
        ], 'hr');
    }

    /**
     * Explicit POST endpoint to mark an announcement as read (idempotent).
     * Route: POST /employee/announcements/{id}/read
     */
    public function markRead(int|string $id): void
    {
        $ctx = $this->resolveAuthorizedEmployeeContext();
        if ($ctx === null) {
            return;
        }

        $aid = (int)$id;
        $res = $this->adapter->recordEmployeeRead(
            announcementId: $aid,
            employeeId: $ctx['employee_id'],
            employeeDepartmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status']
        );

        if (!$res->success) {
            if ($res->statusCode === 404) {
                $this->notFound($res->message);
            } else {
                $this->forbid($res->message);
            }
            return;
        }

        $unreadCount = $this->repository->countUnreadForEmployee(
            employeeId: $ctx['employee_id'],
            departmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status']
        );

        if (request_wants_json()) {
            $this->json([
                'status'       => 'success',
                'message'      => $res->message,
                'data'         => $res->data,
                'unread_count' => $unreadCount,
            ]);
            return;
        }

        $this->navigate('/employee/announcements/' . $aid);
    }

    /**
     * Download an authorized announcement attachment (Employee audience-checked).
     * Route: GET /employee/announcements/{id}/attachments/{attachmentId}
     */
    public function downloadAttachment(int|string $id, int|string $attachmentId): void
    {
        $ctx = $this->resolveAuthorizedEmployeeContext();
        if ($ctx === null) {
            return;
        }

        $aid = (int)$id;
        $attId = (int)$attachmentId;

        $detail = $this->repository->findAccessibleDetailForEmployee(
            announcementId: $aid,
            employeeId: $ctx['employee_id'],
            departmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status']
        );

        if ($detail === null) {
            $this->forbid('You are not authorized to access attachments for this announcement.');
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
     * Resolve and verify the authenticated employee's active record and department_id from the database.
     * Never trusts browser-supplied employee_id or department_id.
     *
     * @return array{user_id: int, employee_id: int, department_id: ?int, employment_status: string}|null
     */
    private function resolveAuthorizedEmployeeContext(): ?array
    {
        $user = currentUser();
        if (!$user) {
            $this->forbid('Authentication required.');
            return null;
        }

        $role = strtolower((string)($user['role'] ?? ''));
        if ($role !== 'employee' || !Permission::can($role, 'announcements.feed')) {
            $this->forbid('Only authorized employees may access the Employee Announcements feed.');
            return null;
        }

        $employeeId = !empty($user['employee_id']) ? (int)$user['employee_id'] : 0;
        if ($employeeId <= 0) {
            $this->forbid('No employee profile is linked to this account.');
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, department_id, employment_status
            FROM employees
            WHERE id = :eid
            LIMIT 1
        ");
        $stmt->execute(['eid' => $employeeId]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$emp || strcasecmp((string)($emp['employment_status'] ?? ''), 'Active') !== 0) {
            $this->forbid('Active employee status is required to view organizational announcements.');
            return null;
        }

        return [
            'user_id'           => (int)$user['id'],
            'employee_id'       => (int)$emp['id'],
            'department_id'     => $emp['department_id'] !== null ? (int)$emp['department_id'] : null,
            'employment_status' => (string)$emp['employment_status'],
        ];
    }

    private function forbidOrNotFound(int $announcementId): void
    {
        $ann = $this->repository->findById($announcementId);
        if ($ann === null) {
            $this->notFound('Announcement not found.');
            return;
        }

        $this->forbid('This announcement is not published or is restricted to another department.');
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
