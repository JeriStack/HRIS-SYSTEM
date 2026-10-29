<?php
/**
 * Recent Activity Stack Service
 * Integrates custom LIFO Stack with HRIS Activity Audit Logs
 * Employee HRIS & Leave Management System — Phase 11
 */

namespace App\Services;

use App\Dsa\Structures\Stack;
use App\Models\ActivityLog;

class RecentActivityStackService
{
    private ActivityLog $activityLogModel;

    public function __construct(?ActivityLog $model = null)
    {
        $this->activityLogModel = $model ?? new ActivityLog();
    }

    /**
     * Load recent activity logs from the database into a custom LIFO Stack.
     * Records fetched in chronological order (oldest to newest) so pushing them
     * onto the stack places the newest activity at the top (peek/pop).
     *
     * @param int $limit Maximum records to load
     * @return Stack<array<string, mixed>>
     */
    public function loadRecentActivitiesStack(int $limit = 20): Stack
    {
        $logs = $this->activityLogModel->getAll(null, null, $limit);
        
        // Reverse array so oldest is pushed first, newest sits on TOP of stack
        $chronological = array_reverse($logs);

        $stack = new Stack();
        foreach ($chronological as $log) {
            $stack->push($log);
        }

        return $stack;
    }

    /**
     * Inspect the most recent activity record without removing it.
     * O(1) time complexity.
     *
     * @return array<string, mixed>|null
     */
    public function getLatestActivity(Stack $stack): ?array
    {
        return $stack->peek();
    }

    /**
     * Pop and return the N most recent activities in descending order of occurrence.
     *
     * @return list<array<string, mixed>>
     */
    public function extractMostRecent(Stack $stack, int $count = 5): array
    {
        $results = [];
        for ($i = 0; $i < $count && !$stack->isEmpty(); $i++) {
            $item = $stack->pop();
            if ($item !== null) {
                $results[] = $item;
            }
        }
        return $results;
    }
}

