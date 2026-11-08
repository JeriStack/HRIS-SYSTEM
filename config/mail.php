<?php
/**
 * Email & SMTP Configuration
 * Employee HRIS & Leave Management System
 */

$host = getenv('SMTP_HOST') ?: '127.0.0.1';
// Automatically resolve Docker container network hostname for Mailpit
if (($host === '127.0.0.1' || $host === 'localhost') && (getenv('DB_HOST') === 'db' || file_exists('/.dockerenv'))) {
    $host = 'mailpit';
}

$port = (int)(getenv('SMTP_PORT') ?: 1025);
$encryption = getenv('SMTP_ENCRYPTION') !== false ? getenv('SMTP_ENCRYPTION') : 'none';

return [
    'driver'     => getenv('MAIL_DRIVER') ?: 'smtp',
    'host'       => $host,
    'port'       => $port,
    'encryption' => $encryption, // 'tls', 'ssl', or 'none'
    'username'   => getenv('SMTP_USERNAME') ?: '',
    'password'   => getenv('SMTP_PASSWORD') ?: '',
    'from'       => [
        'address' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@nexora.local',
        'name'    => getenv('MAIL_FROM_NAME') ?: 'NEXORA HR',
    ],
    'timeout'    => (int)(getenv('MAIL_TIMEOUT') ?: 10),
    'debug'      => (int)(getenv('MAIL_DEBUG') ?: 0), // 0 = off, 1 = client, 2 = client and server
];
