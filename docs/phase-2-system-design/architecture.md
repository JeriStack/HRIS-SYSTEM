# Multi-Layer System Architecture Specification
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 2 — System Analysis and Design  
**Date:** 2026-10-11  
**Status:** Approved for Baseline Design  

---

## 1. Architectural Pattern & Design Principles

The application is structured according to a strict **4-Tier Layered Architecture** adhering to **Model-View-Controller (MVC)** principles. This separation guarantees high maintainability, clear security boundaries, and modular isolation of the custom Data Structures and Algorithms (DSA) component from persistent storage.

```
       +-------------------------------------------------------------+
       |                         END USERS                           |
       |             [Administrator / HR]     [Employee]             |
       +------------------------------+------------------------------+
                                      | HTTP Requests (GET, POST)
                                      v
+===========================================================================+
| 1. PRESENTATION LAYER                                                     |
|    - HTML5 Semantic Views, CSS3 Design System, Vanilla JavaScript         |
|    - Layouts (main.php, auth.php), Partials (navbar, sidebar, footer)     |
|    - Responsive Dashboard Widgets, Modal Dialogs, Alert Notifications     |
+=====================================+=====================================+
                                      | Controller Invocations
                                      v
+===========================================================================+
| 2. APPLICATION LAYER (MVC Controllers, Middleware & DSA Engine)          |
|    - Front Controller (public/index.php) & Core Router (core/Router.php)  |
|    - Security Middleware (Auth Guard, Role Guard, CSRF, Session Timeout)  |
|    - Domain Controllers (Auth, Employee, Department, Position, Leave)     |
|    - Custom In-Memory DSA Engine (app/dsa/structures, app/dsa/algorithms) |
|    - Transaction Coordinators & Business Rule Validators                  |
+=====================================+=====================================+
                                      | Data Mapping & Entity Operations
                                      v
+===========================================================================+
| 3. DATA ACCESS LAYER (PDO & Base Model)                                   |
|    - Centralized PDO Factory (core/Database.php) with Persistent Pooling  |
|    - Base Model Abstraction (core/Model.php)                              |
|    - Parameterized Query Execution & Prepared Statements                  |
|    - Transaction Boundaries (beginTransaction, commit, rollBack)          |
+=====================================+=====================================+
                                      | SQL Commands & Row Locks
                                      v
+===========================================================================+
| 4. DATABASE LAYER (MySQL / MariaDB)                                       |
|    - Persistent Relational Schemas (employees, leaves, users, logs)       |
|    - Declarative Constraints (FOREIGN KEY ON DELETE RESTRICT, UNIQUE)    |
|    - Relational Indices for Optimized $O(1)$ and $O(\log N)$ Retrieval    |
|    - ACID-Compliant Transaction Storage Engine (InnoDB)                   |
+===========================================================================+
```

---

## 2. Layer Responsibilities & Component Boundaries

### 2.1 Presentation Layer
* **Directory Root:** `app/views/` and `public/assets/`
* **Core Responsibilities:**
  * Render HTML views using server-side PHP templates.
  * Enforce visual design tokens via vanilla CSS (curated modern color palette, clean typography, responsive flex/grid layouts).
  * Render user notifications (Green success banners, Yellow warning notices, Red validation errors, confirmation modals for destructive operations).
  * Present interactive algorithm visualizers and benchmark charts.
* **Security Principle:** The Presentation Layer **never** makes authorization decisions. Hiding an action button in a view is purely a usability enhancement; backend authorization guards are mandatory on all endpoints.

### 2.2 Application Layer
* **Directory Root:** `app/controllers/`, `core/`, `app/dsa/`
* **Core Responsibilities:**
  * **Routing & Dispatching:** `Router.php` parses incoming URIs and dispatches requests to appropriate Controller actions.
  * **Middleware Interception:** Intercepts every incoming request to verify authentication, enforce role-based access control (`admin`, `hr`, `employee`), validate CSRF tokens, and monitor session timeouts.
  * **Business Rule Validation:** Executes domain rules (e.g., date ranges, working-day calculations, balance sufficiency, collision detection).
  * **Custom In-Memory DSA Operations:** Retrieves database entities via PDO and populates custom PHP data structures (`ArrayWrapper`, `LinkedList`, `Stack`, `Queue`, `HashTable`, `BinarySearchTree`) to perform search, sort, traversal, and benchmarking algorithms in application memory.

### 2.3 Data Access Layer
* **Directory Root:** `core/Database.php`, `core/Model.php`, `app/models/`
* **Core Responsibilities:**
  * Encapsulate all database communications using PDO.
  * Guarantee parameterized queries to eliminate SQL injection vulnerabilities.
  * Manage database transactions across multi-table operations (e.g., atomic leave approval, balance deduction, activity log creation, and notification dispatch).
  * Transform raw associative array result sets into structured entity domain models.

### 2.4 Database Layer
* **Technology:** MySQL 8.x / MariaDB 10.4+ (InnoDB Engine)
* **Core Responsibilities:**
  * Durable storage for users, employees, departments, positions, leave types, balances, requests, activity logs, and notifications.
  * Guarantee relational integrity using foreign keys with `ON DELETE RESTRICT` to prevent orphaned records.
  * Enforce entity uniqueness (`employee_no`, `username`, `email`).
  * Support ACID transaction guarantees (Atomicity, Consistency, Isolation, Durability) for leave state changes.

---

## 3. Communication Contracts Between Layers

1. **Downstream Flow (Request):**
   * Browser submits HTTP request to `public/index.php`.
   * `Router.php` dispatches through `Middleware.php` to Controller.
   * Controller validates input format and requests domain logic from Model or DSA Engine.
   * Model accesses `core/Database.php` PDO instance to execute prepared queries.
2. **Upstream Flow (Response):**
   * MySQL returns raw result sets to PDO.
   * Model returns domain entity objects or sanitized associative arrays to Controller.
   * Controller passes data into view templates (`Controller::view($template, $data)`).
   * Presentation layer escapes untrusted variables via `htmlspecialchars()` and streams HTML to browser.
