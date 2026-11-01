# HRIS-SYSTEM — Data Structures & Algorithms Complexity Reference

This document provides the formal time and space complexity analysis, implementation notes, duplicate-handling policies, and HRIS architectural boundaries for all custom Data Structures and Algorithms in `app/dsa/`.

---

## 1. Custom Academic Implementations vs. PHP Built-In Functionality

In PHP, the native `array` type is an ordered hash map (`HashTable` in Zend Engine C internals), and standard functions like `sort()`, `usort()`, `in_array()`, `array_splice()`, `array_shift()`, and `array_pop()` delegate to compiled C routines.

To satisfy academic and engineering requirements, the classes under `app/dsa/` implement explicit pointer nodes, manual index shifting, explicit top pointers, polynomial rolling hashes, and recursive/iterative divide-and-conquer logic **from scratch** without delegating core algorithmic steps to PHP built-in sort/search/splice helpers:

| Component | Custom Implementation (`app/dsa/`) | PHP Built-In Equivalent Avoided |
|---|---|---|
| **Array (`ArrayStructure` / `ArrayWrapper`)** | Contiguous `0..n-1` index management with manual `for`-loop shifting on `insert()` and `delete()` | `array_splice()` |
| **Linked List (`Node` & `LinkedList`)** | Dynamic `Node` objects with `$next` object references, `$head` and `$tail` pointers | Indexed PHP `array` |
| **Stack (`Stack`)** | Explicit `$top` integer pointer (`-1` when empty) with manual push/pop index arithmetic | `array_push()`, `array_pop()`, `end()` |
| **Queue (`Queue`)** | Linked `QueueNode` pointers (`$head`, `$tail`) achieving true $O(1)$ `enqueue()` and `dequeue()` | `array_shift()` ($O(n)$ re-index) |
| **Binary Search Tree (`BinarySearchTree`)** | Recursive/iterative `BSTNode` (`$key`, `$value`, `$left`, `$right`) with 3-case deletion | `ksort()` / associative array |
| **Hash Table (`HashTable`)** | Base-31 polynomial rolling `hash()`, bucket array, separate chaining (`HashNode`), dynamic resizing at $\alpha \ge 0.75$ | Native PHP associative `array` |
| **Searching (`LinearSearch`, `BinarySearch`)** | Explicit sequential scan and $O(\log n)$ midpoint halving (`$low`, `$high`, `$mid`) | `in_array()`, `array_search()` |
| **Sorting (`BubbleSort`, `SelectionSort`, `InsertionSort`, `MergeSort`, `QuickSort`)** | Hand-written swap, shift, stable merge, and median-of-three Lomuto partition | `sort()`, `usort()`, `array_multisort()` |

---

## 2. Six Core Data Structures — Complexity Matrix

| Data Structure | Class | Operation | Best Time | Average Time | Worst Time | Auxiliary Space | Notes |
|---|---|---|---|---|---|---|---|
| **Array** | `ArrayStructure` / `ArrayWrapper` | `get($i)` / `update($i, $v)` | $O(1)$ | $O(1)$ | $O(1)$ | $O(1)$ | Direct zero-based index lookup |
| | | `add($v)` / `append($v)` | $O(1)$ | $O(1)$ | $O(1)$ | $O(1)$ | Appends at `$this->length` |
| | | `insert($i, $v)` / `delete($i)` | $O(1)$ (tail) | $O(n)$ | $O(n)$ | $O(1)$ | Manual element shifting |
| | | `search($v)` / `filter($fn)` | $O(1)$ | $O(n)$ | $O(n)$ | $O(1)$ / $O(k)$ | Sequential scan |
| **Linked List** | `LinkedList` & `Node` | `prepend($v)` / `insertFirst($v)` | $O(1)$ | $O(1)$ | $O(1)$ | $O(1)$ | Updates `$head` pointer |
| | | `append($v)` / `insertLast($v)` | $O(1)$ | $O(1)$ | $O(1)$ | $O(1)$ | Uses `$tail` pointer directly |
| | | `insertAt($pos, $v)` | $O(1)$ | $O(n)$ | $O(n)$ | $O(1)$ | $O(1)$ at head/tail; $O(n)$ interior |
| | | `remove($v)` / `removeAt($pos)` | $O(1)$ (head) | $O(n)$ | $O(n)$ | $O(1)$ | Pointer relinking |
| | | `find($v)` / `search($v)` / `traverse()` | $O(1)$ | $O(n)$ | $O(n)$ | $O(1)$ | Pointer traversal from `$head` |
| **Stack (LIFO)** | `Stack` | `push($v)` / `pushOperation(...)` | $O(1)$ | $O(1)$ | $O(1)$ | $O(1)$ | Increments `$top` pointer |
| | | `pop()` / `peek()` | $O(1)$ | $O(1)$ | $O(1)$ | $O(1)$ | Returns `null` safely on underflow |
| **Queue (FIFO)** | `Queue` | `enqueue($v)` | $O(1)$ | $O(1)$ | $O(1)$ | $O(1)$ | Appends `QueueNode` at `$tail` |
| | | `dequeue()` | $O(1)$ | $O(1)$ | $O(1)$ | $O(1)$ | Advances `$head` pointer (no shifting) |
| | | `front()` / `peek()` / `rear()` | $O(1)$ | $O(1)$ | $O(1)$ | $O(1)$ | Returns `null` safely when empty |
| **Binary Search Tree** | `BinarySearchTree` | `insert($k, $v)` | $O(1)$ | $O(\log n)$ | $O(n)$ | $O(h)$ | Worst-case $O(n)$ on skewed/sorted input |
| | | `search($k)` / `contains($k)` | $O(1)$ | $O(\log n)$ | $O(n)$ | $O(1)$ | Iterative descent |
| | | `delete($k)` | $O(1)$ | $O(\log n)$ | $O(n)$ | $O(h)$ | In-order successor for 2-child node |
| | | `inOrder()` / `preOrder()` / `postOrder()` | $O(n)$ | $O(n)$ | $O(n)$ | $O(n)$ | Visits all $n$ nodes |
| **Hash Table** | `HashTable` | `set($k, $v)` / `insert($k, $v)` | $O(1)$ | $O(1)$ | $O(n)$ | $O(1)$ | Amortized $O(1)$; $O(n)$ on resize/all-collide |
| | | `get($k)` / `search($k)` / `containsKey($k)` | $O(1)$ | $O(1)$ | $O(n)$ | $O(1)$ | Separate chaining bucket traversal |
| | | `remove($k)` / `delete($k)` | $O(1)$ | $O(1)$ | $O(n)$ | $O(1)$ | Unlinks `HashNode` from bucket chain |

---

## 3. Searching & Sorting Algorithms — Complexity Matrix

| Algorithm | Class | Best Time | Average Time | Worst Time | Auxiliary Space | Stable? | Mutates Input? |
|---|---|---|---|---|---|---|---|
| **Linear Search** | `LinearSearch` | $O(1)$ | $O(n)$ | $O(n)$ | $O(1)$ | N/A | No |
| **Binary Search** | `BinarySearch` | $O(1)$ | $O(\log n)$ | $O(\log n)$ | $O(1)$ | N/A (requires sorted input) | No |
| **Bubble Sort** | `BubbleSort` | $O(n)$ (early-exit flag) | $O(n^2)$ | $O(n^2)$ | $O(1)$ | Yes | No |
| **Selection Sort** | `SelectionSort` | $O(n^2)$ | $O(n^2)$ | $O(n^2)$ | $O(1)$ | No | No |
| **Insertion Sort** | `InsertionSort` | $O(n)$ | $O(n^2)$ | $O(n^2)$ | $O(1)$ | Yes | No |
| **Merge Sort** | `MergeSort` | $O(n \log n)$ | $O(n \log n)$ | $O(n \log n)$ | $O(n)$ | Yes | No |
| **Quick Sort** | `QuickSort` | $O(n \log n)$ | $O(n \log n)$ | $O(n^2)$ | $O(\log n)$ | No (median-of-3 pivot) | No |

---

## 4. Balancing, Duplicate Policies & Architectural Safeguards

1. **BST Balancing Limitation:** `BinarySearchTree` is an unbalanced academic BST. When keys arrive in random order, expected height is $O(\log n)$. If keys are inserted in strictly ascending or descending order (e.g., `EMP-001`, `EMP-002`, `EMP-003`), the tree degenerates into a singly linked chain of height $n$, degrading lookup/insert/delete to $O(n)$. Self-balancing trees (AVL / Red-Black) guarantee $O(\log n)$ worst-case height via rotations.
2. **Duplicate Key Policies:**
   - `BinarySearchTree::insert($key, $value)` updates the existing node's value in place without creating duplicate key nodes.
   - `HashTable::set($key, $value)` updates the existing `HashNode` in the bucket chain without incrementing `size()`.
   - `BinarySearch::searchFirst()` locates the lowest-index match when duplicate sort keys exist in employee records.
3. **Database Authority & Bounded Memory (`MAX_DSA_DATASET = 2000`):**
   - MySQL remains the single authoritative source of truth for all HRIS persistence, uniqueness constraints, foreign keys, and ACID transactions.
   - In-memory DSA structures operate strictly on bounded, role-authorized datasets.

