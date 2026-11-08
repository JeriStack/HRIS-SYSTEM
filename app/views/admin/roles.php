<?php

declare(strict_types=1);

/**
 * Enterprise Admin Roles & Permissions Management Dashboard
 * Route: GET /admin/roles
 * Access: Administrator Only (Protected by RoleMiddleware(['admin']))
 *
 * Implements 4 primary governance sections:
 * 1. Roles Overview
 * 2. Permission Matrix (The Centerpiece)
 * 3. User Access Management
 * 4. Permission Audit History
 */

$rolesOverview = $rolesOverview ?? [];
$groupedPermissions = $groupedPermissions ?? [];
$rolePermissionsMap = $rolePermissionsMap ?? [];
$recentAuditLogs = $recentAuditLogs ?? [];
$userAccessList = $userAccessList ?? [];

$totalPermCount = count(\Core\PermissionRegistry::PERMISSIONS);
$hrPerms = $rolePermissionsMap[\Core\PermissionRegistry::ROLE_HR] ?? [];
$hrActiveCount = count(array_filter($hrPerms, fn($v) => $v === true));
$empPerms = $rolePermissionsMap[\Core\PermissionRegistry::ROLE_EMPLOYEE] ?? [];
$empActiveCount = count(array_filter($empPerms, fn($v) => $v === true));

$activeTab = $_GET['tab'] ?? 'matrix';
if (!in_array($activeTab, ['overview', 'matrix', 'users', 'audit'], true)) {
    $activeTab = 'matrix';
}

$matrixRole = strtolower(trim((string)($_GET['role'] ?? 'hr')));
if (!in_array($matrixRole, ['hr', 'employee'], true)) {
    $matrixRole = 'hr';
}

$currentRolePerms = $matrixRole === 'hr' ? $hrPerms : $empPerms;
$currentRoleActiveCount = $matrixRole === 'hr' ? $hrActiveCount : $empActiveCount;
$targetRoleName = $matrixRole === 'hr' ? 'Human Resources' : 'Employee';
?>

<!-- Scoped Custom Switch & Matrix Styling -->
<style>
.perm-toggle-switch {
    position: relative;
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
    gap: 0.625rem;
}
.perm-toggle-switch input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.perm-toggle-slider {
    position: relative;
    width: 44px;
    height: 24px;
    background-color: var(--color-border, #cbd5e1);
    border-radius: 24px;
    transition: background-color 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    flex-shrink: 0;
}
.perm-toggle-slider::before {
    content: "";
    position: absolute;
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: #ffffff;
    border-radius: 50%;
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
}
.perm-toggle-switch input:checked + .perm-toggle-slider {
    background-color: var(--color-success, #10b981);
}
.perm-toggle-switch input:checked + .perm-toggle-slider::before {
    transform: translateX(20px);
}
.perm-toggle-switch input:focus-visible + .perm-toggle-slider {
    outline: 2px solid var(--color-primary, #2563eb);
    outline-offset: 2px;
}
.perm-toggle-switch input:disabled + .perm-toggle-slider {
    opacity: 0.6;
    cursor: not-allowed;
    background-color: var(--color-primary, #2563eb);
}
.perm-status-label {
    font-size: 0.8125rem;
    font-weight: 600;
    min-width: 52px;
}
.perm-status-label.allowed {
    color: var(--color-success, #10b981);
}
.perm-status-label.denied {
    color: var(--color-text-muted, #64748b);
}
.unsaved-banner {
    position: sticky;
    top: 1rem;
    z-index: 40;
    background: #fffbeb;
    border: 1px solid #fef3c7;
    border-left: 4px solid var(--color-warning, #f59e0b);
    border-radius: var(--radius-md, 8px);
    padding: 0.875rem 1.25rem;
    box-shadow: var(--shadow-md, 0 4px 6px -1px rgba(0,0,0,0.1));
    margin-bottom: var(--space-4, 1rem);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.unsaved-banner.is-hidden {
    display: none !important;
}
.pulse-badge {
    animation: pulseBadge 1.8s infinite;
}
@keyframes pulseBadge {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
.user-avatar-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--color-bg-muted, #f1f5f9);
    border: 1px solid var(--color-border, #e2e8f0);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--color-text-main, #0f172a);
    flex-shrink: 0;
}
</style>

<!-- =========================================================================
     TOP BREADCRUMBS & PAGE HEADER
     ========================================================================= -->
<div class="breadcrumb" style="margin-bottom: var(--space-3); font-size: 0.8125rem; color: var(--color-text-muted);">
    <a href="<?= url('/admin/dashboard') ?>" style="color: inherit; text-decoration: none;">Dashboard</a>
    <span style="margin: 0 0.5rem;">/</span>
    <span>Administration</span>
    <span style="margin: 0 0.5rem;">/</span>
    <span style="color: var(--color-text-main); font-weight: 600;">Roles &amp; Permissions</span>
</div>

<div class="page-header" style="margin-bottom: var(--space-6);">
    <div class="page-title-group">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem; flex-wrap: wrap;">
            <h1 style="margin: 0; font-size: 1.625rem; font-weight: 700; color: var(--color-text-main);">Roles &amp; Permissions</h1>
            <span class="badge badge-primary">DATABASE-DRIVEN RBAC</span>
        </div>
        <p style="margin: 0; font-size: 0.9375rem; color: var(--color-text-muted);">
            Manage application roles and control which features users can access across all system modules.
        </p>
    </div>
    <div class="page-actions" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="<?= url('/admin/users') ?>" class="btn btn-outline btn-sm">
            <span>👥</span> User Accounts
        </a>
        <a href="<?= url('/admin/provisioning') ?>" class="btn btn-primary btn-sm">
            <span>🛡️</span> Admin Provisioning
        </a>
    </div>
</div>

<!-- Global Status Flash Feedback -->
<?php if (\Core\Session::hasFlash('success')): ?>
    <div class="alert alert-success" role="alert" style="margin-bottom: var(--space-5); display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>✅</span>
            <span><?= e(\Core\Session::getFlash('success')) ?></span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; font-size:1.1rem; color:inherit;" aria-label="Dismiss alert">&times;</button>
    </div>
<?php endif; ?>

<?php if (\Core\Session::hasFlash('error')): ?>
    <div class="alert alert-danger" role="alert" style="margin-bottom: var(--space-5); display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span>⚠️</span>
            <span><?= e(\Core\Session::getFlash('error')) ?></span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; font-size:1.1rem; color:inherit;" aria-label="Dismiss alert">&times;</button>
    </div>
<?php endif; ?>

<!-- =========================================================================
     PRIMARY NAVIGATION TABS
     ========================================================================= -->
<div class="tabs-nav" style="display: flex; gap: 0.5rem; border-bottom: 1px solid var(--color-border); margin-bottom: var(--space-6); overflow-x: auto;" role="tablist" aria-label="RBAC Governance Sections">
    <a href="<?= url('/admin/roles?tab=overview') ?>" class="btn btn-sm <?= $activeTab === 'overview' ? 'btn-primary' : 'btn-outline' ?>" role="tab" aria-selected="<?= $activeTab === 'overview' ? 'true' : 'false' ?>" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
        <span>📊</span> Roles Overview
    </a>
    <a href="<?= url('/admin/roles?tab=matrix&role=' . $matrixRole) ?>" class="btn btn-sm <?= $activeTab === 'matrix' ? 'btn-primary' : 'btn-outline' ?>" role="tab" aria-selected="<?= $activeTab === 'matrix' ? 'true' : 'false' ?>" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
        <span>🛡️</span> Permission Matrix
    </a>
    <a href="<?= url('/admin/roles?tab=users') ?>" class="btn btn-sm <?= $activeTab === 'users' ? 'btn-primary' : 'btn-outline' ?>" role="tab" aria-selected="<?= $activeTab === 'users' ? 'true' : 'false' ?>" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
        <span>👥</span> User Access Management
    </a>
    <a href="<?= url('/admin/roles?tab=audit') ?>" class="btn btn-sm <?= $activeTab === 'audit' ? 'btn-primary' : 'btn-outline' ?>" role="tab" aria-selected="<?= $activeTab === 'audit' ? 'true' : 'false' ?>" style="border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
        <span>📜</span> Permission Audit History
    </a>
</div>

<!-- =========================================================================
     SECTION 1: ROLES OVERVIEW (PAGE 1)
     ========================================================================= -->
<?php if ($activeTab === 'overview'): ?>
<div class="roles-overview-section" style="margin-bottom: var(--space-6);">
    <div style="margin-bottom: var(--space-4);">
        <h2 style="font-size: 1.125rem; font-weight: 700; color: var(--color-text-main); margin: 0 0 0.25rem 0;">System Roles Overview</h2>
        <p style="font-size: 0.875rem; color: var(--color-text-muted); margin: 0;">
            NEXORA HR enforces a strict three-role architecture. Review current user assignments and operational capabilities.
        </p>
    </div>

    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-5); margin-bottom: var(--space-6);">
        <!-- Administrator Role Card -->
        <div class="stat-card accent-purple" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 260px;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.5rem;" aria-hidden="true">🛡️</span>
                        <span style="font-weight: 700; font-size: 1.125rem; color: var(--color-text-main);">Administrator</span>
                    </div>
                    <span class="badge badge-primary">SYSTEM PROTECTED</span>
                </div>
                <p style="font-size: 0.8125rem; color: var(--color-text-muted); line-height: 1.45; margin-bottom: 1rem;">
                    Full application-level authority across all system modules, workforce management, audit logging, and configuration oversight.
                </p>
                <div style="background: var(--color-bg-muted); border-radius: var(--radius-md); padding: 0.75rem 1rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; margin-bottom: 0.375rem;">
                        <span style="color: var(--color-text-muted);">Assigned Accounts:</span>
                        <strong style="color: var(--color-text-main);"><?= (int)($rolesOverview['admin']['user_count'] ?? 1) ?> User(s)</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; margin-bottom: 0.375rem;">
                        <span style="color: var(--color-text-muted);">Enabled Permissions:</span>
                        <strong style="color: var(--color-primary);"><?= $totalPermCount ?> / <?= $totalPermCount ?> (100%)</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8125rem;">
                        <span style="color: var(--color-text-muted);">Role Governance:</span>
                        <strong style="color: var(--color-success);">Immutable Authority</strong>
                    </div>
                </div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--color-text-muted); font-style: italic; margin-bottom: 0.75rem;">
                    Protected from deletion, lockout, and ordinary permission editing.
                </div>
                <a href="<?= url('/admin/roles?tab=matrix&role=hr') ?>" class="btn btn-outline btn-sm" style="width: 100%; justify-content: center; opacity: 0.75; cursor: default;" onclick="event.preventDefault();">
                    <span>🔒</span> Protected System Role
                </a>
            </div>
        </div>

        <!-- Human Resources Role Card -->
        <div class="stat-card accent-blue" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 260px;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.5rem;" aria-hidden="true">👥</span>
                        <span style="font-weight: 700; font-size: 1.125rem; color: var(--color-text-main);">Human Resources</span>
                    </div>
                    <span class="badge badge-success">CONFIGURABLE</span>
                </div>
                <p style="font-size: 0.8125rem; color: var(--color-text-muted); line-height: 1.45; margin-bottom: 1rem;">
                    Operational human resource management, employee records, organizational departments, job positions, and leave approval queues.
                </p>
                <div style="background: var(--color-bg-muted); border-radius: var(--radius-md); padding: 0.75rem 1rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; margin-bottom: 0.375rem;">
                        <span style="color: var(--color-text-muted);">Assigned Accounts:</span>
                        <strong style="color: var(--color-text-main);"><?= (int)($rolesOverview['hr']['user_count'] ?? 0) ?> User(s)</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; margin-bottom: 0.375rem;">
                        <span style="color: var(--color-text-muted);">Enabled Permissions:</span>
                        <strong style="color: var(--color-primary);"><?= $hrActiveCount ?> / <?= $totalPermCount ?> (<?= round(($hrActiveCount / max(1, $totalPermCount)) * 100) ?>%)</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8125rem;">
                        <span style="color: var(--color-text-muted);">Scope:</span>
                        <strong style="color: var(--color-text-main);">Workforce Operations</strong>
                    </div>
                </div>
            </div>
            <div>
                <a href="<?= url('/admin/roles?tab=matrix&role=hr') ?>" class="btn btn-primary btn-sm" style="width: 100%; justify-content: center;">
                    <span>⚙️</span> Manage HR Permissions &rarr;
                </a>
            </div>
        </div>

        <!-- Employee Role Card -->
        <div class="stat-card accent-green" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 260px;">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.5rem;" aria-hidden="true">👤</span>
                        <span style="font-weight: 700; font-size: 1.125rem; color: var(--color-text-main);">Regular Employee</span>
                    </div>
                    <span class="badge badge-success">CONFIGURABLE</span>
                </div>
                <p style="font-size: 0.8125rem; color: var(--color-text-muted); line-height: 1.45; margin-bottom: 1rem;">
                    Employee personal self-service portal restricted strictly to personal identity, leave balance inspection, and personal applications.
                </p>
                <div style="background: var(--color-bg-muted); border-radius: var(--radius-md); padding: 0.75rem 1rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; margin-bottom: 0.375rem;">
                        <span style="color: var(--color-text-muted);">Assigned Accounts:</span>
                        <strong style="color: var(--color-text-main);"><?= (int)($rolesOverview['employee']['user_count'] ?? 0) ?> User(s)</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; margin-bottom: 0.375rem;">
                        <span style="color: var(--color-text-muted);">Enabled Permissions:</span>
                        <strong style="color: var(--color-primary);"><?= $empActiveCount ?> / <?= $totalPermCount ?> (<?= round(($empActiveCount / max(1, $totalPermCount)) * 100) ?>%)</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8125rem;">
                        <span style="color: var(--color-text-muted);">Scope:</span>
                        <strong style="color: var(--color-text-main);">Self-Service Only</strong>
                    </div>
                </div>
            </div>
            <div>
                <a href="<?= url('/admin/roles?tab=matrix&role=employee') ?>" class="btn btn-primary btn-sm" style="width: 100%; justify-content: center;">
                    <span>⚙️</span> Manage Employee Permissions &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Governance Invariants Policy Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;">
                <span>🛡️</span> RBAC Security Architecture &amp; Invariants
            </h3>
            <span class="badge badge-neutral">FAIL-CLOSED DESIGN</span>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-2" style="gap: var(--space-4);">
                <div style="border-left: 3px solid var(--color-primary); padding-left: 1rem;">
                    <div style="font-weight: 600; font-size: 0.875rem; color: var(--color-text-main); margin-bottom: 0.25rem;">
                        1. Immutable Administrator Protection
                    </div>
                    <div style="font-size: 0.8125rem; color: var(--color-text-muted); line-height: 1.4;">
                        The Administrator role permanently retains all 33 system capabilities. Backend controllers reject any attempt to modify or strip Admin permissions.
                    </div>
                </div>
                <div style="border-left: 3px solid var(--color-success); padding-left: 1rem;">
                    <div style="font-weight: 600; font-size: 0.875rem; color: var(--color-text-main); margin-bottom: 0.25rem;">
                        2. Deny-by-Default Policy
                    </div>
                    <div style="font-size: 0.8125rem; color: var(--color-text-muted); line-height: 1.4;">
                        Any unassigned permission, unknown role identifier, or unregistered capability evaluates strictly to false, halting unauthorized requests with HTTP 403.
                    </div>
                </div>
                <div style="border-left: 3px solid var(--color-warning); padding-left: 1rem;">
                    <div style="font-weight: 600; font-size: 0.875rem; color: var(--color-text-main); margin-bottom: 0.25rem;">
                        3. Atomic Transaction Persistence
                    </div>
                    <div style="font-size: 0.8125rem; color: var(--color-text-muted); line-height: 1.4;">
                        Permission assignments execute within database transactions (<code style="font-size:0.75rem;">START TRANSACTION</code> ... <code style="font-size:0.75rem;">COMMIT</code>) ensuring zero partial or corrupted states.
                    </div>
                </div>
                <div style="border-left: 3px solid #8b5cf6; padding-left: 1rem;">
                    <div style="font-weight: 600; font-size: 0.875rem; color: var(--color-text-main); margin-bottom: 0.25rem;">
                        4. Real-Time Cache Invalidation
                    </div>
                    <div style="font-size: 0.8125rem; color: var(--color-text-muted); line-height: 1.4;">
                        Role permission changes invalidate in-memory authorization caches immediately, enforcing updated permissions on the user's very next HTTP request.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- =========================================================================
     SECTION 2: PERMISSION MATRIX (PAGE 2 — CENTERPIECE)
     ========================================================================= -->
<?php if ($activeTab === 'matrix'): ?>
<div class="permission-matrix-section" style="margin-bottom: var(--space-6);">
    <!-- Role Selector Bar -->
    <div class="card" style="margin-bottom: var(--space-4); background: var(--color-bg-surface); padding: var(--space-4);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span style="font-size: 0.8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); display: block; margin-bottom: 0.5rem;">
                    Select Target Role to Configure:
                </span>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="<?= url('/admin/roles?tab=matrix&role=hr') ?>" 
                       class="btn btn-sm <?= $matrixRole === 'hr' ? 'btn-primary' : 'btn-outline' ?>"
                       style="display: flex; align-items: center; gap: 0.5rem;">
                        <span>👥</span>
                        <strong>Human Resources</strong>
                        <span class="badge <?= $matrixRole === 'hr' ? 'badge-neutral' : 'badge-primary' ?>" style="font-size: 0.75rem;">
                            <?= $hrActiveCount ?>/<?= $totalPermCount ?> Active
                        </span>
                    </a>
                    <a href="<?= url('/admin/roles?tab=matrix&role=employee') ?>" 
                       class="btn btn-sm <?= $matrixRole === 'employee' ? 'btn-primary' : 'btn-outline' ?>"
                       style="display: flex; align-items: center; gap: 0.5rem;">
                        <span>👤</span>
                        <strong>Regular Employee</strong>
                        <span class="badge <?= $matrixRole === 'employee' ? 'badge-neutral' : 'badge-primary' ?>" style="font-size: 0.75rem;">
                            <?= $empActiveCount ?>/<?= $totalPermCount ?> Active
                        </span>
                    </a>
                    <div style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.75rem; background: var(--color-bg-muted); border: 1px dashed var(--color-border); border-radius: var(--radius-md); font-size: 0.8125rem; color: var(--color-text-muted);">
                        <span>🛡️</span>
                        <span>Administrator: <strong>33/33 Active (Immutable)</strong></span>
                    </div>
                </div>
            </div>

            <!-- Permission Count Summary -->
            <div style="text-align: right;">
                <div style="font-size: 0.8125rem; color: var(--color-text-muted);">Configuring Role:</div>
                <div style="font-size: 1.125rem; font-weight: 700; color: var(--color-text-main);">
                    <?= e($targetRoleName) ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--color-primary); font-weight: 600;">
                    <span id="activeCountDisplay"><?= $currentRoleActiveCount ?></span> of <?= $totalPermCount ?> Permissions Enabled
                </div>
            </div>
        </div>
    </div>

    <!-- Unsaved Changes Floating Banner -->
    <div id="unsavedChangesBanner" class="unsaved-banner is-hidden" role="alert">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 1.25rem;" aria-hidden="true">⚠️</span>
            <div>
                <strong style="color: #92400e;">Unsaved Modifications:</strong>
                <span style="color: #78350f; font-size: 0.875rem;">
                    You have made changes to the <?= e($targetRoleName) ?> permissions (<span id="unsavedCount">0</span> modified).
                </span>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="button" class="btn btn-outline btn-sm" onclick="discardMatrixChanges()">Discard</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="openSaveModal()">Review &amp; Save Changes</button>
        </div>
    </div>

    <!-- Interactive Matrix Card -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;">
                    <span>🛡️</span> Permission Management — <?= e($targetRoleName) ?>
                </h2>
                <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                    Configure which modules and actions this role can access. Changes require explicit confirmation.
                </span>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button type="button" class="btn btn-outline btn-sm" onclick="bulkToggleVisible(true)">Select All Visible</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="bulkToggleVisible(false)">Deselect All Visible</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="openSaveModal()">Save Changes</button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div style="padding: var(--space-4) var(--space-6); background: var(--color-bg-muted); border-bottom: 1px solid var(--color-border); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; flex: 1; max-width: 700px;">
                <!-- Real-time Search -->
                <div style="position: relative; flex: 1; min-width: 220px;">
                    <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.875rem; color: var(--color-text-muted);" aria-hidden="true">🔍</span>
                    <input type="text" id="permSearchInput" class="form-control" placeholder="Search permissions by name, slug, description..." 
                           style="padding-left: 2.25rem; font-size: 0.875rem;" oninput="filterMatrixRows()">
                </div>
                <!-- Module Dropdown Filter -->
                <div style="min-width: 200px;">
                    <select id="moduleFilterSelect" class="form-control" style="font-size: 0.875rem;" onchange="filterMatrixRows()">
                        <option value="">All Functional Modules (<?= count($groupedPermissions) ?>)</option>
                        <?php foreach (array_keys($groupedPermissions) as $modName): ?>
                            <option value="<?= e($modName) ?>"><?= e($modName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
                Showing <strong id="visibleRowCount"><?= $totalPermCount ?></strong> of <?= $totalPermCount ?> Permissions
            </div>
        </div>

        <!-- Main Form Wrapper -->
        <form method="POST" action="<?= url('/admin/roles/permissions') ?>" id="permissionMatrixForm">
            <?= csrf_field() ?>
            <input type="hidden" name="role" value="<?= e($matrixRole) ?>">

            <div class="table-responsive">
                <table class="table table-hover" id="permMatrixTable" aria-label="Role Permission Matrix">
                    <thead>
                        <tr>
                            <th style="width: 180px;">Module</th>
                            <th>Permission Identifier &amp; Action</th>
                            <th style="width: 160px; text-align: center;">Access Status</th>
                            <th>Operational Policy Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groupedPermissions as $moduleName => $perms): ?>
                            <tr class="module-header-row" data-module="<?= e($moduleName) ?>" style="background: var(--color-bg-muted);">
                                <td colspan="4" style="padding: 0.625rem 1rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div style="font-weight: 700; font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-main);">
                                            <span>📂</span> <?= e($moduleName) ?> Module (<?= count($perms) ?>)
                                        </div>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button type="button" class="btn btn-outline btn-xs" style="font-size: 0.75rem; padding: 0.125rem 0.5rem;" onclick="toggleModuleCheckboxes('<?= e($moduleName) ?>', true)">
                                                Enable All
                                            </button>
                                            <button type="button" class="btn btn-outline btn-xs" style="font-size: 0.75rem; padding: 0.125rem 0.5rem;" onclick="toggleModuleCheckboxes('<?= e($moduleName) ?>', false)">
                                                Disable All
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php foreach ($perms as $slug => $meta): ?>
                                <?php $isChecked = !empty($currentRolePerms[$slug]); ?>
                                <tr class="perm-row" data-module="<?= e($moduleName) ?>" data-slug="<?= e($slug) ?>" data-name="<?= e($meta['name']) ?>" data-desc="<?= e($meta['description']) ?>">
                                    <td style="color: var(--color-text-muted); font-size: 0.8125rem;">
                                        <span class="badge badge-neutral" style="font-size: 0.75rem;"><?= e($moduleName) ?></span>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--color-text-main); font-size: 0.875rem;">
                                            <?= e($meta['name']) ?>
                                        </div>
                                        <code style="font-size: 0.75rem; color: var(--color-primary);"><?= e($slug) ?></code>
                                    </td>
                                    <td style="text-align: center;">
                                        <label class="perm-toggle-switch" for="perm_cb_<?= e(str_replace('.', '_', $slug)) ?>">
                                            <input type="checkbox" 
                                                   name="permissions[]" 
                                                   value="<?= e($slug) ?>" 
                                                   id="perm_cb_<?= e(str_replace('.', '_', $slug)) ?>"
                                                   class="perm-checkbox"
                                                   data-slug="<?= e($slug) ?>"
                                                   data-initial="<?= $isChecked ? '1' : '0' ?>"
                                                   <?= $isChecked ? 'checked' : '' ?>
                                                   onchange="onPermissionToggleChange(this)"
                                                   aria-label="Toggle <?= e($meta['name']) ?>">
                                            <span class="perm-toggle-slider" aria-hidden="true"></span>
                                            <span class="perm-status-label <?= $isChecked ? 'allowed' : 'denied' ?>">
                                                <?= $isChecked ? 'Allowed' : 'Denied' ?>
                                            </span>
                                        </label>
                                    </td>
                                    <td style="font-size: 0.8125rem; color: var(--color-text-muted); line-height: 1.4;">
                                        <?= e($meta['description']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Empty Search State -->
            <div id="noMatchMessage" style="display: none; padding: 3rem 1.5rem; text-align: center; color: var(--color-text-muted);">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;" aria-hidden="true">🔍</div>
                <div style="font-weight: 600; color: var(--color-text-main); margin-bottom: 0.25rem;">No matching permissions found</div>
                <div style="font-size: 0.875rem;">Try adjusting your keyword or clearing the module filter.</div>
            </div>

            <div class="card-footer" style="padding: var(--space-4) var(--space-6); background: var(--color-bg-muted); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                    Atomic update: Changes are wrapped in a database transaction and logged to <code style="font-size:0.75rem;">permission_audit_logs</code>.
                </span>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="discardMatrixChanges()">Discard Changes</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openSaveModal()">Review &amp; Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     SAVE CONFIRMATION MODAL DIALOG
     ========================================================================= -->
<div class="modal-backdrop" id="saveConfirmModal" role="dialog" aria-modal="true" aria-labelledby="saveModalTitle">
    <div class="modal" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title" id="saveModalTitle" style="display: flex; align-items: center; gap: 0.5rem;">
                <span>🛡️</span> Confirm Permission Policy Changes
            </h3>
            <button type="button" class="modal-close" onclick="closeSaveModal()" aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 0.875rem; color: var(--color-text-muted); margin: 0 0 1rem 0;">
                You are about to modify the database-backed capability grants for <strong><?= e($targetRoleName) ?></strong>.
                All active accounts with this role will immediately reflect these changes upon their next request.
            </p>

            <!-- Change Statistics Badges -->
            <div style="display: flex; gap: 1rem; margin-bottom: 1.25rem;">
                <div style="flex: 1; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-md); padding: 0.75rem; text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 700; color: #047857;" id="modalGrantCount">0</div>
                    <div style="font-size: 0.75rem; font-weight: 600; color: #065f46; text-transform: uppercase;">Permissions to Grant</div>
                </div>
                <div style="flex: 1; background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-md); padding: 0.75rem; text-align: center;">
                    <div style="font-size: 1.25rem; font-weight: 700; color: #b91c1c;" id="modalRevokeCount">0</div>
                    <div style="font-size: 0.75rem; font-weight: 600; color: #991b1b; text-transform: uppercase;">Permissions to Revoke</div>
                </div>
            </div>

            <!-- Sensitive Permission Alert (populated dynamically) -->
            <div id="modalSensitiveAlert" style="display: none; background: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid var(--color-warning); border-radius: var(--radius-md); padding: 0.75rem 1rem; margin-bottom: 1rem;">
                <strong style="color: #92400e; font-size: 0.8125rem;">⚠️ Sensitive Capability Warning:</strong>
                <p id="modalSensitiveText" style="margin: 0.25rem 0 0 0; font-size: 0.8125rem; color: #78350f;"></p>
            </div>

            <!-- List of Affected Permissions -->
            <div style="font-weight: 600; font-size: 0.8125rem; color: var(--color-text-main); margin-bottom: 0.5rem;">
                Detailed Modifications List:
            </div>
            <div id="modalChangesList" style="max-height: 200px; overflow-y: auto; background: var(--color-bg-muted); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.75rem; font-size: 0.8125rem;">
                <!-- Populated dynamically via JS -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeSaveModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="confirmSaveSubmitBtn" onclick="submitPermissionMatrixForm()">
                Confirm &amp; Apply Changes
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- =========================================================================
     SECTION 3: USER ACCESS MANAGEMENT (PAGE 3)
     ========================================================================= -->
<?php if ($activeTab === 'users'): ?>
<div class="user-access-section" style="margin-bottom: var(--space-6);">
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;">
                    <span>👥</span> User Access Management
                </h2>
                <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                    Inspect account-level access, assigned roles, and effective permissions across all users.
                </span>
            </div>
            <a href="<?= url('/admin/users') ?>" class="btn btn-outline btn-sm">
                <span>⚙️</span> Manage User Accounts &rarr;
            </a>
        </div>

        <!-- Filter & Search Toolbar -->
        <div style="padding: var(--space-4) var(--space-6); background: var(--color-bg-muted); border-bottom: 1px solid var(--color-border); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; flex: 1; max-width: 650px;">
                <!-- Search user by name/email/username -->
                <div style="position: relative; flex: 1; min-width: 220px;">
                    <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.875rem; color: var(--color-text-muted);" aria-hidden="true">🔍</span>
                    <input type="text" id="userSearchInput" class="form-control" placeholder="Search by name, username, or email..." 
                           style="padding-left: 2.25rem; font-size: 0.875rem;" oninput="filterUserRows()">
                </div>
                <!-- Role Filter -->
                <div style="min-width: 160px;">
                    <select id="userRoleFilter" class="form-control" style="font-size: 0.875rem;" onchange="filterUserRows()">
                        <option value="">All Roles</option>
                        <option value="admin">Administrator</option>
                        <option value="hr">Human Resources</option>
                        <option value="employee">Employee</option>
                    </select>
                </div>
                <!-- Status Filter -->
                <div style="min-width: 140px;">
                    <select id="userStatusFilter" class="form-control" style="font-size: 0.875rem;" onchange="filterUserRows()">
                        <option value="">All Statuses</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
                Showing <strong id="visibleUserCount"><?= count($userAccessList) ?></strong> Accounts
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover" id="userAccessTable" aria-label="User Access Directory">
                <thead>
                    <tr>
                        <th style="width: 260px;">Employee / User</th>
                        <th>Email Address</th>
                        <th style="width: 160px;">Assigned Role</th>
                        <th style="width: 110px; text-align: center;">Account Status</th>
                        <th style="width: 160px; text-align: center;">Enabled Permissions</th>
                        <th style="width: 140px; text-align: right;">Access Inspection</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($userAccessList)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--color-text-muted);">
                                No user accounts registered in the database.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($userAccessList as $u): ?>
                            <?php 
                            $uRole = strtolower($u['role'] ?? 'employee');
                            $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                            if ($fullName === '') {
                                $fullName = $u['username'];
                            }
                            $initials = strtoupper(substr($u['username'], 0, 2));

                            if ($uRole === 'admin') {
                                $effectiveCount = $totalPermCount;
                                $roleBadge = 'badge-primary';
                                $roleLabel = 'Administrator';
                            } elseif ($uRole === 'hr') {
                                $effectiveCount = $hrActiveCount;
                                $roleBadge = 'badge-info';
                                $roleLabel = 'Human Resources';
                            } else {
                                $effectiveCount = $empActiveCount;
                                $roleBadge = 'badge-success';
                                $roleLabel = 'Employee';
                            }
                            ?>
                            <tr class="user-row" data-role="<?= e($uRole) ?>" data-status="<?= e($u['status']) ?>" data-search="<?= e(strtolower($fullName . ' ' . $u['username'] . ' ' . ($u['email'] ?? ''))) ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div class="user-avatar-circle" aria-hidden="true"><?= e($initials) ?></div>
                                        <div>
                                            <div style="font-weight: 600; color: var(--color-text-main); font-size: 0.875rem;">
                                                <?= e($fullName) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                                                @<?= e($u['username']) ?> &bull; ID #<?= (int)$u['id'] ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-size: 0.8125rem; color: var(--color-text-main);">
                                    <?= e($u['email'] ?? '—') ?>
                                </td>
                                <td>
                                    <span class="badge <?= $roleBadge ?>">
                                        <?= e($roleLabel) ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($u['status'] === 'Active'): ?>
                                        <span class="badge badge-success">
                                            <span class="badge-dot" aria-hidden="true"></span> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-neutral">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-neutral" style="font-weight: 600;">
                                        <?= $effectiveCount ?> / <?= $totalPermCount ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-outline btn-xs" 
                                            onclick='openUserAccessModal(<?= json_encode([
                                                "id" => $u["id"],
                                                "username" => $u["username"],
                                                "name" => $fullName,
                                                "email" => $u["email"] ?? "—",
                                                "role" => $uRole,
                                                "roleLabel" => $roleLabel,
                                                "status" => $u["status"],
                                                "department" => $u["department_name"] ?? "Not assigned",
                                                "position" => $u["position_name"] ?? "Not assigned",
                                                "employee_no" => $u["employee_no"] ?? "N/A",
                                                "created_at" => $u["created_at"],
                                                "effective_count" => $effectiveCount
                                            ]) ?>)'
                                            aria-label="Inspect access for <?= e($fullName) ?>">
                                        Inspect Access
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- =========================================================================
     USER ACCESS DETAILS MODAL DIALOG
     ========================================================================= -->
<div class="modal-backdrop" id="userAccessModal" role="dialog" aria-modal="true" aria-labelledby="userModalTitle">
    <div class="modal" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title" id="userModalTitle" style="display: flex; align-items: center; gap: 0.5rem;">
                <span>👤</span> User Access &amp; Permissions Inspection
            </h3>
            <button type="button" class="modal-close" onclick="closeUserAccessModal()" aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Account Information Grid -->
            <div style="background: var(--color-bg-muted); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.25rem;">
                <div class="grid grid-cols-2" style="gap: 0.75rem; font-size: 0.8125rem;">
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Full Name:</span>
                        <strong style="color: var(--color-text-main);" id="uModalName">—</strong>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Username:</span>
                        <code style="color: var(--color-primary);" id="uModalUsername">—</code>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Email Address:</span>
                        <span style="color: var(--color-text-main);" id="uModalEmail">—</span>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Assigned Role:</span>
                        <span class="badge badge-primary" id="uModalRoleBadge">—</span>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Department:</span>
                        <span style="color: var(--color-text-main);" id="uModalDept">—</span>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Position:</span>
                        <span style="color: var(--color-text-main);" id="uModalPos">—</span>
                    </div>
                </div>
            </div>

            <!-- Ownership Boundary Notice -->
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid var(--color-primary); border-radius: var(--radius-md); padding: 0.75rem 1rem; margin-bottom: 1rem;">
                <strong style="color: #1e40af; font-size: 0.8125rem;">ℹ️ Record Ownership Enforcement:</strong>
                <p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem; color: #1e3a8a; line-height: 1.4;">
                    Role permissions authorize operational capabilities; record ownership restrictions (such as employee profile access and self-service leave requests) still enforce employee ID boundary checks server-side.
                </p>
            </div>

            <!-- Effective Permissions List -->
            <div style="font-weight: 600; font-size: 0.8125rem; color: var(--color-text-main); margin-bottom: 0.5rem; display: flex; justify-content: space-between;">
                <span>Inherited Role Capabilities (<span id="uModalPermCount">0</span> Enabled):</span>
                <span style="color: var(--color-text-muted); font-size: 0.75rem;">Source: Database RBAC Policy</span>
            </div>
            <div id="uModalPermList" style="max-height: 220px; overflow-y: auto; background: var(--color-bg-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.75rem; font-size: 0.8125rem;">
                <!-- Populated dynamically via JS -->
            </div>

            <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.75rem; font-style: italic;">
                Security note: Passwords, OTP secret tokens, and session identifiers are strictly omitted to prevent credential leakage.
            </div>
        </div>
        <div class="modal-footer">
            <a href="#" id="uModalEditLink" class="btn btn-outline btn-sm">Edit User Account &rarr;</a>
            <button type="button" class="btn btn-primary btn-sm" onclick="closeUserAccessModal()">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- =========================================================================
     SECTION 4: PERMISSION AUDIT HISTORY (PAGE 4)
     ========================================================================= -->
<?php if ($activeTab === 'audit'): ?>
<div class="permission-audit-section" style="margin-bottom: var(--space-6);">
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h2 class="card-title" style="margin: 0; font-size: 1rem; font-weight: 600;">
                    <span>📜</span> Permission Modification Audit History
                </h2>
                <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                    Permanent, immutable record of role capability modifications, actor identity, and access changes.
                </span>
            </div>
            <span class="badge badge-success">ZERO TAMPERING</span>
        </div>

        <!-- Filter & Search Toolbar -->
        <div style="padding: var(--space-4) var(--space-6); background: var(--color-bg-muted); border-bottom: 1px solid var(--color-border); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; flex: 1; max-width: 650px;">
                <!-- Search by Actor / Permission / IP -->
                <div style="position: relative; flex: 1; min-width: 220px;">
                    <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.875rem; color: var(--color-text-muted);" aria-hidden="true">🔍</span>
                    <input type="text" id="auditSearchInput" class="form-control" placeholder="Search by administrator, permission, IP..." 
                           style="padding-left: 2.25rem; font-size: 0.875rem;" oninput="filterAuditRows()">
                </div>
                <!-- Role Filter -->
                <div style="min-width: 140px;">
                    <select id="auditRoleFilter" class="form-control" style="font-size: 0.875rem;" onchange="filterAuditRows()">
                        <option value="">All Roles</option>
                        <option value="hr">Human Resources</option>
                        <option value="employee">Employee</option>
                    </select>
                </div>
                <!-- Action Filter -->
                <div style="min-width: 140px;">
                    <select id="auditActionFilter" class="form-control" style="font-size: 0.875rem;" onchange="filterAuditRows()">
                        <option value="">All Actions</option>
                        <option value="GRANTED">Granted</option>
                        <option value="REVOKED">Revoked</option>
                    </select>
                </div>
            </div>

            <div style="font-size: 0.8125rem; color: var(--color-text-muted);">
                Showing <strong id="visibleAuditCount"><?= count($recentAuditLogs) ?></strong> Audit Events
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover" id="auditTable" aria-label="Permission Audit Log History">
                <thead>
                    <tr>
                        <th style="width: 170px;">Timestamp</th>
                        <th style="width: 150px;">Administrator</th>
                        <th style="width: 140px;">Target Role</th>
                        <th>Permission Name / Technical Slug</th>
                        <th style="width: 110px; text-align: center;">Action</th>
                        <th style="width: 130px;">IP Address</th>
                        <th style="width: 100px; text-align: right;">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentAuditLogs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--color-text-muted);">
                                No permission modifications recorded yet. Initial baseline configuration remains active.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentAuditLogs as $log): ?>
                            <?php 
                            $isGranted = strtoupper((string)($log['action'] ?? '')) === 'GRANTED';
                            $targetSlug = strtolower((string)($log['target_role_slug'] ?? ''));
                            ?>
                            <tr class="audit-row" data-role="<?= e($targetSlug) ?>" data-action="<?= $isGranted ? 'GRANTED' : 'REVOKED' ?>" data-search="<?= e(strtolower(($log['actor_username'] ?? '') . ' ' . ($log['permission_name'] ?? '') . ' ' . ($log['permission_slug'] ?? '') . ' ' . ($log['ip_address'] ?? ''))) ?>">
                                <td style="font-size: 0.8125rem; color: var(--color-text-muted); font-family: var(--font-mono);">
                                    <?= e($log['created_at']) ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--color-text-main); font-size: 0.875rem;">
                                        <?= e($log['actor_username'] ?? 'System Administrator') ?>
                                    </div>
                                    <span style="font-size: 0.75rem; color: var(--color-text-muted);">ID #<?= (int)($log['actor_user_id'] ?? 0) ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $targetSlug === 'hr' ? 'badge-primary' : 'badge-neutral' ?>">
                                        <?= e(strtoupper($targetSlug ?: 'ROLE')) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; font-size: 0.875rem; color: var(--color-text-main);">
                                        <?= e($log['permission_name'] ?? 'Permission') ?>
                                    </div>
                                    <code style="font-size: 0.75rem; color: var(--color-primary);"><?= e($log['permission_slug'] ?? '') ?></code>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge <?= $isGranted ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $isGranted ? 'GRANTED' : 'REVOKED' ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.8125rem; font-family: var(--font-mono); color: var(--color-text-muted);">
                                    <?= e($log['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-outline btn-xs" 
                                            onclick='openAuditDetailModal(<?= json_encode([
                                                "id" => $log["id"],
                                                "timestamp" => $log["created_at"],
                                                "actor" => $log["actor_username"] ?? "System Administrator",
                                                "actor_id" => $log["actor_user_id"] ?? 0,
                                                "target_role" => strtoupper($targetSlug ?: "ROLE"),
                                                "permission_name" => $log["permission_name"] ?? "Permission",
                                                "permission_slug" => $log["permission_slug"] ?? "",
                                                "permission_module" => $log["permission_module"] ?? "System",
                                                "action" => $isGranted ? "GRANTED" : "REVOKED",
                                                "ip" => $log["ip_address"] ?? "127.0.0.1",
                                                "agent" => $log["user_agent"] ?? "Web Client"
                                            ]) ?>)'
                                            aria-label="Inspect audit event #<?= (int)$log['id'] ?>">
                                        Inspect
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- =========================================================================
     AUDIT DETAIL MODAL DIALOG
     ========================================================================= -->
<div class="modal-backdrop" id="auditDetailModal" role="dialog" aria-modal="true" aria-labelledby="auditModalTitle">
    <div class="modal" style="max-width: 560px;">
        <div class="modal-header">
            <h3 class="modal-title" id="auditModalTitle" style="display: flex; align-items: center; gap: 0.5rem;">
                <span>📜</span> Audit Event Details — #<span id="aModalId">—</span>
            </h3>
            <button type="button" class="modal-close" onclick="closeAuditDetailModal()" aria-label="Close dialog">&times;</button>
        </div>
        <div class="modal-body">
            <div style="background: var(--color-bg-muted); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1rem;">
                <div class="grid grid-cols-2" style="gap: 0.75rem; font-size: 0.8125rem;">
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Acting Administrator:</span>
                        <strong style="color: var(--color-text-main);" id="aModalActor">—</strong>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Target Role:</span>
                        <span class="badge badge-primary" id="aModalRole">—</span>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Action Taken:</span>
                        <span class="badge" id="aModalAction">—</span>
                    </div>
                    <div>
                        <span style="color: var(--color-text-muted); display: block;">Timestamp:</span>
                        <span style="font-family: var(--font-mono); color: var(--color-text-main);" id="aModalTime">—</span>
                    </div>
                    <div style="grid-column: span 2;">
                        <span style="color: var(--color-text-muted); display: block;">Permission Modified:</span>
                        <strong style="color: var(--color-text-main); font-size: 0.875rem;" id="aModalPermName">—</strong>
                        <div style="margin-top: 0.125rem;">
                            <code style="font-size: 0.75rem; color: var(--color-primary);" id="aModalPermSlug">—</code>
                            &bull; Module: <span id="aModalPermModule" style="font-weight: 600;">—</span>
                        </div>
                    </div>
                    <div style="grid-column: span 2;">
                        <span style="color: var(--color-text-muted); display: block;">Client IP Address:</span>
                        <code style="font-family: var(--font-mono); color: var(--color-text-main);" id="aModalIp">—</code>
                    </div>
                    <div style="grid-column: span 2;">
                        <span style="color: var(--color-text-muted); display: block;">User Agent Client:</span>
                        <div style="font-size: 0.75rem; color: var(--color-text-muted); word-break: break-all; margin-top: 0.25rem; max-height: 80px; overflow-y: auto;" id="aModalAgent">—</div>
                    </div>
                </div>
            </div>

            <div style="font-size: 0.75rem; color: var(--color-text-muted); font-style: italic;">
                Immutable Audit Record: Sourced from <code style="font-size:0.75rem;">permission_audit_logs</code>. Read-only and permanent.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary btn-sm" onclick="closeAuditDetailModal()">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- =========================================================================
     EMBEDDED CLIENT-SIDE RBAC JAVASCRIPT LOGIC
     ========================================================================= -->
<script>
// Raw role permissions state passed from backend
const rawRolePermissions = <?= json_encode($rolePermissionsMap) ?>;
const targetRoleSlug = <?= json_encode($matrixRole) ?>;
const targetRoleName = <?= json_encode($targetRoleName) ?>;
const baseUrl = <?= json_encode(url('/')) ?>;

// --- Permission Matrix Interaction & Filtering ---
function filterMatrixRows() {
    const searchVal = (document.getElementById('permSearchInput')?.value || '').toLowerCase().trim();
    const moduleVal = (document.getElementById('moduleFilterSelect')?.value || '').trim();
    const rows = document.querySelectorAll('.perm-row');
    const headerRows = document.querySelectorAll('.module-header-row');
    let visibleCount = 0;
    const visibleModules = new Set();

    rows.forEach(row => {
        const rowModule = row.getAttribute('data-module') || '';
        const rowSlug = (row.getAttribute('data-slug') || '').toLowerCase();
        const rowName = (row.getAttribute('data-name') || '').toLowerCase();
        const rowDesc = (row.getAttribute('data-desc') || '').toLowerCase();

        const matchesSearch = !searchVal || rowSlug.includes(searchVal) || rowName.includes(searchVal) || rowDesc.includes(searchVal);
        const matchesModule = !moduleVal || rowModule === moduleVal;

        if (matchesSearch && matchesModule) {
            row.style.display = '';
            visibleCount++;
            visibleModules.add(rowModule);
        } else {
            row.style.display = 'none';
        }
    });

    headerRows.forEach(header => {
        const mod = header.getAttribute('data-module') || '';
        if (visibleModules.has(mod)) {
            header.style.display = '';
        } else {
            header.style.display = 'none';
        }
    });

    const countDisplay = document.getElementById('visibleRowCount');
    if (countDisplay) {
        countDisplay.textContent = visibleCount;
    }

    const noMatch = document.getElementById('noMatchMessage');
    if (noMatch) {
        noMatch.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

function onPermissionToggleChange(checkbox) {
    const label = checkbox.parentElement.querySelector('.perm-status-label');
    if (label) {
        if (checkbox.checked) {
            label.textContent = 'Allowed';
            label.classList.remove('denied');
            label.classList.add('allowed');
        } else {
            label.textContent = 'Denied';
            label.classList.remove('allowed');
            label.classList.add('denied');
        }
    }
    updateUnsavedState();
}

function updateUnsavedState() {
    const checkboxes = document.querySelectorAll('.perm-checkbox');
    let modifiedCount = 0;
    let currentActive = 0;

    checkboxes.forEach(cb => {
        const initial = cb.getAttribute('data-initial') === '1';
        if (cb.checked !== initial) {
            modifiedCount++;
        }
        if (cb.checked) {
            currentActive++;
        }
    });

    const banner = document.getElementById('unsavedChangesBanner');
    const countSpan = document.getElementById('unsavedCount');
    const activeDisplay = document.getElementById('activeCountDisplay');

    if (banner && countSpan) {
        countSpan.textContent = modifiedCount;
        if (modifiedCount > 0) {
            banner.classList.remove('is-hidden');
        } else {
            banner.classList.add('is-hidden');
        }
    }

    if (activeDisplay) {
        activeDisplay.textContent = currentActive;
    }
}

function discardMatrixChanges() {
    const checkboxes = document.querySelectorAll('.perm-checkbox');
    checkboxes.forEach(cb => {
        const initial = cb.getAttribute('data-initial') === '1';
        cb.checked = initial;
        const label = cb.parentElement.querySelector('.perm-status-label');
        if (label) {
            if (initial) {
                label.textContent = 'Allowed';
                label.classList.remove('denied');
                label.classList.add('allowed');
            } else {
                label.textContent = 'Denied';
                label.classList.remove('allowed');
                label.classList.add('denied');
            }
        }
    });
    updateUnsavedState();
}

function bulkToggleVisible(targetState) {
    const rows = document.querySelectorAll('.perm-row');
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cb = row.querySelector('.perm-checkbox');
            if (cb) {
                cb.checked = targetState;
                const label = cb.parentElement.querySelector('.perm-status-label');
                if (label) {
                    if (targetState) {
                        label.textContent = 'Allowed';
                        label.classList.remove('denied');
                        label.classList.add('allowed');
                    } else {
                        label.textContent = 'Denied';
                        label.classList.remove('allowed');
                        label.classList.add('denied');
                    }
                }
            }
        }
    });
    updateUnsavedState();
}

function toggleModuleCheckboxes(moduleName, targetState) {
    const rows = document.querySelectorAll(`.perm-row[data-module="${moduleName}"]`);
    rows.forEach(row => {
        const cb = row.querySelector('.perm-checkbox');
        if (cb) {
            cb.checked = targetState;
            const label = cb.parentElement.querySelector('.perm-status-label');
            if (label) {
                if (targetState) {
                    label.textContent = 'Allowed';
                    label.classList.remove('denied');
                    label.classList.add('allowed');
                } else {
                    label.textContent = 'Denied';
                    label.classList.remove('allowed');
                    label.classList.add('denied');
                }
            }
        }
    });
    updateUnsavedState();
}

// --- Save Confirmation Modal ---
function openSaveModal() {
    const checkboxes = document.querySelectorAll('.perm-checkbox');
    let toGrant = [];
    let toRevoke = [];
    let sensitiveTouched = [];

    const sensitiveSlugs = [
        'users.deactivate', 'employees.deactivate', 'leave.approve', 
        'leave.policies.manage', 'settings.general', 'security.manage'
    ];

    checkboxes.forEach(cb => {
        const initial = cb.getAttribute('data-initial') === '1';
        const slug = cb.getAttribute('data-slug');
        const row = cb.closest('.perm-row');
        const name = row ? row.getAttribute('data-name') : slug;

        if (initial === false && cb.checked === true) {
            toGrant.push({ slug, name });
            if (sensitiveSlugs.includes(slug)) {
                sensitiveTouched.push(name);
            }
        } else if (initial === true && cb.checked === false) {
            toRevoke.push({ slug, name });
            if (sensitiveSlugs.includes(slug)) {
                sensitiveTouched.push(name);
            }
        }
    });

    if (toGrant.length === 0 && toRevoke.length === 0) {
        alert('No permission changes detected for ' + targetRoleName + '. Modify at least one permission toggle before saving.');
        return;
    }

    document.getElementById('modalGrantCount').textContent = toGrant.length;
    document.getElementById('modalRevokeCount').textContent = toRevoke.length;

    const alertBox = document.getElementById('modalSensitiveAlert');
    const alertText = document.getElementById('modalSensitiveText');
    if (sensitiveTouched.length > 0) {
        alertBox.style.display = 'block';
        alertText.textContent = 'You are modifying high-privilege capabilities: ' + sensitiveTouched.join(', ') + '. Ensure operational authorization before proceeding.';
    } else {
        alertBox.style.display = 'none';
    }

    const listContainer = document.getElementById('modalChangesList');
    let html = '<ul style="margin: 0; padding-left: 1.25rem;">';
    toGrant.forEach(item => {
        html += `<li style="margin-bottom: 0.25rem;"><span style="color:#047857; font-weight:700;">+ GRANT:</span> <strong>${escapeHtml(item.name)}</strong> (<code>${escapeHtml(item.slug)}</code>)</li>`;
    });
    toRevoke.forEach(item => {
        html += `<li style="margin-bottom: 0.25rem;"><span style="color:#b91c1c; font-weight:700;">&minus; REVOKE:</span> <strong>${escapeHtml(item.name)}</strong> (<code>${escapeHtml(item.slug)}</code>)</li>`;
    });
    html += '</ul>';
    listContainer.innerHTML = html;

    const modal = document.getElementById('saveConfirmModal');
    if (modal) {
        modal.classList.add('is-open');
    }
}

function closeSaveModal() {
    const modal = document.getElementById('saveConfirmModal');
    if (modal) {
        modal.classList.remove('is-open');
    }
}

function submitPermissionMatrixForm() {
    const form = document.getElementById('permissionMatrixForm');
    if (form) {
        const btn = document.getElementById('confirmSaveSubmitBtn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Applying Changes...';
        }
        form.submit();
    }
}

// --- User Access Section Search & Filter ---
function filterUserRows() {
    const searchVal = (document.getElementById('userSearchInput')?.value || '').toLowerCase().trim();
    const roleVal = (document.getElementById('userRoleFilter')?.value || '').toLowerCase().trim();
    const statusVal = (document.getElementById('userStatusFilter')?.value || '').trim();
    const rows = document.querySelectorAll('.user-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowRole = row.getAttribute('data-role') || '';
        const rowStatus = row.getAttribute('data-status') || '';
        const rowSearch = row.getAttribute('data-search') || '';

        const matchesSearch = !searchVal || rowSearch.includes(searchVal);
        const matchesRole = !roleVal || rowRole === roleVal;
        const matchesStatus = !statusVal || rowStatus === statusVal;

        if (matchesSearch && matchesRole && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const countDisplay = document.getElementById('visibleUserCount');
    if (countDisplay) {
        countDisplay.textContent = visibleCount;
    }
}

function openUserAccessModal(user) {
    document.getElementById('uModalName').textContent = user.name;
    document.getElementById('uModalUsername').textContent = '@' + user.username;
    document.getElementById('uModalEmail').textContent = user.email;
    document.getElementById('uModalRoleBadge').textContent = user.roleLabel;
    document.getElementById('uModalDept').textContent = user.department;
    document.getElementById('uModalPos').textContent = user.position;
    document.getElementById('uModalPermCount').textContent = user.effective_count;

    const editLink = document.getElementById('uModalEditLink');
    if (editLink) {
        editLink.href = baseUrl + '/admin/users/' + user.id + '/edit';
    }

    const permListContainer = document.getElementById('uModalPermList');
    const rolePerms = rawRolePermissions[user.role] || {};

    let html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 0.5rem;">';
    for (const [slug, isGranted] of Object.entries(rolePerms)) {
        if (isGranted) {
            html += `<div style="display: flex; align-items: center; gap: 0.375rem; color: #047857;">
                        <span>✓</span> <code>${escapeHtml(slug)}</code>
                     </div>`;
        } else {
            html += `<div style="display: flex; align-items: center; gap: 0.375rem; color: #94a3b8; text-decoration: line-through;">
                        <span>✕</span> <code>${escapeHtml(slug)}</code>
                     </div>`;
        }
    }
    html += '</div>';
    permListContainer.innerHTML = html;

    const modal = document.getElementById('userAccessModal');
    if (modal) {
        modal.classList.add('is-open');
    }
}

function closeUserAccessModal() {
    const modal = document.getElementById('userAccessModal');
    if (modal) {
        modal.classList.remove('is-open');
    }
}

// --- Audit History Search & Filter ---
function filterAuditRows() {
    const searchVal = (document.getElementById('auditSearchInput')?.value || '').toLowerCase().trim();
    const roleVal = (document.getElementById('auditRoleFilter')?.value || '').toLowerCase().trim();
    const actionVal = (document.getElementById('auditActionFilter')?.value || '').toUpperCase().trim();
    const rows = document.querySelectorAll('.audit-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowRole = row.getAttribute('data-role') || '';
        const rowAction = row.getAttribute('data-action') || '';
        const rowSearch = row.getAttribute('data-search') || '';

        const matchesSearch = !searchVal || rowSearch.includes(searchVal);
        const matchesRole = !roleVal || rowRole === roleVal;
        const matchesAction = !actionVal || rowAction === actionVal;

        if (matchesSearch && matchesRole && matchesAction) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const countDisplay = document.getElementById('visibleAuditCount');
    if (countDisplay) {
        countDisplay.textContent = visibleCount;
    }
}

function openAuditDetailModal(audit) {
    document.getElementById('aModalId').textContent = audit.id;
    document.getElementById('aModalActor').textContent = audit.actor + ' (ID #' + audit.actor_id + ')';
    document.getElementById('aModalRole').textContent = audit.target_role;
    
    const actionBadge = document.getElementById('aModalAction');
    actionBadge.textContent = audit.action;
    actionBadge.className = 'badge ' + (audit.action === 'GRANTED' ? 'badge-success' : 'badge-danger');

    document.getElementById('aModalTime').textContent = audit.timestamp;
    document.getElementById('aModalPermName').textContent = audit.permission_name;
    document.getElementById('aModalPermSlug').textContent = audit.permission_slug;
    document.getElementById('aModalPermModule').textContent = audit.permission_module;
    document.getElementById('aModalIp').textContent = audit.ip;
    document.getElementById('aModalAgent').textContent = audit.agent;

    const modal = document.getElementById('auditDetailModal');
    if (modal) {
        modal.classList.add('is-open');
    }
}

function closeAuditDetailModal() {
    const modal = document.getElementById('auditDetailModal');
    if (modal) {
        modal.classList.remove('is-open');
    }
}

// Utility: HTML escaping
function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Global Modal Backdrop Click & Escape Key Listener
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeSaveModal();
        closeUserAccessModal();
        closeAuditDetailModal();
    }
});

document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal-backdrop')) {
        closeSaveModal();
        closeUserAccessModal();
        closeAuditDetailModal();
    }
});
</script>
