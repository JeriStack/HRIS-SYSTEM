<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Core\Database;
use PDO;
use RuntimeException;

class DatabaseConnectionTest extends TestCase
{
    public function test_database_implements_singleton_pattern(): void
    {
        $instance1 = Database::getInstance();
        $instance2 = Database::getInstance();

        $this->assertInstanceOf(Database::class, $instance1);
        $this->assertSame($instance1, $instance2, 'Database::getInstance() must return the same singleton instance.');
    }

    public function test_connection_lifecycle_handling(): void
    {
        $db = Database::getInstance();

        if ($db->isConnected()) {
            $pdo = $db->getConnection();
            $this->assertInstanceOf(PDO::class, $pdo);

            // Execute connectivity verification ping
            $stmt = $pdo->query("SELECT 1 AS ping");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->assertSame(1, (int)$result['ping']);
        } else {
            // When database is unreachable, it should safely report diagnostic error
            $this->assertNotNull($db->getLastError());
            $this->expectException(RuntimeException::class);
            $db->getConnection();
        }
    }

    public function test_database_reset_clears_singleton(): void
    {
        $instanceBefore = Database::getInstance();
        Database::reset();
        $instanceAfter = Database::getInstance();

        $this->assertInstanceOf(Database::class, $instanceAfter);
        $this->assertNotSame($instanceBefore, $instanceAfter, 'Database::reset() must create a fresh instance on next call.');
    }

    public function test_schema_tables_exist_when_connected(): void
    {
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database is not connected in this environment.');
        }

        $pdo = $db->getConnection();
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $expectedTables = ['departments', 'positions', 'employees', 'users', 'leave_types', 'leave_requests', 'leave_balances'];
        foreach ($expectedTables as $table) {
            $this->assertContains($table, $tables, "Expected table '{$table}' to exist in database.");
        }
    }

    public function test_database_queries_seed_data_successfully(): void
    {
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            $this->markTestSkipped('Database is not connected in this environment.');
        }

        $pdo = $db->getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments");
        $count = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $this->assertGreaterThan(0, $count, "Seed data should populate departments table.");
    }
}
