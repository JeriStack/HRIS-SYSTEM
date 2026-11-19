<?php
/**
 * CLI Command: SMTP Connection & Delivery Diagnostics
 * 
 * Usage:
 *   php bin/test_smtp.php
 *   php bin/test_smtp.php recipient@domain.com
 * 
 * Security: Restricted strictly to CLI execution. Passwords and sensitive secrets are masked.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This diagnostic tool must only be run via the CLI environment.\n";
    exit(1);
}

// 1. Bootstrap Core Application
require_once __DIR__ . '/../core/bootstrap.php';

use App\Services\MailService;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

if (in_array('--help', $argv) || in_array('-h', $argv)) {
    echo "====================================================================\n";
    echo " Employee HRIS & Leave Management System - SMTP Diagnostics Help\n";
    echo "====================================================================\n\n";
    echo "Usage:\n";
    echo "  php bin/test_smtp.php                 # Run configuration & network test\n";
    echo "  php bin/test_smtp.php <email>         # Run test and send real OTP email\n";
    echo "  php bin/test_smtp.php --help          # Show this help screen\n\n";
    exit(0);
}

echo "====================================================================\n";
echo " Employee HRIS & Leave Management System - SMTP Diagnostics\n";
echo "====================================================================\n\n";

$mailConfig = config('mail', []);

$host       = $mailConfig['host'] ?? 'smtp.gmail.com';
$port       = (int)($mailConfig['port'] ?? 587);
$encryption = strtolower((string)($mailConfig['encryption'] ?? 'tls'));
$username   = $mailConfig['username'] ?? '';
$password   = $mailConfig['password'] ?? '';
$fromEmail  = $mailConfig['from']['address'] ?? '';
$fromName   = $mailConfig['from']['name'] ?? 'HRIS';

// Masked display
$maskedPass = $password !== '' ? str_repeat('*', min(8, strlen($password))) : '(NOT CONFIGURED)';
$maskedUser = $username !== '' ? $username : '(NOT CONFIGURED)';

echo "[1] Configuration Inspection:\n";
echo "    - SMTP Host:       {$host}\n";
echo "    - SMTP Port:       {$port}\n";
echo "    - Encryption:      {$encryption}\n";
echo "    - SMTP Username:   {$maskedUser}\n";
echo "    - SMTP Password:   {$maskedPass}\n";
echo "    - From Address:    {$fromEmail}\n";
echo "    - From Name:       {$fromName}\n\n";

// Check if credentials are configured and whether unauthenticated local relay (e.g., Mailpit) is in use
$hasUsername    = ($username !== '');
$hasCredentials = ($hasUsername && $password !== '');
$isUnauthRelay  = (!$hasUsername && $encryption === 'none');

if (!$hasCredentials && !$isUnauthRelay) {
    echo "⚠️  [WARNING] SMTP credentials are not fully configured in your .env file!\n";
    echo "    To enable real email OTP delivery:\n";
    echo "    1. Copy .env.example to .env (if not already done)\n";
    echo "    2. Set SMTP_USERNAME to your Gmail address\n";
    echo "    3. Set SMTP_PASSWORD to a Google App Password (16 characters)\n";
    echo "       (Generate one at: https://myaccount.google.com/apppasswords)\n";
    echo "    4. Set MAIL_FROM_ADDRESS to your sender email\n\n";
} elseif ($isUnauthRelay) {
    echo "ℹ️  [INFO] Unauthenticated SMTP relay mode detected (encryption=none, e.g., Mailpit).\n\n";
}

// Step 2: Test TCP Socket Reachability
echo "[2] Network Connectivity Test:\n";
echo "    Connecting to {$host}:{$port}... ";

$connectionTimeout = 5; // seconds
$socket = @fsockopen($host, $port, $errno, $errstr, $connectionTimeout);

if (!$socket) {
    echo "FAILED ❌\n";
    echo "    [ERROR {$errno}] {$errstr}\n";
    echo "    Could not establish a TCP connection to {$host}:{$port}.\n";
    echo "    Check firewall rules, proxy settings, or internet access.\n\n";
    exit(1);
} else {
    echo "SUCCESS ✅ (Connected in <{$connectionTimeout}s)\n";
    // Read SMTP banner greeting
    $banner = fgets($socket, 512);
    echo "    Server Banner: " . trim($banner) . "\n";
    fclose($socket);
}

echo "\n";

// Step 3: Test Real Email Delivery if Recipient Provided
$recipient = $argv[1] ?? null;

if (!$recipient) {
    echo "[3] Email Dispatch Test:\n";
    echo "    No recipient specified. To test actual email delivery, pass a recipient email address:\n";
    echo "    php bin/test_smtp.php your-email@example.com\n\n";
    echo "====================================================================\n";
    echo " Diagnostics Completed.\n";
    echo "====================================================================\n";
    exit(0);
}

if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    echo "[3] Email Dispatch Test:\n";
    echo "    ❌ Invalid test recipient email format: '{$recipient}'\n\n";
    exit(1);
}

echo "[3] Dispatching Live OTP Test Email to: {$recipient}...\n";

if (!$hasCredentials && !$isUnauthRelay) {
    echo "    ❌ Cannot dispatch email: SMTP_USERNAME or SMTP_PASSWORD is empty in .env.\n";
    echo "    Please configure real SMTP credentials (or set SMTP_ENCRYPTION=none for Mailpit) before attempting live delivery.\n\n";
    exit(1);
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = $host;
    $mail->SMTPAuth   = $hasUsername;
    $mail->Username   = $username;
    $mail->Password   = str_contains(strtolower($host), 'gmail') ? str_replace(' ', '', $password) : $password;
    $mail->Port       = $port;
    $mail->Timeout    = 15;
    $mail->CharSet    = PHPMailer::CHARSET_UTF8;

    if ($encryption === 'ssl' || $port === 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption === 'tls' || $port === 587) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure  = '';
        $mail->SMTPAutoTLS = false;
    }

    $resolvedFrom = $fromEmail !== '' ? $fromEmail : ($username !== '' ? $username : 'no-reply@hris-system.local');
    $mail->setFrom($resolvedFrom, $fromName);
    $mail->addAddress($recipient);
    $mail->isHTML(true);

    $testOtp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $mail->Subject = "HRIS Diagnostic Test - Verification Code: {$testOtp}";
    $mail->Body    = "<h3>HRIS Diagnostic Email Delivery</h3><p>This is a test OTP verification code: <strong>{$testOtp}</strong></p><p>Sent from HRIS SMTP Diagnostic Utility at " . date('Y-m-d H:i:s') . ".</p>";
    $mail->AltBody = "HRIS Diagnostic Email Delivery\nYour test verification code is: {$testOtp}\nSent at: " . date('Y-m-d H:i:s');

    $mail->send();

    echo "    SUCCESS! ✅ Email was successfully accepted by {$host} and dispatched to {$recipient}.\n";
    echo "    Test OTP dispatched: {$testOtp}\n\n";
    echo "====================================================================\n";
    echo " Live Delivery Verified Successfully!\n";
    echo "====================================================================\n";
    exit(0);
} catch (MailerException $e) {
    $cleanMsg = preg_replace('/password=[^\s]+/i', 'password=***', $e->getMessage());
    echo "    FAILED ❌\n";
    echo "    Mailer Error: {$cleanMsg}\n\n";
    if (str_contains($cleanMsg, 'Username and Password not accepted') || str_contains($cleanMsg, '535')) {
        echo "    💡 Tip: Gmail requires an App Password when 2-Step Verification is active.\n";
        echo "       Generate a 16-character App Password at: https://myaccount.google.com/apppasswords\n";
    }
    exit(1);
} catch (\Throwable $e) {
    echo "    FAILED ❌\n";
    echo "    Unexpected Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
