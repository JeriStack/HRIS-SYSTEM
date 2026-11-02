<?php
/**
 * System Audit & Activity Logs Controller
 * Provides read-only security and operational audit trail
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use App\Models\ActivityLog;
use App\Services\RecentActivityStackService;

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
        $search = !empty($_GET['q']) ? trim((string)$_GET['q']) : null;
        $action = !empty($_GET['action']) ? trim((string)$_GET['action']) : null;

        $logs = $this->logModel->getAll($search, $action, 100);

        // Load custom LIFO Stack for audit trail fast inspection (Phase 11/13)
        $activityStack  = $this->stackService->loadRecentActivitiesStack(50);
        $latestActivity = $this->stackService->getLatestActivity($activityStack);
        $stackDepth     = $activityStack->size();

        $this->view('activity_logs.index', [
            'pageTitle'      => 'System Audit & Activity Logs',
            'logs'           => $logs,
            'searchQuery'    => $search,
            'selectedAction' => $action,
            'latestActivity' => $latestActivity,
            'stackDepth'     => $stackDepth,
            'user'           => currentUser(),
        ], 'hr');
    }
}
