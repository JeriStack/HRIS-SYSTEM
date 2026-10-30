<?php

/**
 * EventDispatcher Unit Test
 * TDD Workstream 4 - Lightweight Event Dispatcher
 */

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Events;

use PHPUnit\Framework\TestCase;
use App\Infrastructure\Events\EventDispatcher;
use App\Domain\Leave\Events\LeaveApprovedEvent;
use App\Domain\Leave\Events\LeaveRejectedEvent;
use DateTimeImmutable;
use RuntimeException;

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new EventDispatcher();
    }

    public function test_can_subscribe_and_dispatch_event_to_handler(): void
    {
        $received = null;
        $this->dispatcher->subscribe(LeaveApprovedEvent::class, function (LeaveApprovedEvent $event) use (&$received) {
            $received = $event;
        });

        $event = new LeaveApprovedEvent(
            requestId: 101,
            employeeId: 5,
            leaveTypeId: 1,
            numberOfDays: 3,
            approverUserId: 99,
            occurredAt: new DateTimeImmutable()
        );

        $this->dispatcher->dispatch($event);

        $this->assertSame($event, $received);
    }

    public function test_supports_multiple_handlers_for_same_event(): void
    {
        $calls = [];
        $this->dispatcher->subscribe(LeaveApprovedEvent::class, function () use (&$calls) {
            $calls[] = 'audit_handler';
        });
        $this->dispatcher->subscribe(LeaveApprovedEvent::class, function () use (&$calls) {
            $calls[] = 'notification_handler';
        });

        $event = new LeaveApprovedEvent(101, 5, 1, 3, 99, new DateTimeImmutable());
        $this->dispatcher->dispatch($event);

        $this->assertSame(['audit_handler', 'notification_handler'], $calls);
    }

    public function test_dispatching_unhandled_event_safely_succeeds(): void
    {
        $event = new LeaveRejectedEvent(102, 5, 1, 3, 99, 'Reason', new DateTimeImmutable());

        // No handlers registered for LeaveRejectedEvent
        $this->dispatcher->dispatch($event);
        $this->assertTrue(true);
    }

    public function test_handler_failure_is_not_silently_swallowed(): void
    {
        $this->dispatcher->subscribe(LeaveApprovedEvent::class, function () {
            throw new RuntimeException('Audit transport failure');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit transport failure');

        $event = new LeaveApprovedEvent(101, 5, 1, 3, 99, new DateTimeImmutable());
        $this->dispatcher->dispatch($event);
    }
}
