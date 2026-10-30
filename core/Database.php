<?php
/**
 * Core Database Manager (PDO Singleton)
 * Employee HRIS & Leave Management System
 */

namespace Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    private bool $connected = false;
    private ?string $lastError = null;

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct()
    {
        $this->connect();
    }

    /**
     * Prevent cloning of the singleton instance.
     */
    private function __clone() {}

    /**
     * Prevent unserialization of the singleton instance.
     */
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize singleton instance of Database.");
    }

    /**
     * Get the globally shared Database instance.
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish the PDO connection using configuration parameters.
     */
    private function connect(): void
    {
        $config = require __DIR__ . '/../config/database.php';

        $driver   = getenv('DB_DRIVER')   ?: ($config['driver'] ?? 'mysql');
        $host     = getenv('DB_HOST')     ?: ($config['host'] ?? '127.0.0.1');
        $port     = getenv('DB_PORT')     ?: ($config['port'] ?? 3306);
        $database = getenv('DB_DATABASE') ?: ($config['database'] ?? 'hris_db');
        $username = getenv('DB_USERNAME') ?: ($config['username'] ?? 'root');
        $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : ($config['password'] ?? '');
        $charset  = $config['charset'] ?? 'utf8mb4';

        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";

        try {
            $this->connection = new PDO($dsn, $username, $password, $options);
            $this->connected = true;
            $this->lastError = null;
        } catch (PDOException $e) {
            $this->connection = null;
            $this->connected = false;
            $this->lastError = $e->getMessage();

            // Log detailed error internally without exposing credentials to end users
            $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
            if ($appEnv !== 'testing') {
                error_log("[Database Connection Error] " . $e->getMessage());
            }
        }
    }

    /**
     * Retrieve the active PDO connection instance.
     * Throws an exception if connection is unestablished when strictly requested.
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null || !$this->connected) {
            throw new RuntimeException("Database connection is not available: " . ($this->lastError ?? 'Unknown error'));
        }
        return $this->connection;
    }

    /**
     * Check whether a valid PDO connection is active.
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->connection !== null;
    }

    /**
     * Get the last connection error message if any (for controlled diagnostics).
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Reset/disconnect the connection (primarily for testing).
     */
    public static function reset(): void
    {
        if (self::$instance !== null) {
            self::$instance->connection = null;
            self::$instance->connected = false;
            self::$instance = null;
        }
    }
}
