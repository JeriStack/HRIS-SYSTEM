# DSA Visualization Guide: Visual States, Playback & Telemetry

## 1. Visualizer Canvas Anatomy

The DSA Visualizer Studio (`/dsa/visualizer`) renders algorithm states dynamically using lightweight HTML, CSS, and SVG elements driven by real native PHP traces.

```
+-----------------------------------------------------------------------------------+
|  [Select Topic: Quick Sort | Merge Sort | Binary Search | Stack | BST ...]        |
+-----------------------------------------------------------------------------------+
|  Dataset Elements: [ 42, 17, 89, 5, 23, 64, 11, 78, 36, 50 ]  [🎲] [⚡ Load Trace]  |
+-----------------------------------------------------------------------------------+
|  Quick Sort Visualizer                                     Step 14 of 38  [Active]|
|  +-----------------------------------------------------------------------------+  |
|  | [▶ Play] [⏮ Prev] [⏭ Next] [↺ Reset]   Step: [-----o----] 14  Speed: [1.0x] |  |
|  +-----------------------------------------------------------------------------+  |
|  |                                                                             |  |
|  |       |*|           |=|           |*|                                       |  |
|  |       |*|     |*|   |=|     |*|   |*|                                       |  |
|  |  [0]  [1]     [2]   [3]     [4]   [5] ...                                   |  |
|  +-----------------------------------------------------------------------------+  |
|  | CURRENT ACTION: Comparing index 1 (17) and pivot index 3 (50).              |  |
+-----------------------------------------------------------------------------------+
|  TELEMETRY: Steps: 38 | Comparisons: 25 | Swaps: 11 | Server: 0.12 ms            |
|  COMPLEXITY: Best: O(n log n) | Avg: O(n log n) | Worst: O(n²) | Space: O(log n)  |
+-----------------------------------------------------------------------------------+
```

---

## 2. Color Coding & Visual State Indicators

To ensure maximum visual contrast and accessibility (following WCAG guidelines), each state is rendered with distinct color tokens:

### A. Sorting Algorithms (Dynamic Bar Chart)
| State / Event | Hex Token | CSS Class | Visual Meaning |
| :--- | :--- | :--- | :--- |
| **Default Element** | `#3B82F6` (Blue) | `.sort-bar` | Neutral, unsorted element in array |
| **Comparing Elements** | `#F59E0B` (Amber) | `.bar-compare` | The two elements currently undergoing evaluation |
| **Swapping / Shifting** | `#EF4444` (Rose Red) | `.bar-swap` | Elements being swapped or actively shifted |
| **Partition Pivot / Key** | `#8B5CF6` (Violet) | `.bar-pivot` | Active pivot value (QuickSort) or insertion key (InsertionSort) |
| **Final Sorted** | `#10B981` (Emerald) | `.bar-sorted` | Array element verified in its final sorted position |

### B. Searching Algorithms (Cell Grid & Pointers)
| State / Pointer | Color Token | Marker Label | Description |
| :--- | :--- | :--- | :--- |
| **Low Pointer** | `#3B82F6` (Blue) | `L` | Lower boundary of active search interval |
| **Mid Pointer** | `#F59E0B` (Amber) | `MID` | Calculated midpoint index `floor((low + high) / 2)` |
| **High Pointer** | `#8B5CF6` (Violet) | `H` | Upper boundary of active search interval |
| **Target Match** | `#10B981` (Emerald) | Target Cell | Successfully matched target index |
| **Outside Window** | `#0F172A` (Muted Dark)| Dimmed Cell | Discarded sub-array outside active search interval |

### C. Data Structures
- **Stack (LIFO):** Vertical stack container where elements enter from above. Top item marked with `▲ [TOP]` badge and blue accent border.
- **Queue (FIFO):** Horizontal pipeline where elements enqueue at `► REAR` and dequeue from `★ FRONT`.
- **Linked List:** Ordered node cards linked by directional pointer arrows (`→`).
- **Binary Search Tree (BST):** Hierarchical node structure displaying recursive visitation badges for In-Order, Pre-Order, and Post-Order traversal steps.

---

## 3. Playback Controls & Slider Synchronization

The player engine provides total control over step execution:
1. **Play / Pause (`▶` / `⏸`):** Toggles automatic step animation at the selected speed interval.
2. **Next Step (`⏭`):** Increments to the subsequent frame and updates bar heights, pointers, and narration.
3. **Previous Step (`⏮`):** Steps backward to the previous state.
4. **Reset (`↺`):** Returns immediately to Step 0 (initial input state).
5. **Timeline Slider:** Scrubbing the slider instantly jumps to any step index without lag.
6. **Speed Multipliers:**
   - `0.5x (Slow)`: 1200 ms per step (ideal for detailed lectures or debugging).
   - `1.0x (Normal)`: 600 ms per step (standard presentation tempo).
   - `2.0x (Fast)`: 250 ms per step (quick verification).
   - `4.0x (Ultra)`: 100 ms per step (overview of large array behavior).

---

## 4. Telemetry Metrics

For every execution trace, the studio records real runtime metrics:
- **Elapsed Execution Time:** Milliseconds taken by the PHP server engine to process the full algorithm.
- **Comparison Counter:** Total pairwise comparisons performed.
- **Swap / Shift Counter:** Number of in-place writes, swaps, or displacements.
- **Step Count:** Number of observable frames generated for playback.

