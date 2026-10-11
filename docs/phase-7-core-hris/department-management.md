# Phase 7: Department Management Module Specification

**System:** Employee HRIS & Leave Management System  
**Module:** Core HRIS — Department Management  
**Status:** Implemented & Verified (100% Automated Test Pass Rate)  
**Primary Technologies:** PHP 8.x, MySQL/MariaDB (PDO Prepared Statements), HTML5, CSS3, Vanilla JavaScript  

---

## 1. Overview and Architecture

The Department Management module manages the organizational hierarchy of the institution. It allows authorized HR Administrators to create, review, and modify organizational departments while maintaining strict referential integrity with dependent positions and employee records.

The module follows the custom MVC architecture:
- **Model:** [`app/models/Department.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/models/Department.php)
- **Controller:** [`app/controllers/DepartmentController.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/controllers/DepartmentController.php)
- **Views:**
  - Directory: [`app/views/departments/index.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/views/departments/index.php)
  - Add Form: [`app/views/departments/create.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/views/departments/create.php)
  - Edit Form: [`app/views/departments/edit.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/views/departments/edit.php)
- **Layout:** [`app/views/layouts/hr.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/views/layouts/hr.php)

---

## 2. Capabilities & Operations

### 2.1 View Department Directory (`GET /hr/departments`)
- Renders summary metric cards: **Total Departments**, **Total Job Positions**, and **Assigned Staff**.
- Displays a structured data table containing:
  - Department Name
  - Department Description
  - Position Count (aggregated via SQL subquery: `SELECT COUNT(*) FROM positions WHERE department_id = d.id`)
  - Employee Count (aggregated via SQL subquery: `SELECT COUNT(*) FROM employees WHERE department_id = d.id`)
  - Creation Timestamp
  - Action Controls: Edit Department
- Displays empty states when no departments are configured.

### 2.2 Add Department (`GET /hr/departments/create`, `POST /hr/departments`)
- Form inputs:
  - Department Name (required, max 100 characters)
  - Description (optional, max 1000 characters)
- Server-side validation enforces unique naming:
  - Case-insensitive comparison: `LOWER(TRIM(department_name)) = LOWER(:name)`
  - Returns clear feedback if the department name already exists.
- State-changing `POST` is protected by CSRF middleware and role middleware (`admin`, `hr`).
- On successful insertion: Logs `DEPARTMENT_CREATED` in `activity_logs`, sets flash message, and redirects to `/hr/departments`.

### 2.3 Edit Department (`GET /hr/departments/{id}/edit`, `POST /hr/departments/{id}/edit`)
- Prepopulates form with the existing department details.
- Validates changed values while safely excluding the department's own ID from uniqueness checks (`id != :id`).
- Referential integrity preservation:
  - Editing department metadata preserves foreign key relations in dependent positions and employees.
  - No orphaned records are created.
- On successful update: Logs `DEPARTMENT_UPDATED` in `activity_logs`, flashes success message, and redirects to `/hr/departments`.

---

## 3. Database Integrity & Constraints

- **Table:** `departments`
- **Primary Key:** `id INT UNSIGNED AUTO_INCREMENT`
- **Unique Constraint:** `department_name UNIQUE`
- **Foreign Key Inbound Constraints:**
  - `positions.department_id REFERENCES departments(id) ON DELETE RESTRICT`
  - `employees.department_id REFERENCES departments(id) ON DELETE RESTRICT`
- **Deletion Policy:** Ordinary delete operations are omitted to guarantee historical referential integrity with past employee rosters and leave records.
