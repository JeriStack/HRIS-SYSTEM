<?php
/**
 * Authentication & Session Security Settings View
 * Route: GET /admin/settings/auth
 * Access: Administrator Only
 */
?>
<div class="page-header" style="margin-bottom: var(--space-6);">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
            <h1 style="margin: 0; font-size: 1.625rem; font-weight: 700;">Authentication &amp; Security Settings</h1>
            <span class="badge badge-success">ZERO-TRUST POLICY ACTIVE</span>
        </div>
        <p style="margin: 0; font-size: 0.9375rem; color: var(--color-text-muted);">
            Active session lifetime, multi-factor authentication (MFA/2FA), rate-limiting thresholds, and cryptographic algorithms.
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/admin/security/events') ?>" class="btn btn-secondary btn-sm">
            <span>🚨</span> View Security Events
        </a>
    </div>
</div>

<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;"><span>🔐</span> Active Authentication &amp; Session Policies</h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Enforced across all web and API entrypoints via <code>Session</code> and <code>AuthMiddleware</code>.</span>
    </div>
    <div class="table-responsive">
        <table class="table" aria-label="Authentication Policies">
            <thead>
                <tr>
                    <th style="width: 280px;">Security Dimension</th>
                    <th>Configured System Policy</th>
                    <th style="width: 160px;">Enforcement Level</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Two-Factor Authentication (2FA)</strong></td>
                    <td><?= e($policy['mfa_status']) ?></td>
                    <td><span class="badge badge-success">Mandatory</span></td>
                </tr>
                <tr>
                    <td><strong>OTP Challenge Expiration</strong></td>
                    <td><?= (int)$policy['otp_expiry_seconds'] ?> seconds (5 minutes) before single-use token invalidation</td>
                    <td><span class="badge badge-primary">Hard Timeout</span></td>
                </tr>
                <tr>
                    <td><strong>OTP Verification Rate Limiting</strong></td>
                    <td>Maximum <?= (int)$policy['max_otp_attempts'] ?> failed attempts per challenge before token exhaustion and temporary IP cooldown</td>
                    <td><span class="badge badge-warning">Fail-Closed</span></td>
                </tr>
                <tr>
                    <td><strong>Session Inactivity Timeout</strong></td>
                    <td><?= (int)$policy['session_idle_timeout'] ?> seconds (30 minutes of idle duration rotates and expires session)</td>
                    <td><span class="badge badge-neutral">Auto-Expire</span></td>
                </tr>
                <tr>
                    <td><strong>Absolute Session Lifetime</strong></td>
                    <td><?= (int)$policy['session_max_lifetime'] ?> seconds (12 hours maximum authenticated lifespan)</td>
                    <td><span class="badge badge-neutral">Hard Limit</span></td>
                </tr>
                <tr>
                    <td><strong>Credential Hashing Standard</strong></td>
                    <td><?= e($policy['password_algorithm']) ?> with automatic salt generation</td>
                    <td><span class="badge badge-success">FIPS / OWASP</span></td>
                </tr>
                <tr>
                    <td><strong>Continuous Session Parity</strong></td>
                    <td><?= e($policy['session_fingerprint']) ?></td>
                    <td><span class="badge badge-primary">Strict Guard</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

