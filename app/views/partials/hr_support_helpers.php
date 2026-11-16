<?php

/**
 * Shared Presentation Helpers for HR-to-Admin Help & Support Views
 */

if (!function_exists('hr_support_status_badge')) {
    function hr_support_status_badge(string $status): string
    {
        return match ($status) {
            'Resolved', 'Closed' => 'badge-success',
            'Waiting for HR'     => 'badge-warning',
            'In Progress'        => 'badge-primary',
            'Under Review'       => 'badge-info',
            default              => 'badge-neutral',
        };
    }
}

if (!function_exists('hr_support_priority_badge')) {
    function hr_support_priority_badge(string $priority): string
    {
        return match ($priority) {
            'Critical' => 'badge-danger',
            'High'     => 'badge-warning',
            'Low'      => 'badge-neutral',
            default    => 'badge-primary',
        };
    }
}

if (!function_exists('hr_support_format_person')) {
    function hr_support_format_person(?string $first, ?string $last, ?string $username, string $fallback = 'Unassigned'): string
    {
        $full = trim(((string)$first) . ' ' . ((string)$last));
        if ($full !== '') {
            return $full;
        }
        $uname = trim((string)$username);
        return $uname !== '' ? $uname : $fallback;
    }
}
