# Phase 12 — DSA Demonstration Scenarios & Presentation Scripts

This document outlines live demonstration scenarios for technical reviews, stakeholder presentations, and instructional workshops.

---

## Scenario 1: Quick Sort vs. Bubble Sort Performance & Early-Exit

### Objective
Demonstrate $O(n \log n)$ vs $O(n^2)$ complexity differences and show how the custom early-exit flag optimizes sorted arrays.

### Live Demo Steps:
1. Navigate to `/dsa/visualizer` as an HR/Admin user.
2. Select **Bubble Sort** and enter an already-sorted dataset: `10, 20, 30, 40, 50, 60`.
3. Click **⚡ Load Trace** and press **▶ Play**.
4. **Key Observation:** Point out that after 1 pass through the 6 elements with 0 swaps, the narration displays:
   > *"Clean pass with 0 swaps detected. Early-exit optimization triggered."*
   Execution halts in just 6 comparisons, verifying the $O(n)$ best-case behavior.
5. Next, select **Quick Sort** with a reversed dataset: `90, 80, 70, 60, 50, 40, 30, 20, 10`.
6. Click **⚡ Load Trace** and observe Lomuto partitioning: violet bar highlights the pivot, elements smaller than pivot are moved to the left partition, and pivot is placed at its permanent sorted boundary.

---

## Scenario 2: Binary Search Interval Halving vs. Linear Search

### Objective
Showcase why Binary Search requires $O(\log n)$ time and why pre-sorting invariants are mandatory.

### Live Demo Steps:
1. Navigate to `/dsa/visualizer`.
2. Select **Binary Search**.
3. Click **🎲 Example** to load `5, 12, 19, 27, 34, 48, 59, 72, 85, 96` with target `48`.
4. Click **⚡ Load Trace** and step through with **⏭ Next**:
   - **Step 1:** Initial array loaded with 10 elements. Low = 0 (`5`), Mid = 4 (`34`), High = 9 (`96`).
   - **Step 2:** Midpoint value 34 evaluated against target 48. Because `34 < 48`, the lower half `[0..4]` is discarded and dimmed out. Low shifts to index 5 (`48`).
   - **Step 3:** New Midpoint calculated at index 7 (`72`). Because `72 > 48`, High shifts to index 6.
   - **Step 4:** Midpoint hits index 5 (`48`). Target match found! Emerald green badge lights up.
5. **Key Metric:** Target was located in an array of 10 elements with only 3 comparisons, demonstrating $\lceil \log_2(10) \rceil = 4$ max bounds.

---

## Scenario 3: Real-Time HR Workflow Structures (Queue & Stack)

### Objective
Demonstrate how the underlying LIFO and FIFO data structures mirror real-world HR workflows.

### Live Demo Steps:
1. Navigate to `/dsa`.
2. Select **Queue (FIFO)**.
3. Show default queue representing pending leave approvals:
   - `#LR-001 (Maria Santos)`
   - `#LR-002 (David Domingo)`
   - `#LR-003 (Juan Dela Cruz)`
4. Select operation **Dequeue (remove front)** and click **▶ Run Operation**.
5. **Key Observation:** `#LR-001 (Maria Santos)` is dequeued first, proving fair first-come, first-served request processing.
6. Switch to **Stack (LIFO)**.
7. Select **Push** with value `User changed emergency contact`.
8. Click **▶ Run Operation**, then execute **Peek**.
9. **Key Observation:** The newest audit action is immediately returned at the top of the stack, proving instantaneous $O(1)$ recent activity inspection.

---

## Scenario 4: Binary Search Tree (BST) Recursive Traversals

### Objective
Demonstrate recursive In-Order, Pre-Order, and Post-Order traversals on employee department hierarchical trees.

### Live Demo Steps:
1. Navigate to `/dsa/visualizer`.
2. Select **Binary Search Tree**.
3. Choose operation **In-Order Traversal (LNR)** and click **⚡ Load Trace**.
4. Press **▶ Play** at `1.0x` speed.
5. **Key Observation:** Nodes are highlighted in strictly ascending numerical order (`10 → 25 → 40 → 50 → 60 → 75 → 90`), proving the recursive left-node-right invariant of BSTs.
6. Switch operation to **Pre-Order (NLR)** and show that the root (`50`) is visited before its left and right subtrees.

