<?php

namespace Tests\Integration\DSA;

use App\Models\ActivityLog;
use App\Services\RecentActivityStackService;
use PHPUnit\Framework\TestCase;

class RecentActivityStackTest extends TestCase
{
    private RecentActivityStackService $service;

    protected function setUp(): void
    {
        // Mock or real model
        $mockModel = $this->createStub(ActivityLog::class);
        $mockModel->method('getAll')->willReturn([
            ['id' => 103, 'action' => 'LOGIN', 'description' => 'User Alice logged in', 'created_at' => '2026-10-29 10:00:00'],
            ['id' => 102, 'action' => 'LEAVE_APPLY', 'description' => 'Applied for vacation leave', 'created_at' => '2026-10-29 09:30:00'],
            ['id' => 101, 'action' => 'PROFILE_UPDATE', 'description' => 'Updated contact number', 'created_at' => '2026-10-29 09:00:00'],
        ]);

        $this->service = new RecentActivityStackService($mockModel);
    }

    public function test_load_recent_activities_into_stack(): void
    {
        $stack = $this->service->loadRecentActivitiesStack(10);
        $this->assertFalse($stack->isEmpty());
        $this->assertSame(3, $stack->size());

        // Top of the stack must be the newest activity (id 103)
        $latest = $this->service->getLatestActivity($stack);
        $this->assertNotNull($latest);
        $this->assertSame(103, $latest['id']);
        $this->assertSame('LOGIN', $latest['action']);
    }

    public function test_extract_most_recent_pops_in_lifo_order(): void
    {
        $stack = $this->service->loadRecentActivitiesStack(10);
        $popped = $this->service->extractMostRecent($stack, 2);

        $this->assertCount(2, $popped);
        $this->assertSame(103, $popped[0]['id']);
        $this->assertSame(102, $popped[1]['id']);

        // Stack size reduced
        $this->assertSame(1, $stack->size());
        $remaining = $stack->pop();
        $this->assertSame(101, $remaining['id']);
        $this->assertTrue($stack->isEmpty());
    }
}

