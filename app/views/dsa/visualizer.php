<?php
/**
 * Screen B: DSA Step-by-Step Interactive Visualizer
 * Provides step-by-step observable animation for Sorting, Searching, and Data Structures.
 * Powered by genuine PHP execution traces from DsaTraceService.
 * Employee HRIS & Leave Management System — Phase 12
 */
?>
<div class="page-header" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: #1E293B; margin: 0 0 0.25rem 0;">DSA Step-by-Step Visualizer</h1>
        <p style="color: #64748B; margin: 0; font-size: 0.875rem;">Step-by-step visual animation powered by genuine native PHP execution traces.</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <a href="<?= url('/dsa') ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem;">💻 Playground</a>
        <a href="<?= url('/dsa/visualizer') ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-weight: 600;">🎬 Visualizer</a>
    </div>
</div>

<!-- Category & Topic Selector Hub -->
<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="font-size: 0.75rem; font-weight: 700; color: #64748B; text-transform: uppercase; margin-bottom: 0.75rem;">Select Topic to Visualize</div>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;" id="visualizerTopics">
        <optgroup label="Sorting">
            <button type="button" class="btn btn-topic <?= $currentTopic === 'quick' ? 'active' : '' ?>" data-cat="sort" data-topic="quick">Quick Sort</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'merge' ? 'active' : '' ?>" data-cat="sort" data-topic="merge">Merge Sort</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'bubble' ? 'active' : '' ?>" data-cat="sort" data-topic="bubble">Bubble Sort</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'selection' ? 'active' : '' ?>" data-cat="sort" data-topic="selection">Selection Sort</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'insertion' ? 'active' : '' ?>" data-cat="sort" data-topic="insertion">Insertion Sort</button>
        </optgroup>
        <div style="width: 1px; background: #E2E8F0; margin: 0 0.25rem;"></div>
        <optgroup label="Searching">
            <button type="button" class="btn btn-topic <?= $currentTopic === 'binary' ? 'active' : '' ?>" data-cat="search" data-topic="binary">Binary Search</button>
            <button type="button" class="btn btn-topic <?= $currentTopic === 'linear' ? 'active' : '' ?>" data-cat="search" data-topic="linear">Linear Search</button>
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

<!-- Configuration & Trigger Bar -->
<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" id="visCategory" value="sort">
        <input type="hidden" id="visTopic" value="<?= e($currentTopic) ?>">
        <input type="hidden" id="csrfToken" value="<?= csrf_token() ?>">

        <div style="flex: 2; min-width: 260px;" id="visArrayGroup">
            <label for="visInputArray" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                Dataset Elements (comma-separated, max 30):
            </label>
            <input type="text" id="visInputArray" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem;" value="42, 17, 89, 5, 23, 64, 11, 78, 36, 50">
        </div>

        <div style="flex: 1; min-width: 120px; display: none;" id="visTargetGroup">
            <label for="visInputTarget" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                Target:
            </label>
            <input type="number" id="visInputTarget" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem;" value="23">
        </div>

        <div style="flex: 1; min-width: 140px; display: none;" id="visOpGroup">
            <label for="visInputOp" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                Operation:
            </label>
            <select id="visInputOp" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem; background: #FFF;">
                <!-- dynamic -->
            </select>
        </div>

        <div style="flex: 1; min-width: 140px; display: none;" id="visValGroup">
            <label for="visInputVal" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #475569; margin-bottom: 0.25rem;">
                Value:
            </label>
            <input type="text" id="visInputVal" class="form-input" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 0.375rem; font-size: 0.875rem;" value="New Node">
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <button type="button" id="btnVisExample" class="btn btn-secondary" style="padding: 0.55rem 0.85rem; font-size: 0.8125rem;">
                🎲 Example
            </button>
            <button type="button" id="btnVisFetch" class="btn btn-primary" style="padding: 0.55rem 1.25rem; font-weight: 600; font-size: 0.8125rem;">
                ⚡ Load Trace
            </button>
        </div>
    </div>
</div>

<!-- Visualizer Main Studio -->
<div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    
    <!-- Studio Header & Step Tracker -->
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #F1F5F9; padding-bottom: 0.75rem; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h2 id="studioTitle" style="font-size: 1.125rem; font-weight: 700; color: #1E293B; margin: 0;">Quick Sort Visualizer</h2>
            <div id="stepCounterText" style="font-size: 0.8125rem; color: #64748B;">Step 0 of 0</div>
        </div>
        
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <span id="visStatusBadge" class="badge badge-neutral" style="font-size: 0.75rem;">Idle</span>
        </div>
    </div>

    <!-- Playback Controls Bar -->
    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <button type="button" id="btnPlayPause" class="btn btn-primary btn-sm" style="font-weight: 600; min-width: 90px;" disabled>
                ▶ Play
            </button>
            <button type="button" id="btnPrevStep" class="btn btn-secondary btn-sm" disabled>
                ⏮ Prev
            </button>
            <button type="button" id="btnNextStep" class="btn btn-secondary btn-sm" disabled>
                ⏭ Next
            </button>
            <button type="button" id="btnResetTrace" class="btn btn-secondary btn-sm" disabled>
                ↺ Reset
            </button>
        </div>

        <!-- Timeline Progress Slider -->
        <div style="flex: 1; max-width: 400px; display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 0.75rem; color: #64748B; font-weight: 600;">Step:</span>
            <input type="range" id="traceSlider" min="0" max="0" value="0" style="flex: 1; cursor: pointer;" disabled>
            <span id="sliderValue" style="font-size: 0.75rem; color: #1E293B; font-weight: 700; min-width: 30px; text-align: right;">0</span>
        </div>

        <!-- Animation Speed Control -->
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <label for="speedSelect" style="font-size: 0.75rem; color: #64748B; font-weight: 600;">Speed:</label>
            <select id="speedSelect" style="padding: 0.25rem 0.5rem; border: 1px solid #CBD5E1; border-radius: 0.25rem; font-size: 0.75rem; background: #FFF;">
                <option value="1200">0.5x (Slow)</option>
                <option value="600" selected>1.0x (Normal)</option>
                <option value="250">2.0x (Fast)</option>
                <option value="100">4.0x (Ultra)</option>
            </select>
        </div>
    </div>

    <!-- Visual Canvas Area -->
    <div id="visualCanvas" style="min-height: 280px; background: #0F172A; border-radius: 0.5rem; padding: 1.5rem; display: flex; flex-direction: column; justify-content: center; align-items: center; position: relative; overflow-x: auto;">
        <div id="emptyNotice" style="color: #64748B; font-size: 0.875rem; text-align: center;">
            Click <strong>"⚡ Load Trace"</strong> to execute the native PHP algorithm and stream step-by-step frames.
        </div>
        <div id="dynamicStage" style="width: 100%; height: 100%; display: none;"></div>
    </div>

    <!-- Step Narration Box -->
    <div style="margin-top: 1rem; background: #F1F5F9; border-left: 4px solid #2563EB; border-radius: 0.375rem; padding: 0.75rem 1rem;">
        <div style="font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 0.25rem;">Current Action:</div>
        <div id="stepNarrationText" style="font-size: 0.875rem; color: #1E293B; font-family: monospace;">No trace loaded.</div>
    </div>
</div>

<!-- Telemetry & Complexity Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start;">
    
    <!-- Live Telemetry Card -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h3 style="font-size: 0.95rem; font-weight: 700; color: #1E293B; margin: 0 0 0.75rem 0;">Algorithm Telemetry</h3>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
            <div style="background: #F8FAFC; padding: 0.6rem; border-radius: 0.375rem; border: 1px solid #E2E8F0;">
                <div style="font-size: 0.65rem; color: #64748B; font-weight: 600;">TOTAL STEPS</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: #1E293B;" id="telTotalSteps">0</div>
            </div>
            <div style="background: #F8FAFC; padding: 0.6rem; border-radius: 0.375rem; border: 1px solid #E2E8F0;">
                <div style="font-size: 0.65rem; color: #64748B; font-weight: 600;">COMPARISONS</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: #2563EB;" id="telComparisons">0</div>
            </div>
            <div style="background: #F8FAFC; padding: 0.6rem; border-radius: 0.375rem; border: 1px solid #E2E8F0;">
                <div style="font-size: 0.65rem; color: #64748B; font-weight: 600;">SWAPS / OPS</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: #DC2626;" id="telSwaps">0</div>
            </div>
            <div style="background: #F8FAFC; padding: 0.6rem; border-radius: 0.375rem; border: 1px solid #E2E8F0;">
                <div style="font-size: 0.65rem; color: #64748B; font-weight: 600;">SERVER TIME</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: #059669;" id="telElapsed">0.00 ms</div>
            </div>
        </div>
    </div>

    <!-- Complexity Profile Card -->
    <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 0.75rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h3 style="font-size: 0.95rem; font-weight: 700; color: #1E293B; margin: 0 0 0.75rem 0;">Complexity Profile</h3>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; text-align: center; margin-bottom: 0.75rem;">
            <div style="background: #F8FAFC; padding: 0.4rem; border-radius: 0.375rem; border: 1px solid #E2E8F0;">
                <div style="font-size: 0.6rem; color: #64748B; font-weight: 600;">BEST</div>
                <div style="font-size: 0.8rem; font-weight: 700; color: #059669;" id="visCmpBest"><?= e($complexity['best']) ?></div>
            </div>
            <div style="background: #F8FAFC; padding: 0.4rem; border-radius: 0.375rem; border: 1px solid #E2E8F0;">
                <div style="font-size: 0.6rem; color: #64748B; font-weight: 600;">AVERAGE</div>
                <div style="font-size: 0.8rem; font-weight: 700; color: #2563EB;" id="visCmpAvg"><?= e($complexity['avg']) ?></div>
            </div>
            <div style="background: #F8FAFC; padding: 0.4rem; border-radius: 0.375rem; border: 1px solid #E2E8F0;">
                <div style="font-size: 0.6rem; color: #64748B; font-weight: 600;">WORST</div>
                <div style="font-size: 0.8rem; font-weight: 700; color: #DC2626;" id="visCmpWorst"><?= e($complexity['worst']) ?></div>
            </div>
            <div style="background: #F8FAFC; padding: 0.4rem; border-radius: 0.375rem; border: 1px solid #E2E8F0;">
                <div style="font-size: 0.6rem; color: #64748B; font-weight: 600;">SPACE</div>
                <div style="font-size: 0.8rem; font-weight: 700; color: #4F46E5;" id="visCmpSpace"><?= e($complexity['space']) ?></div>
            </div>
        </div>
        <div style="font-size: 0.75rem; color: #64748B;" id="visCmpNotes"><?= e($complexity['notes']) ?></div>
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

/* Visualizer Bars */
.sort-bar-container {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 8px;
    height: 220px;
    width: 100%;
}
.sort-bar {
    width: 32px;
    background: #3B82F6;
    border-radius: 4px 4px 0 0;
    transition: height 0.2s ease, background-color 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    padding-top: 4px;
    font-size: 0.7rem;
    font-weight: 700;
    color: #FFFFFF;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
}
.sort-bar-label {
    margin-top: 6px;
    font-size: 0.65rem;
    color: #94A3B8;
    text-align: center;
}
.bar-compare {
    background: #F59E0B !important; /* Amber */
}
.bar-swap {
    background: #EF4444 !important; /* Rose Red */
}
.bar-pivot {
    background: #8B5CF6 !important; /* Violet */
}
.bar-sorted {
    background: #10B981 !important; /* Emerald */
}

/* Search Cells */
.search-cell-row {
    display: flex;
    gap: 8px;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    padding: 1rem;
}
.search-cell {
    width: 48px;
    height: 48px;
    background: #1E293B;
    border: 2px solid #475569;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    color: #F8FAFC;
    font-weight: 700;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    position: relative;
}
.search-pointer {
    position: absolute;
    top: -22px;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 1px 4px;
    border-radius: 3px;
    text-transform: uppercase;
}
.pointer-low { background: #3B82F6; color: #FFF; }
.pointer-mid { background: #F59E0B; color: #000; }
.pointer-high { background: #8B5CF6; color: #FFF; }

/* Data Structures Stage */
.struct-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    min-height: 200px;
    width: 100%;
}
.struct-item {
    background: #1E293B;
    border: 1px solid #334155;
    color: #F8FAFC;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const topicButtons = document.querySelectorAll('#visualizerTopics .btn-topic');
    const visCategory = document.getElementById('visCategory');
    const visTopic = document.getElementById('visTopic');
    const csrfToken = document.getElementById('csrfToken').value;

    const visArrayGroup = document.getElementById('visArrayGroup');
    const visInputArray = document.getElementById('visInputArray');
    const visTargetGroup = document.getElementById('visTargetGroup');
    const visInputTarget = document.getElementById('visInputTarget');
    const visOpGroup = document.getElementById('visOpGroup');
    const visInputOp = document.getElementById('visInputOp');
    const visValGroup = document.getElementById('visValGroup');
    const visInputVal = document.getElementById('visInputVal');

    const btnVisExample = document.getElementById('btnVisExample');
    const btnVisFetch = document.getElementById('btnVisFetch');
    const studioTitle = document.getElementById('studioTitle');
    const stepCounterText = document.getElementById('stepCounterText');
    const visStatusBadge = document.getElementById('visStatusBadge');

    const btnPlayPause = document.getElementById('btnPlayPause');
    const btnPrevStep = document.getElementById('btnPrevStep');
    const btnNextStep = document.getElementById('btnNextStep');
    const btnResetTrace = document.getElementById('btnResetTrace');
    const traceSlider = document.getElementById('traceSlider');
    const sliderValue = document.getElementById('sliderValue');
    const speedSelect = document.getElementById('speedSelect');

    const emptyNotice = document.getElementById('emptyNotice');
    const dynamicStage = document.getElementById('dynamicStage');
    const stepNarrationText = document.getElementById('stepNarrationText');

    const telTotalSteps = document.getElementById('telTotalSteps');
    const telComparisons = document.getElementById('telComparisons');
    const telSwaps = document.getElementById('telSwaps');
    const telElapsed = document.getElementById('telElapsed');

    const visCmpBest = document.getElementById('visCmpBest');
    const visCmpAvg = document.getElementById('visCmpAvg');
    const visCmpWorst = document.getElementById('visCmpWorst');
    const visCmpSpace = document.getElementById('visCmpSpace');
    const visCmpNotes = document.getElementById('visCmpNotes');

    let currentTrace = null;
    let currentStepIdx = 0;
    let isPlaying = false;
    let playTimer = null;

    const operationsMap = {
        'stack': [
            { val: 'push', label: 'Push (add to top)' },
            { val: 'pop', label: 'Pop (remove from top)' },
            { val: 'peek', label: 'Peek (read top)' },
            { val: 'clear', label: 'Clear' }
        ],
        'queue': [
            { val: 'enqueue', label: 'Enqueue (add to rear)' },
            { val: 'dequeue', label: 'Dequeue (remove front)' },
            { val: 'peek', label: 'Peek (read front)' },
            { val: 'clear', label: 'Clear' }
        ],
        'linked_list': [
            { val: 'append', label: 'Append (tail)' },
            { val: 'prepend', label: 'Prepend (head)' },
            { val: 'remove', label: 'Remove value' },
            { val: 'find', label: 'Find value' }
        ],
        'hash_table': [
            { val: 'set', label: 'Set Key-Value' },
            { val: 'get', label: 'Get Value' },
            { val: 'containsKey', label: 'Contains Key' },
            { val: 'remove', label: 'Remove Key' }
        ],
        'bst': [
            { val: 'in_order', label: 'In-Order Traversal (LNR)' },
            { val: 'pre_order', label: 'Pre-Order Traversal (NLR)' },
            { val: 'post_order', label: 'Post-Order Traversal (LRN)' },
            { val: 'insert', label: 'Insert Node' },
            { val: 'search', label: 'Search Key' }
        ]
    };

    function updateTopicLayout(category, topic) {
        visCategory.value = category;
        visTopic.value = topic;
        studioTitle.innerText = topic.replace('_', ' ').toUpperCase() + ' VISUALIZER';

        if (category === 'sort') {
            visArrayGroup.style.display = 'block';
            visTargetGroup.style.display = 'none';
            visOpGroup.style.display = 'none';
            visValGroup.style.display = 'none';
        } else if (category === 'search') {
            visArrayGroup.style.display = 'block';
            visTargetGroup.style.display = 'block';
            visOpGroup.style.display = 'none';
            visValGroup.style.display = 'none';
        } else {
            visArrayGroup.style.display = (topic === 'bst') ? 'block' : 'none';
            visTargetGroup.style.display = 'none';
            visOpGroup.style.display = 'block';
            visValGroup.style.display = 'block';

            visInputOp.innerHTML = '';
            (operationsMap[topic] || []).forEach(op => {
                const opt = document.createElement('option');
                opt.value = op.val;
                opt.innerText = op.label;
                visInputOp.appendChild(opt);
            });
        }
    }

    function loadExample(topic) {
        if (['bubble', 'selection', 'insertion', 'merge', 'quick'].includes(topic)) {
            visInputArray.value = '42, 17, 89, 5, 23, 64, 11, 78, 36, 50';
        } else if (topic === 'linear') {
            visInputArray.value = '18, 42, 7, 95, 33, 61, 24, 80';
            visInputTarget.value = '33';
        } else if (topic === 'binary') {
            visInputArray.value = '5, 12, 19, 27, 34, 48, 59, 72, 85, 96';
            visInputTarget.value = '48';
        } else if (topic === 'bst') {
            visInputArray.value = '50, 25, 75, 10, 40, 60, 90';
            visInputVal.value = '40';
        } else if (topic === 'hash_table') {
            visInputVal.value = 'EMP-004: David';
        } else {
            visInputVal.value = 'New Item';
        }
    }

    topicButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            pausePlayback();
            topicButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            updateTopicLayout(this.dataset.cat, this.dataset.topic);
            loadExample(this.dataset.topic);
            resetVisualizer();
        });
    });

    btnVisExample.addEventListener('click', function () {
        loadExample(visTopic.value);
    });

    function resetVisualizer() {
        pausePlayback();
        currentTrace = null;
        currentStepIdx = 0;
        btnPlayPause.disabled = true;
        btnPrevStep.disabled = true;
        btnNextStep.disabled = true;
        btnResetTrace.disabled = true;
        traceSlider.disabled = true;
        traceSlider.value = 0;
        traceSlider.max = 0;
        sliderValue.innerText = '0';
        stepCounterText.innerText = 'Step 0 of 0';
        visStatusBadge.className = 'badge badge-neutral';
        visStatusBadge.innerText = 'Idle';
        stepNarrationText.innerText = 'Trace reset. Click "Load Trace" to begin.';
        emptyNotice.style.display = 'block';
        dynamicStage.style.display = 'none';
        dynamicStage.innerHTML = '';
        telTotalSteps.innerText = '0';
        telComparisons.innerText = '0';
        telSwaps.innerText = '0';
        telElapsed.innerText = '0.00 ms';
    }

    btnVisFetch.addEventListener('click', async function () {
        pausePlayback();
        btnVisFetch.disabled = true;
        btnVisFetch.innerText = '⚡ Loading...';
        visStatusBadge.className = 'badge badge-info';
        visStatusBadge.innerText = 'Executing PHP';

        const category = visCategory.value;
        const topic = visTopic.value;
        const payload = {
            category: category,
            topic: topic,
            _csrf_token: csrfToken
        };

        if (category === 'sort' || category === 'search') {
            payload.input = visInputArray.value.split(',').map(s => parseFloat(s.trim())).filter(n => !isNaN(n));
            if (category === 'search') {
                payload.target = parseFloat(visInputTarget.value);
            }
        } else if (category === 'bst') {
            payload.keys = visInputArray.value.split(',').map(s => parseFloat(s.trim())).filter(n => !isNaN(n));
            payload.operation = visInputOp.value;
            payload.target = parseFloat(visInputVal.value || 40);
        } else {
            payload.operation = visInputOp.value;
            payload.value = visInputVal.value;
            payload.key = 'KEY-1';
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
            btnVisFetch.disabled = false;
            btnVisFetch.innerText = '⚡ Load Trace';

            if (!resp.ok || !data.success) {
                visStatusBadge.className = 'badge badge-error';
                visStatusBadge.innerText = 'Error';
                stepNarrationText.innerText = 'Error: ' + (data.error || 'Execution failed.');
                return;
            }

            visStatusBadge.className = 'badge badge-success';
            visStatusBadge.innerText = 'Trace Loaded';

            // Complexity updates
            if (data.complexity) {
                visCmpBest.innerText = data.complexity.best || 'N/A';
                visCmpAvg.innerText = data.complexity.avg || 'N/A';
                visCmpWorst.innerText = data.complexity.worst || 'N/A';
                visCmpSpace.innerText = data.complexity.space || 'N/A';
                visCmpNotes.innerText = data.complexity.notes || '';
            }

            // Normalizing trace steps for player
            currentTrace = data;
            preparePlayback(data);
        } catch (e) {
            btnVisFetch.disabled = false;
            btnVisFetch.innerText = '⚡ Load Trace';
            visStatusBadge.className = 'badge badge-error';
            visStatusBadge.innerText = 'Network Error';
            stepNarrationText.innerText = 'Failed to load execution trace: ' + e.message;
        }
    });

    function preparePlayback(data) {
        let steps = [];
        if (data.steps && Array.isArray(data.steps) && data.steps.length > 0) {
            steps = data.steps;
        } else {
            // Synthetic frames for basic structures
            steps = [
                { type: 'init', state: data.before || [], message: `Initial state before ${data.operation}` },
                { type: 'action', state: data.after || [], message: data.message || `Executed ${data.operation}` }
            ];
            if (data.traversal) {
                steps = data.traversal.map((n, idx) => ({
                    type: 'traverse',
                    node: n,
                    index: idx,
                    message: `Visited node key ${n.key} (Step ${idx + 1} of ${data.traversal.length})`
                }));
            }
        }

        currentTrace.playbackSteps = steps;
        currentStepIdx = 0;

        // Telemetry update
        telTotalSteps.innerText = steps.length;
        if (data.stats) {
            telComparisons.innerText = data.stats.comparisons || 0;
            telSwaps.innerText = data.stats.swaps || 0;
            telElapsed.innerText = (data.stats.elapsed_ms || 0.05) + ' ms';
        } else {
            telComparisons.innerText = data.size || 1;
            telSwaps.innerText = 1;
            telElapsed.innerText = '0.05 ms';
        }

        emptyNotice.style.display = 'none';
        dynamicStage.style.display = 'block';

        btnPlayPause.disabled = false;
        btnPrevStep.disabled = false;
        btnNextStep.disabled = false;
        btnResetTrace.disabled = false;
        traceSlider.disabled = false;
        traceSlider.min = 0;
        traceSlider.max = steps.length - 1;
        traceSlider.value = 0;

        renderFrame(0);
    }

    function renderFrame(stepIdx) {
        if (!currentTrace || !currentTrace.playbackSteps || !currentTrace.playbackSteps[stepIdx]) return;

        currentStepIdx = stepIdx;
        traceSlider.value = stepIdx;
        sliderValue.innerText = stepIdx;
        stepCounterText.innerText = `Step ${stepIdx + 1} of ${currentTrace.playbackSteps.length}`;

        const step = currentTrace.playbackSteps[stepIdx];
        stepNarrationText.innerText = step.message || 'Executing step...';

        const category = visCategory.value;
        const topic = visTopic.value;

        if (category === 'sort') {
            renderSortFrame(step);
        } else if (category === 'search') {
            renderSearchFrame(step);
        } else if (topic === 'bst') {
            renderBstFrame(step);
        } else {
            renderStructureFrame(step);
        }
    }

    function renderSortFrame(step) {
        const state = step.state || [];
        const maxVal = Math.max(...state, 1);
        const comparing = (step.type === 'compare') ? (step.indices || []) : [];
        const swapping = (step.type === 'swap' || step.type === 'shift') ? (step.indices || []) : [];
        const isPivot = (step.type === 'partition_pivot') ? [step.pivot_index] : [];
        const isDone = (step.type === 'complete');

        let html = '<div class="sort-bar-container">';
        state.forEach((val, idx) => {
            const heightPercent = Math.max(12, Math.round((val / maxVal) * 190));
            let extraClass = '';
            if (isDone) extraClass = 'bar-sorted';
            else if (swapping.includes(idx)) extraClass = 'bar-swap';
            else if (comparing.includes(idx)) extraClass = 'bar-compare';
            else if (isPivot.includes(idx)) extraClass = 'bar-pivot';

            html += `<div style="display: flex; flex-direction: column; align-items: center;">
                <div class="sort-bar ${extraClass}" style="height: ${heightPercent}px;">
                    <span>${val}</span>
                </div>
                <div class="sort-bar-label">[${idx}]</div>
            </div>`;
        });
        html += '</div>';
        dynamicStage.innerHTML = html;
    }

    function renderSearchFrame(step) {
        const state = currentTrace.dataset || currentTrace.input || [];
        const currentIdx = step.index !== undefined ? step.index : (step.mid !== undefined ? step.mid : -1);
        const low = step.low !== undefined ? step.low : -1;
        const high = step.high !== undefined ? step.high : -1;

        let html = '<div class="search-cell-row">';
        state.forEach((val, idx) => {
            let borderColor = '#475569';
            let bg = '#1E293B';
            let pointers = '';

            if (idx === low) pointers += '<span class="search-pointer pointer-low">L</span>';
            if (idx === currentIdx && step.type === 'interval') pointers += '<span class="search-pointer pointer-mid">MID</span>';
            if (idx === high) pointers += '<span class="search-pointer pointer-high">H</span>';

            if (step.type === 'found' && idx === step.index) {
                borderColor = '#10B981';
                bg = '#065F46';
            } else if (idx === currentIdx) {
                borderColor = '#F59E0B';
                bg = '#78350F';
            } else if (low !== -1 && high !== -1 && (idx < low || idx > high)) {
                bg = '#0F172A';
                borderColor = '#1E293B';
            }

            html += `<div class="search-cell" style="background: ${bg}; border-color: ${borderColor};">
                ${pointers}
                <span>${val}</span>
                <span style="font-size: 0.6rem; color: #94A3B8; font-weight: normal;">[${idx}]</span>
            </div>`;
        });
        html += '</div>';
        dynamicStage.innerHTML = html;
    }

    function renderBstFrame(step) {
        if (currentTrace.traversal) {
            let html = '<div style="display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 1rem;">';
            html += '<div style="font-size: 0.8rem; color: #94A3B8;">Recursive Traversal Sequence:</div>';
            html += '<div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;">';
            currentTrace.traversal.forEach((node, idx) => {
                const active = (step.index !== undefined && idx <= step.index);
                const isCurrent = (step.index === idx);
                const bg = isCurrent ? '#2563EB' : (active ? '#10B981' : '#334155');
                html += `<div style="background: ${bg}; color: #FFF; padding: 0.5rem 0.75rem; border-radius: 6px; font-weight: 700; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.2);">
                    ${node.key}
                </div>`;
            });
            html += '</div></div>';
            dynamicStage.innerHTML = html;
        } else {
            dynamicStage.innerHTML = `<div style="color: #F8FAFC; text-align: center; padding: 2rem;">
                <div style="font-size: 1.25rem; font-weight: 700; color: #38BDF8;">BST Tree Root [50]</div>
                <div style="margin-top: 1rem; color: #94A3B8;">${step.message}</div>
            </div>`;
        }
    }

    function renderStructureFrame(step) {
        const state = step.state || [];
        const topic = visTopic.value;
        let html = '<div class="struct-container">';

        if (state.length === 0) {
            html += '<div style="color: #64748B; font-style: italic;">[Empty Structure]</div>';
        } else {
            if (topic === 'stack') {
                html += '<div style="display: flex; flex-direction: column-reverse; gap: 6px; min-width: 180px;">';
                state.forEach((val, idx) => {
                    const isTop = (idx === state.length - 1);
                    html += `<div class="struct-item" style="border-left: 4px solid ${isTop ? '#2563EB' : '#64748B'};">
                        <span>${isTop ? '▲ [TOP]' : `[${idx}]`}</span>
                        <strong style="color: #FFF;">${val}</strong>
                    </div>`;
                });
                html += '</div>';
            } else if (topic === 'queue') {
                html += '<div style="display: flex; gap: 8px; flex-wrap: wrap;">';
                state.forEach((val, idx) => {
                    const isFront = (idx === 0);
                    const isRear = (idx === state.length - 1);
                    let badge = `[${idx}]`;
                    if (isFront && isRear) badge = '★ FRONT/REAR';
                    else if (isFront) badge = '★ FRONT';
                    else if (isRear) badge = '► REAR';

                    html += `<div class="struct-item" style="border-top: 3px solid ${isFront ? '#10B981' : '#3B82F6'};">
                        <span style="font-size: 0.65rem; color: #94A3B8;">${badge}</span>
                        <strong style="color: #FFF;">${val}</strong>
                    </div>`;
                });
                html += '</div>';
            } else {
                html += '<div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">';
                state.forEach((val, idx) => {
                    html += `<div class="struct-item">
                        <strong style="color: #38BDF8;">${val}</strong>
                    </div>`;
                    if (idx < state.length - 1) {
                        html += '<span style="color: #64748B; font-weight: 700;">→</span>';
                    }
                });
                html += '</div>';
            }
        }

        html += '</div>';
        dynamicStage.innerHTML = html;
    }

    // Playback control handlers
    function play() {
        if (!currentTrace || !currentTrace.playbackSteps) return;
        if (currentStepIdx >= currentTrace.playbackSteps.length - 1) {
            currentStepIdx = 0;
        }
        isPlaying = true;
        btnPlayPause.innerText = '⏸ Pause';
        visStatusBadge.className = 'badge badge-primary';
        visStatusBadge.innerText = 'Playing';

        const speed = parseInt(speedSelect.value, 10) || 600;
        playTimer = setInterval(() => {
            if (currentStepIdx < currentTrace.playbackSteps.length - 1) {
                renderFrame(currentStepIdx + 1);
            } else {
                pausePlayback();
                visStatusBadge.className = 'badge badge-success';
                visStatusBadge.innerText = 'Completed';
            }
        }, speed);
    }

    function pausePlayback() {
        isPlaying = false;
        if (playTimer) clearInterval(playTimer);
        playTimer = null;
        btnPlayPause.innerText = '▶ Play';
        if (visStatusBadge.innerText === 'Playing') {
            visStatusBadge.className = 'badge badge-neutral';
            visStatusBadge.innerText = 'Paused';
        }
    }

    btnPlayPause.addEventListener('click', function () {
        if (isPlaying) {
            pausePlayback();
        } else {
            play();
        }
    });

    btnNextStep.addEventListener('click', function () {
        pausePlayback();
        if (currentTrace && currentTrace.playbackSteps && currentStepIdx < currentTrace.playbackSteps.length - 1) {
            renderFrame(currentStepIdx + 1);
        }
    });

    btnPrevStep.addEventListener('click', function () {
        pausePlayback();
        if (currentTrace && currentTrace.playbackSteps && currentStepIdx > 0) {
            renderFrame(currentStepIdx - 1);
        }
    });

    btnResetTrace.addEventListener('click', function () {
        pausePlayback();
        if (currentTrace && currentTrace.playbackSteps) {
            renderFrame(0);
        }
    });

    traceSlider.addEventListener('input', function () {
        pausePlayback();
        renderFrame(parseInt(this.value, 10));
    });

    speedSelect.addEventListener('change', function () {
        if (isPlaying) {
            pausePlayback();
            play();
        }
    });

    // Initialize default layout
    updateTopicLayout('sort', '<?= e($currentTopic) ?>');
});
</script>
