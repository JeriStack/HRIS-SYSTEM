# Leave-Day Calculation Service

## 1. Architectural Design

Leave day calculations are centralized within the reusable service `App\Services\LeaveCalculator` (`app/services/LeaveCalculator.php`). Controllers and background jobs must not duplicate date arithmetic or rely on client-side values.

---

## 2. Working Day Assumptions & Rules

1. **Working Days (Monday through Friday):**
   - Monday (1), Tuesday (2), Wednesday (3), Thursday (4), and Friday (5) per ISO-8601 day of week are counted as working business days.
2. **Weekend Exclusion (Saturday and Sunday):**
   - Saturday (6) and Sunday (7) are excluded from the leave-day count.
3. **Inclusive Counting:**
   - Both start and end dates are evaluated inclusively.
4. **Zero Working Day Ranges:**
   - If a range falls completely on a weekend (e.g., Saturday to Sunday), the calculation yields `0` working days.
   - The application filing rule rejects requests with `0` working days.
5. **Statutory Public Holidays (Pending Business Decision):**
   - Consistent with Section 7 of the specification, public holiday calendars are not artificially hardcoded. Public-holiday handling is formally documented as a pending corporate business-policy decision, and calendar weekdays are evaluated per the standard Monday–Friday policy.

---

## 3. Mathematical & Deterministic Test Cases

| Scenario | Start Date | End Date | Calendar Days | Working Days | Expected Result |
|---|---|---|---|---|---|
| Same-day Monday | `2026-10-12` | `2026-10-12` | 1 | 1 | Valid (1 working day) |
| Standard Mon–Fri week | `2026-10-12` | `2026-10-16` | 5 | 5 | Valid (5 working days) |
| Weekend spanning | `2026-10-16` (Fri) | `2026-10-19` (Mon) | 4 | 2 | Valid (Fri + Mon = 2 days) |
| Weekend only | `2026-10-17` (Sat) | `2026-10-18` (Sun) | 2 | 0 | Rejected (0 working days) |
| Inverted chronological | `2026-10-20` | `2026-10-15` | N/A | 0 | Rejected (End < Start) |
| Year-end boundary | `2025-12-31` (Wed) | `2026-01-02` (Fri) | 3 | 3 | Valid (3 working days) |
| Leap year February | `2024-02-28` (Wed) | `2024-03-01` (Fri) | 3 | 3 | Valid (Wed, Thu 29th, Fri 1st = 3 days) |
| Non-leap invalid date | `2026-02-30` | `2026-03-01` | N/A | 0 | Rejected (Invalid date string) |

---

## 4. API Reference

### `LeaveCalculator::calculateWorkingDays(string $startDate, string $endDate): int`
Calculates and returns the exact count of working days. Throws `InvalidArgumentException` if date format is invalid or end precedes start.

### `LeaveCalculator::validateDateRange(string $startDate, string $endDate): array`
Validates date strings and sequence. Returns:
```php
[
    'valid'        => bool,
    'working_days' => int,
    'total_days'   => int,
    'error'        => ?string
]
```

### `LeaveCalculator::isWorkingDay(string $date): bool`
Returns `true` if the specified date falls on Monday through Friday, `false` otherwise.
