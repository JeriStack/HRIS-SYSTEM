<?php

/**
 * REST API v1 — Employee Announcements Feed & Read Receipt Controller
 * Employee HRIS & Leave Management System (NEXORA HR)
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Application\Announcement\Adapters\AnnouncementControllerAdapter;
use App\Infrastructure\Persistence\Announcement\MySqlAnnouncementRepository;
use Core\Database;
use Core\Permission;
use OpenApi\Attributes as OA;
use PDO;

class EmployeeAnnouncementController extends ApiController
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
        path: "/api/v1/employee/announcements",
        summary: "Retrieve targeted published announcements for the authenticated employee",
        tags: ["Employee Announcements"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "category", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "priority", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["Normal", "Important", "Urgent"])),
            new OA\Parameter(name: "unread_only", in: "query", required: false, schema: new OA\Schema(type: "boolean")),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1)),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 10))
        ],
        responses: [
            new OA\Response(response: 200, description: "Employee announcement feed"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden")
        ]
    )]
    public function index(): void
    {
        $ctx = $this->requireAuthorizedEmployeeContext();

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

        $this->success([
            'unread_count'  => $unreadCount,
            'announcements' => $paginated['items'],
            'pagination'    => [
                'total'       => $paginated['total'],
                'page'        => $paginated['page'],
                'per_page'    => $paginated['per_page'],
                'total_pages' => $paginated['total_pages'],
            ],
        ], 'Employee announcements retrieved.');
    }

    #[OA\Get(
        path: "/api/v1/employee/announcements/unread-count",
        summary: "Retrieve unread announcement count for the authenticated employee",
        tags: ["Employee Announcements"],
        security: [["cookieAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Unread announcement count"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden")
        ]
    )]
    public function unreadCount(): void
    {
        $ctx = $this->requireAuthorizedEmployeeContext();
        $count = $this->repository->countUnreadForEmployee(
            employeeId: $ctx['employee_id'],
            departmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status']
        );

        $this->success(['unread_count' => $count], 'Unread announcement count retrieved.');
    }

    #[OA\Get(
        path: "/api/v1/employee/announcements/{id}",
        summary: "Retrieve full announcement details for an authorized targeted employee",
        tags: ["Employee Announcements"],
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
        $ctx = $this->requireAuthorizedEmployeeContext();
        $aid = (int)$id;

        $detail = $this->repository->findAccessibleDetailForEmployee(
            announcementId: $aid,
            employeeId: $ctx['employee_id'],
            departmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status']
        );

        if ($detail === null) {
            $ann = $this->repository->findById($aid);
            if ($ann === null) {
                $this->error('Announcement not found.', 404);
            }
            $this->error('Forbidden: This announcement is not published or is restricted to another department.', 403);
        }

        $readRes = $this->adapter->recordEmployeeRead(
            announcementId: $aid,
            employeeId: $ctx['employee_id'],
            employeeDepartmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status']
        );
        if ($readRes->success && is_array($readRes->data)) {
            $detail['is_read'] = true;
            $detail['read_at'] = $readRes->data['read_at'] ?? $detail['read_at'];
        }

        $this->success($detail, 'Announcement retrieved.');
    }

    #[OA\Post(
        path: "/api/v1/employee/announcements/{id}/read",
        summary: "Idempotently record a read receipt for a targeted announcement",
        tags: ["Employee Announcements"],
        security: [["cookieAuth" => [], "csrfToken" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Read receipt recorded"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not found")
        ]
    )]
    public function markRead(int|string $id): void
    {
        $ctx = $this->requireAuthorizedEmployeeContext();
        $aid = (int)$id;

        $res = $this->adapter->recordEmployeeRead(
            announcementId: $aid,
            employeeId: $ctx['employee_id'],
            employeeDepartmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status']
        );

        if (!$res->success) {
            $this->error($res->message, $res->statusCode);
        }

        $unreadCount = $this->repository->countUnreadForEmployee(
            employeeId: $ctx['employee_id'],
            departmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status']
        );

        $data = is_array($res->data) ? $res->data : [];
        $data['unread_count'] = $unreadCount;

        $this->success($data, $res->message, 200);
    }

    #[OA\Get(
        path: "/api/v1/employee/announcements/{id}/attachments/{attachmentId}",
        summary: "Download an authorized announcement attachment",
        tags: ["Employee Announcements"],
        security: [["cookieAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "attachmentId", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Binary attachment stream"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not found")
        ]
    )]
    public function downloadAttachment(int|string $id, int|string $attachmentId): void
    {
        $ctx = $this->requireAuthorizedEmployeeContext();
        $aid = (int)$id;
        $attId = (int)$attachmentId;

        $detail = $this->repository->findAccessibleDetailForEmployee(
            announcementId: $aid,
            employeeId: $ctx['employee_id'],
            departmentId: $ctx['department_id'],
            employmentStatus: $ctx['employment_status']
        );

        if ($detail === null) {
            $this->error('Forbidden: You are not authorized to access attachments for this announcement.', 403);
        }

        $attRow = $this->repository->findAttachmentById($attId, $aid);
        if ($attRow === null) {
            $this->error('Attachment not found.', 404);
        }

        $filePath = $this->adapter->getAttachmentService()->resolveAuthorizedFilePath((string)$attRow['storage_relative_path']);
        if ($filePath === null) {
            $this->error('Attachment file is unavailable.', 404);
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
        exit;
    }

    /**
     * @return array{user_id: int, employee_id: int, department_id: ?int, employment_status: string}
     */
    private function requireAuthorizedEmployeeContext(): array
    {
        $user = $this->requireRole('employee');
        if (!Permission::can('employee', 'announcements.feed')) {
            $this->error('Forbidden: You do not have permission to view announcements.', 403);
        }

        $employeeId = !empty($user['employee_id']) ? (int)$user['employee_id'] : 0;
        if ($employeeId <= 0) {
            $this->error('Forbidden: No employee profile record is associated with this account.', 403);
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
            $this->error('Forbidden: Active employment status is required to access announcements.', 403);
        }

        return [
            'user_id'           => (int)$user['id'],
            'employee_id'       => (int)$emp['id'],
            'department_id'     => $emp['department_id'] !== null ? (int)$emp['department_id'] : null,
            'employment_status' => (string)$emp['employment_status'],
        ];
    }
}
