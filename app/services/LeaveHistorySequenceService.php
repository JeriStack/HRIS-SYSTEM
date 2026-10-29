<?php
/**
 * Leave History Sequence Service
 * Integrates custom Singly Linked List with Employee Leave History Chains
 * Employee HRIS & Leave Management System — Phase 11
 */

namespace App\Services;

use App\Dsa\Structures\LinkedList;
use App\Models\Leave;

class LeaveHistorySequenceService
{
    private Leave $leaveModel;

    public function __construct(?Leave $model = null)
    {
        $this->leaveModel = $model ?? new Leave();
    }

    /**
     * Load an employee's leave records into a custom Linked List in chronological sequence.
     *
     * @param int $employeeId
     * @param int $limit
     * @return LinkedList
     */
    public function buildEmployeeLeaveHistoryChain(int $employeeId, int $limit = 50): LinkedList
    {
        $records = $this->leaveModel->getAllRequests(null, null, $employeeId);

        // Sort chronologically ascending (oldest first to newest at tail)
        usort($records, function (array $a, array $b): int {
            $tA = strtotime((string)($a['applied_at'] ?? $a['created_at'] ?? '0'));
            $tB = strtotime((string)($b['applied_at'] ?? $b['created_at'] ?? '0'));
            return $tA <=> $tB;
        });

        if ($limit > 0 && count($records) > $limit) {
            $records = array_slice($records, -$limit);
        }

        $list = new LinkedList();
        foreach ($records as $record) {
            $list->append($record);
        }

        return $list;
    }

    /**
     * Locate a specific leave record within the linked list by request ID.
     * O(n) traversal.
     *
     * @return array<string, mixed>|null
     */
    public function findLeaveRequest(LinkedList $list, int $requestId): ?array
    {
        $result = $list->find($requestId);
        return is_array($result) ? $result : null;
    }

    /**
     * Extract the sequential leave history as a flat ordered array.
     *
     * @return list<array<string, mixed>>
     */
    public function getHistoryArray(LinkedList $list): array
    {
        /** @var list<array<string, mixed>> */
        return $list->toArray();
    }
}
