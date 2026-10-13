document.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".site-header");
  const mobileMenuButton = document.getElementById("mobileMenuBtn");
  const mobileDrawer = document.getElementById("mobileDrawer");

  const sectionIds = [
    "home",
    "features",
    "how-it-works",
    "dsa-laboratory",
    "roles",
    "about",
  ];

  const sections = sectionIds
    .map((id) => document.getElementById(id))
    .filter(Boolean);

  const navigationLinks = document.querySelectorAll(
    ".site-nav-links .nav-link, .mobile-drawer .mobile-nav-link",
  );

  const closeMobileMenu = () => {
    if (!mobileMenuButton || !mobileDrawer) return;

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
        isOpen ? "Close navigation" : "Open navigation",
      );
    });
  }

  const updateActiveNavigation = () => {
    const headerHeight = header?.offsetHeight || 72;
    const activationPoint = headerHeight + 140;

    let activeSectionId = "home";

    sections.forEach((section) => {
      const sectionTop = section.getBoundingClientRect().top;

      if (sectionTop <= activationPoint) {
        activeSectionId = section.id;
      }
    });

    /*
     * Ensure About remains active near the bottom of the page,
     * including the CTA and footer.
     */
    const nearPageBottom =
      window.innerHeight + window.scrollY >=
      document.documentElement.scrollHeight - 20;

    if (nearPageBottom && document.getElementById("about")) {
      activeSectionId = "about";
    }

    navigationLinks.forEach((link) => {
      const linkTarget = link.getAttribute("href");
      const isActive = linkTarget === `#${activeSectionId}`;

      link.classList.toggle("active", isActive);

      if (isActive) {
        link.setAttribute("aria-current", "page");
      } else {
        link.removeAttribute("aria-current");
      }
    });
  };

  const scrollToSection = (target) => {
    const headerHeight = header?.offsetHeight || 72;

    const targetPosition =
      target.getBoundingClientRect().top +
      window.scrollY -
      headerHeight;

    window.scrollTo({
      top: targetPosition,
      behavior: "smooth",
    });
  };

  navigationLinks.forEach((link) => {
    link.addEventListener("click", (event) => {
      const targetId = link.getAttribute("href");

      if (!targetId || !targetId.startsWith("#")) return;

      const target = document.querySelector(targetId);

      if (!target) return;

      event.preventDefault();
      scrollToSection(target);
      closeMobileMenu();
    });
  });

  /*
   * Enable smooth scrolling for buttons and footer links
   * that point to landing-page sections.
   */
  document.querySelectorAll('a[href^="#"]').forEach((link) => {
    if ([...navigationLinks].includes(link)) return;

    link.addEventListener("click", (event) => {
      const targetId = link.getAttribute("href");

      if (!targetId || targetId === "#") return;

      const target = document.querySelector(targetId);

      if (!target) return;

      event.preventDefault();
      scrollToSection(target);
    });
  });

  /*
   * Dashboard preview interaction.
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

  let scrollUpdateRequested = false;

  window.addEventListener(
    "scroll",
    () => {
      if (scrollUpdateRequested) return;

      scrollUpdateRequested = true;

      window.requestAnimationFrame(() => {
        updateActiveNavigation();
        scrollUpdateRequested = false;
      });
    },
    { passive: true },
  );

  window.addEventListener("resize", () => {
    updateActiveNavigation();

    if (window.innerWidth > 767) {
      closeMobileMenu();
    }
  });

  updateActiveNavigation();
});