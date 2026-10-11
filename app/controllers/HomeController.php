<?php
/**
 * Home & Public Landing Page Controller
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers;

use Core\Controller;
use Core\Database;
use PDOException;

class HomeController extends Controller
{
    /**
     * Display the public welcome landing page.
     */
    public function index(): void
    {
        $db = Database::getInstance();
        $dbConnected = $db->isConnected();
        $dbStatus = $dbConnected ? "Database Connected" : "Database Offline: " . ($db->getLastError() ?? 'Unreachable');

        $stats = [
            'total_employees' => 8,
            'active_employees' => 7,
            'pending_leaves'   => 3,
            'departments'      => 5,
        ];

        // If database is live, attempt a safe lightweight query to verify live table statistics
        if ($dbConnected) {
            try {
                $pdo = $db->getConnection();
                
                // Safe read counts if tables are populated
                $empStmt = $pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN employment_status='Active' THEN 1 ELSE 0 END) AS active FROM employees");
                if ($empRow = $empStmt->fetch()) {
                    $stats['total_employees'] = (int)$empRow['total'];
                    $stats['active_employees'] = (int)($empRow['active'] ?? 0);
                }

                $leaveStmt = $pdo->query("SELECT COUNT(*) AS pending FROM leave_requests WHERE status = 'Pending'");
                if ($leaveRow = $leaveStmt->fetch()) {
                    $stats['pending_leaves'] = (int)$leaveRow['pending'];
                }

                $deptStmt = $pdo->query("SELECT COUNT(*) AS depts FROM departments");
                if ($deptRow = $deptStmt->fetch()) {
                    $stats['departments'] = (int)$deptRow['depts'];
                }
            } catch (PDOException $e) {
                // Fail gracefully and retain baseline synthetic counts
                error_log("[HomeController Notice] Query fallback: " . $e->getMessage());
            }
        }

        $this->view('home.index', [
            'appName'     => config('config.app_name', 'Employee HRIS & Leave Management System'),
            'dbConnected' => $dbConnected,
            'dbStatus'    => $dbStatus,
            'dbName'      => config('database.database', 'hris_db'),
            'stats'       => $stats,
        ], null); // Render full landing page without extra outer layout
    }

    /**
     * Diagnostic Health & Framework Verification Endpoint (JSON).
     */
    public function health(): void
    {
        $db = Database::getInstance();
        $dbConnected = $db->isConnected();

        $dbCheck = false;
        $dbLatencyMs = null;

        if ($dbConnected) {
            try {
                $start = hrtime(true);
                $stmt = $db->getConnection()->query("SELECT 1 AS ping");
                $dbCheck = ($stmt->fetchColumn() == 1);
                $end = hrtime(true);
                $dbLatencyMs = round(($end - $start) / 1e6, 2);
            } catch (PDOException $e) {
                $dbCheck = false;
            }
        }

        $this->json([
            'status'      => 'ok',
            'timestamp'   => date('c'),
            'environment' => config('config.app_env', 'development'),
            'php_version' => PHP_VERSION,
            'framework'   => 'Native PHP MVC Foundation (Phase 5)',
            'database'    => [
                'driver'     => config('database.driver', 'mysql'),
                'database'   => config('database.database', 'hris_db'),
                'connected'  => $dbConnected,
                'ping'       => $dbCheck ? 'success' : 'failed',
                'latency_ms' => $dbLatencyMs,
            ],
            'routing'     => [
                'status'  => 'operational',
                'router'  => 'Core\\Router',
            ],
            'memory_peak' => memory_get_peak_usage(true) . ' bytes',
        ]);
    }
}
