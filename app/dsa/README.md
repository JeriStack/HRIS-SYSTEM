# Custom Data Structures & Algorithms (`app/dsa`)

This directory contains custom, from-scratch PHP 8 implementations of core Data Structures and Algorithms integrated into the **Employee HRIS & Leave Management System**.

> **Architectural Rule:** DSA is an internal technical and academic layer. MySQL remains the authoritative persistent data store, and DSA is not exposed as a standalone business module on the HR or Employee dashboards.

---

## 1. Directory Layout

```text
app/dsa/
├── structures/
│   ├── ArrayStructure.php      # Canonical custom dynamic array abstraction
│   ├── ArrayWrapper.php        # Base custom dynamic array implementation (manual index shifting)
│   ├── Node.php                # Singly linked list Node class ($value, $next)
│   ├── LinkedList.php          # Pointer-based Singly Linked List ($head, $tail)
│   ├── Stack.php               # LIFO Stack with explicit $top index pointer
│   ├── Queue.php               # FIFO Queue with linked QueueNode pointers (O(1) enqueue/dequeue)
│   ├── BinarySearchTree.php    # Binary Search Tree (BSTNode, insert, search, delete, traversals)
│   └── HashTable.php           # Separate-chaining Hash Table with base-31 polynomial rolling hash
├── algorithms/
│   ├── LinearSearch.php        # O(n) exact, all-match, and case-insensitive substring search
│   ├── BinarySearch.php        # O(log n) iterative binary search + searchFirst() for duplicates
│   ├── BubbleSort.php          # O(n²) stable sort with O(n) early-exit swapped flag
│   ├── SelectionSort.php       # O(n²) in-place minimum/maximum selection sort
│   ├── InsertionSort.php       # O(n²) stable shifting sort (O(n) best case on sorted data)
│   ├── MergeSort.php           # O(n log n) stable divide-and-conquer merge sort
│   ├── QuickSort.php           # O(n log n) median-of-three Lomuto partition quick sort
│   └── RecursionHelper.php     # Recursive tree traversals, height, factorial, and Fibonacci helpers
├── arrays/
│   └── ArrayUtils.php          # Shared comparator, null-last ordering, and sorted-input verifier
└── documentation/
    └── complexity.md           # Formal time/space complexity matrices and architectural policies
```

---

## 2. Six Core Data Structures & Usage Examples

### 2.1 Array (`App\Dsa\Structures\ArrayStructure` / `ArrayWrapper`)
- **Purpose:** Store bounded collections of employee records with zero-based indexed access, manual shifting on insertion/deletion (no `array_splice`), traversal, filtering, searching, and sorting.
```php
use App\Dsa\Structures\ArrayStructure;

$roster = new ArrayStructure($employees);
$roster->insert(0, $newEmployee);                      // Manual O(n) right-shift
$active = $roster->filter(fn($e) => $e['employment_status'] === 'Active');
$sorted = $active->sort('merge', 'last_name', 'asc');  // Non-mutating O(n log n) sort
```

### 2.2 Linked List (`App\Dsa\Structures\Node` & `App\Dsa\Structures\LinkedList`)
- **Purpose:** Dynamic node-based sequential storage supporting $O(1)$ head/tail insertion (`prepend`, `append`), arbitrary position insertion (`insertAt`), deletion by value (`remove`) or position (`removeAt`), and sequential traversal (`traverse`).
```php
use App\Dsa\Structures\LinkedList;

$list = new LinkedList();
$list->prepend($firstRecord);
$list->append($lastRecord);
$list->insertAt(1, $middleRecord);
$found = $list->find('EMP-001', 'employee_no');
```

### 2.3 Stack (`App\Dsa\Structures\Stack`)
- **Purpose:** Last-In, First-Out (LIFO) structure using an explicit `$top` pointer (`-1` when empty) with safe `null` underflow handling. Used internally to inspect most recent audit events (`RecentActivityStackService`) and track reversible in-memory operation frames (`pushOperation`).
```php
use App\Dsa\Structures\Stack;

$stack = new Stack();
$stack->pushOperation('UPDATE_EMPLOYEE', $beforeState, $afterState);
$latestFrame = $stack->peek(); // O(1)
$poppedFrame = $stack->pop();  // O(1), returns null safely when empty
```

### 2.4 Queue (`App\Dsa\Structures\Queue`)
- **Purpose:** First-In, First-Out (FIFO) structure built with `QueueNode` pointers (`$head`, `$tail`) so `enqueue()`, `dequeue()`, `front()`, `peek()`, and `rear()` all execute in $O(1)$ time without shifting array elements.
```php
use App\Dsa\Structures\Queue;

$queue = new Queue();
$queue->enqueue($pendingTask);
$next = $queue->front();   // O(1) non-destructive inspection
$item = $queue->dequeue(); // O(1) removal from head
```

### 2.5 Binary Search Tree (`App\Dsa\Structures\BinarySearchTree`)
- **Purpose:** Hierarchical key-value organization supporting $O(\log n)$ average `insert`, `search`, `contains`, 3-case `delete` (leaf, single-child, two-child with in-order successor), `height()`, and `inOrder()`, `preOrder()`, and `postOrder()` traversals.
```php
use App\Dsa\Structures\BinarySearchTree;

$bst = new BinarySearchTree();
$bst->insert('EMP-050', $emp50);
$bst->insert('EMP-025', $emp25);
$ordered = $bst->inOrder(); // Ascending order by key
```

### 2.6 Hash Table (`App\Dsa\Structures\HashTable`)
- **Purpose:** $O(1)$ average-case key-value lookup using a custom base-31 polynomial rolling hash (`hash()`), separate chaining (`HashNode`) for collision resolution, and dynamic bucket doubling at load factor $\ge 0.75$.
```php
use App\Dsa\Structures\HashTable;

$table = new HashTable(64);
$table->set('EMP-001', $employeeRecord);
if ($table->containsKey('EMP-001')) {
    $emp = $table->get('EMP-001'); // O(1) average lookup
}
```

---

## 3. Practical HRIS Service Integrations

| Service | Data Structure / Algorithm | HRIS Application |
|---|---|---|
| `App\Services\EmployeeDirectoryService` | `ArrayStructure`, `LinkedList`, `Stack`, `LinearSearch`, `BinarySearch`, `BubbleSort`, `SelectionSort`, `InsertionSort`, `MergeSort`, `QuickSort` | Bounded employee directory collection processing, searching, sorting, sequential roster traversal, and internal reversible operation tracking (`MAX_DSA_DATASET = 2000`). |
| `App\Services\EmployeeLookupService` | `HashTable` | $O(1)$ average-case employee lookup keyed by normalized `employee_no`. |
| `App\Services\EmployeeDirectoryTreeService` | `BinarySearchTree` | Hierarchical indexing of employee records by `employee_no` and sorted extraction via recursive in-order traversal. |
| `App\Services\LeaveHistorySequenceService` | `LinkedList` & `Node` | Chronological node-by-node traversal of an employee's leave request history. |
| `App\Services\LeaveApprovalQueueService` | `Queue` | Deterministic FIFO ordering of pending leave requests while delegating actual approval persistence to MySQL ACID transactions. |
| `App\Services\RecentActivityStackService` | `Stack` | LIFO inspection of the most recent security/audit activity log entries. |

---

## 4. Running the Test Suite

```bash
php -d auto_prepend_file=tests/bootstrap.php vendor/phpunit/phpunit/phpunit --no-configuration tests/Unit/DSA tests/Feature/DsaWorkflowIntegrationTest.php
```
