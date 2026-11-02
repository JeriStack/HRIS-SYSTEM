# Phase 8 Database Migration & Schema Notes

## 1. Migration Rationale

To fulfill the requirements of FR-LEAVE-009 (Rejection reason capture and auditing), the `leave_requests` table required a nullable text field to store the operational justification provided by an HR officer when declining a pending leave application.

---

## 2. Migration Execution

The migration was created in `database/migrations/2026_10_14_add_rejection_reason_to_leave_requests.sql` and safely executed on the development MariaDB database:

```sql
ALTER TABLE `leave_requests` 
ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT NULL AFTER `reason`;
```

### Verification Query:
```sql
SHOW COLUMNS FROM `leave_requests` LIKE 'rejection_reason';
```
Result:
- **Field:** `rejection_reason`
- **Type:** `text`
- **Null:** `YES`
- **Default:** `NULL`

---

## 3. Data Integrity & Backwards Compatibility

- **Non-Destructive:** The migration is fully non-destructive. All existing leave requests retain their status, reasons, and timestamps.
- **Nullable Default:** Because the column defaults to `NULL`, existing approved, pending, and cancelled records remain valid without data backfills.
- **Updated Baseline:** `database/schema.sql` was updated to include `rejection_reason TEXT NULL` so new database initializations contain the column automatically.

---

## 4. Rollback Procedure

If a rollback is ever needed in an emergency:
```sql
ALTER TABLE `leave_requests` DROP COLUMN `rejection_reason`;
```
*Note: Dropping the column will discard recorded rejection reasons while leaving all other leave request fields intact.*
