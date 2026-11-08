<?php

/**
 * Outbox Relay Queue Job Handler
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Bridges asynchronous domain outbox events to registered event handlers.
 */

declare(strict_types=1);

namespace App\Queue\Handlers;

use App\Infrastructure\Events\EventBootstrap;
use App\Infrastructure\Outbox\OutboxEventSerializer;
use App\Queue\JobInterface;
use InvalidArgumentException;

class OutboxRelayHandler implements JobInterface
{
    public function handle(array $payload): void
    {
        $eventType = trim((string)($payload['event_type'] ?? ''));
        $eventData = (array)($payload['event_data'] ?? []);

        if ($eventType === '') {
            throw new InvalidArgumentException("OutboxRelayHandler: Missing 'event_type'.");
        }

        // Reconstitute domain event contract
        $domainEvent = OutboxEventSerializer::deserialize($eventType, $eventData);

        // Dispatch through domain event dispatcher
        $dispatcher = EventBootstrap::createConfiguredDispatcher();
        $dispatcher->dispatch($domainEvent);
    }
}
