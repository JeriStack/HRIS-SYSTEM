<?php

/**
 * LeaveControllerAdapter Unit Test
 * TDD Workstream 2 - Controller Adapter / ACL
 */

declare(strict_types=1);

namespace Tests\Unit\Application\Leave;

use PHPUnit\Framework\TestCase;
use App\Application\Leave\Adapters\LeaveControllerAdapter;
use App\Application\Leave\Adapters\LeaveOperationResult;
use App\Application\Leave\UseCases\FileLeaveRequestUseCase;
use App\Application\Leave\UseCases\ApproveLeaveRequestUseCase;
use App\Application\Leave\UseCases\RejectLeaveRequestUseCase;
use App\Application\Leave\UseCases\CancelLeaveRequestUseCase;
use App\Domain\Leave\Entities\LeaveRequest;
use App\Domain\Leave\Entities\LeaveBalance;
use App\Domain\Leave\ValueObjects\LeaveDateRange;
use Tests\Fakes\InMemoryTransactionManager;
use Tests\Fakes\InMemoryLeaveRequestRepository;
use Tests\Fakes\InMemoryLeaveBalanceRepository;

class LeaveControllerAdapterTest extends TestCase
{
    private InMemoryTransactionManager $txManager;
    private InMemoryLeaveRequestRepository $requestRepo;
    private InMemoryLeaveBalanceRepository $balanceRepo;
    private LeaveControllerAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->txManager = new InMemoryTransactionManager();
        $this->requestRepo = new InMemoryLeaveRequestRepository();
        $this->balanceRepo = new InMemoryLeaveBalanceRepository();

        $fileUseCase = new FileLeaveRequestUseCase($this->requestRepo, $this->balanceRepo, $this->txManager);
        $approveUseCase = new ApproveLeaveRequestUseCase($this->requestRepo, $this->balanceRepo, $this->txManager);
        $rejectUseCase = new RejectLeaveRequestUseCase($this->requestRepo, $this->txManager);
        $cancelUseCase = new CancelLeaveRequestUseCase($this->requestRepo, $this->balanceRepo, $this->txManager);

        $this->adapter = new LeaveControllerAdapter(
            $fileUseCase,
            $approveUseCase,
            $rejectUseCase,
            $cancelUseCase
        );
    }

    public function test_approve_delegates_to_use_case_and_returns_success(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04'); // 3 working days
        $request = LeaveRequest::create(
            employeeId: 10,
            leaveTypeId: 1,
            dateRange: $dateRange,
            reason: 'Vacation'
        );
        $requestId = $this->requestRepo->save($request);

        $balance = LeaveBalance::create(10, 1, 2026, 15);
        $this->balanceRepo->save($balance);

        $result = $this->adapter->approve($requestId, 99);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->statusCode);
        $this->assertStringContainsString('approved', strtolower($result->message));
        $this->assertTrue($this->requestRepo->findById($requestId)->isApproved());
    }

    public function test_approve_translates_not_found_exception_to_404(): void
    {
        $result = $this->adapter->approve(9999, 99);

        $this->assertFalse($result->success);
        $this->assertSame(404, $result->statusCode);
        $this->assertStringContainsString('not found', strtolower($result->message));
    }

    public function test_approve_translates_insufficient_balance_to_400(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-06'); // 5 working days
        $request = LeaveRequest::create(10, 1, $dateRange, 'Vacation');
        $requestId = $this->requestRepo->save($request);

        // Only 2 days remaining
        $balance = LeaveBalance::reconstitute(1, 10, 1, 2026, 10, 8, 2);
        $this->balanceRepo->save($balance);

        $result = $this->adapter->approve($requestId, 99);

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->statusCode);
        $this->assertStringContainsString('insufficient leave balance', strtolower($result->message));
    }

    public function test_approve_translates_invalid_status_transition_to_400(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(10, 1, $dateRange, 'Vacation');
        $request->approve(99);
        $requestId = $this->requestRepo->save($request);

        $balance = LeaveBalance::create(10, 1, 2026, 15);
        $this->balanceRepo->save($balance);

        $result = $this->adapter->approve($requestId, 99);

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->statusCode);
        $this->assertStringContainsString('cannot transition', strtolower($result->message));
    }

    public function test_reject_delegates_to_use_case_and_returns_success(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(10, 1, $dateRange, 'Vacation');
        $requestId = $this->requestRepo->save($request);

        $result = $this->adapter->reject($requestId, 99, 'Scheduling conflict');

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->statusCode);
        $this->assertTrue($this->requestRepo->findById($requestId)->isRejected());
    }

    public function test_reject_translates_empty_reason_to_400(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(10, 1, $dateRange, 'Vacation');
        $requestId = $this->requestRepo->save($request);

        $result = $this->adapter->reject($requestId, 99, '   ');

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->statusCode);
        $this->assertStringContainsString('reason cannot be empty', strtolower($result->message));
    }

    public function test_cancel_delegates_to_use_case_and_returns_success(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(10, 1, $dateRange, 'Vacation');
        $requestId = $this->requestRepo->save($request);

        $result = $this->adapter->cancel($requestId, 10, false);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->statusCode);
        $this->assertTrue($this->requestRepo->findById($requestId)->isCancelled());
    }

    public function test_cancel_translates_unauthorized_exception_to_403(): void
    {
        $dateRange = LeaveDateRange::fromStrings('2026-11-02', '2026-11-04');
        $request = LeaveRequest::create(10, 1, $dateRange, 'Vacation');
        $requestId = $this->requestRepo->save($request);

        // Employee 20 attempting to cancel Employee 10's request
        $result = $this->adapter->cancel($requestId, 20, false);

        $this->assertFalse($result->success);
        $this->assertSame(403, $result->statusCode);
        $this->assertStringContainsString('unauthorized', strtolower($result->message));
    }

    public function test_file_delegates_to_use_case_and_returns_201(): void
    {
        $balance = LeaveBalance::create(10, 1, 2026, 15);
        $this->balanceRepo->save($balance);

        $result = $this->adapter->file(10, 1, '2026-11-02', '2026-11-04', 'Family vacation');

        $this->assertTrue($result->success);
        $this->assertSame(201, $result->statusCode);
        $this->assertIsInt($result->data['id'] ?? null);
    }

    public function test_file_translates_overlapping_dates_to_400(): void
    {
        $balance = LeaveBalance::create(10, 1, 2026, 15);
        $this->balanceRepo->save($balance);

        $existing = LeaveRequest::create(
            10,
            1,
            LeaveDateRange::fromStrings('2026-11-02', '2026-11-06'),
            'Existing trip'
        );
        $this->requestRepo->save($existing);

        // Attempt overlap
        $result = $this->adapter->file(10, 1, '2026-11-05', '2026-11-09', 'Overlapping trip');

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->statusCode);
        $this->assertStringContainsString('overlaps', strtolower($result->message));
    }
}
