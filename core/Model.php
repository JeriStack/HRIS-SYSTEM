<?php
/**
 * Core Base Model
 * Employee HRIS & Leave Management System
 */

namespace Core;

use PDO;
use PDOStatement;
use RuntimeException;

abstract class Model
{
    protected ?PDO $db = null;

    /**
     * Initialize the base model with the shared PDO connection.
     */
    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Execute a parameterized SQL query and return the PDOStatement.
     */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        if ($this->db === null) {
            throw new RuntimeException("Database connection is not initialized.");
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Execute a parameterized query and fetch all matching records.
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a parameterized query and fetch a single record.
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Execute a parameterized SQL statement (INSERT, UPDATE, DELETE).
     */
    protected function execute(string $sql, array $params = []): bool
    {
        if ($this->db === null) {
            throw new RuntimeException("Database connection is not initialized.");
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Retrieve the last inserted primary key ID.
     */
    protected function lastInsertId(): string
    {
        if ($this->db === null) {
            throw new RuntimeException("Database connection is not initialized.");
        }
        return (string)$this->db->lastInsertId();
    }

    /**
     * Begin an ACID database transaction.
     */
    protected function beginTransaction(): bool
    {
        if ($this->db === null) {
            throw new RuntimeException("Database connection is not initialized.");
        }
        return $this->db->beginTransaction();
    }

    /**
     * Commit the active transaction.
     */
    protected function commit(): bool
    {
        if ($this->db === null) {
            throw new RuntimeException("Database connection is not initialized.");
        }
        return $this->db->commit();
    }

    /**
     * Rollback the active transaction.
     */
    protected function rollBack(): bool
    {
        if ($this->db === null) {
            throw new RuntimeException("Database connection is not initialized.");
        }
        return $this->db->rollBack();
    }
}
