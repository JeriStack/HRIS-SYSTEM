<?php
$backUrl = url('/auth/help');
$backAriaLabel = 'Back to Help & Support';
require __DIR__ . '/_public_support_header.php';

$trackingView = $trackingView ?? null;
$activeToken = $activeToken ?? '';
$ticket = is_array($trackingView) ? ($trackingView['ticket'] ?? null) : null;
$messages = is_array($trackingView) ? ($trackingView['messages'] ?? []) : [];
$attachments = is_array($trackingView) ? ($trackingView['attachments'] ?? []) : [];

$maxFiles = (int)($maxFiles ?? 3);
$maxBytes = (int)($maxBytes ?? 5242880);
$maxMb = number_format($maxBytes / (1024 * 1024), 1);

$statusBadgeClass = 'ps-badge-info';
if (is_array($ticket)) {
    $rawStatus = (string)($ticket['status'] ?? '');
    $statusBadgeClass = match ($rawStatus) {
        'Resolved', 'Closed'   => 'ps-badge-success',
        'Waiting for Employee' => 'ps-badge-warning',
        default                => 'ps-badge-info',
    };
}
?>
    <main id="mainPublicSupport" class="ps-shell" role="main">
        <div class="ps-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= url('/login') ?>">Sign In</a>
            <span aria-hidden="true">/</span>
            <a href="<?= url('/auth/help') ?>">Help &amp; Support</a>
            <span aria-hidden="true">/</span>
            <span>Track Request</span>
        </div>

        <section class="ps-hero">
            <h1>Track Public Login Support Request</h1>
            <p class="ps-hero-desc">
                Access your redacted ticket status and official HR responses using the private tracking link or token emailed after submission.
            </p>
        </section>

        <?php if (!empty($error)): ?>
            <div class="alert-banner alert-error" role="alert">
                <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" />
                    <path d="m15 9-6 6M9 9l6 6" />
                </svg>
                <div><?= e((string)$error) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-banner alert-success" role="status">
                <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <path d="m9 11 3 3L22 4" />
                </svg>
                <div><?= e((string)$success) ?></div>
            </div>
        <?php endif; ?>

        <section class="ps-card" aria-labelledby="trackLookupHeading">
            <div class="ps-card-header">
                <h2 id="trackLookupHeading" class="ps-card-title">Tracking Lookup</h2>
            </div>
            <form method="POST" action="<?= url('/auth/support/track') ?>" data-ps-validate novalidate>
                <?= csrf_field() ?>
                <div class="ps-field">
                    <label for="trackingTokenInput" class="ps-label">Private Tracking Token or URL <span aria-hidden="true">*</span></label>
                    <p id="trackTokenHint" class="ps-hint">
                        For your privacy, only the recipient holding the secret tracking token from the confirmation email can open this ticket.
                    </p>
                    <input
                        type="text"
                        id="trackingTokenInput"
                        name="tracking_token"
                        class="ps-control tabular-nums"
                        value=""
                        required
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="none"
                        spellcheck="false"
                        aria-invalid="false"
                        aria-describedby="trackTokenHint"
                        data-error-id="trackTokenError"
                        placeholder="Paste your selector.verifier token or full tracking link">
                    <div id="trackTokenError" class="ps-field-error" data-empty="true"></div>
                </div>
                <div class="ps-actions-row">
                    <a href="<?= url('/auth/support/report') ?>" class="ps-btn-secondary">Submit New Support Request</a>
                    <button type="submit" class="ps-btn-primary" data-submit-lock>Look Up Ticket</button>
                </div>
            </form>
        </section>

        <?php if (is_array($ticket)): ?>
            <section class="ps-card" aria-labelledby="trackedCaseTitle">
                <div class="ps-card-header">
                    <div>
                        <h2 id="trackedCaseTitle" class="ps-card-title tabular-nums">
                            <?= e((string)$ticket['ticket_ref']) ?> &mdash; <?= e((string)$ticket['subject']) ?>
                        </h2>
                    </div>
                    <span id="trackedStatusBadge" class="ps-badge <?= $statusBadgeClass ?>">
                        <?= e((string)$ticket['display_status']) ?>
                    </span>
                </div>

                <div class="ps-summary-grid">
                    <div class="ps-meta-cell">
                        <div class="ps-meta-label">Category</div>
                        <div class="ps-meta-value"><?= e((string)$ticket['category']) ?></div>
                    </div>
                    <div class="ps-meta-cell">
                        <div class="ps-meta-label">Priority</div>
                        <div class="ps-meta-value"><?= e((string)$ticket['priority']) ?></div>
                    </div>
                    <div class="ps-meta-cell">
                        <div class="ps-meta-label">Submitted</div>
                        <div class="ps-meta-value tabular-nums">
                            <?= !empty($ticket['created_at']) ? e(date('M j, Y g:i A', strtotime((string)$ticket['created_at']))) : '—' ?>
                        </div>
                    </div>
                    <div class="ps-meta-cell">
                        <div class="ps-meta-label">Last Updated</div>
                        <div class="ps-meta-value tabular-nums">
                            <?= !empty($ticket['last_activity_at']) ? e(date('M j, Y g:i A', strtotime((string)$ticket['last_activity_at']))) : '—' ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($ticket['resolution_remarks'])): ?>
                    <div class="alert-banner alert-success" role="note" style="margin-bottom: 1rem;">
                        <div>
                            <strong>Resolution Summary:</strong> <?= e((string)$ticket['resolution_remarks']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <h3 style="margin: 0.75rem 0 0.65rem; font-size: 1rem; font-weight: 700; color: var(--heading);">
                    Public Case Conversation
                </h3>
                <div class="ps-thread">
                    <?php foreach ($messages as $msg):
                        $role = (string)($msg['sender_role'] ?? '');
                        $msgClass = 'ps-msg';
                        if (in_array($role, ['hr', 'admin'], true)) {
                            $msgClass .= ' ps-msg--hr';
                        } elseif ($role === 'system') {
                            $msgClass .= ' ps-msg--system';
                        }
                    ?>
                        <article class="<?= $msgClass ?>">
                            <div class="ps-msg-meta">
                                <span class="ps-msg-sender"><?= e((string)($msg['sender_label'] ?? 'Update')) ?></span>
                                <span class="tabular-nums">
                                    <?= !empty($msg['created_at']) ? e(date('M j, Y g:i A', strtotime((string)$msg['created_at']))) : '' ?>
                                </span>
                            </div>
                            <div class="ps-msg-body"><?= e((string)$msg['body']) ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($attachments)): ?>
                    <h3 style="margin: 1.15rem 0 0.5rem; font-size: 1rem; font-weight: 700; color: var(--heading);">
                        Shared Attachments
                    </h3>
                    <ul class="ps-file-list">
                        <?php foreach ($attachments as $att): ?>
                            <li class="ps-file-item">
                                <span><?= e((string)$att['original_filename']) ?></span>
                                <a href="<?= url('/auth/support/attachments/' . (int)$att['id'] . '?token=' . rawurlencode((string)$activeToken)) ?>" class="ps-btn-secondary" style="padding: 0.35rem 0.7rem; font-size: 0.75rem;">
                                    Download (<?= number_format(max(1, (int)$att['file_size_bytes']) / 1024, 1) ?> KB)
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <?php if ((string)($ticket['status'] ?? '') !== 'Closed'): ?>
                <section class="ps-card" aria-labelledby="publicReplyHeading">
                    <div class="ps-card-header">
                        <h2 id="publicReplyHeading" class="ps-card-title">Send a Follow-Up Reply</h2>
                    </div>
                    <form method="POST" action="<?= url('/auth/support/track/reply') ?>" enctype="multipart/form-data" data-ps-validate novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="tracking_token" value="<?= e((string)$activeToken) ?>">

                        <div class="ps-field">
                            <label for="publicReplyBody" class="ps-label">Additional Details or Response <span aria-hidden="true">*</span></label>
                            <p id="publicReplyHint" class="ps-hint">Respond to HR Support questions here. Never share passwords, OTP codes, or private tokens.</p>
                            <textarea
                                id="publicReplyBody"
                                name="body"
                                class="ps-control"
                                rows="4"
                                required
                                minlength="2"
                                maxlength="5000"
                                aria-invalid="false"
                                aria-describedby="publicReplyHint"
                                data-error-id="publicReplyError"
                                placeholder="Provide requested context or clarify whether the sign-in issue persists..."></textarea>
                            <div id="publicReplyError" class="ps-field-error" data-empty="true"></div>
                        </div>

                        <div class="ps-field">
                            <label for="publicReplyFiles" class="ps-label">Attach Files (Optional)</label>
                            <p id="publicReplyFilesHint" class="ps-hint">PNG, JPG, WEBP, PDF, or TXT (up to <?= $maxFiles ?> files, max <?= $maxMb ?> MB each).</p>
                            <input
                                type="file"
                                id="publicReplyFiles"
                                name="attachments[]"
                                class="ps-control"
                                multiple
                                accept=".png,.jpg,.jpeg,.webp,.pdf,.txt"
                                aria-describedby="publicReplyFilesHint"
                                data-ps-files
                                data-max-files="<?= $maxFiles ?>"
                                data-max-bytes="<?= $maxBytes ?>"
                                data-list-id="publicReplyFileList"
                                data-status-id="publicReplyFileStatus">
                            <ul id="publicReplyFileList" class="ps-file-list" hidden></ul>
                            <div id="publicReplyFileStatus" class="ps-field-error" data-empty="true" hidden></div>
                        </div>

                        <div class="ps-actions-row">
                            <span class="ps-hint">Updates notify the assigned HR Support team immediately.</span>
                            <button type="submit" class="ps-btn-primary" data-submit-lock>Post Follow-Up Reply</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
<?php require __DIR__ . '/_public_support_footer.php'; ?>
