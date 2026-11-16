<?php

/**
 * HR-to-Admin Help & Support Configuration
 * Defines ticket identifiers, categories, priorities, controlled workflow transitions,
 * attachment storage rules, reopening policy, and rate limits.
 */

$maxAttachmentBytes = getenv('HR_SUPPORT_ATTACHMENT_MAX_BYTES') ?: getenv('SUPPORT_ATTACHMENT_MAX_BYTES');
$maxFilesPerPost = getenv('HR_SUPPORT_MAX_FILES_PER_POST') ?: getenv('SUPPORT_MAX_FILES_PER_POST');
$reopenWindowDays = getenv('HR_SUPPORT_REOPEN_WINDOW_DAYS');

return [
    'ticket_prefix' => 'HR-SUP',

    // Attachment validation & non-public storage limits
    'attachment_max_bytes' => ($maxAttachmentBytes !== false && (int)$maxAttachmentBytes > 0)
        ? (int)$maxAttachmentBytes
        : 5 * 1024 * 1024, // 5 MB default per file

    'max_attachments_per_submission' => ($maxFilesPerPost !== false && (int)$maxFilesPerPost > 0)
        ? (int)$maxFilesPerPost
        : 3,

    'attachment_allowed_mimes' => [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'text/plain'      => 'txt',
    ],

    // Calendar days after Resolved timestamp during which HR may request reopening
    'reopen_window_days' => ($reopenWindowDays !== false && (int)$reopenWindowDays > 0)
        ? (int)$reopenWindowDays
        : 14,

    // Rate limiting (per user within rolling window via activity_logs)
    'submission_rate_limit_window' => 900, // 15 minutes
    'submission_rate_limit_max'    => 10,
    'reply_rate_limit_window'      => 900,
    'reply_rate_limit_max'         => 30,

    // Exact HR-to-Admin issue categories
    'categories' => [
        'System Error' => [
            'label'            => 'System Error',
            'description'      => 'Unexpected application exceptions, HTTP 500 responses, or broken system actions.',
            'default_priority' => 'High',
        ],
        'Login and Authentication' => [
            'label'            => 'Login and Authentication',
            'description'      => 'Sign-in flow problems, Email OTP delays, TOTP checks, or session timeout defects.',
            'default_priority' => 'High',
        ],
        'Account and Access Problems' => [
            'label'            => 'Account and Access Problems',
            'description'      => 'Role capability mismatches, account provisioning needs, or access denials.',
            'default_priority' => 'Medium',
        ],
        'Dashboard or UI Issue' => [
            'label'            => 'Dashboard or UI Issue',
            'description'      => 'Layout glitches, broken filters, dark/light contrast issues, or unresponsive pages.',
            'default_priority' => 'Medium',
        ],
        'Employee Management Issue' => [
            'label'            => 'Employee Management Issue',
            'description'      => 'Errors creating, editing, deactivating, or updating employee/organization records.',
            'default_priority' => 'Medium',
        ],
        'Leave Management Issue' => [
            'label'            => 'Leave Management Issue',
            'description'      => 'Issues with leave policies, balances, statutory qualifications, or approval queues.',
            'default_priority' => 'High',
        ],
        'Notification or Email Issue' => [
            'label'            => 'Notification or Email Issue',
            'description'      => 'Delayed SMTP emails, real-time alert dropouts, or outbox processing problems.',
            'default_priority' => 'Medium',
        ],
        'Performance or System Availability' => [
            'label'            => 'Performance or System Availability',
            'description'      => 'Slow page loads, timeouts, background worker stalls, or service downtime.',
            'default_priority' => 'Critical',
        ],
        'Data Discrepancy' => [
            'label'            => 'Data Discrepancy',
            'description'      => 'Mismatched leave ledgers, report totals, or record synchronization discrepancies.',
            'default_priority' => 'High',
        ],
        'Other' => [
            'label'            => 'Other',
            'description'      => 'Other operational or technical concern requiring Administrator review.',
            'default_priority' => 'Low',
        ],
    ],

    // Exact priority levels
    'priorities' => [
        'Low',
        'Medium',
        'High',
        'Critical',
    ],

    // Exact controlled ticket lifecycle statuses
    'statuses' => [
        'Submitted',
        'Under Review',
        'In Progress',
        'Waiting for HR',
        'Resolved',
        'Closed',
    ],

    'open_statuses' => [
        'Submitted',
        'Under Review',
        'In Progress',
        'Waiting for HR',
    ],

    // Admin-permitted status transitions
    'admin_transitions' => [
        'Submitted'      => ['Under Review', 'In Progress', 'Waiting for HR', 'Resolved', 'Closed'],
        'Under Review'   => ['In Progress', 'Waiting for HR', 'Resolved', 'Closed'],
        'In Progress'    => ['Under Review', 'Waiting for HR', 'Resolved', 'Closed'],
        'Waiting for HR' => ['Under Review', 'In Progress', 'Resolved', 'Closed'],
        'Resolved'       => ['Under Review', 'In Progress', 'Closed'],
        'Closed'         => ['Under Review', 'In Progress'],
    ],

    // HR reopening/confirmation policy:
    // - HR can confirm resolution on a 'Resolved' ticket -> 'Closed'
    // - HR can request reopening on a 'Resolved' ticket within reopen_window_days -> 'Under Review'
    // - Closed tickets cannot be reopened by HR directly (only Admin may reopen Closed tickets)
    'hr_reopenable_statuses' => ['Resolved'],
    'hr_confirmable_statuses' => ['Resolved'],

    // UI status descriptions shown to HR/Admin
    'reopen_policy_summary' => 'Resolved tickets may be confirmed closed by HR or reopened within %d days if the issue persists. Once closed, only an Administrator may reopen a ticket.',
];
