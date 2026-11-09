<?php
/**
 * Notifications & Alerts View
 * Clean, filterable notification inbox with semantic SVG icons and read-state management.
 */
$notifications = $notifications ?? [];

$unreadNotifications = 0;
foreach ($notifications as $n) {
    if (empty($n['is_read'])) {
        $unreadNotifications++;
    }
}
$readNotifications = count($notifications) - $unreadNotifications;
?>

<div class="page-header">
    <div>
        <div class="inline-flex items-center gap-3">
            <h1 class="page-title">Notifications</h1>
            <?php if ($unreadNotifications > 0) : ?>
                <span class="badge badge-primary"><?= $unreadNotifications ?> unread</span>
            <?php endif; ?>
        </div>
        <p class="page-subtitle">Updates on your leave requests, approvals, and account activity</p>
    </div>
    <?php if ($unreadNotifications > 0) : ?>
        <div class="page-actions">
            <form method="POST" action="<?= url('/notifications/mark-all-read') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">
                    <?= icon('check', 16) ?>
                    <span>Mark All as Read</span>
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (!empty($notifications)) : ?>
        <!-- Filter & Search Bar -->
        <div class="emp-filter-toolbar">
            <div class="emp-filter-pills" role="tablist" aria-label="Filter notifications">
                <button type="button" class="emp-filter-pill active" data-notif-filter="all" role="tab" aria-selected="true">
                    <span>All</span>
                    <span class="emp-filter-count"><?= count($notifications) ?></span>
                </button>
                <button type="button" class="emp-filter-pill" data-notif-filter="unread" role="tab" aria-selected="false">
                    <span>Unread</span>
                    <span class="emp-filter-count"><?= $unreadNotifications ?></span>
                </button>
                <button type="button" class="emp-filter-pill" data-notif-filter="read" role="tab" aria-selected="false">
                    <span>Read</span>
                    <span class="emp-filter-count"><?= $readNotifications ?></span>
                </button>
            </div>

            <div class="emp-search-box">
                <span class="emp-search-icon"><?= icon('search', 15) ?></span>
                <input
                    type="search"
                    id="notificationSearch"
                    class="form-control"
                    placeholder="Search notifications..."
                    aria-label="Search notifications"
                >
            </div>
        </div>

        <div class="card-body p-0">
            <div class="emp-notif-list" id="notificationListContainer">
                <?php foreach ($notifications as $n) :
                    $isUnread = empty($n['is_read']);
                    $type = strtolower($n['type'] ?? 'info');
                    $iconName = match ($type) {
                        'success' => 'check-circle',
                        'warning' => 'alert-triangle',
                        'error', 'danger' => 'x-circle',
                        default   => 'bell',
                    };
                    $iconTone = match ($type) {
                        'success' => 'emp-notif-icon-success',
                        'warning' => 'emp-notif-icon-warning',
                        'error', 'danger' => 'emp-notif-icon-danger',
                        default   => 'emp-notif-icon-info',
                    };
                    $createdFormatted = !empty($n['created_at']) ? date('M j, Y \a\t g:i A', strtotime($n['created_at'])) : '';
                    ?>
                    <div
                        class="emp-notif-item <?= $isUnread ? 'is-unread' : '' ?>"
                        data-read-state="<?= $isUnread ? 'unread' : 'read' ?>"
                    >
                        <div class="emp-notif-icon <?= $iconTone ?>">
                            <?= icon($iconName, 18) ?>
                        </div>

                        <div class="emp-notif-content">
                            <div class="emp-notif-title-row">
                                <span class="emp-notif-title">
                                    <?php if ($isUnread) : ?>
                                        <span class="emp-unread-dot" aria-hidden="true" title="Unread notification"></span>
                                        <span class="sr-only">Unread: </span>
                                    <?php endif; ?>
                                    <?= e($n['title'] ?? 'Notification') ?>
                                </span>
                                <span class="emp-notif-time tabular-nums"><?= e($createdFormatted) ?></span>
                            </div>
                            <p class="emp-notif-msg"><?= e($n['message'] ?? '') ?></p>
                        </div>

                        <?php if ($isUnread) : ?>
                            <div class="flex items-center">
                                <form method="POST" action="<?= url('/notifications/' . (int)$n['id'] . '/read') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-secondary btn-sm" title="Mark notification as read">
                                        Mark Read
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Filtered Empty State -->
            <div id="notifFilterEmptyState" class="empty-state" style="display: none;">
                <div class="empty-state-icon"><?= icon('bell-off', 32) ?></div>
                <h3 class="empty-state-title">No Matching Notifications</h3>
                <p class="empty-state-desc">No notifications match your current filter or search criteria.</p>
            </div>
        </div>
    <?php else : ?>
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon"><?= icon('bell', 40) ?></div>
                <h3 class="empty-state-title">No Notifications Yet</h3>
                <p class="empty-state-desc">You are all caught up. Updates regarding your leave requests and profile will appear here.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterButtons = document.querySelectorAll('[data-notif-filter]');
    const searchInput = document.getElementById('notificationSearch');
    const container = document.getElementById('notificationListContainer');
    const emptyState = document.getElementById('notifFilterEmptyState');
    let currentFilter = 'all';

    function applyNotifFilter() {
        if (!container) return;
        const items = container.querySelectorAll('.emp-notif-item');
        const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
        let visibleCount = 0;

        items.forEach(function (item) {
            const state = item.getAttribute('data-read-state') || 'read';
            const matchesState = (currentFilter === 'all') || (state === currentFilter);
            const matchesQuery = !query || item.textContent.toLowerCase().includes(query);

            if (matchesState && matchesQuery) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    filterButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filterButtons.forEach(function (b) {
                b.classList.remove('active');
                b.setAttribute('aria-selected', 'false');
            });
            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
            currentFilter = btn.getAttribute('data-notif-filter') || 'all';
            applyNotifFilter();
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', applyNotifFilter);
    }
});
</script>
