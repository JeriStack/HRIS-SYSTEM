<!doctype html>
<html lang="en" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta
    name="description"
    content="NEXORA HR — People. Processes. Progress. A centralized enterprise Human Resource Information System for employee records, policy-driven leave administration, announcements, and role-based governance.">
  <meta name="color-scheme" content="light dark">

  <title>NEXORA HR &mdash; People. Processes. Progress.</title>

  <!-- Prevent FOUC: Light Mode default on first visit; restore saved user preference and sync color-scheme immediately -->
  <script>
    (function() {
      var theme = 'light';
      try {
        localStorage.removeItem('hris_landing_theme');
        if (localStorage.getItem('nexora_theme') === 'dark') {
          theme = 'dark';
        }
      } catch (e) {}
      document.documentElement.setAttribute('data-theme', theme);
      var meta = document.querySelector('meta[name="color-scheme"]');
      if (meta) {
        meta.setAttribute('content', theme);
      }
    })();
  </script>

  <link rel="icon" type="image/png" href="<?= e(asset('img/nexora-logo-icon.png')) ?>">

  <?php
  $cssPath = __DIR__ . '/../../../public/assets/css/landing.css';
  $cssVer = file_exists($cssPath) ? filemtime($cssPath) : '3.0';
  $jsPath = __DIR__ . '/../../../public/assets/js/landing.js';
  $jsVer = file_exists($jsPath) ? filemtime($jsPath) : '3.0';
  ?>

  <link
    rel="stylesheet"
    href="<?= e(asset('css/landing.css')) ?>?v=<?= e((string)$cssVer) ?>">
</head>

<body class="landing-page">

  <!-- Accessible Skip to Content Link -->
  <a href="#main-content" class="skip-link">Skip to main content</a>

  <!-- A. Navigation Header -->
  <header class="site-header" id="navbar">
    <div class="container nav-container">

      <a
        class="brand-wrapper"
        href="#home"
        aria-label="NEXORA HR Home">
        <img
          class="brand-symbol"
          src="<?= e(asset('img/nexora-logo-icon.png')) ?>"
          alt=""
          width="42"
          height="42"
          decoding="async">
        <span class="brand-text-block">
          <span class="brand-title">
            <span class="brand-title-navy">NEXORA</span>
            <span class="brand-title-blue">HR</span>
          </span>
        </span>
      </a>

      <nav class="desktop-nav" aria-label="Primary">
        <ul class="site-nav-links" id="desktopNavLinks">
          <li>
            <a class="nav-link active" href="#home" aria-current="page">
              Home
            </a>
          </li>
          <li>
            <a class="nav-link" href="#features">
              Features
            </a>
          </li>
          <li>
            <a class="nav-link" href="#about">
              About
            </a>
          </li>
          <li>
            <a class="nav-link" href="#how-it-works">
              How It Works
            </a>
          </li>
          <li class="nav-indicator-item" aria-hidden="true">
            <span class="nav-scroll-indicator" id="navScrollIndicator"></span>
          </li>
        </ul>
      </nav>

      <div class="header-actions">
        <!-- Light / Dark Theme Toggle Button -->
        <button
          id="themeToggleBtn"
          class="theme-toggle-btn"
          type="button"
          aria-label="Switch to dark mode"
          title="Switch to dark mode">
          <!-- Moon Icon (shown in Light Mode to switch to Dark Mode) -->
          <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
          <!-- Sun Icon (shown in Dark Mode to switch to Light Mode) -->
          <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="5"></circle>
            <line x1="12" y1="1" x2="12" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="23"></line>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
            <line x1="1" y1="12" x2="3" y2="12"></line>
            <line x1="21" y1="12" x2="23" y2="12"></line>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
          </svg>
        </button>

        <!-- Public Help & Support Link (Icon-Only Utility Button) -->
        <a
          id="headerHelpSupportLink"
          class="header-icon-btn"
          href="<?= e(url('/auth/help')) ?>"
          aria-label="Help &amp; Support"
          title="Help &amp; Support">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
        </a>

        <a
          class="btn btn-primary header-cta"
          href="<?= e(url('/login')) ?>">
          Login
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
            <polyline points="10 17 15 12 10 7"></polyline>
            <line x1="15" y1="12" x2="3" y2="12"></line>
          </svg>
        </a>

        <button
          id="mobileMenuBtn"
          class="mobile-menu-btn"
          type="button"
          aria-label="Open menu"
          aria-controls="mobileDrawer"
          aria-expanded="false">
          <span></span>
          <span></span>
          <span></span>
        </button>
      </div>

    </div>

    <!-- Mobile Navigation Drawer -->
    <nav
      id="mobileDrawer"
      class="mobile-drawer"
      aria-label="Mobile"
      inert>
      <a class="mobile-nav-link active" href="#home">
        Home
      </a>
      <a class="mobile-nav-link" href="#features">
        Features
      </a>
      <a class="mobile-nav-link" href="#about">
        About
      </a>
      <a class="mobile-nav-link" href="#how-it-works">
        How It Works
      </a>

      <div class="mobile-drawer-footer">
        <a
          class="btn btn-primary mobile-drawer-cta"
          href="<?= e(url('/login')) ?>">
          Login to NEXORA HR
        </a>

        <a
          class="btn btn-secondary mobile-drawer-cta"
          href="<?= e(url('/auth/help')) ?>">
          Help &amp; Support
        </a>

        <button
          id="mobileThemeToggleBtn"
          class="mobile-theme-toggle"
          type="button"
          aria-label="Switch to dark mode">
          <span class="mobile-theme-label-wrap">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="5"></circle>
              <line x1="12" y1="1" x2="12" y2="3"></line>
              <line x1="12" y1="21" x2="12" y2="23"></line>
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
              <line x1="1" y1="12" x2="3" y2="12"></line>
              <line x1="21" y1="12" x2="23" y2="12"></line>
            </svg>
            <span class="theme-mode-text">Theme: Light</span>
          </span>
          <span class="mobile-theme-toggle-hint">Switch</span>
        </button>
      </div>
    </nav>
  </header>

  <main id="main-content" tabindex="-1">

    <!-- B. Hero Section -->
    <section id="home" data-nav-section="home" class="hero-section">
      <div class="container hero-grid">

        <!-- Brand Messaging & Actions -->
        <div class="hero-content">
          <h1 class="hero-title">
            People Management, Made More Organized.
          </h1>

          <p class="hero-paragraph">
            NEXORA HR brings employee records, policy-driven leave administration, workplace announcements, and secure role-based governance together in one centralized platform.
          </p>

          <div class="hero-actions">
            <a
              class="btn btn-primary btn-lg"
              href="<?= e(url('/login')) ?>">
              Get Started
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </a>

            <a
              class="btn btn-secondary btn-lg"
              href="#features">
              Explore Features
            </a>
          </div>

          <div class="hero-brand-pillars">
            <span class="pillar-pill">
              <span class="pillar-dot" aria-hidden="true"></span>
              Employee Records
            </span>
            <span class="pillar-pill">
              <span class="pillar-dot teal" aria-hidden="true"></span>
              Leave Governance
            </span>
            <span class="pillar-pill">
              <span class="pillar-dot navy" aria-hidden="true"></span>
              Real-Time Operations
            </span>
          </div>
        </div>

      </div>
    </section>

    <!-- D. Core Features (#features) -->
    <section id="features" data-nav-section="features" class="features-section section-padding">
      <div class="container">

        <div class="section-header">
          <h2 class="section-title">
            Everything Essential for Organized HR Operations
          </h2>
          <p class="section-desc">
            Designed around the core workflows that HR teams, system administrators, and employees rely on every day.
          </p>
        </div>

        <div class="features-grid">

          <!-- 1. Employee Management -->
          <article class="feature-card">
            <span class="feature-icon-box blue" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <line x1="20" y1="8" x2="20" y2="14"></line>
                <line x1="23" y1="11" x2="17" y2="11"></line>
              </svg>
            </span>
            <h3 class="feature-title">Employee Management</h3>
            <p class="feature-desc">
              Create, search, and maintain complete employee profiles including employee codes, tenure history, emergency contacts, and employment status.
            </p>
          </article>

          <!-- 2. Leave Management -->
          <article class="feature-card">
            <span class="feature-icon-box teal" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
            </span>
            <h3 class="feature-title">Leave Management</h3>
            <p class="feature-desc">
              Submit, review, and adjudicate leave requests with automatic business-day calculations, overlap detection, attachments, and review remarks.
            </p>
          </article>

          <!-- 3. Leave Balances & Ledger -->
          <article class="feature-card">
            <span class="feature-icon-box navy" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
            </span>
            <h3 class="feature-title">Leave Balances &amp; Ledger</h3>
            <p class="feature-desc">
              Monitor annual allocations, used credits, and remaining days per leave type with automatic, atomic balance updates upon HR approval.
            </p>
          </article>

          <!-- 4. Departments & Positions -->
          <article class="feature-card">
            <span class="feature-icon-box blue" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 21h18"></path>
                <path d="M5 21V7l8-4v18"></path>
                <path d="M19 21V11l-6-4"></path>
              </svg>
            </span>
            <h3 class="feature-title">Departments &amp; Positions</h3>
            <p class="feature-desc">
              Organize departments and linked job titles with active headcount visibility and database-enforced referential integrity across records.
            </p>
          </article>

          <!-- 5. Company Announcements -->
          <article class="feature-card">
            <span class="feature-icon-box teal" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 11l18-5v12L3 13v-2z"></path>
                <path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"></path>
              </svg>
            </span>
            <h3 class="feature-title">Company Announcements</h3>
            <p class="feature-desc">
              Draft, schedule, and publish targeted workplace announcements with automatic expiration lifecycles and verifiable employee read receipts.
            </p>
          </article>

          <!-- 6. Help & Support Desk -->
          <article class="feature-card">
            <span class="feature-icon-box navy" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
              </svg>
            </span>
            <h3 class="feature-title">Help &amp; Support Desk</h3>
            <p class="feature-desc">
              Manage employee support inquiries, confidential HR complaints, and public sign-in recovery tickets with strict internal-note isolation.
            </p>
          </article>

          <!-- 7. Real-Time Notifications -->
          <article class="feature-card">
            <span class="feature-icon-box blue" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
              </svg>
            </span>
            <h3 class="feature-title">Real-Time Notifications</h3>
            <p class="feature-desc">
              Deliver instant WebSocket push alerts and inbox updates whenever leave requests, announcements, or support ticket responses occur.
            </p>
          </article>

          <!-- 8. Reporting & Audit Trail -->
          <article class="feature-card">
            <span class="feature-icon-box teal" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
              </svg>
            </span>
            <h3 class="feature-title">Reporting &amp; Audit Trail</h3>
            <p class="feature-desc">
              Generate workforce summaries and CSV leave reports alongside immutable, secret-redacted activity logs for governance accountability.
            </p>
          </article>

        </div>
      </div>
    </section>

    <!-- C. Product Introduction (#about) -->
    <section id="about" data-nav-section="about" class="about-intro-section section-padding">
      <div class="container">

        <div class="about-intro-layout">
          <div class="about-intro-header">
            <h2 class="section-title">
              A Better Way to Manage Your Workforce
            </h2>
            <p class="section-desc">
              NEXORA HR replaces scattered spreadsheets and manual leave tracking with a structured, role-aware platform built for everyday human resource administration.
            </p>
          </div>

          <div class="about-pillars-grid">
            <article class="about-pillar-card">
              <div class="pillar-icon-wrap navy" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
              </div>
              <h3 class="pillar-card-title">Centralized Employee Records</h3>
              <p class="pillar-card-desc">
                Keep employee profiles, contact details, department placements, job positions, and employment history organized in one authoritative directory.
              </p>
            </article>

            <article class="about-pillar-card">
              <div class="pillar-icon-wrap blue" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                  <path d="M9 16l2 2 4-4"></path>
                </svg>
              </div>
              <h3 class="pillar-card-title">Policy-Driven Leave Ledger</h3>
              <p class="pillar-card-desc">
                Streamline the full leave lifecycle from business-day validation and overlap checks to FIFO HR review and transaction-safe credit deductions.
              </p>
            </article>

            <article class="about-pillar-card">
              <div class="pillar-icon-wrap teal" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
              </div>
              <h3 class="pillar-card-title">Clear Operational Visibility</h3>
              <p class="pillar-card-desc">
                Give HR and administrators real-time insight into pending queues, system health, and audit trails while empowering staff with self-service access.
              </p>
            </article>
          </div>
        </div>

      </div>
    </section>

    <!-- F. User Experiences (#roles) -->
    <section id="roles" data-nav-section="about" class="roles-section section-padding">
      <div class="container">

        <div class="section-header">
          <h2 class="section-title">
            The Right Experience for Every User
          </h2>
          <p class="section-desc">
            NEXORA HR delivers dedicated, role-appropriate workspaces tailored to HR &amp; System Administrators and Employees.
          </p>
        </div>

        <div class="roles-grid">

          <!-- HR & Administration Experience -->
          <article class="role-card role-card-hr">
            <div class="role-card-body">
              <div class="role-card-header">
                <span class="role-icon-badge navy" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                  </svg>
                </span>
                <div>
                  <h3 class="role-title">HR &amp; Administration</h3>
                  <p class="role-subtitle">Operational &amp; Governance Portals</p>
                </div>
              </div>

              <p class="role-summary">
                End-to-end operational control over workforce records, leave adjudication, announcements, support triage, and security governance.
              </p>

              <details class="role-capabilities-dropdown">
                <summary class="role-capabilities-trigger">
                  <span class="role-capabilities-label">Key Capabilities</span>
                  <span class="role-capabilities-count">5</span>
                </summary>
                <ul class="role-checklist" role="list">
                  <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Manage employee profiles, departments, and job positions.</span>
                  </li>
                  <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Review FIFO leave queues and process approvals atomically.</span>
                  </li>
                  <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Publish scheduled announcements and monitor read receipts.</span>
                  </li>
                  <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Triage HR support tickets and confidential employee complaints.</span>
                  </li>
                  <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Oversee dynamic RBAC permissions, system health, and audit logs.</span>
                  </li>
                </ul>
              </details>
            </div>

            <div class="role-card-footer">
              <a class="btn btn-primary role-cta" href="<?= e(url('/login')) ?>">
                Sign In to HR Workspace
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                  <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
              </a>
            </div>
          </article>

          <!-- Employees Experience -->
          <article class="role-card role-card-employee">
            <div class="role-card-body">
              <div class="role-card-header">
                <span class="role-icon-badge teal" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </span>
                <div>
                  <h3 class="role-title">Employees</h3>
                  <p class="role-subtitle">Self-Service Workspace</p>
                </div>
              </div>

              <p class="role-summary">
                Focused self-service access to personal employment records, leave entitlements, workplace updates, and account security controls.
              </p>

              <details class="role-capabilities-dropdown">
                <summary class="role-capabilities-trigger">
                  <span class="role-capabilities-label">Key Capabilities</span>
                  <span class="role-capabilities-count">5</span>
                </summary>
                <ul class="role-checklist" role="list">
                  <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Inspect personal employment details and leave credit balances.</span>
                  </li>
                  <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>File leave applications with instant business-day calculation.</span>
                  </li>
                  <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Track request statuses and receive live approval notifications.</span>
                  </li>
                  <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Read company announcements and submit HR help requests.</span>
                  </li>
                  <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Manage two-factor security and trusted browser sessions.</span>
                  </li>
                </ul>
              </details>
            </div>

            <div class="role-card-footer">
              <a class="btn btn-secondary role-cta" href="<?= e(url('/login')) ?>">
                Sign In to Employee Portal
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                  <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
              </a>
            </div>
          </article>

        </div>
      </div>
    </section>

    <!-- G. Security and Reliability (#security) -->
    <section id="security" data-nav-section="about" class="security-section section-padding">
      <div class="container">

        <div class="section-header">
          <h2 class="section-title">
            Built Around Responsible Information Management
          </h2>
          <p class="section-desc">
            Every layer of NEXORA HR is engineered to safeguard employee records, enforce strict role boundaries, and preserve accurate leave data.
          </p>
        </div>

        <div class="security-grid">

          <article class="security-card">
            <span class="security-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
            </span>
            <div>
              <h3 class="security-card-title">Smart OTP &amp; Session Security</h3>
              <p class="security-card-desc">
                Protects accounts with bcrypt password hashing, Email OTP and TOTP two-factor authentication, revocable trusted devices, and automatic idle timeout controls.
              </p>
            </div>
          </article>

          <article class="security-card">
            <span class="security-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
              </svg>
            </span>
            <div>
              <h3 class="security-card-title">Dynamic Three-Role Authorization</h3>
              <p class="security-card-desc">
                Server-side RBAC middleware enforces strict separation across Admin, HR, and Employee permissions with record-level ownership checks on every route and WebSocket channel.
              </p>
            </div>
          </article>

          <article class="security-card">
            <span class="security-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 11 12 14 22 4"></polyline>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
              </svg>
            </span>
            <div>
              <h3 class="security-card-title">Request Protection &amp; Audit Trail</h3>
              <p class="security-card-desc">
                Every state-changing form is guarded by CSRF tokens, strict input validation, context-aware HTML escaping, and immutable secret-redacted activity logs.
              </p>
            </div>
          </article>

          <article class="security-card">
            <span class="security-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
              </svg>
            </span>
            <div>
              <h3 class="security-card-title">Transactional &amp; Async Reliability</h3>
              <p class="security-card-desc">
                Uses parameterized PDO prepared statements, ACID transactions for leave balance deductions, and a MySQL-backed background queue for resilient email and notification delivery.
              </p>
            </div>
          </article>

        </div>
      </div>
    </section>

    <!-- E. How It Works (#how-it-works) -->
    <section id="how-it-works" data-nav-section="how-it-works" class="workflow-section section-padding">
      <span id="workflow" class="anchor-target" aria-hidden="true"></span>
      <div class="container">

        <div class="section-header">
          <h2 class="section-title">
            Simplifying Everyday HR Processes
          </h2>
          <p class="section-desc">
            NEXORA HR connects workforce organization, leave administration, and operational tracking in three clear steps.
          </p>
        </div>

        <ol class="workflow-steps-grid" role="list">

          <li class="workflow-step-card">
            <div class="workflow-step-top">
              <span class="workflow-step-badge">Step 1</span>
              <span class="workflow-step-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
              </span>
            </div>
            <h3 class="workflow-step-title">
              Organize Employee Information
            </h3>
            <p class="workflow-step-desc">
              HR establishes departments and positions, registers employee profiles, provisions role-based accounts, and initializes annual leave allocations in one centralized directory.
            </p>
          </li>

          <li class="workflow-step-card">
            <div class="workflow-step-top">
              <span class="workflow-step-badge">Step 2</span>
              <span class="workflow-step-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
              </span>
            </div>
            <h3 class="workflow-step-title">
              Submit &amp; Validate Leave Requests
            </h3>
            <p class="workflow-step-desc">
              Employees file leave applications from their self-service portal. The system checks remaining credits, business days, and date overlaps before queuing requests for HR review.
            </p>
          </li>

          <li class="workflow-step-card">
            <div class="workflow-step-top">
              <span class="workflow-step-badge">Step 3</span>
              <span class="workflow-step-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
              </span>
            </div>
            <h3 class="workflow-step-title">
              Review, Notify, and Audit
            </h3>
            <p class="workflow-step-desc">
              HR processes approvals or rejections in FIFO order. Leave ledgers, real-time employee notifications, operational reports, and immutable audit logs update immediately.
            </p>
          </li>

        </ol>

      </div>
    </section>

    <!-- H. Final Call to Action -->
    <section data-nav-section="how-it-works" class="cta-section">
      <div class="container">
        <div class="cta-banner">
          <div class="cta-content">
            <h2 class="cta-title">
              Bring Your HR Operations Together.
            </h2>
            <p class="cta-desc">
              Manage employee records, leave workflows, and workplace governance with ledger-grade clarity in NEXORA HR.
            </p>
          </div>

          <div class="cta-actions">
            <a
              class="btn btn-cta-white btn-lg"
              href="<?= e(url('/login')) ?>">
              Access NEXORA HR
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </a>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- I. Footer -->
  <footer class="site-footer">
    <div class="container">

      <div class="footer-grid">

        <div class="footer-brand-col">
          <a class="brand-wrapper footer-brand-link" href="#home" aria-label="NEXORA HR Home">
            <img
              class="brand-symbol"
              src="<?= e(asset('img/nexora-logo-icon.png')) ?>"
              alt=""
              width="40"
              height="40"
              loading="lazy"
              decoding="async">
            <span class="brand-text-block">
              <span class="brand-title">
                <span class="brand-title-navy">NEXORA</span>
                <span class="brand-title-blue">HR</span>
              </span>
            </span>
          </a>

          <p class="footer-brand-desc">
            A centralized enterprise Human Resource Information System that organizes employee records, leave workflows, and role-based HR governance.
          </p>

          <p class="footer-tagline-badge">
            People. Processes. Progress.
          </p>
        </div>

        <div>
          <h3 class="footer-col-title">Navigation</h3>
          <ul class="footer-links-list" role="list">
            <li><a class="footer-link" href="#home">Home</a></li>
            <li><a class="footer-link" href="#about">About</a></li>
            <li><a class="footer-link" href="#features">Features</a></li>
            <li><a class="footer-link" href="#how-it-works">How It Works</a></li>
          </ul>
        </div>

        <div>
          <h3 class="footer-col-title">Platform</h3>
          <ul class="footer-links-list" role="list">
            <li><a class="footer-link" href="#roles">Admin, HR &amp; Employee Roles</a></li>
            <li><a class="footer-link" href="#security">Security &amp; Reliability</a></li>
            <li><a class="footer-link" href="<?= e(url('/login')) ?>">Sign In</a></li>
          </ul>
        </div>

        <div>
          <h3 class="footer-col-title">System Access</h3>
          <ul class="footer-links-list" role="list">
            <li><a class="footer-link" href="<?= e(url('/login')) ?>">Account Login</a></li>
            <li><a class="footer-link" href="<?= e(url('/auth/help')) ?>">Help &amp; Support</a></li>
            <li><a class="footer-link" href="<?= e(url('/forgot-password')) ?>">Forgot Password</a></li>
            <li><a class="footer-link" href="<?= e(url('/auth/support/track')) ?>">Track Support Request</a></li>
            <li><a class="footer-link" href="<?= e(url('/health')) ?>">System Health Check</a></li>
          </ul>
        </div>

      </div>

      <div class="footer-bottom">
        <p>
          &copy; <?= date('Y') ?> NEXORA HR. All rights reserved. People. Processes. Progress.
        </p>

        <div class="footer-bottom-links">
          <a href="#home">Back to Top</a>
          <span aria-hidden="true">&bull;</span>
          <a href="<?= e(url('/auth/help')) ?>">Help &amp; Support</a>
          <span aria-hidden="true">&bull;</span>
          <a href="<?= e(url('/login')) ?>">Login</a>
        </div>
      </div>

    </div>
  </footer>

  <script defer src="<?= e(asset('js/landing.js')) ?>?v=<?= e((string)$jsVer) ?>"></script>
</body>

</html>
