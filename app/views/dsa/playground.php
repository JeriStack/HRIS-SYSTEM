<?php
/**
 * DSA Laboratory & Algorithm Benchmarks View (HR & Admin Access)
 * Employee HRIS & Leave Management System — Phase 11
 */
?>
<div class="page-header" style="margin-bottom: 1.5rem;">
    <div class="page-title-group">
        <h1 style="font-size: 1.75rem; font-weight: 700; color: #1E293B; margin: 0 0 0.25rem 0;">DSA Laboratory & Advanced Demonstrator</h1>
        <p style="color: #64748B; margin: 0; font-size: 0.875rem;">Live architectural demonstration of custom Data Structures & Algorithms powering HRIS workflows.</p>
    </div>
    <div class="page-actions" style="margin-top: 0.5rem;">
        <span style="font-size: 0.75rem; font-weight: 600; color: #475569; background: #F1F5F9; padding: 0.4rem 0.75rem; border-radius: 0.375rem; border: 1px solid #CBD5E1;">
            ⚡ Academic & Engineering Showcase — Phase 11
        </span>
    </div>
</div>

<!-- Section 1: Algorithm Benchmarks Comparison -->
<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.75rem;">
        <h2 style="font-size: 1.125rem; font-weight: 600; color: #1E293B; margin: 0;"><span>⏱️</span> Sorting Algorithm Benchmark (Dataset: <?= e((string)$benchmarks['size']) ?> Elements)</h2>
        <span style="font-size: 0.8125rem; color: #64748B;">Measured in milliseconds (ms)</span>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 0.5rem; padding: 1rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: #64748B; text-transform: uppercase;">QuickSort (Divide & Conquer)</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #059669; margin: 0.25rem 0;"><?= e((string)$benchmarks['quick']) ?> ms</div>
            <div style="font-size: 0.75rem; color: #64748B;">Time Complexity: <strong>O(n log n)</strong></div>
        </div>
        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 0.5rem; padding: 1rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: #64748B; text-transform: uppercase;">MergeSort (Stable Divide & Conquer)</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #2563EB; margin: 0.25rem 0;"><?= e((string)$benchmarks['merge']) ?> ms</div>
            <div style="font-size: 0.75rem; color: #64748B;">Time Complexity: <strong>O(n log n)</strong></div>
        </div>
        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 0.5rem; padding: 1rem;">
            <div style="font-size: 0.75rem; font-weight: 600; color: #64748B; text-transform: uppercase;">BubbleSort (Iterative Swap)</div>
            <div style="font-size: 1.5rem; font-weight: 700; color: #DC2626; margin: 0.25rem 0;"><?= e((string)$benchmarks['bubble']) ?> ms</div>
            <div style="font-size: 0.75rem; color: #64748B;">Time Complexity: <strong>O(n²)</strong></div>
        </div>
    </div>
</div>

<!-- Section 2: Core Data Structures (2-Column Grid) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- DS 1: FIFO Queue -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.5rem;">
            <h2 style="font-size: 1rem; font-weight: 600; color: #1E293B; margin: 0;"><span>📥</span> FIFO Queue: Leave Approval Pipeline</h2>
            <span class="badge badge-info" style="font-size: 0.7rem;">First-In, First-Out (O(1))</span>
        </div>
        <p style="font-size: 0.8125rem; color: #64748B; margin-bottom: 1rem;">
            Pending leave requests enter at the rear and dequeue in strict chronological arrival order.
        </p>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <?php foreach ($queue->toArray() as $idx => $req): ?>
                <div style="background: <?= $idx === 0 ? '#ECFDF5' : '#F8FAFC' ?>; border: 1px solid <?= $idx === 0 ? '#A7F3D0' : '#E2E8F0' ?>; padding: 0.6rem 0.85rem; border-radius: 0.375rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem;">
                    <div>
                        <strong><?= e($req['name']) ?></strong> &middot; <span style="color: #64748B;"><?= e($req['type']) ?> (<?= e((string)$req['days']) ?>d)</span>
                    </div>
                    <?php if ($idx === 0): ?>
                        <span style="font-size: 0.7rem; font-weight: 700; color: #059669; background: #D1FAE5; padding: 0.2rem 0.5rem; border-radius: 0.25rem;">★ FRONT (NEXT)</span>
                    <?php else: ?>
                        <span style="font-size: 0.7rem; color: #94A3B8;">Position <?= $idx + 1 ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top: 0.75rem; font-size: 0.75rem; color: #64748B; border-top: 1px dashed #E2E8F0; padding-top: 0.5rem;">
            Pointer-based nodes avoid O(N) array shifts; preserves transaction-safe model validation.
        </div>
    </div>

    <!-- DS 2: LIFO Stack -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.5rem;">
            <h2 style="font-size: 1rem; font-weight: 600; color: #1E293B; margin: 0;"><span>🥞</span> LIFO Stack: Recent Activity & Audit Trail</h2>
            <span class="badge badge-neutral" style="font-size: 0.7rem;">Last-In, First-Out (O(1))</span>
        </div>
        <p style="font-size: 0.8125rem; color: #64748B; margin-bottom: 1rem;">
            Operational activities push to the top of the stack for instant chronological inspection or rollback.
        </p>
        <div style="display: flex; flex-direction: column-reverse; gap: 0.5rem;">
            <?php 
            $stackItems = $stack->toArray();
            $topIdx = count($stackItems) - 1;
            foreach ($stackItems as $idx => $log): ?>
                <div style="background: <?= $idx === $topIdx ? '#EFF6FF' : '#F8FAFC' ?>; border: 1px solid <?= $idx === $topIdx ? '#BFDBFE' : '#E2E8F0' ?>; padding: 0.6rem 0.85rem; border-radius: 0.375rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem;">
                    <div>
                        <span style="font-weight: 600; color: #1E293B;">[<?= e($log['action']) ?>]</span> <?= e($log['desc']) ?>
                    </div>
                    <?php if ($idx === $topIdx): ?>
                        <span style="font-size: 0.7rem; font-weight: 700; color: #2563EB; background: #DBEAFE; padding: 0.2rem 0.5rem; border-radius: 0.25rem;">▲ TOP (LATEST)</span>
                    <?php else: ?>
                        <span style="font-size: 0.7rem; color: #94A3B8;">Depth <?= $topIdx - $idx ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top: 0.75rem; font-size: 0.75rem; color: #64748B; border-top: 1px dashed #E2E8F0; padding-top: 0.5rem;">
            O(1) push and pop performance; latest security and operational events accessed instantly.
        </div>
    </div>
</div>

<!-- Section 3: Singly Linked List & Hash Table (2-Column Grid) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- DS 3: Singly Linked List -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.5rem;">
            <h2 style="font-size: 1rem; font-weight: 600; color: #1E293B; margin: 0;"><span>🔗</span> Linked List: Employee Career Event Chain</h2>
            <span class="badge badge-primary" style="font-size: 0.7rem;">Pointer Chain (O(1) Append)</span>
        </div>
        <p style="font-size: 0.8125rem; color: #64748B; margin-bottom: 1rem;">
            Explicit node pointers maintain an immutable chronological history of employee milestones.
        </p>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <?php foreach ($linkedList->toArray() as $idx => $evt): ?>
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.6rem 0.85rem; border-radius: 0.375rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem;">
                    <div>
                        <span style="color: #64748B; font-size: 0.75rem;">Node <?= $evt['seq'] ?> &rarr;</span>
                        <strong style="color: #1E293B; margin-left: 0.25rem;"><?= e($evt['event']) ?></strong>
                    </div>
                    <span style="font-size: 0.75rem; color: #64748B;"><?= e($evt['date']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top: 0.75rem; font-size: 0.75rem; color: #64748B; border-top: 1px dashed #E2E8F0; padding-top: 0.5rem;">
            Non-contiguous memory allocation with explicit head and tail pointer management.
        </div>
    </div>

    <!-- DS 4: Hash Table -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.5rem;">
            <h2 style="font-size: 1rem; font-weight: 600; color: #1E293B; margin: 0;"><span>🔑</span> Hash Table: Separate Chaining Index</h2>
            <span class="badge badge-success" style="font-size: 0.7rem;">Average O(1) Lookup</span>
        </div>
        <p style="font-size: 0.8125rem; color: #64748B; margin-bottom: 1rem;">
            Polynomial rolling hash with bucket linked lists for deterministic collision handling and automatic dynamic rehashing.
        </p>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <?php foreach ($hashTable->toArray() as $empNo => $emp): ?>
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.6rem 0.85rem; border-radius: 0.375rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem;">
                    <div>
                        <code style="color: #4338CA; font-weight: 700;"><?= e($empNo) ?></code> &middot;
                        <strong style="color: #1E293B;"><?= e($emp['name']) ?></strong>
                    </div>
                    <span style="font-size: 0.75rem; color: #64748B;"><?= e($emp['role']) ?> (<?= e($emp['dept']) ?>)</span>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top: 0.75rem; font-size: 0.75rem; color: #64748B; border-top: 1px dashed #E2E8F0; padding-top: 0.5rem;">
            Current capacity: <?= e((string)$hashTable->getCapacity()) ?> buckets | Load factor threshold: 0.75.
        </div>
    </div>
</div>

<!-- Section 4: Binary Search Tree & Recursive Traversals -->
<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.75rem;">
        <h2 style="font-size: 1.125rem; font-weight: 600; color: #1E293B; margin: 0;"><span>🌳</span> Binary Search Tree (BST) & Recursive Traversals (RecursionHelper)</h2>
        <span class="badge badge-success" style="font-size: 0.7rem;">Height: <?= e((string)$traversals['height']) ?> Levels | O(log n) Average Search</span>
    </div>

    <div style="text-align: center; font-family: monospace; line-height: 2; margin-bottom: 1.5rem; background: #F8FAFC; padding: 1.25rem; border-radius: 0.5rem; border: 1px solid #E2E8F0; font-size: 0.875rem;">
        <div><span style="background: #E0E7FF; color: #3730A3; padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-weight: 700;">ROOT: EMP-050</span></div>
        <div style="color: #94A3B8;">/ &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; \</div>
        <div>
            <span style="background: #F1F5F9; padding: 0.2rem 0.6rem; border-radius: 0.25rem;">EMP-025</span> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
            <span style="background: #F1F5F9; padding: 0.2rem 0.6rem; border-radius: 0.25rem;">EMP-075</span>
        </div>
        <div style="color: #94A3B8;">/ &nbsp; &nbsp; &nbsp; &nbsp; \ &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; / &nbsp; &nbsp; &nbsp; &nbsp; \</div>
        <div>
            <span style="background: #F1F5F9; padding: 0.2rem 0.5rem; border-radius: 0.25rem;">EMP-010</span> &nbsp;
            <span style="background: #F1F5F9; padding: 0.2rem 0.5rem; border-radius: 0.25rem;">EMP-040</span> &nbsp; &nbsp; &nbsp;
            <span style="background: #F1F5F9; padding: 0.2rem 0.5rem; border-radius: 0.25rem;">EMP-060</span> &nbsp;
            <span style="background: #F1F5F9; padding: 0.2rem 0.5rem; border-radius: 0.25rem;">EMP-090</span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
        <div style="background: #0F172A; color: #F8FAFC; padding: 1rem; border-radius: 0.5rem; font-size: 0.8125rem;">
            <div style="color: #38BDF8; font-weight: 700; margin-bottom: 0.5rem;">Recursive In-Order (LNR) &mdash; Sorted:</div>
            <code><?= e(implode(' → ', array_column($traversals['inOrder'], 'key'))) ?></code>
            <div style="color: #94A3B8; font-size: 0.7rem; margin-top: 0.4rem;">Produces strict ascending order.</div>
        </div>
        <div style="background: #0F172A; color: #F8FAFC; padding: 1rem; border-radius: 0.5rem; font-size: 0.8125rem;">
            <div style="color: #A78BFA; font-weight: 700; margin-bottom: 0.5rem;">Recursive Pre-Order (NLR) &mdash; Root First:</div>
            <code><?= e(implode(' → ', array_column($traversals['preOrder'], 'key'))) ?></code>
            <div style="color: #94A3B8; font-size: 0.7rem; margin-top: 0.4rem;">Used for hierarchical serialization.</div>
        </div>
        <div style="background: #0F172A; color: #F8FAFC; padding: 1rem; border-radius: 0.5rem; font-size: 0.8125rem;">
            <div style="color: #34D399; font-weight: 700; margin-bottom: 0.5rem;">Recursive Post-Order (LRN) &mdash; Bottom-Up:</div>
            <code><?= e(implode(' → ', array_column($traversals['postOrder'], 'key'))) ?></code>
            <div style="color: #94A3B8; font-size: 0.7rem; margin-top: 0.4rem;">Used for bottom-up evaluations.</div>
        </div>
    </div>
</div>
