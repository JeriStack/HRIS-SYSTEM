/**
 * Employee HRIS & Leave Management System
 * Global Application Controller, Modals, Toasts & DSA Simulation
 */

// ==========================================================================
// 1. Toast Notification Manager
// ==========================================================================
function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    let icon = 'ℹ';
    if (type === 'success') icon = '✓';
    if (type === 'error') icon = '✕';
    if (type === 'warning') icon = '⚠';

    toast.innerHTML = `
        <span style="font-weight: bold; font-size: 1.1rem;">${icon}</span>
        <div style="flex: 1;">${message}</div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.25s ease';
        setTimeout(() => toast.remove(), 250);
    }, 4000);
}

// ==========================================================================
// 2. Modal Controller
// ==========================================================================
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
    }
}

// Global dismiss listeners for modal backdrop and close buttons
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal-close, [data-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const backdrop = e.target.closest('.modal-backdrop');
            if (backdrop) {
                closeModal(backdrop.id);
            }
        });
    });

    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                closeModal(backdrop.id);
            }
        });
    });
});

// ==========================================================================
// 3. Client-Side Table Search & Filter Helper
// ==========================================================================
function initTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);

    if (input && table) {
        input.addEventListener('keyup', () => {
            const query = input.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
}

// ==========================================================================
// 4. DSA Laboratory Simulator (In-Memory Demonstrations)
// ==========================================================================
const DsaLab = {
    // 4.1 LIFO Stack
    stackData: ["Viewed Leave Report", "Added Employee EMP010", "Updated Employee EMP003", "Approved Leave LR005"],
    pushStack(item) {
        if (!item) return;
        this.stackData.push(item);
        this.renderStack();
        showToast(`[Stack] Pushed item onto stack (Top = "${item}")`, 'info');
    },
    popStack() {
        if (this.stackData.length === 0) {
            showToast("[Stack] Underflow: Stack is empty!", 'error');
            return null;
        }
        const popped = this.stackData.pop();
        this.renderStack();
        showToast(`[Stack] Popped item from top: "${popped}"`, 'warning');
        return popped;
    },
    renderStack() {
        const el = document.getElementById('stackDisplay');
        if (!el) return;
        if (this.stackData.length === 0) {
            el.innerHTML = '<div class="empty-state-text" style="padding: 1rem;">Stack is Empty (LIFO)</div>';
            return;
        }
        let html = '';
        for (let i = this.stackData.length - 1; i >= 0; i--) {
            const isTop = i === this.stackData.length - 1;
            html += `
                <div class="dsa-node ${isTop ? 'active-front' : ''}" style="width: 100%; margin-bottom: 0.5rem; display: flex; justify-content: space-between;">
                    <span>${this.stackData[i]}</span>
                    <span style="font-size: 0.75rem; color: #94A3B8;">${isTop ? '▲ TOP (LIFO)' : `Index [${i}]`}</span>
                </div>
            `;
        }
        el.innerHTML = html;
    },

    // 4.2 FIFO Queue
    queueData: ["LR001 - Maria Santos", "LR002 - David Cruz", "LR003 - Juan Dela Cruz"],
    enqueue(item) {
        if (!item) return;
        this.queueData.push(item);
        this.renderQueue();
        showToast(`[Queue] Enqueued "${item}" at rear (FIFO)`, 'info');
    },
    dequeue() {
        if (this.queueData.length === 0) {
            showToast("[Queue] Underflow: Queue is empty!", 'error');
            return null;
        }
        const item = this.queueData.shift();
        this.renderQueue();
        showToast(`[Queue] Dequeued "${item}" from front (FIFO)`, 'success');
        return item;
    },
    renderQueue() {
        const el = document.getElementById('queueDisplay');
        if (!el) return;
        if (this.queueData.length === 0) {
            el.innerHTML = '<div class="empty-state-text" style="padding: 1rem;">Queue is Empty (FIFO)</div>';
            return;
        }
        let html = '<div class="dsa-pipeline">';
        this.queueData.forEach((item, index) => {
            const isFront = index === 0;
            const isRear = index === this.queueData.length - 1;
            html += `
                <div class="dsa-node ${isFront ? 'active-front' : ''}">
                    <div style="font-size: 0.7rem; color: #38BDF8;">${isFront ? '★ FRONT' : (isRear ? 'REAR' : `POS ${index+1}`)}</div>
                    <div style="font-weight: 600;">${item}</div>
                </div>
            `;
            if (index < this.queueData.length - 1) {
                html += '<div class="dsa-pointer-arrow">➔</div>';
            }
        });
        html += '</div>';
        el.innerHTML = html;
    },

    // 4.3 Binary Search Tree (BST)
    bstNodes: [
        { id: "EMP050", left: "EMP025", right: "EMP075" },
        { id: "EMP025", left: "EMP010", right: "EMP040" },
        { id: "EMP075", left: "EMP060", right: "EMP090" }
    ],
    renderBst() {
        const el = document.getElementById('bstDisplay');
        if (!el) return;
        el.innerHTML = `
            <div style="text-align: center; font-family: var(--font-mono); line-height: 2;">
                <div><span class="dsa-node active-front" style="display:inline-block;">ROOT: EMP050</span></div>
                <div>/ &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; \\</div>
                <div>
                    <span class="dsa-node" style="display:inline-block;">EMP025</span> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
                    <span class="dsa-node" style="display:inline-block;">EMP075</span>
                </div>
                <div>/ &nbsp; &nbsp; &nbsp; \\ &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; / &nbsp; &nbsp; &nbsp; \\</div>
                <div>
                    <span class="dsa-node" style="display:inline-block; min-width:80px;">EMP010</span>
                    <span class="dsa-node" style="display:inline-block; min-width:80px;">EMP040</span>
                    <span class="dsa-node" style="display:inline-block; min-width:80px;">EMP060</span>
                    <span class="dsa-node" style="display:inline-block; min-width:80px;">EMP090</span>
                </div>
            </div>
            <div style="margin-top: 1.5rem; background: #1E293B; padding: 0.85rem; border-radius: 6px; font-size: 0.8rem;">
                <strong>Recursive In-Order Traversal (Sorted Output):</strong><br>
                <code>EMP010 ➔ EMP025 ➔ EMP040 ➔ EMP050 ➔ EMP060 ➔ EMP075 ➔ EMP090</code>
            </div>
        `;
    }
};

// Auto-render simulators if elements present on page
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('stackDisplay')) DsaLab.renderStack();
    if (document.getElementById('queueDisplay')) DsaLab.renderQueue();
    if (document.getElementById('bstDisplay')) DsaLab.renderBst();
});
