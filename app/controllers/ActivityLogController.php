<?php
/**
 * System Audit & Activity Logs Controller
 * Provides read-only security and operational audit trail
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    private ActivityLog $logModel;

    public function __construct()
    {
        $this->logModel = new ActivityLog();
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

        $this->view('activity_logs.index', [
            'pageTitle'      => 'System Audit & Activity Logs',
            'logs'           => $logs,
            'searchQuery'    => $search,
            'selectedAction' => $action,
            'user'           => currentUser(),
        ], 'hr');
    }
}
