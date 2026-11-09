/**
 * Employee HRIS & Leave Management System
 * Production Form Utilities: Dynamic Working Day Calculator,
 * Server-Connected Department-to-Position Cascades & Password Toggles
 */

document.addEventListener('DOMContentLoaded', () => {
    initWorkingDayCalculator();
    initDepartmentPositionCascade();
    initPasswordToggles();
    initAccessibleValidationState();
});

/**
 * Dynamic Working Days Calculator (Monday-Friday, excluding weekends)
 */
function initWorkingDayCalculator() {
    const startDateInput = document.getElementById('leaveStartDate') || document.querySelector('input[name="start_date"]');
    const endDateInput = document.getElementById('leaveEndDate') || document.querySelector('input[name="end_date"]');
    const displaySpan = document.getElementById('calculatedDaysDisplay');
    const daysHiddenInput = document.getElementById('numberOfDaysInput') || document.querySelector('input[name="number_of_days"]');
    const errorNotice = document.getElementById('dateValidationError');
    const calcBox = document.getElementById('leaveCalcBox');
    const balanceHint = document.getElementById('selectedTypeBalanceHint');
    const submitBtn = document.getElementById('submitLeaveBtn');
    const typeSelect = document.getElementById('leaveTypeSelect');

    if (startDateInput && endDateInput && displaySpan) {
        const updateTypeContext = () => {
            if (!typeSelect || !typeSelect.selectedOptions.length) return;
            const opt = typeSelect.selectedOptions[0];
            const bal = opt.getAttribute('data-balance');
            const alloc = opt.getAttribute('data-allocated');
            const typeId = opt.value;

            document.querySelectorAll('[data-balance-type-id]').forEach(row => {
                const isMatch = typeId && row.getAttribute('data-balance-type-id') === typeId;
                row.classList.toggle('is-selected', Boolean(isMatch));
            });

            if (balanceHint) {
                if (typeId && bal !== null) {
                    balanceHint.textContent = `${bal} of ${alloc || bal} days available`;
                    balanceHint.style.display = 'inline-flex';
                } else {
                    balanceHint.textContent = '';
                    balanceHint.style.display = 'none';
                }
            }
        };

        const setValidationError = (msg) => {
            if (errorNotice) {
                errorNotice.textContent = msg;
                errorNotice.style.display = msg ? 'block' : 'none';
            }
            if (calcBox) {
                calcBox.classList.toggle('is-error', Boolean(msg));
                if (msg) calcBox.classList.remove('is-ready');
            }
            startDateInput.setAttribute('aria-invalid', msg ? 'true' : 'false');
            endDateInput.setAttribute('aria-invalid', msg ? 'true' : 'false');
            if (submitBtn) {
                submitBtn.disabled = Boolean(msg);
            }
        };

        const calculateDays = () => {
            updateTypeContext();

            const startVal = startDateInput.value;
            const endVal = endDateInput.value;

            if (startVal) {
                endDateInput.min = startVal;
            }

            if (!startVal || !endVal) {
                displaySpan.textContent = "0 Working Days";
                displaySpan.style.color = "";
                if (calcBox) {
                    calcBox.classList.remove('is-ready', 'is-error');
                }
                if (errorNotice) {
                    errorNotice.textContent = "";
                    errorNotice.style.display = "none";
                }
                if (submitBtn) submitBtn.disabled = false;
                if (daysHiddenInput) daysHiddenInput.value = 0;
                return;
            }

            const start = new Date(startVal + 'T00:00:00');
            const end = new Date(endVal + 'T00:00:00');

            if (start > end) {
                setValidationError("End date must be on or after the selected start date.");
                displaySpan.textContent = "Invalid date range";
                displaySpan.style.color = "var(--color-error)";
                if (daysHiddenInput) daysHiddenInput.value = 0;
                return;
            }

            // Count business days (Mon-Fri)
            let count = 0;
            let cur = new Date(start.getTime());

            while (cur <= end) {
                const dayOfWeek = cur.getDay(); // 0 = Sun, 6 = Sat
                if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                    count++;
                }
                cur.setDate(cur.getDate() + 1);
            }

            if (daysHiddenInput) daysHiddenInput.value = count;

            if (count === 0) {
                setValidationError("Selected dates fall strictly on weekends (Saturday/Sunday). Please choose at least one weekday.");
                displaySpan.textContent = "0 Working Days (Weekend Only)";
                displaySpan.style.color = "var(--color-error)";
                return;
            }

            // Check against remaining leave balance if attribute exists
            if (typeSelect && typeSelect.selectedOptions.length) {
                const rawBal = typeSelect.selectedOptions[0].getAttribute('data-balance');
                const max = rawBal !== null ? parseInt(rawBal, 10) : 999;
                if (!Number.isNaN(max) && count > max && max < 900) {
                    setValidationError(`Requested duration (${count} working days) exceeds your remaining entitlement (${max} available day${max === 1 ? '' : 's'}).`);
                    displaySpan.textContent = `${count} Working Day${count === 1 ? '' : 's'} (Exceeds Balance)`;
                    displaySpan.style.color = "var(--color-error)";
                    return;
                }
            }

            setValidationError("");
            displaySpan.style.color = "var(--color-primary)";
            displaySpan.textContent = `${count} Working Day${count === 1 ? '' : 's'}`;
            if (calcBox) {
                calcBox.classList.add('is-ready');
            }
        };

        startDateInput.addEventListener('change', calculateDays);
        endDateInput.addEventListener('change', calculateDays);
        if (typeSelect) typeSelect.addEventListener('change', calculateDays);

        // Run initial calculation when pre-selected or restored
        calculateDays();
    }
}

/**
 * Synchronize aria-invalid and fallback class on blurred invalid inputs
 */
function initAccessibleValidationState() {
    document.querySelectorAll('.form-control, .form-input, .form-select, .form-textarea').forEach(field => {
        field.addEventListener('blur', () => {
            if (typeof field.checkValidity === 'function' && field.value !== '') {
                const valid = field.checkValidity();
                field.classList.toggle('user-invalid-fallback', !valid);
                field.setAttribute('aria-invalid', valid ? 'false' : 'true');
            }
        });
        field.addEventListener('input', () => {
            if (field.classList.contains('user-invalid-fallback') && typeof field.checkValidity === 'function' && field.checkValidity()) {
                field.classList.remove('user-invalid-fallback');
                field.setAttribute('aria-invalid', 'false');
            }
        });
    });
}

/**
 * Server-Connected Department -> Position Cascading Dropdown
 */
function initDepartmentPositionCascade() {
    const deptSelect = document.getElementById('departmentSelect') || document.querySelector('select[name="department_id"]');
    const posSelect = document.getElementById('positionSelect') || document.querySelector('select[name="position_id"]');

    if (deptSelect && posSelect) {
        // If the element already has pre-rendered data-dept options, let local DOM filter handle it
        if (posSelect.querySelector('option[data-dept]')) {
            return;
        }

        deptSelect.addEventListener('change', async () => {
            const deptId = deptSelect.value;
            if (!deptId) {
                posSelect.innerHTML = '<option value="">-- Select Department First --</option>';
                posSelect.disabled = true;
                return;
            }

            posSelect.disabled = true;
            const posParent = posSelect.parentElement;
            let skeletonEl = posParent ? posParent.querySelector('.pos-skeleton') : null;
            if (!skeletonEl && posParent) {
                skeletonEl = document.createElement('div');
                skeletonEl.className = 'skeleton-input pos-skeleton';
                skeletonEl.setAttribute('aria-hidden', 'true');
                skeletonEl.style.display = 'none';
                posSelect.insertAdjacentElement('afterend', skeletonEl);
            }
            if (skeletonEl) {
                posSelect.style.display = 'none';
                skeletonEl.style.display = 'block';
                if (posParent) posParent.setAttribute('aria-busy', 'true');
            } else {
                posSelect.innerHTML = '<option value="">Loading job positions...</option>';
            }

            try {
                // Dynamically resolve base URL from meta tag or window global
                const metaBase = document.querySelector('meta[name="base-url"]')?.getAttribute('content');
                const baseUrl = metaBase ? metaBase.replace(/\/$/, '') : '';
                const endpoint = `${baseUrl}/hr/api/positions-by-department/${deptId}`;

                const res = await fetch(endpoint, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error('Network error loading positions');
                const data = await res.json();
                const positions = data.positions || [];

                posSelect.innerHTML = '<option value="">-- Select Position --</option>';
                if (positions.length === 0) {
                    posSelect.innerHTML += '<option value="" disabled>No active positions in this department</option>';
                } else {
                    positions.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.position_name;
                        posSelect.appendChild(opt);
                    });
                }
                posSelect.disabled = false;
            } catch (err) {
                console.error(err);
                posSelect.innerHTML = '<option value="">-- Failed to load positions --</option>';
            } finally {
                if (skeletonEl) {
                    skeletonEl.style.display = 'none';
                    posSelect.style.display = '';
                    if (posParent) posParent.removeAttribute('aria-busy');
                }
            }
        });
    }
}

/**
 * Password Visibility Toggles
 */
function initPasswordToggles() {
    document.querySelectorAll('[data-toggle="password"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const target = document.getElementById(targetId);
            if (target) {
                const isPass = target.type === 'password';
                target.type = isPass ? 'text' : 'password';
                btn.textContent = isPass ? 'Hide' : 'Show';
                btn.setAttribute('aria-label', isPass ? 'Hide password' : 'Show password');
            }
        });
    });
}
