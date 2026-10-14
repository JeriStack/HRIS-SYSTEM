<?php
/**
 * DSA Laboratory & Algorithm Benchmarks View (HR & Admin Access)
 * Employee HRIS & Leave Management System
 */
?>
<div class="page-header">
    <div class="page-title-group">
        <h1>DSA Laboratory & Algorithm Benchmarks</h1>
        <p>Live architectural demonstration of custom Data Structures & Algorithms powering HRIS workflows.</p>
    </div>
    <div class="page-actions">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); background: var(--color-bg-surface); padding: 0.4rem 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
            ⚡ Academic & Engineering Showcase
        </span>
    </div>
</div>

<!-- Section 1: Algorithm Benchmarks Comparison -->
<div class="card" style="margin-bottom: var(--space-6);">
    <div class="card-header">
        <h2 class="card-title"><span>⏱️</span> Sorting Algorithm Benchmark (Dataset: <?= $benchmarks['size'] ?> Elements)</h2>
        <span style="font-size: 0.8125rem; color: var(--color-text-muted);">Measured in milliseconds (ms)</span>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-3">
            <div class="stat-card">
                <div class="stat-info">
                    <span class="stat-label">QuickSort (Divide & Conquer)</span>
                    <span class="stat-value" style="color: var(--color-success);"><?= $benchmarks['quick'] ?> ms</span>
                    <span class="stat-subtext">Time Complexity: <strong>O(n log n)</strong></span>
                </div>
                <div class="stat-icon-wrapper icon-green" aria-hidden="true">⚡</div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <span class="stat-label">MergeSort (Stable Recursive)</span>
                    <span class="stat-value" style="color: var(--color-primary);"><?= $benchmarks['merge'] ?> ms</span>
                    <span class="stat-subtext">Time Complexity: <strong>O(n log n)</strong></span>
                </div>
                <div class="stat-icon-wrapper icon-blue" aria-hidden="true">🔄</div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <span class="stat-label">BubbleSort (Comparison-Based)</span>
                    <span class="stat-value" style="color: var(--color-error);"><?= $benchmarks['bubble'] ?> ms</span>
                    <span class="stat-subtext">Time Complexity: <strong>O(n²)</strong></span>
                </div>
                <div class="stat-icon-wrapper icon-amber" aria-hidden="true">🐌</div>
            </div>
        </div>
    </div>
</div>

<!-- Section 2: Data Structures Visualizer Grid -->
<div class="grid grid-cols-2" style="align-items: start;">
    <!-- DS 1: FIFO Queue -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><span>📥</span> FIFO Queue: Leave Processing Pipeline</h2>
            <span class="badge badge-info">First-In, First-Out</span>
        </div>
        <div class="card-body">
            <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1rem;">
                Employee leave requests enter at the rear and are evaluated in the order received.
            </p>
            <div id="queueDisplay">
                <div class="dsa-pipeline">
                    <div class="dsa-node active-front">
                        <div style="font-size: 0.7rem; color: #10B981; font-weight: bold;">★ FRONT (NEXT)</div>
                        <div style="font-weight: 600;">#LR-001 (Maria)</div>
                    </div>
                    <div class="dsa-pointer-arrow">&rarr;</div>
                    <div class="dsa-node">
                        <div style="font-size: 0.7rem; color: #94A3B8;">POSITION 2</div>
                        <div style="font-weight: 600;">#LR-002 (David)</div>
                    </div>
                    <div class="dsa-pointer-arrow">&rarr;</div>
                    <div class="dsa-node">
                        <div style="font-size: 0.7rem; color: #38BDF8; font-weight: bold;">REAR (ENQUEUED)</div>
                        <div style="font-weight: 600;">#LR-003 (Juan)</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer" style="font-size: 0.75rem; color: var(--color-text-muted);">
            Enforces fair, chronological approval scheduling (O(1) enqueue & dequeue).
        </div>
    </div>

    <!-- DS 2: LIFO Stack -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><span>🥞</span> LIFO Stack: Activity Undo / Audit Log</h2>
            <span class="badge badge-neutral">Last-In, First-Out</span>
        </div>
        <div class="card-body">
            <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1rem;">
                Recent operations push onto the top of the stack for instant audit rollback.
            </p>
            <div id="stackDisplay">
                <div class="dsa-node active-front" style="width: 100%; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>Approved Leave #LR-001</span>
                    <span style="font-size: 0.725rem; color: #10B981; font-weight: bold;">▲ TOP (LIFO)</span>
                </div>
                <div class="dsa-node" style="width: 100%; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>Updated Employee EMP004</span>
                    <span style="font-size: 0.725rem; color: #94A3B8;">Index [2]</span>
                </div>
                <div class="dsa-node" style="width: 100%; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>Created Department [IT]</span>
                    <span style="font-size: 0.725rem; color: #94A3B8;">Index [1]</span>
                </div>
            </div>
        </div>
        <div class="card-footer" style="font-size: 0.75rem; color: var(--color-text-muted);">
            LIFO operations provide strict O(1) push and pop performance.
        </div>
    </div>
</div>

<!-- DS 3: Binary Search Tree (BST) -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><span>🌳</span> Binary Search Tree (BST): Employee Index Tree</h2>
        <span class="badge badge-success">O(log n) Lookup & In-Order Traversal</span>
    </div>
    <div class="card-body">
        <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 1rem;">
            Hierarchical binary search tree ordering employee records by Employee Number for logarithmic search.
        </p>
        <div class="dsa-visualizer-container" id="bstDisplay">
            <div style="text-align: center; font-family: var(--font-mono); line-height: 2.2;">
                <div><span class="dsa-node active-front" style="display:inline-block;">ROOT: EMP050</span></div>
                <div>/ &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; \</div>
                <div>
                    <span class="dsa-node" style="display:inline-block;">EMP025</span> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
                    <span class="dsa-node" style="display:inline-block;">EMP075</span>
                </div>
                <div>/ &nbsp; &nbsp; &nbsp; &nbsp; \ &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; / &nbsp; &nbsp; &nbsp; &nbsp; \</div>
                <div>
                    <span class="dsa-node" style="display:inline-block; min-width:80px;">EMP010</span> &nbsp;
                    <span class="dsa-node" style="display:inline-block; min-width:80px;">EMP040</span> &nbsp; &nbsp; &nbsp;
                    <span class="dsa-node" style="display:inline-block; min-width:80px;">EMP060</span> &nbsp;
                    <span class="dsa-node" style="display:inline-block; min-width:80px;">EMP090</span>
                </div>
            </div>
            <div style="margin-top: 1.5rem; background: #1E293B; padding: 0.85rem 1.25rem; border-radius: var(--radius-md); font-size: 0.8rem; border: 1px solid #334155;">
                <strong>Recursive In-Order Traversal (Sorted Output):</strong><br>
                <code style="color: #38BDF8;">EMP010 &rarr; EMP025 &rarr; EMP040 &rarr; EMP050 &rarr; EMP060 &rarr; EMP075 &rarr; EMP090</code>
            </div>
        </div>
    </div>
</div>
