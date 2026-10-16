/**
 * Employee HRIS & Leave Management System
 * Landing-page navigation and preview interactions.
 */

document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".site-header");
  const mobileMenuButton = document.getElementById("mobileMenuBtn");
  const mobileDrawer = document.getElementById("mobileDrawer");

  const navigationLinks = document.querySelectorAll(
    ".site-nav-links .nav-link, .mobile-drawer .mobile-nav-link",
  );

  const sections = [...document.querySelectorAll("main section[id]")];

  /**
   * Closes the mobile navigation drawer.
   */
  const closeMobileNavigation = () => {
    if (!mobileDrawer || !mobileMenuButton) return;

    mobileDrawer.classList.remove("is-open");
    mobileMenuButton.classList.remove("is-open");
    mobileMenuButton.setAttribute("aria-expanded", "false");
    mobileMenuButton.setAttribute("aria-label", "Open navigation");
  };

  /**
   * Opens and closes the mobile navigation.
   */
  if (mobileMenuButton && mobileDrawer) {
    mobileMenuButton.addEventListener("click", () => {
      const isOpen = mobileDrawer.classList.toggle("is-open");

      mobileMenuButton.classList.toggle("is-open", isOpen);
      mobileMenuButton.setAttribute("aria-expanded", String(isOpen));

      mobileMenuButton.setAttribute(
        "aria-label",
        isOpen ? "Close navigation" : "Open navigation",
      );
    });
  }

  /**
   * Updates the highlighted navigation item based on scroll position.
   */
  const setActiveNavigation = () => {
    const headerHeight = header?.offsetHeight || 72;
    const marker = window.scrollY + headerHeight + 90;

    let activeId = sections[0]?.id || "home";

    sections.forEach((section) => {
      if (section.offsetTop <= marker) {
        activeId = section.id;
      }
    });

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
  };

  /**
   * Smooth scrolling for desktop and mobile navigation links.
   */
  navigationLinks.forEach((link) => {
    link.addEventListener("click", (event) => {
      const targetId = link.getAttribute("href");

      if (!targetId || !targetId.startsWith("#")) return;

      const target = document.querySelector(targetId);

      if (!target) return;

      event.preventDefault();

      const headerHeight = header?.offsetHeight || 72;
      const targetPosition =
        target.getBoundingClientRect().top +
        window.scrollY -
        headerHeight;

      window.scrollTo({
        top: targetPosition,
        behavior: "smooth",
      });

      closeMobileNavigation();
    });
  });

  /**
   * Smooth scrolling for other internal anchor links, such as buttons
   * and footer navigation.
   */
  document.querySelectorAll('a[href^="#"]').forEach((link) => {
    if ([...navigationLinks].includes(link)) return;

    link.addEventListener("click", (event) => {
      const targetId = link.getAttribute("href");

      if (!targetId || targetId === "#") return;

      const target = document.querySelector(targetId);

      if (!target) return;

      event.preventDefault();

      const headerHeight = header?.offsetHeight || 72;
      const targetPosition =
        target.getBoundingClientRect().top +
        window.scrollY -
        headerHeight;

      window.scrollTo({
        top: targetPosition,
        behavior: "smooth",
      });
    });
  });

  /**
   * Adds simple interaction to the dashboard preview navigation.
   */
  document.querySelectorAll(".mockup-nav-item").forEach((item) => {
    item.addEventListener("click", () => {
      document
        .querySelectorAll(".mockup-nav-item")
        .forEach((navigationItem) => {
          navigationItem.classList.remove("active");
        });

      item.classList.add("active");

      const title = document.querySelector(".mockup-title");

      if (title) {
        title.textContent =
          `HR Portal / ${item.textContent.trim()} (Demo Preview)`;
      }
    });
  });

  /**
   * Close mobile navigation when clicking outside it.
   */
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

  /**
   * Close mobile navigation using the Escape key.
   */
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMobileNavigation();
    }
  });

  setActiveNavigation();

  window.addEventListener("scroll", setActiveNavigation, {
    passive: true,
  });

  window.addEventListener("resize", () => {
    setActiveNavigation();

    if (window.innerWidth > 767) {
      closeMobileNavigation();
    }
  });
});