/**
 * Employee HRIS & Leave Management System
 * Production Form Utilities: Dynamic Working Day Calculator,
 * Server-Connected Department-to-Position Cascades & Password Toggles
 */

document.addEventListener('DOMContentLoaded', () => {
    initWorkingDayCalculator();
    initLeaveDialogs();
    initDepartmentPositionCascade();
    initPasswordToggles();
    initAccessibleValidationState();
});

/**
 * Dynamic Leave Duration Calculator & Statutory Category Panel Controller
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

    if (!startDateInput || !endDateInput || !displaySpan) {
        return;
    }

    const eligibilityBanner = document.getElementById('eligibilityStatusBanner');
    const confidentialityBanner = document.getElementById('confidentialPrivacyNotice');
    const docReqText = document.getElementById('docRequirementHint');
    const legalRefBadge = document.getElementById('selectedTypeLegalRef');
    const matScenario = document.getElementById('maternityScenario');
    const matUnpaidExt = document.getElementById('maternityUnpaidExt');
    const matFatherTransfer = document.getElementById('maternityFatherTransfer');
    const vawcExtInput = document.getElementById('vawcExtensionDays');

    const toggleConditionalPanels = (code) => {
        document.querySelectorAll('.leave-conditional-panel[data-for-code]').forEach(panel => {
            const targetCode = panel.getAttribute('data-for-code');
            const active = Boolean(code && targetCode === code);
            panel.hidden = !active;
            panel.querySelectorAll('input, select, textarea').forEach(el => {
                el.disabled = !active;
            });
        });
    };

    const getSelectedMeta = () => {
        if (!typeSelect || !typeSelect.selectedOptions.length || !typeSelect.value) {
            return {
                id: '',
                name: '',
                code: '',
                unit: 'working_days',
                model: 'annual',
                crossYear: false,
                eligible: true,
                eligibleReason: '',
                confidential: false,
                requiresDoc: false,
                docReq: '',
                legalRef: '',
                balance: null,
                allocated: null,
            };
        }
        const opt = typeSelect.selectedOptions[0];
        const rawBal = opt.getAttribute('data-balance');
        const rawAlloc = opt.getAttribute('data-allocated');
        return {
            id: opt.value,
            name: opt.getAttribute('data-name') || opt.textContent.trim(),
            code: (opt.getAttribute('data-code') || '').toUpperCase(),
            unit: opt.getAttribute('data-unit') === 'calendar_days' ? 'calendar_days' : 'working_days',
            model: opt.getAttribute('data-model') || 'annual',
            crossYear: opt.getAttribute('data-cross-year') === '1',
            eligible: opt.getAttribute('data-eligible') !== '0',
            eligibleReason: opt.getAttribute('data-eligible-reason') || '',
            confidential: opt.getAttribute('data-confidential') === '1',
            requiresDoc: opt.getAttribute('data-requires-doc') === '1',
            docReq: opt.getAttribute('data-doc-req') || '',
            legalRef: opt.getAttribute('data-legal') || '',
            balance: rawBal !== null && rawBal !== '' ? parseInt(rawBal, 10) : null,
            allocated: rawAlloc !== null && rawAlloc !== '' ? parseInt(rawAlloc, 10) : null,
        };
    };

    const computeEffectiveLimit = (meta) => {
        if (meta.model === 'unpaid') {
            return Infinity;
        }
        if (meta.code === 'MATERNITY') {
            const sc = matScenario ? matScenario.value : 'live_childbirth';
            const base = sc === 'solo_parent' ? 120 : (sc === 'miscarriage_etp' ? 60 : 105);
            const ext = matUnpaidExt ? Math.min(30, Math.max(0, parseInt(matUnpaidExt.value || '0', 10) || 0)) : 0;
            const transfer = matFatherTransfer && sc !== 'miscarriage_etp'
                ? Math.min(7, Math.max(0, parseInt(matFatherTransfer.value || '0', 10) || 0))
                : 0;
            return Math.max(1, base + ext - transfer);
        }
        if (meta.code === 'VAWC') {
            const ext = vawcExtInput ? Math.max(0, parseInt(vawcExtInput.value || '0', 10) || 0) : 0;
            return 10 + ext;
        }
        if (meta.balance !== null && !Number.isNaN(meta.balance)) {
            return meta.balance;
        }
        return Infinity;
    };

    const updateTypeContext = () => {
        const meta = getSelectedMeta();

        document.querySelectorAll('[data-balance-type-id]').forEach(row => {
            const isMatch = meta.id && row.getAttribute('data-balance-type-id') === meta.id;
            row.classList.toggle('is-selected', Boolean(isMatch));
        });

        toggleConditionalPanels(meta.code);

        if (balanceHint) {
            if (meta.id && meta.model === 'unpaid') {
                balanceHint.textContent = 'Unpaid leave (zero credit deduction)';
                balanceHint.style.display = 'inline-flex';
            } else if (meta.id && meta.balance !== null) {
                const unitWord = meta.unit === 'calendar_days' ? 'calendar days' : 'working days';
                balanceHint.textContent = `${meta.balance} of ${meta.allocated ?? meta.balance} ${unitWord} available`;
                balanceHint.style.display = 'inline-flex';
            } else {
                balanceHint.textContent = '';
                balanceHint.style.display = 'none';
            }
        }

        if (legalRefBadge) {
            if (meta.legalRef) {
                legalRefBadge.textContent = meta.legalRef;
                legalRefBadge.hidden = false;
            } else {
                legalRefBadge.textContent = '';
                legalRefBadge.hidden = true;
            }
        }

        if (eligibilityBanner) {
            if (!meta.id) {
                eligibilityBanner.hidden = true;
            } else if (!meta.eligible) {
                eligibilityBanner.className = 'leave-eligibility-banner is-blocked';
                eligibilityBanner.textContent = meta.eligibleReason || 'You do not currently meet eligibility requirements for this leave type.';
                eligibilityBanner.hidden = false;
            } else {
                eligibilityBanner.className = 'leave-eligibility-banner is-eligible';
                eligibilityBanner.textContent = meta.eligibleReason || 'Eligible under active company and Philippine statutory leave rules.';
                eligibilityBanner.hidden = false;
            }
        }

        if (confidentialityBanner) {
            confidentialityBanner.hidden = !meta.confidential;
        }

        if (docReqText) {
            if (meta.requiresDoc) {
                docReqText.textContent = meta.docReq || 'Supporting documentation is required before final HR approval.';
            } else {
                docReqText.textContent = meta.docReq || 'Optional supporting proof (PDF, JPEG, PNG, or WebP, max 5 MB).';
            }
        }

        return meta;
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
        const meta = updateTypeContext();
        const unitLabel = meta.unit === 'calendar_days' ? 'Calendar Day' : 'Working Day';

        const startVal = startDateInput.value;
        const endVal = endDateInput.value;

        if (startVal) {
            endDateInput.min = startVal;
        }

        if (!startVal || !endVal) {
            displaySpan.textContent = `0 ${unitLabel}s`;
            displaySpan.style.color = '';
            if (calcBox) {
                calcBox.classList.remove('is-ready', 'is-error');
            }
            if (errorNotice) {
                errorNotice.textContent = '';
                errorNotice.style.display = 'none';
            }
            if (submitBtn) submitBtn.disabled = false;
            if (daysHiddenInput) daysHiddenInput.value = 0;
            return;
        }

        const start = new Date(startVal + 'T00:00:00');
        const end = new Date(endVal + 'T00:00:00');

        if (start > end) {
            setValidationError('End date must be on or after the selected start date.');
            displaySpan.textContent = 'Invalid date range';
            displaySpan.style.color = 'var(--color-error)';
            if (daysHiddenInput) daysHiddenInput.value = 0;
            return;
        }

        if (!meta.crossYear && meta.model !== 'unpaid' && start.getFullYear() !== end.getFullYear()) {
            setValidationError('This leave classification cannot span across calendar years. File separate requests per year.');
            displaySpan.textContent = 'Cross-year not permitted';
            displaySpan.style.color = 'var(--color-error)';
            if (daysHiddenInput) daysHiddenInput.value = 0;
            return;
        }

        let count = 0;
        const cur = new Date(start.getTime());
        while (cur <= end) {
            if (meta.unit === 'calendar_days') {
                count++;
            } else {
                const dayOfWeek = cur.getDay();
                if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                    count++;
                }
            }
            cur.setDate(cur.getDate() + 1);
        }

        if (daysHiddenInput) daysHiddenInput.value = count;

        if (count === 0) {
            setValidationError('Selected dates fall strictly on weekends (Saturday/Sunday). Please choose at least one weekday.');
            displaySpan.textContent = '0 Working Days (Weekend Only)';
            displaySpan.style.color = 'var(--color-error)';
            return;
        }

        const effectiveLimit = computeEffectiveLimit(meta);
        if (Number.isFinite(effectiveLimit) && count > effectiveLimit && effectiveLimit < 900) {
            setValidationError(
                `Requested duration (${count} ${unitLabel.toLowerCase()}${count === 1 ? '' : 's'}) exceeds available limit (${effectiveLimit} day${effectiveLimit === 1 ? '' : 's'}).`
            );
            displaySpan.textContent = `${count} ${unitLabel}${count === 1 ? '' : 's'} (Exceeds Limit)`;
            displaySpan.style.color = 'var(--color-error)';
            return;
        }

        setValidationError('');
        displaySpan.style.color = 'var(--color-primary)';
        displaySpan.textContent = `${count} ${unitLabel}${count === 1 ? '' : 's'}`;
        if (calcBox) {
            calcBox.classList.add('is-ready');
        }
    };

    startDateInput.addEventListener('change', calculateDays);
    endDateInput.addEventListener('change', calculateDays);
    if (typeSelect) typeSelect.addEventListener('change', calculateDays);
    [matScenario, matUnpaidExt, matFatherTransfer, vawcExtInput].forEach(el => {
        if (el) el.addEventListener('change', calculateDays);
    });

    calculateDays();
}

/**
 * Native <dialog> Modals for Leave Application Preview and Policy Details
 */
function initLeaveDialogs() {
    const previewBtn = document.getElementById('previewLeaveBtn');
    const previewDialog = document.getElementById('leavePreviewDialog');
    const confirmSubmitBtn = document.getElementById('confirmSubmitLeaveBtn');
    const leaveForm = document.getElementById('leaveApplicationForm');

    if (previewBtn && previewDialog && leaveForm) {
        previewBtn.addEventListener('click', () => {
            if (typeof leaveForm.reportValidity === 'function' && !leaveForm.reportValidity()) {
                return;
            }
            const typeSelect = document.getElementById('leaveTypeSelect');
            const selectedOpt = typeSelect && typeSelect.selectedOptions.length ? typeSelect.selectedOptions[0] : null;
            const setText = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.textContent = val || '—';
            };
            setText('prevTypeName', selectedOpt ? (selectedOpt.getAttribute('data-name') || selectedOpt.textContent.trim()) : '');
            setText('prevStartDate', document.getElementById('leaveStartDate')?.value || '');
            setText('prevEndDate', document.getElementById('leaveEndDate')?.value || '');
            setText('prevDuration', document.getElementById('calculatedDaysDisplay')?.textContent || '');
            const reasonVal = document.getElementById('leaveReason')?.value || '';
            setText('prevReason', reasonVal.length > 220 ? reasonVal.slice(0, 217) + '...' : reasonVal);
            const docInput = document.getElementById('supportingDocumentInput');
            const docName = docInput && docInput.files && docInput.files[0] ? docInput.files[0].name : 'None attached';
            setText('prevDocument', docName);

            if (typeof previewDialog.showModal === 'function') {
                previewDialog.showModal();
            }
        });

        if (confirmSubmitBtn) {
            confirmSubmitBtn.addEventListener('click', () => {
                previewDialog.close();
                if (typeof leaveForm.requestSubmit === 'function') {
                    leaveForm.requestSubmit();
                } else {
                    leaveForm.submit();
                }
            });
        }
    }

    const policyDialog = document.getElementById('policyDetailsDialog');
    if (policyDialog) {
        document.querySelectorAll('[data-policy-dialog-trigger]').forEach(btn => {
            btn.addEventListener('click', () => {
                const setVal = (id, attr) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = btn.getAttribute(attr) || '—';
                };
                setVal('policyDlgTitle', 'data-policy-name');
                setVal('policyDlgCode', 'data-policy-code');
                setVal('policyDlgCategory', 'data-policy-category');
                setVal('policyDlgLegal', 'data-policy-legal');
                setVal('policyDlgQuota', 'data-policy-quota');
                setVal('policyDlgService', 'data-policy-service');
                setVal('policyDlgDocs', 'data-policy-docs');
                setVal('policyDlgNotes', 'data-policy-notes');
                if (typeof policyDialog.showModal === 'function') {
                    policyDialog.showModal();
                }
            });
        });
    }

    document.querySelectorAll('dialog[data-close-on-backdrop="1"]').forEach(dlg => {
        dlg.addEventListener('click', (ev) => {
            if (ev.target === dlg && typeof dlg.close === 'function') {
                dlg.close();
            }
        });
    });

    document.querySelectorAll('[data-dialog-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const dlg = btn.closest('dialog');
            if (dlg && typeof dlg.close === 'function') {
                dlg.close();
            }
        });
    });
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
