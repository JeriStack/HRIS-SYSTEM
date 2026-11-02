# Phase 7: Position Management Module Specification

**System:** Employee HRIS & Leave Management System  
**Module:** Core HRIS — Position Management  
**Status:** Implemented & Verified (100% Automated Test Pass Rate)  
**Primary Technologies:** PHP 8.x, MySQL/MariaDB (PDO Prepared Statements), HTML5, CSS3, Vanilla JavaScript  

---

## 1. Overview and Architecture

The Position Management module governs job classifications and organizational titles across the institution. Every position is formally bound to a governing department, establishing an unambiguous relationship between employee job titles and organizational units.

The module follows the custom MVC architecture:
- **Model:** [`app/models/Position.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/models/Position.php)
- **Controller:** [`app/controllers/PositionController.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/controllers/PositionController.php)
- **Views:**
  - Directory: [`app/views/positions/index.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/views/positions/index.php)
  - Add Form: [`app/views/positions/create.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/views/positions/create.php)
  - Edit Form: [`app/views/positions/edit.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/views/positions/edit.php)
- **Layout:** [`app/views/layouts/hr.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/views/layouts/hr.php)

---

## 2. Capabilities & Operations

### 2.1 View Position Directory (`GET /hr/positions`)
- Displays organizational metric cards: **Total Job Titles**, **Associated Departments**, and **Employed Personnel**.
- Features department-filtering controls allowing HR to filter positions by parent department or view all.
- Displays a structured data table containing:
  - Job Position Title
  - Associated Department (with visual pill indicator)
  - Job Description
  - Active Staff Headcount (aggregated via SQL subquery: `SELECT COUNT(*) FROM employees WHERE position_id = p.id`)
  - Created Date
  - Action Controls: Edit Position

### 2.2 Add Position (`GET /hr/positions/create`, `POST /hr/positions`)
- Fields:
  - Position Name (required, max 100 characters)
  - Department (required, select from existing active departments)
  - Description (optional, max 1000 characters)
- Uniqueness Rule Enforcement:
  - Uniqueness is strictly **department-scoped** according to schema constraint `UNIQUE (department_id, position_name)`:
    ```sql
    SELECT COUNT(*) FROM positions 
    WHERE LOWER(TRIM(position_name)) = LOWER(:name) 
      AND department_id = :dept_id
    ```
  - Identical position titles across different departments are allowed (e.g., "Team Lead" can exist independently in both IT and HR).
  - Duplicate titles within the same department are rejected with clear error feedback.
- On success: Logs `POSITION_CREATED` in `activity_logs`, sets flash message, and redirects to `/hr/positions`.

### 2.3 Edit Position (`GET /hr/positions/{id}/edit`, `POST /hr/positions/{id}/edit`)
- Prepopulates form with the position's current title, department affiliation, and description.
- Enforces department-scoped uniqueness check excluding self (`id != :id`).
- Verifies that the selected department exists before updating.
- Preserves referential integrity for all employees holding this position.
- On success: Logs `POSITION_UPDATED` in `activity_logs`, flashes success message, and redirects to `/hr/positions`.

### 2.4 Dynamic Department Position API (`GET /hr/api/positions-by-department/{id}`)
- Lightweight RESTful endpoint returning JSON array of positions belonging to the requested department.
- Consumed by client-side cascade dropdowns in employee forms.
- Protected by `AuthMiddleware` and `RoleMiddleware(['admin', 'hr'])`.

---

## 3. Database Integrity & Constraints

- **Table:** `positions`
- **Primary Key:** `id INT UNSIGNED AUTO_INCREMENT`
- **Unique Constraint:** `UNIQUE KEY uq_dept_position (department_id, position_name)`
- **Foreign Keys:**
  - `department_id REFERENCES departments(id) ON DELETE RESTRICT`
  - Inbound: `employees.position_id REFERENCES positions(id) ON DELETE RESTRICT`
