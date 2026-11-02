<?php

/**
 * Leave Event Handlers Unit Test
 * TDD Workstream 6 - Audit & Notification Handlers
 */

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Events;

use PHPUnit\Framework\TestCase;
use App\Infrastructure\Events\Handlers\LeaveAuditHandler;
use App\Infrastructure\Events\Handlers\LeaveNotificationHandler;
use App\Domain\Leave\Events\LeaveApprovedEvent;
use App\Domain\Leave\Events\LeaveRejectedEvent;
use App\Models\ActivityLog;
use App\Services\LeaveNotificationService;
use DateTimeImmutable;

class LeaveHandlersTest extends TestCase
{
    public function test_audit_handler_logs_leave_approved_event(): void
    {
        $mockActivityLog = $this->createMock(ActivityLog::class);
        $mockActivityLog->expects($this->once())
            ->method('log')
            ->with(
                99,
                'LEAVE_APPROVED',
                'Approved leave request #101',
                'LeaveRequest',
                '101'
            )
            ->willReturn(true);

        $handler = new LeaveAuditHandler($mockActivityLog);

        $event = new LeaveApprovedEvent(
            requestId: 101,
            employeeId: 5,
            leaveTypeId: 1,
            numberOfDays: 3,
            approverUserId: 99,
            occurredAt: new DateTimeImmutable()
        );

        $handler->handleApproved($event);
    }

    public function test_audit_handler_logs_leave_rejected_event(): void
    {
        $mockActivityLog = $this->createMock(ActivityLog::class);
        $mockActivityLog->expects($this->once())
            ->method('log')
            ->with(
                99,
                'LEAVE_REJECTED',
                'Rejected leave request #102. Reason: Staffing shortage',
                'LeaveRequest',
                '102'
            )
            ->willReturn(true);

        $handler = new LeaveAuditHandler($mockActivityLog);

        $event = new LeaveRejectedEvent(
            requestId: 102,
            employeeId: 5,
            leaveTypeId: 1,
            numberOfDays: 3,
            approverUserId: 99,
            rejectionReason: 'Staffing shortage',
            occurredAt: new DateTimeImmutable()
        );

        $handler->handleRejected($event);
    }

    public function test_notification_handler_delivers_approval_notification(): void
    {
        $mockNotificationService = $this->createMock(LeaveNotificationService::class);
        $mockNotificationService->expects($this->once())
            ->method('leaveDecided')
            ->with(
                ['id' => 101, 'employee_id' => 5],
                'Approved',
                $this->stringContains('approved')
            )
            ->willReturn(true);

        $handler = new LeaveNotificationHandler($mockNotificationService);

        $event = new LeaveApprovedEvent(
            requestId: 101,
            employeeId: 5,
            leaveTypeId: 1,
            numberOfDays: 3,
            approverUserId: 99,
            occurredAt: new DateTimeImmutable()
        );

        $handler->handleApproved($event);
    }

    public function test_notification_handler_delivers_rejection_notification(): void
    {
        $mockNotificationService = $this->createMock(LeaveNotificationService::class);
        $mockNotificationService->expects($this->once())
            ->method('leaveDecided')
            ->with(
                ['id' => 102, 'employee_id' => 5],
                'Rejected',
                $this->stringContains('Staffing shortage')
            )
            ->willReturn(true);

        $handler = new LeaveNotificationHandler($mockNotificationService);

        $event = new LeaveRejectedEvent(
            requestId: 102,
            employeeId: 5,
            leaveTypeId: 1,
            numberOfDays: 3,
            approverUserId: 99,
            rejectionReason: 'Staffing shortage',
            occurredAt: new DateTimeImmutable()
        );

        $handler->handleRejected($event);
    }
}
