# Data Structures & Algorithms Complexity Analysis

## 1. Asymptotic Complexity Matrix

| Structure / Algorithm | Operation | Average Time | Worst-Case Time | Auxiliary Space | Invariant / Policy |
|---|---|:---:|:---:|:---:|---|
| **Stack** | `push($item)` | $O(1)$ amortized | $O(1)$ | $O(n)$ | LIFO order. Items appended to dynamic array. |
| | `pop()` | $O(1)$ | $O(1)$ | $O(1)$ | Returns top item; null on empty stack. |
| | `peek()` | $O(1)$ | $O(1)$ | $O(1)$ | Reads top element without mutation. |
| **Queue** | `enqueue($item)` | $O(1)$ | $O(1)$ | $O(n)$ | FIFO order. Appends to tail pointer. |
| | `dequeue()` | $O(1)$ | $O(1)$ | $O(1)$ | Removes head pointer node; avoids array shifts. |
| | `peek()` | $O(1)$ | $O(1)$ | $O(1)$ | Inspects head node without removal. |
| **Linked List** | `append($val)` | $O(1)$ | $O(1)$ | $O(n)$ | Maintains head and tail pointers. |
| | `prepend($val)` | $O(1)$ | $O(1)$ | $O(1)$ | Updates head pointer. |
| | `remove($val)` | $O(n)$ | $O(n)$ | $O(1)$ | Unlinks matching node (head, middle, tail). |
| | `find($val)` | $O(n)$ | $O(n)$ | $O(1)$ | Linear node traversal. |
| | `getAt($idx)` | $O(n)$ | $O(n)$ | $O(1)$ | Steps forward $k$ nodes from head. |
| **Hash Table** | `set($k, $v)` | $O(1)$ | $O(n)$ | $O(n + C)$ | Polynomial hash with separate chaining. Rehashes when load factor $> 0.75$. |
| | `get($k)` | $O(1)$ | $O(n)$ | $O(1)$ | Scans bucket chain for key match. |
| | `containsKey($k)` | $O(1)$ | $O(n)$ | $O(1)$ | Disambiguates stored `null` from missing key. |
| | `remove($k)` | $O(1)$ | $O(n)$ | $O(1)$ | Unlinks key node from bucket list. |
| **BST** | `insert($k, $v)` | $O(\log n)$ | $O(n)$ | $O(n)$ | Unbalanced BST. Degenerates to $O(n)$ on sorted input. |
| | `search($k)` | $O(\log n)$ | $O(n)$ | $O(1)$ | Binary search path from root. |
| | `delete($k)` | $O(\log n)$ | $O(n)$ | $O(1)$ | Replaces 2-child node with in-order successor. |
| **Recursion** | `inOrder()` | $O(n)$ | $O(n)$ | $O(h)$ | Call stack depth bounded by tree height $h \le n$. |
| | `preOrder()` | $O(n)$ | $O(n)$ | $O(h)$ | Call stack depth bounded by tree height $h \le n$. |
| | `postOrder()` | $O(n)$ | $O(n)$ | $O(h)$ | Call stack depth bounded by tree height $h \le n$. |

---

## 2. Practical HRIS System Implications

### 2.1 In-Memory vs Database Trade-Offs
- While custom data structures achieve theoretical $O(1)$ or $O(\log n)$ in-memory execution, the total end-to-end response time is bounded by the initial MySQL query latency, network transfer, and PHP object allocation costs.
- The in-memory DSA structures are used for **bounded working sets** (e.g., current department requests, recent 20 activities, active directory page). They complement, rather than replace, database indexes and queries.

### 2.2 Memory Safety & Call Stack Limits
- **Hash Table:** Resizing doubles capacity to maintain $O(1)$ average performance, requiring $O(n)$ rehashing work during expansion.
- **Tree Recursion:** PHP has a recursion depth limit (typically determined by `xdebug.max_nesting_level` or system memory). For deep trees, recursion height $h$ can impact memory. In this implementation, bounded workforce inputs guarantee $h \ll 100$.
- **Queue Memory:** Pointer nodes decouple storage from array memory buffers, ensuring $O(1)$ enqueue/dequeue without reallocating arrays.

