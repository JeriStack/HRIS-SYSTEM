<?php

/**
 * CLI Command: Controlled Initial & Additional Administrator Account Provisioning
 * Employee HRIS & Leave Management System - NEXORA HR
 * 
 * Usage:
 *   php bin/create_admin_user.php
 *   php bin/create_admin_user.php --email="admin@company.local" --username="admin" --password="SuperSecurePassword123"
 * 
 * Security Guardrails:
 * - Strictly restricted to CLI execution (Web execution permanently forbidden).
 * - Guards against uncontrolled duplicate Admin provisioning (requires --force if an active Admin already exists).
 * - Passwords securely hashed with Bcrypt (cost 12). Never outputs credentials to logs.
 * - Full audit activity trail recorded on account creation.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden: This administrative script must only be run via the CLI environment.\n";
    exit(1);
}

// 1. Bootstrap Core Application
require_once __DIR__ . '/../core/bootstrap.php';

use App\Models\User;
use App\Models\Employee;
use Core\Database;

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    echo "====================================================================\n";
    echo " NEXORA HR — Controlled Administrator Account Provisioning (CLI)\n";
    echo "====================================================================\n\n";
    echo "Usage:\n";
    echo "  php bin/create_admin_user.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --email=<email>             Administrator email address (required)\n";
    echo "  --username=<username>       Administrator username (optional, defaults to email local-part)\n";
    echo "  --first-name=<first_name>   First name (optional)\n";
    echo "  --last-name=<last_name>     Last name (optional)\n";
    echo "  --password=<password>       Initial password (minimum 8 characters)\n";
    echo "  --force                     Force creation when an active Administrator already exists\n";
    echo "  --help, -h                  Display this help message\n\n";
    echo "Interactive Mode:\n";
    echo "  Run without arguments to be prompted interactively for required fields.\n\n";
    exit(0);
}

echo "====================================================================\n";
echo " NEXORA HR — Administrator Account Provisioning\n";
echo "====================================================================\n\n";

// 2. Parse CLI Arguments
$options = getopt('', [
    'email:',
    'username:',
    'first-name:',
    'last-name:',
    'password:',
    'force',
]);

$userModel = new User();
$force = isset($options['force']) || in_array('--force', $argv, true);

// 3. Setup Guard: Check if an active Administrator already exists
$activeAdmins = $userModel->countActiveAdmins();
if ($activeAdmins > 0 && !$force) {
    echo "[GUARD NOTICE] System already has {$activeAdmins} active Administrator account(s).\n";
    echo "To provision an additional Administrator, execute with the '--force' flag:\n";
    echo "  php bin/create_admin_user.php --force\n\n";
    exit(1);
}

$prompt = function (string $label, bool $hidden = false): string {
    echo $label . ': ';
    if ($hidden && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty -echo');
        $input = trim((string)fgets(STDIN));
        system('stty echo');
        echo "\n";
        return $input;
    }
    return trim((string)fgets(STDIN));
};

// 4. Validate Email Address
$email = strtolower(trim((string)($options['email'] ?? '')));
if ($email === '') {
    $email = strtolower(trim($prompt('Enter Administrator Email Address')));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "\n[ERROR] Invalid email address format: '{$email}'. Aborting.\n";
    exit(1);
}

if ($userModel->emailExists($email)) {
    echo "\n[ERROR] An account with email '{$email}' already exists. Aborting.\n";
    exit(1);
}

// 5. Validate Username
$username = strtolower(trim((string)($options['username'] ?? '')));
if ($username === '') {
    $defaultUser = explode('@', $email)[0];
    $enteredUser = trim($prompt("Enter Administrator Username [default: {$defaultUser}]"));
    $username = $enteredUser !== '' ? strtolower($enteredUser) : $defaultUser;
}

if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
    echo "\n[ERROR] Username must be 3-50 characters containing letters, numbers, dots, underscores, or dashes.\n";
    exit(1);
}

if ($userModel->findByUsername($username)) {
    echo "\n[ERROR] Username '{$username}' is already in use. Aborting.\n";
    exit(1);
}

// 6. Name resolution
$firstName = trim((string)($options['first-name'] ?? ''));
if ($firstName === '') {
    $firstName = trim($prompt('Enter Administrator First Name [default: System]'));
    if ($firstName === '') {
        $firstName = 'System';
    }
}

$lastName = trim((string)($options['last-name'] ?? ''));
if ($lastName === '') {
    $lastName = trim($prompt('Enter Administrator Last Name [default: Administrator]'));
    if ($lastName === '') {
        $lastName = 'Administrator';
    }
}

// 7. Validate Password
$password = (string)($options['password'] ?? '');
if ($password === '') {
    $password = $prompt('Enter Initial Password (min 8 chars)', true);
}

if (strlen($password) < 8) {
    echo "\n[ERROR] Password must be at least 8 characters long. Aborting.\n";
    exit(1);
}

// 8. Provision within Database Transaction
$pdo = Database::getInstance()->getConnection();
try {
    $pdo->beginTransaction();

    $newUserId = $userModel->create([
        'employee_id' => null,
        'username'    => $username,
        'email'       => $email,
        'password'    => $password, // User::create() securely hashes using PASSWORD_BCRYPT
        'role'        => 'admin',
        'status'      => 'Active',
    ]);

    // Record audit event
    $userModel->logActivity(
        $newUserId,
        'ADMIN_BOOTSTRAPPED_CLI',
        "Administrator account provisioned via CLI for {$firstName} {$lastName} ({$email}) with username '{$username}'",
        'users',
        (string)$newUserId
    );

    $pdo->commit();

    echo "\n====================================================================\n";
    echo "[SUCCESS] Administrator Account successfully provisioned!\n";
    echo "  User ID:    #{$newUserId}\n";
    echo "  Username:   {$username}\n";
    echo "  Name:       {$firstName} {$lastName}\n";
    echo "  Email:      {$email}\n";
    echo "  Role:       admin (System Administrator)\n";
    echo "  Status:     Active\n";
    echo "  MFA Policy: Mandatory 6-digit Email OTP required on sign-in\n";
    echo "====================================================================\n";
    echo "Sign in at: " . (getenv('BASE_URL') ?: 'http://localhost:8080') . "/login\n";
    echo "Enter your registered email ({$email}) to initiate multi-factor authentication.\n\n";
    exit(0);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n[ERROR] Failed to provision Administrator account: " . $e->getMessage() . "\n";
    exit(1);
}
