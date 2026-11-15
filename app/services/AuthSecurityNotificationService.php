<?php

/**
 * Authentication Security Notification Service
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Delivers security alerts (trusted device added, revoked, recovery completed,
 * anomalous token use) cleanly separated from OTP verification emails.
 * Never logs or renders raw tokens, secret hashes, or OTP codes.
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Realtime\EventAuthorizer;
use Throwable;

class AuthSecurityNotificationService
{
    private Notification $notifications;
    private MailService $mailer;

    public function __construct(?Notification $notifications = null, ?MailService $mailer = null)
    {
        $this->notifications = $notifications ?? new Notification();
        $this->mailer = $mailer ?? new MailService();
    }

    /**
     * Notify the account owner after a new trusted browser is registered.
     */
    public function notifyDeviceRegistered(array $user, string $deviceName, string $expiresAtDisplay, ?string $ipAddress = null): void
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $safeDevice = trim($deviceName) !== '' ? trim($deviceName) : 'Web Browser';
        $message = "A trusted browser ({$safeDevice}) was registered for your account until {$expiresAtDisplay}. "
            . 'You can review or revoke trusted devices anytime in Security & Trusted Devices.';

        $this->createInAppNotification(
            $userId,
            'New Trusted Device Added',
            $message,
            'info'
        );

        $this->queueSecurityEmail(
            $user,
            'Security Notice: New Trusted Browser Registered',
            'New Trusted Device Registered',
            "Your account now trusts <strong>" . e($safeDevice) . "</strong> for sign-in OTP convenience until <strong>"
                . e($expiresAtDisplay) . "</strong>."
                . ($ipAddress ? ' Request IP context: <code>' . e($ipAddress) . '</code>.' : ''),
            'If you did not approve this browser, open Account Security &amp; Trusted Devices immediately to revoke all devices and change your password.'
        );
    }

    /**
     * Notify the account owner after a trusted device is revoked.
     */
    public function notifyDeviceRevoked(array $user, string $deviceName, string $reason): void
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $safeDevice = trim($deviceName) !== '' ? trim($deviceName) : 'Trusted Browser';
        $message = "Trusted access for ({$safeDevice}) has been revoked. Email OTP verification will be required next time you sign in from that browser.";

        $this->createInAppNotification(
            $userId,
            'Trusted Device Revoked',
            $message,
            'warning'
        );

        $this->queueSecurityEmail(
            $user,
            'Security Notice: Trusted Device Revoked',
            'Trusted Device Access Revoked',
            'Trusted browser access for <strong>' . e($safeDevice) . '</strong> was revoked (' . e($reason) . ').',
            'Future sign-ins from that browser will require full Email OTP verification.'
        );
    }

    /**
     * Notify the account owner when all trusted devices are revoked at once.
     */
    public function notifyAllDevicesRevoked(array $user, int $revokedCount, string $reason): void
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $message = $revokedCount > 0
            ? "All {$revokedCount} active trusted browser(s) on your account were revoked. Email OTP verification will be required on every browser next sign-in."
            : 'All active trusted browser sessions were cleared for your account.';

        $this->createInAppNotification(
            $userId,
            'All Trusted Devices Revoked',
            $message,
            'warning'
        );

        $this->queueSecurityEmail(
            $user,
            'Security Notice: All Trusted Devices Revoked',
            'All Trusted Devices Revoked',
            'All active trusted browsers (' . (int)$revokedCount . ') were revoked on your account (' . e($reason) . ').',
            'If you initiated this action, no further steps are needed. Otherwise, update your password immediately.'
        );
    }

    /**
     * Notify the account owner when a lost-device recovery action completes.
     */
    public function notifyRecoveryCompleted(array $user, int $revokedCount, ?string $ipAddress = null): void
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $message = "Trusted-device account recovery was completed: {$revokedCount} prior device trust record(s) revoked and fresh OTP step-up enforced.";

        $this->createInAppNotification(
            $userId,
            'Account Recovery Completed',
            $message,
            'warning'
        );

        $this->queueSecurityEmail(
            $user,
            'Security Notice: Trusted Device Recovery Completed',
            'Trusted Device Recovery Completed',
            'A lost-device recovery workflow revoked <strong>' . (int)$revokedCount . '</strong> trusted browser credential(s) on your account.'
                . ($ipAddress ? ' Client IP context: <code>' . e($ipAddress) . '</code>.' : ''),
            'You must verify with Email OTP before any replacement browser can be trusted.'
        );
    }

    /**
     * Notify the account owner when an invalid or replayed trusted-device credential is rejected.
     */
    public function notifyAnomalyDetected(array $user, string $deviceName, ?string $ipAddress = null): void
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $safeDevice = trim($deviceName) !== '' ? trim($deviceName) : 'Trusted Browser';
        $message = "Suspicious trusted-device token activity detected on ({$safeDevice}). The device was automatically revoked and OTP verification is required.";

        $this->createInAppNotification(
            $userId,
            'Suspicious Device Token Blocked',
            $message,
            'error'
        );

        $this->queueSecurityEmail(
            $user,
            'Security Alert: Suspicious Device Token Blocked',
            'Suspicious Trusted-Device Activity Detected',
            'An invalid or replayed credential for <strong>' . e($safeDevice) . '</strong> was rejected and revoked automatically.'
                . ($ipAddress ? ' Request IP context: <code>' . e($ipAddress) . '</code>.' : ''),
            'Please sign in with Email OTP, review your active trusted devices, and update your password if you do not recognize this attempt.'
        );
    }

    private function createInAppNotification(int $userId, string $title, string $message, string $type): void
    {
        try {
            $this->notifications->create(
                $userId,
                $title,
                $message,
                $type,
                EventAuthorizer::SECURITY_DEVICE_ALERT,
                $userId
            );
        } catch (Throwable $e) {
            error_log('[AuthSecurityNotification] In-app notification failed: ' . get_class($e));
        }
    }

    private function queueSecurityEmail(array $user, string $subject, string $headline, string $bodyHtml, string $advisoryHtml): void
    {
        $email = trim((string)($user['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $name = trim((string)($user['name'] ?? $user['username'] ?? 'User'));
        $appName = e((string)config('config.app_name', 'NEXORA HR'));
        $safeName = e($name);
        $safeHeadline = e($headline);
        $year = date('Y');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>{$safeHeadline}</title></head>
<body style="margin:0;padding:24px;background:#F1F5F9;font-family:Arial,Helvetica,sans-serif;color:#0F172A;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;margin:0 auto;background:#FFFFFF;border:1px solid #CBD5E1;border-radius:10px;overflow:hidden;">
    <tr>
      <td style="background:#0F2C59;padding:20px 24px;color:#FFFFFF;">
        <div style="font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:#93C5FD;font-weight:700;">{$appName} Security Notice</div>
        <h1 style="margin:6px 0 0;font-size:18px;font-weight:700;color:#FFFFFF;">{$safeHeadline}</h1>
      </td>
    </tr>
    <tr>
      <td style="padding:24px;font-size:14px;line-height:1.6;">
        <p style="margin:0 0 12px;">Hello {$safeName},</p>
        <p style="margin:0 0 14px;">{$bodyHtml}</p>
        <div style="background:#F8FAFC;border-left:4px solid #2563EB;padding:12px 14px;font-size:13px;color:#334155;">
          {$advisoryHtml}
        </div>
      </td>
    </tr>
    <tr>
      <td style="padding:14px 24px;background:#F8FAFC;border-top:1px solid #E2E8F0;font-size:12px;color:#64748B;">
        Automated security notice from {$appName} &copy; {$year}. Never reply with passwords or codes.
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        try {
            $this->mailer->queueHtmlEmail($email, $subject, $html, $name);
        } catch (Throwable $e) {
            error_log('[AuthSecurityNotification] Security email queue failed: ' . get_class($e));
        }
    }
}
