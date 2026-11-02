# Phase 7: Server-Side Validation Rules & Integrity Matrix

**System:** Employee HRIS & Leave Management System  
**Module:** Core HRIS Validation & Data Integrity  
**Status:** Implemented & Verified (100% Automated Test Pass Rate)  

---

## 1. Validation Philosophy & Strategy

1. **Server-Side Primacy:** While modern HTML5 form attributes provide immediate browser UX feedback, all validation rules are enforced strictly in PHP before executing any database operations.
2. **Defensive Sanitization:** Incoming input is trimmed, cast, and coalesced with default fallbacks before evaluation.
3. **Database as Final Safeguard:** Application logic prevents invalid submissions, while underlying MySQL table constraints (`UNIQUE`, `NOT NULL`, `FOREIGN KEY`, `ON DELETE RESTRICT`) guarantee data integrity even under concurrent transactions.
4. **State Retention:** When a validation error occurs, previous non-sensitive input is flashed to session (`old`) so the user does not need to retype the entire form.

---

## 2. Employee Validation Matrix

| Field | Rule Type | Specification / Constraint | Error Feedback Message |
|---|---|---|---|
| `employee_no` | Required | Cannot be empty or whitespace | "Employee number is required." |
| `employee_no` | Max Length | `mb_strlen($val) <= 30` | "Employee number cannot exceed 30 characters." |
| `employee_no` | Uniqueness | Unique in `employees` table (excludes current ID on edit) | "Employee number ':no' is already assigned to another employee." |
| `first_name` | Required | Cannot be empty or whitespace | "First name is required." |
| `first_name` | Max Length | `mb_strlen($val) <= 50` | "First name cannot exceed 50 characters." |
| `middle_name` | Optional | If provided, `mb_strlen($val) <= 50` | "Middle name cannot exceed 50 characters." |
| `last_name` | Required | Cannot be empty or whitespace | "Last name is required." |
| `last_name` | Max Length | `mb_strlen($val) <= 50` | "Last name cannot exceed 50 characters." |
| `email` | Required | Cannot be empty or whitespace | "Email address is required." |
| `email` | Syntax | Validated via `filter_var(..., FILTER_VALIDATE_EMAIL)` | "Please enter a valid email address." |
| `email` | Max Length | `mb_strlen($val) <= 100` | "Email cannot exceed 100 characters." |
| `email` | Uniqueness | Unique in `employees` table (excludes current ID on edit) | "Email address ':email' is already registered to another employee." |
| `phone` | Optional | If provided, `mb_strlen($val) <= 25` | "Phone number cannot exceed 25 characters." |
| `birth_date` | Required | Cannot be empty, format must be `YYYY-MM-DD` | "Birth date is required." / "Invalid date format (must be YYYY-MM-DD)." |
| `birth_date` | Past Date | Must be `<= today` | "Birth date cannot be in the future." |
| `gender` | Whitelist | Must be in `['Male', 'Female', 'Other']` | "Please select a valid gender option." |
| `department_id` | Foreign Key | Must reference an existing record in `departments` | "Please select a valid department." |
| `position_id` | Foreign Key | Must reference an existing record in `positions` | "Please select a valid job position." |
| `position_id` | Alignment | Position's `department_id` must match selected `department_id` | "The selected position does not belong to the selected department." |
| `date_hired` | Required | Valid `YYYY-MM-DD` date | "Date hired is required." / "Invalid date format (must be YYYY-MM-DD)." |
| `date_hired` | Past / Present | Hire date cannot be in the future (`<= today`) | "Date hired cannot be in the future." |
| `employment_status` | Domain | Restricted strictly to `['Active', 'Inactive']` | "Please select a valid employment status (Active or Inactive)." |

---

## 3. Department Validation Matrix

| Field | Rule Type | Specification / Constraint | Error Feedback Message |
|---|---|---|---|
| `department_name` | Required | Cannot be empty or whitespace | "Department name is required." |
| `department_name` | Max Length | `mb_strlen($val) <= 100` | "Department name cannot exceed 100 characters." |
| `department_name` | Uniqueness | Case-insensitive global uniqueness (`LOWER(TRIM(name))`) | "A department with this name already exists." |
| `description` | Max Length | `mb_strlen($val) <= 1000` | "Description cannot exceed 1000 characters." |

---

## 4. Position Validation Matrix

| Field | Rule Type | Specification / Constraint | Error Feedback Message |
|---|---|---|---|
| `position_name` | Required | Cannot be empty or whitespace | "Position name is required." |
| `position_name` | Max Length | `mb_strlen($val) <= 100` | "Position name cannot exceed 100 characters." |
| `department_id` | Foreign Key | Must reference an existing record in `departments` | "Please select a valid department." |
| `position_name` | Uniqueness | Department-scoped uniqueness (`UNIQUE(department_id, position_name)`) | "A position with this name already exists in the selected department." |
| `description` | Max Length | `mb_strlen($val) <= 1000` | "Description cannot exceed 1000 characters." |
