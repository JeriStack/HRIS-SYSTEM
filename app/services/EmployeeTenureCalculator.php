<?php

/**
 * Employee Tenure & Date Formatting Service
 * Computes dynamic years/months of service relative to hire date without static persistence.
 */

namespace App\Services;

use DateTimeImmutable;

final class EmployeeTenureCalculator
{
    /**
     * Compute years of service from hire date to reference date (defaults to today).
     *
     * @param string|null $dateHired YYYY-MM-DD date string
     * @param DateTimeImmutable|null $asOf Reference date for deterministic calculations
     * @return array{
     *     valid: bool,
     *     is_future: bool,
     *     status: string,
     *     years: int,
     *     months: int,
     *     days: int,
     *     total_days: int,
     *     formatted: string,
     *     display: string,
     *     short_display: string
     * }
     */
    public static function calculate(?string $dateHired, ?DateTimeImmutable $asOf = null): array
    {
        $raw = trim((string)$dateHired);
        if ($raw === '') {
            return [
                'valid'         => false,
                'is_future'     => false,
                'status'        => 'missing',
                'years'         => 0,
                'months'        => 0,
                'days'          => 0,
                'total_days'    => 0,
                'formatted'     => 'Not specified',
                'display'       => 'Not specified',
                'short_display' => 'N/A',
            ];
        }

        $hire = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if (!$hire || $hire->format('Y-m-d') !== $raw) {
            return [
                'valid'         => false,
                'is_future'     => false,
                'status'        => 'invalid',
                'years'         => 0,
                'months'        => 0,
                'days'          => 0,
                'total_days'    => 0,
                'formatted'     => 'Not available',
                'display'       => 'Not available',
                'short_display' => 'N/A',
            ];
        }

        $today = ($asOf ?? new DateTimeImmutable('today'))->setTime(0, 0, 0);

        if ($hire > $today) {
            $upcomingText = 'Starts on ' . $hire->format('M j, Y');
            return [
                'valid'         => true,
                'is_future'     => true,
                'status'        => 'future',
                'years'         => 0,
                'months'        => 0,
                'days'          => 0,
                'total_days'    => 0,
                'formatted'     => $upcomingText,
                'display'       => $upcomingText,
                'short_display' => 'Not started',
            ];
        }

        $diff = $hire->diff($today);
        $years = (int)$diff->y;
        $months = (int)$diff->m;
        $days = (int)$diff->d;
        $totalDays = (int)$diff->days;

        if ($years === 0 && $months === 0) {
            $dayLabel = $days === 1 ? '1 day' : "{$days} days";
            $display = $days === 0
                ? 'Less than 1 month (hired today)'
                : "Less than 1 month ({$dayLabel})";
            $short = $days === 0 ? 'New' : '< 1 mo';
        } elseif ($years === 0) {
            $monthLabel = $months === 1 ? '1 month' : "{$months} months";
            $display = $monthLabel;
            $short = "{$months} mo";
        } else {
            $yearLabel = $years === 1 ? '1 year' : "{$years} years";
            $monthLabel = $months === 1 ? '1 month' : "{$months} months";
            $display = $months > 0 ? "{$yearLabel}, {$monthLabel}" : $yearLabel;
            $short = number_format($years + ($months / 12), 1) . ' yrs';
        }

        return [
            'valid'         => true,
            'is_future'     => false,
            'status'        => 'valid',
            'years'         => $years,
            'months'        => $months,
            'days'          => $days,
            'total_days'    => $totalDays,
            'formatted'     => $display,
            'display'       => $display,
            'short_display' => $short,
        ];
    }

    /**
     * Safely format an ISO date for profile views.
     */
    public static function formatDate(?string $date, string $fallback = 'Not specified'): string
    {
        $raw = trim((string)$date);
        if ($raw === '') {
            return $fallback;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($parsed && $parsed->format('Y-m-d') === $raw) {
            return $parsed->format('M j, Y');
        }

        $ts = strtotime($raw);
        return $ts !== false ? date('M j, Y', $ts) : $fallback;
    }
}
