# Phase 11 — Advanced Data Structures & Algorithms

## 1. Executive Summary

Phase 11 completes the formal integration of custom Data Structures and Algorithms (DSA) into the core operational workflows of the Employee HRIS & Leave Management System. Every structure was engineered natively in PHP without utilizing built-in high-level collection or sort wrappers, satisfying the dual-mandate of an enterprise HR platform and an academic Computer Science demonstration workbench.

---

## 2. Implemented Components

### 2.1 Stack (`App\Dsa\Structures\Stack`)
- **Principle:** Last-In, First-Out (LIFO).
- **Internal Storage:** Dynamic array with strict pointer/index bounds.
- **Methods:** `push()`, `pop()`, `peek()`, `isEmpty()`, `size()`, `clear()`, `toArray()`.
- **HRIS Use Case:** `RecentActivityStackService` loads chronological database audit logs; pushing places the newest activity at the top, enabling instant $O(1)$ review of recent operations.

### 2.2 Queue (`App\Dsa\Structures\Queue`)
- **Principle:** First-In, First-Out (FIFO).
- **Internal Storage:** Pointer-based singly linked `QueueNode` objects maintaining `head`, `tail`, and `count` references. Avoids $O(n)$ PHP `array_shift()` re-indexing penalties.
- **Methods:** `enqueue()`, `dequeue()`, `peek()`, `isEmpty()`, `size()`, `clear()`, `toArray()`.
- **HRIS Use Case:** `LeaveApprovalQueueService` stages pending leave applications in strict submission order ($t_{\text{applied}}$ ASC, ID ASC). Dequeueing evaluates requests sequentially and delegates to `Leave::approveRequest()` inside ACID transactions without bypassing business rules.

### 2.3 Singly Linked List (`App\Dsa\Structures\LinkedList`)
- **Principle:** Linear sequence linked via explicit node references (`ListNode::$next`).
- **Methods:** `append()`, `prepend()`, `remove()`, `find()`, `getAt()`, `size()`, `isEmpty()`, `clear()`, `toArray()`.
- **HRIS Use Case:** `LeaveHistorySequenceService` models employee leave and career milestone histories as an explicit chain, supporting sequential iteration and index queries.

### 2.4 Hash Table (`App\Dsa\Structures\HashTable`)
- **Principle:** Key-value store using polynomial rolling hash and separate chaining collision resolution.
- **Hashing Function:** $H(S) = \sum_{i=0}^{k-1} (S[i] \cdot 31^{k-1-i}) \pmod{\text{capacity}}$.
- **Dynamic Resizing:** Doubles bucket capacity and rehashes all nodes when load factor $\frac{N}{\text{capacity}} > 0.75$.
- **Methods:** `set()`, `get()`, `containsKey()`, `remove()`, `size()`, `isEmpty()`, `clear()`, `toArray()`.
- **HRIS Use Case:** `EmployeeLookupService` indexes demographic records by `employee_no` for average $O(1)$ lookup time, cleanly distinguishing stored `null` from missing keys.

### 2.5 Binary Search Tree (`App\Dsa\Structures\BinarySearchTree`)
- **Principle:** Hierarchical node structure (`BSTNode`) enforcing the BST invariant ($left < root < right$).
- **Comparison Engine:** Integrates with `ArrayUtils::compareValues()` for uniform string and numeric ID comparisons.
- **Deletion:** Implements full 3-case deletion (leaf, single-child, and two-child using in-order successor).
- **Methods:** `insert()`, `search()`, `contains()`, `delete()`, `inOrderTraversal()`, `preOrderTraversal()`, `postOrderTraversal()`, `isEmpty()`, `size()`, `clear()`.
- **HRIS Use Case:** `EmployeeDirectoryTreeService` organizes employees into a binary search hierarchy for logarithmic lookup.

### 2.6 Recursion Helper (`App\Dsa\Algorithms\RecursionHelper`)
- **Principle:** Recursive tree traversals and mathematical demonstrators with explicit base cases and call-stack bounds.
- **Methods:** `inOrderTraversal()`, `preOrderTraversal()`, `postOrderTraversal()`, `treeHeight()`, `factorial()`, `fibonacci()`, `binarySearchRecursive()`.
- **HRIS Use Case:** Produces ordered employee sequences from the BST using call-stack recursion.

---

## 3. Security, Authorization & Persistence Protection

1. **Deny-by-Default Authorization:** All demonstration routes (`/dsa`) and underlying services require authenticated sessions and strict role gating (`AuthMiddleware` + `RoleMiddleware(['hr'])`).
2. **Persistence Integrity:** In-memory DSA structures do not replace authoritative MySQL InnoDB tables. MySQL remains the authoritative system of record.
3. **No Blind Approvals:** Queue operations never approve leave requests automatically. Every request transitions through `Leave::approveRequest()` with row locking (`FOR UPDATE`) and balance validation.
4. **Data Sanitization:** All view outputs are rendered through the escaping helper `e()`.

