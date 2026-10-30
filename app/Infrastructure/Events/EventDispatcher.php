<?php

/**
 * Lightweight In-Memory Event Dispatcher Implementation
 * HRIS-SYSTEM — Infrastructure Layer
 */

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Domain\Common\EventDispatcherInterface;

class EventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, list<callable>> */
    private array $handlers = [];

    /**
     * Register a callback handler for an event class.
     *
     * @param class-string $eventClass
     */
    public function subscribe(string $eventClass, callable $handler): void
    {
        $this->handlers[$eventClass][] = $handler;
    }

    /**
     * Dispatch an event to all registered handlers.
     * Re-throws any exception thrown by a handler explicitly.
     */
    public function dispatch(object $event): void
    {
        $class = get_class($event);

        if (empty($this->handlers[$class])) {
            return;
        }

        foreach ($this->handlers[$class] as $handler) {
            $handler($event);
        }
    }
}
