<?php

namespace Tests\Unit;

use App\Services\EmployeeValidator;
use PHPUnit\Framework\TestCase;

/**
 * Group 3 regression: EmployeeValidator::sanitize (EMP-03) and the position-belongs-to-
 * department rule (EMP-04/EMP-02 parity). These guard the API path, which previously used
 * a weaker private validator and could receive non-scalar JSON values.
 */
class EmployeeValidatorSanitizeTest extends TestCase
{
    public function test_sanitize_coerces_non_scalar_values_to_empty_strings(): void
    {
        $clean = EmployeeValidator::sanitize([
            'employee_no'   => ['array-injection'],
            'first_name'    => ['a'],
            'department_id' => ['x'],
            'position_id'   => ['y'],
            'email'         => "  spaced@example.com  ",
        ]);

        // Non-scalars become '' (never arrays) so downstream trim()/model calls are type-safe.
        $this->assertSame('', $clean['employee_no']);
        $this->assertSame('', $clean['first_name']);
        $this->assertSame(0, $clean['department_id']);
        $this->assertSame(0, $clean['position_id']);
        $this->assertSame('spaced@example.com', $clean['email']);
        $this->assertSame('Active', $clean['employment_status']);
    }

    public function test_sanitized_array_is_safe_to_validate_without_type_errors(): void
    {
        // The exact payload shape that previously caused a TypeError (array in a string field).
        $clean = EmployeeValidator::sanitize(['first_name' => ['a'], 'employee_no' => ['b']]);
        $errors = EmployeeValidator::validate($clean);

        $this->assertArrayHasKey('first_name', $errors);
        $this->assertArrayHasKey('employee_no', $errors);
    }

    public function test_position_must_belong_to_selected_department(): void
    {
        $payload = EmployeeValidator::sanitize([
            'employee_no'   => 'EMP-X', 'first_name' => 'A', 'last_name' => 'B',
            'email'         => 'a@b.test', 'birth_date' => '1990-01-01', 'gender' => 'Male',
            'department_id' => 5, 'position_id' => 9, 'date_hired' => '2024-01-01',
            'employment_status' => 'Active',
        ]);

        // Position 9 belongs to department 7, not the selected 5.
        $errors = EmployeeValidator::validate(
            $payload,
            null,
            null,
            fn (int $posId, int $deptId): bool => $posId === 9 && $deptId === 7
        );

        $this->assertArrayHasKey('position_id', $errors);
        $this->assertStringContainsString('does not belong', $errors['position_id']);
    }

    public function test_position_in_matching_department_passes(): void
    {
        $payload = EmployeeValidator::sanitize([
            'employee_no'   => 'EMP-X', 'first_name' => 'A', 'last_name' => 'B',
            'email'         => 'a@b.test', 'birth_date' => '1990-01-01', 'gender' => 'Male',
            'department_id' => 7, 'position_id' => 9, 'date_hired' => '2024-01-01',
            'employment_status' => 'Active',
        ]);

        $errors = EmployeeValidator::validate(
            $payload,
            null,
            null,
            fn (int $posId, int $deptId): bool => $posId === 9 && $deptId === 7
        );

        $this->assertArrayNotHasKey('position_id', $errors);
    }
}
