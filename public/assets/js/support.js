/**
 * NEXORA HR - Employee Help & Support and Complaint Management UI Helpers
 * Progressive enhancement for dynamic category fields, attachment validation,
 * native <dialog> modals, accessible inline validation, and HR triage cues.
 */
(function () {
    'use strict';

    function formatBytes(bytes) {
        if (!bytes || bytes <= 0) {
            return '0 KB';
        }
        if (bytes < 1024 * 1024) {
            return Math.max(1, Math.round(bytes / 1024)) + ' KB';
        }
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function initCategoryFields() {
        var categorySelect = document.getElementById('supportCategorySelect');
        if (!categorySelect) {
            return;
        }

        var descriptionBox = document.getElementById('categoryDescriptionText');
        var prioritySelect = document.getElementById('supportPrioritySelect');
        var complaintRedirectCallout = document.getElementById('complaintSwitchCallout');
        var panels = document.querySelectorAll('[data-category-panel]');

        function syncPanels(applyDefaultPriority) {
            var chosen = categorySelect.value;
            var opt = categorySelect.options[categorySelect.selectedIndex];

            if (descriptionBox && opt) {
                var desc = opt.getAttribute('data-description') || '';
                descriptionBox.textContent = desc;
                descriptionBox.hidden = desc === '';
            }

            if (applyDefaultPriority && prioritySelect && opt) {
                var defPrio = opt.getAttribute('data-default-priority') || '';
                if (defPrio !== '') {
                    prioritySelect.value = defPrio;
                }
            }

            var isComplaint = chosen === 'Workplace Complaint';
            if (complaintRedirectCallout) {
                complaintRedirectCallout.hidden = !isComplaint;
            }

            panels.forEach(function (panel) {
                var targetCat = panel.getAttribute('data-category-panel');
                var active = targetCat === chosen;
                panel.hidden = !active;
                var inputs = panel.querySelectorAll('input, select, textarea');
                inputs.forEach(function (el) {
                    el.disabled = !active;
                });
            });
        }

        syncPanels(false);
        categorySelect.addEventListener('change', function () {
            syncPanels(true);
        });
    }

    function initAttachmentPickers() {
        var fileInputs = document.querySelectorAll('input[type="file"][data-support-files]');
        fileInputs.forEach(function (input) {
            var listId = input.getAttribute('data-file-list-id');
            var statusId = input.getAttribute('data-file-status-id');
            var listEl = listId ? document.getElementById(listId) : null;
            var statusEl = statusId ? document.getElementById(statusId) : null;
            var maxFiles = parseInt(input.getAttribute('data-max-files') || '3', 10);
            var maxBytes = parseInt(input.getAttribute('data-max-bytes') || String(5 * 1024 * 1024), 10);

            input.addEventListener('change', function () {
                var files = Array.prototype.slice.call(input.files || []);
                var errors = [];
                input.setCustomValidity('');

                if (files.length > maxFiles) {
                    errors.push('Maximum ' + maxFiles + ' files allowed per submission.');
                }

                files.forEach(function (file) {
                    if (file.size <= 0) {
                        errors.push(file.name + ' is empty.');
                    } else if (file.size > maxBytes) {
                        errors.push(file.name + ' exceeds the ' + formatBytes(maxBytes) + ' limit.');
                    }
                });

                if (listEl) {
                    listEl.innerHTML = '';
                    files.forEach(function (file) {
                        var li = document.createElement('li');
                        li.className = 'support-selected-file';
                        li.textContent = file.name + ' (' + formatBytes(file.size) + ')';
                        listEl.appendChild(li);
                    });
                    listEl.hidden = files.length === 0;
                }

                if (errors.length > 0) {
                    var message = errors.join(' ');
                    input.setCustomValidity(message);
                    if (statusEl) {
                        statusEl.textContent = message;
                        statusEl.className = 'support-file-status is-error';
                        statusEl.hidden = false;
                    }
                } else if (statusEl) {
                    statusEl.textContent = files.length > 0
                        ? files.length + ' file(s) ready for encrypted non-public upload.'
                        : '';
                    statusEl.className = 'support-file-status';
                    statusEl.hidden = files.length === 0;
                }
            });
        });
    }

    function initAccessibleFormValidation() {
        var forms = document.querySelectorAll('form[data-support-form]');

        function setDescribedByError(ctrl, errId, active) {
            if (!errId) {
                return;
            }
            var described = (ctrl.getAttribute('aria-describedby') || '')
                .split(/\s+/)
                .filter(function (id) { return id && id !== errId; });
            if (active) {
                described.push(errId);
            }
            if (described.length > 0) {
                ctrl.setAttribute('aria-describedby', described.join(' '));
            } else {
                ctrl.removeAttribute('aria-describedby');
            }
        }

        function syncControlState(ctrl, forceCheck) {
            if (!ctrl || !ctrl.checkValidity || ctrl.disabled) {
                return true;
            }
            var errId = ctrl.getAttribute('data-error-id');
            var errEl = errId ? document.getElementById(errId) : null;
            var isUserInvalid = false;
            try {
                isUserInvalid = ctrl.matches(':user-invalid');
            } catch (e) {
                isUserInvalid = false;
            }

            var showInvalid = forceCheck ? !ctrl.checkValidity() : (isUserInvalid && !ctrl.checkValidity());
            if (showInvalid) {
                ctrl.setAttribute('aria-invalid', 'true');
                if (errEl) {
                    errEl.textContent = ctrl.validationMessage || 'Please complete this field.';
                    errEl.hidden = false;
                }
                setDescribedByError(ctrl, errId, true);
                return false;
            }

            ctrl.setAttribute('aria-invalid', 'false');
            if (errEl) {
                errEl.textContent = '';
                errEl.hidden = true;
            }
            setDescribedByError(ctrl, errId, false);
            return true;
        }

        forms.forEach(function (form) {
            var liveRegion = form.querySelector('[data-form-live-status]');
            var controls = form.querySelectorAll('input, select, textarea');

            controls.forEach(function (ctrl) {
                if (ctrl.matches('input, select, textarea') && !ctrl.hasAttribute('aria-invalid')) {
                    ctrl.setAttribute('aria-invalid', 'false');
                }
            });

            form.addEventListener('blur', function (event) {
                if (event.target && event.target.matches && event.target.matches('input, select, textarea')) {
                    syncControlState(event.target, false);
                }
            }, true);

            form.addEventListener('input', function (event) {
                var t = event.target;
                if (t && t.getAttribute && t.getAttribute('aria-invalid') === 'true') {
                    syncControlState(t, true);
                }
            });

            form.addEventListener('change', function (event) {
                if (event.target && event.target.matches && event.target.matches('select, input[type="checkbox"], input[type="radio"]')) {
                    syncControlState(event.target, false);
                }
            });

            form.addEventListener('submit', function (event) {
                var invalidControls = [];
                form.querySelectorAll('input, select, textarea').forEach(function (ctrl) {
                    if (!syncControlState(ctrl, true)) {
                        invalidControls.push(ctrl);
                    }
                });

                if (invalidControls.length > 0) {
                    event.preventDefault();
                    if (liveRegion) {
                        liveRegion.textContent = invalidControls.length + ' required field(s) need attention before submitting.';
                    }
                    invalidControls[0].focus();
                    return;
                }

                var confirmDialogId = form.getAttribute('data-confirm-dialog');
                if (confirmDialogId && form.dataset.confirmedSubmit !== '1') {
                    var dialog = document.getElementById(confirmDialogId);
                    if (dialog && typeof dialog.showModal === 'function') {
                        event.preventDefault();
                        var subjEl = document.getElementById('confirmTicketSubject');
                        var catEl = document.getElementById('confirmTicketCategory');
                        var prioEl = document.getElementById('confirmTicketPriority');
                        var filesEl = document.getElementById('confirmTicketFiles');

                        var subjInput = form.querySelector('[name="subject"]');
                        var catInput = form.querySelector('[name="category"]');
                        var prioInput = form.querySelector('[name="priority"]');
                        var fileInput = form.querySelector('input[type="file"]');

                        if (subjEl && subjInput) {
                            subjEl.textContent = subjInput.value.trim() || '—';
                        }
                        if (catEl && catInput) {
                            catEl.textContent = catInput.value || '—';
                        }
                        if (prioEl && prioInput) {
                            prioEl.textContent = prioInput.value || 'Medium';
                        }
                        if (filesEl && fileInput) {
                            var count = fileInput.files ? fileInput.files.length : 0;
                            filesEl.textContent = count === 0 ? 'None' : count + ' file' + (count === 1 ? '' : 's') + ' selected';
                        }

                        var confirmBtn = dialog.querySelector('[data-confirm-submit-action]');
                        if (confirmBtn && !confirmBtn.dataset.bound) {
                            confirmBtn.dataset.bound = '1';
                            confirmBtn.addEventListener('click', function () {
                                form.dataset.confirmedSubmit = '1';
                                dialog.close();
                                form.submit();
                            });
                        }

                        dialog.showModal();
                        return;
                    }
                }

                if (liveRegion) {
                    liveRegion.textContent = '';
                }
            });
        });
    }

    function initNativeDialogs() {
        document.querySelectorAll('[data-dialog-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-dialog-open');
                var dialog = targetId ? document.getElementById(targetId) : null;
                if (dialog && typeof dialog.showModal === 'function') {
                    dialog.showModal();
                }
            });
        });

        document.querySelectorAll('[data-dialog-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var dialog = btn.closest('dialog');
                if (dialog && typeof dialog.close === 'function') {
                    dialog.close();
                }
            });
        });

        function isPointerOutsideDialog(event, dialog) {
            var r = dialog.getBoundingClientRect();
            return event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom;
        }

        document.querySelectorAll('dialog.support-dialog').forEach(function (dialog) {
            var pressedOutside = false;
            dialog.addEventListener('pointerdown', function (event) {
                pressedOutside = isPointerOutsideDialog(event, dialog);
            });
            dialog.addEventListener('pointerup', function (event) {
                if (pressedOutside && isPointerOutsideDialog(event, dialog)) {
                    dialog.close();
                }
                pressedOutside = false;
            });
        });
    }

    function initHrResponseModeCue() {
        var modeRadios = document.querySelectorAll('input[name="response_mode"][data-hr-mode-radio]');
        var modeNotice = document.getElementById('hrResponseModeNotice');
        var submitBtn = document.getElementById('hrResponseSubmitBtn');
        if (modeRadios.length === 0) {
            return;
        }

        function syncCue() {
            var checked = document.querySelector('input[name="response_mode"][data-hr-mode-radio]:checked');
            var val = checked ? checked.value : 'public_reply';

            if (modeNotice) {
                if (val === 'internal_note') {
                    modeNotice.textContent = 'Internal HR Note: Visible ONLY to authorized HR handlers. Never shown to the employee.';
                    modeNotice.className = 'support-mode-cue is-internal';
                } else if (val === 'request_info') {
                    modeNotice.textContent = 'Request Information: Visible to employee and sets ticket status to Waiting for Employee.';
                    modeNotice.className = 'support-mode-cue is-warning';
                } else {
                    modeNotice.textContent = 'Employee Response: Posted to the shared ticket timeline and notifies the employee.';
                    modeNotice.className = 'support-mode-cue is-public';
                }
            }

            if (submitBtn) {
                if (val === 'internal_note') {
                    submitBtn.textContent = 'Save Internal HR Note';
                } else if (val === 'request_info') {
                    submitBtn.textContent = 'Request Additional Information';
                } else {
                    submitBtn.textContent = 'Send Response to Employee';
                }
            }
        }

        syncCue();
        modeRadios.forEach(function (radio) {
            radio.addEventListener('change', syncCue);
        });
    }

    function initAdminSupportResponseModeCue() {
        var modeRadios = document.querySelectorAll('input[name="response_mode"][data-admin-mode-radio]');
        var modeNotice = document.getElementById('adminResponseModeNotice');
        var submitBtn = document.getElementById('adminResponseSubmitBtn');
        var targetStatusGroup = document.getElementById('adminReplyTargetStatusGroup');
        if (modeRadios.length === 0) {
            return;
        }

        function syncCue() {
            var checked = document.querySelector('input[name="response_mode"][data-admin-mode-radio]:checked');
            var val = checked ? checked.value : 'reply';

            if (modeNotice) {
                if (val === 'internal_note') {
                    modeNotice.textContent = 'Private Internal Note: Visible ONLY to Administrators. Never exposed to HR or sent in emails.';
                    modeNotice.className = 'support-mode-cue is-internal';
                } else if (val === 'info_request') {
                    modeNotice.textContent = 'Request Information: Visible to HR, notifies the submitter, and sets status to Waiting for HR.';
                    modeNotice.className = 'support-mode-cue is-warning';
                } else {
                    modeNotice.textContent = 'Official Admin Response: Visible to the submitting HR member on the shared ticket thread.';
                    modeNotice.className = 'support-mode-cue is-public';
                }
            }

            if (targetStatusGroup) {
                targetStatusGroup.hidden = val === 'internal_note';
            }

            if (submitBtn) {
                if (val === 'internal_note') {
                    submitBtn.textContent = 'Save Internal Admin Note';
                } else if (val === 'info_request') {
                    submitBtn.textContent = 'Request Additional Info from HR';
                } else {
                    submitBtn.textContent = 'Send Response to HR';
                }
            }
        }

        syncCue();
        modeRadios.forEach(function (radio) {
            radio.addEventListener('change', syncCue);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCategoryFields();
        initAttachmentPickers();
        initAccessibleFormValidation();
        initNativeDialogs();
        initHrResponseModeCue();
        initAdminSupportResponseModeCue();
    });
})();
