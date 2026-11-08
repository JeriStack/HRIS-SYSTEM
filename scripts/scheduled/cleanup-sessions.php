<?php

/**
 * Scheduled Task: Expired Session & Authentication Artifact Cleanup
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Runs every 15 minutes to purge expired OTPs, expired realtime tickets,
 * and stale session storage files.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && !defined('SCHEDULER_IN_PROCESS')) {
    http_response_code(403);
    echo "Forbidden: CLI access only.\n";
    exit(1);
}

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

use Core\Database;

$cleanedOtp = 0;
$cleanedTickets = 0;
$cleanedFiles = 0;

try {
    $db = Database::getInstance()->getConnection();

    // 1. Delete expired Email OTPs older than 1 hour
    $stmtOtp = $db->prepare("DELETE FROM email_otps WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmtOtp->execute();
    $cleanedOtp = $stmtOtp->rowCount();

    // 2. Delete expired Realtime Tickets older than 10 minutes (tickets expire in 30-60s)
    $stmtTicket = $db->prepare("DELETE FROM realtime_tickets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $stmtTicket->execute();
    $cleanedTickets = $stmtTicket->rowCount();

    // 3. Clean stale session files from session_save_path if writable directory
    $savePath = session_save_path();
    if ($savePath !== '' && is_dir($savePath) && is_readable($savePath)) {
        $lifetime = (int)config('config.session_lifetime', 1800);
        $threshold = time() - $lifetime;

        $files = glob($savePath . '/sess_*') ?: [];
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $threshold) {
                if (@unlink($file)) {
                    $cleanedFiles++;
                }
            }
        }
    }

    echo sprintf(
        "[%s] [cleanup-sessions] Purged %d expired OTP(s), %d stale ticket(s), %d session file(s).\n",
        date('Y-m-d H:i:s'),
        $cleanedOtp,
        $cleanedTickets,
        $cleanedFiles
    );

    return 0;
} catch (\Throwable $e) {
    error_log('[cleanup-sessions error] ' . $e->getMessage());
    echo sprintf("[%s] [cleanup-sessions ERROR] %s\n", date('Y-m-d H:i:s'), $e->getMessage());
    return 1;
}

