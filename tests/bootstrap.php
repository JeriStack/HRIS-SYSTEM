<?php
/**
 * PHPUnit Test Suite Bootstrap
 * Employee HRIS & Leave Management System
 */

// Set testing environment
$_ENV['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load application bootstrap
require_once __DIR__ . '/../core/bootstrap.php';
