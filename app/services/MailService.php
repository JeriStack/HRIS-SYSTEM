<?php
/**
 * Reusable Mail Service (PHPMailer & SMTP Integration)
 * Handles secure email OTP delivery with branded responsive templates
 * Employee HRIS & Leave Management System
 */

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;
use Throwable;

class MailService
{
    private array $config;
    private ?string $lastError = null;
    private bool $mockMode = false;
    private array $sentMails = []; // Recorded in mock mode for test assertions

    /**
     * @param array|null $config Optional custom configuration override
     * @param bool $mockMode Enable mock mode (useful for testing without real SMTP)
     */
    public function __construct(?array $config = null, bool $mockMode = false)
    {
        $this->config = $config ?? config('mail', []);
        $this->mockMode = $mockMode || (getenv('APP_ENV') === 'testing' && empty($this->config['username']));
    }

    /**
     * Enable or disable mock mode.
     */
    public function setMockMode(bool $mockMode): self
    {
        $this->mockMode = $mockMode;
        return $this;
    }

    /**
     * Check if currently in mock mode.
     */
    public function isMockMode(): bool
    {
        return $this->mockMode;
    }

    /**
     * Retrieve last recorded error message.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Retrieve sent emails captured in mock mode.
     */
    public function getSentMails(): array
    {
        return $this->sentMails;
    }

    /**
     * Clear recorded mock emails.
     */
    public function clearSentMails(): void
    {
        $this->sentMails = [];
    }

    /**
     * Create and configure a fresh PHPMailer instance.
     */
    protected function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);

        $driver = $this->config['driver'] ?? 'smtp';

        if ($driver === 'smtp') {
            $mail->isSMTP();
            $mail->Host       = $this->config['host'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = !empty($this->config['username']);
            $mail->Username   = $this->config['username'] ?? '';
            $rawPass          = $this->config['password'] ?? '';
            $mail->Password   = (str_contains(strtolower($this->config['host'] ?? ''), 'gmail')) 
                                ? str_replace(' ', '', $rawPass) 
                                : $rawPass;
            $mail->Port       = (int)($this->config['port'] ?? 587);
            $mail->Timeout    = (int)($this->config['timeout'] ?? 15);

            $encryption = strtolower((string)($this->config['encryption'] ?? 'tls'));
            if ($encryption === 'ssl' || $this->config['port'] == 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls' || $this->config['port'] == 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->SMTPDebug = (int)($this->config['debug'] ?? 0);
            if ($mail->SMTPDebug > 0) {
                $mail->Debugoutput = function ($str, $level) {
                    error_log("[PHPMailer Debug Level {$level}] " . trim($str));
                };
            }
        }

        $mail->CharSet = PHPMailer::CHARSET_UTF8;

        $fromAddress = $this->config['from']['address'] ?? 'no-reply@hris-system.local';
        $fromName    = $this->config['from']['name'] ?? 'NEXORA HR';
        $mail->setFrom($fromAddress, $fromName);

        return $mail;
    }

    /**
     * Send a formatted Email OTP verification message.
     *
     * @param string $recipientEmail Validated email address from database
     * @param string $recipientName Full name or username of recipient
     * @param string $otp 6-digit numeric OTP code
     * @param int $lifetimeMinutes Expiration window in minutes (default 5)
     * @return bool True if successfully dispatched, false otherwise
     */
    public function sendOtpEmail(
        string $recipientEmail,
        string $recipientName,
        string $otp,
        int $lifetimeMinutes = 5
    ): bool {
        $subject = "Your Sign-In Verification Code: {$otp}";
        $htmlBody = $this->buildOtpHtmlTemplate($recipientName, $otp, $lifetimeMinutes);
        $altBody = $this->buildOtpPlainTextTemplate($recipientName, $otp, $lifetimeMinutes);

        return $this->sendMail($recipientEmail, $recipientName, $subject, $htmlBody, $altBody);
    }

    /**
     * Dispatch an email to a single recipient.
     *
     * @param string $toAddress
     * @param string $toName
     * @param string $subject
     * @param string $htmlBody
     * @param string|null $altBody
     * @return bool
     */
    public function sendMail(
        string $toAddress,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $altBody = null
    ): bool {
        $this->lastError = null;

        // Mock mode for local testing without valid SMTP credentials
        if ($this->mockMode) {
            $this->sentMails[] = [
                'to_address' => $toAddress,
                'to_name'    => $toName,
                'subject'    => $subject,
                'html_body'  => $htmlBody,
                'alt_body'   => $altBody,
                'time'       => time(),
            ];
            return true;
        }

        try {
            $mail = $this->createMailer();
            $mail->addAddress($toAddress, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $altBody ?? strip_tags($htmlBody);

            $logoPath = __DIR__ . '/../../public/assets/img/hris-logo.png';
            if (file_exists($logoPath)) {
                $mail->addEmbeddedImage($logoPath, 'hris_logo', 'hris-logo.png', 'base64', 'image/png');
            }

            $mail->send();
            return true;
        } catch (MailerException $e) {
            // Mask any credentials in error messages for security
            $cleanError = preg_replace('/password=[^\s]+/i', 'password=***', $e->getMessage());
            $this->lastError = $cleanError;
            error_log('[MailService MailerException] Failed to send email to ' . mask_email($toAddress) . ": {$cleanError}");
            return false;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService Exception] Unexpected error sending email to ' . mask_email($toAddress) . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Build responsive branded HTML template for OTP email.
     */
    private function buildOtpHtmlTemplate(string $name, string $otp, int $minutes): string
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $appName = htmlspecialchars((string)config('config.app_name', 'NEXORA HR'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign-In Verification Code</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #EEF2F6;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1E293B;
            line-height: 1.6;
        }
        .text-justify {
            text-align: justify !important;
            text-justify: inter-word !important;
        }
        @media only screen and (max-width: 600px) {
            .container-table {
                width: 100% !important;
                border-radius: 8px !important;
            }
            .content-td {
                padding: 24px 20px 18px !important;
            }
            .otp-code {
                font-size: 32px !important;
                letter-spacing: 6px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #EEF2F6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #EEF2F6; margin: 0; padding: 36px 14px 48px; width: 100%;">
        <tr>
            <td align="center" style="padding: 0;">
                <table role="presentation" class="container-table" cellpadding="0" cellspacing="0" border="0" style="max-width: 540px; width: 100%; background-color: #FFFFFF; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); border: 1px solid #E2E8F0; text-align: left;">
                    <!-- Logo Header -->
                    <tr>
                        <td align="center" style="padding: 26px 20px 20px; text-align: center; background-color: #FFFFFF;">
                            <img src="cid:hris_logo" alt="HRIS Logo" width="210" style="max-width: 210px; width: 100%; height: auto; display: block; margin: 0 auto; border: 0;">
                        </td>
                    </tr>
                    <!-- Navy / Gradient Banner Bar -->
                    <tr>
                        <td align="center" style="background-color: #0F2E5C; background: linear-gradient(90deg, #0F2E5C 0%, #1E5E99 100%); padding: 13px 24px; text-align: center; color: #FFFFFF; font-size: 15px; font-weight: 700; letter-spacing: 0.01em; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            {$appName}
                        </td>
                    </tr>
                    <!-- Body Content -->
                    <tr>
                        <td class="content-td" style="padding: 28px 36px 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <div style="font-size: 18px; font-weight: 700; color: #0F172A; margin-bottom: 12px;">Hello, {$safeName}</div>
                            <p class="text-justify" style="font-size: 14px; color: #334155; margin: 0 0 20px 0; line-height: 1.6; text-align: justify; text-justify: inter-word;">
                                We received a sign-in request for your HRIS account. Please use the following One-Time Password (OTP) to complete your sign-in:
                            </p>
                            
                            <!-- Dashed OTP Box -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #F0F7FF; border: 1.5px dashed #60A5FA; border-radius: 12px; margin: 20px 0 24px 0;">
                                <tr>
                                    <td align="center" style="padding: 22px 20px 18px; text-align: center;">
                                        <div class="otp-code" style="font-size: 38px; font-weight: 800; letter-spacing: 8px; color: #0F2E5C; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; margin-bottom: 6px;">{$otp}</div>
                                        <div style="font-size: 13px; color: #64748B; font-weight: 500;">⏱ Valid for {$minutes} minutes • Single-use only</div>
                                    </td>
                                </tr>
                            </table>

                            <!-- Security Advisory -->
                            <div class="text-justify" style="background-color: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 6px; padding: 12px 16px; margin: 0 0 20px 0; font-size: 13px; color: #92400E; line-height: 1.5; text-align: justify; text-justify: inter-word;">
                                🔒 <strong>Security Advisory:</strong> Never share this code with anyone. HRIS administrators will never ask for your verification code.
                            </div>

                            <p style="font-size: 13px; color: #64748B; margin: 0 0 10px 0; line-height: 1.5;">
                                If you did not initiate this login request, please disregard this email or report it to your HR department immediately.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color: #EDF2F7; padding: 24px 32px; text-align: center; border-top: 1px solid #E2E8F0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <div style="font-size: 11.5px; color: #64748B; line-height: 1.6;">
                                &copy; {$year} {$appName}. All rights reserved.<br>
                                This is an automated system notification. Please do not reply directly to this email.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Dispatch an HTML email to the background queue for asynchronous delivery.
     */
    public function queueHtmlEmail(string $to, string $subject, string $htmlBody, string $recipientName = '', int $delaySeconds = 0): int
    {
        return \App\Queue\QueueService::getInstance()->push(
            \App\Queue\JobRegistry::TYPE_SEND_EMAIL,
            [
                'to'      => $to,
                'subject' => $subject,
                'body'    => $htmlBody,
                'name'    => $recipientName,
            ],
            'default',
            $delaySeconds
        );
    }

    /**
     * Build plain-text alternative body for OTP email.
     */
    private function buildOtpPlainTextTemplate(string $name, string $otp, int $minutes): string
    {
        $appName = (string)config('config.app_name', 'NEXORA HR');

        return <<<TEXT
{$appName}
Sign-In Verification Code

Hello, {$name}

We received a sign-in request for your HRIS account.
Your One-Time Password (OTP) is:

    {$otp}

This code is valid for {$minutes} minutes and can only be used once.

SECURITY ADVISORY:
Never share this verification code with anyone. HRIS administrators will never ask for your code.

If you did not initiate this sign-in attempt, please disregard this message or notify HR immediately.

--
{$appName}
Automated Security Notification (Do not reply)
TEXT;
    }
}
