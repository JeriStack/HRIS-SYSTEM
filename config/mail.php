<?php
/**
 * Email & SMTP Configuration
 * Employee HRIS & Leave Management System
 */

return [
    'driver'     => getenv('MAIL_DRIVER') ?: 'smtp',
    'host'       => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'port'       => (int)(getenv('SMTP_PORT') ?: 587),
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls', // 'tls', 'ssl', or 'none'
    'username'   => getenv('SMTP_USERNAME') ?: '',
    'password'   => getenv('SMTP_PASSWORD') ?: '',
    'from'       => [
        'address' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@nexora-hr.local',
        'name'    => getenv('MAIL_FROM_NAME') ?: 'NEXORA HR',
    ],
    'timeout'    => (int)(getenv('MAIL_TIMEOUT') ?: 15),
    'debug'      => (int)(getenv('MAIL_DEBUG') ?: 0), // 0 = off, 1 = client, 2 = client and server
];
