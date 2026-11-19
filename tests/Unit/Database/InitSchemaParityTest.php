<?php

namespace Tests\Unit\Database;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * DB-01 drift guard: the Docker first-boot SQL (docker/mysql/init/*) must stay an exact
 * copy of the canonical database/schema.sql and database/seed.sql, so a fresh volume
 * boots with the same structure the migrations produce.
 */
class InitSchemaParityTest extends TestCase
{
    private const MARKER = '-- >>> canonical content below >>>';

    /**
     * @return array<string, array{string, string}>
     */
    public static function initFileProvider(): array
    {
        return [
            'schema' => ['docker/mysql/init/01-schema.sql', 'database/schema.sql'],
            'seed'   => ['docker/mysql/init/02-seed.sql', 'database/seed.sql'],
        ];
    }

    #[DataProvider('initFileProvider')]
    public function test_init_file_matches_canonical_source(string $initPath, string $canonicalPath): void
    {
        $init = $this->read($initPath);
        $canonical = $this->read($canonicalPath);

        $markerPos = strpos($init, self::MARKER . "\n");
        $this->assertNotFalse($markerPos, "{$initPath} is missing the '" . self::MARKER . "' line.");

        $body = substr($init, $markerPos + strlen(self::MARKER) + 1);
        $this->assertSame(
            $canonical,
            $body,
            "{$initPath} has drifted from {$canonicalPath}. Edit {$canonicalPath} and regenerate {$initPath}."
        );
    }

    #[DataProvider('initFileProvider')]
    public function test_init_file_selects_the_application_database(string $initPath, string $canonicalPath): void
    {
        $header = strstr($this->read($initPath), self::MARKER, true);

        $this->assertIsString($header, "{$initPath} is missing the marker line.");
        $this->assertStringContainsString('USE `hris_db`;', $header);
        $this->assertStringNotContainsString(
            'USE `hris_db`;',
            $this->read($canonicalPath),
            "{$canonicalPath} must stay database-agnostic so it can also load hris_test."
        );
    }

    public function test_every_migration_table_is_in_the_canonical_schema(): void
    {
        $schema = $this->read('database/schema.sql');
        $migrationsDir = $this->path('database/migrations');
        $files = glob($migrationsDir . '/*.sql') ?: [];
        $this->assertNotEmpty($files, 'No migration files found.');

        foreach ($files as $file) {
            $migrationSql = $this->read('database/migrations/' . basename($file));
            preg_match_all('/CREATE TABLE IF NOT EXISTS `([a-z_]+)`/i', $migrationSql, $matches);
            foreach ($matches[1] as $table) {
                $this->assertStringContainsString(
                    "CREATE TABLE IF NOT EXISTS `{$table}`",
                    $schema,
                    "Table `{$table}` from " . basename($file) . ' is missing from database/schema.sql.'
                );
            }
        }
    }

    public function test_notifications_entity_event_index_is_in_the_canonical_schema(): void
    {
        $this->assertStringContainsString(
            'INDEX `idx_notifications_entity_event` (`entity_id`, `event_type`)',
            $this->read('database/schema.sql')
        );
    }

    public function test_mysql_exporter_init_script_follows_least_privilege(): void
    {
        $sql = $this->read('docker/mysql/init/03-create-exporter.sql');

        $this->assertStringNotContainsStringIgnoringCase(
            'SELECT ON *.*',
            $sql,
            'Exporter account must not have global SELECT ON *.* access to HRIS tables.'
        );
        $this->assertStringContainsStringIgnoringCase(
            'GRANT SELECT ON performance_schema.*',
            $sql
        );
    }

    public function test_database_config_enforces_safe_pdo_options(): void
    {
        $config = require $this->path('config/database.php');

        $this->assertIsArray($config['options'] ?? null);
        $this->assertSame(\PDO::ERRMODE_EXCEPTION, $config['options'][\PDO::ATTR_ERRMODE] ?? null);
        $this->assertSame(\PDO::FETCH_ASSOC, $config['options'][\PDO::ATTR_DEFAULT_FETCH_MODE] ?? null);
        $this->assertFalse($config['options'][\PDO::ATTR_EMULATE_PREPARES] ?? true);
        $this->assertGreaterThan(0, (int)($config['options'][\PDO::ATTR_TIMEOUT] ?? 0));
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->path($relativePath));
        $this->assertNotFalse($contents, "Unable to read {$relativePath}.");

        // Compare content, not the platform's checkout line endings.
        return str_replace("\r\n", "\n", $contents);
    }

    private function path(string $relativePath): string
    {
        return dirname(__DIR__, 3) . '/' . $relativePath;
    }
}
