<?php

/**
 * Report Export Queue Job Handler
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Prepares and saves asynchronous system reports to the temporary storage path.
 */

declare(strict_types=1);

namespace App\Queue\Handlers;

use App\Models\Notification;
use App\Queue\JobInterface;
use Core\AuditService;
use Core\Database;
use InvalidArgumentException;
use PDO;

class ReportExportHandler implements JobInterface
{
    private PDO $db;
    private Notification $notifications;

    public function __construct(?PDO $db = null, ?Notification $notifications = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->notifications = $notifications ?? new Notification();
    }

    public function handle(array $payload): void
    {
        $reportType = trim((string)($payload['report_type'] ?? 'leave_summary'));
        $requestedBy = isset($payload['requested_by']) ? (int)$payload['requested_by'] : 0;
        $filters = (array)($payload['filters'] ?? []);

        $storageDir = dirname(__DIR__, 3) . '/storage/exports';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $filename = sprintf('%s_%s_%s.csv', $reportType, date('Ymd_His'), bin2hex(random_bytes(4)));
        $filePath = $storageDir . '/' . $filename;

        $fp = fopen($filePath, 'w');
        if ($fp === false) {
            throw new InvalidArgumentException("Unable to create export file at {$filePath}");
        }

        if ($reportType === 'leave_summary') {
            fputcsv($fp, ['Request ID', 'Employee No', 'Employee Name', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Status']);

            $stmt = $this->db->query("
                SELECT lr.id, e.employee_no, CONCAT(e.first_name, ' ', e.last_name) AS full_name,
                       d.department_name, lt.leave_name, lr.start_date, lr.end_date, lr.number_of_days, lr.status
                FROM leave_requests lr
                JOIN employees e ON e.id = lr.employee_id
                LEFT JOIN departments d ON d.id = e.department_id
                JOIN leave_types lt ON lt.id = lr.leave_type_id
                ORDER BY lr.id DESC
            ");

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($fp, array_map([AuditService::class, 'sanitizeCsvField'], [
                    (string)$row['id'],
                    (string)$row['employee_no'],
                    (string)$row['full_name'],
                    (string)($row['department_name'] ?? 'N/A'),
                    (string)$row['leave_name'],
                    (string)$row['start_date'],
                    (string)$row['end_date'],
                    (string)$row['number_of_days'],
                    (string)$row['status'],
                ]));
            }
        } else {
            fputcsv($fp, ['Notice', 'Generated report placeholder']);
        }

        fclose($fp);

        // Notify requesting administrator that the report is ready
        if ($requestedBy > 0) {
            $this->notifications->create(
                $requestedBy,
                'Report Export Ready',
                "Your requested '{$reportType}' export has completed successfully ({$filename}).",
                'success',
                'report.ready'
            );
        }
    }
}
