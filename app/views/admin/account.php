<?php
/**
 * Administrator Personal Account & Profile Settings View
 * Route: GET /admin/account
 * Access: Administrator Only
 */
?>
<div class="page-header" style="margin-bottom: var(--space-6);">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
            <h1 style="margin: 0; font-size: 1.625rem; font-weight: 700;">My Account Settings</h1>
            <span class="badge badge-primary">ADMINISTRATOR IDENTITY</span>
        </div>
        <p style="margin: 0; font-size: 0.9375rem; color: var(--color-text-muted);">
            Manage your administrator credentials, email address, and security authentication settings.
        </p>
    </div>
</div>

<div class="grid grid-cols-2" style="gap: 1.5rem; align-items: start;">
    <!-- Profile & Security Form Card -->
    <div class="card" style="margin: 0;">
        <div class="card-header">
            <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;"><span>👤</span> Administrator Profile &amp; Password</h2>
            <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Password updates require verifying your current password.</span>
        </div>
        <div class="card-body" style="padding: 1.25rem;">
            <form method="POST" action="<?= url('/admin/account') ?>" style="margin: 0;">
                <?= csrf_field() ?>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="username" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: var(--color-text-main);">Username (Identity)</label>
                    <input type="text" id="username" value="<?= e($user['username'] ?? '') ?>" class="form-control" disabled style="background: var(--color-bg-muted); cursor: not-allowed;">
                    <small style="color: var(--color-text-muted); font-size: 0.75rem;">Username is immutable once provisioned.</small>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="email" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: var(--color-text-main);">Email Address (For 2FA OTP)</label>
                    <input type="email" id="email" name="email" value="<?= e($user['email'] ?? '') ?>" class="form-control" required>
                    <small style="color: var(--color-text-muted); font-size: 0.75rem;">All 2FA verification codes are dispatched to this email address.</small>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--color-border); margin: 1.5rem 0;">

                <h3 style="font-size: 0.9375rem; font-weight: 600; margin: 0 0 1rem 0; color: var(--color-text-main);">Change Security Password</h3>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="current_password" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: var(--color-text-main);">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" autocomplete="current-password">
                    <small style="color: var(--color-text-muted); font-size: 0.75rem;">Leave empty if you do not wish to change your password.</small>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="new_password" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: var(--color-text-main);">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" autocomplete="new-password">
                    <small style="color: var(--color-text-muted); font-size: 0.75rem;">Minimum 8 characters with numbers and symbols recommended.</small>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="confirm_password" style="display: block; font-weight: 600; font-size: 0.875rem; margin-bottom: 0.35rem; color: var(--color-text-main);">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" autocomplete="new-password">
                </div>

                <div style="display: flex; gap: 0.75rem;">
                    <button type="submit" class="btn btn-primary">Save Profile Changes</button>
                    <a href="<?= url('/admin/dashboard') ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Account Details & Status Card -->
    <div class="card" style="margin: 0;">
        <div class="card-header">
            <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;"><span>🛡️</span> Security Clearance &amp; Session</h2>
        </div>
        <div class="card-body" style="padding: 1.25rem;">
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border);">
                    <span style="color: var(--color-text-muted); font-size: 0.875rem;">Application Role</span>
                    <span class="role-badge admin">ADMINISTRATOR</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border);">
                    <span style="color: var(--color-text-muted); font-size: 0.875rem;">Account Status</span>
                    <span class="badge badge-success">
                        <span class="badge-dot" aria-hidden="true"></span> <?= e($user['status'] ?? 'Active') ?>
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border);">
                    <span style="color: var(--color-text-muted); font-size: 0.875rem;">Two-Factor Authentication</span>
                    <span class="badge badge-success">Enabled (Email OTP)</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border);">
                    <span style="color: var(--color-text-muted); font-size: 0.875rem;">Account Created</span>
                    <span style="font-size: 0.8125rem; font-weight: 600; color: var(--color-text-main);"><?= date('M j, Y H:i', strtotime($user['created_at'] ?? 'now')) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: var(--color-text-muted); font-size: 0.875rem;">Session Rotation</span>
                    <span class="badge badge-neutral">Auto (Every Auth Change)</span>
                </div>
            </div>
        </div>
    </div>
</div>

