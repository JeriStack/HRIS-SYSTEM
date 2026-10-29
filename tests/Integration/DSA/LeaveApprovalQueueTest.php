<?php

namespace Tests\Integration\DSA;

use App\Models\Leave;
use App\Services\LeaveApprovalQueueService;
use PHPUnit\Framework\TestCase;

class LeaveApprovalQueueTest extends TestCase
{
    public function test_build_queue_enforces_fifo_chronological_ordering(): void
    {
        $mockLeave = $this->createStub(Leave::class);
        $mockLeave->method('getAllRequests')->willReturn([
            ['id' => 3, 'employee_name' => 'Charlie', 'applied_at' => '2026-10-29 11:00:00', 'status' => 'Pending'],
            ['id' => 1, 'employee_name' => 'Alice', 'applied_at' => '2026-10-29 08:00:00', 'status' => 'Pending'],
            ['id' => 2, 'employee_name' => 'Bob', 'applied_at' => '2026-10-29 09:30:00', 'status' => 'Pending'],
        ]);

        $service = new LeaveApprovalQueueService($mockLeave);
        $queue = $service->buildPendingApprovalQueue();

        $this->assertSame(3, $queue->size());

        // Alice applied earliest -> must be front of the queue
        $next = $service->getNextInLine($queue);
        $this->assertNotNull($next);
        $this->assertSame(1, $next['id']);
        $this->assertSame('Alice', $next['employee_name']);

        // Dequeue one by one and check FIFO sequence
        $first = $queue->dequeue();
        $this->assertSame(1, $first['id']);

        $second = $queue->dequeue();
        $this->assertSame(2, $second['id']);

        $third = $queue->dequeue();
        $this->assertSame(3, $third['id']);

        $this->assertTrue($queue->isEmpty());
    }

    public function test_process_next_approval_delegates_to_transactional_model(): void
    {
        $mockLeave = $this->createMock(Leave::class);
        $mockLeave->method('getAllRequests')->willReturn([
            ['id' => 45, 'employee_name' => 'John Doe', 'applied_at' => '2026-10-29 08:15:00', 'status' => 'Pending'],
        ]);

        $mockLeave->expects($this->once())
            ->method('approveRequest')
            ->with(45, 99)
            ->willReturn([
                'status'  => 'approved',
                'message' => 'Leave approved successfully.',
            ]);

        $service = new LeaveApprovalQueueService($mockLeave);
        $queue = $service->buildPendingApprovalQueue();

        $result = $service->processNextApproval($queue, 99);

        $this->assertTrue($result['success']);
        $this->assertSame('approved', $result['status']);
        $this->assertSame(45, $result['request']['id']);
        $this->assertTrue($queue->isEmpty());
    }

    public function test_process_empty_queue_returns_safe_error(): void
    {
        $mockLeave = $this->createStub(Leave::class);
        $service = new LeaveApprovalQueueService($mockLeave);
        $emptyQueue = new \App\Dsa\Structures\Queue();

        $result = $service->processNextApproval($emptyQueue, 1);
        $this->assertFalse($result['success']);
        $this->assertSame('empty_queue', $result['status']);
    }
}
