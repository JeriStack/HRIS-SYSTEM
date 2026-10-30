<?php

/**
 * PDO Transaction Manager Implementation
 * HRIS-SYSTEM — Infrastructure Layer
 */

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Common\TransactionManagerInterface;
use PDO;
use Throwable;

class PdoTransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function transactional(callable $operation)
    {
        if ($this->pdo->inTransaction()) {
            return $operation();
        }

        $this->pdo->beginTransaction();

        try {
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
