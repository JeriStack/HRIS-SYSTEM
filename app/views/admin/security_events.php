<?php
/**
 * Security Events & Authentication Monitoring View
 * Route: GET /admin/security/events
 * Access: Administrator Only
 */
?>
<div class="page-header" style="margin-bottom: var(--space-6);">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
            <h1 style="margin: 0; font-size: 1.625rem; font-weight: 700;">Security Events &amp; Monitoring</h1>
            <span class="badge badge-warning">AUTHENTICATION AUDIT</span>
        </div>
        <p style="margin: 0; font-size: 0.9375rem; color: var(--color-text-muted);">
            Real-time security telemetry, failed logins, OTP challenges, account lockouts, and privilege changes.
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/activity-logs') ?>" class="btn btn-secondary btn-sm">
            <span>📜</span> View Full Activity Logs
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-body" style="padding: 1rem 1.25rem;">
        <form method="GET" action="<?= url('/admin/security/events') ?>" style="display: flex; gap: 0.75rem; align-items: center; margin: 0;">
            <input type="text" name="filter" value="<?= e($filter ?? '') ?>" placeholder="Filter by event type, username, or IP address..." class="form-control" style="flex: 1;">
            <button type="submit" class="btn btn-primary btn-sm">Search Events</button>
            <?php if (!empty($filter)): ?>
                <a href="<?= url('/admin/security/events') ?>" class="btn btn-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Security Events Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;">Security Telemetry Trail</h2>
            <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Displaying latest <?= count($securityEvents) ?> security events recorded.</span>
        </div>
        <span class="badge badge-neutral" style="font-family: var(--font-mono);">FAIL-SAFE AUDITING</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" aria-label="Security Events">
            <thead>
                <tr>
                    <th style="width: 160px;">Operator / Identity</th>
                    <th style="width: 220px;">Security Event</th>
                    <th>Event Description / Payload</th>
                    <th style="width: 140px;">IP Address</th>
                    <th style="width: 140px;">Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($securityEvents)): ?>
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 3rem; color: var(--color-text-muted);">
                            No matching security events found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($securityEvents as $event): ?>
                        <tr>
                            <td>
                                <strong><?= e($event['username'] ?? 'Anonymous') ?></strong>
                                <?php if (!empty($event['role'])): ?>
                                    <div style="font-size: 0.725rem; color: var(--color-text-muted);">
                                        <?= e(strtoupper($event['role'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-warning" style="font-family: var(--font-mono); font-size: 0.7rem;">
                                    <?= e($event['action']) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8125rem; color: var(--color-text-body);">
                                <?= e($event['description'] ?? 'Security action') ?>
                            </td>
                            <td style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--color-text-muted);">
                                <?= e($event['ip_address'] ?? '127.0.0.1') ?>
                            </td>
                            <td style="font-size: 0.775rem; color: var(--color-text-muted); white-space: nowrap;">
                                <?= date('M j, Y H:i:s', strtotime($event['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

