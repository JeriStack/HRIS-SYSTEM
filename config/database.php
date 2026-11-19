<?php
/**
 * Database Configuration
 * Employee HRIS & Leave Management System
 */

return [
    'driver'    => getenv('DB_DRIVER') ?: 'mysql',
    'host'      => getenv('DB_HOST') ?: '127.0.0.1',
    'port'      => (int)(getenv('DB_PORT') ?: 3306),
    'database'  => getenv('DB_DATABASE') ?: 'hris_db',
    'username'  => getenv('DB_USERNAME') ?: 'root',
    'password'  => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
    'charset'   => getenv('DB_CHARSET') ?: 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => (int)(getenv('DB_CONNECT_TIMEOUT') ?: 5),
    ]
];
