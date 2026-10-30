<?php

/**
 * Outbox Event Serializer & Deserializer
 * HRIS-SYSTEM — Infrastructure Layer (Phase 1: Transactional Outbox)
 */

declare(strict_types=1);

namespace App\Infrastructure\Outbox;

use App\Domain\Common\DomainEventInterface;
use App\Domain\Leave\Events\LeaveApprovedEvent;
use App\Domain\Leave\Events\LeaveRejectedEvent;
use DateTimeImmutable;
use InvalidArgumentException;

final class OutboxEventSerializer
{
    /**
     * Serialize a domain event into a minimal, PII-free payload map.
     *
     * @return array<string, mixed>
     */
    public static function serialize(DomainEventInterface $event): array
    {
        if ($event instanceof LeaveApprovedEvent) {
            return [
                'requestId'      => $event->requestId,
                'employeeId'     => $event->employeeId,
                'leaveTypeId'    => $event->leaveTypeId,
                'numberOfDays'   => $event->numberOfDays,
                'approverUserId' => $event->approverUserId,
                'occurredAt'     => $event->occurredAt()->format(DateTimeImmutable::ATOM),
            ];
        }

        if ($event instanceof LeaveRejectedEvent) {
            return [
                'requestId'       => $event->requestId,
                'employeeId'      => $event->employeeId,
                'leaveTypeId'     => $event->leaveTypeId,
                'numberOfDays'    => $event->numberOfDays,
                'approverUserId'  => $event->approverUserId,
                'rejectionReason' => $event->rejectionReason,
                'occurredAt'      => $event->occurredAt()->format(DateTimeImmutable::ATOM),
            ];
        }

        // Generic fallback for any future DomainEventInterface implementation
        $props = get_object_vars($event);
        $payload = [];
        foreach ($props as $key => $value) {
            if ($value instanceof DateTimeImmutable) {
                $payload[$key] = $value->format(DateTimeImmutable::ATOM);
            } else {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * Reconstitute a domain event instance from an event type and stored payload.
     *
     * @param array<string, mixed> $payload
     */
    public static function deserialize(string $eventType, array $payload): DomainEventInterface
    {
        $occurredAt = isset($payload['occurredAt']) && is_string($payload['occurredAt'])
            ? new DateTimeImmutable($payload['occurredAt'])
            : new DateTimeImmutable();

        if ($eventType === LeaveApprovedEvent::class || $eventType === 'LeaveApprovedEvent' || $eventType === 'leave.approved') {
            return new LeaveApprovedEvent(
                (int)($payload['requestId'] ?? 0),
                (int)($payload['employeeId'] ?? 0),
                (int)($payload['leaveTypeId'] ?? 0),
                (int)($payload['numberOfDays'] ?? 0),
                (int)($payload['approverUserId'] ?? 0),
                $occurredAt
            );
        }

        if ($eventType === LeaveRejectedEvent::class || $eventType === 'LeaveRejectedEvent' || $eventType === 'leave.rejected') {
            return new LeaveRejectedEvent(
                (int)($payload['requestId'] ?? 0),
                (int)($payload['employeeId'] ?? 0),
                (int)($payload['leaveTypeId'] ?? 0),
                (int)($payload['numberOfDays'] ?? 0),
                (int)($payload['approverUserId'] ?? 0),
                (string)($payload['rejectionReason'] ?? ''),
                $occurredAt
            );
        }

        throw new InvalidArgumentException("Unsupported outbox event type: {$eventType}");
    }
}
