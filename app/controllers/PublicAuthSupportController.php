<?php

/**
 * Public Authentication Help, Password Recovery & Login Support Controller
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Serves unauthenticated public help hub, OTP troubleshooting, forgot-password /
 * reset-password workflows, and pre-login AUTH-* ticket submission/tracking.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PasswordPolicy;
use App\Services\PasswordRecoveryService;
use App\Services\PublicAuthSupportService;
use Core\Controller;
use Core\Session;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PublicAuthSupportController extends Controller
{
    private PasswordRecoveryService $recoveryService;
    private PublicAuthSupportService $publicSupport;
    private bool $terminateOnRedirect;
    /** @var array{url: string, status: int}|null */
    private ?array $lastRedirect = null;

    public function __construct(
        ?PasswordRecoveryService $recoveryService = null,
        ?PublicAuthSupportService $publicSupport = null,
        bool $terminateOnRedirect = true
    ) {
        $this->recoveryService = $recoveryService ?? new PasswordRecoveryService();
        $this->publicSupport = $publicSupport ?? new PublicAuthSupportService();
        $this->terminateOnRedirect = $terminateOnRedirect;
    }

    /**
     * Expose last navigation target when redirect termination is disabled in PHPUnit tests.
     *
     * @return array{url: string, status: int}|null
     */
    public function getLastRedirect(): ?array
    {
        return $this->lastRedirect;
    }

    /**
     * Public Help & Support Hub presenting the 4 primary assistance options + tokenized tracker lookup.
     * Route: GET /auth/help
     */
    public function helpHub(): void
    {
        $error = Session::getFlash('error');
        $success = Session::getFlash('success');

        $this->view('auth.help', [
            'pageTitle' => 'Help & Support - NEXORA HR',
            'error'     => $error,
            'success'   => $success,
        ], null);
    }

    /**
     * Self-service OTP troubleshooting guide & recovery pathways.
     * Route: GET /auth/help/otp
     */
    public function otpHelp(): void
    {
        $this->view('auth.otp_help', [
            'pageTitle' => 'OTP Verification Problems - NEXORA HR',
        ], null);
    }

    /**
     * Render Forgot Password form.
     * Route: GET /forgot-password
     */
    public function showForgotPasswordForm(): void
    {
        $this->view('auth.forgot_password', [
            'pageTitle' => 'Forgot Password - NEXORA HR',
            'error'     => Session::getFlash('error'),
            'success'   => Session::getFlash('success'),
            'oldEmail'  => (string)Session::getFlash('old_forgot_email', ''),
        ], null);
    }

    /**
     * Process Forgot Password form with strict anti-enumeration & rate limiting.
     * Route: POST /forgot-password
     */
    public function submitForgotPassword(): void
    {
        $email = trim((string)($_POST['email'] ?? ''));

        try {
            $result = $this->recoveryService->requestReset(
                $email,
                client_ip(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );
        } catch (Throwable $e) {
            error_log('[PublicAuthSupportController::submitForgotPassword] ' . get_class($e));
            Session::flash('success', PasswordRecoveryService::GENERIC_ACCEPTANCE_MESSAGE);
            $this->navigate('/forgot-password');
            return;
        }

        if (!$result['ok'] && $result['status'] === 'validation_error') {
            Session::flash('error', $result['message']);
            Session::flash('old_forgot_email', $email);
            $this->navigate('/forgot-password');
            return;
        }

        Session::flash('success', PasswordRecoveryService::GENERIC_ACCEPTANCE_MESSAGE);
        $this->navigate('/forgot-password');
    }

    /**
     * Validate a password reset token without consuming it and render reset form.
     * Route: GET /reset-password
     */
    public function showResetPasswordForm(): void
    {
        $this->applySensitiveTokenHeaders();

        $token = trim((string)($_GET['token'] ?? Session::getFlash('_reset_token_retry', '')));
        $error = Session::getFlash('error');
        $resetErrors = Session::getFlash('reset_errors', []);

        $tokenValid = false;
        $tokenError = $error;

        if ($token === '') {
            $tokenError = $tokenError ?: PasswordRecoveryService::INVALID_TOKEN_MESSAGE;
        } else {
            $check = $this->recoveryService->validateResetToken($token);
            $tokenValid = $check['valid'];
            if (!$tokenValid) {
                $tokenError = $tokenError ?: $check['message'];
            }
        }

        $this->view('auth.reset_password', [
            'pageTitle'     => 'Reset Password - NEXORA HR',
            'token'         => $tokenValid ? $token : '',
            'tokenValid'    => $tokenValid,
            'tokenError'    => $tokenError,
            'error'         => $error,
            'resetErrors'   => is_array($resetErrors) ? $resetErrors : [],
            'passwordRules' => PasswordPolicy::rulesSummary(),
        ], null);
    }

    /**
     * Validate complexity, consume reset token atomically, revoke trust/sessions, and redirect to login.
     * Route: POST /reset-password
     */
    public function submitResetPassword(): void
    {
        $this->applySensitiveTokenHeaders();

        $token = trim((string)($_POST['token'] ?? ''));
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        $result = $this->recoveryService->completeReset(
            $token,
            $newPassword,
            $confirmPassword,
            client_ip(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        if ($result['ok']) {
            Session::flash('success', $result['message']);
            $this->navigate('/login');
            return;
        }

        Session::flash('error', $result['message']);
        if ($result['status'] === 'validation_error' && $token !== '') {
            Session::flash('reset_errors', $result['errors']);
            Session::flash('_reset_token_retry', $token);
        }

        $this->navigate('/reset-password');
    }

    /**
     * Render Public Login Issue / Contact Support submission page.
     * Route: GET /auth/support/report
     */
    public function showReportForm(): void
    {
        $categories = (array)config('support.public_auth_categories', []);
        $requestedCat = trim((string)($_GET['category'] ?? ''));
        $oldInput = Session::getFlash('old_public_support', []);
        if (!is_array($oldInput)) {
            $oldInput = [];
        }
        if ($requestedCat !== '' && isset($categories[$requestedCat]) && empty($oldInput['category'])) {
            $oldInput['category'] = $requestedCat;
        }

        $successData = Session::getFlash('public_support_success');

        $this->view('auth.support_report', [
            'pageTitle'       => 'Report Login Issue / Contact Support - NEXORA HR',
            'categories'      => $categories,
            'priorities'      => (array)config('support.priorities', ['Low', 'Medium', 'High', 'Urgent']),
            'maxBytes'        => (int)config('support.attachment_max_bytes', 5 * 1024 * 1024),
            'maxFiles'        => (int)config('support.max_attachments_per_submission', 3),
            'error'           => Session::getFlash('error'),
            'old'             => $oldInput,
            'submissionResult' => is_array($successData) ? $successData : null,
        ], null);
    }

    /**
     * Validate and store an unauthenticated public login/support ticket (AUTH-YYYY-NNNNN).
     * Route: POST /auth/support/report
     */
    public function submitReport(): void
    {
        try {
            $result = $this->publicSupport->submitPublicRequest(
                $_POST,
                $_FILES['attachments'] ?? null,
                client_ip(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            $requesterEmail = strtolower(trim((string)($_POST['requester_email'] ?? '')));
            Session::flash('public_support_success', [
                'ticket_ref'   => $result['ticket_ref'],
                'email_sent'   => $result['email_sent'],
                'masked_email' => mask_email($requesterEmail),
            ]);
            $this->navigate('/auth/support/report');
        } catch (InvalidArgumentException | RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            Session::flash('old_public_support', [
                'requester_name'  => trim((string)($_POST['requester_name'] ?? '')),
                'requester_email' => trim((string)($_POST['requester_email'] ?? '')),
                'category'        => trim((string)($_POST['category'] ?? '')),
                'priority'        => trim((string)($_POST['priority'] ?? '')),
                'subject'         => trim((string)($_POST['subject'] ?? '')),
                'description'     => trim((string)($_POST['description'] ?? '')),
                'issue_datetime'  => trim((string)($_POST['issue_datetime'] ?? '')),
            ]);
            $this->navigate('/auth/support/report');
        }
    }

    /**
     * Render tokenized Public Support Tracker (GET ?token=... or POST tracking_token lookup).
     * Never exposes internal HR notes, HR staff names, internal attachments, or account linkage.
     * Routes: GET /auth/support/track, POST /auth/support/track
     */
    public function trackTicket(): void
    {
        $this->applySensitiveTokenHeaders();

        $postedToken = trim((string)($_POST['tracking_token'] ?? ''));
        $queryToken = trim((string)($_GET['token'] ?? ''));
        $retryToken = trim((string)Session::getFlash('_track_token_retry', ''));

        $candidateToken = $postedToken !== ''
            ? $postedToken
            : ($queryToken !== '' ? $queryToken : $retryToken);

        if ($candidateToken === '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $error = 'Please paste the full private tracking token or link from your confirmation email.';
            $this->view('auth.support_track', [
                'pageTitle'    => 'Track Support Request - NEXORA HR',
                'trackingView' => null,
                'activeToken'  => '',
                'error'        => $error,
                'success'      => Session::getFlash('success'),
                'maxBytes'     => (int)config('support.attachment_max_bytes', 5 * 1024 * 1024),
                'maxFiles'     => (int)config('support.max_attachments_per_submission', 3),
            ], null);
            return;
        }

        // Support pasting either raw `selector.verifier` or the full tracking URL containing `?token=...`
        if ($candidateToken !== '' && str_contains($candidateToken, 'token=')) {
            $parsedQuery = parse_url($candidateToken, PHP_URL_QUERY);
            if (is_string($parsedQuery)) {
                parse_str($parsedQuery, $qsParams);
                if (!empty($qsParams['token']) && is_string($qsParams['token'])) {
                    $candidateToken = trim($qsParams['token']);
                }
            }
        }

        $trackingView = null;
        $flashError = Session::getFlash('error');
        $error = $flashError;

        if ($candidateToken !== '') {
            $resolved = $this->publicSupport->resolvePublicTrackingView($candidateToken);
            if ($resolved['valid']) {
                $trackingView = $resolved;
                Session::set('_public_support_active_token', $candidateToken);
            } else {
                $error = $error ?: $resolved['message'];
                Session::remove('_public_support_active_token');
            }
        } elseif (Session::has('_public_support_active_token')) {
            $sessionToken = trim((string)Session::get('_public_support_active_token', ''));
            if ($sessionToken !== '') {
                $resolved = $this->publicSupport->resolvePublicTrackingView($sessionToken);
                if ($resolved['valid']) {
                    $trackingView = $resolved;
                    $candidateToken = $sessionToken;
                } else {
                    Session::remove('_public_support_active_token');
                }
            }
        }

        $this->view('auth.support_track', [
            'pageTitle'    => 'Track Support Request - NEXORA HR',
            'trackingView' => $trackingView,
            'activeToken'  => $trackingView !== null ? $candidateToken : '',
            'error'        => $error,
            'success'      => Session::getFlash('success'),
            'maxBytes'     => (int)config('support.attachment_max_bytes', 5 * 1024 * 1024),
            'maxFiles'     => (int)config('support.max_attachments_per_submission', 3),
        ], null);
    }

    /**
     * Allow unauthenticated requester with valid tracking token to post a follow-up reply.
     * Route: POST /auth/support/track/reply
     */
    public function replyToTrackedTicket(): void
    {
        $this->applySensitiveTokenHeaders();

        $token = trim((string)($_POST['tracking_token'] ?? Session::get('_public_support_active_token', '')));
        $body = trim((string)($_POST['body'] ?? ''));

        try {
            $updated = $this->publicSupport->submitPublicFollowUpReply(
                $token,
                $body,
                $_FILES['attachments'] ?? null,
                client_ip(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            Session::flash('success', "Follow-up reply posted to {$updated['ticket_ref']}.");
            Session::flash('_track_token_retry', $token);
            $this->navigate('/auth/support/track');
        } catch (InvalidArgumentException | RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            if ($token !== '') {
                Session::flash('_track_token_retry', $token);
            }
            $this->navigate('/auth/support/track');
        }
    }

    /**
     * Serve non-internal attachment for a public support ticket when authorized by tracking token.
     * Route: GET /auth/support/attachments/{id}
     */
    public function downloadPublicAttachment(int|string $id): void
    {
        $this->applySensitiveTokenHeaders();

        $attachmentId = (int)$id;
        $token = trim((string)($_GET['token'] ?? Session::get('_public_support_active_token', '')));

        try {
            $resolved = $this->publicSupport->resolvePublicAttachmentDownload($attachmentId, $token);
        } catch (RuntimeException $e) {
            $code = (int)$e->getCode() === 404 ? 404 : 403;
            if (!headers_sent()) {
                http_response_code($code);
            }
            $this->view($code === 404 ? 'errors.404' : 'errors.403', [
                'pageTitle' => $code === 404 ? '404 Not Found' : '403 Forbidden - Access Restricted',
                'message'   => $e->getMessage(),
            ], null);
            return;
        }

        $attachment = $resolved['attachment'];
        $filePath = $resolved['file_path'];
        $safeDownloadName = str_replace(['"', "\r", "\n"], '', (string)($attachment['original_filename'] ?? 'attachment'));
        $mimeType = (string)($attachment['mime_type'] ?? 'application/octet-stream');

        if (!headers_sent()) {
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . (string)filesize($filePath));
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: attachment; filename="' . $safeDownloadName . '"');
        }

        readfile($filePath);
    }

    private function applySensitiveTokenHeaders(): void
    {
        if (!headers_sent()) {
            header('Referrer-Policy: no-referrer');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
    }

    private function navigate(string $path, int $status = 302): void
    {
        $this->lastRedirect = ['url' => $path, 'status' => $status];
        if (!$this->terminateOnRedirect) {
            return;
        }
        $this->redirect($path, $status);
    }
}
