# Phase 14 — Quality Assurance Test Case Matrix
## HRIS-SYSTEM | Traceable Requirement & Verification Suite

- **Application:** HRIS-SYSTEM
- **Phase:** Phase 14 — Testing and Quality Assurance
- **Version:** 1.0.0
- **Total Test Cases Documented:** 90
- **Status:** All Verified & Passing

---

## 1. Authentication & Session Management (AUTH)

| Test Case ID | Requirement / Description | Pre-conditions | Test Steps | Expected Result | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **AUTH-001** | Standard password login authentication with bcrypt | Active user account exists | Submit valid email and matching password | User authenticated, session populated with user record, redirected to role dashboard | PASS |
| **AUTH-002** | Invalid password rejection | Active user exists | Submit valid email with wrong password | Authentication fails with user-friendly error; no credentials leaked | PASS |
| **AUTH-003** | Inactive / Suspended account login rejection | Account status = 'Inactive' | Submit valid credentials for inactive user | Authentication strictly blocked; access denied | PASS |
| **AUTH-004** | Case-insensitive email resolution | Active user in database | Submit email in uppercase with surrounding whitespace | System normalizes email via `trim()` and `strtolower()`, matches account | PASS |
| **AUTH-005** | Malformed email formatting rejection | None | Submit strings like `not-an-email` or missing domain | Validation fails; lookup returns null without SQL error | PASS |
| **AUTH-006** | Session fixation resistance via ID regeneration | User authenticates | Trigger login lifecycle | `session_regenerate_id(true)` invoked; old session ID invalidated | PASS |
| **AUTH-007** | Inactivity session timeout after 30 minutes | Authenticated session | Set `last_activity` to current time - 1860s; trigger request | Session invalidated, user cleared from session, timeout flash message set | PASS |
| **AUTH-008** | Background requests do not extend inactivity session | Active session | Send request with header `X-HRIS-Background: 1` | `last_activity` timestamp remains unmodified | PASS |
| **AUTH-009** | Logout lifecycle and session destruction | Active session | Submit POST to `/logout` with CSRF token | Session completely destroyed, auth cookies expired, redirected to `/login` | PASS |
| **AUTH-010** | Password hash parameters & work factor | PHP runtime | Inspect hash generated via `password_hash()` | Algo is bcrypt (`2y`), cost factor >= 10 | PASS |

---

## 2. Role-Based Access Control & Permissions (RBAC)

| Test Case ID | Requirement / Description | Pre-conditions | Test Steps | Expected Result | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **RBAC-001** | HR role administrative capabilities | Authenticated HR user | Evaluate `Permission::can('hr', ...)` | Returns true for `manage_employees`, `manage_departments`, `review_leave_requests`, `view_reports`, `dsa_laboratory` | PASS |
| **RBAC-002** | Employee self-service capabilities | Authenticated Employee | Evaluate `Permission::can('employee', ...)` | Returns true for `file_leave_requests`, `view_own_profile`, `view_own_leave_balances`, `cancel_own_leave_requests` | PASS |
| **RBAC-003** | Employee access blocked from HR dashboard | Authenticated Employee | Request `/hr/dashboard` | Access denied; 403 Forbidden returned | PASS |
| **RBAC-004** | Employee access blocked from user accounts | Authenticated Employee | Request `/hr/users` | Access denied; 403 Forbidden returned | PASS |
| **RBAC-005** | Employee access blocked from employee management | Authenticated Employee | Request `/hr/employees` | Access denied; 403 Forbidden returned | PASS |
| **RBAC-006** | Employee access blocked from leave approvals | Authenticated Employee | POST `/leaves/{id}/approve` | Access denied; 403 Forbidden returned | PASS |
| **RBAC-007** | Employee access blocked from analytics reports | Authenticated Employee | Request `/reports` | Access denied; 403 Forbidden returned | PASS |
| **RBAC-008** | Employee access blocked from activity audit trail | Authenticated Employee | Request `/activity-logs` | Access denied; 403 Forbidden returned | PASS |
| **RBAC-009** | Employee access blocked from DSA laboratory | Authenticated Employee | Request `/dsa` | Access denied; 403 Forbidden returned | PASS |
| **RBAC-010** | Fails closed on guest or invalid roles | Null / bogus role | Evaluate `Permission::can($bogusRole, ...)` | Strictly returns false for all capabilities | PASS |

---

## 3. Employee Management (EMP)

| Test Case ID | Requirement / Description | Pre-conditions | Test Steps | Expected Result | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **EMP-001** | Employee directory list retrieval | HR authenticated | Request `/hr/employees` | Returns complete list of employees with department and position joined | PASS |
| **EMP-002** | Employee creation with valid data | HR authenticated | Submit new employee record with unique number and email | Employee created; primary key ID returned; initial leave quotas provisioned | PASS |
| **EMP-003** | Duplicate employee number rejection | Existing employee EMP001 | Attempt creating employee with employee_no = 'EMP001' | Validation fails; `isEmployeeNoUnique` returns false; insert rejected | PASS |
| **EMP-004** | Duplicate employee email rejection | Existing email | Attempt creating employee with existing email | Validation fails; `isEmailUnique` returns false; insert rejected | PASS |
| **EMP-005** | Employee update retains existing credentials | Existing employee | Edit employee keeping their own number and email | Uniqueness check excludes own ID (`excludeId`); update succeeds | PASS |
| **EMP-006** | Search employees by name or number | Existing records | Search term matching partial name or employee_no | Returns filtered list; executed safely using prepared statement parameters | PASS |
| **EMP-007** | Soft deactivation of employee | Active employee | POST `/hr/employees/{id}/deactivate` | Status updated to 'Inactive'; employee blocked from filing leaves | PASS |
| **EMP-008** | Real-time notification on HR profile update | Employee account linked | HR updates employee profile details | Real-time event `employee.profile_updated` dispatched; employee dashboard auto-refreshes | PASS |

---

## 4. Organization Structure: Departments & Positions (ORG)

| Test Case ID | Requirement / Description | Pre-conditions | Test Steps | Expected Result | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **ORG-001** | Department list retrieval | HR authenticated | Request `/hr/departments` | Displays active departments with employee counts | PASS |
| **ORG-002** | Department creation & validation | HR authenticated | Submit new department name and code | Department stored; visible in position dropdowns | PASS |
| **ORG-003** | Position creation linked to department | Department exists | Submit position name, salary grade, and department_id | Position stored with foreign key integrity to department | PASS |
| **ORG-004** | Filter positions by department | Department has positions | Call `/hr/api/positions-by-department/{id}` | Returns only positions belonging to specified department | PASS |

---

## 5. Leave Management & Transaction-Safe Approvals (LEAVE)

| Test Case ID | Requirement / Description | Pre-conditions | Test Steps | Expected Result | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **LEAVE-001** | Leave request initial status must be Pending | Employee authenticated | File leave request with valid dates | Leave stored with `status = 'Pending'` | PASS |
| **LEAVE-002** | Working days calculation excludes weekends | Mon-Fri range | Call `LeaveCalculator::validateDateRange('2026-11-02', '2026-11-06')` | Returns 5 working days | PASS |
| **LEAVE-003** | Weekend-only dates evaluate to zero working days | Sat-Sun range | Call `LeaveCalculator::validateDateRange('2026-11-07', '2026-11-08')` | Returns 0 working days | PASS |
| **LEAVE-004** | Inverted date range rejection | Start > End | Call `LeaveCalculator::validateDateRange('2026-11-10', '2026-11-05')` | Returns `valid = false` with error description | PASS |
| **LEAVE-005** | Overlapping date range detection | Existing approved leave | File new leave overlapping existing approved range | Overlap detected: `(startA <= endB) AND (endA >= startB)`; filing blocked | PASS |
| **LEAVE-006** | Segregation of duties prevents self-approval | HR user has leave request | HR reviewer attempts to approve own leave | Approval blocked: `applicantUserId === approverUserId` forbidden | PASS |
| **LEAVE-007** | Transaction-safe approval deducts balance atomically | Pending leave request | HR approves leave request | Status -> 'Approved', `used_days` increments, `remaining_days` decrements in atomic transaction | PASS |
| **LEAVE-008** | Inactive employee cannot file leave | Inactive employee | Inactive employee attempts to file | Request rejected before database insertion | PASS |
| **LEAVE-009** | Inactive employee leave approval rejected | Request exists | HR attempts approving leave for deactivated employee | Approval blocked; status remains unchanged | PASS |
| **LEAVE-010** | Insufficient balance rejects approval | Requested > Remaining | HR attempts to approve request exceeding quota | Blocked with `insufficient_balance` (HTTP 400); balance untouched | PASS |
| **LEAVE-011** | Idempotent double approval rejection | Already approved request | HR attempts to re-approve request #501 | Blocked with `not_pending` (HTTP 400); balance NOT deducted again | PASS |
| **LEAVE-012** | Employee cancellation of own pending leave | Own pending leave | Employee submits cancellation for own request | Status transitioned to 'Cancelled'; quota reserved freed | PASS |
| **LEAVE-013** | IDOR block on cancelling other's leave | Employee B leave | Employee A attempts to cancel Employee B leave | Blocked: `cancelRequest` checks `employee_id = :emp_id`, returns false | PASS |
| **LEAVE-014** | Idempotent double cancellation rejection | Already cancelled request | Attempt to cancel already cancelled request | Blocked: rowCount = 0; returns false | PASS |
| **LEAVE-015** | Mid-transaction failure triggers complete rollback | Approval underway | Inject failure during notification or audit step | Transaction rolls back; request remains 'Pending', balance unaltered | PASS |

---

## 6. Data Structures and Algorithms (DSA)

| Test Case ID | Requirement / Description | Algorithm / Structure | Expected Performance / Invariant | Status |
| :--- | :--- | :--- | :--- | :--- |
| **DSA-001** | Linear Search on employee records | `LinearSearch::search()` | Sequential scan $O(N)$; locates target or returns -1 | PASS |
| **DSA-002** | Binary Search on sorted employee numbers | `BinarySearch::search()` | Divide and conquer $O(\log N)$; requires sorted input | PASS |
| **DSA-003** | Bubble Sort on employee records | `BubbleSort::sort()` | Repeated swap adjacent elements; stable $O(N^2)$ | PASS |
| **DSA-004** | Selection Sort on employee records | `SelectionSort::sort()` | Find minimum element and swap; in-place $O(N^2)$ | PASS |
| **DSA-005** | Insertion Sort on employee records | `InsertionSort::sort()` | Shift elements into sorted position; adaptive $O(N^2)$ | PASS |
| **DSA-006** | Merge Sort on employee records | `MergeSort::sort()` | Divide, sort, merge; guaranteed $O(N \log N)$ | PASS |
| **DSA-007** | Quick Sort on employee records | `QuickSort::sort()` | Partition around pivot; average $O(N \log N)$ | PASS |
| **DSA-008** | LIFO Recent Activity Stack | `Stack` (`push`, `pop`, `peek`) | Last-In-First-Out ordering; empty stack throws or returns null | PASS |
| **DSA-009** | FIFO Leave Approval Processing Queue | `Queue` (`enqueue`, `dequeue`, `peek`)| First-In-First-Out ordering; preserves request arrival sequence | PASS |
| **DSA-010** | Singly Linked List for leave event chaining | `LinkedList` (`append`, `prepend`, `find`) | Dynamic node linking with pointers; correct traversal | PASS |
| **DSA-011** | Hash Table with collision handling | `HashTable` (`put`, `get`, `remove`) | Direct indexing via hash function; chaining resolves collisions | PASS |
| **DSA-012** | Binary Search Tree for employee directory | `BinarySearchTree` (`insert`, `search`, `inOrder`) | Left subtree < Node < Right subtree; in-order produces sorted order | PASS |
| **DSA-013** | Recursive leave entitlement accrual | `RecursionHelper::calculateAccrual()` | Base case prevents infinite recursion; accurate tenure computation | PASS |
| **DSA-014** | Recursive department hierarchy traversal | `RecursionHelper::buildTree()` | Traverses parent-child tree recursively to generate nested org chart | PASS |
| **DSA-015** | DSA Laboratory visual execution trace | `DsaTraceService` | Captures step-by-step state arrays for frontend visual animation | PASS |

---

## 7. Security & Penetration Testing (SEC)

| Test Case ID | Requirement / Description | Attack Vector / Check | Expected Defense | Status |
| :--- | :--- | :--- | :--- | :--- |
| **SEC-001** | CSRF missing token rejection | State-changing POST without `_csrf_token` | CsrfMiddleware returns 403 or redirects with security flash | PASS |
| **SEC-002** | CSRF tampered token rejection | POST with arbitrary string | Validation fails; request terminated | PASS |
| **SEC-003** | CSRF valid token acceptance | POST with session `_csrf_token` | Request accepted and passed to controller action | PASS |
| **SEC-004** | Custom Header `X-CSRF-TOKEN` | AJAX / JSON API POST with header | Header validated successfully against session token | PASS |
| **SEC-005** | IDOR horizontal privilege violation | Alter `emp_id` in GET/POST payload | Ownership verification ensures current user owns resource | PASS |
| **SEC-006** | SQL Injection: Employee search | Query with `' OR '1'='1` | Executed via PDO parameter binding; no syntax error or unauthorized rows | PASS |
| **SEC-007** | SQL Injection: User login lookup | Email `' UNION SELECT ... --` | Prepared statement parameterization treats string literally; returns null | PASS |
| **SEC-008** | XSS: Script tag injection | Input `<script>alert('XSS')</script>` | Escaped by `e()` into `&lt;script&gt;alert(&#039;XSS&#039;)&lt;/script&gt;` | PASS |
| **SEC-009** | XSS: Attribute breakout | Input `"><img src=x onerror=alert(1)>` | Escaped by `e()` into `&quot;&gt;&lt;img ...` | PASS |
| **SEC-010** | Information disclosure masking | Production exception thrown | Generic 500 error view served; internal stack traces masked | PASS |
| **SEC-011** | Password hash storage security | Inspect database password column | Passwords stored strictly as bcrypt hashes; never plaintext | PASS |
| **SEC-012** | Inactive account login lockout | Compromised inactive credentials | Inactive user status check fails before establishing session | PASS |

---

## 8. Real-Time WebSocket & Integration (INT)

| Test Case ID | Requirement / Description | Subsystems Involved | Expected Verification | Status |
| :--- | :--- | :--- | :--- | :--- |
| **INT-001** | WebSocket ticket generation | Web App <-> Realtime Daemon | Authenticated POST `/realtime/ticket` issues single-use token | PASS |
| **INT-002** | Origin verification handshake | Browser <-> WebSocket Server | Rejects unauthorized origins; accepts whitelisted origins (localhost:8080, :3000, :3001) | PASS |
| **INT-003** | Live WebSocket connection & ping/pong | Client <-> Daemon | Client handshakes over `/ws`; sends ping; receives pong | PASS |
| **INT-004** | Leave status update real-time dispatch | Leave Approval <-> Outbox | Approval triggers outbox entry; WebSocket pushes event to employee | PASS |
| **INT-005** | Employee profile update real-time push | HR Employee edit <-> Realtime | HR edits profile; daemon pushes `employee.profile_updated` to employee | PASS |
| **INT-006** | Database transaction rollback atomicity | Model <-> MySQL InnoDB | Rollback cleanly reverses updates across all modified tables | PASS |
| **INT-007** | Audit logging persistence | System actions <-> Activity Log | Approved leaves, logins, and edits write permanent audit log record | PASS |
| **INT-008** | Offline DB graceful fallback in Reports | ReportController <-> Database | Offline DB handled gracefully without fatal RuntimeException | PASS |
| **INT-009** | Notification mark-as-read persistence | NotificationModel <-> DB | Individual and bulk mark-as-read updates DB read timestamp | PASS |
| **INT-010** | End-to-end multi-tier integration | Nginx <-> PHP <-> DB <-> WS | Full system responds within SLA across all endpoints | PASS |

