<?php
/**
 * CLI Command: Provision Initial or Additional HR Account
 * 
 * Usage:
 *   php bin/create_hr_user.php
 *   php bin/create_hr_user.php --email="hr.lead@company.local" --first-name="Jane" --last-name="Doe" --password="SuperSecurePassword123"
 * 
 * Security: Strictly restricted to CLI execution. Web access is permanently forbidden.
 * Strict Role Policy: Only provisions 'hr' role accounts. No Admin role exists in the system.
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
use App\Models\Department;
use App\Models\Position;

if (in_array('--help', $argv) || in_array('-h', $argv)) {
    echo "====================================================================\n";
    echo " Employee HRIS & Leave Management System - HR Provisioning Help\n";
    echo "====================================================================\n\n";
    echo "Usage:\n";
    echo "  php bin/create_hr_user.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --email=<email>             HR staff email address (required)\n";
    echo "  --first-name=<first_name>   First name (optional if linked employee exists)\n";
    echo "  --last-name=<last_name>     Last name (optional if linked employee exists)\n";
    echo "  --password=<password>       Initial password (minimum 8 chars)\n";
    echo "  --employee-no=<emp_no>      Employee number (optional)\n";
    echo "  --help, -h                  Display this help message\n\n";
    echo "Interactive Mode:\n";
    echo "  Run without arguments to be prompted interactively for all required fields.\n\n";
    exit(0);
}

echo "====================================================================\n";
echo " Employee HRIS & Leave Management System - HR Account Provisioning\n";
echo "====================================================================\n\n";

// 2. Parse CLI Arguments
$options = getopt('', [
    'email:',
    'first-name:',
    'last-name:',
    'password:',
    'employee-no:',
]);

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

$email = strtolower(trim((string)($options['email'] ?? '')));
if ($email === '') {
    $email = strtolower(trim($prompt('Enter HR Staff Email Address')));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "\n[ERROR] Invalid email address format: '{$email}'. Aborting.\n";
    exit(1);
}

$userModel = new User();
if ($userModel->emailExists($email)) {
    echo "\n[ERROR] An account with email '{$email}' already exists. Aborting.\n";
    exit(1);
}

$firstName = trim((string)($options['first-name'] ?? ''));
if ($firstName === '') {
    $firstName = trim($prompt('Enter HR Staff First Name'));
}
if ($firstName === '') {
    echo "\n[ERROR] First name cannot be empty. Aborting.\n";
    exit(1);
}

$lastName = trim((string)($options['last-name'] ?? ''));
if ($lastName === '') {
    $lastName = trim($prompt('Enter HR Staff Last Name'));
}
if ($lastName === '') {
    echo "\n[ERROR] Last name cannot be empty. Aborting.\n";
    exit(1);
}

$password = (string)($options['password'] ?? '');
if ($password === '') {
    $password = $prompt('Enter Initial Password (min 8 chars)', true);
}

if (strlen($password) < 8) {
    echo "\n[ERROR] Password must be at least 8 characters long. Aborting.\n";
    exit(1);
}

// 3. Resolve or Create Employee Record
$employeeModel = new Employee();
$employeeNo = trim((string)($options['employee-no'] ?? ''));
if ($employeeNo === '') {
    $employeeNo = 'HR' . date('Y') . str_pad((string)random_int(100, 999), 3, '0', STR_PAD_LEFT);
}

// Check if an employee record with this email already exists
$existingEmp = $employeeModel->findByEmail($email);
$employeeId = null;

if ($existingEmp) {
    $employeeId = (int)$existingEmp['id'];
    echo "[INFO] Linking to existing employee record #{$employeeId} ({$existingEmp['employee_no']}).\n";
} else {
    // Look up HR Department (ID 2 default) and HR Officer Position (ID 3 default)
    $deptModel = new Department();
    $depts = $deptModel->getAll();
    $deptId = 2;
    foreach ($depts as $d) {
        if (stripos($d['department_name'], 'Human Resources') !== false) {
            $deptId = (int)$d['id'];
            break;
        }
    }

    $posModel = new Position();
    $positions = $posModel->getAll();
    $posId = 3;
    foreach ($positions as $p) {
        if (stripos($p['position_name'], 'HR') !== false) {
            $posId = (int)$p['id'];
            break;
        }
    }

    $employeeId = $employeeModel->create([
        'employee_no'       => $employeeNo,
        'first_name'        => $firstName,
        'middle_name'       => '',
        'last_name'         => $lastName,
        'email'             => $email,
        'phone'             => '+63 900 000 0000',
        'address'           => 'Corporate Headquarters',
        'birth_date'        => '1990-01-01',
        'gender'            => 'Other',
        'department_id'     => $deptId,
        'position_id'       => $posId,
        'date_hired'        => date('Y-m-d'),
        'employment_status' => 'Active',
    ]);
    echo "[INFO] Created new employee record #{$employeeId} ({$employeeNo}).\n";
}

// 4. Create User Record (Strict role: 'hr')
$username = strtolower(explode('@', $email)[0]);
$newUserId = $userModel->create([
    'employee_id' => $employeeId,
    'username'    => $username,
    'email'       => $email,
    'password'    => $password, // User::create() securely hashes using PASSWORD_BCRYPT
    'role'        => 'hr',
    'status'      => 'Active',
]);

// 5. Audit Logging
$userModel->logActivity(
    $newUserId,
    'HR_ACCOUNT_PROVISIONED_CLI',
    "HR account provisioned via CLI for {$firstName} {$lastName} ({$email})",
    'users',
    (string)$newUserId
);

echo "\n====================================================================\n";
echo "[SUCCESS] HR Account successfully provisioned!\n";
echo "  User ID:    #{$newUserId}\n";
echo "  Name:       {$firstName} {$lastName}\n";
echo "  Email:      {$email}\n";
echo "  Role:       hr (HR Officer)\n";
echo "  Status:     Active\n";
echo "====================================================================\n";
echo "Sign in at: " . (getenv('BASE_URL') ?: 'http://localhost:8080') . "/login\n";
echo "Enter your registered email ({$email}) and password to trigger Email OTP verification.\n\n";
exit(0);
