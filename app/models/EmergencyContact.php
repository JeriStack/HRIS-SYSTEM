<?php

/**
 * Emergency Contact Model
 * Manages normalized employee emergency contact records with strict ownership scoping.
 */

namespace App\Models;

use Core\Model;
use PDO;

class EmergencyContact extends Model
{
    public const VALID_RELATIONSHIPS = [
        'Spouse',
        'Parent',
        'Sibling',
        'Child',
        'Relative',
        'Friend',
        'Guardian',
        'Other',
    ];

    public function __construct(?PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            parent::__construct();
        }
    }

    /**
     * Retrieve all emergency contacts for a specific employee, primary contact first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByEmployee(int $employeeId): array
    {
        $sql = "
            SELECT
                id,
                employee_id,
                full_name,
                relationship,
                contact_number,
                alternative_contact_number,
                address,
                is_primary,
                created_at,
                updated_at
            FROM employee_emergency_contacts
            WHERE employee_id = :emp_id
            ORDER BY is_primary DESC, id ASC
        ";

        return $this->fetchAll($sql, ['emp_id' => $employeeId]);
    }

    /**
     * Retrieve the primary emergency contact for an employee, if one exists.
     */
    public function getPrimaryByEmployee(int $employeeId): ?array
    {
        $sql = "
            SELECT
                id,
                employee_id,
                full_name,
                relationship,
                contact_number,
                alternative_contact_number,
                address,
                is_primary,
                created_at,
                updated_at
            FROM employee_emergency_contacts
            WHERE employee_id = :emp_id
            ORDER BY is_primary DESC, id ASC
            LIMIT 1
        ";

        return $this->fetchOne($sql, ['emp_id' => $employeeId]);
    }

    /**
     * Validate emergency contact input strictly on the server side.
     *
     * @param array<string, mixed> $input
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public static function validateInput(array $input): array
    {
        $fullName = trim((string)($input['full_name'] ?? ''));
        $relationship = trim((string)($input['relationship'] ?? ''));
        $contactNumber = trim((string)($input['contact_number'] ?? ''));
        $altNumber = trim((string)($input['alternative_contact_number'] ?? ''));
        $address = trim((string)($input['address'] ?? ''));

        $errors = [];

        if ($fullName === '') {
            $errors['full_name'] = 'Emergency contact full name is required.';
        } elseif (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
            $errors['full_name'] = 'Emergency contact name must be between 2 and 120 characters.';
        }

        if ($relationship === '') {
            $errors['relationship'] = 'Relationship is required.';
        } elseif (mb_strlen($relationship) > 60) {
            $errors['relationship'] = 'Relationship cannot exceed 60 characters.';
        }

        if ($contactNumber === '') {
            $errors['contact_number'] = 'Primary contact number is required.';
        } elseif (!self::isValidPhoneNumber($contactNumber)) {
            $errors['contact_number'] = 'Please enter a valid primary contact number (7 to 25 phone characters).';
        }

        if ($altNumber !== '' && !self::isValidPhoneNumber($altNumber)) {
            $errors['alternative_contact_number'] = 'Please enter a valid alternative contact number.';
        }

        if ($address !== '' && mb_strlen($address) > 500) {
            $errors['address'] = 'Emergency contact address cannot exceed 500 characters.';
        }

        return [
            'data'   => [
                'full_name'                  => $fullName,
                'relationship'               => $relationship,
                'contact_number'             => $contactNumber,
                'alternative_contact_number' => $altNumber !== '' ? $altNumber : null,
                'address'                    => $address !== '' ? $address : null,
            ],
            'errors' => $errors,
        ];
    }

    /**
     * Validate international and local phone number formats cleanly.
     */
    public static function isValidPhoneNumber(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '' || preg_match('/^[\d\s+\-().]{7,25}$/', $trimmed) !== 1) {
            return false;
        }
        $digits = preg_replace('/\D+/', '', $trimmed);
        return $digits !== null && strlen($digits) >= 7 && strlen($digits) <= 15;
    }

    /**
     * Create or update the primary emergency contact scoped strictly to one employee.
     */
    public function upsertPrimary(int $employeeId, array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->getPrimaryByEmployee($employeeId);

        if ($existing) {
            $sql = "
                UPDATE employee_emergency_contacts
                SET full_name = :full_name,
                    relationship = :relationship,
                    contact_number = :contact_number,
                    alternative_contact_number = :alt_number,
                    address = :address,
                    is_primary = 1,
                    updated_at = :updated_at
                WHERE id = :id AND employee_id = :emp_id
            ";
            $this->execute($sql, [
                'full_name'      => $data['full_name'],
                'relationship'   => $data['relationship'],
                'contact_number' => $data['contact_number'],
                'alt_number'     => $data['alternative_contact_number'] ?? null,
                'address'        => $data['address'] ?? null,
                'updated_at'     => $now,
                'id'             => (int)$existing['id'],
                'emp_id'         => $employeeId,
            ]);

            return (int)$existing['id'];
        }

        $sql = "
            INSERT INTO employee_emergency_contacts (
                employee_id,
                full_name,
                relationship,
                contact_number,
                alternative_contact_number,
                address,
                is_primary,
                created_at,
                updated_at
            ) VALUES (
                :emp_id,
                :full_name,
                :relationship,
                :contact_number,
                :alt_number,
                :address,
                1,
                :created_at,
                :updated_at
            )
        ";
        $this->execute($sql, [
            'emp_id'         => $employeeId,
            'full_name'      => $data['full_name'],
            'relationship'   => $data['relationship'],
            'contact_number' => $data['contact_number'],
            'alt_number'     => $data['alternative_contact_number'] ?? null,
            'address'        => $data['address'] ?? null,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        return (int)$this->lastInsertId();
    }

    /**
     * Delete an emergency contact only if owned by the specified employee.
     */
    public function deleteForEmployee(int $contactId, int $employeeId): bool
    {
        $stmt = $this->query(
            "DELETE FROM employee_emergency_contacts WHERE id = :id AND employee_id = :emp_id",
            ['id' => $contactId, 'emp_id' => $employeeId]
        );

        return $stmt->rowCount() > 0;
    }
}
