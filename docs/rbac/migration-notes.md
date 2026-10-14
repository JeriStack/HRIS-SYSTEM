# RBAC Migration & Database Baseline Notes
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Master RBAC Refactor & Production Implementation  

---

## 1. Schema Baseline Verification

The existing database schema in `database/schema.sql` already establishes the core requirements:

```sql
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NULL,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'hr', 'employee') NOT NULL DEFAULT 'employee',
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_users_username` UNIQUE (`username`),
    CONSTRAINT `uq_users_employee` UNIQUE (`employee_id`),
    CONSTRAINT `fk_users_employee` FOREIGN KEY (`employee_id`) 
        REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Key Schema Observations:
1. **Roles:** `users.role` ENUM natively contains `'admin'`, `'hr'`, and `'employee'`. No schema migration or ALTER TABLE was required.
2. **Account Status:** `users.status` ENUM natively contains `'Active'` and `'Inactive'`.
3. **Foreign Key Integrity:** `users.employee_id` maintains a strict `ON DELETE RESTRICT` constraint against `employees.id`, preserving relational integrity.
4. **Data Preservation:** Zero database resets, zero table drops, and zero record deletions were performed. All 7 pre-seeded users and 8 employees remain intact.

---

## 2. Default Seed Accounts for Local Testing

| Username | Role | Initial Password | Associated Employee | Operational Scope |
| :--- | :---: | :--- | :--- | :--- |
| `admin` | `admin` | `Admin@123` | EMP001 (Eleanor Vance) | System Administration & User Governance (`/admin/dashboard`) |
| `hr01` | `hr` | `Admin@123` | EMP002 (Marcus Tan) | HR Workforce Operations & Leave Reviews (`/hr/dashboard`) |
| `hr02` | `hr` | `Admin@123` | EMP003 (Sofia Reyes) | HR Workforce Operations & Leave Reviews (`/hr/dashboard`) |
| `emp004` | `employee` | `Employee@123` | EMP004 (David Cruz) | Employee Self-Service Portal (`/employee/dashboard`) |
| `emp005` | `employee` | `Employee@123` | EMP005 (Maria Santos) | Employee Self-Service Portal (`/employee/dashboard`) |
| `emp006` | `employee` | `Employee@123` | EMP006 (Juan Dela Cruz)| Employee Self-Service Portal (`/employee/dashboard`) |
| `emp007` | `employee` | `Employee@123` | EMP007 (Beatrice Gomez)| Employee Self-Service Portal (`/employee/dashboard`) |

---

## 3. Account Provisioning Guide

If deploying to a fresh environment without seed data, run:

```bash
# 1. Connect to MySQL / MariaDB
mysql -u root -p

# 2. Source database schema
source database/schema.sql;

# 3. Source baseline operational seed
source database/seed.sql;
```

To create a new administrator account via PHP CLI without exposing plaintext passwords:

```bash
php -r "
require 'core/bootstrap.php';
\$pdo = Core\Database::getInstance()->getConnection();
\$hash = password_hash('YourSecurePassword', PASSWORD_BCRYPT, ['cost' => 12]);
\$stmt = \$pdo->prepare('INSERT INTO users (username, password, role, status) VALUES (:u, :p, \"admin\", \"Active\")');
\$stmt->execute(['u' => 'super_admin', 'p' => \$hash]);
echo 'Admin account created successfully!\n';
"
```
