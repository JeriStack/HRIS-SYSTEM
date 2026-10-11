/**
 * Employee HRIS & Leave Management System
 * Form Validations, Dynamic Working-Day Calculator & Dependent Dropdowns
 */

document.addEventListener('DOMContentLoaded', () => {
    initWorkingDayCalculator();
    initDepartmentPositionDropdowns();
    initPasswordToggles();
});

// Dynamic Working Days Calculator (Monday through Friday, excluding weekends)
function initWorkingDayCalculator() {
    const startDateInput = document.getElementById('leaveStartDate');
    const endDateInput = document.getElementById('leaveEndDate');
    const displaySpan = document.getElementById('calculatedDaysDisplay');
    const errorNotice = document.getElementById('dateValidationError');

    if (startDateInput && endDateInput && displaySpan) {
        const calculateDays = () => {
            const startVal = startDateInput.value;
            const endVal = endDateInput.value;

            if (!startVal || !endVal) {
                displaySpan.textContent = "0 Working Days";
                return;
            }

            const start = new Date(startVal);
            const end = new Date(endVal);

            if (start > end) {
                if (errorNotice) {
                    errorNotice.textContent = "Start date must be earlier than or equal to End date.";
                    errorNotice.style.display = "block";
                }
                displaySpan.textContent = "Invalid date sequence";
                displaySpan.style.color = "var(--color-error)";
                return;
            }

            if (errorNotice) {
                errorNotice.style.display = "none";
            }
            displaySpan.style.color = "var(--color-text-primary)";

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
            
            // Check balance limit if balance element exists
            const maxDaysEl = document.getElementById('availableDaysNotice');
            if (maxDaysEl && count > 0) {
                const max = parseInt(maxDaysEl.dataset.remaining || "12", 10);
                if (count > max) {
                    displaySpan.textContent += ` — Exceeds ${max} days balance!`;
                    displaySpan.style.color = "var(--color-error)";
                }
            }
        };

        startDateInput.addEventListener('change', calculateDays);
        endDateInput.addEventListener('change', calculateDays);
    }
}

// Dependent Department -> Position Dropdown
function initDepartmentPositionDropdowns() {
    const deptSelect = document.getElementById('departmentSelect');
    const posSelect = document.getElementById('positionSelect');

    if (deptSelect && posSelect) {
        deptSelect.addEventListener('change', () => {
            const selectedDeptId = parseInt(deptSelect.value, 10);
            posSelect.innerHTML = '<option value="">-- Select Position --</option>';

            if (!selectedDeptId) {
                posSelect.disabled = true;
                return;
            }

            const filteredPositions = PrototypeData.positions.filter(p => p.departmentId === selectedDeptId);
            filteredPositions.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                posSelect.appendChild(opt);
            });

            posSelect.disabled = false;
        });
    }
}

// Password Visibility Toggle
function initPasswordToggles() {
    const toggles = document.querySelectorAll('.password-toggle-btn');
    toggles.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetInputId = btn.dataset.target;
            const input = document.getElementById(targetInputId);
            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.textContent = '🙈';
                    btn.setAttribute('aria-label', 'Hide password');
                } else {
                    input.type = 'password';
                    btn.textContent = '👁️';
                    btn.setAttribute('aria-label', 'Show password');
                }
            }
        });
    });
}
