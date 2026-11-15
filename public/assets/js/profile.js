/**
 * NEXORA HR — Profile Enhancement & Photo Cropper Interactions
 * Accessible modal dialogs, canvas square photo cropper, and dynamic correction fields.
 */
(function () {
    'use strict';

    function safeJsonParse(raw, fallback) {
        if (!raw) {
            return fallback;
        }
        try {
            return JSON.parse(raw);
        } catch (err) {
            return fallback;
        }
    }

    function openDialogById(id) {
        if (!id) {
            return null;
        }
        var dlg = document.getElementById(id);
        if (!dlg || typeof dlg.showModal !== 'function') {
            return null;
        }
        if (!dlg.open) {
            dlg.showModal();
        }
        return dlg;
    }

    function initDialogs() {
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-open-dialog]');
            if (trigger) {
                var targetId = trigger.getAttribute('data-open-dialog');
                var dlg = openDialogById(targetId);
                var prefillField = trigger.getAttribute('data-correction-field');
                if (dlg && prefillField) {
                    var fieldSelect = dlg.querySelector('[data-correction-select]');
                    if (fieldSelect) {
                        fieldSelect.value = prefillField;
                        fieldSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                return;
            }

            var closeTrigger = event.target.closest('[data-close-dialog]');
            if (closeTrigger) {
                var ownerDlg = closeTrigger.closest('dialog');
                if (ownerDlg && ownerDlg.open) {
                    ownerDlg.close();
                }
            }
        });

        document.querySelectorAll('dialog.profile-dialog').forEach(function (dlg) {
            dlg.addEventListener('click', function (event) {
                var rect = dlg.getBoundingClientRect();
                var clickedInDialog =
                    rect.top <= event.clientY &&
                    event.clientY <= rect.top + rect.height &&
                    rect.left <= event.clientX &&
                    event.clientX <= rect.left + rect.width;
                if (!clickedInDialog && dlg.open) {
                    dlg.close();
                }
            });
        });

        document.querySelectorAll('dialog[data-auto-open="true"]').forEach(function (dlg) {
            if (typeof dlg.showModal === 'function' && !dlg.open) {
                dlg.showModal();
            }
        });
    }

    function initAvatarCropper() {
        var form = document.getElementById('avatarUploadForm');
        var fileInput = document.getElementById('avatarFileInput');
        var canvas = document.getElementById('avatarCropCanvas');
        var previewCanvas = document.getElementById('avatarPreviewCanvas');
        var zoomInput = document.getElementById('avatarZoomInput');
        var resetBtn = document.getElementById('avatarResetCropBtn');
        var workspace = document.getElementById('avatarCropWorkspace');
        var clientError = document.getElementById('avatarClientError');
        var submitBtn = document.getElementById('avatarSaveBtn');
        var cropXInput = document.getElementById('cropXInput');
        var cropYInput = document.getElementById('cropYInput');
        var cropSizeInput = document.getElementById('cropSizeInput');

        if (!form || !fileInput || !canvas || !previewCanvas || !zoomInput) {
            return;
        }

        var ctx = canvas.getContext('2d');
        var previewCtx = previewCanvas.getContext('2d');
        var maxBytes = parseInt(form.getAttribute('data-max-bytes') || '5242880', 10);
        var allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        var img = null;
        var objectUrl = null;
        var zoom = 1;
        var offsetX = 0;
        var offsetY = 0;
        var dragging = false;
        var dragStartX = 0;
        var dragStartY = 0;
        var submittingCropped = false;

        function setError(message) {
            if (!clientError) {
                return;
            }
            if (message) {
                clientError.textContent = message;
                clientError.hidden = false;
            } else {
                clientError.textContent = '';
                clientError.hidden = true;
            }
        }

        function clampOffsets() {
            if (!img) {
                return;
            }
            var stage = canvas.width;
            var baseScale = Math.max(stage / img.naturalWidth, stage / img.naturalHeight);
            var drawnW = img.naturalWidth * baseScale * zoom;
            var drawnH = img.naturalHeight * baseScale * zoom;
            var maxPanX = Math.max(0, (drawnW - stage) / 2);
            var maxPanY = Math.max(0, (drawnH - stage) / 2);
            offsetX = Math.max(-maxPanX, Math.min(maxPanX, offsetX));
            offsetY = Math.max(-maxPanY, Math.min(maxPanY, offsetY));
        }

        function computeSourceCrop() {
            if (!img) {
                return { x: 0, y: 0, size: 0 };
            }
            var stage = canvas.width;
            var baseScale = Math.max(stage / img.naturalWidth, stage / img.naturalHeight);
            var effScale = baseScale * zoom;
            var drawnW = img.naturalWidth * effScale;
            var drawnH = img.naturalHeight * effScale;
            var originX = (stage - drawnW) / 2 + offsetX;
            var originY = (stage - drawnH) / 2 + offsetY;

            var srcX = Math.max(0, -originX / effScale);
            var srcY = Math.max(0, -originY / effScale);
            var srcSize = Math.min(stage / effScale, img.naturalWidth - srcX, img.naturalHeight - srcY);

            return {
                x: Math.round(srcX),
                y: Math.round(srcY),
                size: Math.max(1, Math.round(srcSize))
            };
        }

        function renderCrop() {
            if (!ctx || !previewCtx || !img) {
                return;
            }
            clampOffsets();

            var stage = canvas.width;
            var baseScale = Math.max(stage / img.naturalWidth, stage / img.naturalHeight);
            var effScale = baseScale * zoom;
            var drawnW = img.naturalWidth * effScale;
            var drawnH = img.naturalHeight * effScale;
            var dx = (stage - drawnW) / 2 + offsetX;
            var dy = (stage - drawnH) / 2 + offsetY;

            ctx.clearRect(0, 0, stage, stage);
            ctx.drawImage(img, dx, dy, drawnW, drawnH);

            // Soft rule-of-thirds overlay + circular avatar guide
            ctx.save();
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.72)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(stage / 2, stage / 2, stage / 2 - 4, 0, Math.PI * 2);
            ctx.stroke();
            ctx.restore();

            var crop = computeSourceCrop();
            if (cropXInput) {
                cropXInput.value = String(crop.x);
            }
            if (cropYInput) {
                cropYInput.value = String(crop.y);
            }
            if (cropSizeInput) {
                cropSizeInput.value = String(crop.size);
            }

            previewCtx.clearRect(0, 0, previewCanvas.width, previewCanvas.height);
            if (crop.size > 0) {
                previewCtx.drawImage(
                    img,
                    crop.x,
                    crop.y,
                    crop.size,
                    crop.size,
                    0,
                    0,
                    previewCanvas.width,
                    previewCanvas.height
                );
            }
        }

        function resetCropState() {
            zoom = 1;
            offsetX = 0;
            offsetY = 0;
            zoomInput.value = '1';
            renderCrop();
        }

        fileInput.addEventListener('change', function () {
            setError('');
            var file = fileInput.files && fileInput.files[0];
            if (!file) {
                if (workspace) {
                    workspace.hidden = true;
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                return;
            }

            if (allowedTypes.indexOf(file.type) === -1) {
                setError('Unsupported image format. Please choose a JPEG, PNG, or WebP photo.');
                fileInput.value = '';
                if (workspace) {
                    workspace.hidden = true;
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                return;
            }

            if (file.size > maxBytes) {
                var mb = (maxBytes / (1024 * 1024)).toFixed(1);
                setError('Selected file exceeds the ' + mb + ' MB maximum size limit.');
                fileInput.value = '';
                if (workspace) {
                    workspace.hidden = true;
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                return;
            }

            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
            }

            objectUrl = URL.createObjectURL(file);
            var loader = new Image();
            loader.onload = function () {
                if (loader.naturalWidth < 64 || loader.naturalHeight < 64) {
                    setError('Image must be at least 64 x 64 pixels.');
                    fileInput.value = '';
                    if (workspace) {
                        workspace.hidden = true;
                    }
                    if (submitBtn) {
                        submitBtn.disabled = true;
                    }
                    return;
                }
                img = loader;
                if (workspace) {
                    workspace.hidden = false;
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                resetCropState();
            };
            loader.onerror = function () {
                setError('The selected file could not be decoded as a valid image.');
                fileInput.value = '';
                if (workspace) {
                    workspace.hidden = true;
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
            };
            loader.src = objectUrl;
        });

        zoomInput.addEventListener('input', function () {
            zoom = parseFloat(zoomInput.value) || 1;
            renderCrop();
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                resetCropState();
            });
        }

        canvas.addEventListener('pointerdown', function (event) {
            if (!img) {
                return;
            }
            dragging = true;
            dragStartX = event.clientX - offsetX;
            dragStartY = event.clientY - offsetY;
            if (typeof canvas.setPointerCapture === 'function') {
                canvas.setPointerCapture(event.pointerId);
            }
        });

        canvas.addEventListener('pointermove', function (event) {
            if (!dragging || !img) {
                return;
            }
            offsetX = event.clientX - dragStartX;
            offsetY = event.clientY - dragStartY;
            renderCrop();
        });

        ['pointerup', 'pointercancel'].forEach(function (evtName) {
            canvas.addEventListener(evtName, function () {
                dragging = false;
            });
        });

        form.addEventListener('submit', function (event) {
            var file = fileInput.files && fileInput.files[0];
            if (!file) {
                event.preventDefault();
                setError('Please select a JPEG, PNG, or WebP image first.');
                return;
            }

            if (submittingCropped || !img || typeof DataTransfer !== 'function') {
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Uploading...';
                }
                return;
            }

            event.preventDefault();
            var crop = computeSourceCrop();
            if (crop.size <= 0) {
                submittingCropped = true;
                form.submit();
                return;
            }

            var targetSize = Math.min(512, Math.max(128, crop.size));
            var outCanvas = document.createElement('canvas');
            outCanvas.width = targetSize;
            outCanvas.height = targetSize;
            var outCtx = outCanvas.getContext('2d');
            if (!outCtx) {
                submittingCropped = true;
                form.submit();
                return;
            }

            outCtx.drawImage(
                img,
                crop.x,
                crop.y,
                crop.size,
                crop.size,
                0,
                0,
                targetSize,
                targetSize
            );

            var targetMime = allowedTypes.indexOf(file.type) !== -1 ? file.type : 'image/png';
            outCanvas.toBlob(function (blob) {
                if (blob && blob.size > 0 && blob.size <= maxBytes) {
                    var ext = targetMime === 'image/png' ? 'png' : (targetMime === 'image/webp' ? 'webp' : 'jpg');
                    var croppedFile = new File([blob], 'avatar.' + ext, { type: blob.type || targetMime });
                    var dt = new DataTransfer();
                    dt.items.add(croppedFile);
                    fileInput.files = dt.files;
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Uploading...';
                }
                submittingCropped = true;
                form.submit();
            }, targetMime, 0.9);
        });
    }

    function initCorrectionForm() {
        var form = document.getElementById('correctionRequestForm');
        if (!form) {
            return;
        }

        var fieldSelect = form.querySelector('[data-correction-select]');
        var valueContainer = document.getElementById('correctionValueContainer');
        var currentPreview = document.getElementById('correctionCurrentPreview');

        if (!fieldSelect || !valueContainer) {
            return;
        }

        var fieldConfigs = safeJsonParse(form.getAttribute('data-fields'), {});
        var currentValues = safeJsonParse(form.getAttribute('data-current'), {});
        var departments = safeJsonParse(form.getAttribute('data-departments'), []);
        var positions = safeJsonParse(form.getAttribute('data-positions'), []);
        var initialOldValue = form.getAttribute('data-old-value') || '';
        var usedInitialOld = false;

        function buildOption(value, label, selectedVal) {
            var opt = document.createElement('option');
            opt.value = String(value);
            opt.textContent = label;
            if (String(selectedVal) === String(value)) {
                opt.selected = true;
            }
            return opt;
        }

        function resolveCurrentDisplay(fieldKey) {
            var rawVal = currentValues[fieldKey];
            if (rawVal === null || rawVal === undefined || String(rawVal).trim() === '') {
                return 'Not set';
            }
            if (fieldKey === 'department_id') {
                var deptMatch = departments.find(function (d) {
                    return String(d.id) === String(rawVal);
                });
                return deptMatch ? deptMatch.department_name : String(rawVal);
            }
            if (fieldKey === 'position_id') {
                var posMatch = positions.find(function (p) {
                    return String(p.id) === String(rawVal);
                });
                return posMatch ? posMatch.position_name : String(rawVal);
            }
            return String(rawVal);
        }

        function renderControl() {
            var fieldKey = fieldSelect.value;
            var meta = fieldConfigs[fieldKey] || { input: 'text', required: true, label: 'Requested Value' };
            var presetVal = '';
            if (!usedInitialOld && initialOldValue !== '') {
                presetVal = initialOldValue;
                usedInitialOld = true;
            }

            if (currentPreview) {
                currentPreview.textContent = resolveCurrentDisplay(fieldKey);
            }

            valueContainer.innerHTML = '';

            if (meta.input === 'select' && Array.isArray(meta.options)) {
                var sel = document.createElement('select');
                sel.id = 'correctionRequestedValue';
                sel.name = 'requested_value';
                sel.className = 'form-select';
                sel.required = meta.required !== false;
                sel.appendChild(buildOption('', '-- Select updated value --', presetVal));
                meta.options.forEach(function (optLabel) {
                    sel.appendChild(buildOption(optLabel, optLabel, presetVal));
                });
                valueContainer.appendChild(sel);
                return;
            }

            if (meta.input === 'department') {
                var deptSel = document.createElement('select');
                deptSel.id = 'correctionRequestedValue';
                deptSel.name = 'requested_value';
                deptSel.className = 'form-select';
                deptSel.required = true;
                deptSel.appendChild(buildOption('', '-- Select Department --', presetVal));
                departments.forEach(function (dept) {
                    deptSel.appendChild(buildOption(dept.id, dept.department_name, presetVal));
                });
                valueContainer.appendChild(deptSel);
                return;
            }

            if (meta.input === 'position') {
                var posSel = document.createElement('select');
                posSel.id = 'correctionRequestedValue';
                posSel.name = 'requested_value';
                posSel.className = 'form-select';
                posSel.required = true;
                posSel.appendChild(buildOption('', '-- Select Job Position --', presetVal));
                positions.forEach(function (pos) {
                    var label = pos.position_name;
                    var dept = departments.find(function (d) {
                        return String(d.id) === String(pos.department_id);
                    });
                    if (dept) {
                        label += ' (' + dept.department_name + ')';
                    }
                    posSel.appendChild(buildOption(pos.id, label, presetVal));
                });
                valueContainer.appendChild(posSel);
                return;
            }

            var inp = document.createElement('input');
            inp.id = 'correctionRequestedValue';
            inp.name = 'requested_value';
            inp.className = 'form-control';
            inp.type = meta.input === 'date' ? 'date' : (meta.input === 'email' ? 'email' : 'text');
            inp.required = meta.required !== false;
            if (meta.maxLength) {
                inp.maxLength = parseInt(meta.maxLength, 10);
            }
            if (meta.input === 'date') {
                inp.max = new Date().toISOString().slice(0, 10);
            }
            inp.value = presetVal;
            inp.placeholder = 'Enter requested ' + String(meta.label || 'value').toLowerCase();
            valueContainer.appendChild(inp);
        }

        fieldSelect.addEventListener('change', renderControl);
        renderControl();
    }

    function initHrMilestoneDeptFilter() {
        var deptSel = document.getElementById('milestoneDeptSelect');
        var posSel = document.getElementById('milestonePosSelect');
        if (!deptSel || !posSel) {
            return;
        }

        function filterPositions() {
            var chosenDept = deptSel.value;
            var currentPos = posSel.value;
            Array.prototype.forEach.call(posSel.options, function (opt) {
                if (!opt.value) {
                    opt.hidden = false;
                    return;
                }
                var optDept = opt.getAttribute('data-department-id') || '';
                var matches = chosenDept === '' || optDept === chosenDept;
                opt.hidden = !matches;
                if (!matches && opt.value === currentPos) {
                    posSel.value = '';
                }
            });
        }

        deptSel.addEventListener('change', filterPositions);
        filterPositions();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initDialogs();
        initAvatarCropper();
        initCorrectionForm();
        initHrMilestoneDeptFilter();
    });
})();
