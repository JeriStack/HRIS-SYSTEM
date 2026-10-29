<?php

namespace Tests\Integration\DSA;

use App\Models\Leave;
use App\Services\LeaveHistorySequenceService;
use PHPUnit\Framework\TestCase;

class LeaveHistoryLinkedListTest extends TestCase
{
    public function test_build_employee_leave_history_chain(): void
    {
        $mockLeave = $this->createMock(Leave::class);
        $mockLeave->method('getAllRequests')
            ->with(null, null, 10)
            ->willReturn([
                ['id' => 102, 'employee_id' => 10, 'applied_at' => '2026-06-01', 'leave_type_name' => 'Sick Leave'],
                ['id' => 101, 'employee_id' => 10, 'applied_at' => '2026-01-15', 'leave_type_name' => 'Vacation Leave'],
                ['id' => 103, 'employee_id' => 10, 'applied_at' => '2026-09-20', 'leave_type_name' => 'Emergency Leave'],
            ]);

        $service = new LeaveHistorySequenceService($mockLeave);
        $list = $service->buildEmployeeLeaveHistoryChain(10);

        $this->assertSame(3, $list->size());

        // Chronological order: 101 (Jan), 102 (Jun), 103 (Sep)
        $historyArray = $service->getHistoryArray($list);
        $this->assertSame([101, 102, 103], array_column($historyArray, 'id'));

        // Node index access
        $firstNode = $list->getAt(0);
        $this->assertSame(101, $firstNode['id']);
        $this->assertSame('Vacation Leave', $firstNode['leave_type_name']);

        // Find specific record by ID
        $found = $service->findLeaveRequest($list, 102);
        $this->assertNotNull($found);
        $this->assertSame('Sick Leave', $found['leave_type_name']);

        // Missing record
        $missing = $service->findLeaveRequest($list, 999);
        $this->assertNull($missing);
    }
}
