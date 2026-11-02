<?php
/**
 * Leave Approval Queue Service
 * Integrates custom FIFO Queue with HRIS Leave Request Review Pipeline
 * Employee HRIS & Leave Management System — Phase 11
 */

namespace App\Services;

use App\Dsa\Structures\Queue;
use App\Models\Leave;

class LeaveApprovalQueueService
{
    private Leave $leaveModel;

    public function __construct(?Leave $model = null)
    {
        $this->leaveModel = $model ?? new Leave();
    }

    /**
     * Build a deterministic FIFO Queue of pending leave requests.
     * Orders requests by submission timestamp ASC, then ID ASC.
     *
     * @param int|null $departmentId Optional department filter
     * @param int $limit Maximum requests to enqueue
     * @return Queue
     */
    public function buildPendingApprovalQueue(?int $departmentId = null, int $limit = 50): Queue
    {
        // Fetch pending leave requests. getAllRequests signature is
        // (status, empId, limit, startDate, endDate, deptId): the department filter
        // must go in the deptId slot, not the empId slot, and the limit must be applied in SQL.
        $requests = $this->leaveModel->getAllRequests('Pending', null, $limit > 0 ? $limit : null, null, null, $departmentId);

        // Sort deterministically by applied_at / created_at ascending, then id ascending
        usort($requests, function (array $a, array $b): int {
            $dateA = $a['applied_at'] ?? $a['created_at'] ?? '';
            $dateB = $b['applied_at'] ?? $b['created_at'] ?? '';
            $dateCmp = strcmp((string)$dateA, (string)$dateB);
            if ($dateCmp !== 0) {
                return $dateCmp;
            }
            return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
        });

        if ($limit > 0 && count($requests) > $limit) {
            $requests = array_slice($requests, 0, $limit);
        }

        $queue = new Queue();
        foreach ($requests as $req) {
            $queue->enqueue($req);
        }

        return $queue;
    }

    /**
     * Inspect the next pending request at the head of the queue.
     * Does not mutate queue or change request status.
     *
     * @return array<string, mixed>|null
     */
    public function getNextInLine(Queue $queue): ?array
    {
        return $queue->peek();
    }

    /**
     * Process the next item in the queue with authoritative approval.
     * Dequeues the request and delegates to Leave::approveRequest() inside a transaction.
     *
     * @param Queue $queue
     * @param int $approverId Authorized HR/Manager user ID
     * @return array{success: bool, status: string, message: string, request: array|null}
     */
    public function processNextApproval(Queue $queue, int $approverId): array
    {
        if ($queue->isEmpty()) {
            return [
                'success' => false,
                'status'  => 'empty_queue',
                'message' => 'No pending requests in queue.',
                'request' => null,
            ];
        }

        $request = $queue->dequeue();
        $requestId = (int)($request['id'] ?? 0);

        if ($requestId <= 0) {
            return [
                'success' => false,
                'status'  => 'invalid_request',
                'message' => 'Dequeued item contains no valid request ID.',
                'request' => $request,
            ];
        }

        // Delegate to authoritative transaction-safe leave approval.
        // Leave::approveRequest returns ['ok' => bool, 'code' => string, ...], not 'status'.
        $result = $this->leaveModel->approveRequest($requestId, $approverId);

        return [
            'success' => (bool)($result['ok'] ?? false),
            'status'  => $result['code'] ?? ($result['ok'] ?? false ? 'approved' : 'error'),
            'message' => $result['message'] ?? 'Leave approval completed.',
            'request' => $request,
        ];
    }
}
