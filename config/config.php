<?php
/**
 * Application Configuration
 * Employee HRIS & Leave Management System
 */

return [
    'app_name'         => 'Employee HRIS & Leave Management System',
    'app_env'          => 'development', // 'development' or 'production'
    'base_url'         => 'http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/public',
    'timezone'         => 'Asia/Manila',
    'session_lifetime' => 1800, // 30 minutes in seconds
    'csrf_token_name'  => '_csrf_token',
    'dsa_benchmark'    => [
        'dataset_sizes' => [10, 100, 500, 1000, 5000, 10000],
        'default_size'  => 100
    ]
];
