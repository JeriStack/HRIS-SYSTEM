<?php
/**
 * Leave Day Calculation & Date Range Validation Service
 * Employee HRIS & Leave Management System - Phase 8
 */

namespace App\Services;

use DateTimeImmutable;
use DateInterval;
use InvalidArgumentException;

class LeaveCalculator
{
    /**
     * Working day policy assumption:
     * Monday (1) through Friday (5) are business working days.
     * Saturday (6) and Sunday (7) are excluded statutory non-working days.
     * Note: Statutory public holidays are pending business-policy decision and
     * currently treated as standard calendar weekdays per Section 7 specifications.
     */
    public const WORKING_DAYS = [1, 2, 3, 4, 5]; // 1 = Monday, 7 = Sunday (ISO-8601)

    /**
     * Calculate eligible working days between two dates inclusive.
     *
     * @param string $startDate 'Y-m-d'
     * @param string $endDate   'Y-m-d'
     * @return int Number of working days (Mon-Fri)
     * @throws InvalidArgumentException When date formats are invalid or end < start
     */
    public static function calculateWorkingDays(string $startDate, string $endDate): int
    {
        $validation = self::validateDateRange($startDate, $endDate);
        if (!$validation['valid']) {
            throw new InvalidArgumentException($validation['error'] ?? 'Invalid date range specified.');
        }

        return $validation['working_days'];
    }

    /**
     * Check if a specific date falls on a business working day (Mon-Fri).
     *
     * @param string $date 'Y-m-d'
     * @return bool True if Monday through Friday, False for Saturday or Sunday
     * @throws InvalidArgumentException
     */
    public static function isWorkingDay(string $date): bool
    {
        $dt = self::parseDate($date);
        $dayOfWeek = (int)$dt->format('N'); // 1 (for Monday) through 7 (for Sunday)
        return in_array($dayOfWeek, self::WORKING_DAYS, true);
    }

    /**
     * Validate date range format, chronological ordering, and compute working day count.
     *
     * @param string $startDate 'Y-m-d'
     * @param string $endDate   'Y-m-d'
     * @return array [
     *    'valid'        => bool,
     *    'working_days' => int,
     *    'total_days'   => int,
     *    'error'        => ?string
     * ]
     */
    public static function validateDateRange(string $startDate, string $endDate): array
    {
        try {
            $start = self::parseDate($startDate);
            $end = self::parseDate($endDate);
        } catch (InvalidArgumentException $e) {
            return [
                'valid'        => false,
                'working_days' => 0,
                'total_days'   => 0,
                'error'        => $e->getMessage(),
            ];
        }

        if ($end < $start) {
            return [
                'valid'        => false,
                'working_days' => 0,
                'total_days'   => 0,
                'error'        => 'Invalid chronological sequence: End date (' . $endDate . ') cannot precede start date (' . $startDate . ').',
            ];
        }

        $workingDays = 0;
        $totalDays = 0;
        $curr = $start;
        $oneDay = new DateInterval('P1D');

        while ($curr <= $end) {
            $totalDays++;
            $dayOfWeek = (int)$curr->format('N'); // 1 = Mon ... 7 = Sun
            if (in_array($dayOfWeek, self::WORKING_DAYS, true)) {
                $workingDays++;
            }
            $curr = $curr->add($oneDay);
        }

        return [
            'valid'        => true,
            'working_days' => $workingDays,
            'total_days'   => $totalDays,
            'error'        => null,
        ];
    }

    /**
     * Calculate inclusive calendar days (all Mon-Sun days, used for Maternity RA 11210 & SLW RA 9710).
     */
    public static function calculateCalendarDays(string $startDateStr, string $endDateStr): int
    {
        $start = self::parseDate($startDateStr);
        $end = self::parseDate($endDateStr);
        if ($end < $start) {
            throw new InvalidArgumentException("End date ({$endDateStr}) cannot precede start date ({$startDateStr}).");
        }
        return (int)$start->diff($end)->days + 1;
    }

    /**
     * Calculate leave duration in the requested entitlement unit ('working_days' | 'calendar_days').
     */
    public static function calculateDaysForUnit(string $startDateStr, string $endDateStr, string $unit = 'working_days'): int
    {
        return $unit === 'calendar_days'
            ? self::calculateCalendarDays($startDateStr, $endDateStr)
            : self::calculateWorkingDays($startDateStr, $endDateStr);
    }

    /**
     * Safely parse a date string into a DateTimeImmutable object at midnight UTC.
     *
     * @param string $dateStr
     * @return DateTimeImmutable
     * @throws InvalidArgumentException
     */
    private static function parseDate(string $dateStr): DateTimeImmutable
    {
        $trimmed = trim($dateStr);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Date string cannot be empty.');
        }

        // Strict Y-m-d format validation
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $trimmed);
        if (!$dt || $dt->format('Y-m-d') !== $trimmed) {
            throw new InvalidArgumentException("Invalid date format '{$dateStr}'. Required format: YYYY-MM-DD.");
        }

        return $dt;
    }
}
