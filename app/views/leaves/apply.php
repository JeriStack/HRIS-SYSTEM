<?php
/**
 * File Leave Request View (Employee Self-Service)
 * Employee HRIS & Leave Management System
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>File Leave Application</h1>
        <p>Submit an official absence request for vacation, medical leave, or personal emergency.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/employee/dashboard') ?>" class="btn btn-secondary btn-sm">
            <span>←</span> Back to Dashboard
        </a>
    </div>
</div>

<div class="grid grid-cols-3" style="align-items: start;">
    <!-- Form Card (Spans 2 columns) -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h2 class="card-title"><span>✍️</span> Leave Request Form</h2>
            <span style="font-size: 0.75rem; color: var(--color-text-muted);">Subject to Management Approval</span>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= url('/leaves/apply') ?>" id="leaveApplicationForm">
                <?= csrf_field() ?>

                <!-- Leave Type Selection -->
                <div class="form-group">
                    <label for="leaveTypeSelect" class="form-label required">Leave Classification</label>
                    <select name="leave_type_id" id="leaveTypeSelect" class="form-select" required>
                        <option value="">-- Select Approved Leave Type --</option>
                        <?php foreach ($leaveTypes as $type): 
                            $matchedBal = 0;
                            foreach ($balances as $b) {
                                if ((int)$b['leave_type_id'] === (int)$type['id']) {
                                    $matchedBal = (int)$b['remaining_days'];
                                    break;
                                }
                            }
                        ?>
                            <option value="<?= (int)$type['id'] ?>" data-balance="<?= $matchedBal ?>">
                                <?= e($type['leave_name']) ?> (<?= $matchedBal ?> days remaining)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">Only active leave types with sufficient balance can be approved.</span>
                </div>

                <!-- Date Range Grid -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="leaveStartDate" class="form-label required">Start Date</label>
                        <input type="date" 
                               name="start_date" 
                               id="leaveStartDate" 
                               class="form-control" 
                               min="<?= date('Y-m-d') ?>" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="leaveEndDate" class="form-label required">End Date</label>
                        <input type="date" 
                               name="end_date" 
                               id="leaveEndDate" 
                               class="form-control" 
                               min="<?= date('Y-m-d') ?>" 
                               required>
                    </div>
                </div>

                <!-- Dynamic Working Days Calculation Display -->
                <div style="background-color: var(--color-bg-muted); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.85rem 1.15rem; margin-bottom: 1.25rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; margin-bottom: 0.25rem;">
                        Calculated Business Days:
                    </div>
                    <div id="calculatedDaysDisplay" style="font-size: 1.1rem; font-weight: 700; color: var(--color-text-main);">
                        0 Working Days
                    </div>
                    <input type="hidden" name="number_of_days" id="numberOfDaysInput" value="0">
                    <div id="dateValidationError" class="form-error" style="display: none; margin-top: 0.35rem;"></div>
                </div>

                <!-- Reason for Leave -->
                <div class="form-group">
                    <label for="leaveReason" class="form-label required">Reason / Supporting Details</label>
                    <textarea name="reason" 
                              id="leaveReason" 
                              rows="4" 
                              class="form-textarea" 
                              placeholder="Provide a concise description of your reason for leave..." 
                              required></textarea>
                    <span class="form-hint">Please provide sufficient detail for your supervisor and HR officer to evaluate.</span>
                </div>

                <!-- Submit / Cancel -->
                <div style="display: flex; gap: 0.75rem; align-items: center; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--color-border);">
                    <a href="<?= url('/employee/dashboard') ?>" class="btn btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <span>📤</span> Submit Leave Application
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Sidebar: Current Available Balances -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><span>⚖️</span> Available Balances</h2>
        </div>
        <div class="card-body" style="padding: 1.25rem;">
            <?php if (empty($balances)): ?>
                <div style="font-size: 0.85rem; color: var(--color-text-muted);">
                    No balance allocation found for <?= date('Y') ?>.
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($balances as $b): 
                        $rem = (int)($b['remaining_days'] ?? 0);
                    ?>
                        <div style="padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border-subtle);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                <strong style="font-size: 0.875rem;"><?= e($b['leave_name']) ?></strong>
                                <span class="badge <?= $rem > 2 ? 'badge-success' : ($rem > 0 ? 'badge-warning' : 'badge-danger') ?>">
                                    <?= $rem ?> Left
                                </span>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                                <?= (int)$b['used_days'] ?> used of <?= (int)$b['allocated_days'] ?> allocated
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer" style="font-size: 0.75rem; color: var(--color-text-muted);">
            Year <?= date('Y') ?> Statutory & Corporate Quota
        </div>
    </div>
</div>
