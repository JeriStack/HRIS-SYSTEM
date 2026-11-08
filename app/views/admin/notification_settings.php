<?php
/**
 * Notification Settings & Transport Configuration View
 * Route: GET /admin/settings/notifications
 * Access: Administrator Only
 */
?>
<div class="page-header" style="margin-bottom: var(--space-6);">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
            <h1 style="margin: 0; font-size: 1.625rem; font-weight: 700;">Notification Settings &amp; Channels</h1>
            <span class="badge badge-primary">TRANSPORT ARCHITECTURE</span>
        </div>
        <p style="margin: 0; font-size: 0.9375rem; color: var(--color-text-muted);">
            Configuration and listener status for Email OTP dispatch and real-time WebSocket broadcast channels.
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/notifications') ?>" class="btn btn-secondary btn-sm">
            <span>🔔</span> Notification Inbox
        </a>
    </div>
</div>

<!-- Email Dispatch Configuration Card -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;"><span>✉️</span> SMTP Email Delivery Transport</h2>
            <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Powering 2FA OTP codes, leave approval notices, and system alerts.</span>
        </div>
        <span class="badge badge-success">
            <span class="badge-dot" aria-hidden="true"></span> <?= e($smtpStatus) ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table" aria-label="SMTP Configuration">
            <thead>
                <tr>
                    <th style="width: 250px;">Transport Property</th>
                    <th>Configured Runtime Parameter</th>
                    <th style="width: 140px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>SMTP Server Host</strong></td>
                    <td><code><?= e($mailConfig['host'] ?? '127.0.0.1') ?></code></td>
                    <td><span class="badge badge-neutral">Resolved</span></td>
                </tr>
                <tr>
                    <td><strong>SMTP Server Port</strong></td>
                    <td><code><?= (int)($mailConfig['port'] ?? 1025) ?></code> <span style="font-size: 0.8125rem; color: var(--color-text-muted);">(SMTP socket &bull; View inbox at <a href="http://localhost:8025" target="_blank" rel="noopener noreferrer" style="color: var(--color-primary); font-weight: 500;">Mailpit Web UI Port 8025 &nearr;</a>)</span></td>
                    <td><span class="badge badge-neutral">Standard</span></td>
                </tr>
                <tr>
                    <td><strong>Transport Encryption</strong></td>
                    <td><code><?= e(strtoupper($mailConfig['encryption'] ?? 'NONE')) ?></code></td>
                    <td><span class="badge badge-neutral">Security</span></td>
                </tr>
                <tr>
                    <td><strong>Sender Email Address</strong></td>
                    <td><code><?= e($mailConfig['from_address'] ?? 'noreply@company.local') ?></code> (<?= e($mailConfig['from_name'] ?? 'NEXORA HR System') ?>)</td>
                    <td><span class="badge badge-neutral">From Header</span></td>
                </tr>
                <tr>
                    <td><strong>SMTP Password / Auth Secret</strong></td>
                    <td><code>•••••••••••••••• (Encrypted)</code></td>
                    <td><span class="badge badge-success">Protected</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- WebSocket Channel Policy Overview Card -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;"><span>⚡</span> Real-Time WebSocket Channel Policies</h2>
            <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Enforced via <code>App\Realtime\ChannelPolicy</code> and <code>EventAuthorizer</code>.</span>
        </div>
        <span class="badge badge-primary">
            <span class="badge-dot" aria-hidden="true"></span> <?= e($wsStatus) ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table" aria-label="WebSocket Channels">
            <thead>
                <tr>
                    <th style="width: 220px;">Channel Name</th>
                    <th>Allowed Audience &amp; Events</th>
                    <th style="width: 140px;">Isolation Level</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>role.admin</code></td>
                    <td>System administrators. Receives critical security events, system alerts, and global operational updates.</td>
                    <td><span class="badge badge-primary">Admin Only</span></td>
                </tr>
                <tr>
                    <td><code>role.hr</code></td>
                    <td>HR officers and Administrators. Receives <code>LEAVE_CREATED</code> when employees file leave requests.</td>
                    <td><span class="badge badge-neutral">Admin &bull; HR</span></td>
                </tr>
                <tr>
                    <td><code>user.{id}</code></td>
                    <td>Private per-user channel. Receives personalized <code>LEAVE_STATUS_CHANGED</code> and system alerts.</td>
                    <td><span class="badge badge-success">Strictly Isolated</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

