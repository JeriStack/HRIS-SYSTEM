/**
 * NEXORA HR — People. Processes. Progress.
 * Role-Based Pop-Up Login Modal & Theme Controller
 */

document.addEventListener("DOMContentLoaded", () => {
  const modalBackdrop = document.getElementById("loginModalBackdrop");
  const modalDialog = document.getElementById("loginModalDialog");
  const closeModalBtn = document.getElementById("closeLoginModalBtn");
  const backToRolesBtn = document.getElementById("backToRolesBtn");
  const portalRoleInput = document.getElementById("portalRoleInput");
  const modalRolePillText = document.getElementById("modalRolePillText");
  const modalTitle = document.getElementById("loginModalTitle");
  const submitBtn = document.getElementById("loginSubmitBtn");
  const submitBtnText = document.getElementById("loginSubmitBtnText");
  const loginForm = document.getElementById("loginForm");
  const emailInput = document.getElementById("loginEmail");
  const passwordInput = document.getElementById("loginPassword");
  const togglePasswordBtn = document.getElementById("togglePasswordBtn");
  const themeToggleBtn = document.getElementById("themeToggleBtn");

  const roleCards = document.querySelectorAll("[data-role-card]");
  const roleTriggerBtns = document.querySelectorAll("[data-open-role-modal]");
  const roleSwitchTabs = document.querySelectorAll("[data-switch-role]");

  let lastTriggerElement = null;
  let isSubmitting = false;

  /* --------------------------------------------------------------------------
     1. Role Metadata Configuration (Admin, HR, and Employee Portals)
     -------------------------------------------------------------------------- */
  const ROLE_CONFIG = {
    admin: {
      code: "admin",
      pill: "Admin Workspace",
      title: "Admin Portal Login",
      submitText: "Sign In to Admin Portal",
      emailPlaceholder: "admin.name@company.local",
    },
    hr: {
      code: "hr",
      pill: "HR Workspace",
      title: "HR Portal Login",
      submitText: "Sign In to HR Portal",
      emailPlaceholder: "hr.name@company.local",
    },
    employee: {
      code: "employee",
      pill: "Employee Workspace",
      title: "Employee Portal Login",
      submitText: "Sign In to Employee Portal",
      emailPlaceholder: "employee.name@company.local",
    },
  };

  /* --------------------------------------------------------------------------
     2. Theme Management (Light Default, Persistent Dark Mode)
     -------------------------------------------------------------------------- */
  const THEME_STORAGE_KEY = "nexora_theme";
  let themeTransitionTimer = null;
  let roleSwitchTimer = null;

  const updateThemeButton = (theme) => {
    const isDark = theme === "dark";
    const actionLabel = isDark ? "Switch to light mode" : "Switch to dark mode";
    if (themeToggleBtn) {
      themeToggleBtn.setAttribute("aria-label", actionLabel);
      themeToggleBtn.setAttribute("title", actionLabel);
    }
  };

  const applyTheme = (theme, animate = false) => {
    const resolved = theme === "dark" ? "dark" : "light";
    if (animate) {
      document.documentElement.classList.add("theme-transitioning");
      if (themeTransitionTimer) {
        window.clearTimeout(themeTransitionTimer);
      }
      themeTransitionTimer = window.setTimeout(() => {
        document.documentElement.classList.remove("theme-transitioning");
      }, 280);
    }
    document.documentElement.setAttribute("data-theme", resolved);
    try {
      localStorage.setItem(THEME_STORAGE_KEY, resolved);
    } catch {
      // Ignore storage access errors in restricted modes
    }
    updateThemeButton(resolved);
  };

  const initialTheme =
    document.documentElement.getAttribute("data-theme") === "dark"
      ? "dark"
      : "light";
  updateThemeButton(initialTheme);

  if (themeToggleBtn) {
    themeToggleBtn.addEventListener("click", () => {
      const current =
        document.documentElement.getAttribute("data-theme") === "dark"
          ? "dark"
          : "light";
      applyTheme(current === "dark" ? "light" : "dark", true);
    });
  }

  /* --------------------------------------------------------------------------
     3. Dynamic Role Application inside Modal
     -------------------------------------------------------------------------- */
  const setModalRole = (roleKey, setPortalHiddenValue = true) => {
    const config = ROLE_CONFIG[roleKey] || ROLE_CONFIG.hr;

    if (modalDialog) {
      modalDialog.setAttribute("data-active-role", config.code);
    }

    if (portalRoleInput) {
      portalRoleInput.value = setPortalHiddenValue ? config.code : "";
    }

    if (modalRolePillText) {
      modalRolePillText.textContent = config.pill;
    }

    if (modalTitle) {
      modalTitle.textContent = config.title;
    }

    if (submitBtnText && !isSubmitting) {
      submitBtnText.textContent = config.submitText;
    }

    if (emailInput) {
      emailInput.setAttribute("placeholder", config.emailPlaceholder);
    }

    roleSwitchTabs.forEach((tab) => {
      const tabRole = tab.getAttribute("data-switch-role");
      const active = tabRole === config.code;
      tab.classList.toggle("is-active", active);
      tab.setAttribute("aria-selected", String(active));
    });

    roleCards.forEach((card) => {
      const cardRole = card.getAttribute("data-role-card");
      card.classList.toggle("is-selected", cardRole === config.code);
    });
  };

  /* --------------------------------------------------------------------------
     4. Accessible Modal Open / Close & Focus Management
     -------------------------------------------------------------------------- */
  const openLoginModal = (roleKey, triggerEl = null, lockRole = true) => {
    if (!modalBackdrop || !modalDialog) return;

    if (triggerEl) {
      lastTriggerElement = triggerEl;
    }

    setModalRole(roleKey, lockRole);

    modalBackdrop.classList.add("is-open");
    modalBackdrop.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-scroll-locked");

    window.requestAnimationFrame(() => {
      if (emailInput && !emailInput.value) {
        emailInput.focus();
      } else if (passwordInput) {
        passwordInput.focus();
      }
    });
  };

  const closeLoginModal = () => {
    if (!modalBackdrop || isSubmitting) return;

    modalBackdrop.classList.remove("is-open");
    modalBackdrop.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-scroll-locked");

    if (lastTriggerElement && typeof lastTriggerElement.focus === "function") {
      lastTriggerElement.focus();
    }
  };

  roleTriggerBtns.forEach((btn) => {
    btn.addEventListener("click", (event) => {
      const roleKey = btn.getAttribute("data-open-role-modal") || "hr";
      openLoginModal(roleKey, event.currentTarget, true);
    });
  });

  roleSwitchTabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      if (isSubmitting) return;
      const roleKey = tab.getAttribute("data-switch-role") || "hr";
      if (modalDialog) {
        modalDialog.setAttribute("data-switching", "true");
        if (roleSwitchTimer) {
          window.clearTimeout(roleSwitchTimer);
        }
        roleSwitchTimer = window.setTimeout(() => {
          modalDialog.removeAttribute("data-switching");
        }, 160);
      }
      setModalRole(roleKey, true);
    });
  });

  if (closeModalBtn) {
    closeModalBtn.addEventListener("click", closeLoginModal);
  }

  if (backToRolesBtn) {
    backToRolesBtn.addEventListener("click", closeLoginModal);
  }

  if (modalBackdrop && modalDialog) {
    modalBackdrop.addEventListener("click", (event) => {
      if (isSubmitting) return;
      if (event.target === modalBackdrop) {
        closeLoginModal();
        return;
      }
      const rect = modalDialog.getBoundingClientRect();
      const insideDialog =
        rect.top <= event.clientY &&
        event.clientY <= rect.top + rect.height &&
        rect.left <= event.clientX &&
        event.clientX <= rect.left + rect.width;
      if (!insideDialog) {
        closeLoginModal();
      }
    });
  }

  /* --------------------------------------------------------------------------
     5. Keyboard Accessibility: Escape & Focus Trap inside Modal
     -------------------------------------------------------------------------- */
  document.addEventListener("keydown", (event) => {
    if (!modalBackdrop || !modalBackdrop.classList.contains("is-open")) return;

    if (event.key === "Escape") {
      event.preventDefault();
      closeLoginModal();
      return;
    }

    if (event.key === "Tab" && modalDialog) {
      const focusableSelectors = [
        "button:not([disabled])",
        "a[href]",
        "input:not([disabled]):not([type='hidden'])",
        "[tabindex]:not([tabindex='-1'])",
      ].join(", ");

      const focusableNodes = [
        ...modalDialog.querySelectorAll(focusableSelectors),
      ].filter((el) => el.offsetParent !== null);

      if (focusableNodes.length === 0) return;

      const firstNode = focusableNodes[0];
      const lastNode = focusableNodes[focusableNodes.length - 1];

      if (event.shiftKey && document.activeElement === firstNode) {
        event.preventDefault();
        lastNode.focus();
      } else if (!event.shiftKey && document.activeElement === lastNode) {
        event.preventDefault();
        firstNode.focus();
      }
    }
  });

  /* --------------------------------------------------------------------------
     6. Show / Hide Password Toggle
     -------------------------------------------------------------------------- */
  if (togglePasswordBtn && passwordInput) {
    togglePasswordBtn.addEventListener("click", () => {
      const isPassword = passwordInput.getAttribute("type") === "password";
      const nextType = isPassword ? "text" : "password";
      passwordInput.setAttribute("type", nextType);
      togglePasswordBtn.setAttribute("data-visible", String(isPassword));
      togglePasswordBtn.setAttribute(
        "aria-label",
        isPassword ? "Hide password" : "Show password"
      );
      togglePasswordBtn.setAttribute(
        "title",
        isPassword ? "Hide password" : "Show password"
      );
    });
  }

  /* --------------------------------------------------------------------------
     7. Duplicate Submission Prevention & Loading Feedback
     -------------------------------------------------------------------------- */
  if (loginForm && submitBtn) {
    loginForm.addEventListener("submit", (event) => {
      if (isSubmitting) {
        event.preventDefault();
        return;
      }

      if (!loginForm.checkValidity()) {
        return;
      }

      isSubmitting = true;
      submitBtn.disabled = true;
      submitBtn.classList.add("is-submitting");

      if (submitBtnText) {
        submitBtnText.textContent = "Authenticating...";
      }
    });
  }

  /* --------------------------------------------------------------------------
     8. Initialize Default or Server-Restored Portal State
     -------------------------------------------------------------------------- */
  const initialPortalRole =
    modalDialog?.getAttribute("data-initial-role") || "";
  const shouldOpenInitially =
    modalBackdrop?.getAttribute("data-initial-open") === "true";

  if (initialPortalRole === "hr" || initialPortalRole === "employee") {
    setModalRole(initialPortalRole, true);
  } else {
    setModalRole("hr", false);
  }

  if (shouldOpenInitially) {
    document.body.classList.add("modal-scroll-locked");
  }
});

