/**
 * Employee HRIS & Leave Management System
 * Production Global Application Script: Modals, Duplicate Submit Prevention, Toasts, DSA Engine
 */

document.addEventListener('DOMContentLoaded', () => {
    initModalHandlers();
    initFormSubmitGuard();
});

// ==========================================================================
// 1. Accessible Modal Dialog Manager
// ==========================================================================
let lastFocusedElement = null;

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    lastFocusedElement = document.activeElement;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    // Focus on first input or cancel button
    const focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable.length) {
        setTimeout(() => focusable[0].focus(), 50);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';

    if (lastFocusedElement) {
        lastFocusedElement.focus();
    }
}

function initModalHandlers() {
    // Dismiss buttons with data-dismiss or .modal-close
    document.querySelectorAll('.modal-close, [data-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const modalContainer = e.target.closest('.modal-backdrop, .modal-overlay');
            if (modalContainer) {
                closeModal(modalContainer.id);
            }
        });
    });

    // Dismiss on click outside modal card
    document.querySelectorAll('.modal-backdrop, .modal-overlay').forEach(backdrop => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                closeModal(backdrop.id);
            }
        });
    });
}

// ==========================================================================
// 2. Form Protection: Prevent Accidental Duplicate Submissions
// ==========================================================================
function initFormSubmitGuard() {
    document.querySelectorAll('form').forEach(form => {
        if (form.getAttribute('data-guard-initialized')) return;
        form.setAttribute('data-guard-initialized', 'true');

        form.addEventListener('submit', (e) => {
            if (form.hasAttribute('data-submitting')) {
                e.preventDefault();
                return false;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                form.setAttribute('data-submitting', 'true');
                const originalHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.classList.add('is-loading');
                submitBtn.innerHTML = `<span>⏳ Processing...</span>`;

                // Failsafe: Re-enable after 8 seconds in case of client-side validation abort
                setTimeout(() => {
                    form.removeAttribute('data-submitting');
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('is-loading');
                    submitBtn.innerHTML = originalHtml;
                }, 8000);
            }
        });
    });
}

// ==========================================================================
// 3. Accessible Toast Feedback Notifications
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
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');

    let icon = 'ℹ';
    if (type === 'success') icon = '✓';
    if (type === 'error')   icon = '✕';
    if (type === 'warning') icon = '⚠';

    toast.innerHTML = `
        <span style="font-weight: bold; font-size: 1.1rem;" aria-hidden="true">${icon}</span>
        <div style="flex: 1;">${message}</div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.25s ease';
        setTimeout(() => toast.remove(), 250);
    }, 4500);
}

// ==========================================================================
// 4. Client-side Table Filter Helper
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
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
}
