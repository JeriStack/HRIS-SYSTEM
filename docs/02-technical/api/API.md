# NEXORA HR — REST API v1 Specification & Reference

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.2  
**Status:** Authoritative API Reference  
**Date:** November 2026 (ADR-012 Three-Role API Authorization)  
**Document Owner:** API Engineering & Integration Team  

---

## 1. Overview & Architectural Conventions

NEXORA HR provides an enterprise REST API v1 for programmatic workforce operations, employee self-service, leave processing, and real-time event distribution.

* **Base URL:** `http://localhost:8080/api/v1` (Production: `https://your-domain.com/api/v1`)
* **Transport:** HTTPS mandatory in production; HTTP allowed in local development.
* **Content Type:** `application/json; charset=utf-8` on all request bodies and responses.
* **Interactive Documentation:** Integrated Swagger UI accessible at `/docs` or `/api/docs`.
* **Machine-Readable Specifications:**
  * YAML: [`docs/02-technical/api/openapi.yaml`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/02-technical/api/openapi.yaml)
  * JSON: [`docs/02-technical/api/openapi.json`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/02-technical/api/openapi.json)

---

## 2. Authentication & Headers

API requests authenticate using session cookies established during primary login and MFA verification.

### Mandatory Request Headers
| Header | Value | Description |
|---|---|---|
| `Accept` | `application/json` | Mandates JSON response format from controllers. |
| `Content-Type` | `application/json` | Required on `POST`, `PUT`, `PATCH` requests with bodies. |
| `X-CSRF-TOKEN` | `<token>` | Required on all state-modifying requests (`POST`, `PUT`, `DELETE`). |
| `Cookie` | `PHPSESSID=<id>` | Authenticated session cookie. |

---

## 3. Standard HTTP Status & Error Envelope

All API responses follow a structured JSON response envelope:

### Successful Response
```json
{
  "status": "success",
  "data": { ... }
}
```

### Error Response
```json
{
  "status": "error",
  "code": 400,
  "message": "Descriptive human-readable error message.",
  "errors": {
    "field_name": ["Specific validation error explanation."]
  }
}
```

### Standard Status Codes
* `200 OK`: Request succeeded.
* `201 Created`: Resource successfully created.
* `400 Bad Request`: Invalid payload or missing parameters.
* `401 Unauthorized`: Session missing or expired.
* `403 Forbidden`: Insufficient role permissions or ownership violation.
* `404 Not Found`: Target resource does not exist.
* `419 Page Expired`: CSRF token invalid or missing.
* `422 Unprocessable Entity`: Business validation failure (e.g., insufficient leave balance).
* `429 Too Many Requests`: Rate limit threshold exceeded.
* `500 Internal Server Error`: Unexpected server exception.

---

## 4. Endpoints Catalog

### 4.1 System & Authentication

#### Health Check
* **`GET /api/v1/health`**
* **Access:** Public
* **Response:**
  ```json
  {
    "status": "healthy",
    "database": "connected",
    "timestamp": "2026-11-02T13:50:00Z"
  }
  ```

#### Fetch CSRF Token
* **`GET /api/v1/auth/csrf`**
* **Access:** Public
* **Response:**
  ```json
  {
    "csrf_token": "a1b2c3d4..."
  }
  ```

#### Primary Login
* **`POST /api/v1/auth/login`**
* **Access:** Public
* **Payload:**
  ```json
  {
    "username": "jeril.estin",
    "password": "Password@123"
  }
  ```
* **Response (Step 1 Complete):**
  ```json
  {
    "status": "mfa_required",
    "message": "OTP has been dispatched to your registered email."
  }
  ```

#### Verify OTP (Complete MFA)
* **`POST /api/v1/auth/verify-otp`**
* **Access:** Partial Session
* **Payload:**
  ```json
  {
    "otp": "123456"
  }
  ```
* **Response:**
  ```json
  {
    "status": "success",
    "role": "employee",
    "redirect": "/employee/dashboard"
  }
  ```

#### Logout
* **`POST /api/v1/auth/logout`**
* **Access:** Authenticated (All Roles)

---

### 4.2 Profile & Notifications

#### Current User Profile
* **`GET /api/v1/profile`**
* **Access:** Authenticated (All Roles)

#### Notifications List
* **`GET /api/v1/notifications?limit=10`**
* **Access:** Authenticated (All Roles)

#### Mark Notification as Read
* **`POST /api/v1/notifications/{id}/read`**
* **Access:** Authenticated (All Roles)

#### Mark All Notifications as Read
* **`POST /api/v1/notifications/mark-all-read`**
* **Access:** Authenticated (All Roles)

---

### 4.3 Workforce & Organizational Management (Admin & HR)

#### List Employees
* **`GET /api/v1/employees?search=Jeril&department_id=1&status=Active`**
* **Access:** Admin & HR (`admin`, `hr`)

#### Show Employee Details
* **`GET /api/v1/employees/{id}`**
* **Access:** Admin & HR (`admin`, `hr`)

#### Create Employee
* **`POST /api/v1/employees`**
* **Access:** Admin & HR (`admin`, `hr`)
* **Payload:**
  ```json
  {
    "employee_no": "EMP015",
    "first_name": "Eleanor",
    "last_name": "Vance",
    "email": "eleanor.vance@company.local",
    "birth_date": "1994-05-12",
    "gender": "Female",
    "department_id": 2,
    "position_id": 4,
    "date_hired": "2026-11-01"
  }
  ```

#### Update Employee Record
* **`PUT /api/v1/employees/{id}`**
* **Access:** Admin & HR (`admin`, `hr`)

#### Deactivate Employee
* **`POST /api/v1/employees/{id}/deactivate`**
* **Access:** Admin & HR (`admin`, `hr`)

#### Reactivate Employee
* **`POST /api/v1/employees/{id}/reactivate`**
* **Access:** Admin & HR (`admin`, `hr`)

#### List Departments / Positions
* **`GET /api/v1/departments`**
* **`GET /api/v1/positions`**
* **Access:** Authenticated (All Roles)

---

### 4.4 Leave Entitlements & Transactions

#### Employee Own Leave Balances
* **`GET /api/v1/leave-balances/my`**
* **Access:** Employee Only (`employee`)

#### Submit Leave Request
* **`POST /api/v1/leaves`**
* **Access:** Employee Only (`employee`)
* **Payload:**
  ```json
  {
    "leave_type_id": 1,
    "start_date": "2026-11-10",
    "end_date": "2026-11-12",
    "reason": "Family vacation"
  }
  ```

#### Cancel Leave Request
* **`POST /api/v1/leaves/{id}/cancel`**
* **Access:** Employee Only (Owner)

#### HR Leave Approval Queue (FIFO)
* **`GET /api/v1/hr/leaves?status=Pending`**
* **Access:** Admin & HR (`admin`, `hr`)

#### Approve Leave Request (Pessimistic Locking)
* **`POST /api/v1/hr/leaves/{id}/approve`**
* **Access:** Admin & HR (`admin`, `hr`)

#### Reject Leave Request
* **`POST /api/v1/hr/leaves/{id}/reject`**
* **Access:** Admin & HR (`admin`, `hr`)
* **Payload:**
  ```json
  {
    "rejection_reason": "Department peak coverage requirement."
  }
  ```

---

### 4.5 Real-Time WebSocket Handshake

#### Request WebSocket Ticket
* **`POST /realtime/ticket`**
* **Access:** Authenticated (All Roles)
* **Response:**
  ```json
  {
    "ticket": "8f3a9e2d...",
    "expires_in": 60,
    "ws_url": "ws://localhost:8080/ws"
  }
  ```

