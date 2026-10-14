<?php
/**
 * Global Flash Alerts Partial
 * Employee HRIS & Leave Management System
 */

$flashError = \Core\Session::getFlash('error');
$flashSuccess = \Core\Session::getFlash('success');
$flashWarning = \Core\Session::getFlash('warning');
?>
<?php if (!empty($flashError)): ?>
    <div class="alert alert-danger" role="alert" style="margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 0.5rem;">
        <span aria-hidden="true" style="font-weight: bold;">✕</span>
        <div><?= e($flashError) ?></div>
    </div>
<?php endif; ?>

<?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success" role="alert" style="margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 0.5rem;">
        <span aria-hidden="true" style="font-weight: bold;">✓</span>
        <div><?= e($flashSuccess) ?></div>
    </div>
<?php endif; ?>

<?php if (!empty($flashWarning)): ?>
    <div class="alert alert-warning" role="alert" style="margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 0.5rem;">
        <span aria-hidden="true" style="font-weight: bold;">⚠️</span>
        <div><?= e($flashWarning) ?></div>
    </div>
<?php endif; ?>
