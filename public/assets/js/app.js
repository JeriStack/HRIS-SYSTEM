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

let openModalId = null;

const FOCUSABLE_SELECTOR = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function focusableIn(modal) {
    return Array.from(modal.querySelectorAll(FOCUSABLE_SELECTOR))
        .filter(el => el.offsetParent !== null || el === document.activeElement);
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    lastFocusedElement = document.activeElement;
    openModalId = modalId;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    // Move focus into the dialog (first focusable control).
    const focusable = focusableIn(modal);
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

    if (openModalId === modalId) {
        openModalId = null;
    }

    // Return focus to the control that opened the dialog.
    if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
        lastFocusedElement.focus();
    }
}

/**
 * Keyboard support for the currently open modal: Escape closes it, Tab is trapped
 * within the dialog so focus cannot escape to the page behind it (WCAG 2.4.3 / 2.1.2).
 */
function handleModalKeydown(e) {
    if (!openModalId) return;
    const modal = document.getElementById(openModalId);
    if (!modal) return;

    if (e.key === 'Escape') {
        closeModal(openModalId);
        return;
    }

    if (e.key === 'Tab') {
        const focusable = focusableIn(modal);
        if (focusable.length === 0) {
            e.preventDefault();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
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

    // Single delegated keyboard handler for Escape + focus trap.
    document.addEventListener('keydown', handleModalKeydown);
}

// ==========================================================================
// 2. Form Protection: Prevent Accidental Duplicate Submissions
// ==========================================================================
function initFormSubmitGuard() {
    document.querySelectorAll('form').forEach(form => {
        if (form.getAttribute('data-guard-initialized')) return;
        form.setAttribute('data-guard-initialized', 'true');

        // Only guard state-changing submissions. GET forms (search/filter) must stay
        // interactive and must never leave a disabled button behind.
        if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') {
            return;
        }

        form.addEventListener('submit', (e) => {
            // An inline onsubmit (e.g. confirm()/prompt()) or native validation may cancel
            // the submit. If so, do not lock the button — the form is not being sent.
            if (e.defaultPrevented) {
                return;
            }

            if (form.hasAttribute('data-submitting')) {
                e.preventDefault();
                return false;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                form.setAttribute('data-submitting', 'true');
                const originalHtml = submitBtn.innerHTML;
                submitBtn.dataset.originalHtml = originalHtml;
                submitBtn.disabled = true;
                submitBtn.classList.add('is-loading');
                submitBtn.innerHTML = `<span>⏳ Processing...</span>`;

                // Failsafe: Re-enable after 8 seconds in case of client-side validation abort
                setTimeout(() => {
                    resetFormGuard(form);
                }, 8000);
            }
        });
    });
}

/**
 * Clear the duplicate-submit guard on a form and restore its submit button.
 */
function resetFormGuard(form) {
    form.removeAttribute('data-submitting');
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('is-loading');
        if (submitBtn.dataset.originalHtml !== undefined) {
            submitBtn.innerHTML = submitBtn.dataset.originalHtml;
        }
    }
}

// When a page is restored from the back/forward cache, its forms were left "submitting"
// during navigation. Reset every guard so buttons are usable again.
window.addEventListener('pageshow', (e) => {
    if (e.persisted) {
        document.querySelectorAll('form[data-submitting]').forEach(resetFormGuard);
    }
});

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
// 4. Client-side Table Filter Helper & Dynamic Skeletons
// ==========================================================================
function initTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);

    if (input && table) {
        let debounceTimer = null;
        input.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const query = input.value.toLowerCase().trim();
                const tbody = table.querySelector('tbody');
                if (!tbody) return;

                const rows = Array.from(tbody.querySelectorAll('tr:not(.table-empty-row)'));
                let visibleCount = 0;

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    const matches = text.includes(query);
                    row.style.display = matches ? '' : 'none';
                    if (matches) visibleCount++;
                });

                // Manage empty state row
                let emptyRow = tbody.querySelector('.table-empty-row');
                if (visibleCount === 0 && query !== '') {
                    if (!emptyRow) {
                        emptyRow = document.createElement('tr');
                        emptyRow.className = 'table-empty-row';
                        const colCount = table.querySelectorAll('thead th').length || 5;
                        emptyRow.innerHTML = `<td colspan="${colCount}" class="text-center" style="padding: 2.5rem; color: var(--color-text-muted);">No records found matching "<strong>${escapeHtml(query)}</strong>".</td>`;
                        tbody.appendChild(emptyRow);
                    }
                    emptyRow.style.display = '';
                } else if (emptyRow) {
                    emptyRow.style.display = 'none';
                }
            }, 120);
        });
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Global Skeleton Helper Shortcuts
window.showTableSkeleton = function(tableSelector, rows = 5, cols = 5) {
    if (window.Skeleton) {
        return window.Skeleton.show(tableSelector, 'table', { rows, cols });
    }
    return null;
};

window.hideTableSkeleton = function(tableSelector, replacementHtml = null) {
    if (window.Skeleton) {
        return window.Skeleton.hide(tableSelector, replacementHtml);
    }
    return null;
};

window.showCardSkeleton = function(containerSelector, lines = 3) {
    if (window.Skeleton) {
        return window.Skeleton.show(containerSelector, 'card', { lines });
    }
    return null;
};

window.hideCardSkeleton = function(containerSelector, replacementHtml = null) {
    if (window.Skeleton) {
        return window.Skeleton.hide(containerSelector, replacementHtml);
    }
    return null;
};
