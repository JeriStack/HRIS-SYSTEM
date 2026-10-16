<?php
/**
 * Edit Leave Type View (HR & Admin Access)
 * Employee HRIS & Leave Management System - Phase 8
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Edit Leave Classification</h1>
        <p>Modify policy parameters, default quota allowance, or operational status for <?= e($type['leave_name']) ?>.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/hr/leave-types') ?>" class="btn btn-secondary btn-sm">
            <span>←</span> Back to Leave Types
        </a>
    </div>
</div>

<div class="card" style="max-width: 720px; margin: 0 auto;">
    <div class="card-header">
        <h2 class="card-title">
            <span>✏️</span> Classification #<?= (int)$type['id'] ?>
        </h2>
        <span class="badge <?= $type['status'] === 'Active' ? 'badge-success' : 'badge-neutral' ?>">
            <?= e($type['status']) ?>
        </span>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= url('/hr/leave-types/' . $type['id'] . '/edit') ?>">
            <?= csrf_field() ?>

            <!-- Leave Type Name -->
            <div class="form-group">
                <label for="leave_name" class="form-label required">Classification Name</label>
                <input type="text" 
                       name="leave_name" 
                       id="leave_name" 
                       class="form-control" 
                       value="<?= e($type['leave_name']) ?>" 
                       maxlength="100" 
                       required>
                <span class="form-hint">Must be unique across the organization.</span>
            </div>

            <!-- Default Days Quota -->
            <div class="form-group">
                <label for="default_days" class="form-label required">Default Annual Allowance (Days)</label>
                <input type="number" 
                       name="default_days" 
                       id="default_days" 
                       class="form-control" 
                       value="<?= (int)$type['default_days'] ?>" 
                       min="1" 
                       max="365" 
                       required>
                <span class="form-hint">Used when initializing balances for new employees or new calendar years.</span>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="description" class="form-label">Policy Guidelines & Description</label>
                <textarea name="description" 
                          id="description" 
                          rows="4" 
                          class="form-textarea"><?= e($type['description'] ?? '') ?></textarea>
                <span class="form-hint">Guidance displayed to employees during leave filing.</span>
            </div>

            <!-- Status -->
            <div class="form-group">
                <label for="status" class="form-label required">Operational Status</label>
                <select name="status" id="status" class="form-select" required>
                    <option value="Active" <?= $type['status'] === 'Active' ? 'selected' : '' ?>>Active (Enabled for new filings)</option>
                    <option value="Inactive" <?= $type['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive (Disabled for new filings - historical data preserved)</option>
                </select>
                <span class="form-hint">Deactivation safely prevents new filings without affecting historical requests or balances.</span>
            </div>

            <div style="display: flex; gap: 0.75rem; align-items: center; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--color-border);">
                <a href="<?= url('/hr/leave-types') ?>" class="btn btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <span>💾</span> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
