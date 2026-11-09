<?php
/**
 * Global Flash Alerts Partial
 * Employee HRIS & Leave Management System
 */

$flashError = \Core\Session::getFlash('error');
$flashSuccess = \Core\Session::getFlash('success');
$flashWarning = \Core\Session::getFlash('warning');
$flashInfo = \Core\Session::getFlash('info');
?>
<?php if (!empty($flashError)): ?>
    <div class="alert alert-danger" role="alert">
        <span class="alert-icon" aria-hidden="true"><?= icon('x-circle', 18) ?></span>
        <div class="alert-content"><?= e($flashError) ?></div>
    </div>
<?php endif; ?>

<?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success" role="status" aria-live="polite">
        <span class="alert-icon" aria-hidden="true"><?= icon('check-circle', 18) ?></span>
        <div class="alert-content"><?= e($flashSuccess) ?></div>
    </div>
<?php endif; ?>

<?php if (!empty($flashWarning)): ?>
    <div class="alert alert-warning" role="alert">
        <span class="alert-icon" aria-hidden="true"><?= icon('alert-triangle', 18) ?></span>
        <div class="alert-content"><?= e($flashWarning) ?></div>
    </div>
<?php endif; ?>

<?php if (!empty($flashInfo)): ?>
    <div class="alert alert-info" role="status" aria-live="polite">
        <span class="alert-icon" aria-hidden="true"><?= icon('info', 18) ?></span>
        <div class="alert-content"><?= e($flashInfo) ?></div>
    </div>
<?php endif; ?>
