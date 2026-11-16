<?php

/**
 * System Audit & Activity Logs Controller
 * Provides read-only security and operational audit trail
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use App\Models\ActivityLog;
use App\Services\RecentActivityStackService;
use Core\AuditService;
use Core\Controller;
use Core\Session;

class ActivityLogController extends Controller
{
    private ActivityLog $logModel;
    private RecentActivityStackService $stackService;

    public function __construct()
    {
        $this->logModel = new ActivityLog();
        $this->stackService = new RecentActivityStackService($this->logModel);
    }

    /**
     * Display Audit Trail Directory (HR & Admin Access)
     * Route: GET /activity-logs
     */
    public function index(): void
    {
        $filters = [
            'q'         => !empty($_GET['q']) ? trim((string)$_GET['q']) : null,
            'category'  => !empty($_GET['category']) ? trim((string)$_GET['category']) : null,
            'action'    => !empty($_GET['action']) ? trim((string)$_GET['action']) : null,
            'role'      => !empty($_GET['role']) ? trim((string)$_GET['role']) : null,
            'status'    => !empty($_GET['status']) ? trim((string)$_GET['status']) : null,
            'from_date' => !empty($_GET['from_date']) ? trim((string)$_GET['from_date']) : null,
            'to_date'   => !empty($_GET['to_date']) ? trim((string)$_GET['to_date']) : null,
        ];

        // Clean nulls from filters
        $activeFilters = array_filter($filters, fn($v) => $v !== null && $v !== '');

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 25)));

        $totalCount = $this->logModel->countFiltered($activeFilters);
        $totalPages = max(1, (int)ceil($totalCount / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $logs = $this->logModel->getFiltered($activeFilters, $perPage, $offset);
        $stats = $this->logModel->getStats();
        $distinctActions = $this->logModel->getDistinctActions();

        $categories = [
            AuditService::CATEGORY_AUTH         => 'Authentication & Security',
            AuditService::CATEGORY_USER         => 'User Management',
            AuditService::CATEGORY_RBAC         => 'Roles & Permissions',
            AuditService::CATEGORY_EMPLOYEE     => 'Employee Workforce',
            AuditService::CATEGORY_ORGANIZATION => 'Departments & Positions',
            AuditService::CATEGORY_LEAVE        => 'Leave Administration',
            AuditService::CATEGORY_SUPPORT      => 'Help & Support Operations',
            AuditService::CATEGORY_REPORTS      => 'Reports & Exports',
            AuditService::CATEGORY_SYSTEM       => 'System Configuration',
        ];

        // Custom LIFO Stack for audit trail fast inspection
        $activityStack  = $this->stackService->loadRecentActivitiesStack(50);
        $latestActivity = $this->stackService->getLatestActivity($activityStack);
        $stackDepth     = $activityStack->size();

        $this->view('activity_logs.index', [
            'pageTitle'        => 'Audit Trail & Activity Logs',
            'logs'             => $logs,
            'stats'            => $stats,
            'filters'          => $filters,
            'activeFilters'    => $activeFilters,
            'distinctActions'  => $distinctActions,
            'categories'       => $categories,
            'page'             => $page,
            'perPage'          => $perPage,
            'totalCount'       => $totalCount,
            'totalPages'       => $totalPages,
            'latestActivity'   => $latestActivity,
            'stackDepth'       => $stackDepth,
            'user'             => currentUser(),
        ], 'hr');
    }

    /**
     * Export Audit Trail to CSV with formula injection defense
     * Route: GET /activity-logs/export
     */
    public function export(): void
    {
        $role = currentUserRole();
        if (!in_array($role, ['admin', 'hr'], true)) {
            http_response_code(403);
            echo 'Access denied: Only Administrators and HR Officers may export audit logs.';
            return;
        }

        $filters = [
            'q'         => !empty($_GET['q']) ? trim((string)$_GET['q']) : null,
            'category'  => !empty($_GET['category']) ? trim((string)$_GET['category']) : null,
            'action'    => !empty($_GET['action']) ? trim((string)$_GET['action']) : null,
            'role'      => !empty($_GET['role']) ? trim((string)$_GET['role']) : null,
            'status'    => !empty($_GET['status']) ? trim((string)$_GET['status']) : null,
            'from_date' => !empty($_GET['from_date']) ? trim((string)$_GET['from_date']) : null,
            'to_date'   => !empty($_GET['to_date']) ? trim((string)$_GET['to_date']) : null,
        ];

        $activeFilters = array_filter($filters, fn($v) => $v !== null && $v !== '');
        $csvContent = $this->logModel->exportCsv($activeFilters);

        $filename = 'nexora_audit_trail_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel compatibility
        echo $csvContent;
        exit;
    }
}
