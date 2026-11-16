<?php
/**
 * Role-Aware Dynamic Navigation Sidebar Partial
 * Employee HRIS & Leave Management System
 * Supports 7-Group Admin Navigation Structure, Operational HR Navigation, and Employee Self-Service
 */

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentPath = rtrim((string)$rawPath, '/') ?: '/';
$currentQuery = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '';
$queryParams = [];
if ($currentQuery !== '') {
    parse_str($currentQuery, $queryParams);
}
$currentTab = $queryParams['tab'] ?? ($_GET['tab'] ?? '');
$user = currentUser() ?? [];
$userName = $user['name'] ?? $user['username'] ?? 'User';
$rawRole = strtolower($user['role'] ?? 'employee');
$initials = strtoupper(substr($userName, 0, 2));

if ($rawRole === 'admin') {
    $dashboardUrl = url('/admin/dashboard');
    $brandLabel = 'Admin Portal';
    $roleTitle = 'System Administrator';
    $iconClass = 'admin';
} elseif ($rawRole === 'hr') {
    $dashboardUrl = url('/hr/dashboard');
    $brandLabel = 'HR Portal';
    $roleTitle = 'HR Officer';
    $iconClass = 'hr';
} else {
    $dashboardUrl = url('/employee/dashboard');
    $brandLabel = 'NEXORA HR';
    $roleTitle = $user['employee_no'] ?? 'Employee';
    $iconClass = 'emp';
}

$sidebarUnreadCount = 0;
$sidebarPendingCount = 0;
$sidebarCorrectionPendingCount = 0;
$sidebarSupportOpenCount = 0;
$sidebarHrSupportOpenCount = 0;
$sidebarHrToAdminOpenCount = 0;
$sidebarUnreadAnnouncementsCount = 0;
if (isAuthenticated()) {
    try {
        $sidebarUnreadCount = (new \App\Models\Notification())->getUnreadCount((int)currentUserId());
    } catch (\Throwable $e) {
        $sidebarUnreadCount = 0;
    }

    try {
        $leaveSidebarModel = new \App\Models\Leave();
        if ($rawRole === 'hr' || $rawRole === 'admin') {
            $sidebarPendingCount = $leaveSidebarModel->countPendingAll();
        } elseif (!empty($user['employee_id'])) {
            $sidebarPendingCount = $leaveSidebarModel->countPendingByEmployee((int)$user['employee_id']);
        }
    } catch (\Throwable $e) {
        $sidebarPendingCount = 0;
    }

    if ($rawRole === 'hr' || $rawRole === 'admin') {
        try {
            $sidebarCorrectionPendingCount = (new \App\Models\ProfileCorrectionRequest())->countPending();
        } catch (\Throwable $e) {
            $sidebarCorrectionPendingCount = 0;
        }
        try {
            $sidebarSupportPdo = \Core\Database::getInstance()->getConnection();
            $sidebarSupportModel = new \App\Models\SupportTicket($sidebarSupportPdo);
            $sidebarHandlers = new \App\Models\SupportTicketHandler($sidebarSupportPdo);
            $sidebarSupportAccess = new \App\Services\SupportAccessService();
            $sidebarViewerCtx = $sidebarSupportAccess->buildViewerContext($user, $sidebarHandlers, $sidebarSupportPdo);
            $sidebarHrStats = $sidebarSupportModel->getHrOverviewStats($sidebarViewerCtx);
            $sidebarSupportOpenCount = (int)($sidebarHrStats['total_open'] ?? 0);

            $hrSupportSidebarModel = new \App\Models\HrSupportTicket($sidebarSupportPdo);
            if ($rawRole === 'admin') {
                $admSupStats = $hrSupportSidebarModel->getAdminDashboardStats();
                $sidebarHrSupportOpenCount = (int)($admSupStats['total_open'] ?? 0);
            } else {
                $hrSupStats = $hrSupportSidebarModel->getHrUserSummaryStats((int)currentUserId());
                $sidebarHrToAdminOpenCount = (int)($hrSupStats['open'] ?? 0);
            }
        } catch (\Throwable $e) {
            $sidebarSupportOpenCount = 0;
            $sidebarHrSupportOpenCount = 0;
            $sidebarHrToAdminOpenCount = 0;
        }
    } elseif (!empty($user['employee_id'])) {
        try {
            $sidebarSupportOpenCount = (new \App\Models\SupportTicket())->countOpenByEmployee((int)$user['employee_id']);
        } catch (\Throwable $e) {
            $sidebarSupportOpenCount = 0;
        }
        try {
            $empSidebarPdo = \Core\Database::getInstance()->getConnection();
            $empRowStmt = $empSidebarPdo->prepare('SELECT department_id, employment_status FROM employees WHERE id = :eid LIMIT 1');
            $empRowStmt->execute(['eid' => (int)$user['employee_id']]);
            $empRowData = $empRowStmt->fetch(\PDO::FETCH_ASSOC);
            if ($empRowData) {
                $sidebarUnreadAnnouncementsCount = (new \App\Infrastructure\Persistence\Announcement\MySqlAnnouncementRepository($empSidebarPdo))
                    ->countUnreadForEmployee(
                        (int)$user['employee_id'],
                        $empRowData['department_id'] !== null ? (int)$empRowData['department_id'] : null,
                        (string)($empRowData['employment_status'] ?? 'Active')
                    );
            }
        } catch (\Throwable $e) {
            $sidebarUnreadAnnouncementsCount = 0;
        }
    }
}

$sidebarAvatarUrl = \App\Services\ProfilePictureService::avatarUrl(
    !empty($user['employee_id']) ? (int)$user['employee_id'] : null,
    is_string($user['profile_picture'] ?? null) ? $user['profile_picture'] : null
);

$sidebarProfileUrl = url('/employee/profile');
if ($rawRole === 'admin') {
    $sidebarProfileUrl = url('/admin/account');
} elseif ($rawRole === 'hr') {
    $sidebarProfileUrl = !empty($user['employee_id'])
        ? url('/hr/employees/' . (int)$user['employee_id'])
        : url('/hr/dashboard');
}
?>
<aside class="app-sidebar" id="appSidebar" data-role="<?= e($rawRole) ?>" aria-label="Main Navigation">
    <div class="sidebar-header">
        <a href="<?= $dashboardUrl ?>" class="sidebar-brand" aria-label="NEXORA HR — People. Processes. Progress." title="NEXORA HR" data-tooltip="NEXORA HR">
            <img
                src="<?= e(asset('img/nexora-logo-icon.png')) ?>"
                alt=""
                class="sidebar-logo-emblem"
                width="34"
                height="34"
            >
            <div class="sidebar-brand-lockup">
                <div class="sidebar-logo-wordmark">
                    <span class="logo-word-nexora">NEXOR<span class="logo-letter-a">A<svg class="logo-delta-mark" viewBox="0 0 12 10" aria-hidden="true" focusable="false"><polygon points="6,1 11,10 1,10" fill="currentColor"/></svg></span></span>
                    <span class="logo-word-hr">HR</span>
                </div>
                <div class="sidebar-logo-tagline">
                    <span class="logo-tagline-rule" aria-hidden="true"></span>
                    <span class="logo-tagline-text">People. Processes. Progress.</span>
                    <span class="logo-tagline-rule" aria-hidden="true"></span>
                </div>
            </div>
        </a>
        <button type="button" class="mobile-drawer-close" id="mobileSidebarClose" aria-label="Close navigation menu" title="Close navigation menu">
            <?= icon('x', 18) ?>
        </button>
    </div>

    <nav class="sidebar-nav">
        <?php if ($rawRole === 'admin' || $rawRole === 'hr'): ?>
            <search class="sidebar-search" role="search">
                <label for="sidebarNavFilter" class="sr-only">Filter navigation menu</label>
                <div class="sidebar-search-field">
                    <span class="sidebar-search-icon" aria-hidden="true"><?= icon('search', 14) ?></span>
                    <input
                        type="search"
                        id="sidebarNavFilter"
                        class="sidebar-search-input"
                        placeholder="Quick jump..."
                        aria-label="Filter navigation menu"
                        aria-keyshortcuts="/"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="none"
                        spellcheck="false"
                    >
                    <kbd class="sidebar-search-shortcut" aria-hidden="true">/</kbd>
                    <button type="button" id="sidebarNavFilterClear" class="sidebar-search-clear" aria-label="Clear navigation filter" title="Clear filter" hidden>
                        <?= icon('x', 13) ?>
                    </button>
                </div>
            </search>
        <?php endif; ?>

        <?php if ($rawRole === 'admin'): ?>
            <!-- ==============================================================
                 ADMINISTRATOR 7-GROUP HIERARCHY
                 ============================================================== -->
            <?php
            $isAdmOverview  = ($currentPath === '/admin/dashboard');
            $isAdmUsers     = ($currentPath === '/admin/users' || str_starts_with($currentPath, '/admin/users/'));
            $isAdmRoles     = ($currentPath === '/admin/roles');
            $isAdmProvision = ($currentPath === '/admin/provisioning');
            $isAdmEmployees   = (str_starts_with($currentPath, '/hr/employees') || $currentPath === '/admin/employees');
            $isAdmCorrections = str_starts_with($currentPath, '/hr/correction-requests');
            $isAdmDepts       = (str_starts_with($currentPath, '/hr/departments') || $currentPath === '/admin/departments');
            $isAdmPositions = (str_starts_with($currentPath, '/hr/positions') || $currentPath === '/admin/positions');
            $isAdmLeaves    = ($currentPath === '/leaves' || $currentPath === '/admin/leaves' || str_starts_with($currentPath, '/hr/leave-requests') || preg_match('#^/leaves/\d+$#', $currentPath) === 1);
            $isAdmTypes     = (str_starts_with($currentPath, '/hr/leave-types') || $currentPath === '/admin/leave-types');
            $isAdmBalances  = (str_starts_with($currentPath, '/leaves/balances') || $currentPath === '/admin/leave-balances');
            $isAdmQuals     = str_starts_with($currentPath, '/hr/leave-qualifications');
            $isAdmReports   = (($currentPath === '/reports' || $currentPath === '/admin/reports') && $currentTab !== 'analytics');
            $isAdmAnalytics = ((($currentPath === '/reports' || $currentPath === '/admin/reports') && $currentTab === 'analytics') || $currentPath === '/admin/analytics');
            $isAdmAudit     = ($currentPath === '/activity-logs' || $currentPath === '/admin/audit-logs');
            $isAdmSecurity  = ($currentPath === '/admin/security/events');
            $isAdmAuthCfg   = ($currentPath === '/admin/settings/auth');
            $isAdmSettings  = ($currentPath === '/admin/settings');
            $isAdmHealth    = ($currentPath === '/admin/system-health');
            $isAdmNotifCfg  = ($currentPath === '/admin/settings/notifications');
            $isAdmHrSupport = ($currentPath === '/admin/support' || str_starts_with($currentPath, '/admin/support/'));
            $isAdmSupport   = ($currentPath === '/hr/support' || (str_starts_with($currentPath, '/hr/support/') && !str_starts_with($currentPath, '/hr/support/handlers')));
            $isAdmHandlers  = str_starts_with($currentPath, '/hr/support/handlers');
            $isAdmAccount   = ($currentPath === '/admin/account');
            $isAdmDevSec    = str_starts_with($currentPath, '/account/security');
            ?>

            <!-- Dashboard -->
            <div class="nav-section-group" data-section-id="admin-dashboard">
                <div class="nav-section-title">Dashboard</div>
                <div class="nav-section-items" id="navGroupAdminDashboard">
                    <a href="<?= url('/admin/dashboard') ?>" class="nav-item <?= $isAdmOverview ? 'active' : '' ?>"<?= $isAdmOverview ? ' aria-current="page"' : '' ?> title="Overview" data-tooltip="Overview" data-keywords="home summary metrics">
                        <span class="icon" aria-hidden="true"><?= icon('dashboard', 18) ?></span>
                        <span class="nav-item-label">Overview</span>
                    </a>
                </div>
            </div>

            <!-- User Administration -->
            <div class="nav-section-group" data-section-id="admin-users">
                <button type="button" class="nav-section-toggle" aria-expanded="true" aria-controls="navGroupAdminUsers">
                    <span class="nav-section-toggle-label">User Administration</span>
                    <span class="nav-section-chevron" aria-hidden="true"><?= icon('chevron-right', 12) ?></span>
                </button>
                <div class="nav-section-items" id="navGroupAdminUsers">
                    <a href="<?= url('/admin/users') ?>" class="nav-item <?= $isAdmUsers ? 'active' : '' ?>"<?= $isAdmUsers ? ' aria-current="page"' : '' ?> title="User Accounts" data-tooltip="User Accounts" data-keywords="accounts credentials access">
                        <span class="icon" aria-hidden="true"><?= icon('users', 18) ?></span>
                        <span class="nav-item-label">User Accounts</span>
                    </a>
                    <a href="<?= url('/admin/roles') ?>" class="nav-item <?= $isAdmRoles ? 'active' : '' ?>"<?= $isAdmRoles ? ' aria-current="page"' : '' ?> title="Roles &amp; Permissions" data-tooltip="Roles &amp; Permissions" data-keywords="rbac policy">
                        <span class="icon" aria-hidden="true"><?= icon('shield', 18) ?></span>
                        <span class="nav-item-label">Roles &amp; Permissions</span>
                    </a>
                    <a href="<?= url('/admin/provisioning') ?>" class="nav-item <?= $isAdmProvision ? 'active' : '' ?>"<?= $isAdmProvision ? ' aria-current="page"' : '' ?> title="Admin Provisioning" data-tooltip="Admin Provisioning" data-keywords="onboarding accounts">
                        <span class="icon" aria-hidden="true"><?= icon('user', 18) ?></span>
                        <span class="nav-item-label">Admin Provisioning</span>
                    </a>
                </div>
            </div>

            <!-- Organization Management -->
            <div class="nav-section-group" data-section-id="admin-org">
                <button type="button" class="nav-section-toggle" aria-expanded="true" aria-controls="navGroupAdminOrg">
                    <span class="nav-section-toggle-label">Organization Management</span>
                    <span class="nav-section-chevron" aria-hidden="true"><?= icon('chevron-right', 12) ?></span>
                </button>
                <div class="nav-section-items" id="navGroupAdminOrg">
                    <a href="<?= url('/hr/employees') ?>" class="nav-item <?= $isAdmEmployees ? 'active' : '' ?>"<?= $isAdmEmployees ? ' aria-current="page"' : '' ?> title="Employee Records" data-tooltip="Employee Records" data-keywords="staff directory personnel">
                        <span class="icon" aria-hidden="true"><?= icon('file-text', 18) ?></span>
                        <span class="nav-item-label">Employee Records</span>
                    </a>
                    <a href="<?= url('/hr/departments') ?>" class="nav-item <?= $isAdmDepts ? 'active' : '' ?>"<?= $isAdmDepts ? ' aria-current="page"' : '' ?> title="Departments" data-tooltip="Departments" data-keywords="teams units division">
                        <span class="icon" aria-hidden="true"><?= icon('building', 18) ?></span>
                        <span class="nav-item-label">Departments</span>
                    </a>
                    <a href="<?= url('/hr/positions') ?>" class="nav-item <?= $isAdmPositions ? 'active' : '' ?>"<?= $isAdmPositions ? ' aria-current="page"' : '' ?> title="Positions" data-tooltip="Positions" data-keywords="jobs titles roles">
                        <span class="icon" aria-hidden="true"><?= icon('briefcase', 18) ?></span>
                        <span class="nav-item-label">Positions</span>
                    </a>
                    <?php if (function_exists('can') && can('employees.update')): ?>
                    <a href="<?= url('/hr/correction-requests') ?>" class="nav-item <?= $isAdmCorrections ? 'active' : '' ?>"<?= $isAdmCorrections ? ' aria-current="page"' : '' ?> title="Profile Corrections" data-tooltip="Profile Corrections<?= $sidebarCorrectionPendingCount > 0 ? ' (' . (int)$sidebarCorrectionPendingCount . ' pending)' : '' ?>" data-keywords="correction requests profile edits approvals">
                        <span class="icon" aria-hidden="true"><?= icon('check-circle', 18) ?></span>
                        <span class="nav-item-label">Profile Corrections</span>
                        <?php if ($sidebarCorrectionPendingCount > 0): ?>
                            <span class="nav-badge nav-badge-pending" aria-label="<?= (int)$sidebarCorrectionPendingCount ?> pending profile corrections"><?= $sidebarCorrectionPendingCount > 99 ? '99+' : (int)$sidebarCorrectionPendingCount ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Leave Administration -->
            <div class="nav-section-group" data-section-id="admin-leave">
                <button type="button" class="nav-section-toggle" aria-expanded="true" aria-controls="navGroupAdminLeave">
                    <span class="nav-section-toggle-label">Leave Administration</span>
                    <span class="nav-section-chevron" aria-hidden="true"><?= icon('chevron-right', 12) ?></span>
                </button>
                <div class="nav-section-items" id="navGroupAdminLeave">
                    <a href="<?= url('/leaves') ?>" class="nav-item <?= $isAdmLeaves ? 'active' : '' ?>"<?= $isAdmLeaves ? ' aria-current="page"' : '' ?> title="Leave Requests" data-tooltip="Leave Requests<?= $sidebarPendingCount > 0 ? ' (' . (int)$sidebarPendingCount . ' pending)' : '' ?>" data-keywords="approvals queue pending pto">
                        <span class="icon" aria-hidden="true"><?= icon('calendar', 18) ?></span>
                        <span class="nav-item-label">Leave Requests</span>
                        <?php if ($sidebarPendingCount > 0): ?>
                            <span class="nav-badge nav-badge-pending" id="sidebarPendingBadge" aria-label="<?= (int)$sidebarPendingCount ?> pending leave requests"><?= $sidebarPendingCount > 99 ? '99+' : (int)$sidebarPendingCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= url('/hr/leave-types') ?>" class="nav-item <?= $isAdmTypes ? 'active' : '' ?>"<?= $isAdmTypes ? ' aria-current="page"' : '' ?> title="Leave Types &amp; Policies" data-tooltip="Leave Types &amp; Policies" data-keywords="classifications rules">
                        <span class="icon" aria-hidden="true"><?= icon('layers', 18) ?></span>
                        <span class="nav-item-label">Leave Types &amp; Policies</span>
                    </a>
                    <a href="<?= url('/leaves/balances') ?>" class="nav-item <?= $isAdmBalances ? 'active' : '' ?>"<?= $isAdmBalances ? ' aria-current="page"' : '' ?> title="Leave Balances" data-tooltip="Leave Balances" data-keywords="credits quota allocation">
                        <span class="icon" aria-hidden="true"><?= icon('balances', 18) ?></span>
                        <span class="nav-item-label">Leave Balances</span>
                    </a>
                    <?php if (function_exists('can') && can('leave_qualifications.verify')): ?>
                    <a href="<?= url('/hr/leave-qualifications') ?>" class="nav-item <?= $isAdmQuals ? 'active' : '' ?>"<?= $isAdmQuals ? ' aria-current="page"' : '' ?> title="Leave Qualifications" data-tooltip="Leave Qualifications" data-keywords="spic solo parent paternity sil exclusion">
                        <span class="icon" aria-hidden="true"><?= icon('check-circle', 18) ?></span>
                        <span class="nav-item-label">Leave Qualifications</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reports & Analytics -->
            <div class="nav-section-group" data-section-id="admin-reports">
                <button type="button" class="nav-section-toggle" aria-expanded="true" aria-controls="navGroupAdminReports">
                    <span class="nav-section-toggle-label">Reports &amp; Analytics</span>
                    <span class="nav-section-chevron" aria-hidden="true"><?= icon('chevron-right', 12) ?></span>
                </button>
                <div class="nav-section-items" id="navGroupAdminReports">
                    <a href="<?= url('/reports') ?>" class="nav-item <?= $isAdmReports ? 'active' : '' ?>"<?= $isAdmReports ? ' aria-current="page"' : '' ?> title="System Reports" data-tooltip="System Reports" data-keywords="export csv summary">
                        <span class="icon" aria-hidden="true"><?= icon('bar-chart', 18) ?></span>
                        <span class="nav-item-label">System Reports</span>
                    </a>
                    <a href="<?= url('/reports?tab=analytics') ?>" class="nav-item <?= $isAdmAnalytics ? 'active' : '' ?>"<?= $isAdmAnalytics ? ' aria-current="page"' : '' ?> title="HR Analytics" data-tooltip="HR Analytics" data-keywords="charts trends insights">
                        <span class="icon" aria-hidden="true"><?= icon('activity', 18) ?></span>
                        <span class="nav-item-label">HR Analytics</span>
                    </a>
                </div>
            </div>

            <!-- Security & Compliance -->
            <div class="nav-section-group" data-section-id="admin-security">
                <button type="button" class="nav-section-toggle" aria-expanded="true" aria-controls="navGroupAdminSecurity">
                    <span class="nav-section-toggle-label">Security &amp; Compliance</span>
                    <span class="nav-section-chevron" aria-hidden="true"><?= icon('chevron-right', 12) ?></span>
                </button>
                <div class="nav-section-items" id="navGroupAdminSecurity">
                    <a href="<?= url('/activity-logs') ?>" class="nav-item <?= $isAdmAudit ? 'active' : '' ?>"<?= $isAdmAudit ? ' aria-current="page"' : '' ?> title="Audit Logs" data-tooltip="Audit Logs" data-keywords="trail history actions">
                        <span class="icon" aria-hidden="true"><?= icon('file-text', 18) ?></span>
                        <span class="nav-item-label">Audit Logs</span>
                    </a>
                    <a href="<?= url('/admin/security/events') ?>" class="nav-item <?= $isAdmSecurity ? 'active' : '' ?>"<?= $isAdmSecurity ? ' aria-current="page"' : '' ?> title="Security Events" data-tooltip="Security Events" data-keywords="alerts threats auth">
                        <span class="icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
                        <span class="nav-item-label">Security Events</span>
                    </a>
                    <a href="<?= url('/admin/settings/auth') ?>" class="nav-item <?= $isAdmAuthCfg ? 'active' : '' ?>"<?= $isAdmAuthCfg ? ' aria-current="page"' : '' ?> title="Authentication Settings" data-tooltip="Authentication Settings" data-keywords="otp mfa password">
                        <span class="icon" aria-hidden="true"><?= icon('lock', 18) ?></span>
                        <span class="nav-item-label">Authentication Settings</span>
                    </a>
                    <a href="<?= url('/admin/support') ?>" class="nav-item <?= $isAdmHrSupport ? 'active' : '' ?>"<?= $isAdmHrSupport ? ' aria-current="page"' : '' ?> title="Support Management" data-tooltip="Support Management<?= $sidebarHrSupportOpenCount > 0 ? ' (' . (int)$sidebarHrSupportOpenCount . ' open)' : '' ?>" data-keywords="hr support management tickets errors helpdesk">
                        <span class="icon" aria-hidden="true"><?= icon('shield', 18) ?></span>
                        <span class="nav-item-label">Support Management</span>
                        <?php if ($sidebarHrSupportOpenCount > 0): ?>
                            <span class="nav-badge nav-badge-pending" aria-label="<?= (int)$sidebarHrSupportOpenCount ?> open HR support tickets"><?= $sidebarHrSupportOpenCount > 99 ? '99+' : (int)$sidebarHrSupportOpenCount ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if (function_exists('can') && can('support.manage_handlers')): ?>
                    <a href="<?= url('/hr/support') ?>" class="nav-item <?= $isAdmSupport ? 'active' : '' ?>"<?= $isAdmSupport ? ' aria-current="page"' : '' ?> title="Escalated Complaints" data-tooltip="Escalated Complaints<?= $sidebarSupportOpenCount > 0 ? ' (' . (int)$sidebarSupportOpenCount . ' open)' : '' ?>" data-keywords="escalated confidential complaints coi">
                        <span class="icon" aria-hidden="true"><?= icon('alert-circle', 18) ?></span>
                        <span class="nav-item-label">Escalated Complaints</span>
                        <?php if ($sidebarSupportOpenCount > 0): ?>
                            <span class="nav-badge nav-badge-pending" aria-label="<?= (int)$sidebarSupportOpenCount ?> escalated complaint tickets"><?= $sidebarSupportOpenCount > 99 ? '99+' : (int)$sidebarSupportOpenCount ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    <a href="<?= url('/hr/support/handlers') ?>" class="nav-item <?= $isAdmHandlers ? 'active' : '' ?>"<?= $isAdmHandlers ? ' aria-current="page"' : '' ?> title="Complaint Handlers" data-tooltip="Complaint Handlers" data-keywords="confidential handlers clearance coi">
                        <span class="icon" aria-hidden="true"><?= icon('lock', 18) ?></span>
                        <span class="nav-item-label">Complaint Handlers</span>
                    </a>
                </div>
            </div>

            <!-- System Administration -->
            <div class="nav-section-group" data-section-id="admin-system">
                <button type="button" class="nav-section-toggle" aria-expanded="true" aria-controls="navGroupAdminSystem">
                    <span class="nav-section-toggle-label">System Administration</span>
                    <span class="nav-section-chevron" aria-hidden="true"><?= icon('chevron-right', 12) ?></span>
                </button>
                <div class="nav-section-items" id="navGroupAdminSystem">
                    <a href="<?= url('/admin/settings') ?>" class="nav-item <?= $isAdmSettings ? 'active' : '' ?>"<?= $isAdmSettings ? ' aria-current="page"' : '' ?> title="General Settings" data-tooltip="General Settings" data-keywords="config preferences">
                        <span class="icon" aria-hidden="true"><?= icon('settings', 18) ?></span>
                        <span class="nav-item-label">General Settings</span>
                    </a>
                    <a href="<?= url('/admin/system-health') ?>" class="nav-item <?= $isAdmHealth ? 'active' : '' ?>"<?= $isAdmHealth ? ' aria-current="page"' : '' ?> title="System Health" data-tooltip="System Health" data-keywords="diagnostics status db">
                        <span class="icon" aria-hidden="true"><?= icon('activity', 18) ?></span>
                        <span class="nav-item-label">System Health</span>
                    </a>
                    <a href="<?= url('/admin/settings/notifications') ?>" class="nav-item <?= $isAdmNotifCfg ? 'active' : '' ?>"<?= $isAdmNotifCfg ? ' aria-current="page"' : '' ?> title="Notification Settings" data-tooltip="Notification Settings" data-keywords="email alerts smtp">
                        <span class="icon" aria-hidden="true"><?= icon('bell', 18) ?></span>
                        <span class="nav-item-label">Notification Settings</span>
                    </a>
                </div>
            </div>

            <!-- Account Session -->
            <div class="nav-section-group" data-section-id="admin-account">
                <button type="button" class="nav-section-toggle" aria-expanded="true" aria-controls="navGroupAdminAccount">
                    <span class="nav-section-toggle-label">Account</span>
                    <span class="nav-section-chevron" aria-hidden="true"><?= icon('chevron-right', 12) ?></span>
                </button>
                <div class="nav-section-items" id="navGroupAdminAccount">
                    <a href="<?= url('/admin/account') ?>" class="nav-item <?= $isAdmAccount ? 'active' : '' ?>"<?= $isAdmAccount ? ' aria-current="page"' : '' ?> title="My Account" data-tooltip="My Account" data-keywords="profile password">
                        <span class="icon" aria-hidden="true"><?= icon('user', 18) ?></span>
                        <span class="nav-item-label">My Account</span>
                    </a>
                    <a href="<?= url('/account/security') ?>" class="nav-item <?= $isAdmDevSec ? 'active' : '' ?>"<?= $isAdmDevSec ? ' aria-current="page"' : '' ?> title="Security &amp; Devices" data-tooltip="Security &amp; Devices" data-keywords="trusted devices otp security recovery">
                        <span class="icon" aria-hidden="true"><?= icon('shield', 18) ?></span>
                        <span class="nav-item-label">Security &amp; Devices</span>
                    </a>
                    <form method="POST" action="<?= url('/logout') ?>" style="margin: 0;">
                        <?= csrf_field() ?>
                        <button type="submit" class="nav-item nav-item-btn" title="Sign Out" data-tooltip="Sign Out" data-keywords="logout exit">
                            <span class="icon" aria-hidden="true"><?= icon('log-out', 18) ?></span>
                            <span class="nav-item-label">Sign Out</span>
                        </button>
                    </form>
                </div>
            </div>

        <?php elseif ($rawRole === 'hr'): ?>
            <!-- ==============================================================
                 HR OFFICER OPERATIONAL NAVIGATION
                 ============================================================== -->
            <?php
            $isHrDashboard   = ($currentPath === '/hr/dashboard');
            $isHrEmployees   = str_starts_with($currentPath, '/hr/employees');
            $isHrCorrections = str_starts_with($currentPath, '/hr/correction-requests');
            $isHrDepartments = str_starts_with($currentPath, '/hr/departments');
            $isHrPositions   = str_starts_with($currentPath, '/hr/positions');
            $isHrLeaveQueue  = (
                $currentPath === '/leaves'
                || str_starts_with($currentPath, '/hr/leave-requests')
                || preg_match('#^/leaves/\d+$#', $currentPath) === 1
            );
            $isHrBalances    = str_starts_with($currentPath, '/leaves/balances');
            $isHrLeaveTypes  = str_starts_with($currentPath, '/hr/leave-types');
            $isHrQuals       = str_starts_with($currentPath, '/hr/leave-qualifications');
            $isHrReports     = str_starts_with($currentPath, '/reports');
            $isHrAuditLogs   = str_starts_with($currentPath, '/activity-logs');
            $isHrHelpAdmin   = ($currentPath === '/hr/help' || str_starts_with($currentPath, '/hr/help/'));
            $isHrAnnouncements = ($currentPath === '/hr/announcements' || str_starts_with($currentPath, '/hr/announcements/'));
            $isHrSupport     = ($currentPath === '/hr/support' || (str_starts_with($currentPath, '/hr/support/') && !str_starts_with($currentPath, '/hr/support/handlers')));
            $isHrNotifs      = str_starts_with($currentPath, '/notifications');
            $isHrDevSec      = str_starts_with($currentPath, '/account/security');
            ?>
            <div class="nav-section-group" data-section-id="hr-overview">
                <div class="nav-section-title">Overview</div>
                <div class="nav-section-items" id="navGroupHrOverview">
                    <a href="<?= url('/hr/dashboard') ?>" class="nav-item <?= $isHrDashboard ? 'active' : '' ?>"<?= $isHrDashboard ? ' aria-current="page"' : '' ?> title="Dashboard" data-tooltip="Dashboard" data-keywords="overview metrics summary home">
                        <span class="icon" aria-hidden="true"><?= icon('dashboard', 18) ?></span>
                        <span class="nav-item-label">Dashboard</span>
                    </a>
                </div>
            </div>

            <div class="nav-section-group" data-section-id="hr-workforce">
                <button type="button" class="nav-section-toggle" aria-expanded="true" aria-controls="navGroupHrWorkforce">
                    <span class="nav-section-toggle-label">Workforce Management</span>
                    <span class="nav-section-chevron" aria-hidden="true"><?= icon('chevron-right', 12) ?></span>
                </button>
                <div class="nav-section-items" id="navGroupHrWorkforce">
                    <a href="<?= url('/hr/employees') ?>" class="nav-item <?= $isHrEmployees ? 'active' : '' ?>"<?= $isHrEmployees ? ' aria-current="page"' : '' ?> title="Employees Directory" data-tooltip="Employees Directory" data-keywords="staff people records hire roster">
                        <span class="icon" aria-hidden="true"><?= icon('users', 18) ?></span>
                        <span class="nav-item-label">Employees Directory</span>
                    </a>
                    <a href="<?= url('/hr/departments') ?>" class="nav-item <?= $isHrDepartments ? 'active' : '' ?>"<?= $isHrDepartments ? ' aria-current="page"' : '' ?> title="Departments" data-tooltip="Departments" data-keywords="teams units division org">
                        <span class="icon" aria-hidden="true"><?= icon('building', 18) ?></span>
                        <span class="nav-item-label">Departments</span>
                    </a>
                    <a href="<?= url('/hr/positions') ?>" class="nav-item <?= $isHrPositions ? 'active' : '' ?>"<?= $isHrPositions ? ' aria-current="page"' : '' ?> title="Job Positions" data-tooltip="Job Positions" data-keywords="roles titles designations">
                        <span class="icon" aria-hidden="true"><?= icon('briefcase', 18) ?></span>
                        <span class="nav-item-label">Job Positions</span>
                    </a>
                    <a href="<?= url('/hr/correction-requests') ?>" class="nav-item <?= $isHrCorrections ? 'active' : '' ?>"<?= $isHrCorrections ? ' aria-current="page"' : '' ?> title="Profile Corrections" data-tooltip="Profile Corrections<?= $sidebarCorrectionPendingCount > 0 ? ' (' . (int)$sidebarCorrectionPendingCount . ' pending)' : '' ?>" data-keywords="correction requests approvals profile changes">
                        <span class="icon" aria-hidden="true"><?= icon('check-circle', 18) ?></span>
                        <span class="nav-item-label">Profile Corrections</span>
                        <?php if ($sidebarCorrectionPendingCount > 0): ?>
                            <span class="nav-badge nav-badge-pending" aria-label="<?= (int)$sidebarCorrectionPendingCount ?> pending profile corrections"><?= $sidebarCorrectionPendingCount > 99 ? '99+' : (int)$sidebarCorrectionPendingCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <div class="nav-section-group" data-section-id="hr-leave">
                <button type="button" class="nav-section-toggle" aria-expanded="true" aria-controls="navGroupHrLeave">
                    <span class="nav-section-toggle-label">Leave Management</span>
                    <span class="nav-section-chevron" aria-hidden="true"><?= icon('chevron-right', 12) ?></span>
                </button>
                <div class="nav-section-items" id="navGroupHrLeave">
                    <a href="<?= url('/leaves') ?>" class="nav-item <?= $isHrLeaveQueue ? 'active' : '' ?>"<?= $isHrLeaveQueue ? ' aria-current="page"' : '' ?> title="Leave Review Queue" data-tooltip="Leave Review Queue<?= $sidebarPendingCount > 0 ? ' (' . (int)$sidebarPendingCount . ' pending)' : '' ?>" data-keywords="approvals requests pending pto review">
                        <span class="icon" aria-hidden="true"><?= icon('calendar', 18) ?></span>
                        <span class="nav-item-label">Leave Review Queue</span>
                        <?php if ($sidebarPendingCount > 0): ?>
                            <span class="nav-badge nav-badge-pending" id="sidebarPendingBadge" aria-label="<?= (int)$sidebarPendingCount ?> pending leave requests"><?= $sidebarPendingCount > 99 ? '99+' : (int)$sidebarPendingCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= url('/leaves/balances') ?>" class="nav-item <?= $isHrBalances ? 'active' : '' ?>"<?= $isHrBalances ? ' aria-current="page"' : '' ?> title="Workforce Balances" data-tooltip="Workforce Balances" data-keywords="credits quota remaining entitlement">
                        <span class="icon" aria-hidden="true"><?= icon('balances', 18) ?></span>
                        <span class="nav-item-label">Workforce Balances</span>
                    </a>
                    <a href="<?= url('/hr/leave-types') ?>" class="nav-item <?= $isHrLeaveTypes ? 'active' : '' ?>"<?= $isHrLeaveTypes ? ' aria-current="page"' : '' ?> title="Leave Classifications" data-tooltip="Leave Classifications" data-keywords="types policies vacation sick">
                        <span class="icon" aria-hidden="true"><?= icon('layers', 18) ?></span>
                        <span class="nav-item-label">Leave Classifications</span>
                    </a>
                    <a href="<?= url('/hr/leave-qualifications') ?>" class="nav-item <?= $isHrQuals ? 'active' : '' ?>"<?= $isHrQuals ? ' aria-current="page"' : '' ?> title="Leave Qualifications" data-tooltip="Leave Qualifications" data-keywords="spic solo parent paternity sil exclusion">
                        <span class="icon" aria-hidden="true"><?= icon('check-circle', 18) ?></span>
                        <span class="nav-item-label">Leave Qualifications</span>
                    </a>
                </div>
            </div>

            <div class="nav-section-group" data-section-id="hr-analytics">
                <button type="button" class="nav-section-toggle" aria-expanded="true" aria-controls="navGroupHrAnalytics">
                    <span class="nav-section-toggle-label">Analytics &amp; Tools</span>
                    <span class="nav-section-chevron" aria-hidden="true"><?= icon('chevron-right', 12) ?></span>
                </button>
                <div class="nav-section-items" id="navGroupHrAnalytics">
                    <a href="<?= url('/reports') ?>" class="nav-item <?= $isHrReports ? 'active' : '' ?>"<?= $isHrReports ? ' aria-current="page"' : '' ?> title="Reports &amp; Analytics" data-tooltip="Reports &amp; Analytics" data-keywords="export csv metrics trends">
                        <span class="icon" aria-hidden="true"><?= icon('bar-chart', 18) ?></span>
                        <span class="nav-item-label">Reports &amp; Analytics</span>
                    </a>
                    <a href="<?= url('/activity-logs') ?>" class="nav-item <?= $isHrAuditLogs ? 'active' : '' ?>"<?= $isHrAuditLogs ? ' aria-current="page"' : '' ?> title="Audit Activity Logs" data-tooltip="Audit Activity Logs" data-keywords="trail history compliance">
                        <span class="icon" aria-hidden="true"><?= icon('file-text', 18) ?></span>
                        <span class="nav-item-label">Audit Activity Logs</span>
                    </a>
                </div>
            </div>

            <div class="nav-section-group" data-section-id="hr-comms">
                <button type="button" class="nav-section-toggle" aria-expanded="true" aria-controls="navGroupHrComms">
                    <span class="nav-section-toggle-label">Communications</span>
                    <span class="nav-section-chevron" aria-hidden="true"><?= icon('chevron-right', 12) ?></span>
                </button>
                <div class="nav-section-items" id="navGroupHrComms">
                    <a href="<?= url('/hr/announcements') ?>" class="nav-item <?= $isHrAnnouncements ? 'active' : '' ?>"<?= $isHrAnnouncements ? ' aria-current="page"' : '' ?> title="Announcements" data-tooltip="Announcements" data-keywords="announcements notices bulletins publish schedule">
                        <span class="icon" aria-hidden="true"><?= icon('megaphone', 18) ?></span>
                        <span class="nav-item-label">Announcements</span>
                    </a>
                    <a href="<?= url('/hr/help') ?>" class="nav-item <?= $isHrHelpAdmin ? 'active' : '' ?>"<?= $isHrHelpAdmin ? ' aria-current="page"' : '' ?> title="Help &amp; Support (Admin)" data-tooltip="Help &amp; Support (Admin)<?= $sidebarHrToAdminOpenCount > 0 ? ' (' . (int)$sidebarHrToAdminOpenCount . ' open)' : '' ?>" data-keywords="help support admin errors issues tickets">
                        <span class="icon" aria-hidden="true"><?= icon('alert-circle', 18) ?></span>
                        <span class="nav-item-label">Help &amp; Support (Admin)</span>
                        <?php if ($sidebarHrToAdminOpenCount > 0): ?>
                            <span class="nav-badge nav-badge-pending" aria-label="<?= (int)$sidebarHrToAdminOpenCount ?> open HR support tickets"><?= $sidebarHrToAdminOpenCount > 99 ? '99+' : (int)$sidebarHrToAdminOpenCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= url('/hr/support') ?>" class="nav-item <?= $isHrSupport ? 'active' : '' ?>"<?= $isHrSupport ? ' aria-current="page"' : '' ?> title="Employee Support Inbox" data-tooltip="Employee Support Inbox<?= $sidebarSupportOpenCount > 0 ? ' (' . (int)$sidebarSupportOpenCount . ' open)' : '' ?>" data-keywords="help support tickets complaints inbox">
                        <span class="icon" aria-hidden="true"><?= icon('shield', 18) ?></span>
                        <span class="nav-item-label">Employee Support Inbox</span>
                        <?php if ($sidebarSupportOpenCount > 0): ?>
                            <span class="nav-badge nav-badge-pending" aria-label="<?= (int)$sidebarSupportOpenCount ?> open employee support tickets"><?= $sidebarSupportOpenCount > 99 ? '99+' : (int)$sidebarSupportOpenCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= url('/notifications') ?>" class="nav-item <?= $isHrNotifs ? 'active' : '' ?>"<?= $isHrNotifs ? ' aria-current="page"' : '' ?> title="Notifications" data-tooltip="Notifications" data-keywords="alerts inbox messages">
                        <span class="icon" aria-hidden="true"><?= icon('bell', 18) ?></span>
                        <span class="nav-item-label">Notifications</span>
                        <span class="nav-badge" id="sidebarUnreadBadge"<?= $sidebarUnreadCount > 0 ? '' : ' hidden' ?> aria-label="<?= (int)$sidebarUnreadCount ?> unread notifications"><?= $sidebarUnreadCount > 99 ? '99+' : (int)$sidebarUnreadCount ?></span>
                    </a>
                </div>
            </div>

            <div class="nav-section-group" data-section-id="hr-session">
                <div class="nav-section-title">Account Session</div>
                <div class="nav-section-items" id="navGroupHrSession">
                    <a href="<?= url('/account/security') ?>" class="nav-item <?= $isHrDevSec ? 'active' : '' ?>"<?= $isHrDevSec ? ' aria-current="page"' : '' ?> title="Security &amp; Devices" data-tooltip="Security &amp; Devices" data-keywords="trusted devices otp security recovery">
                        <span class="icon" aria-hidden="true"><?= icon('shield', 18) ?></span>
                        <span class="nav-item-label">Security &amp; Devices</span>
                    </a>
                    <form method="POST" action="<?= url('/logout') ?>" style="margin: 0;">
                        <?= csrf_field() ?>
                        <button type="submit" class="nav-item nav-item-btn" title="Sign Out" data-tooltip="Sign Out" data-keywords="logout exit">
                            <span class="icon" aria-hidden="true"><?= icon('log-out', 18) ?></span>
                            <span class="nav-item-label">Sign Out</span>
                        </button>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <!-- ==============================================================
                 EMPLOYEE WORKSPACE NAVIGATION
                 ============================================================== -->
            <?php
            $isEmpDashboard = ($currentPath === '/employee/dashboard' || str_contains($currentPath, '/dashboard'));
            $isEmpProfile   = str_starts_with($currentPath, '/employee/profile');
            $isEmpBalances  = str_starts_with($currentPath, '/employee/leave-balances');
            $isEmpRequests  = (
                str_starts_with($currentPath, '/employee/leave-requests')
                || str_starts_with($currentPath, '/employee/leave-history')
                || preg_match('#^/leaves/\d+$#', $currentPath) === 1
            );
            $isEmpApply     = str_starts_with($currentPath, '/leaves/apply');
            $isEmpAnnouncements = str_starts_with($currentPath, '/employee/announcements');
            $isEmpSupport   = str_starts_with($currentPath, '/support');
            $isEmpNotifs    = str_starts_with($currentPath, '/notifications');
            $isEmpDevSec    = str_starts_with($currentPath, '/account/security');
            ?>
            <div class="nav-section-title">Workspace</div>
            <a href="<?= url('/employee/dashboard') ?>" class="nav-item <?= $isEmpDashboard ? 'active' : '' ?>"<?= $isEmpDashboard ? ' aria-current="page"' : '' ?> title="Dashboard" data-tooltip="Dashboard">
                <span class="icon" aria-hidden="true"><?= icon('dashboard', 18) ?></span>
                <span class="nav-item-label">Dashboard</span>
            </a>
            <a href="<?= url('/employee/profile') ?>" class="nav-item <?= $isEmpProfile ? 'active' : '' ?>"<?= $isEmpProfile ? ' aria-current="page"' : '' ?> title="My Profile" data-tooltip="My Profile">
                <span class="icon" aria-hidden="true"><?= icon('user', 18) ?></span>
                <span class="nav-item-label">My Profile</span>
            </a>

            <div class="nav-section-title">Leave &amp; Time Off</div>
            <a href="<?= url('/employee/leave-balances') ?>" class="nav-item <?= $isEmpBalances ? 'active' : '' ?>"<?= $isEmpBalances ? ' aria-current="page"' : '' ?> title="Leave Balances" data-tooltip="Leave Balances">
                <span class="icon" aria-hidden="true"><?= icon('calendar-check', 18) ?></span>
                <span class="nav-item-label">Leave Balances</span>
            </a>
            <a href="<?= url('/employee/leave-requests') ?>" class="nav-item <?= $isEmpRequests ? 'active' : '' ?>"<?= $isEmpRequests ? ' aria-current="page"' : '' ?> title="Leave Requests" data-tooltip="Leave Requests<?= $sidebarPendingCount > 0 ? ' (' . (int)$sidebarPendingCount . ' pending)' : '' ?>">
                <span class="icon" aria-hidden="true"><?= icon('file-text', 18) ?></span>
                <span class="nav-item-label">Leave Requests</span>
                <?php if ($sidebarPendingCount > 0): ?>
                    <span class="nav-badge nav-badge-pending" id="sidebarPendingBadge" aria-label="<?= (int)$sidebarPendingCount ?> pending leave requests"><?= $sidebarPendingCount > 99 ? '99+' : (int)$sidebarPendingCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= url('/leaves/apply') ?>" class="nav-item <?= $isEmpApply ? 'active' : '' ?>"<?= $isEmpApply ? ' aria-current="page"' : '' ?> title="File Leave Request" data-tooltip="File Leave Request">
                <span class="icon" aria-hidden="true"><?= icon('file-plus', 18) ?></span>
                <span class="nav-item-label">File Leave Request</span>
            </a>

            <div class="nav-section-title">Updates</div>
            <a href="<?= url('/employee/announcements') ?>" class="nav-item <?= $isEmpAnnouncements ? 'active' : '' ?>"<?= $isEmpAnnouncements ? ' aria-current="page"' : '' ?> title="Announcements" data-tooltip="Announcements<?= $sidebarUnreadAnnouncementsCount > 0 ? ' (' . (int)$sidebarUnreadAnnouncementsCount . ' unread)' : '' ?>">
                <span class="icon" aria-hidden="true"><?= icon('megaphone', 18) ?></span>
                <span class="nav-item-label">Announcements</span>
                <span class="nav-badge" id="sidebarAnnouncementsBadge"<?= $sidebarUnreadAnnouncementsCount > 0 ? '' : ' hidden' ?> aria-label="<?= (int)$sidebarUnreadAnnouncementsCount ?> unread announcements"><?= $sidebarUnreadAnnouncementsCount > 99 ? '99+' : (int)$sidebarUnreadAnnouncementsCount ?></span>
            </a>
            <a href="<?= url('/support') ?>" class="nav-item <?= $isEmpSupport ? 'active' : '' ?>"<?= $isEmpSupport ? ' aria-current="page"' : '' ?> title="Help & Support" data-tooltip="Help & Support">
                <span class="icon" aria-hidden="true"><?= icon('shield', 18) ?></span>
                <span class="nav-item-label">Help &amp; Support</span>
                <span class="nav-badge" id="sidebarSupportOpenBadge"<?= $sidebarSupportOpenCount > 0 ? '' : ' hidden' ?> aria-label="<?= (int)$sidebarSupportOpenCount ?> open support requests"><?= $sidebarSupportOpenCount > 99 ? '99+' : (int)$sidebarSupportOpenCount ?></span>
            </a>
            <a href="<?= url('/notifications') ?>" class="nav-item <?= $isEmpNotifs ? 'active' : '' ?>"<?= $isEmpNotifs ? ' aria-current="page"' : '' ?> title="Notifications" data-tooltip="Notifications">
                <span class="icon" aria-hidden="true"><?= icon('bell', 18) ?></span>
                <span class="nav-item-label">Notifications</span>
                <span class="nav-badge" id="sidebarUnreadBadge"<?= $sidebarUnreadCount > 0 ? '' : ' hidden' ?> aria-label="<?= (int)$sidebarUnreadCount ?> unread notifications"><?= $sidebarUnreadCount > 99 ? '99+' : (int)$sidebarUnreadCount ?></span>
            </a>

            <div class="nav-section-title">Session</div>
            <a href="<?= url('/account/security') ?>" class="nav-item <?= $isEmpDevSec ? 'active' : '' ?>"<?= $isEmpDevSec ? ' aria-current="page"' : '' ?> title="Security &amp; Devices" data-tooltip="Security &amp; Devices">
                <span class="icon" aria-hidden="true"><?= icon('shield', 18) ?></span>
                <span class="nav-item-label">Security &amp; Devices</span>
            </a>
            <form method="POST" action="<?= url('/logout') ?>" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" class="nav-item nav-item-btn" title="Sign Out" data-tooltip="Sign Out">
                    <span class="icon" aria-hidden="true"><?= icon('log-out', 18) ?></span>
                    <span class="nav-item-label">Sign Out</span>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($rawRole === 'admin' || $rawRole === 'hr'): ?>
            <div class="sidebar-search-empty" id="sidebarSearchEmpty" role="status" hidden>
                <p>No matching menu items</p>
                <button type="button" id="sidebarSearchResetBtn" class="sidebar-search-reset-btn">Clear filter</button>
            </div>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= $sidebarProfileUrl ?>" class="sidebar-user-link" title="<?= ($rawRole === 'hr' && empty($user['employee_id'])) ? 'HR Dashboard' : 'View Profile' ?>" aria-label="<?= e($userName) ?> (<?= e($roleTitle) ?>) — <?= ($rawRole === 'hr' && empty($user['employee_id'])) ? 'HR Dashboard' : 'View Profile' ?>" data-tooltip="<?= e($userName) ?> · <?= e($roleTitle) ?>">
            <div class="sidebar-user-avatar<?= $sidebarAvatarUrl ? ' has-photo' : '' ?>" aria-hidden="true">
                <?php if ($sidebarAvatarUrl): ?>
                    <img src="<?= e($sidebarAvatarUrl) ?>" alt="" class="avatar-img-cover" width="38" height="38" decoding="async">
                <?php else: ?>
                    <?= e($initials) ?>
                <?php endif; ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= e($userName) ?></div>
                <div class="sidebar-user-role"><?= e($roleTitle) ?></div>
            </div>
        </a>
        <button type="button" class="sidebar-toggle sidebar-footer-toggle" id="sidebarToggle" aria-label="Minimize sidebar (Ctrl+B)" title="Minimize sidebar (Ctrl+B)" aria-expanded="true" aria-controls="appSidebar" data-tooltip="Minimize sidebar (Ctrl+B)">
            <?= icon('panel-left-close', 18) ?>
        </button>
    </div>
</aside>
