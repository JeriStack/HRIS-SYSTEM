/**
 * NEXORA HR — People. Processes. Progress.
 * Landing Page Interactive Controllers:
 * - Theme Management (Light Mode Default, Dark Mode Option, Persistence, FOUC Prevention)
 * - Responsive Mobile Navigation Drawer & Keyboard/Outside Listeners
 * - Active Scroll Spy & Sliding Navbar Indicator
 * - Interactive NEXORA HR Workspace Preview
 */

document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".site-header");
  const mobileMenuButton = document.getElementById("mobileMenuBtn");
  const mobileDrawer = document.getElementById("mobileDrawer");
  const themeToggleBtn = document.getElementById("themeToggleBtn");
  const mobileThemeToggleBtn = document.getElementById("mobileThemeToggleBtn");
  const desktopNavList = document.getElementById("desktopNavLinks");
  const navScrollIndicator = document.getElementById("navScrollIndicator");

  const navigationLinks = document.querySelectorAll(
    ".site-nav-links .nav-link, .mobile-drawer .mobile-nav-link"
  );

  const trackedSections = [
    ...document.querySelectorAll("main section[data-nav-section], main section[id]"),
  ];

  /* --------------------------------------------------------------------------
     1. Theme Management (White Mode Default, Dark Mode Option)
     -------------------------------------------------------------------------- */

  const THEME_STORAGE_KEY = "nexora_theme";

  const updateThemeUI = (theme) => {
    const isDark = theme === "dark";
    const label = isDark ? "Switch to light mode" : "Switch to dark mode";

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

  const setTheme = (theme) => {
    const nextTheme = theme === "dark" ? "dark" : "light";
    document.documentElement.setAttribute("data-theme", nextTheme);
    try {
      localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
    } catch {
      // Storage may be restricted in private browsing modes
    }
    updateThemeUI(nextTheme);
  };

  const currentTheme =
    document.documentElement.getAttribute("data-theme") === "dark"
      ? "dark"
      : "light";
  updateThemeUI(currentTheme);

  const handleThemeToggle = () => {
    const active =
      document.documentElement.getAttribute("data-theme") === "dark"
        ? "light"
        : "dark";
    setTheme(active);
  };

  if (themeToggleBtn) {
    themeToggleBtn.addEventListener("click", handleThemeToggle);
  }

  if (mobileThemeToggleBtn) {
    mobileThemeToggleBtn.addEventListener("click", handleThemeToggle);
  }

  /* --------------------------------------------------------------------------
     2. Mobile Navigation Drawer
     -------------------------------------------------------------------------- */

  const closeMobileNavigation = () => {
    if (!mobileDrawer || !mobileMenuButton) return;

    mobileDrawer.classList.remove("is-open");
    mobileMenuButton.classList.remove("is-open");
    mobileMenuButton.setAttribute("aria-expanded", "false");
    mobileMenuButton.setAttribute("aria-label", "Open navigation");
  };

  if (mobileMenuButton && mobileDrawer) {
    mobileMenuButton.addEventListener("click", () => {
      const isOpen = mobileDrawer.classList.toggle("is-open");

      mobileMenuButton.classList.toggle("is-open", isOpen);
      mobileMenuButton.setAttribute("aria-expanded", String(isOpen));
      mobileMenuButton.setAttribute(
        "aria-label",
        isOpen ? "Close navigation" : "Open navigation"
      );
    });
  }

  /* --------------------------------------------------------------------------
     3. Active Scroll Spy & Sliding Indicator
     -------------------------------------------------------------------------- */

  const updateSlidingIndicator = () => {
    if (!desktopNavList || !navScrollIndicator) return;

    const activeDesktopLink = desktopNavList.querySelector(".nav-link.active");
    if (!activeDesktopLink) {
      navScrollIndicator.style.opacity = "0";
      return;
    }

    const listRect = desktopNavList.getBoundingClientRect();
    const linkRect = activeDesktopLink.getBoundingClientRect();
    const offsetX = linkRect.left - listRect.left;

    navScrollIndicator.style.width = `${linkRect.width}px`;
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

  const applyActiveNavId = (activeId) => {
    navigationLinks.forEach((link) => {
      const href = link.getAttribute("href");
      const isActive = href === `#${activeId}`;

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

  const setActiveNavigation = () => {
    const headerHeight = header?.offsetHeight || 76;
    const triggerLine = headerHeight + 120;

    let activeId = "home";

    for (const section of trackedSections) {
      const rect = section.getBoundingClientRect();
      if (rect.top <= triggerLine) {
        activeId = resolveNavTargetForSection(section);
      }
    }

    // If scrolled near the bottom of the document, activate the final nav item
    const scrollTop = window.scrollY || document.documentElement.scrollTop || 0;
    const viewportBottom = scrollTop + window.innerHeight;
    const docHeight = Math.max(
      document.body.scrollHeight,
      document.documentElement.scrollHeight
    );

    if (docHeight - viewportBottom <= 48) {
      activeId = "how-it-works";
    }

    applyActiveNavId(activeId);
  };

  /* --------------------------------------------------------------------------
     4. Smooth Scrolling for Internal Anchors
     -------------------------------------------------------------------------- */

  const handleAnchorClick = (event, link) => {
    const targetId = link.getAttribute("href");
    if (!targetId || !targetId.startsWith("#")) return;

    const target = document.querySelector(targetId);
    if (!target) return;

    event.preventDefault();

    const headerHeight = header?.offsetHeight || 76;
    const targetPosition =
      target.getBoundingClientRect().top + window.scrollY - headerHeight;

    const navTarget = resolveNavTargetForSection(target);
    applyActiveNavId(navTarget);

    window.scrollTo({
      top: Math.max(0, targetPosition),
      behavior: "smooth",
    });

    closeMobileNavigation();
  };

  navigationLinks.forEach((link) => {
    link.addEventListener("click", (event) => handleAnchorClick(event, link));
  });

  document.querySelectorAll('a[href^="#"]').forEach((link) => {
    if ([...navigationLinks].includes(link)) return;
    link.addEventListener("click", (event) => handleAnchorClick(event, link));
  });

  /* --------------------------------------------------------------------------
     5. Outside Click, Escape & Scroll/Resize Listeners
     -------------------------------------------------------------------------- */

  document.addEventListener("click", (event) => {
    if (!mobileDrawer || !mobileMenuButton) return;

    const clickedInsideDrawer = mobileDrawer.contains(event.target);
    const clickedMenuButton = mobileMenuButton.contains(event.target);

    if (
      mobileDrawer.classList.contains("is-open") &&
      !clickedInsideDrawer &&
      !clickedMenuButton
    ) {
      closeMobileNavigation();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMobileNavigation();
    }
  });

  setActiveNavigation();

  window.addEventListener("scroll", setActiveNavigation, { passive: true });
  document.addEventListener("scroll", setActiveNavigation, {
    passive: true,
    capture: true,
  });

  window.addEventListener("resize", () => {
    setActiveNavigation();
    if (window.innerWidth > 820) {
      closeMobileNavigation();
    }
  });
});
