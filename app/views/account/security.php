<?php

/**
 * Self-Service Security & Trusted Devices Management View
 * Accessible to Employee, HR, and Admin roles via GET /account/security
 */

use App\Services\AuthRiskService;

$devices = is_array($devices ?? null) ? $devices : [];
$recentEvents = is_array($recentEvents ?? null) ? $recentEvents : [];
$securityState = is_array($securityState ?? null) ? $securityState : null;
$activeCount = (int)($activeCount ?? 0);
$maxDevices = (int)($maxDevices ?? 10);
$trustDays = (int)($trustDays ?? 30);
$employeeDays = (int)($employeeDays ?? 30);
$hrDays = (int)($hrDays ?? 7);
$role = strtolower((string)($role ?? 'employee'));
$loginAuthMethod = (string)($loginAuthMethod ?? 'email_otp');
$stepUpNotice = AuthRiskService::describeStepUpReason(is_string($stepUpReason ?? null) ? $stepUpReason : null);

$backUrl = url('/employee/profile');
$backLabel = 'Back to Profile';
if ($role === 'admin') {
    $backUrl = url('/admin/account');
    $backLabel = 'Back to Admin Account';
} elseif ($role === 'hr') {
    $backUrl = url('/hr/dashboard');
    $backLabel = 'Back to HR Dashboard';
}

$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('M j, Y · g:i A', $ts) : '—';
};

$roleDisplay = strtoupper($role);
?>

<div class="security-page-shell">
    <div class="page-header security-page-header">
        <div class="page-title-group">
            <div class="security-heading-row">
                <h1 class="security-page-title">Security &amp; Trusted Devices</h1>
                <span class="badge badge-primary"><?= e($roleDisplay) ?> TRUST POLICY: <?= (int)$trustDays ?> DAYS</span>
            </div>
            <p class="security-page-subtitle">
                Manage browsers remembered for Smart OTP login convenience, inspect recent sign-in activity, or recover from a lost device.
            </p>
        </div>
        <div class="page-actions security-page-actions">
            <a href="<?= e($backUrl) ?>" class="btn btn-secondary btn-sm">
                <?= icon('arrow-left', 16) ?>
                <span><?= e($backLabel) ?></span>
            </a>
        </div>
    </div>

    <?php if ($stepUpNotice !== null): ?>
        <div class="alert alert-warning security-alert-banner" role="alert">
            <span class="security-alert-icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
            <div>
                <strong>Fresh Email OTP Step-Up Required on Next Sign-In</strong>
                <p><?= e($stepUpNotice) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Top Summary Posture Grid -->
    <div class="security-posture-grid">
        <article class="card security-posture-card">
            <div class="security-posture-meta">
                <span class="security-posture-label">Active Trusted Browsers</span>
                <span class="security-posture-icon" aria-hidden="true"><?= icon('shield', 18) ?></span>
            </div>
            <div class="security-posture-value">
                <strong><?= $activeCount ?></strong>
                <span class="security-posture-cap">of <?= $maxDevices ?> max</span>
            </div>
            <p class="security-posture-note">
                Fixed expiration per browser: Employee (<?= $employeeDays ?>d) &middot; HR &amp; Admin (<?= $hrDays ?>d).
            </p>
        </article>

        <article class="card security-posture-card">
            <div class="security-posture-meta">
                <span class="security-posture-label">This Browser Status</span>
                <span class="security-posture-icon" aria-hidden="true"><?= icon('check-circle', 18) ?></span>
            </div>
            <div class="security-posture-value">
                <?php if (!empty($currentDevice)): ?>
                    <span class="badge badge-success">Remembered Browser</span>
                <?php else: ?>
                    <span class="badge badge-neutral">OTP Required Next Login</span>
                <?php endif; ?>
            </div>
            <p class="security-posture-note">
                <?php if (!empty($currentDevice)): ?>
                    <?= e((string)($currentDevice['device_name'] ?? 'Web Browser')) ?> &middot; Expires <?= e($formatDate($currentDevice['expires_at'] ?? null)) ?>.
                <?php else: ?>
                    To remember this browser, check <strong>Remember this device</strong> during Email OTP verification.
                <?php endif; ?>
            </p>
        </article>

        <article class="card security-posture-card">
            <div class="security-posture-meta">
                <span class="security-posture-label">Current Login Session</span>
                <span class="security-posture-icon" aria-hidden="true"><?= icon('lock', 18) ?></span>
            </div>
            <div class="security-posture-value">
                <?php if ($loginAuthMethod === 'trusted_device'): ?>
                    <span class="badge badge-primary">Password + Trusted Device</span>
                <?php else: ?>
                    <span class="badge badge-success">Password + Email OTP</span>
                <?php endif; ?>
            </div>
            <p class="security-posture-note">
                Signing out ends your PHP session (30m idle / 12h max) without revoking remembered browsers.
            </p>
        </article>
    </div>

    <!-- Trusted Devices Table Card -->
    <section class="card security-section-card" aria-labelledby="trustedDevicesHeading">
        <div class="card-header security-card-header">
            <div class="security-card-title-wrap">
                <h2 class="card-title" id="trustedDevicesHeading">
                    <span aria-hidden="true"><?= icon('shield', 18) ?></span>
                    <span>Remembered &amp; Recent Browsers</span>
                </h2>
                <span class="security-card-subtitle">Single-use cryptographic credentials rotate automatically on every trusted login.</span>
            </div>
            <div class="security-card-actions">
                <button
                    type="button"
                    class="btn btn-danger btn-sm"
                    id="openRevokeAllDialogBtn"
                    data-open-revoke-all
                    <?= $activeCount === 0 ? 'disabled aria-disabled="true"' : '' ?>
                >
                    <?= icon('trash', 15) ?>
                    <span>Revoke All Trusted Devices</span>
                </button>
            </div>
        </div>

        <div class="card-body security-table-card-body">
            <?php if (empty($devices)): ?>
                <div class="security-empty-state">
                    <div class="security-empty-icon" aria-hidden="true"><?= icon('shield', 28) ?></div>
                    <h3>No trusted browsers registered yet</h3>
                    <p>
                        On your next Email OTP verification, select <strong>Remember this device</strong> to skip repetitive OTP emails on a personal browser for up to <?= $trustDays ?> days.
                    </p>
                </div>
            <?php else: ?>
                <div class="security-table-wrap" tabindex="0" role="region" aria-labelledby="trustedDevicesHeading">
                    <table class="table security-devices-table">
                        <thead>
                            <tr>
                                <th>Browser &amp; OS</th>
                                <th>Last Known IP</th>
                                <th>Trusted On</th>
                                <th>Last Used</th>
                                <th>Expires</th>
                                <th>Status</th>
                                <th class="security-col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devices as $dev): ?>
                                <?php
                                $status = (string)($dev['device_status'] ?? 'active');
                                $isCurrent = !empty($dev['is_current_device']);
                                $deviceId = (int)($dev['id'] ?? 0);
                                $deviceLabel = (string)($dev['device_name'] ?? 'Web Browser');
                                $badgeClass = 'badge-neutral';
                                $statusText = ucfirst($status);
                                if ($status === 'active') {
                                    $badgeClass = 'badge-success';
                                    $statusText = 'Active';
                                } elseif ($status === 'revoked') {
                                    $badgeClass = 'badge-danger';
                                    $statusText = 'Revoked';
                                } elseif ($status === 'expired') {
                                    $badgeClass = 'badge-warning';
                                    $statusText = 'Expired';
                                }
                                ?>
                                <tr class="<?= $isCurrent ? 'security-row-current' : '' ?>">
                                    <td data-label="Browser &amp; OS">
                                        <div class="security-device-cell">
                                            <div class="security-device-name-row">
                                                <strong><?= e($deviceLabel) ?></strong>
                                                <?php if ($isCurrent): ?>
                                                    <span class="security-this-browser-pill">This Browser</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="security-device-subtext">
                                                <?= e((string)($dev['browser_name'] ?? 'Browser')) ?> &middot; <?= e((string)($dev['os_name'] ?? 'OS')) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td data-label="Last Known IP">
                                        <code class="security-ip-code"><?= e((string)($dev['last_ip'] ?: '—')) ?></code>
                                    </td>
                                    <td data-label="Trusted On"><?= e($formatDate($dev['created_at'] ?? null)) ?></td>
                                    <td data-label="Last Used"><?= e($formatDate($dev['last_used_at'] ?? null)) ?></td>
                                    <td data-label="Expires">
                                        <?= e($formatDate($dev['expires_at'] ?? null)) ?>
                                    </td>
                                    <td data-label="Status">
                                        <span class="badge <?= $badgeClass ?>"><?= e($statusText) ?></span>
                                        <?php if ($status === 'revoked' && !empty($dev['revocation_reason'])): ?>
                                            <div class="security-revoke-reason"><?= e((string)$dev['revocation_reason']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Action" class="security-col-action">
                                        <?php if ($status === 'active'): ?>
                                            <button
                                                type="button"
                                                class="btn btn-secondary btn-sm security-revoke-btn"
                                                data-revoke-device
                                                data-device-id="<?= $deviceId ?>"
                                                data-device-name="<?= e($deviceLabel) ?>"
                                                data-is-current="<?= $isCurrent ? '1' : '0' ?>"
                                                data-revoke-url="<?= e(url('/account/security/devices/' . $deviceId . '/revoke')) ?>"
                                            >
                                                Revoke
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted text-xs">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Lost Device Recovery & Security Architecture Explainer -->
    <div class="security-two-col-grid">
        <section class="card security-section-card" id="recovery" aria-labelledby="recoveryHeading">
            <div class="card-header">
                <h2 class="card-title" id="recoveryHeading">
                    <span aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
                    <span>Lost or Compromised Device Recovery</span>
                </h2>
            </div>
            <div class="card-body">
                <p class="security-body-copy">
                    Lost a laptop, used a shared computer by mistake, or noticed unfamiliar browser activity? Confirm your current password to immediately revoke all remembered browsers and force Email OTP verification before any replacement device can be trusted.
                </p>

                <form method="POST" action="<?= e(url('/account/security/recover')) ?>" class="security-recovery-form">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="recoveryCurrentPassword" class="form-label">Current Account Password</label>
                        <input
                            type="password"
                            id="recoveryCurrentPassword"
                            name="current_password"
                            class="form-control"
                            autocomplete="current-password"
                            required
                            placeholder="Confirm your current password to execute recovery"
                        >
                        <small class="form-hint-text">Password verification is required even if you signed in from a trusted browser.</small>
                    </div>
                    <div class="security-form-actions">
                        <button type="submit" class="btn btn-danger">
                            <?= icon('shield', 16) ?>
                            <span>Revoke All Devices &amp; Require OTP</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card security-section-card" aria-labelledby="howItWorksHeading">
            <div class="card-header">
                <h2 class="card-title" id="howItWorksHeading">
                    <span aria-hidden="true"><?= icon('info', 18) ?></span>
                    <span>How Smart OTP &amp; Trust Policies Work</span>
                </h2>
            </div>
            <div class="card-body">
                <ul class="security-policy-list">
                    <li>
                        <strong>Password Always Comes First:</strong> Trusted browser credentials never replace password authentication. Email &amp; password are always verified before device trust is checked.
                    </li>
                    <li>
                        <strong>Single-Use Rotating Tokens:</strong> Each trusted browser holds a host-only <code>HttpOnly</code> cryptographic selector and hashed secret validator that rotates on every trusted login without extending its fixed expiration date.
                    </li>
                    <li>
                        <strong>Risk-Based Step-Up Protection:</strong> Password updates, email changes, account recovery, token anomalies, or correlated HR/Admin context shifts trigger Email OTP automatically—while harmless Wi-Fi/mobile IP changes alone do not lock you out.
                    </li>
                    <li>
                        <strong>Session vs. Device Independence:</strong> Ordinary <em>Sign Out</em> ends your PHP session while preserving remembered browsers until you revoke them or they reach their fixed expiration.
                    </li>
                    <li>
                        <strong>Assurance Disclosure:</strong> Email OTP with trusted devices prevents repeated email prompts on recognized personal browsers; however, Email OTP is not phishing-resistant authentication like hardware keys or TOTP apps.
                    </li>
                </ul>
            </div>
        </section>
    </div>

    <!-- User-Scoped Recent Auth Telemetry -->
    <section class="card security-section-card" aria-labelledby="recentAuthHeading">
        <div class="card-header">
            <h2 class="card-title" id="recentAuthHeading">
                <span aria-hidden="true"><?= icon('activity', 18) ?></span>
                <span>Recent Sign-In &amp; Device Security Events</span>
            </h2>
            <span class="security-card-subtitle">Metadata scoped strictly to your account (no tokens or verification codes recorded).</span>
        </div>
        <div class="card-body security-table-card-body">
            <?php if (empty($recentEvents)): ?>
                <div class="security-empty-state security-empty-compact">
                    <p>No recent authentication or trusted-device audit events recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="security-table-wrap" tabindex="0" role="region" aria-labelledby="recentAuthHeading">
                    <table class="table security-events-table">
                        <thead>
                            <tr>
                                <th>Date &amp; Time</th>
                                <th>Event</th>
                                <th>Summary</th>
                                <th>IP Address</th>
                                <th>Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentEvents as $event): ?>
                                <?php
                                $outcome = strtoupper((string)($event['status'] ?? 'INFO'));
                                $outcomeBadge = 'badge-neutral';
                                if ($outcome === 'SUCCESS') {
                                    $outcomeBadge = 'badge-success';
                                } elseif ($outcome === 'WARNING') {
                                    $outcomeBadge = 'badge-warning';
                                } elseif ($outcome === 'FAILURE') {
                                    $outcomeBadge = 'badge-danger';
                                }
                                ?>
                                <tr>
                                    <td data-label="Date &amp; Time"><?= e($formatDate($event['created_at'] ?? null)) ?></td>
                                    <td data-label="Event"><code class="security-event-code"><?= e((string)($event['action'] ?? '')) ?></code></td>
                                    <td data-label="Summary"><?= e((string)($event['description'] ?? '')) ?></td>
                                    <td data-label="IP Address"><code class="security-ip-code"><?= e((string)($event['ip_address'] ?: '—')) ?></code></td>
                                    <td data-label="Outcome"><span class="badge <?= $outcomeBadge ?>"><?= e($outcome) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- Native Accessible Dialog: Revoke Single Trusted Device -->
<dialog id="revokeDeviceDialog" class="security-dialog" aria-labelledby="revokeDeviceDialogTitle">
    <div class="security-dialog-head">
        <h2 class="security-dialog-title" id="revokeDeviceDialogTitle">Revoke Trusted Browser</h2>
        <button type="button" class="security-dialog-close" data-close-dialog aria-label="Close revoke device dialog">
            <?= icon('x', 18) ?>
        </button>
    </div>
    <div class="security-dialog-body">
        <p id="revokeDeviceDialogMessage">
            Revoking this trusted browser requires full Email OTP verification the next time you sign in from it.
        </p>
        <p class="security-dialog-current-note" id="revokeDeviceCurrentWarning" hidden>
            <strong>Notice:</strong> You are revoking the browser you are currently using. Your active login session remains open until you sign out, and your next login here will ask for an Email OTP.
        </p>
    </div>
    <div class="security-dialog-foot">
        <button type="button" class="btn btn-secondary" data-close-dialog autofocus>Cancel</button>
        <form id="revokeDeviceDialogForm" method="POST" action="">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Revoke Device</button>
        </form>
    </div>
</dialog>

<!-- Native Accessible Dialog: Revoke All Trusted Devices -->
<dialog id="revokeAllDevicesDialog" class="security-dialog" aria-labelledby="revokeAllDialogTitle">
    <div class="security-dialog-head">
        <h2 class="security-dialog-title" id="revokeAllDialogTitle">Revoke All Trusted Devices</h2>
        <button type="button" class="security-dialog-close" data-close-dialog aria-label="Close revoke all devices dialog">
            <?= icon('x', 18) ?>
        </button>
    </div>
    <div class="security-dialog-body">
        <p>
            Are you sure you want to revoke all <strong><?= $activeCount ?></strong> active trusted browser(s) on your account?
        </p>
        <p>
            Every browser — including this one — will require Email OTP verification on your next sign-in.
        </p>
    </div>
    <div class="security-dialog-foot">
        <button type="button" class="btn btn-secondary" data-close-dialog autofocus>Cancel</button>
        <form method="POST" action="<?= e(url('/account/security/devices/revoke-all')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Revoke All Devices</button>
        </form>
    </div>
</dialog>

<script src="<?= asset('js/account_security.js') ?>" defer></script>
