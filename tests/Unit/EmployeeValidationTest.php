<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\EmployeeValidator;

class EmployeeValidationTest extends TestCase
{
    private array $validPayload;

    protected function setUp(): void
    {
        $this->validPayload = [
            'employee_no'       => 'EMP-2026-001',
            'first_name'        => 'Juan',
            'middle_name'       => 'Santos',
            'last_name'         => 'Dela Cruz',
            'email'             => 'juan.delacruz@company.com',
            'phone'             => '+63 917 123 4567',
            'birth_date'        => '1995-05-15',
            'gender'            => 'Male',
            'department_id'     => 1,
            'position_id'       => 2,
            'date_hired'        => '2024-01-10',
            'employment_status' => 'Active',
        ];
    }

    public function test_valid_employee_payload_passes_validation(): void
    {
        $errors = EmployeeValidator::validate($this->validPayload);
        $this->assertEmpty($errors, 'Valid employee payload should have no validation errors.');
    }

    public function test_missing_required_fields_produces_errors(): void
    {
        $payload = [
            'employee_no' => '',
            'first_name'  => '',
            'last_name'   => '',
            'email'       => '',
            'birth_date'  => '',
            'date_hired'  => '',
        ];

        $errors = EmployeeValidator::validate($payload);

        $this->assertArrayHasKey('employee_no', $errors);
        $this->assertArrayHasKey('first_name', $errors);
        $this->assertArrayHasKey('last_name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('birth_date', $errors);
        $this->assertArrayHasKey('date_hired', $errors);
    }

    public function test_invalid_email_format_is_rejected(): void
    {
        $payload = $this->validPayload;
        $payload['email'] = 'invalid-email-address';

        $errors = EmployeeValidator::validate($payload);
        $this->assertArrayHasKey('email', $errors);
        $this->assertSame('Please enter a valid email address.', $errors['email']);
    }

    public function test_future_birth_date_is_rejected(): void
    {
        $payload = $this->validPayload;
        $payload['birth_date'] = date('Y-m-d', strtotime('+1 year'));

        $errors = EmployeeValidator::validate($payload);
        $this->assertArrayHasKey('birth_date', $errors);
        $this->assertSame('Birth date cannot be in the future.', $errors['birth_date']);
    }

    public function test_invalid_gender_is_rejected(): void
    {
        $payload = $this->validPayload;
        $payload['gender'] = 'InvalidGender';

        $errors = EmployeeValidator::validate($payload);
        $this->assertArrayHasKey('gender', $errors);
        $this->assertSame('Please select a valid gender option.', $errors['gender']);
    }

    public function test_invalid_employment_status_is_rejected(): void
    {
        $payload = $this->validPayload;
        $payload['employment_status'] = 'UnknownStatus';

        $errors = EmployeeValidator::validate($payload);
        $this->assertArrayHasKey('employment_status', $errors);
    }

    public function test_duplicate_employee_number_detection(): void
    {
        $uniqueCheck = fn(string $empNo) => false; // Simulates duplicate found in database

        $errors = EmployeeValidator::validate($this->validPayload, $uniqueCheck);
        $this->assertArrayHasKey('employee_no', $errors);
        $this->assertStringContainsString('already assigned', $errors['employee_no']);
    }
}
