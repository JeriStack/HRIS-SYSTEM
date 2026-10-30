<?php

/**
 * Transaction Manager Interface
 * HRIS-SYSTEM — Clean Architecture / DDD
 */

declare(strict_types=1);

namespace App\Domain\Common;

interface TransactionManagerInterface
{
    /**
     * Executes a callable within an atomic database transaction.
     * Automatically commits on successful return or rolls back and rethrows on Throwable.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation);
}
