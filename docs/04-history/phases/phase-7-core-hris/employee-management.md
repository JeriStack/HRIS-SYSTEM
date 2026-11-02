# Phase 7: Employee Management Module Specification

**System:** Employee HRIS & Leave Management System  
**Module:** Core HRIS — Employee Management  
**Status:** Implemented & Verified (100% Automated Test Pass Rate)  
**Primary Technologies:** PHP 8.x, MySQL/MariaDB (PDO Prepared Statements), HTML5, CSS3, Vanilla JavaScript  

---

## 1. Overview and Architecture

The Employee Management module serves as the primary workforce registry of the HRIS. It empowers authorized Human Resources and System Administrators to manage employee demographic records, organizational affiliations, employment statuses, and lifecycle transitions without direct database manipulation.

The module follows the established custom MVC architectural pattern:
- **Model:** [`app/models/Employee.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/models/Employee.php)
- **Controller:** [`app/controllers/EmployeeController.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/controllers/EmployeeController.php)
- **Views:**
  - Directory: [`app/views/employees/index.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/views/employees/index.php)
  - Profile: [`app/views/employees/show.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/views/employees/show.php)
  - Add Form: [`app/views/employees/create.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/views/employees/create.php)
  - Edit Form: [`app/views/employees/edit.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/views/employees/edit.php)
- **Layout:** [`app/views/layouts/hr.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/views/layouts/hr.php)

---

## 2. Capabilities & Operations

### 2.1 View Employee Directory (`GET /hr/employees`)
- Renders workforce summary cards: **Total Workforce**, **Active Employees**, and **Inactive / On Leave**.
- Features an integrated filtering hub:
  - Text search: parameterized SQL matching across `employee_no`, `first_name`, `last_name`, and `email`.
  - Status filter: `Active`, `Inactive`.
  - Department filter: dropdown populated with existing departments.
- Renders a responsive data table detailing:
  - Employee Number (Badge)
  - Full Name & Gender
  - Work Email & Phone
  - Department
  - Position
  - Employment Status badge (`Active` in emerald green, `Inactive` in crimson red)
  - Date Hired
  - Action Controls: View Profile, Edit, Deactivate (with confirmation modal)
- Includes dedicated Empty States and Zero-Search-Result indicators with reset action.

### 2.2 View Individual Employee Profile (`GET /hr/employees/{id}`)
- Presents structured information organized into clear sections:
  1. **Personal & Demographic Information**:
     - Full Name (First, Middle, Last)
     - Employee Number
     - Email Address
     - Contact Phone
     - Residential Address
     - Birth Date & Computed Age
     - Gender
  2. **Employment & Organizational Placement**:
     - Assigned Department
     - Job Position Title
     - Date Hired & Computed Tenure (Years / Months)
     - Employment Status Badge
  3. **Record Metadata**:
     - Profile Creation Timestamp (`created_at`)
     - Last Profile Update Timestamp (`updated_at`)
- Navigation actions: Back to Directory, Edit Record, Deactivate Record (if Active).
- Security rule: Never reveals sensitive system data (such as login password hashes or session tokens).

### 2.3 Add Employee (`GET /hr/employees/create`, `POST /hr/employees`)
- Two-section form capturing all approved schema fields:
  - Demographic & Personal information.
  - Organizational & Employment information.
- Dynamic department-to-position cascade:
  - Choosing a Department filters the Position dropdown instantly via vanilla JavaScript using cached `data-dept` attributes.
  - Also supported by a background API endpoint: `GET /hr/api/positions-by-department/{id}`.
- Server-side validation before any database insertion.
- On success: Records audit event `EMPLOYEE_CREATED` in `activity_logs`, sets flash notification, and redirects to the new profile `GET /hr/employees/{id}`.

### 2.4 Edit Employee (`GET /hr/employees/{id}/edit`, `POST /hr/employees/{id}/edit`)
- Prepopulates form with existing database values or previous invalid submission (`old`).
- Allows editing demographic fields, contact information, department, position, date hired, and employment status.
- Uniqueness validation excludes the employee's own record ID (`id != :id`), permitting saving with the same employee number and email.
- On success: Logs `EMPLOYEE_UPDATED` in `activity_logs`, flashes success message, and redirects to the profile.

### 2.5 Soft Deactivation (`POST /hr/employees/{id}/deactivate`)
- Deactivation is executed as a **soft status update**, strictly avoiding destructive SQL `DELETE`:
  ```sql
  UPDATE employees SET employment_status = 'Inactive' WHERE id = :id;
  UPDATE users SET status = 'Inactive' WHERE employee_id = :emp_id;
  ```
- **Historical Integrity:**
  - Preserves employee identity, department/position links, leave balances, leave history, and audit log trails intact.
  - Deactivates the linked login account in `users` (if present) to prevent unauthorized portal access.
  - Requires user confirmation via modal dialog before submitting POST.
  - Protected by CSRF token verification and RBAC (`admin`, `hr`).
  - Idempotent and safe: Attempting deactivation on an already-inactive record safely rejects the redundant action.

---

## 3. Database Integrity & Constraints

- **Table:** `employees`
- **Primary Key:** `id INT UNSIGNED AUTO_INCREMENT`
- **Unique Constraints:** `employee_no UNIQUE`, `email UNIQUE`
- **Foreign Keys:**
  - `department_id REFERENCES departments(id) ON DELETE RESTRICT`
  - `position_id REFERENCES positions(id) ON DELETE RESTRICT`
- **Status Domain:** `ENUM('Active', 'Inactive')`
