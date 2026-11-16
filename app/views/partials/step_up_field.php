<?php
/**
 * Step-up re-verification field for sensitive HR operations.
 *
 * Rendered only when the acting user's recent MFA / step-up window has elapsed.
 * The server (App\Services\StepUpAuthService) remains the authority: this field
 * only collects the current password; it never decides whether verification passed.
 *
 * Expected variables:
 * @var bool        $stepUpRequired Whether the server requires re-verification
 * @var string      $stepUpFieldId  Unique DOM id for the input (several forms may share a page)
 * @var string      $stepUpHelp     Context-specific help text
 * @var bool        $stepUpOptional Whether the field is only needed for some submissions
 */
$stepUpFieldId  = $stepUpFieldId ?? 'current_password';
$stepUpHelp     = $stepUpHelp ?? 'For your security, confirm your own current password to complete this action.';
$stepUpOptional = $stepUpOptional ?? false;
?>
<?php if (!empty($stepUpRequired)): ?>
    <div class="step-up-field" style="background: var(--color-bg-subtle, #F8FAFC); border: 1px solid var(--color-border, #E2E8F0); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.25rem; text-align: left;">
        <label for="<?= e($stepUpFieldId) ?>" style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--color-text-main, #1E293B); margin-bottom: 0.35rem;">
            Confirm Your Current Password<?php if (!$stepUpOptional): ?> <span style="color: var(--color-error, #DC2626);" aria-hidden="true">*</span><?php endif; ?>
        </label>
        <p id="<?= e($stepUpFieldId) ?>_help" style="color: var(--color-text-body, #475569); font-size: 0.75rem; margin: 0 0 0.5rem 0;">
            <?= e($stepUpHelp) ?>
        </p>
        <input type="password"
               id="<?= e($stepUpFieldId) ?>"
               name="current_password"
               autocomplete="current-password"
               aria-describedby="<?= e($stepUpFieldId) ?>_help"
               placeholder="Enter your current password"
               <?= $stepUpOptional ? '' : 'required' ?>
               style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--color-border-dark, #CBD5E1); border-radius: 0.375rem; font-size: 0.875rem; box-sizing: border-box;">
    </div>
<?php endif; ?>
