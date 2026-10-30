<?php

/**
 * Event Dispatcher Interface
 * HRIS-SYSTEM — Domain / Application Common Contract
 */

declare(strict_types=1);

namespace App\Domain\Common;

interface EventDispatcherInterface
{
    /**
     * Dispatch an event to all subscribed handlers.
     */
    public function dispatch(object $event): void;

    /**
     * Register a callback listener for a specific event class name.
     *
     * @param class-string $eventClass
     */
    public function subscribe(string $eventClass, callable $handler): void;
}
