<?php
/**
 * Notifications Inbox View
 * Employee HRIS & Leave Management System
 */
?>
<div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
    <div>
        <h1 style="font-size: var(--font-size-2xl); font-weight: 700; color: var(--color-text-main); margin-bottom: var(--space-1);">Notifications &amp; Alerts</h1>
        <p style="color: var(--color-text-muted); margin: 0; font-size: var(--font-size-sm);">
            Stay informed with real-time system alerts, leave workflow determinations, and operational updates.
        </p>
    </div>
    <div style="display: flex; gap: var(--space-3); align-items: center;">
        <?php if (!empty($unreadCount) && $unreadCount > 0): ?>
            <span class="badge badge-warning" style="padding: 6px 12px; font-size: 0.85rem;">
                <?= (int)$unreadCount ?> Unread
            </span>
            <form action="<?= url('/notifications/mark-all-read') ?>" method="POST" style="display: inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Mark All as Read
                </button>
            </form>
        <?php else: ?>
            <span class="badge badge-success" style="padding: 6px 12px; font-size: 0.85rem;">All Caught Up</span>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($notifications)): ?>
    <div class="card" style="padding: var(--space-8); text-align: center;">
        <div style="width: 56px; height: 56px; border-radius: 50%; background-color: var(--color-slate-100); display: inline-flex; align-items: center; justify-content: center; margin-bottom: var(--space-4); color: var(--color-text-muted);">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
        </div>
        <h3 style="font-size: var(--font-size-lg); font-weight: 600; color: var(--color-text-main); margin-bottom: var(--space-2);">No Notifications</h3>
        <p style="color: var(--color-text-muted); max-width: 420px; margin: 0 auto; font-size: var(--font-size-sm);">
            You have no active or historical notifications in your inbox. Relevant updates about leave filings and account actions will appear here.
        </p>
    </div>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: var(--space-3);">
        <?php foreach ($notifications as $notif): ?>
            <?php
            $isUnread = empty($notif['is_read']);
            $cardBorder = $isUnread ? 'border-left: 4px solid var(--color-primary-500);' : 'border-left: 4px solid var(--color-slate-300);';
            $bg = $isUnread ? '#FFFFFF' : 'var(--color-slate-50)';
            
            $badgeClass = 'badge-info';
            if ($notif['type'] === 'success') $badgeClass = 'badge-success';
            elseif ($notif['type'] === 'warning') $badgeClass = 'badge-warning';
            elseif ($notif['type'] === 'error') $badgeClass = 'badge-danger';
            ?>
            <div class="card" style="padding: var(--space-4) var(--space-5); background: <?= $bg ?>; <?= $cardBorder ?> display: flex; justify-content: space-between; align-items: flex-start; gap: var(--space-4);">
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-1); flex-wrap: wrap;">
                        <span class="badge <?= $badgeClass ?>" style="text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.04em;">
                            <?= htmlspecialchars(strtoupper($notif['type'])) ?>
                        </span>
                        <h4 style="font-size: var(--font-size-base); font-weight: 600; color: var(--color-text-main); margin: 0;">
                            <?= htmlspecialchars($notif['title']) ?>
                        </h4>
                        <?php if ($isUnread): ?>
                            <span style="font-size: 0.75rem; font-weight: 600; color: var(--color-primary-600); background: var(--color-primary-50); padding: 2px 6px; border-radius: var(--radius-sm);">New</span>
                        <?php endif; ?>
                    </div>
                    <p style="color: var(--color-text-body); font-size: var(--font-size-sm); margin: var(--space-1) 0 var(--space-2) 0; line-height: 1.5;">
                        <?= htmlspecialchars($notif['message']) ?>
                    </p>
                    <span style="font-size: 0.75rem; color: var(--color-text-muted); display: inline-flex; align-items: center; gap: 4px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?>
                    </span>
                </div>
                <?php if ($isUnread): ?>
                    <div>
                        <form action="<?= url('/notifications/' . $notif['id'] . '/read') ?>" method="POST" style="margin: 0;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm" title="Mark as read" style="white-space: nowrap; font-size: 0.75rem; padding: 4px 8px;">
                                Mark Read
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
