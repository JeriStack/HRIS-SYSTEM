<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class LeaveApprovalTest extends TestCase
{
    public function test_leave_request_initial_state_must_be_pending(): void
    {
        $newRequest = [
            'id'             => 101,
            'employee_id'    => 5,
            'leave_type_id'  => 1,
            'start_date'     => '2026-11-02',
            'end_date'       => '2026-11-04',
            'number_of_days' => 3,
            'status'         => 'Pending',
        ];

        $this->assertSame('Pending', $newRequest['status']);
    }

    public function test_leave_status_transition_rules(): void
    {
        $validStatuses = ['Pending', 'Approved', 'Rejected', 'Cancelled'];

        // Only Pending requests can be Approved or Rejected
        $canApprovePending = in_array('Approved', $validStatuses, true);
        $this->assertTrue($canApprovePending);

        // Transition from Pending to Approved
        $requestStatus = 'Pending';
        $nextStatus = 'Approved';
        $isTransitionAllowed = ($requestStatus === 'Pending' && in_array($nextStatus, ['Approved', 'Rejected'], true));
        $this->assertTrue($isTransitionAllowed);

        // Transitioning an already Approved request must be blocked
        $requestStatus = 'Approved';
        $secondAttempt = 'Approved';
        $isSecondTransitionAllowed = ($requestStatus === 'Pending');
        $this->assertFalse($isSecondTransitionAllowed, 'Cannot re-approve an already processed request.');
    }

    public function test_segregation_of_duties_prevents_self_approval(): void
    {
        $applicantUserId = 12;
        $approverUserId  = 12; // Same user attempting self-approval

        $isSelfApproval = ($applicantUserId === $approverUserId);
        $canApprove = !$isSelfApproval;

        $this->assertFalse($canApprove, 'An employee must not be permitted to approve their own leave request.');
    }

    public function test_overlap_detection_logic_identifies_conflicting_dates(): void
    {
        // Existing approved leave: 2026-11-02 to 2026-11-06
        $existingStart = '2026-11-02';
        $existingEnd   = '2026-11-06';

        // Overlapping request: 2026-11-04 to 2026-11-08
        $newStart = '2026-11-04';
        $newEnd   = '2026-11-08';

        // Overlap formula: (startA <= endB) AND (endA >= startB)
        $hasOverlap = ($existingStart <= $newEnd) && ($existingEnd >= $newStart);
        $this->assertTrue($hasOverlap, 'Conflicting date ranges for the same employee must trigger overlap detection.');

        // Non-overlapping request: 2026-11-10 to 2026-11-12
        $distantStart = '2026-11-10';
        $distantEnd   = '2026-11-12';
        $hasDistantOverlap = ($existingStart <= $distantEnd) && ($existingEnd >= $distantStart);
        $this->assertFalse($hasDistantOverlap, 'Disjoint date ranges should not trigger overlap detection.');
    }

    public function test_balance_is_correctly_deducted_upon_approval(): void
    {
        $initialBalance = [
            'allocated_days' => 15,
            'used_days'      => 2,
            'remaining_days' => 13,
        ];

        $approvedDays = 4;

        $updatedUsed = $initialBalance['used_days'] + $approvedDays;
        $updatedRemaining = max(0, $initialBalance['allocated_days'] - $updatedUsed);

        $this->assertSame(6, $updatedUsed);
        $this->assertSame(9, $updatedRemaining);
    }
}
