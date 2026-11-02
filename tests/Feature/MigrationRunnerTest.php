<?php

namespace Tests\Feature;

use Core\Database;
use Core\MigrationRunner;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * DB-02: multi-statement migration execution (isolated hris_test database only).
 *
 * Proves that a failure in ANY statement of a migration file is raised (so bin/migrate.php
 * never records a partially failed migration as applied), that server-side parsing handles
 * semicolons inside strings and comments, and that the shared connection stays usable.
 * Only session-scoped TEMPORARY tables are created, so no persistent schema changes occur.
 */
class MigrationRunnerTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        if (!Database::getInstance()->isConnected()) {
            $this->markTestSkipped('Database connection is not available.');
        }
        $this->pdo = Database::getInstance()->getConnection();
    }

    protected function tearDown(): void
    {
        $this->pdo?->exec('DROP TEMPORARY TABLE IF EXISTS g4_runner_probe');
    }

    public function test_failure_in_a_later_statement_is_raised(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/g4_runner_missing_table/');

        MigrationRunner::executeSql($this->pdo, "SELECT 1; SELECT * FROM g4_runner_missing_table");
    }

    public function test_failure_after_a_variable_assignment_is_raised(): void
    {
        $this->expectException(PDOException::class);

        MigrationRunner::executeSql($this->pdo, "SET @g4_probe = 1; SELECT * FROM g4_runner_missing_table");
    }

    public function test_failure_in_the_first_statement_is_raised(): void
    {
        $this->expectException(PDOException::class);

        MigrationRunner::executeSql($this->pdo, "SELECT * FROM g4_runner_missing_table; SELECT 1");
    }

    public function test_semicolons_in_strings_and_comments_are_not_split(): void
    {
        $sql = "-- comment; with a semicolon\n"
            . "CREATE TEMPORARY TABLE g4_runner_probe (val VARCHAR(20) NOT NULL);\n"
            . "/* block; comment */ INSERT INTO g4_runner_probe (val) VALUES ('a;b'), ('c\\'; d');\n";

        MigrationRunner::executeSql($this->pdo, $sql);

        $values = $this->pdo->query("SELECT val FROM g4_runner_probe ORDER BY val")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['a;b', "c'; d"], $values);
    }

    public function test_guarded_prepared_statement_pattern_used_by_migrations_runs(): void
    {
        $sql = "CREATE TEMPORARY TABLE g4_runner_probe (id INT);\n"
            . "SET @g4_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS\n"
            . "    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'g4_runner_probe_absent');\n"
            . "SET @g4_sql = IF(@g4_exists = 0, 'SELECT \"column absent\"', 'SELECT \"column present\"');\n"
            . "PREPARE g4_stmt FROM @g4_sql;\n"
            . "EXECUTE g4_stmt;\n"
            . "DEALLOCATE PREPARE g4_stmt;\n";

        MigrationRunner::executeSql($this->pdo, $sql);

        $this->assertSame(1, (int)$this->pdo->query('SELECT 1')->fetchColumn());
    }

    public function test_connection_is_usable_after_a_failed_migration(): void
    {
        try {
            MigrationRunner::executeSql($this->pdo, "SELECT 1; SELECT 2; SELECT * FROM g4_runner_missing_table");
            $this->fail('Expected the failing statement to raise.');
        } catch (PDOException) {
            // Expected: the third statement fails.
        }

        $stmt = $this->pdo->prepare('SELECT :v AS v');
        $stmt->execute(['v' => 42]);
        $this->assertSame(42, (int)$stmt->fetchColumn());
    }

    public function test_connection_attributes_are_restored(): void
    {
        $emulationBefore = (bool)$this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $errorModeBefore = $this->pdo->getAttribute(PDO::ATTR_ERRMODE);

        MigrationRunner::executeSql($this->pdo, 'SELECT 1; SELECT 2');

        try {
            MigrationRunner::executeSql($this->pdo, 'SELECT * FROM g4_runner_missing_table');
        } catch (PDOException) {
            // Attributes must also be restored on the failure path.
        }

        $this->assertSame($emulationBefore, (bool)$this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        $this->assertSame($errorModeBefore, $this->pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function test_empty_migration_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        MigrationRunner::executeSql($this->pdo, "  \n\t ");
    }
}
