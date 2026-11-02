<?php
/**
 * CLI Command: Database Migration Runner
 * 
 * Usage:
 *   php bin/migrate.php
 * 
 * Executes all pending SQL migration files in database/migrations/
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This script must only be run via CLI.\n";
    exit(1);
}

require_once __DIR__ . '/../core/bootstrap.php';

use Core\Database;
use Core\MigrationRunner;

echo "====================================================================\n";
echo " Employee HRIS & Leave Management System - Migration Runner\n";
echo "====================================================================\n\n";

$db = Database::getInstance()->getConnection();

// Ensure migrations tracking table exists
$db->exec("
    CREATE TABLE IF NOT EXISTS `migrations` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `migration` VARCHAR(255) NOT NULL,
        `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_migrations_migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$migrationsDir = __DIR__ . '/../database/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

$appliedCount = 0;

foreach ($files as $file) {
    $filename = basename($file);

    $stmt = $db->prepare("SELECT COUNT(*) FROM migrations WHERE migration = :migration");
    $stmt->execute(['migration' => $filename]);
    $alreadyApplied = (int)$stmt->fetchColumn() > 0;

    if ($alreadyApplied) {
        echo "  [SKIPPED] Already applied: {$filename}\n";
        continue;
    }

    echo "  [APPLYING] {$filename}... ";
    $sql = file_get_contents($file);

    try {
        if ($sql === false) {
            throw new \RuntimeException("Unable to read migration file: {$filename}");
        }

        // Runs every statement and raises if ANY of them fails, so a partially
        // failed migration is never recorded as applied.
        MigrationRunner::executeSql($db, $sql);

        $recordStmt = $db->prepare("INSERT INTO migrations (migration) VALUES (:migration)");
        $recordStmt->execute(['migration' => $filename]);

        echo "DONE ✅\n";
        $appliedCount++;
    } catch (\Throwable $e) {
        echo "FAILED ❌\n";
        echo "  Migration: {$filename} (NOT recorded as applied)\n";
        echo "  Error: " . $e->getMessage() . "\n";
        echo "  MySQL DDL commits implicitly, so statements before the failure may have taken effect.\n";
        echo "  Migrations must stay idempotent; fix the file and re-run.\n\n";
        exit(1);
    }
}

echo "\nMigration run complete. {$appliedCount} migration(s) applied.\n";
exit(0);
