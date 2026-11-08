<?php
/**
 * General Application Settings View
 * Route: GET /admin/settings
 * Access: Administrator Only
 */
?>
<div class="page-header" style="margin-bottom: var(--space-6);">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
            <h1 style="margin: 0; font-size: 1.625rem; font-weight: 700;">General Application Settings</h1>
            <span class="badge badge-primary">SYSTEM CONFIGURATION</span>
        </div>
        <p style="margin: 0; font-size: 0.9375rem; color: var(--color-text-muted);">
            Core environment variables, runtime parameters, and application branding configuration.
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/admin/system-health') ?>" class="btn btn-secondary btn-sm">
            <span>🩺</span> System Health Monitor
        </a>
    </div>
</div>

<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;"><span>⚙️</span> Application Environment Configuration</h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Loaded from application config files (Secrets are masked for security).</span>
    </div>
    <div class="table-responsive">
        <table class="table" aria-label="System Settings">
            <thead>
                <tr>
                    <th style="width: 250px;">Configuration Key</th>
                    <th>Configured Runtime Value</th>
                    <th style="width: 140px;">Scope</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Application Name</strong></td>
                    <td><?= e($settings['app_name']) ?></td>
                    <td><span class="badge badge-neutral">Global Branding</span></td>
                </tr>
                <tr>
                    <td><strong>Deployment Environment</strong></td>
                    <td>
                        <span class="badge <?= $settings['app_env'] === 'production' ? 'badge-success' : 'badge-warning' ?>">
                            <?= e(strtoupper($settings['app_env'])) ?>
                        </span>
                    </td>
                    <td><span class="badge badge-neutral">Environment</span></td>
                </tr>
                <tr>
                    <td><strong>Application Base URL</strong></td>
                    <td><code style="font-size: 0.8125rem;"><?= e($settings['base_url']) ?></code></td>
                    <td><span class="badge badge-neutral">Routing</span></td>
                </tr>
                <tr>
                    <td><strong>Server Timezone</strong></td>
                    <td><?= e($settings['timezone']) ?> (<?= date('P') ?>)</td>
                    <td><span class="badge badge-neutral">Localization</span></td>
                </tr>
                <tr>
                    <td><strong>Primary Database Engine</strong></td>
                    <td><?= e($settings['database']) ?></td>
                    <td><span class="badge badge-neutral">Persistence</span></td>
                </tr>
                <tr>
                    <td><strong>Email Transport Driver</strong></td>
                    <td><?= e($settings['mail_driver']) ?></td>
                    <td><span class="badge badge-neutral">Communications</span></td>
                </tr>
                <tr>
                    <td><strong>Real-Time Notification Pipeline</strong></td>
                    <td><?= e($settings['realtime']) ?></td>
                    <td><span class="badge badge-neutral">WebSockets</span></td>
                </tr>
                <tr>
                    <td><strong>Security Credentials &amp; Secrets</strong></td>
                    <td><code style="font-size: 0.8125rem; color: var(--color-text-muted);">•••••••••••••••••••••••••••••••• (Protected)</code></td>
                    <td><span class="badge badge-success">Zero Exposure</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

