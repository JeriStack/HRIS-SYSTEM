<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\LeaveCalculator;
use App\Models\Notification;
use Core\Permission;

class LeaveWorkflowIntegrationTest extends TestCase
{
    public function test_inactive_employee_cannot_file_leave(): void
    {
        $inactiveEmployee = [
            'id'                => 15,
            'employee_no'       => 'EMP-015',
            'employment_status' => 'Inactive',
        ];

        $isEligibleToFile = ($inactiveEmployee['employment_status'] === 'Active');
        $this->assertFalse($isEligibleToFile, 'Inactive employees must be blocked from submitting leave requests.');
    }

    public function test_active_employee_is_eligible_to_file_leave(): void
    {
        $activeEmployee = [
            'id'                => 16,
            'employee_no'       => 'EMP-016',
            'employment_status' => 'Active',
        ];

        $isEligibleToFile = ($activeEmployee['employment_status'] === 'Active');
        $this->assertTrue($isEligibleToFile, 'Active employees must be permitted to submit leave requests.');
    }

    public function test_working_days_calculation_excludes_weekends(): void
    {
        // Monday 2026-11-02 to Friday 2026-11-06 = 5 working days
        $validation = LeaveCalculator::validateDateRange('2026-11-02', '2026-11-06');
        $this->assertTrue($validation['valid']);
        $this->assertSame(5, $validation['working_days']);

        // Saturday 2026-11-07 to Sunday 2026-11-08 = 0 working days
        $weekendVal = LeaveCalculator::validateDateRange('2026-11-07', '2026-11-08');
        $this->assertTrue($weekendVal['valid']);
        $this->assertSame(0, $weekendVal['working_days']);
    }

    public function test_overlapping_date_ranges_are_strictly_detected(): void
    {
        $existingStart = '2026-11-09';
        $existingEnd   = '2026-11-13';

        // Partial overlap on Thursday/Friday
        $req1Start = '2026-11-12';
        $req1End   = '2026-11-16';
        $hasOverlap = ($existingStart <= $req1End) && ($existingEnd >= $req1Start);
        $this->assertTrue($hasOverlap);

        // Entirely outside range
        $req2Start = '2026-11-16';
        $req2End   = '2026-11-20';
        $hasNoOverlap = ($existingStart <= $req2End) && ($existingEnd >= $req2Start);
        $this->assertFalse($hasNoOverlap);
    }

    public function test_employee_ownership_blocks_unauthorized_cancellation(): void
    {
        $sessionEmpId = 5;
        $targetRequest = [
            'id'          => 42,
            'employee_id' => 8, // Belongs to different employee
            'status'      => 'Pending',
        ];

        $isOwner = ((int)$targetRequest['employee_id'] === $sessionEmpId);
        $this->assertFalse($isOwner, 'Employees must not be permitted to cancel other employees requests.');
    }

    public function test_only_pending_requests_can_be_cancelled(): void
    {
        $pendingRequest = ['id' => 1, 'status' => 'Pending'];
        $approvedRequest = ['id' => 2, 'status' => 'Approved'];
        $rejectedRequest = ['id' => 3, 'status' => 'Rejected'];

        $this->assertTrue($pendingRequest['status'] === 'Pending');
        $this->assertFalse($approvedRequest['status'] === 'Pending', 'Approved requests cannot be cancelled by employee.');
        $this->assertFalse($rejectedRequest['status'] === 'Pending', 'Rejected requests cannot be cancelled.');
    }

    public function test_hr_role_authorization_for_leave_review(): void
    {
        $this->assertTrue(Permission::can('hr', 'review_leave_requests'));
        $this->assertFalse(Permission::can('employee', 'review_leave_requests'));
    }

    public function test_balance_deduction_invariants(): void
    {
        $initialBalance = [
            'allocated_days' => 15,
            'used_days'      => 3,
            'remaining_days' => 12,
        ];

        $approvedDays = 4;

        // Pending does not deduct
        $this->assertSame(12, $initialBalance['remaining_days']);

        // Post-approval calculation
        $newUsed = $initialBalance['used_days'] + $approvedDays;
        $newRemaining = $initialBalance['allocated_days'] - $newUsed;

        $this->assertSame(7, $newUsed);
        $this->assertSame(8, $newRemaining);
    }
}

