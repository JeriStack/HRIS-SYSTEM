# Resource Ownership & Data Isolation Rules
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 2.0  
**Status:** Approved Security Specification (NFR-SEC-004)  

---

## 1. Requirement & Threat Model

In a multi-user Human Resources system, regular employees must **never** be able to view, tamper with, or access another employee's private personnel data, leave balances, or leave requests.

### Threat Scenarios Mitigated:
1. **Insecure Direct Object Reference (IDOR):** An attacker alters an ID parameter in the URL (e.g. changing `/employee/profile?emp_id=4` to `/employee/profile?emp_id=5`).
2. **Hidden Record Discovery:** Querying API endpoints without parameters expecting the backend to dump all records and filtering only on the frontend.
3. **Session Swapping / Spoofing:** Passing arbitrary employee numbers in POST bodies.

---

## 2. Server-Side Ownership Enforcement Rules

### Rule 1: Identity Anchoring
All employee self-service queries must anchor directly to `$_SESSION['user']['employee_id']`. The client-submitted ID is never trusted as the primary filter.

```php
// Secure Backend Pattern
$user = currentUser();
$myEmpId = $user['employee_id'] ?? null;

$stmt = $this->pdo->prepare("
    SELECT * FROM leave_balances 
    WHERE employee_id = :emp_id AND year = YEAR(CURDATE())
");
$stmt->execute(['emp_id' => $myEmpId]);
```

### Rule 2: Active Tampering Detection
If a client explicitly passes an identifier (via `GET['emp_id']`, `POST['employee_id']`, or URL route parameters), the backend validates whether the submitted identifier matches the authenticated session's `employee_id`.

```php
if (isset($_GET['emp_id'])) {
    $requestedEmpId = (int)$_GET['emp_id'];
    if ($requestedEmpId !== $myEmpId) {
        http_response_code(403);
        $this->view('errors.403', [
            'pageTitle' => '403 Forbidden - Ownership Violation',
            'message'   => 'Ownership Access Denied: You are not authorized to view another employee\'s private records (NFR-SEC-004).'
        ], null);
        exit;
    }
}
```

If the ID does not match, the application immediately halts and returns **HTTP 403 Forbidden**.

### Rule 3: Missing Employee Association Safety
If a user account with role `employee` is not linked to any record in the `employees` table (`users.employee_id IS NULL`), queries return empty sets or an explicit "No associated employee record found" state. The backend **never** defaults to returning the first record in the database.

---

## 3. Administrative and HR Access Exceptions

- Users with roles `admin` and `hr` possess authorized operational access to view employee records across the organization via the dedicated `/hr/employees` endpoints.
- However, they do so through the auditable `/hr/employees/{id}` route, which logs all record lookups and modifications in `activity_logs`.
