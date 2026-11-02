<?php

/**
 * Core Migration SQL Executor
 * Employee HRIS & Leave Management System
 *
 * Runs one migration file's SQL as a multi-statement batch and makes MySQL report
 * the outcome of EVERY statement, not only the first.
 *
 * Why this exists: PDO::exec() on a multi-statement string only reliably surfaces an
 * error from the first statement. A failure in a later statement can be swallowed, and
 * any unread result sets leave the connection unusable ("2014 Cannot execute queries
 * while there are pending result sets"). Draining every rowset with nextRowset() raises
 * the real error for whichever statement failed and leaves the connection clean.
 *
 * Statements are parsed by the MySQL server, so semicolons inside string literals and
 * comments are handled correctly; no client-side SQL splitting is performed.
 * DELIMITER is a mysql-client command and is not supported in migration files.
 */

namespace Core;

use PDO;
use PDOStatement;
use RuntimeException;

class MigrationRunner
{
    /**
     * Execute a migration's SQL and throw if any statement in it fails.
     *
     * @throws \PDOException When MySQL rejects any statement in the batch
     * @throws RuntimeException When the SQL is empty or the driver returns no statement
     */
    public static function executeSql(PDO $pdo, string $sql): void
    {
        if (trim($sql) === '') {
            throw new RuntimeException('Migration file is empty.');
        }

        $previousEmulation = (bool)$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $previousErrorMode = $pdo->getAttribute(PDO::ATTR_ERRMODE);

        // Multi-statement batches need the text protocol (emulated prepares);
        // exceptions are required so a failing rowset aborts the migration.
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $statement = null;
        try {
            $statement = $pdo->query($sql);
            if (!$statement instanceof PDOStatement) {
                throw new RuntimeException('The database driver did not return a statement for the migration.');
            }

            do {
                if ($statement->columnCount() > 0) {
                    $statement->fetchAll();
                }
            } while ($statement->nextRowset());
        } finally {
            if ($statement instanceof PDOStatement) {
                try {
                    $statement->closeCursor();
                } catch (\Throwable) {
                    // The original statement error, if any, is the one worth reporting.
                }
            }
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $previousEmulation);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, $previousErrorMode);
        }
    }
}
