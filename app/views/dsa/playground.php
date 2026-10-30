<?php
/**
 * Screen A: DSA Laboratory & Interactive Playground
 * Allows selecting algorithms, supplying inputs, running genuine PHP execution, and inspecting results.
 * Employee HRIS & Leave Management System — Phase 12
 */
?>
<div class="page-header" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: #1E293B; margin: 0 0 0.25rem 0;">DSA Laboratory & Playground</h1>
        <p style="color: #64748B; margin: 0; font-size: 0.875rem;">Interactive workspace for testing and evaluating native PHP algorithms and custom data structures.</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <a href="<?= url('/dsa') ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-weight: 600;">💻 Playground</a>
        <a href="<?= url('/dsa/visualizer') ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem;">🎬 Visualizer</a>
    </div>
</div>

<!-- Category & Topic Selector Hub -->
<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="font-size: 0.75rem; font-weight: 700; color: #64748B; text-transform: uppercase; margin-bottom: 0.75rem;">Select Topic</div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;" id="topicButtonGroup">
        <optgroup label="Sorting">
            <button type="button" class="btn btn-topic <?= $currentTopic === 'bubble' ? 'active' : '' ?>" data-cat="sort" data-topic="bubble">Bubble Sort</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'selection' ? 'active' : '' ?>" data-cat="sort" data-topic="selection">Selection Sort</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'insertion' ? 'active' : '' ?>" data-cat="sort" data-topic="insertion">Insertion Sort</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'merge' ? 'active' : '' ?>" data-cat="sort" data-topic="merge">Merge Sort</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'quick' ? 'active' : '' ?>" data-cat="sort" data-topic="quick">Quick Sort</button>
        </optgroup>
        <div style="width: 1px; background: #E2E8F0; margin: 0 0.25rem;"></div>
        <optgroup label="Searching">
            <button type="button" class="btn btn-topic <?= $currentTopic === 'linear' ? 'active' : '' ?>" data-cat="search" data-topic="linear">Linear Search</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'binary' ? 'active' : '' ?>" data-cat="search" data-topic="binary">Binary Search</button>
        </optgroup>
        <div style="width: 1px; background: #E2E8F0; margin: 0 0.25rem;"></div>
        <optgroup label="Data Structures">
            <button type="button" class="btn btn-topic <?= $currentTopic === 'stack' ? 'active' : '' ?>" data-cat="stack" data-topic="stack">Stack</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'queue' ? 'active' : '' ?>" data-cat="queue" data-topic="queue">Queue</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'linked_list' ? 'active' : '' ?>" data-cat="linked_list" data-topic="linked_list">Linked List</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'hash_table' ? 'active' : '' ?>" data-cat="hash_table" data-topic="hash_table">Hash Table</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'bst' ? 'active' : '' ?>" data-cat="bst" data-topic="bst">Binary Search Tree</button>
        </optgroup>
    </div>
</div>

<!-- Main Playground Grid: Left Input & Controls | Right Result & Complexity -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; align-items: start;">
    
    <!-- Left Column: Input Data Panel & Execution Controls -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.75rem;">
            <h2 style="font-size: 1.125rem; font-weight: 600; color: #1E293B; margin: 0;" id="panelTitle">Configure Input</h2>
            <button type="button" id="btnGenerateExample" class="btn btn-secondary btn-sm" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">
                🎲 Generate Example
            </button>
        </div>

        <form id="playgroundForm">
            <input type="hidden" id="categoryField" name="category" value="sort">
            <input type="hidden" id="topicField" name="topic" value="<?= e($currentTopic) ?>">
            <input type="hidden" id="csrfToken" name="_csrf_token" value="<?= csrf_token() ?>">

            <!-- Dynamic Input Fields based on category -->
            <div id="dynamicInputsContainer">
                <!-- Sorting/Searching Array Input -->
                <div id="arrayInputGroup" style="margin-bottom: 1rem;">
                    <label for="arrayInput" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                        Input Values (comma-separated numbers, max 30):
                    </label>
                    <input type="text" id="arrayInput" class="form-input" style="width: 100%; padding: 0.6rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem;" value="42, 17, 89, 5, 23, 64, 11, 78, 36, 50">
                    <span style="font-size: 0.75rem; color: #64748B;">For Binary Search, inputs are automatically validated/sorted.</span>
                </div>

                <!-- Search Target Input -->
                <div id="targetInputGroup" style="margin-bottom: 1rem; display: none;">
                    <label for="targetInput" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                        Search Target Value:
                    </label>
                    <input type="number" id="targetInput" class="form-input" style="width: 100%; padding: 0.6rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem;" value="23">
                </div>

                <!-- Structure Operation Selectors -->
                <div id="structureInputGroup" style="margin-bottom: 1rem; display: none;">
                    <label for="operationSelect" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                        Operation:
                    </label>
                    <select id="operationSelect" class="form-input" style="width: 100%; padding: 0.6rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem; background: #FFFFFF;">
                        <!-- Injected via JS based on topic -->
                    </select>
                </div>

                <!-- Structure Value Field -->
                <div id="valueInputGroup" style="margin-bottom: 1rem; display: none;">
                    <label for="valueInput" id="valueLabel" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                        Value / Payload:
                    </label>
                    <input type="text" id="valueInput" class="form-input" style="width: 100%; padding: 0.6rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem;" value="Task Item">
                </div>

                <!-- Hash Table Key Field -->
                <div id="keyInputGroup" style="margin-bottom: 1rem; display: none;">
                    <label for="keyInput" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                        Key (e.g. EMP-001):
                    </label>
                    <input type="text" id="keyInput" class="form-input" style="width: 100%; padding: 0.6rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem;" value="EMP-001">
                </div>
            </div>

            <!-- Execution Actions -->
            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; pt: 1rem; border-top: 1px solid #F1F5F9;">
                <button type="submit" id="btnRun" class="btn btn-primary" style="flex: 1; padding: 0.65rem 1.25rem; font-weight: 600;">
                    ▶ Run Operation
                </button>
                <button type="button" id="btnReset" class="btn btn-secondary" style="padding: 0.65rem 1.25rem;">
                    ↺ Reset
                </button>
            </div>
        </form>
    </div>

    <!-- Right Column: Result Inspection & Complexity Matrix -->
    <div>
        <!-- Complexity Badge Card -->
        <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <h3 style="font-size: 0.95rem; font-weight: 700; color: #1E293B; margin: 0;" id="complexityTitle">Complexity Profile</h3>
                <span class="badge badge-info" id="complexityBadge" style="font-size: 0.7rem;">Time & Space</span>
            </div>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; text-align: center; margin-bottom: 0.75rem;">
                <div style="background: #F8FAFC; padding: 0.5rem; border-radius: 0.375rem; border: 1px solid #E2E8F0;">
                    <div style="font-size: 0.65rem; color: #64748B; font-weight: 600;">BEST</div>
                    <div style="font-size: 0.875rem; font-weight: 700; color: #059669;" id="cmpBest"><?= e($complexity['best']) ?></div>
                </div>
                <div style="background: #F8FAFC; padding: 0.5rem; border-radius: 0.375rem; border: 1px solid #E2E8F0;">
                    <div style="font-size: 0.65rem; color: #64748B; font-weight: 600;">AVERAGE</div>
                    <div style="font-size: 0.875rem; font-weight: 700; color: #2563EB;" id="cmpAvg"><?= e($complexity['avg']) ?></div>
                </div>
                <div style="background: #F8FAFC; padding: 0.5rem; border-radius: 0.375rem; border: 1px solid #E2E8F0;">
                    <div style="font-size: 0.65rem; color: #64748B; font-weight: 600;">WORST</div>
                    <div style="font-size: 0.875rem; font-weight: 700; color: #DC2626;" id="cmpWorst"><?= e($complexity['worst']) ?></div>
                </div>
                <div style="background: #F8FAFC; padding: 0.5rem; border-radius: 0.375rem; border: 1px solid #E2E8F0;">
                    <div style="font-size: 0.65rem; color: #64748B; font-weight: 600;">SPACE</div>
                    <div style="font-size: 0.875rem; font-weight: 700; color: #4F46E5;" id="cmpSpace"><?= e($complexity['space']) ?></div>
                </div>
            </div>
            <div style="font-size: 0.75rem; color: #64748B;" id="cmpNotes"><?= e($complexity['notes']) ?></div>
        </div>

        <!-- Result Inspection Card -->
        <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.5rem;">
                <h3 style="font-size: 0.95rem; font-weight: 700; color: #1E293B; margin: 0;">Execution Result</h3>
                <span id="statusBadge" class="badge badge-neutral" style="font-size: 0.7rem;">Idle</span>
            </div>

            <!-- Telemetry Bar -->
            <div id="telemetryBar" style="display: flex; gap: 1rem; font-size: 0.75rem; color: #64748B; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px dashed #E2E8F0;">
                <div>Elapsed: <strong id="statElapsed">0.00 ms</strong></div>
                <div>Operations: <strong id="statOps">0</strong></div>
                <div>Elements: <strong id="statElements">0</strong></div>
            </div>

            <!-- Result Content Area -->
            <div id="resultContent" style="background: #0F172A; color: #F8FAFC; border-radius: 0.5rem; padding: 1rem; font-family: monospace; font-size: 0.8125rem; min-height: 140px; overflow-x: auto;">
                <span style="color: #64748B;">// Select an algorithm and click "Run Operation" to view execution results.</span>
            </div>
        </div>
    </div>
</div>

<style>
.btn-topic {
    background: #F8FAFC;
    color: #475569;
    border: 1px solid #CBD5E1;
    padding: 0.4rem 0.75rem;
    border-radius: 0.375rem;
    font-size: 0.8125rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}
.btn-topic:hover {
    background: #E2E8F0;
    color: #1E293B;
}
.btn-topic.active {
    background: #2563EB;
    color: #FFFFFF;
    border-color: #1D4ED8;
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const topicButtons = document.querySelectorAll('.btn-topic');
    const categoryField = document.getElementById('categoryField');
    const topicField = document.getElementById('topicField');
    const panelTitle = document.getElementById('panelTitle');
    const form = document.getElementById('playgroundForm');
    const btnRun = document.getElementById('btnRun');
    const btnReset = document.getElementById('btnReset');
    const btnGenerateExample = document.getElementById('btnGenerateExample');
    const csrfToken = document.getElementById('csrfToken').value;

    const arrayInputGroup = document.getElementById('arrayInputGroup');
    const arrayInput = document.getElementById('arrayInput');
    const targetInputGroup = document.getElementById('targetInputGroup');
    const targetInput = document.getElementById('targetInput');
    const structureInputGroup = document.getElementById('structureInputGroup');
    const operationSelect = document.getElementById('operationSelect');
    const valueInputGroup = document.getElementById('valueInputGroup');
    const valueInput = document.getElementById('valueInput');
    const keyInputGroup = document.getElementById('keyInputGroup');
    const keyInput = document.getElementById('keyInput');

    const resultContent = document.getElementById('resultContent');
    const statusBadge = document.getElementById('statusBadge');
    const statElapsed = document.getElementById('statElapsed');
    const statOps = document.getElementById('statOps');
    const statElements = document.getElementById('statElements');

    const cmpBest = document.getElementById('cmpBest');
    const cmpAvg = document.getElementById('cmpAvg');
    const cmpWorst = document.getElementById('cmpWorst');
    const cmpSpace = document.getElementById('cmpSpace');
    const cmpNotes = document.getElementById('cmpNotes');

    const operationsMap = {
        'stack': [
            { val: 'push', label: 'Push (add to top)' },
            { val: 'pop', label: 'Pop (remove from top)' },
            { val: 'peek', label: 'Peek (read top)' },
            { val: 'clear', label: 'Clear (empty stack)' }
        ],
        'queue': [
            { val: 'enqueue', label: 'Enqueue (add to rear)' },
            { val: 'dequeue', label: 'Dequeue (remove front)' },
            { val: 'peek', label: 'Peek (read front)' },
            { val: 'clear', label: 'Clear (empty queue)' }
        ],
        'linked_list': [
            { val: 'append', label: 'Append (to tail)' },
            { val: 'prepend', label: 'Prepend (to head)' },
            { val: 'remove', label: 'Remove (first occurrence)' },
            { val: 'find', label: 'Find (by value)' },
            { val: 'clear', label: 'Clear (empty list)' }
        ],
        'hash_table': [
            { val: 'set', label: 'Set (key & value)' },
            { val: 'get', label: 'Get (lookup value)' },
            { val: 'containsKey', label: 'Contains Key' },
            { val: 'remove', label: 'Remove (delete key)' },
            { val: 'clear', label: 'Clear (empty table)' }
        ],
        'bst': [
            { val: 'insert', label: 'Insert Node' },
            { val: 'search', label: 'Search Key' },
            { val: 'delete', label: 'Delete Node' },
            { val: 'in_order', label: 'In-Order Traversal (Sorted)' },
            { val: 'pre_order', label: 'Pre-Order Traversal' },
            { val: 'post_order', label: 'Post-Order Traversal' }
        ]
    };

    function updateFormLayout(category, topic) {
        categoryField.value = category;
        topicField.value = topic;
        panelTitle.innerText = 'Configure: ' + topic.replace('_', ' ').toUpperCase();

        // Toggle input groups
        if (category === 'sort') {
            arrayInputGroup.style.display = 'block';
            targetInputGroup.style.display = 'none';
            structureInputGroup.style.display = 'none';
            valueInputGroup.style.display = 'none';
            keyInputGroup.style.display = 'none';
        } else if (category === 'search') {
            arrayInputGroup.style.display = 'block';
            targetInputGroup.style.display = 'block';
            structureInputGroup.style.display = 'none';
            valueInputGroup.style.display = 'none';
            keyInputGroup.style.display = 'none';
        } else {
            arrayInputGroup.style.display = (topic === 'bst') ? 'block' : 'none';
            targetInputGroup.style.display = 'none';
            structureInputGroup.style.display = 'block';
            keyInputGroup.style.display = (topic === 'hash_table') ? 'block' : 'none';
            valueInputGroup.style.display = (topic === 'hash_table' || topic === 'stack' || topic === 'queue' || topic === 'linked_list') ? 'block' : 'none';

            // Populate operations
            operationSelect.innerHTML = '';
            (operationsMap[topic] || []).forEach(op => {
                const opt = document.createElement('option');
                opt.value = op.val;
                opt.innerText = op.label;
                operationSelect.appendChild(opt);
            });
        }
    }

    topicButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            topicButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            updateFormLayout(this.dataset.cat, this.dataset.topic);
            loadExample(this.dataset.topic);
        });
    });

    function loadExample(topic) {
        if (['bubble', 'selection', 'insertion', 'merge', 'quick'].includes(topic)) {
            arrayInput.value = '42, 17, 89, 5, 23, 64, 11, 78, 36, 50';
        } else if (topic === 'linear') {
            arrayInput.value = '18, 42, 7, 95, 33, 61, 24, 80';
            targetInput.value = '33';
        } else if (topic === 'binary') {
            arrayInput.value = '5, 12, 19, 27, 34, 48, 59, 72, 85, 96';
            targetInput.value = '48';
        } else if (topic === 'hash_table') {
            keyInput.value = 'EMP-004';
            valueInput.value = 'David Domingo';
        } else if (topic === 'bst') {
            arrayInput.value = '50, 25, 75, 10, 40, 60, 90';
        }
    }

    btnGenerateExample.addEventListener('click', function () {
        loadExample(topicField.value);
    });

    btnReset.addEventListener('click', function () {
        resultContent.innerHTML = '<span style="color: #64748B;">// Workspace reset to initial state.</span>';
        statusBadge.className = 'badge badge-neutral';
        statusBadge.innerText = 'Idle';
        statElapsed.innerText = '0.00 ms';
        statOps.innerText = '0';
        statElements.innerText = '0';
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        btnRun.disabled = true;
        btnRun.innerText = '⏳ Executing...';
        statusBadge.className = 'badge badge-info';
        statusBadge.innerText = 'Running';

        const category = categoryField.value;
        const topic = topicField.value;
        const payload = {
            category: category,
            topic: topic,
            _csrf_token: csrfToken
        };

        if (category === 'sort' || category === 'search') {
            payload.input = arrayInput.value.split(',').map(s => parseFloat(s.trim())).filter(n => !isNaN(n));
            if (category === 'search') {
                payload.target = parseFloat(targetInput.value);
            }
        } else if (category === 'bst') {
            payload.keys = arrayInput.value.split(',').map(s => parseFloat(s.trim())).filter(n => !isNaN(n));
            payload.operation = operationSelect.value;
            payload.target = parseFloat(valueInput.value || 25);
        } else {
            payload.operation = operationSelect.value;
            payload.value = valueInput.value;
            payload.key = keyInput.value;
        }

        try {
            const resp = await fetch('<?= url('/dsa/execute') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const data = await resp.json();
            btnRun.disabled = false;
            btnRun.innerText = '▶ Run Operation';

            if (!resp.ok || !data.success) {
                statusBadge.className = 'badge badge-error';
                statusBadge.innerText = 'Error';
                resultContent.innerHTML = `<span style="color: #EF4444;">Error: ${data.error || 'Execution failed.'}</span>`;
                return;
            }

            statusBadge.className = 'badge badge-success';
            statusBadge.innerText = 'Success';

            // Update stats
            if (data.stats) {
                statElapsed.innerText = `${data.stats.elapsed_ms || 0} ms`;
                statOps.innerText = `${(data.stats.comparisons || 0) + (data.stats.swaps || 0)}`;
                statElements.innerText = `${data.stats.elements || 0}`;
            } else {
                statElapsed.innerText = '0.05 ms';
                statOps.innerText = `${data.size || 1}`;
                statElements.innerText = `${data.size || 1}`;
            }

            // Update Complexity
            if (data.complexity) {
                cmpBest.innerText = data.complexity.best || 'N/A';
                cmpAvg.innerText = data.complexity.avg || 'N/A';
                cmpWorst.innerText = data.complexity.worst || 'N/A';
                cmpSpace.innerText = data.complexity.space || 'N/A';
                cmpNotes.innerText = data.complexity.notes || '';
            }

            // Render Result
            let html = '';
            if (category === 'sort') {
                html = `<div><strong style="color: #38BDF8;">// Original Input:</strong> [${data.original.join(', ')}]</div>` +
                       `<div style="margin-top: 0.5rem;"><strong style="color: #4ADE80;">// Final Sorted:</strong> [${data.sorted.join(', ')}]</div>` +
                       `<div style="margin-top: 0.5rem; color: #94A3B8;">Executed ${data.stats.comparisons} comparisons & ${data.stats.swaps} swaps in ${data.stats.elapsed_ms} ms.</div>`;
            } else if (category === 'search') {
                html = `<div><strong style="color: #38BDF8;">// Search Target:</strong> ${data.target}</div>` +
                       `<div style="margin-top: 0.5rem;"><strong style="color: ${data.found ? '#4ADE80' : '#EF4444'};">// Outcome:</strong> ${data.found ? `Target found at index [${data.index}]` : 'Target not found in dataset'}</div>` +
                       `<div style="margin-top: 0.5rem; color: #94A3B8;">Completed in ${data.stats.comparisons} comparisons (${data.stats.elapsed_ms} ms).</div>`;
            } else if (category === 'bst') {
                html = `<div><strong style="color: #38BDF8;">// BST Operation:</strong> ${data.operation}</div>` +
                       `<div style="margin-top: 0.5rem;"><strong style="color: #4ADE80;">// Result:</strong> ${data.message}</div>` +
                       (data.traversal ? `<div style="margin-top: 0.5rem;"><strong style="color: #A78BFA;">// Traversal Order:</strong> [${data.traversal.map(n => n.key).join(' → ')}]</div>` : '') +
                       `<div style="margin-top: 0.5rem; color: #94A3B8;">Tree size: ${data.size} nodes | Height: ${data.height} levels.</div>`;
            } else {
                html = `<div><strong style="color: #38BDF8;">// Structure:</strong> ${data.structure} (${data.operation})</div>` +
                       `<div style="margin-top: 0.5rem;"><strong style="color: #4ADE80;">// Status:</strong> ${data.message}</div>` +
                       `<div style="margin-top: 0.5rem; color: #94A3B8;">State Array: ${JSON.stringify(data.after)}</div>`;
            }

            resultContent.innerHTML = html;
        } catch (err) {
            btnRun.disabled = false;
            btnRun.innerText = '▶ Run Operation';
            statusBadge.className = 'badge badge-error';
            statusBadge.innerText = 'Exception';
            resultContent.innerHTML = `<span style="color: #EF4444;">Network Error: ${err.message}</span>`;
        }
    });

    // Initialize default layout
    updateFormLayout('sort', '<?= e($currentTopic) ?>');
});
</script>
