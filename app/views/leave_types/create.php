<?php
/**
 * Create Leave Type View (HR & Admin Access)
 * Employee HRIS & Leave Management System - Phase 8
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>Create Leave Classification</h1>
        <p>Establish a new corporate leave policy category and set its default annual entitlement.</p>
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
            <span>🏷️</span> Classification Details
        </h2>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= url('/hr/leave-types') ?>">
            <?= csrf_field() ?>

            <!-- Leave Type Name -->
            <div class="form-group">
                <label for="leave_name" class="form-label required">Classification Name</label>
                <input type="text" 
                       name="leave_name" 
                       id="leave_name" 
                       class="form-control" 
                       placeholder="e.g. Parental Leave, Bereavement Leave, Sabbatical"
                       maxlength="100" 
                       required>
                <span class="form-hint">Must be unique across the organization (2-100 characters).</span>
            </div>

            <!-- Default Days Quota -->
            <div class="form-group">
                <label for="default_days" class="form-label required">Default Annual Allowance (Days)</label>
                <input type="number" 
                       name="default_days" 
                       id="default_days" 
                       class="form-control" 
                       value="15" 
                       min="1" 
                       max="365" 
                       required>
                <span class="form-hint">Default working days credited upon annual balance initialization.</span>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="description" class="form-label">Policy Guidelines & Description</label>
                <textarea name="description" 
                          id="description" 
                          rows="4" 
                          class="form-textarea" 
                          placeholder="Provide details regarding eligibility criteria, certification requirements, or advance notice rules..."></textarea>
                <span class="form-hint">Displayed to employees when submitting a leave application.</span>
            </div>

            <!-- Initial Status -->
            <div class="form-group">
                <label for="status" class="form-label required">Status</label>
                <select name="status" id="status" class="form-select" required>
                    <option value="Active" selected>Active (Available for filings)</option>
                    <option value="Inactive">Inactive (Disabled for new filings)</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.75rem; align-items: center; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--color-border);">
                <a href="<?= url('/hr/leave-types') ?>" class="btn btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <span>💾</span> Create Leave Classification
                </button>
            </div>
        </form>
    </div>
</div>
