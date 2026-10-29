# DSA Demonstrations & Practical Verification Guide

## 1. Overview

The HRIS Data Structures & Algorithms Laboratory provides an interactive, live demonstration environment at `GET /dsa` (restricted to HR Management). Every demonstration binds to actual or representative HRIS data entities.

---

## 2. Demonstration Catalog

### Demonstration 1: LIFO Stack (Activity Logs)
- **Component:** `App\Dsa\Structures\Stack`
- **Service:** `App\Services\RecentActivityStackService`
- **Application Flow:** Chronological audit entries from `activity_logs` are loaded and pushed onto the stack. The top element is inspected via `peek()`, and recent events are extracted via `pop()` in reverse-chronological order.
- **Entry Point:** `GET /dsa` &rarr; Section 2 (LIFO Stack card).

### Demonstration 2: FIFO Queue (Leave Approval Queue)
- **Component:** `App\Dsa\Structures\Queue`
- **Service:** `App\Services\LeaveApprovalQueueService`
- **Application Flow:** Pending leave applications are queued in strict chronological arrival order ($t_{\text{applied}}$ ASC, ID ASC). The front application is previewed via `peek()`, and processed sequentially via `dequeue()`.
- **Entry Point:** `GET /dsa` &rarr; Section 2 (FIFO Queue card).

### Demonstration 3: Singly Linked List (Career & Leave History)
- **Component:** `App\Dsa\Structures\LinkedList`
- **Service:** `App\Services\LeaveHistorySequenceService`
- **Application Flow:** Milestone records are appended to an explicit pointer chain. Demonstrates linear traversal, random index retrieval via `getAt()`, and node unlinking via `remove()`.
- **Entry Point:** `GET /dsa` &rarr; Section 3 (Linked List card).

### Demonstration 4: Hash Table (Employee Record Lookup)
- **Component:** `App\Dsa\Structures\HashTable`
- **Service:** `App\Services\EmployeeLookupService`
- **Application Flow:** Workforce records are indexed by normalized `employee_no` using a polynomial rolling hash with separate chaining. Average $O(1)$ lookup is demonstrated via `get($key)` and `containsKey($key)`.
- **Entry Point:** `GET /dsa` &rarr; Section 3 (Hash Table card).

### Demonstration 5: Binary Search Tree (Employee Directory)
- **Component:** `App\Dsa\Structures\BinarySearchTree`
- **Service:** `App\Services\EmployeeDirectoryTreeService`
- **Application Flow:** Employees are inserted into an ordered binary tree using `employee_no` as the key. Logarithmic lookup and three-case deletion are demonstrated.
- **Entry Point:** `GET /dsa` &rarr; Section 4 (Binary Search Tree card).

### Demonstration 6: Recursion Helper (Tree Traversal)
- **Component:** `App\Dsa\Algorithms\RecursionHelper`
- **Application Flow:** Executes recursive In-Order ($L \to N \to R$), Pre-Order ($N \to L \to R$), and Post-Order ($L \to R \to N$) traversals of the employee tree, displaying the resultant sequences.
- **Entry Point:** `GET /dsa` &rarr; Section 4 (Traversals panel).

---

## 3. Verification Commands

### Execute Unit & Integration Tests
```powershell
# Run all DSA Unit and Integration tests
php vendor/bin/phpunit tests/Unit/DSA tests/Integration/DSA

# Inside Docker container
docker compose exec app php vendor/bin/phpunit tests/Unit/DSA tests/Integration/DSA
```

### Full Application Regression Suite
```powershell
php vendor/bin/phpunit
```

