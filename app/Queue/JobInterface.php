<?php

/**
 * Queue Job Handler Interface
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * All asynchronous job handlers must implement this interface.
 */

declare(strict_types=1);

namespace App\Queue;

interface JobInterface
{
    /**
     * Execute the job logic with the provided sanitized payload.
     *
     * @param array<string, mixed> $payload
     * @throws \Throwable If job processing fails and requires a retry
     */
    public function handle(array $payload): void;
}
