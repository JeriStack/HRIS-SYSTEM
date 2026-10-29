<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\LeaveApprovalQueueService;
use App\Services\LeaveHistorySequenceService;
use App\Services\RecentActivityStackService;
use App\Services\EmployeeDirectoryService;
use App\Models\Leave;
use App\Models\ActivityLog;

class DsaWorkflowIntegrationTest extends TestCase
{
    public function test_leave_approval_queue_fifo_pipeline(): void
    {
        $mockLeave = $this->createStub(Leave::class);
        $mockLeave->method('getAllRequests')->willReturn([
            ['id' => 10, 'applied_at' => '2026-10-29 10:00:00', 'employee_name' => 'Charlie'],
            ['id' => 5,  'applied_at' => '2026-10-29 08:30:00', 'employee_name' => 'Alice'],
            ['id' => 8,  'applied_at' => '2026-10-29 09:15:00', 'employee_name' => 'Bob'],
        ]);

        $queueService = new LeaveApprovalQueueService($mockLeave);
        $queue = $queueService->buildPendingApprovalQueue();

        $this->assertSame(3, $queue->size());

        // Earliest timestamp (Alice) is first
        $head = $queueService->getNextInLine($queue);
        $this->assertSame(5, $head['id']);
        $this->assertSame('Alice', $head['employee_name']);

        // Dequeuing maintains exact FIFO order
        $first = $queue->dequeue();
        $this->assertSame(5, $first['id']);

        $second = $queue->dequeue();
        $this->assertSame(8, $second['id']);

        $third = $queue->dequeue();
        $this->assertSame(10, $third['id']);
    }

    public function test_leave_history_linked_list_chain(): void
    {
        $mockLeave = $this->createStub(Leave::class);
        $mockLeave->method('getAllRequests')->willReturn([
            ['id' => 201, 'applied_at' => '2026-06-01', 'leave_name' => 'Vacation Leave', 'number_of_days' => 3],
            ['id' => 202, 'applied_at' => '2026-08-15', 'leave_name' => 'Sick Leave', 'number_of_days' => 1],
            ['id' => 203, 'applied_at' => '2026-10-20', 'leave_name' => 'Emergency Leave', 'number_of_days' => 2],
        ]);

        $historyService = new LeaveHistorySequenceService($mockLeave);
        $chain = $historyService->buildEmployeeLeaveHistoryChain(12);

        $this->assertSame(3, $chain->size());

        // Extract sequential array from linked list
        $items = $historyService->getHistoryArray($chain);
        $this->assertCount(3, $items);
        $this->assertSame(201, $items[0]['id']);
        $this->assertSame(202, $items[1]['id']);
        $this->assertSame(203, $items[2]['id']);

        // Node find by request ID
        $found = $historyService->findLeaveRequest($chain, 202);
        $this->assertNotNull($found);
        $this->assertSame('Sick Leave', $found['leave_name']);
    }

    public function test_recent_activity_lifo_stack(): void
    {
        $mockLog = $this->createStub(ActivityLog::class);
        $mockLog->method('getAll')->willReturn([
            ['id' => 103, 'action' => 'LEAVE_APPROVED', 'description' => 'HR approved leave', 'created_at' => '2026-10-29 10:00:00'],
            ['id' => 102, 'action' => 'LEAVE_REQUESTED', 'description' => 'Applied for leave', 'created_at' => '2026-10-29 09:00:00'],
            ['id' => 101, 'action' => 'AUTH_LOGIN', 'description' => 'User signed in', 'created_at' => '2026-10-29 08:00:00'],
        ]);

        $stackService = new RecentActivityStackService($mockLog);
        $stack = $stackService->loadRecentActivitiesStack();

        $this->assertSame(3, $stack->size());

        // LIFO Top is the most recent (id 103)
        $top = $stackService->getLatestActivity($stack);
        $this->assertNotNull($top);
        $this->assertSame(103, $top['id']);
        $this->assertSame('LEAVE_APPROVED', $top['action']);

        // Extract most recent
        $popped = $stackService->extractMostRecent($stack, 2);
        $this->assertCount(2, $popped);
        $this->assertSame(103, $popped[0]['id']);
        $this->assertSame(102, $popped[1]['id']);
    }

    public function test_employee_directory_dsa_sorting_and_searching(): void
    {
        $employees = [
            ['id' => 1, 'employee_no' => 'EMP-003', 'last_name' => 'Zaragosa', 'first_name' => 'Zack'],
            ['id' => 2, 'employee_no' => 'EMP-001', 'last_name' => 'Alvarez',  'first_name' => 'Alice'],
            ['id' => 3, 'employee_no' => 'EMP-002', 'last_name' => 'Cruz',     'first_name' => 'Carlos'],
        ];

        $service = new EmployeeDirectoryService();

        // Sort ascending by last_name with Merge Sort
        $sortedRes = $service->sort($employees, 'last_name', 'asc', 'merge');
        $sortedEmployees = $sortedRes['employees'];
        $this->assertSame('Alvarez', $sortedEmployees[0]['last_name']);
        $this->assertSame('Cruz', $sortedEmployees[1]['last_name']);
        $this->assertSame('Zaragosa', $sortedEmployees[2]['last_name']);

        // Search by last_name with Binary Search
        $searchRes = $service->search($employees, 'last_name', 'Cruz', 'binary');
        $this->assertCount(1, $searchRes['employees']);
        $this->assertSame('Cruz', $searchRes['employees'][0]['last_name']);
    }
}
