<?php
/**
 * Application Configuration
 * Employee HRIS & Leave Management System
 */

return [
    'app_name'         => getenv('APP_NAME') ?: 'Employee HRIS & Leave Management System',
    'app_env'          => getenv('APP_ENV') ?: 'development', // 'development' or 'production'
    'base_url'         => getenv('BASE_URL') ?: 'http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/public',
    'timezone'         => getenv('TIMEZONE') ?: 'Asia/Manila',
    'session_lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 1800), // 30 minutes
    'csrf_token_name'  => '_csrf_token',
    'dsa_benchmark'    => [
        'dataset_sizes' => [10, 100, 500, 1000, 5000, 10000],
        'default_size'  => 100
    ]
];
