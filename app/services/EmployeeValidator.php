<?php
/**
 * Employee Data Validation Service
 * Validates employee profile attributes according to HRIS business logic
 * Employee HRIS & Leave Management System
 */

namespace App\Services;

use DateTime;

class EmployeeValidator
{
    /**
     * Validate an employee data array.
     *
     * @param array $data Input payload
     * @param callable|null $uniqueEmpNoCheck Optional callback: fn(string $empNo): bool
     * @param callable|null $uniqueEmailCheck Optional callback: fn(string $email): bool
     * @return array<string, string> Array of validation error messages keyed by field
     */
    public static function validate(
        array $data, 
        ?callable $uniqueEmpNoCheck = null, 
        ?callable $uniqueEmailCheck = null
    ): array {
        $errors = [];

        $empNo      = trim((string)($data['employee_no'] ?? ''));
        $firstName  = trim((string)($data['first_name'] ?? ''));
        $middleName = trim((string)($data['middle_name'] ?? ''));
        $lastName   = trim((string)($data['last_name'] ?? ''));
        $email      = trim((string)($data['email'] ?? ''));
        $phone      = trim((string)($data['phone'] ?? ''));
        $birthDate  = trim((string)($data['birth_date'] ?? ''));
        $gender     = trim((string)($data['gender'] ?? ''));
        $deptId     = (int)($data['department_id'] ?? 0);
        $posId      = (int)($data['position_id'] ?? 0);
        $dateHired  = trim((string)($data['date_hired'] ?? ''));
        $status     = trim((string)($data['employment_status'] ?? 'Active'));

        // 1. Employee Number
        if ($empNo === '') {
            $errors['employee_no'] = 'Employee number is required.';
        } elseif (mb_strlen($empNo) > 30) {
            $errors['employee_no'] = 'Employee number cannot exceed 30 characters.';
        } elseif ($uniqueEmpNoCheck !== null && !$uniqueEmpNoCheck($empNo)) {
            $errors['employee_no'] = "Employee number '{$empNo}' is already assigned to another employee.";
        }

        // 2. Names
        if ($firstName === '') {
            $errors['first_name'] = 'First name is required.';
        } elseif (mb_strlen($firstName) > 50) {
            $errors['first_name'] = 'First name cannot exceed 50 characters.';
        }

        if ($middleName !== '' && mb_strlen($middleName) > 50) {
            $errors['middle_name'] = 'Middle name cannot exceed 50 characters.';
        }

        if ($lastName === '') {
            $errors['last_name'] = 'Last name is required.';
        } elseif (mb_strlen($lastName) > 50) {
            $errors['last_name'] = 'Last name cannot exceed 50 characters.';
        }

        // 3. Email
        if ($email === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 100) {
            $errors['email'] = 'Email cannot exceed 100 characters.';
        } elseif ($uniqueEmailCheck !== null && !$uniqueEmailCheck($email)) {
            $errors['email'] = "Email address '{$email}' is already registered to another employee.";
        }

        // 4. Phone
        if ($phone !== '' && mb_strlen($phone) > 25) {
            $errors['phone'] = 'Phone number cannot exceed 25 characters.';
        }

        // 5. Birth Date
        if ($birthDate === '') {
            $errors['birth_date'] = 'Birth date is required.';
        } else {
            $d = DateTime::createFromFormat('Y-m-d', $birthDate);
            if (!$d || $d->format('Y-m-d') !== $birthDate) {
                $errors['birth_date'] = 'Invalid date format (must be YYYY-MM-DD).';
            } elseif ($d > new DateTime()) {
                $errors['birth_date'] = 'Birth date cannot be in the future.';
            }
        }

        // 6. Gender
        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $errors['gender'] = 'Please select a valid gender option.';
        }

        // 7. Department & Position
        if ($deptId <= 0) {
            $errors['department_id'] = 'Please select a valid department.';
        }
        if ($posId <= 0) {
            $errors['position_id'] = 'Please select a valid job position.';
        }

        // 8. Date Hired
        if ($dateHired === '') {
            $errors['date_hired'] = 'Date hired is required.';
        } else {
            $dh = DateTime::createFromFormat('Y-m-d', $dateHired);
            if (!$dh || $dh->format('Y-m-d') !== $dateHired) {
                $errors['date_hired'] = 'Invalid date format (must be YYYY-MM-DD).';
            } elseif ($dh > (new DateTime())->setTime(23, 59, 59)) {
                $errors['date_hired'] = 'Date hired cannot be in the future.';
            }
        }

        // 9. Employment Status
        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $errors['employment_status'] = 'Please select a valid employment status (Active or Inactive).';
        }

        return $errors;
    }
}
