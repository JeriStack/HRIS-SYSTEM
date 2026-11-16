/**
 * NEXORA HR — Account Security & Trusted Device Dialog Interactions
 * Implements native <dialog> top-layer modal handling with returnValue resets,
 * safe cancel autofocus, and backdrop click-coordinate dismissal.
 */
(function () {
    "use strict";

    var singleDialog = document.getElementById("revokeDeviceDialog");
    var singleForm = document.getElementById("revokeDeviceDialogForm");
    var singleMessage = document.getElementById("revokeDeviceDialogMessage");
    var singleCurrentNote = document.getElementById("revokeDeviceCurrentWarning");
    var allDialog = document.getElementById("revokeAllDevicesDialog");
    var openAllBtn = document.getElementById("openRevokeAllDialogBtn");

    function closeDialogSafely(dialogEl) {
        if (!dialogEl || !dialogEl.open) {
            return;
        }
        dialogEl.close("cancel");
    }

    function wireDialogLifecycle(dialogEl) {
        if (!dialogEl) {
            return;
        }

        dialogEl.addEventListener("close", function () {
            dialogEl.returnValue = "";
        });

        // Light-dismiss when clicking backdrop rectangle strictly outside dialog bounds
        dialogEl.addEventListener("click", function (event) {
            if (event.target !== dialogEl) {
                return;
            }
            var rect = dialogEl.getBoundingClientRect();
            var inside =
                event.clientX >= rect.left &&
                event.clientX <= rect.right &&
                event.clientY >= rect.top &&
                event.clientY <= rect.bottom;
            if (!inside) {
                closeDialogSafely(dialogEl);
            }
        });

        dialogEl.querySelectorAll("[data-close-dialog]").forEach(function (closeBtn) {
            closeBtn.addEventListener("click", function () {
                closeDialogSafely(dialogEl);
            });
        });
    }

    wireDialogLifecycle(singleDialog);
    wireDialogLifecycle(allDialog);

    document.querySelectorAll("[data-revoke-device]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            if (!singleDialog || !singleForm || typeof singleDialog.showModal !== "function") {
                return;
            }
            var deviceName = btn.getAttribute("data-device-name") || "Trusted Browser";
            var revokeUrl = btn.getAttribute("data-revoke-url") || "";
            var isCurrent = btn.getAttribute("data-is-current") === "1";

            singleForm.setAttribute("action", revokeUrl);
            if (singleMessage) {
                singleMessage.textContent =
                    'Revoking "' +
                    deviceName +
                    '" immediately invalidates its trusted credential. Email OTP verification will be required at next sign-in.';
            }
            if (singleCurrentNote) {
                singleCurrentNote.hidden = !isCurrent;
            }

            singleDialog.showModal();
        });
    });

    if (openAllBtn && allDialog && typeof allDialog.showModal === "function") {
        openAllBtn.addEventListener("click", function () {
            if (openAllBtn.disabled) {
                return;
            }
            allDialog.showModal();
        });
    }
})();
