<?php

/**
 * In-Memory Transaction Manager Fake for Testing
 */

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Common\TransactionManagerInterface;

class InMemoryTransactionManager implements TransactionManagerInterface
{
    private bool $wasExecuted = false;

    public function transactional(callable $operation)
    {
        $this->wasExecuted = true;
        return $operation();
    }

    public function wasExecuted(): bool
    {
        return $this->wasExecuted;
    }
}
