<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <meta
    name="description"
    content="Employee HRIS and Leave Management System with practical Data Structures and Algorithms demonstrations."
  >

  <title><?= e($appName) ?> — Enterprise HRIS &amp; DSA</title>

  <link
    rel="stylesheet"
    href="<?= e(asset('css/landing.css')) ?>"
  >
</head>

<body class="landing-page">

  <!-- Fixed navigation -->
  <header class="site-header" id="navbar">
    <div class="container nav-container">

      <a
        class="brand-wrapper"
        href="#home"
        aria-label="EmployeeHRIS home"
      >
        <img
          class="brand-symbol"
          src="<?= e(asset('icon/610ca.svg')) ?>"
          alt=""
        >

        <span class="brand-text-block">
          <strong class="brand-title">EmployeeHRIS</strong>
          <span class="brand-subtitle">
            HRIS &amp; Leave Management
          </span>
        </span>
      </a>

      <nav class="desktop-nav" aria-label="Main navigation">
        <ul class="site-nav-links">
          <li>
            <a class="nav-link active" href="#home">
              Home
            </a>
          </li>

          <li>
            <a class="nav-link" href="#features">
              Features
            </a>
          </li>

          <li>
            <a class="nav-link" href="#how-it-works">
              How It Works
            </a>
          </li>

          <li>
            <a class="nav-link" href="#dsa-laboratory">
              DSA Laboratory
            </a>
          </li>

          <li>
            <a class="nav-link" href="#roles">
              Roles
            </a>
          </li>

          <li>
            <a class="nav-link" href="#about">
              About
            </a>
          </li>
        </ul>
      </nav>

      <div class="header-actions">
        <a
          class="btn btn-primary header-cta"
          href="<?= e(url('/login')) ?>"
        >
          Sign In

          <img
            src="<?= e(asset('icon/49e26.svg')) ?>"
            alt=""
          >
        </a>

        <button
          id="mobileMenuBtn"
          class="mobile-menu-btn"
          type="button"
          aria-label="Open navigation"
          aria-controls="mobileDrawer"
          aria-expanded="false"
        >
          <span></span>
          <span></span>
          <span></span>
        </button>
      </div>

    </div>

    <!-- Mobile navigation -->
    <nav
      id="mobileDrawer"
      class="mobile-drawer"
      aria-label="Mobile navigation"
    >
      <a class="mobile-nav-link active" href="#home">
        Home
      </a>

      <a class="mobile-nav-link" href="#features">
        Features
      </a>

      <a class="mobile-nav-link" href="#how-it-works">
        How It Works
      </a>

      <a class="mobile-nav-link" href="#dsa-laboratory">
        DSA Laboratory
      </a>

      <a class="mobile-nav-link" href="#roles">
        Roles
      </a>

      <a class="mobile-nav-link" href="#about">
        About
      </a>
    </nav>
  </header>

  <main>

    <!-- Home -->
    <section id="home" class="hero-section">

      <div class="ambient ambient-one"></div>
      <div class="ambient ambient-two"></div>

      <div class="container hero-grid">

        <div class="hero-content">
          <span class="hero-eyebrow">
            Employee Management · Leave Management · DSA
          </span>

          <h1 class="hero-title">
            Simplify HR Management.
            <span>Organize Every Record.</span>
          </h1>

          <p class="hero-paragraph">
            An Employee Human Resource Information System designed
            to organize employee records, manage leave requests,
            track leave balances, and demonstrate the practical
            application of Data Structures and Algorithms.
          </p>

          <div class="hero-actions">
            <a
              class="btn btn-primary btn-lg"
              href="<?= e(url('/login')) ?>"
            >
              Get Started

              <img
                src="<?= e(asset('icon/49e26.svg')) ?>"
                alt=""
              >
            </a>

            <a
              class="btn btn-secondary btn-lg"
              href="#features"
            >
              Explore Features
            </a>
          </div>

          <div class="hero-meta-line">
            <code>Native PHP 8.x</code>
            <span></span>
            <code>MySQL PDO</code>
            <span></span>
            <code>Custom DSA</code>
          </div>
        </div>

        <!-- Dashboard preview -->
        <div
          class="browser-mockup"
          aria-label="HR portal dashboard preview"
        >
          <div class="browser-header">
            <div class="browser-controls">
              <span class="browser-dot dot-red"></span>
              <span class="browser-dot dot-yellow"></span>
              <span class="browser-dot dot-green"></span>
            </div>

            <div class="browser-address-bar">
              https://hris.local/admin/dashboard
            </div>
          </div>

          <div class="browser-body">

            <aside class="mockup-sidebar">
              <strong class="mockup-brand">
                <img
                  src="<?= e(asset('icon/32208.svg')) ?>"
                  alt=""
                >
                HR PORTAL
              </strong>

              <button
                class="mockup-nav-item active"
                type="button"
              >
                <img
                  src="<?= e(asset('icon/d332b.svg')) ?>"
                  alt=""
                >
                Dashboard
              </button>

              <button class="mockup-nav-item" type="button">
                <img
                  src="<?= e(asset('icon/6144b.svg')) ?>"
                  alt=""
                >
                Employees
              </button>

              <button class="mockup-nav-item" type="button">
                <img
                  src="<?= e(asset('icon/9df7d.svg')) ?>"
                  alt=""
                >
                Leave Queue
              </button>

              <button class="mockup-nav-item" type="button">
                <img
                  src="<?= e(asset('icon/75d07.svg')) ?>"
                  alt=""
                >
                Balances
              </button>

              <button class="mockup-nav-item" type="button">
                <img
                  src="<?= e(asset('icon/0a16f.svg')) ?>"
                  alt=""
                >
                Reports
              </button>

              <button class="mockup-nav-item" type="button">
                <img
                  src="<?= e(asset('icon/df59a.svg')) ?>"
                  alt=""
                >
                DSA Lab
              </button>
            </aside>

            <div class="mockup-content">

              <div class="mockup-header-strip">
                <p class="mockup-title">
                  HR Portal / Administrator Dashboard
                  (Demo Preview)
                </p>

                <span class="mockup-user-pill">
                  Administrator
                </span>
              </div>

              <div class="mockup-stats-row">
                <div class="mockup-stat-card">
                  <span class="mockup-stat-label">
                    Total Staff
                  </span>

                  <strong class="mockup-stat-value">
                    <?= e((string)($stats['total_employees'] ?? 8)) ?>
                  </strong>
                </div>

                <div class="mockup-stat-card">
                  <span class="mockup-stat-label">
                    Active Staff
                  </span>

                  <strong class="mockup-stat-value success">
                    <?= e((string)($stats['active_employees'] ?? 7)) ?>
                  </strong>
                </div>

                <div class="mockup-stat-card">
                  <span class="mockup-stat-label">
                    Pending Leaves
                  </span>

                  <strong class="mockup-stat-value warning">
                    <?= e((string)($stats['pending_leaves'] ?? 3)) ?>
                  </strong>
                </div>
              </div>

              <div class="mockup-table-card">
                <div class="mockup-table-title">
                  <span>Pending Leave Applications</span>
                  <span>FIFO Queue</span>
                </div>

                <table class="mockup-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Employee</th>
                      <th>Type</th>
                      <th>Days</th>
                      <th>Status</th>
                    </tr>
                  </thead>

                  <tbody>
                    <tr>
                      <td>#001</td>
                      <td>John Cruz</td>
                      <td>Vacation</td>
                      <td>5d</td>
                      <td><span>Pending</span></td>
                    </tr>

                    <tr>
                      <td>#002</td>
                      <td>Ana Reyes</td>
                      <td>Sick</td>
                      <td>2d</td>
                      <td><span>Pending</span></td>
                    </tr>

                    <tr>
                      <td>#003</td>
                      <td>Mark Lee</td>
                      <td>Emergency</td>
                      <td>3d</td>
                      <td><span>Pending</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <p class="mockup-watermark">
                Product preview using synthetic demo data.
              </p>
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- Product highlights -->
    <section
      class="trust-section"
      aria-label="Product highlights"
    >
      <div class="container trust-grid">

        <article class="trust-card">
          <span class="trust-icon-box">
            <img
              src="<?= e(asset('icon/29f2b.svg')) ?>"
              alt=""
            >
          </span>

          <div>
            <h3>Organized Employee Records</h3>
            <p>
              Keep profiles, departments, positions,
              and employment status structured.
            </p>
          </div>
        </article>

        <article class="trust-card">
          <span class="trust-icon-box amber">
            <img
              src="<?= e(asset('icon/be178.svg')) ?>"
              alt=""
            >
          </span>

          <div>
            <h3>Streamlined Leave Management</h3>
            <p>
              Support filing, review, approval,
              and leave-history tracking.
            </p>
          </div>
        </article>

        <article class="trust-card">
          <span class="trust-icon-box green">
            <img
              src="<?= e(asset('icon/55784.svg')) ?>"
              alt=""
            >
          </span>

          <div>
            <h3>Clear Leave Balances</h3>
            <p>
              Present allocated, used, and remaining
              leave days clearly.
            </p>
          </div>
        </article>

        <article class="trust-card">
          <span class="trust-icon-box indigo">
            <img
              src="<?= e(asset('icon/81853.svg')) ?>"
              alt=""
            >
          </span>

          <div>
            <h3>Practical DSA Learning</h3>
            <p>
              Connect algorithms and data structures
              to real HR operations.
            </p>
          </div>
        </article>

      </div>
    </section>

    <!-- Features -->
    <section
      id="features"
      class="features-section section-padding"
    >
      <div class="container">

        <div class="section-header">
          <span class="section-eyebrow">Features</span>

          <h2 class="section-title">
            Everything Organized in One HRIS
          </h2>

          <p class="section-desc">
            Explore modules designed to support employee
            administration, leave management, reporting,
            and algorithm demonstrations.
          </p>
        </div>

        <div class="features-grid">

          <article class="feature-card">
            <div class="feature-top">
              <span class="feature-icon-wrapper">
                <img
                  src="<?= e(asset('icon/a8481.svg')) ?>"
                  alt=""
                >
              </span>

              <h3 class="feature-title">
                Employee Management
              </h3>

              <p class="feature-desc">
                Maintain employee profiles, department
                assignments, positions, and employment status.
              </p>
            </div>

            <div class="feature-preview-box employee-preview">
              <img
                src="<?= e(asset('icon/d2495.svg')) ?>"
                alt=""
              >

              <span>
                <strong>David J. Cruz</strong>
                <small>Full Engineer · IT Department</small>
              </span>

              <b>Active</b>
            </div>
          </article>

          <article class="feature-card">
            <div class="feature-top">
              <span class="feature-icon-wrapper indigo">
                <img
                  src="<?= e(asset('icon/8e7fa.svg')) ?>"
                  alt=""
                >
              </span>

              <h3 class="feature-title">
                Department &amp; Position Management
              </h3>

              <p class="feature-desc">
                Organize departments and positions with
                MySQL foreign-key safeguards.
              </p>
            </div>

            <div class="feature-preview-box">
              <strong>Information Technology</strong>
              <span>Integrity protected</span>
            </div>
          </article>

          <article class="feature-card">
            <div class="feature-top">
              <span class="feature-icon-wrapper amber">
                <img
                  src="<?= e(asset('icon/a423e.svg')) ?>"
                  alt=""
                >
              </span>

              <h3 class="feature-title">
                Leave Management
              </h3>

              <p class="feature-desc">
                Submit leave requests and support approval
                workflows with working-day calculations.
              </p>
            </div>

            <div class="feature-preview-box">
              <strong>Vacation Leave · Nov 02–06</strong>
              <span>5 working days</span>
            </div>
          </article>

          <article class="feature-card">
            <div class="feature-top">
              <span class="feature-icon-wrapper green">
                <img
                  src="<?= e(asset('icon/14df8.svg')) ?>"
                  alt=""
                >
              </span>

              <h3 class="feature-title">
                Leave Balance Tracking
              </h3>

              <p class="feature-desc">
                View allocated, used, and remaining leave days
                by employee, leave type, and year.
              </p>
            </div>

            <div class="feature-preview-box mono">
              Vacation: 15 allocated · 3 used · 12 remaining
            </div>
          </article>

          <article class="feature-card">
            <div class="feature-top">
              <span class="feature-icon-wrapper">
                <img
                  src="<?= e(asset('icon/178f1.svg')) ?>"
                  alt=""
                >
              </span>

              <h3 class="feature-title">
                Reports &amp; Activity Logs
              </h3>

              <p class="feature-desc">
                Present workforce reports while preserving
                an immutable activity audit trail.
              </p>
            </div>

            <div class="feature-preview-box mono">
              REPORT_READY · AUDIT_LOGGED
            </div>
          </article>

          <article class="feature-card">
            <div class="feature-top">
              <span class="feature-icon-wrapper red">
                <img
                  src="<?= e(asset('icon/08ac2.svg')) ?>"
                  alt=""
                >
              </span>

              <h3 class="feature-title">
                DSA Laboratory
              </h3>

              <p class="feature-desc">
                Explore custom data structures and compare
                searching and sorting algorithms.
              </p>
            </div>

            <div class="feature-preview-box mono">
              Stack · Queue · HashMap · BST
            </div>
          </article>

        </div>
      </div>
    </section>

    <!-- How it works -->
    <section
      id="how-it-works"
      class="how-it-works-section section-padding"
    >
      <div class="container">

        <div class="section-header">
          <span class="section-eyebrow">
            How It Works
          </span>

          <h2 class="section-title">
            How the HRIS Workflow Operates
          </h2>

          <p class="section-desc">
            A structured employee-to-leave workflow
            protected by role-based access.
          </p>
        </div>

        <div class="steps-sequence-grid">

          <article class="step-card">
            <span class="step-badge">01</span>

            <h3 class="step-title">
              Organize Employee Information
            </h3>

            <p class="step-desc">
              HR creates departments, positions,
              employee profiles, and annual leave allocations.
            </p>
          </article>

          <article class="step-card">
            <span class="step-badge">02</span>

            <h3 class="step-title">
              Submit and Review Leave
            </h3>

            <p class="step-desc">
              Employees file leave requests while HR processes
              the FIFO review queue with balance validation.
            </p>
          </article>

          <article class="step-card">
            <span class="step-badge">03</span>

            <h3 class="step-title">
              Preserve and Report
            </h3>

            <p class="step-desc">
              Approved deductions, history, reports,
              and security activities remain traceable.
            </p>
          </article>

        </div>
      </div>
    </section>

    <!-- DSA Laboratory -->
    <section
      id="dsa-laboratory"
      class="dsa-showcase-section"
    >
      <div class="container">

        <div class="section-header">
          <span class="section-eyebrow">
            DSA Laboratory
          </span>

          <h2 class="section-title">
            Beyond HR Management: DSA in Action
          </h2>

          <p class="section-desc">
            Explore practical algorithm behavior using
            realistic employee and leave datasets.
          </p>
        </div>

        <div class="dsa-pipeline-banner">
          <span class="pipeline-node">HRIS Dataset</span>
          <span class="pipeline-arrow">→</span>
          <span class="pipeline-node">Data Structure</span>
          <span class="pipeline-arrow">→</span>
          <span class="pipeline-node">Algorithm</span>
          <span class="pipeline-arrow">→</span>
          <span class="pipeline-node">Measured Result</span>
        </div>

        <div class="dsa-cards-grid">

          <article class="dsa-card">
            <div>
              <div class="dsa-card-header">
                <img
                  class="dsa-card-icon"
                  src="<?= e(asset('icon/df59a.svg')) ?>"
                  alt=""
                >
                <span class="dsa-complexity-pill">O(1)</span>
              </div>

              <h3 class="dsa-card-title">
                Activity Log Stack
              </h3>

              <p class="dsa-card-desc">
                LIFO processing for recent administrative
                and security activities.
              </p>
            </div>

            <span class="dsa-tech-tag">
              CustomStack.php
            </span>
          </article>

          <article class="dsa-card">
            <div>
              <div class="dsa-card-header">
                <img
                  class="dsa-card-icon"
                  src="<?= e(asset('icon/9df7d.svg')) ?>"
                  alt=""
                >
                <span class="dsa-complexity-pill">O(1)</span>
              </div>

              <h3 class="dsa-card-title">
                Leave Approval Queue
              </h3>

              <p class="dsa-card-desc">
                FIFO processing ensures leave applications
                are reviewed fairly.
              </p>
            </div>

            <span class="dsa-tech-tag">
              CustomQueue.php
            </span>
          </article>

          <article class="dsa-card">
            <div>
              <div class="dsa-card-header">
                <img
                  class="dsa-card-icon"
                  src="<?= e(asset('icon/6144b.svg')) ?>"
                  alt=""
                >
                <span class="dsa-complexity-pill">O(n)</span>
              </div>

              <h3 class="dsa-card-title">
                Linked Employee List
              </h3>

              <p class="dsa-card-desc">
                Sequential employee traversal with node
                insertion and deletion operations.
              </p>
            </div>

            <span class="dsa-tech-tag">
              LinkedList.php
            </span>
          </article>

          <article class="dsa-card">
            <div>
              <div class="dsa-card-header">
                <img
                  class="dsa-card-icon"
                  src="<?= e(asset('icon/29f2b.svg')) ?>"
                  alt=""
                >
                <span class="dsa-complexity-pill">
                  O(1) average
                </span>
              </div>

              <h3 class="dsa-card-title">
                Employee Hash Table
              </h3>

              <p class="dsa-card-desc">
                Fast employee lookup with custom hashing
                and collision handling.
              </p>
            </div>

            <span class="dsa-tech-tag">
              HashTable.php
            </span>
          </article>

          <article class="dsa-card">
            <div>
              <div class="dsa-card-header">
                <img
                  class="dsa-card-icon"
                  src="<?= e(asset('icon/0a16f.svg')) ?>"
                  alt=""
                >
                <span class="dsa-complexity-pill">
                  O(log n)
                </span>
              </div>

              <h3 class="dsa-card-title">
                Search &amp; Sort Benchmarks
              </h3>

              <p class="dsa-card-desc">
                Compare linear and binary search plus
                bubble and merge sort performance.
              </p>
            </div>

            <span class="dsa-tech-tag">
              Search · Sort
            </span>
          </article>

          <article class="dsa-card">
            <div>
              <div class="dsa-card-header">
                <img
                  class="dsa-card-icon"
                  src="<?= e(asset('icon/81853.svg')) ?>"
                  alt=""
                >
                <span class="dsa-complexity-pill">
                  O(log n)
                </span>
              </div>

              <h3 class="dsa-card-title">
                Binary Search Tree
              </h3>

              <p class="dsa-card-desc">
                Hierarchical employee indexing with
                recursive tree traversals.
              </p>
            </div>

            <span class="dsa-tech-tag">
              BST · Recursion
            </span>
          </article>

        </div>

        <div class="dsa-cta-box">
          <a
            class="btn btn-primary"
            href="<?= e(url('/dsa')) ?>"
          >
            Explore the DSA Laboratory

            <img
              src="<?= e(asset('icon/49e26.svg')) ?>"
              alt=""
            >
          </a>
        </div>
      </div>
    </section>

    <!-- Roles -->
    <section
      id="roles"
      class="roles-section section-padding"
    >
      <div class="container">

        <div class="section-header">
          <span class="section-eyebrow">
            User Experience
          </span>

          <h2 class="section-title">
            Tailored Experiences for Every Role
          </h2>

          <p class="section-desc">
            Distinct role-specific interfaces protected
            by authentication and authorization.
          </p>
        </div>

        <div class="roles-grid">

          <article class="role-card">
            <div>
              <div class="role-card-header">
                <span class="role-icon-box">
                  <img
                    src="<?= e(asset('icon/32208.svg')) ?>"
                    alt=""
                  >
                </span>

                <h3 class="role-title">
                  Administrator / HR
                </h3>
              </div>

              <p class="role-desc">
                Manage employee records, organizational
                structure, leave approvals, and audit logs.
              </p>

              <ul class="role-features-list">
                <li class="role-feature-item">
                  <span class="check">✓</span>
                  Master workforce directory
                </li>

                <li class="role-feature-item">
                  <span class="check">✓</span>
                  FIFO leave approval queue
                </li>

                <li class="role-feature-item">
                  <span class="check">✓</span>
                  Department and position configuration
                </li>

                <li class="role-feature-item">
                  <span class="check">✓</span>
                  Reports, balances, audit logs, and DSA access
                </li>
              </ul>
            </div>

            <a
              class="btn btn-secondary"
              href="<?= e(url('/hr/dashboard')) ?>"
            >
              Preview HR Dashboard
            </a>
          </article>

          <article class="role-card">
            <div>
              <div class="role-card-header">
                <span class="role-icon-box">
                  <img
                    src="<?= e(asset('icon/a8481.svg')) ?>"
                    alt=""
                  >
                </span>

                <h3 class="role-title">
                  Authenticated Employee
                </h3>
              </div>

              <p class="role-desc">
                View employment details, leave balances,
                file applications, and track request status.
              </p>

              <ul class="role-features-list">
                <li class="role-feature-item">
                  <span class="check">✓</span>
                  Personal profile and employment status
                </li>

                <li class="role-feature-item">
                  <span class="check">✓</span>
                  Leave balances with visual meters
                </li>

                <li class="role-feature-item">
                  <span class="check">✓</span>
                  Working-day leave calculator
                </li>

                <li class="role-feature-item">
                  <span class="check">✓</span>
                  Application status and history
                </li>
              </ul>
            </div>

            <a
              class="btn btn-secondary"
              href="<?= e(url('/employee/dashboard')) ?>"
            >
              Preview Employee View
            </a>
          </article>

        </div>
      </div>
    </section>

    <!-- About -->
    <section
      id="about"
      class="about-section section-padding"
    >
      <div class="container">

        <div class="section-header">
          <span class="section-eyebrow">
            Academic Rationale
          </span>

          <h2 class="section-title">
            Designed for Practical HRIS and DSA Learning
          </h2>

          <p class="section-desc">
            A dual-purpose architecture combining workforce
            management with computer science outcomes.
          </p>
        </div>

        <div class="about-grid">

          <article class="about-box">
            <h3 class="about-box-title">
              <img
                src="<?= e(asset('icon/8e7fa.svg')) ?>"
                alt=""
              >
              Enterprise HRIS Purpose
            </h3>

            <p class="about-box-desc">
              Provide organizations with a reliable platform
              to maintain employee information, manage
              departments, calculate working-day leave
              deductions, and preserve audit histories.
            </p>
          </article>

          <article class="about-box">
            <h3 class="about-box-title">
              <img
                src="<?= e(asset('icon/81853.svg')) ?>"
                alt=""
              >
              Academic Computer Science Purpose
            </h3>

            <p class="about-box-desc">
              Bridge theory and enterprise software through
              custom stacks, queues, hash tables, trees,
              searching, sorting, and benchmarking.
            </p>
          </article>

        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="cta-section">
      <div class="container cta-container">

        <h2 class="cta-title">
          Your HRIS Workspace Starts Here.
        </h2>

        <p class="cta-desc">
          Sign in to access employee and leave-management
          features available to your assigned role.
        </p>

        <div class="cta-actions">
          <a
            class="btn btn-primary"
            href="<?= e(url('/login')) ?>"
          >
            Sign In to HRIS

            <img
              src="<?= e(asset('icon/49e26.svg')) ?>"
              alt=""
            >
          </a>

          <a
            class="btn btn-secondary"
            href="<?= e(url('/dashboard')) ?>"
          >
            Open App Dashboard
          </a>
        </div>

      </div>
    </section>

  </main>

  <!-- Footer -->
  <footer class="site-footer">
    <div class="container">

      <div class="footer-grid">

        <div>
          <h2 class="footer-brand-title">
            HRIS Portal
          </h2>

          <p class="footer-brand-desc">
            Employee Human Resource Information System
            and Leave Management System using PHP,
            MySQL, and Data Structures &amp; Algorithms.
          </p>

          <p class="footer-code">
            Database: <?= e($dbName) ?> (MySQL/InnoDB)
          </p>
        </div>

        <div>
          <h3 class="footer-col-title">
            Navigation
          </h3>

          <ul class="footer-links-list">
            <li>
              <a class="footer-link" href="#home">
                Home
              </a>
            </li>

            <li>
              <a class="footer-link" href="#features">
                Features
              </a>
            </li>

            <li>
              <a class="footer-link" href="#how-it-works">
                How It Works
              </a>
            </li>

            <li>
              <a class="footer-link" href="#dsa-laboratory">
                DSA Laboratory
              </a>
            </li>

            <li>
              <a class="footer-link" href="#roles">
                Role-Based Access
              </a>
            </li>
          </ul>
        </div>

        <div>
          <h3 class="footer-col-title">
            Prototype Views
          </h3>

          <ul class="footer-links-list">
            <li>
              <a
                class="footer-link"
                href="<?= e(url('/login')) ?>"
              >
                Sign In
              </a>
            </li>

            <li>
              <a
                class="footer-link"
                href="<?= e(url('/hr/dashboard')) ?>"
              >
                HR Dashboard
              </a>
            </li>

            <li>
              <a
                class="footer-link"
                href="<?= e(url('/employee/dashboard')) ?>"
              >
                Employee Dashboard
              </a>
            </li>

            <li>
              <a
                class="footer-link"
                href="<?= e(url('/dsa')) ?>"
              >
                DSA Laboratory
              </a>
            </li>

            <li>
              <a
                class="footer-link"
                href="<?= e(url('/dashboard')) ?>"
              >
                App Dashboard
              </a>
            </li>
          </ul>
        </div>

        <div>
          <h3 class="footer-col-title">
            Technical Foundation
          </h3>

          <p class="footer-brand-desc">
            Built with Native PHP, MySQL, HTML5, CSS3,
            JavaScript, and Data Structures &amp; Algorithms.
          </p>

          <p class="footer-status">
            Status: Project Foundation Active
          </p>
        </div>

      </div>

      <div class="footer-bottom">
        <p>
          &copy; <?= date('Y') ?>
          <?= e($appName) ?>.
          Academic Software Project.
        </p>

        <p>
          Developed in compliance with approved
          system requirements and specifications.
        </p>
      </div>

    </div>
  </footer>

  <script src="<?= e(asset('js/landing.js')) ?>"></script>
</body>
</html>