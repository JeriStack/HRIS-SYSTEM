# Database Installation and Maintenance Guide
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Database Name:** `hris_db`  
**Storage Engine:** InnoDB  
**Character Set:** `utf8mb4`  
**Collation:** `utf8mb4_unicode_ci`  
**Target Environment:** MySQL 8.0+ / MariaDB 10.4+ (under XAMPP on Windows)  

---

## 1. Quick Setup via MySQL CLI

Open PowerShell or Windows Command Prompt and execute the following commands using the XAMPP MySQL client:

### Step 1: Create the Database
```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS hris_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Step 2: Import the Schema DDL
```powershell
C:\xampp\mysql\bin\mysql.exe -u root hris_db < database/schema.sql
```

### Step 3: Import the Baseline Seed Data
```powershell
C:\xampp\mysql\bin\mysql.exe -u root hris_db < database/seed.sql
```

### Step 4: Verify Database Tables
```powershell
C:\xampp\mysql\bin\mysql.exe -u root hris_db -e "SHOW TABLES;"
```

---

## 2. Setup via phpMyAdmin GUI

1. Open your browser and navigate to `http://localhost/phpmyadmin`.
2. Click **New** in the left sidebar to create a database.
3. Enter **Database name:** `hris_db` and select collation **`utf8mb4_unicode_ci`**, then click **Create**.
4. Select the newly created `hris_db` database.
5. Navigate to the **Import** tab.
6. Click **Choose File**, select `database/schema.sql`, and click **Import** (or **Go**).
7. Once schema tables are created, repeat the import step with `database/seed.sql`.

---

## 3. Seed Accounts & Credentials

The seed data creates development-only user accounts with pre-hashed BCRYPT credentials:

| Username | Role | Linked Employee | Password (Development Only) |
| :--- | :--- | :--- | :--- |
| `admin` | `admin` | Eleanor Vance (EMP001) | `Admin@123` |
| `hr01` | `hr` | Marcus Tan (EMP002) | `Admin@123` |
| `hr02` | `hr` | Sofia Reyes (EMP003) | `Admin@123` |
| `emp004` | `employee` | David Cruz (EMP004) | `Employee@123` |
| `emp005` | `employee` | Maria Santos (EMP005) | `Employee@123` |
| `emp006` | `employee` | Juan Dela Cruz (EMP006) | `Employee@123` |
| `emp007` | `employee` | Beatrice Gomez (EMP007) | `Employee@123` |

> **Security Notice:** Never use these default demo passwords in production environments.

---

## 4. How to Safely Reset the Database

If test data becomes corrupted or needs a clean wipe during testing:

```powershell
# Drop and recreate strictly 'hris_db' (never drop other databases)
C:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE IF EXISTS hris_db; CREATE DATABASE hris_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Re-import schema and seed data
C:\xampp\mysql\bin\mysql.exe -u root hris_db < database/schema.sql
C:\xampp\mysql\bin\mysql.exe -u root hris_db < database/seed.sql
```

---

## 5. Troubleshooting Common Import Issues

1. **Foreign Key Constraint Errors (`Error 1215 / 1452`):**
   * Ensure `database/schema.sql` runs with `SET FOREIGN_KEY_CHECKS = 0;` at the beginning and `SET FOREIGN_KEY_CHECKS = 1;` at the end so tables can be generated in clean dependency order.
2. **Access Denied for Root:**
   * If your XAMPP MySQL root has a password, append `-p` to the command line:
     `C:\xampp\mysql\bin\mysql.exe -u root -p hris_db < database/schema.sql`
3. **Collation Incompatibility:**
   * If running on an older MariaDB version that rejects `utf8mb4_unicode_ci`, switch collation to `utf8mb4_general_ci` in `schema.sql`.
