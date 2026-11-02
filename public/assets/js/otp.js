/**
 * NEXORA HR — People. Processes. Progress.
 * Two-Factor Email OTP Interactive Controller:
 * - Theme Management (Light Mode Default, Persistent Dark Mode Toggle)
 * - Six-Digit OTP Input UX (Auto-advance, Backspace, Paste, Arrow/Home/End Navigation)
 * - Accessible Validation & Live Announcements (Without per-second screen-reader spam)
 * - Resend Cooldown Countdown & OTP Expiration Countdown
 */

document.addEventListener("DOMContentLoaded", () => {
  const otpBoxesGroup = document.getElementById("otpBoxesGroup");
  const otpDigits = document.querySelectorAll(".otp-box-digit");
  const otpHidden = document.getElementById("otpFullValue");
  const verifyForm = document.getElementById("otpVerifyForm");
  const verifySubmitBtn = document.getElementById("verifySubmitBtn");
  const resendForm = document.getElementById("resendForm");
  const resendBtn = document.getElementById("resendBtn");
  const timerSpan = document.getElementById("cooldownTimer");
  const clientFeedbackBanner = document.getElementById("clientFeedbackBanner");
  const clientFeedbackTitle = document.getElementById("clientFeedbackTitle");
  const clientFeedbackMessage = document.getElementById("clientFeedbackMessage");
  const srStatusAnnouncer = document.getElementById("otpStatusAnnouncer");
  const themeToggleBtn = document.getElementById("themeToggleBtn");

  let isSubmitting = false;
  let themeTransitionTimer = null;

  /* --------------------------------------------------------------------------
     1. Theme Management (Light Mode Default, Persistent Dark Mode)
     -------------------------------------------------------------------------- */
  const THEME_STORAGE_KEY = "nexora_theme";

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
     2. Accessible Screen-Reader Announcements & Client Validation Banner
     -------------------------------------------------------------------------- */
  const announceToScreenReader = (message) => {
    if (!srStatusAnnouncer || !message) return;
    srStatusAnnouncer.textContent = "";
    window.setTimeout(() => {
      srStatusAnnouncer.textContent = message;
    }, 30);
  };

  const showClientError = (title, message) => {
    if (clientFeedbackBanner && clientFeedbackTitle && clientFeedbackMessage) {
      clientFeedbackTitle.textContent = title;
      clientFeedbackMessage.textContent = message;
      clientFeedbackBanner.hidden = false;
    }

    otpDigits.forEach((input) => {
      if (!input.value) {
        input.classList.add("is-invalid");
        input.setAttribute("aria-invalid", "true");
      }
    });

    if (otpBoxesGroup) {
      otpBoxesGroup.classList.remove("is-shaking");
      // Force reflow so subtle shake can re-trigger if needed
      void otpBoxesGroup.offsetWidth;
      otpBoxesGroup.classList.add("is-shaking");
    }

    announceToScreenReader(`${title}. ${message}`);
  };

  const clearClientError = () => {
    if (clientFeedbackBanner) {
      clientFeedbackBanner.hidden = true;
    }
    if (otpBoxesGroup) {
      otpBoxesGroup.classList.remove("is-shaking");
    }
    otpDigits.forEach((input) => {
      input.classList.remove("is-invalid");
      input.removeAttribute("aria-invalid");
    });
  };

  /* --------------------------------------------------------------------------
     3. Six-Digit OTP Input Synchronization & Interactive States
     -------------------------------------------------------------------------- */
  const syncOtpHidden = () => {
    let fullCode = "";
    otpDigits.forEach((input) => {
      const hasDigit = input.value !== "";
      input.classList.toggle("is-filled", hasDigit);
      fullCode += input.value;
    });
    if (otpHidden) {
      otpHidden.value = fullCode;
    }
    return fullCode;
  };

  const setVerifyingState = () => {
    if (isSubmitting) return;
    isSubmitting = true;

    if (verifySubmitBtn) {
      verifySubmitBtn.disabled = true;
      verifySubmitBtn.innerHTML = `
        <svg class="spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
          <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
          <path d="M12 2a10 10 0 0 1 10 10" />
        </svg>
        <span>Verifying your code...</span>
      `;
    }

    announceToScreenReader("Verifying your code...");
  };

  const triggerSubmit = () => {
    if (isSubmitting) return;
    setVerifyingState();

    if (verifyForm) {
      if (typeof verifyForm.requestSubmit === "function") {
        verifyForm.requestSubmit();
      } else {
        verifyForm.submit();
      }
    }
  };

  otpDigits.forEach((input, index) => {
    input.addEventListener("focus", () => {
      input.select();
    });

    input.addEventListener("input", function () {
      const cleaned = this.value.replace(/[^0-9]/g, "");
      this.value = cleaned ? cleaned[cleaned.length - 1] : "";

      if (this.value) {
        clearClientError();
      }

      const fullCode = syncOtpHidden();

      if (this.value && index < otpDigits.length - 1) {
        otpDigits[index + 1].focus();
      }

      if (fullCode.length === 6) {
        triggerSubmit();
      }
    });

    input.addEventListener("keydown", function (event) {
      if (event.key === "Backspace") {
        clearClientError();
        if (!this.value && index > 0) {
          event.preventDefault();
          otpDigits[index - 1].value = "";
          otpDigits[index - 1].focus();
          syncOtpHidden();
        } else {
          this.value = "";
          syncOtpHidden();
        }
      } else if (event.key === "ArrowLeft" && index > 0) {
        event.preventDefault();
        otpDigits[index - 1].focus();
      } else if (event.key === "ArrowRight" && index < otpDigits.length - 1) {
        event.preventDefault();
        otpDigits[index + 1].focus();
      } else if (event.key === "Home") {
        event.preventDefault();
        otpDigits[0]?.focus();
      } else if (event.key === "End") {
        event.preventDefault();
        otpDigits[otpDigits.length - 1]?.focus();
      } else if (
        event.key.length === 1 &&
        !event.ctrlKey &&
        !event.metaKey &&
        !event.altKey &&
        !/^[0-9]$/.test(event.key)
      ) {
        event.preventDefault();
      }
    });

    // Seamless paste support for complete or partial numeric codes
    input.addEventListener("paste", (event) => {
      event.preventDefault();
      const clipboardData = event.clipboardData || window.clipboardData;
      const clipboardText = clipboardData ? clipboardData.getData("text") : "";
      const digits = clipboardText.replace(/[^0-9]/g, "").slice(0, 6);

      if (!digits) return;

      clearClientError();

      digits.split("").forEach((char, i) => {
        if (otpDigits[i]) {
          otpDigits[i].value = char;
        }
      });

      const fullCode = syncOtpHidden();
      const nextFocusIndex = Math.min(digits.length, otpDigits.length - 1);
      otpDigits[nextFocusIndex]?.focus();

      if (fullCode.length === 6) {
        triggerSubmit();
      }
    });
  });

  if (verifyForm) {
    verifyForm.addEventListener("submit", (event) => {
      if (isSubmitting) {
        return;
      }

      const fullCode = syncOtpHidden();
      if (fullCode.length !== 6) {
        event.preventDefault();
        showClientError(
          "Incomplete verification code",
          "Please enter all 6 numeric digits sent to your email before verifying."
        );

        for (let i = 0; i < otpDigits.length; i++) {
          if (!otpDigits[i].value) {
            otpDigits[i].focus();
            break;
          }
        }
        return;
      }

      setVerifyingState();
    });
  }

  /* --------------------------------------------------------------------------
     4. Resend Code Cooldown Countdown (Meaningful SR Announcements Only)
     -------------------------------------------------------------------------- */
  const rawCooldown = Number(
    resendBtn?.getAttribute("data-cooldown-seconds") || 0
  );
  let cooldownSeconds = Number.isFinite(rawCooldown) ? Math.max(0, rawCooldown) : 0;

  if (cooldownSeconds > 0 && resendBtn && timerSpan) {
    resendBtn.disabled = true;
    const interval = window.setInterval(() => {
      cooldownSeconds--;
      if (cooldownSeconds <= 0) {
        window.clearInterval(interval);
        resendBtn.disabled = false;
        timerSpan.textContent = "";
        announceToScreenReader("You can now request a new verification code.");
      } else {
        timerSpan.textContent = `(${cooldownSeconds}s)`;
      }
    }, 1000);
  }

  if (resendForm && resendBtn) {
    resendForm.addEventListener("submit", (event) => {
      if (resendBtn.disabled) {
        event.preventDefault();
        return;
      }
      resendBtn.disabled = true;
      if (timerSpan) {
        timerSpan.textContent = "(Sending...)";
      }
    });
  }
});

