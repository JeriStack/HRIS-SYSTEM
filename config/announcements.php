<?php

/**
 * HR Announcements Management Configuration
 * Employee HRIS & Leave Management System (NEXORA HR)
 *
 * Defines categories, priority levels, lifecycle statuses, valid state transitions,
 * audience targeting rules, attachment limits, notification batching, and ownership policies.
 */

declare(strict_types=1);

$maxAttachmentBytes = getenv('ANNOUNCEMENT_ATTACHMENT_MAX_BYTES') ?: getenv('SUPPORT_ATTACHMENT_MAX_BYTES');
$maxFilesPerAnnouncement = getenv('ANNOUNCEMENT_MAX_ATTACHMENTS');
$notificationBatchSize = getenv('ANNOUNCEMENT_NOTIFICATION_BATCH_SIZE');

return [
    'reference_prefix' => 'ANN',

    // External bulk email via PHPMailer is strictly disabled unless explicitly approved
    'enable_external_email' => false,

    // Pagination defaults
    'hr_per_page_default'       => 10,
    'hr_per_page_max'           => 50,
    'employee_per_page_default' => 10,
    'employee_per_page_max'     => 50,
    'dashboard_preview_limit'   => 3,
    'reads_per_page_default'    => 25,

    // Asynchronous queue notification batching
    'notification_batch_size' => ($notificationBatchSize !== false && (int)$notificationBatchSize > 0)
        ? (int)$notificationBatchSize
        : 100,

    // Rate limiting (rolling window in seconds)
    'rate_limit_window_seconds' => 900, // 15 minutes
    'rate_limit_max_mutations'  => 30,

    // Attachment validation & non-public storage limits
    'attachment_max_bytes' => ($maxAttachmentBytes !== false && (int)$maxAttachmentBytes > 0)
        ? (int)$maxAttachmentBytes
        : 5 * 1024 * 1024, // 5 MB default per file

    'max_attachments_per_announcement' => ($maxFilesPerAnnouncement !== false && (int)$maxFilesPerAnnouncement > 0)
        ? (int)$maxFilesPerAnnouncement
        : 3,

    'attachment_allowed_mimes' => [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'text/plain'      => 'txt',
    ],

    // Official Announcement Categories (Phase 3.2)
    'categories' => [
        'General Announcement' => [
            'label'       => 'General Announcement',
            'description' => 'Organization-wide general communications and notices.',
        ],
        'Company Updates' => [
            'label'       => 'Company Updates',
            'description' => 'Executive updates, milestones, and organizational announcements.',
        ],
        'HR Policy Updates' => [
            'label'       => 'HR Policy Updates',
            'description' => 'Updates to workplace policies, compliance rules, and employee handbook guidelines.',
        ],
        'Leave and Benefits' => [
            'label'       => 'Leave and Benefits',
            'description' => 'Leave cycle schedules, statutory benefit updates, and enrollment reminders.',
        ],
        'Events and Activities' => [
            'label'       => 'Events and Activities',
            'description' => 'Town halls, training sessions, wellness programs, and company events.',
        ],
        'Office Operations' => [
            'label'       => 'Office Operations',
            'description' => 'Facility schedules, holiday work arrangements, and IT/office maintenance notices.',
        ],
        'Emergency Advisory' => [
            'label'       => 'Emergency Advisory',
            'description' => 'Weather advisories, safety protocols, and urgent operational disruptions.',
        ],
        'Other' => [
            'label'       => 'Other',
            'description' => 'Additional official HR notices and workforce advisories.',
        ],
    ],

    // Official Priority Levels (Phase 3.2)
    'priorities' => [
        'Normal',
        'Important',
        'Urgent',
    ],

    // Priority sort rank (higher = higher precedence in feeds/previews)
    'priority_weights' => [
        'Urgent'    => 3,
        'Important' => 2,
        'Normal'    => 1,
    ],

    // Official Lifecycle Statuses (Phase 4)
    'statuses' => [
        'Draft',
        'Scheduled',
        'Published',
        'Archived',
        'Expired',
    ],

    // Audience Target Types (Phase 3.2 & Phase 7.2)
    'target_types' => [
        'all',
        'department',
    ],

    // Controlled Server-Side Lifecycle Status Transitions (Phase 3.4 & Phase 4)
    'allowed_transitions' => [
        'Draft'     => ['Draft', 'Scheduled', 'Published', 'Archived'],
        'Scheduled' => ['Scheduled', 'Draft', 'Published', 'Archived'],
        'Published' => ['Published', 'Expired', 'Archived'],
        'Expired'   => ['Expired', 'Published', 'Archived'],
        'Archived'  => ['Archived'],
    ],
];
