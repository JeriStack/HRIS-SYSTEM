<?php

/**
 * Employee Help & Support and Complaint Management Configuration
 * Defines ticket identifiers, categories, lifecycle transitions, attachment rules, and limits.
 */

$maxAttachmentBytes = getenv('SUPPORT_ATTACHMENT_MAX_BYTES');
$maxFilesPerPost = getenv('SUPPORT_MAX_FILES_PER_POST');
$reopenWindowDays = getenv('SUPPORT_REOPEN_WINDOW_DAYS');

return [
    'ticket_prefix'        => 'SUP',
    'public_ticket_prefix' => 'AUTH',

    // Public pre-login support tracking & rate limits
    'public_tracking_token_ttl_days'     => 30,
    'public_submission_rate_limit_window' => 900, // 15 minutes
    'public_submission_rate_limit_max_ip' => 5,
    'public_submission_rate_limit_max_email' => 3,

    // Attachment validation & private storage limits
    'attachment_max_bytes' => ($maxAttachmentBytes !== false && (int)$maxAttachmentBytes > 0)
        ? (int)$maxAttachmentBytes
        : 5 * 1024 * 1024, // 5 MB default per file

    'max_attachments_per_submission' => ($maxFilesPerPost !== false && (int)$maxFilesPerPost > 0)
        ? (int)$maxFilesPerPost
        : 3,

    // Strictly curated safe MIME -> extension mapping (executables, scripts, HTML, SVG excluded)
    'attachment_allowed_mimes' => [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'text/plain'      => 'txt',
    ],

    // Employee reopen allowance window for Resolved tickets (in calendar days)
    'reopen_window_days' => ($reopenWindowDays !== false && (int)$reopenWindowDays > 0)
        ? (int)$reopenWindowDays
        : 14,

    // Abuse/flood prevention for employee ticket & reply creation
    'submission_rate_limit_window' => 900, // 15 minutes
    'submission_rate_limit_max'    => 10,

    // General support categories
    'support_categories' => [
        'General Inquiry'             => [
            'label'       => 'General Inquiry',
            'description' => 'General questions regarding workplace operations, onboarding, or employee services.',
            'default_priority' => 'Low',
            'forces_confidential' => false,
        ],
        'Technical Issue'             => [
            'label'       => 'Technical Issue',
            'description' => 'System access problems, account bugs, or portal display issues.',
            'default_priority' => 'Medium',
            'forces_confidential' => false,
        ],
        'Leave Concern'               => [
            'label'       => 'Leave Concern',
            'description' => 'Questions about leave balances, statutory leave requirements, or filing issues.',
            'default_priority' => 'Medium',
            'forces_confidential' => false,
        ],
        'Employee Profile Correction' => [
            'label'       => 'Employee Profile Correction',
            'description' => 'Follow-up assistance or inquiry regarding employee record updates.',
            'default_priority' => 'Low',
            'forces_confidential' => false,
        ],
        'Workplace Complaint'         => [
            'label'       => 'Workplace Complaint',
            'description' => 'Formal workplace concern handled through confidential complaint access controls.',
            'default_priority' => 'High',
            'forces_confidential' => true,
        ],
        'Policy Clarification'        => [
            'label'       => 'Policy Clarification',
            'description' => 'Clarification on HR handbooks, statutory compliance, or workplace policies.',
            'default_priority' => 'Low',
            'forces_confidential' => false,
        ],
        'Other'                       => [
            'label'       => 'Other',
            'description' => 'Any support matter that does not fit the listed categories.',
            'default_priority' => 'Medium',
            'forces_confidential' => false,
        ],
    ],

    // Confidential workplace complaint sub-categories
    'complaint_categories' => [
        'Workplace Conduct'            => 'Workplace Conduct',
        'Employee Relations'           => 'Employee Relations',
        'Workplace Policy Concern'     => 'Workplace Policy Concern',
        'Discrimination or Harassment' => 'Discrimination or Harassment',
        'Unprofessional Behavior'      => 'Unprofessional Behavior',
        'Other Workplace Concern'      => 'Other Workplace Concern',
    ],

    // Ticket priorities
    'priorities' => [
        'Low',
        'Medium',
        'High',
        'Urgent',
    ],

    // Ticket lifecycle statuses
    'statuses' => [
        'Submitted',
        'Under Review',
        'Waiting for Employee',
        'Resolved',
        'Closed',
    ],

    'open_statuses' => [
        'Submitted',
        'Under Review',
        'Waiting for Employee',
    ],

    // HR/authorized handler permitted transitions from each status
    'hr_transitions' => [
        'Submitted'            => ['Under Review', 'Waiting for Employee', 'Resolved', 'Closed'],
        'Under Review'         => ['Waiting for Employee', 'Resolved', 'Closed'],
        'Waiting for Employee' => ['Under Review', 'Resolved', 'Closed'],
        'Resolved'             => ['Under Review', 'Closed'],
        'Closed'               => ['Under Review'],
    ],

    // Configured escalation handling policy notice
    'escalation_policy_summary' => 'When no non-conflicted authorized HR handler is available (for example due to conflict of interest), the complaint is flagged for escalated independent Administrator oversight and withheld from all conflicted personnel.',

    // Unauthenticated public login/auth support categories (AUTH-YYYY-NNNNN)
    'public_auth_categories' => [
        'Unable to Sign In' => [
            'label'            => 'Unable to Sign In',
            'description'      => 'Password rejected, unrecognized sign-in prompt, or unexpected portal errors.',
            'default_priority' => 'Medium',
        ],
        'Account Locked or Disabled' => [
            'label'            => 'Account Locked or Disabled',
            'description'      => 'Temporary lockout or inactive account notice blocking portal access.',
            'default_priority' => 'High',
        ],
        'OTP Delivery Problem' => [
            'label'            => 'OTP Delivery Problem',
            'description'      => 'Verification code delayed, missing, or bouncing before delivery.',
            'default_priority' => 'High',
        ],
        'Invalid OTP' => [
            'label'            => 'Invalid OTP',
            'description'      => 'Entered verification code rejected even though it appears current.',
            'default_priority' => 'Medium',
        ],
        'Password Recovery Problem' => [
            'label'            => 'Password Recovery Problem',
            'description'      => 'Reset link not arriving, expired immediately, or unable to complete password reset.',
            'default_priority' => 'Medium',
        ],
        'Login Page Error' => [
            'label'            => 'Login Page Error',
            'description'      => 'Session/CSRF warnings, browser redirect loops, or rendering issues on sign-in pages.',
            'default_priority' => 'Medium',
        ],
        'Other Technical Issue' => [
            'label'            => 'Other Technical Issue',
            'description'      => 'Other pre-login technical or access problem requiring HR/IT assistance.',
            'default_priority' => 'Low',
        ],
    ],
];
