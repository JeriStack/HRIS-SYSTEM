/**
 * NEXORA HR — People. Processes. Progress.
 * Landing Page Interactive Controllers:
 * - Theme Management (Light default, Dark toggle, color-scheme sync, cross-tab storage sync)
 * - Accessible Mobile Navigation Drawer (inert management, focus restoration, Escape/outside click)
 * - rAF-Throttled Scroll Spy & Batched Sliding Navbar Indicator
 * - Reduced-Motion-Safe Scroll Reveal (IntersectionObserver)
 */

document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".site-header");
  const mobileMenuButton = document.getElementById("mobileMenuBtn");
  const mobileDrawer = document.getElementById("mobileDrawer");
  const themeToggleBtn = document.getElementById("themeToggleBtn");
  const mobileThemeToggleBtn = document.getElementById("mobileThemeToggleBtn");
  const desktopNavList = document.getElementById("desktopNavLinks");
  const navScrollIndicator = document.getElementById("navScrollIndicator");
  const colorSchemeMeta = document.querySelector('meta[name="color-scheme"]');

  const navigationLinks = [
    ...document.querySelectorAll(
      ".site-nav-links .nav-link, .mobile-drawer .mobile-nav-link"
    ),
  ];

  const trackedSections = [
    ...document.querySelectorAll("main section[data-nav-section], main section[id]"),
  ];

  const reducedMotionQuery =
    typeof window.matchMedia === "function"
      ? window.matchMedia("(prefers-reduced-motion: reduce)")
      : null;

  const prefersReducedMotion = () => Boolean(reducedMotionQuery?.matches);

  /* --------------------------------------------------------------------------
     1. Theme Management (Light Mode Default, Dark Mode Option, Cross-Tab Sync)
     -------------------------------------------------------------------------- */

  const THEME_STORAGE_KEY = "nexora_theme";
  let themeTransitionTimer = null;

  const updateThemeUI = (theme) => {
    const isDark = theme === "dark";
    const label = isDark ? "Switch to light mode" : "Switch to dark mode";

    if (colorSchemeMeta) {
      colorSchemeMeta.setAttribute("content", isDark ? "dark" : "light");
    }

    if (themeToggleBtn) {
      themeToggleBtn.setAttribute("aria-label", label);
      themeToggleBtn.setAttribute("title", label);
    }

    if (mobileThemeToggleBtn) {
      mobileThemeToggleBtn.setAttribute("aria-label", label);
      const textSpan = mobileThemeToggleBtn.querySelector(".theme-mode-text");
      if (textSpan) {
        textSpan.textContent = isDark ? "Theme: Dark" : "Theme: Light";
      }
    }
  };

  const setTheme = (theme, { animate = false, persist = true } = {}) => {
    const nextTheme = theme === "dark" ? "dark" : "light";

    if (animate && !prefersReducedMotion()) {
      document.documentElement.classList.add("theme-transitioning");
      if (themeTransitionTimer) {
        window.clearTimeout(themeTransitionTimer);
      }
      themeTransitionTimer = window.setTimeout(() => {
        document.documentElement.classList.remove("theme-transitioning");
      }, 260);
    }

    document.documentElement.setAttribute("data-theme", nextTheme);

    if (persist) {
      try {
        localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
      } catch {
        // Storage may be restricted in private browsing modes
      }
    }

    updateThemeUI(nextTheme);
  };

  const initialTheme =
    document.documentElement.getAttribute("data-theme") === "dark"
      ? "dark"
      : "light";
  updateThemeUI(initialTheme);

  const handleThemeToggle = () => {
    const active =
      document.documentElement.getAttribute("data-theme") === "dark"
        ? "light"
        : "dark";
    setTheme(active, { animate: true, persist: true });
  };

  if (themeToggleBtn) {
    themeToggleBtn.addEventListener("click", handleThemeToggle);
  }

  if (mobileThemeToggleBtn) {
    mobileThemeToggleBtn.addEventListener("click", handleThemeToggle);
  }

  // Keep theme synchronized across tabs
  window.addEventListener("storage", (event) => {
    if (event.key !== THEME_STORAGE_KEY) return;
    const synced = event.newValue === "dark" ? "dark" : "light";
    setTheme(synced, { animate: false, persist: false });
  });

  /* --------------------------------------------------------------------------
     2. Accessible Mobile Navigation Drawer (inert + focus restoration)
     -------------------------------------------------------------------------- */

  const setDrawerState = (isOpen, { restoreFocus = false } = {}) => {
    if (!mobileDrawer || !mobileMenuButton) return;

    mobileDrawer.classList.toggle("is-open", isOpen);
    mobileDrawer.inert = !isOpen;
    mobileMenuButton.classList.toggle("is-open", isOpen);
    mobileMenuButton.setAttribute("aria-expanded", String(isOpen));
    mobileMenuButton.setAttribute("aria-label", isOpen ? "Close menu" : "Open menu");

    if (!isOpen && restoreFocus) {
      mobileMenuButton.focus();
    }
  };

  const closeMobileNavigation = (options = {}) => {
    if (!mobileDrawer || !mobileDrawer.classList.contains("is-open")) return;
    setDrawerState(false, options);
  };

  if (mobileMenuButton && mobileDrawer) {
    // Ensure closed drawer is inert on initial load
    mobileDrawer.inert = !mobileDrawer.classList.contains("is-open");

    mobileMenuButton.addEventListener("click", () => {
      const nextOpen = !mobileDrawer.classList.contains("is-open");
      setDrawerState(nextOpen);
    });
  }

  /* --------------------------------------------------------------------------
     3. rAF-Throttled Scroll Spy & Batched Sliding Indicator
     -------------------------------------------------------------------------- */

  let currentActiveId = null;
  let lastScrolledState = null;
  let scrollRafPending = false;

  const updateSlidingIndicator = () => {
    if (!desktopNavList || !navScrollIndicator) return;

    const activeDesktopLink = desktopNavList.querySelector(".nav-link.active");
    if (!activeDesktopLink) {
      navScrollIndicator.style.opacity = "0";
      return;
    }

    // Batch geometry reads before style writes
    const listRect = desktopNavList.getBoundingClientRect();
    const linkRect = activeDesktopLink.getBoundingClientRect();
    const offsetX = linkRect.left - listRect.left;
    const width = linkRect.width;

    navScrollIndicator.style.width = `${width}px`;
    navScrollIndicator.style.transform = `translateX(${offsetX}px)`;
    navScrollIndicator.style.opacity = "1";
  };

  const resolveNavTargetForSection = (section) => {
    const mapped = section.getAttribute("data-nav-section");
    if (mapped) return mapped;

    const id = section.id;
    if (id === "roles" || id === "security") return "about";
    if (id === "workflow") return "how-it-works";
    return id || "home";
  };

  const applyActiveNavId = (activeId, { forceIndicator = false } = {}) => {
    if (activeId === currentActiveId && !forceIndicator) return;
    currentActiveId = activeId;

    navigationLinks.forEach((link) => {
      const isActive = link.getAttribute("href") === `#${activeId}`;
      link.classList.toggle("active", isActive);

      if (link.matches(".site-nav-links .nav-link")) {
        if (isActive) {
          link.setAttribute("aria-current", "page");
        } else {
          link.removeAttribute("aria-current");
        }
      }
    });

    updateSlidingIndicator();
  };

  const setActiveNavigation = ({ forceIndicator = false } = {}) => {
    const headerHeight = header ? header.offsetHeight : 76;
    const triggerLine = headerHeight + 120;
    const scrollTop = window.scrollY || document.documentElement.scrollTop || 0;
    const isScrolled = scrollTop > 12;

    if (header && isScrolled !== lastScrolledState) {
      header.classList.toggle("is-scrolled", isScrolled);
      lastScrolledState = isScrolled;
    }

    let activeId = "home";

    for (const section of trackedSections) {
      const rect = section.getBoundingClientRect();
      if (rect.top <= triggerLine) {
        activeId = resolveNavTargetForSection(section);
      }
    }

    const viewportBottom = scrollTop + window.innerHeight;
    const docHeight = Math.max(
      document.body.scrollHeight,
      document.documentElement.scrollHeight
    );

    if (docHeight - viewportBottom <= 48) {
      activeId = "how-it-works";
    }

    applyActiveNavId(activeId, { forceIndicator });
  };

  const scheduleScrollUpdate = () => {
    if (scrollRafPending) return;
    scrollRafPending = true;
    window.requestAnimationFrame(() => {
      scrollRafPending = false;
      setActiveNavigation();
    });
  };

  /* --------------------------------------------------------------------------
     4. Smooth Scrolling for Internal Anchors (Reduced-Motion Aware)
     -------------------------------------------------------------------------- */

  const handleAnchorClick = (event, link) => {
    const targetId = link.getAttribute("href");
    if (!targetId || !targetId.startsWith("#") || targetId === "#main-content") {
      return;
    }

    const target = document.querySelector(targetId);
    if (!target) return;

    event.preventDefault();

    const headerHeight = header ? header.offsetHeight : 76;
    const targetPosition =
      target.getBoundingClientRect().top + window.scrollY - headerHeight;

    const navTarget = resolveNavTargetForSection(target);
    applyActiveNavId(navTarget);

    window.scrollTo({
      top: Math.max(0, targetPosition),
      behavior: prefersReducedMotion() ? "auto" : "smooth",
    });

    closeMobileNavigation();
  };

  const navLinkSet = new Set(navigationLinks);
  navigationLinks.forEach((link) => {
    link.addEventListener("click", (event) => handleAnchorClick(event, link));
  });

  document.querySelectorAll('a[href^="#"]').forEach((link) => {
    if (navLinkSet.has(link)) return;
    link.addEventListener("click", (event) => handleAnchorClick(event, link));
  });

  /* --------------------------------------------------------------------------
     5. Outside Click, Escape & rAF Scroll/Resize Listeners
     -------------------------------------------------------------------------- */

  document.addEventListener("click", (event) => {
    if (!mobileDrawer || !mobileMenuButton) return;
    if (!mobileDrawer.classList.contains("is-open")) return;

    if (
      !mobileDrawer.contains(event.target) &&
      !mobileMenuButton.contains(event.target)
    ) {
      closeMobileNavigation();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMobileNavigation({ restoreFocus: true });
    }
  });

  setActiveNavigation({ forceIndicator: true });

  window.addEventListener("scroll", scheduleScrollUpdate, { passive: true });

  let resizeRafPending = false;
  window.addEventListener("resize", () => {
    if (resizeRafPending) return;
    resizeRafPending = true;
    window.requestAnimationFrame(() => {
      resizeRafPending = false;
      setActiveNavigation({ forceIndicator: true });
      if (window.innerWidth > 820) {
        closeMobileNavigation();
      }
    });
  });

  /* --------------------------------------------------------------------------
     6. Scroll-Triggered Content Section Reveal (IntersectionObserver)
     -------------------------------------------------------------------------- */

  const initScrollReveal = () => {
    if (prefersReducedMotion() || typeof IntersectionObserver === "undefined") {
      return;
    }

    const revealGroups = [
      { selector: ".about-intro-header, .section-header, .cta-banner" },
      { parent: ".about-pillars-grid", children: ".about-pillar-card", stepMs: 65 },
      { parent: ".features-grid", children: ".feature-card", stepMs: 55 },
      { parent: ".roles-grid", children: ".role-card", stepMs: 80 },
      { parent: ".security-grid", children: ".security-card", stepMs: 65 },
      { parent: ".workflow-steps-grid", children: ".workflow-step-card", stepMs: 85 },
    ];

    revealGroups.forEach((group) => {
      if (group.selector) {
        document.querySelectorAll(group.selector).forEach((el) => {
          el.setAttribute("data-reveal", "");
        });
        return;
      }

      const container = document.querySelector(group.parent);
      if (!container) return;

      container.querySelectorAll(group.children).forEach((card, index) => {
        card.setAttribute("data-reveal", "");
        if (index > 0) {
          card.style.setProperty("--reveal-delay", `${index * group.stepMs}ms`);
        }
      });
    });

    const revealTargets = document.querySelectorAll("[data-reveal]");
    if (revealTargets.length === 0) return;

    document.documentElement.classList.add("js-motion-ready");

    const revealObserver = new IntersectionObserver(
      (entries, observer) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("is-revealed");
          observer.unobserve(entry.target);
        });
      },
      {
        threshold: 0.1,
        rootMargin: "0px 0px -32px 0px",
      }
    );

    revealTargets.forEach((target) => {
      revealObserver.observe(target);
    });
  };

  initScrollReveal();
});
