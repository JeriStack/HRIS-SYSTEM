/**
 * Employee HRIS & Leave Management System
 * Production Form Utilities: Dynamic Working Day Calculator,
 * Server-Connected Department-to-Position Cascades & Password Toggles
 */

document.addEventListener('DOMContentLoaded', () => {
    initWorkingDayCalculator();
    initDepartmentPositionCascade();
    initPasswordToggles();
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

    if (startDateInput && endDateInput && displaySpan) {
        const calculateDays = () => {
            const startVal = startDateInput.value;
            const endVal = endDateInput.value;

            if (!startVal || !endVal) {
                displaySpan.textContent = "0 Working Days";
                if (daysHiddenInput) daysHiddenInput.value = 0;
                return;
            }

            const start = new Date(startVal + 'T00:00:00');
            const end = new Date(endVal + 'T00:00:00');

            if (start > end) {
                if (errorNotice) {
                    errorNotice.textContent = "Start date must be earlier than or equal to End date.";
                    errorNotice.style.display = "block";
                }
                displaySpan.textContent = "Invalid date sequence";
                displaySpan.style.color = "var(--color-error)";
                if (daysHiddenInput) daysHiddenInput.value = 0;
                return;
            }

            if (errorNotice) {
                errorNotice.style.display = "none";
            }
            displaySpan.style.color = "var(--color-text-main)";

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

            displaySpan.textContent = `${count} Working Day${count === 1 ? '' : 's'} (Excludes Weekends)`;
            if (daysHiddenInput) daysHiddenInput.value = count;

            // Check against remaining leave balance if attribute exists
            const typeSelect = document.getElementById('leaveTypeSelect');
            if (typeSelect && typeSelect.selectedOptions.length) {
                const max = parseInt(typeSelect.selectedOptions[0].getAttribute('data-balance') || "999", 10);
                if (count > max && max < 900) {
                    displaySpan.textContent += ` — Warning: Exceeds ${max} available days!`;
                    displaySpan.style.color = "var(--color-error)";
                }
            }
        };

        startDateInput.addEventListener('change', calculateDays);
        endDateInput.addEventListener('change', calculateDays);
        const typeSelect = document.getElementById('leaveTypeSelect');
        if (typeSelect) typeSelect.addEventListener('change', calculateDays);
    }
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
