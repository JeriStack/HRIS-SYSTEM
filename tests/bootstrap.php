<?php
/**
 * PHPUnit Test Suite Bootstrap
 * Employee HRIS & Leave Management System
 */

// Set testing environment
$_ENV['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');

// Safety guard: automated tests must only ever run against the isolated test database.
// Override with HRIS_TEST_DATABASE only for another dedicated, disposable test schema.
$allowedTestDatabase = getenv('HRIS_TEST_DATABASE') ?: 'hris_test';
$configuredDatabase = getenv('DB_DATABASE') ?: '';
if ($configuredDatabase !== $allowedTestDatabase) {
    fwrite(STDERR, "Refusing to run tests: DB_DATABASE is '{$configuredDatabase}', expected isolated test database '{$allowedTestDatabase}'.\n");
    exit(1);
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load application bootstrap
require_once __DIR__ . '/../core/bootstrap.php';
