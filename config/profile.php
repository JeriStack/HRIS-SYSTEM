<?php

/**
 * Employee Profile Enhancement Configuration
 * Upload policies, organizational enumerations, and HR correction field metadata.
 */

$maxAvatarBytes = getenv('PROFILE_PICTURE_MAX_BYTES');

return [
    // Profile picture security & storage limits
    'avatar_max_bytes'     => ($maxAvatarBytes !== false && (int)$maxAvatarBytes > 0)
        ? (int)$maxAvatarBytes
        : 5 * 1024 * 1024, // 5 MB default
    'avatar_allowed_mimes' => [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ],
    'avatar_min_width'     => 64,
    'avatar_min_height'    => 64,
    'avatar_max_width'     => 6000,
    'avatar_max_height'    => 6000,
    'avatar_output_size'   => 512,

    // Organizational profile attribute options
    'civil_statuses' => [
        'Single',
        'Married',
        'Widowed',
        'Separated',
        'Divorced',
    ],
    'genders' => [
        'Male',
        'Female',
        'Other',
    ],
    'employment_types' => [
        'Regular',
        'Probationary',
        'Contractual',
        'Project-Based',
        'Part-Time',
    ],

    // Whitelist of HR-controlled profile fields eligible for Correction Requests
    'correction_fields' => [
        'first_name' => [
            'label'     => 'First Name',
            'group'     => 'Personal Information',
            'input'     => 'text',
            'maxLength' => 50,
            'required'  => true,
        ],
        'middle_name' => [
            'label'     => 'Middle Name',
            'group'     => 'Personal Information',
            'input'     => 'text',
            'maxLength' => 50,
            'required'  => false,
        ],
        'last_name' => [
            'label'     => 'Last Name',
            'group'     => 'Personal Information',
            'input'     => 'text',
            'maxLength' => 50,
            'required'  => true,
        ],
        'birth_date' => [
            'label'    => 'Date of Birth',
            'group'    => 'Personal Information',
            'input'    => 'date',
            'required' => true,
        ],
        'gender' => [
            'label'    => 'Gender',
            'group'    => 'Personal Information',
            'input'    => 'select',
            'options'  => ['Male', 'Female', 'Other'],
            'required' => true,
        ],
        'civil_status' => [
            'label'    => 'Civil Status',
            'group'    => 'Personal Information',
            'input'    => 'select',
            'options'  => ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'],
            'required' => true,
        ],
        'email' => [
            'label'     => 'Work Email Address',
            'group'     => 'Contact Information',
            'input'     => 'email',
            'maxLength' => 100,
            'required'  => true,
        ],
        'department_id' => [
            'label'    => 'Department',
            'group'    => 'Employment & Organization',
            'input'    => 'department',
            'required' => true,
        ],
        'position_id' => [
            'label'    => 'Current Position',
            'group'    => 'Employment & Organization',
            'input'    => 'position',
            'required' => true,
        ],
        'date_hired' => [
            'label'    => 'Date Hired',
            'group'    => 'Employment & Organization',
            'input'    => 'date',
            'required' => true,
        ],
        'employment_type' => [
            'label'    => 'Employment Type',
            'group'    => 'Employment & Organization',
            'input'    => 'select',
            'options'  => ['Regular', 'Probationary', 'Contractual', 'Project-Based', 'Part-Time'],
            'required' => true,
        ],
    ],
];
