/**
 * NEXORA HR — Public Help, Password Recovery & Pre-Login Support Behavior
 * Progressive enhancement following modern-web-guidance:
 * - Light/Dark theme persistence via nexora_theme
 * - Validation feedback shown AFTER user interaction (blur/submit)
 * - Accessible aria-invalid & aria-describedby updates + 500ms polite aria-live announcements
 * - Submit lock only on valid submit + bfcache restore on pageshow
 */

(function () {
  "use strict";

  const THEME_STORAGE_KEY = "nexora_theme";

  function applyTheme(theme) {
    const resolved = theme === "dark" ? "dark" : "light";
    document.documentElement.setAttribute("data-theme", resolved);
    const btn = document.getElementById("themeToggleBtn");
    if (btn) {
      const nextLabel = resolved === "dark" ? "Switch to light mode" : "Switch to dark mode";
      btn.setAttribute("aria-label", nextLabel);
      btn.setAttribute("title", nextLabel);
    }
  }

  function initThemeToggle() {
    const current = document.documentElement.getAttribute("data-theme") || "light";
    applyTheme(current);

    const toggleBtn = document.getElementById("themeToggleBtn");
    if (!toggleBtn) return;

    toggleBtn.addEventListener("click", function () {
      const active = document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light";
      const next = active === "dark" ? "light" : "dark";
      applyTheme(next);
      try {
        localStorage.setItem(THEME_STORAGE_KEY, next);
      } catch (_err) {
        // Ignore private storage limitations
      }
    });
  }

  function initPasswordToggles() {
    document.querySelectorAll("[data-toggle-password]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const targetId = btn.getAttribute("data-toggle-password");
        const input = targetId ? document.getElementById(targetId) : null;
        if (!input) return;

        const show = input.type === "password";
        input.type = show ? "text" : "password";
        btn.setAttribute("data-visible", show ? "true" : "false");
        btn.setAttribute("aria-pressed", show ? "true" : "false");
        btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
      });
    });
  }

  function initPasswordRulesChecklist() {
    const newPw = document.getElementById("resetNewPassword");
    const confirmPw = document.getElementById("resetConfirmPassword");
    const checklist = document.getElementById("passwordRulesBox");
    if (!newPw || !checklist) return;

    function updateRules() {
      const val = newPw.value || "";
      const conf = confirmPw ? confirmPw.value || "" : "";

      const checks = {
        length: val.length >= 8 && val.length <= 72,
        upper: /[A-Z]/.test(val),
        lower: /[a-z]/.test(val),
        digit: /[0-9]/.test(val),
        special: /[^A-Za-z0-9]/.test(val),
        match: val.length >= 8 && conf.length > 0 && val === conf,
      };

      Object.keys(checks).forEach(function (key) {
        const item = checklist.querySelector('[data-rule="' + key + '"]');
        if (item) {
          item.classList.toggle("is-met", Boolean(checks[key]));
        }
      });

      if (confirmPw && confirmPw.dataset.touched === "true") {
        if (conf !== "" && val !== conf) {
          confirmPw.setCustomValidity("Confirmation password must match your new password.");
        } else {
          confirmPw.setCustomValidity("");
        }
      }
    }

    newPw.addEventListener("input", updateRules);
    if (confirmPw) {
      confirmPw.addEventListener("input", updateRules);
    }
    updateRules();
  }

  function initClientEnvPrefill() {
    const envInput = document.getElementById("publicClientEnv");
    if (!envInput || envInput.value.trim() !== "") return;

    const ua = navigator.userAgent || "";
    let browser = "Browser";
    if (ua.indexOf("Edg/") !== -1) browser = "Microsoft Edge";
    else if (ua.indexOf("Chrome/") !== -1) browser = "Chrome";
    else if (ua.indexOf("Firefox/") !== -1) browser = "Firefox";
    else if (ua.indexOf("Safari/") !== -1) browser = "Safari";

    let os = "Desktop";
    if (ua.indexOf("Windows") !== -1) os = "Windows";
    else if (ua.indexOf("Android") !== -1) os = "Android";
    else if (ua.indexOf("iPhone") !== -1 || ua.indexOf("iPad") !== -1) os = "iOS";
    else if (ua.indexOf("Mac OS X") !== -1) os = "macOS";
    else if (ua.indexOf("Linux") !== -1) os = "Linux";

    envInput.value = browser + " on " + os;
  }

  function initFormValidationAndSubmitLock() {
    const liveRegion = document.getElementById("formLiveAnnouncer");
    let announceTimer = null;

    function queueLiveAnnouncement(form) {
      if (!liveRegion) return;
      clearTimeout(announceTimer);
      announceTimer = window.setTimeout(function () {
        const messages = [];
        form.querySelectorAll("[aria-invalid='true']").forEach(function (el) {
          const errId = el.getAttribute("data-error-id");
          const errNode = errId ? document.getElementById(errId) : null;
          if (errNode && errNode.textContent.trim() !== "") {
            messages.push(errNode.textContent.trim());
          }
        });
        liveRegion.textContent = messages.join(" ");
      }, 500);
    }

    function syncFieldState(field) {
      const errId = field.getAttribute("data-error-id");
      const errNode = errId ? document.getElementById(errId) : null;
      if (!errNode) return;

      const baseDescribedBy = field.getAttribute("data-base-describedby") || "";
      const isValid = field.checkValidity();

      if (!isValid && field.dataset.touched === "true") {
        field.setAttribute("aria-invalid", "true");
        errNode.textContent = field.validationMessage || "Please check this field.";
        errNode.setAttribute("data-empty", "false");
        const ids = baseDescribedBy ? baseDescribedBy + " " + errId : errId;
        field.setAttribute("aria-describedby", ids.trim());
      } else {
        field.setAttribute("aria-invalid", "false");
        errNode.textContent = "";
        errNode.setAttribute("data-empty", "true");
        if (baseDescribedBy) {
          field.setAttribute("aria-describedby", baseDescribedBy);
        } else {
          field.removeAttribute("aria-describedby");
        }
      }
    }

    document.querySelectorAll("form[data-ps-validate]").forEach(function (form) {
      const fields = form.querySelectorAll("input, select, textarea");
      fields.forEach(function (field) {
        if (!field.getAttribute("data-base-describedby")) {
          field.setAttribute("data-base-describedby", field.getAttribute("aria-describedby") || "");
        }

        field.addEventListener("blur", function () {
          field.dataset.touched = "true";
          syncFieldState(field);
          queueLiveAnnouncement(form);
        });

        field.addEventListener("input", function () {
          if (field.dataset.touched === "true") {
            if (field.id === "resetConfirmPassword") {
              const newPw = document.getElementById("resetNewPassword");
              if (newPw && field.value !== "" && newPw.value !== field.value) {
                field.setCustomValidity("Confirmation password must match your new password.");
              } else {
                field.setCustomValidity("");
              }
            }
            syncFieldState(field);
            queueLiveAnnouncement(form);
          }
        });
      });

      form.addEventListener("submit", function (event) {
        fields.forEach(function (field) {
          field.dataset.touched = "true";
          if (field.id === "resetConfirmPassword") {
            const newPw = document.getElementById("resetNewPassword");
            if (newPw && field.value !== "" && newPw.value !== field.value) {
              field.setCustomValidity("Confirmation password must match your new password.");
            } else {
              field.setCustomValidity("");
            }
          }
          syncFieldState(field);
        });

        if (!form.checkValidity()) {
          event.preventDefault();
          queueLiveAnnouncement(form);
          const firstInvalid = form.querySelector("[aria-invalid='true']");
          if (firstInvalid) {
            firstInvalid.focus();
          }
          return;
        }

        const submitBtn = form.querySelector("[data-submit-lock]");
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.setAttribute("aria-disabled", "true");
        }
      });
    });

    window.addEventListener("pageshow", function () {
      document.querySelectorAll("[data-submit-lock]").forEach(function (btn) {
        btn.disabled = false;
        btn.removeAttribute("aria-disabled");
      });
    });
  }

  function initAttachmentPreview() {
    document.querySelectorAll("input[type='file'][data-ps-files]").forEach(function (input) {
      const listId = input.getAttribute("data-list-id");
      const statusId = input.getAttribute("data-status-id");
      const maxFiles = parseInt(input.getAttribute("data-max-files") || "3", 10);
      const maxBytes = parseInt(input.getAttribute("data-max-bytes") || String(5 * 1024 * 1024), 10);
      const listEl = listId ? document.getElementById(listId) : null;
      const statusEl = statusId ? document.getElementById(statusId) : null;

      input.addEventListener("change", function () {
        if (!listEl) return;
        listEl.innerHTML = "";
        if (statusEl) {
          statusEl.textContent = "";
          statusEl.hidden = true;
        }

        const files = Array.from(input.files || []);
        if (files.length === 0) {
          listEl.hidden = true;
          return;
        }

        const warnings = [];
        if (files.length > maxFiles) {
          warnings.push("Maximum " + maxFiles + " attachments allowed per submission.");
        }

        files.forEach(function (file) {
          const li = document.createElement("li");
          li.className = "ps-file-item";
          const nameSpan = document.createElement("span");
          nameSpan.textContent = file.name;
          const sizeSpan = document.createElement("span");
          sizeSpan.className = "tabular-nums";
          sizeSpan.textContent = Math.max(1, Math.round(file.size / 1024)) + " KB";
          li.appendChild(nameSpan);
          li.appendChild(sizeSpan);
          listEl.appendChild(li);

          if (file.size > maxBytes) {
            warnings.push(file.name + " exceeds the maximum file size.");
          }
        });

        listEl.hidden = false;
        if (warnings.length > 0 && statusEl) {
          statusEl.textContent = warnings.join(" ");
          statusEl.hidden = false;
        }
      });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initThemeToggle();
    initPasswordToggles();
    initPasswordRulesChecklist();
    initClientEnvPrefill();
    initFormValidationAndSubmitLock();
    initAttachmentPreview();
  });
})();
