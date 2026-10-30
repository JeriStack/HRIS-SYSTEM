# Custom Data Structures & Algorithms (DSA) Engine

## 1. Overview

This module provides native PHP implementations of foundational Data Structures and Algorithms engineered specifically for the **Employee HRIS & Leave Management System**. None of these classes rely on built-in PHP sorting, collection, or search utilities (`sort`, `usort`, `array_shift`, `array_search`, etc.).

---

## 2. Directory Layout

```
app/dsa/
├── structures/
│   ├── Stack.php             # LIFO Stack for recent activity & audit undo
│   ├── Queue.php             # FIFO pointer Queue for leave approval pipeline
│   ├── LinkedList.php        # Singly Linked List for sequential career/leave history
│   ├── HashTable.php         # Hash Table with polynomial rolling hash & separate chaining
│   └── BinarySearchTree.php  # BST for logarithmic search and ordered hierarchical indexing
├── algorithms/
│   ├── RecursionHelper.php   # Reusable recursive tree traversals (In, Pre, Post Order)
│   ├── BubbleSort.php        # Educational O(n²) comparison sort with early exit
│   ├── SelectionSort.php     # O(n²) non-stable in-place selection sort
│   ├── InsertionSort.php     # O(n) best-case adaptive stable sort
│   ├── MergeSort.php         # O(n log n) stable divide-and-conquer sort with manual merge
│   ├── QuickSort.php         # O(n log n) median-of-three Lomuto partitioned sort
│   ├── LinearSearch.php      # O(n) sequential scan (exact, all, substring)
│   └── BinarySearch.php      # O(log n) iterative binary search with sortedness check
└── arrays/
    └── ArrayUtils.php        # Shared comparator contract, direction normalizer, null policy
```

---

## 3. Practical HRIS Integration Mapping

| Data Structure / Algorithm | HRIS Service Bridge | Practical HRIS Operational Workflow |
|---|---|---|
| **Stack (LIFO)** | `RecentActivityStackService` | Pushes chronological database audit logs so the newest activity sits at the top for instant LIFO extraction. |
| **Queue (FIFO)** | `LeaveApprovalQueueService` | Stages pending leave requests in deterministic submission order for sequential review; delegates to transactional model. |
| **Linked List** | `LeaveHistorySequenceService` | Constructs a sequential chain of employee career and leave milestone records with explicit forward node pointers. |
| **Hash Table** | `EmployeeLookupService` | Indexes demographic records by `employee_no` using polynomial rolling hash and separate chaining for average O(1) lookup. |
| **Binary Search Tree** | `EmployeeDirectoryTreeService` | Organizes employee records in an ordered binary tree for logarithmic lookup by ID. |
| **Recursion Helper** | `RecursionHelper` | Traverses the binary search tree recursively (In-Order, Pre-Order, Post-Order) with documented call-stack bounds. |

---

## 4. Complexity Summary

| Component | Operation | Average Time | Worst Time | Auxiliary Space |
|---|---|:---:|:---:|:---:|
| **Stack** | `push` / `pop` / `peek` | $O(1)$ | $O(1)$ | $O(n)$ |
| **Queue** | `enqueue` / `dequeue` / `peek` | $O(1)$ | $O(1)$ | $O(n)$ pointer nodes |
| **Linked List** | `append` / `prepend` | $O(1)$ | $O(1)$ | $O(n)$ nodes |
| **Linked List** | `find` / `getAt` / `remove` | $O(n)$ | $O(n)$ | $O(1)$ |
| **Hash Table** | `set` / `get` / `containsKey` | $O(1)$ | $O(n)$ (bucket chain) | $O(n + \text{capacity})$ |
| **BST** | `search` / `insert` / `delete` | $O(\log n)$ | $O(n)$ (degenerate) | $O(n)$ nodes |
| **Recursion** | `inOrder` / `preOrder` / `postOrder` | $O(n)$ | $O(n)$ | $O(h)$ stack frames |

