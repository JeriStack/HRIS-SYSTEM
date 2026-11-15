/**
 * NEXORA HR — People. Processes. Progress.
 * Two-Factor Email OTP Interactive Controller:
 * - Theme Management (Light Mode Default, Persistent Dark Mode Toggle)
 * - Six-Digit OTP Input UX (Auto-advance, Backspace, Paste, Arrow/Home/End Navigation)
 * - Accessible Validation & Live Announcements (Without per-second screen-reader spam)
 * - Resend Cooldown Countdown & OTP Expiration Countdown
 */

document.addEventListener("DOMContentLoaded", () => {
  const otpMainCard = document.getElementById("otpMainCard");
  const otpBoxesGroup = document.getElementById("otpBoxesGroup");
  const otpDigits = document.querySelectorAll(".otp-box-digit");
  const otpHidden = document.getElementById("otpFullValue");
  const otpAutofillRow = document.getElementById("otpAutofillRow");
  const otpAutofillBtn = document.getElementById("otpAutofillBtn");
  const verifyForm = document.getElementById("otpVerifyForm");
  const verifySubmitBtn = document.getElementById("verifySubmitBtn");
  const resendForm = document.getElementById("resendForm");
  const resendBtn = document.getElementById("resendBtn");
  const cancelForm = document.getElementById("cancelForm");
  const timerSpan = document.getElementById("cooldownTimer");
  const clientFeedbackBanner = document.getElementById("clientFeedbackBanner");
  const clientFeedbackTitle = document.getElementById("clientFeedbackTitle");
  const clientFeedbackMessage = document.getElementById("clientFeedbackMessage");
  const srStatusAnnouncer = document.getElementById("otpStatusAnnouncer");
  const themeToggleBtn = document.getElementById("themeToggleBtn");

  let isSubmitting = false;
  let themeTransitionTimer = null;
  let webOtpAbortController = null;
  const previousDigitValues = Array.from(otpDigits, (input) => input.value || "");

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
     3. Six-Digit OTP Input, Autofill, Paste & WebOTP Integration
     -------------------------------------------------------------------------- */
  const syncOtpHidden = () => {
    let fullCode = "";
    otpDigits.forEach((input, idx) => {
      const hasDigit = input.value !== "";
      input.classList.toggle("is-filled", hasDigit);
      previousDigitValues[idx] = input.value;
      fullCode += input.value;
    });
    if (otpHidden) {
      otpHidden.value = fullCode;
    }
    return fullCode;
  };

  const stopWebOtpListener = () => {
    if (webOtpAbortController) {
      webOtpAbortController.abort();
      webOtpAbortController = null;
    }
  };

  const setVerifyingState = () => {
    if (isSubmitting) return;
    isSubmitting = true;
    stopWebOtpListener();

    if (otpAutofillBtn) {
      otpAutofillBtn.disabled = true;
    }

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
    stopWebOtpListener();

    if (verifyForm) {
      if (typeof verifyForm.requestSubmit === "function") {
        verifyForm.requestSubmit();
      } else {
        setVerifyingState();
        verifyForm.submit();
      }
    }
  };

  /**
   * Extract numeric OTP digits from clipboard, email snippet, or autofill strings.
   * Prefers an explicit 6-digit token (or 3-3 grouped digits) before raw stripping.
   */
  const extractOtpDigits = (rawText) => {
    const text = String(rawText || "");
    const groupedMatch = text.match(/(?:^|\D)(\d{3}[\s-]?\d{3})(?!\d)/);
    if (groupedMatch && groupedMatch[1]) {
      return groupedMatch[1].replace(/\D/g, "").slice(0, otpDigits.length);
    }
    return text.replace(/\D/g, "").slice(0, otpDigits.length);
  };

  const applyOtpSequence = (
    digits,
    startIndex = 0,
    { autofilled = false, autoSubmit = true } = {}
  ) => {
    if (!digits || !otpDigits.length) {
      return "";
    }

    clearClientError();

    const safeStart =
      digits.length >= otpDigits.length
        ? 0
        : Math.max(0, Math.min(startIndex, otpDigits.length - 1));

    digits.split("").forEach((char, offset) => {
      const targetIndex = safeStart + offset;
      const targetInput = otpDigits[targetIndex];
      if (!targetInput) return;

      targetInput.value = char;
      if (autofilled) {
        targetInput.classList.add("is-autofilled");
        window.setTimeout(() => {
          targetInput.classList.remove("is-autofilled");
        }, 900);
      }
    });

    const fullCode = syncOtpHidden();
    const nextFocusIndex = Math.min(
      safeStart + digits.length,
      otpDigits.length - 1
    );
    otpDigits[nextFocusIndex]?.focus();

    if (autofilled && fullCode.length === otpDigits.length) {
      announceToScreenReader("6-digit verification code autofilled.");
    }

    if (autoSubmit && fullCode.length === otpDigits.length) {
      triggerSubmit();
    }

    return fullCode;
  };

  otpDigits.forEach((input, index) => {
    input.addEventListener("focus", () => {
      input.select();
    });

    input.addEventListener("input", function (event) {
      const prevValue = previousDigitValues[index] || "";
      const cleaned = this.value.replace(/[^0-9]/g, "");

      // Browser/OS autofill (`one-time-code`), WebOTP, or bulk insert into a digit box
      const isBulkInsert =
        cleaned.length > 2 ||
        event.inputType === "insertReplacementText" ||
        event.inputType === "insertFromPaste" ||
        (cleaned.length === 2 && prevValue === "");

      if (isBulkInsert && cleaned.length >= 2) {
        const sequence = cleaned.slice(0, otpDigits.length);
        const startAt = sequence.length === otpDigits.length ? 0 : index;
        applyOtpSequence(sequence, startAt, { autofilled: true, autoSubmit: true });
        return;
      }

      // Single-digit overwrite inside a box that already held a digit
      let singleDigit = "";
      if (cleaned.length === 2 && prevValue.length === 1) {
        singleDigit = cleaned.startsWith(prevValue)
          ? cleaned.charAt(1)
          : cleaned.charAt(0);
      } else if (cleaned.length > 0) {
        singleDigit = cleaned.charAt(cleaned.length - 1);
      }

      this.value = singleDigit;

      if (this.value) {
        clearClientError();
      }

      const fullCode = syncOtpHidden();

      if (this.value && index < otpDigits.length - 1) {
        otpDigits[index + 1].focus();
      }

      if (fullCode.length === otpDigits.length) {
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
      event.stopPropagation();
      const clipboardData = event.clipboardData || window.clipboardData;
      const clipboardText = clipboardData ? clipboardData.getData("text") : "";
      const digits = extractOtpDigits(clipboardText);

      if (!digits) return;

      const startAt = digits.length === otpDigits.length ? 0 : index;
      applyOtpSequence(digits, startAt, { autofilled: true, autoSubmit: true });
    });
  });

  // Allow pasting a copied 6-digit OTP anywhere on the verification card
  if (otpMainCard) {
    otpMainCard.addEventListener("paste", (event) => {
      if (isSubmitting) return;
      const clipboardData = event.clipboardData || window.clipboardData;
      const clipboardText = clipboardData ? clipboardData.getData("text") : "";
      const digits = extractOtpDigits(clipboardText);

      if (!digits) return;
      event.preventDefault();
      applyOtpSequence(digits, 0, { autofilled: true, autoSubmit: true });
    });
  }

  // Progressive Clipboard "Autofill from Clipboard" button
  const hasClipboardReadSupport = Boolean(
    navigator.clipboard && typeof navigator.clipboard.readText === "function"
  );
  if (otpAutofillRow && otpAutofillBtn && hasClipboardReadSupport) {
    otpAutofillRow.hidden = false;

    otpAutofillBtn.addEventListener("click", async () => {
      if (isSubmitting) return;

      try {
        const clipboardText = await navigator.clipboard.readText();
        const digits = extractOtpDigits(clipboardText);

        if (!digits) {
          showClientError(
            "No verification code found",
            "Copy the 6-digit code from your email to your clipboard first, then try Autofill again."
          );
          otpDigits[0]?.focus();
          return;
        }

        if (digits.length < otpDigits.length) {
          applyOtpSequence(digits, 0, { autofilled: true, autoSubmit: false });
          announceToScreenReader(
            `Entered ${digits.length} digits from clipboard. Please complete the remaining digits.`
          );
          return;
        }

        applyOtpSequence(digits, 0, { autofilled: true, autoSubmit: true });
      } catch {
        showClientError(
          "Clipboard access blocked",
          "Allow clipboard permission or press Ctrl+V (Cmd+V on Mac) inside the code boxes to paste your OTP."
        );
        otpDigits[0]?.focus();
      }
    });
  }

  // Progressive WebOTP API (OTPCredential) Autofill with Lifecycle AbortController
  const startWebOtpListener = async () => {
    if (!otpDigits.length || !("OTPCredential" in window) || !navigator.credentials?.get) {
      return;
    }

    stopWebOtpListener();
    const abortController = new AbortController();
    webOtpAbortController = abortController;

    try {
      const credential = await navigator.credentials.get({
        otp: { transport: ["sms"] },
        signal: abortController.signal,
      });

      if (!credential || !credential.code || isSubmitting) {
        return;
      }

      const digits = extractOtpDigits(credential.code);
      if (digits) {
        applyOtpSequence(digits, 0, { autofilled: true, autoSubmit: true });
      }
    } catch (error) {
      if (error && error.name === "AbortError") {
        return;
      }
    }
  };

  startWebOtpListener();
  window.addEventListener("pagehide", stopWebOtpListener);

  if (cancelForm) {
    cancelForm.addEventListener("submit", stopWebOtpListener);
  }

  if (verifyForm) {
    verifyForm.addEventListener("submit", (event) => {
      stopWebOtpListener();

      if (isSubmitting) {
        return;
      }

      const fullCode = syncOtpHidden();
      if (fullCode.length !== otpDigits.length) {
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
      stopWebOtpListener();
      resendBtn.disabled = true;
      if (timerSpan) {
        timerSpan.textContent = "(Sending...)";
      }
    });
  }
});

