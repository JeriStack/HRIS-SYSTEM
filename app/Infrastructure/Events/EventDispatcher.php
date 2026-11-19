<?php

/**
 * Lightweight In-Memory Event Dispatcher Implementation
 * HRIS-SYSTEM — Infrastructure Layer
 */

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Domain\Common\EventDispatcherInterface;
use Throwable;

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
     * Isolates per-handler failures so every subscribed handler still runs,
     * then re-throws the first exception encountered so failures are never
     * silently swallowed.
     */
    public function dispatch(object $event): void
    {
        $class = get_class($event);

        if (empty($this->handlers[$class])) {
            return;
        }

        $firstFailure = null;

        foreach ($this->handlers[$class] as $handler) {
            try {
                $handler($event);
            } catch (Throwable $e) {
                if ($firstFailure === null) {
                    $firstFailure = $e;
                } else {
                    error_log(sprintf(
                        '[EventDispatcher::dispatch] Secondary handler failure for %s: %s',
                        $class,
                        $e->getMessage()
                    ));
                }
            }
        }

        if ($firstFailure !== null) {
            throw $firstFailure;
        }
    }
}
