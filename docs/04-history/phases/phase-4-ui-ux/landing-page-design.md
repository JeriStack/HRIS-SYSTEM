# Landing Page Design & Frontend Specification
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design & Frontend Implementation  
**Status:** Approved & Implemented  
**Primary Routes:** `GET /` (`public/index.php`), `GET /landing.html` (`public/landing.html`)  

---

## 1. Objective & Purpose

The landing page functions as the public welcome portal for the **Employee HRIS & Leave Management System**. It provides a modern, SaaS-inspired introduction to the system's human resource capabilities, explains leave-management workflows, highlights the core academic Data Structures & Algorithms (DSA) component, and provides a clear gateway to the authentication portal for authorized personnel.

The landing page is strictly public and does not expose protected employee records or live operational data.

---

## 2. Visual Design System & Design Tokens

The landing page shares the project's enterprise design token system defined in [`landing.css`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/public/assets/css/landing.css):

| Token Name | Value | Role |
| :--- | :--- | :--- |
| `--color-primary` | `#2563EB` | Primary CTA buttons, active state indicators, focus rings |
| `--color-primary-hover`| `#1D4ED8` | Hover transition for interactive buttons |
| `--color-navy` | `#172033` | Brand headings, navigation text, hero heading |
| `--color-navy-dark`| `#0F172A` | Background surface for DSA section and dark footer |
| `--color-bg` | `#F5F7FB` | Main page canvas background surface |
| `--color-surface` | `#FFFFFF` | Card surfaces, browser mockup frame, and header |
| `--color-text-primary`| `#172033` | Primary body typography |
| `--color-text-secondary`| `#64748B` | Subheadings, card body text, metadata |
| `--color-border` | `#E2E8F0` | Dividers, card borders, table borders |
| `--color-success` | `#15803D` | Approved badges, verified indicators |
| `--color-warning` | `#B45309` | Pending request status, cautionary notices |

---

## 3. Structural Section Breakdown

### Section 1: Sticky Navigation Bar
- **Brand Identity:** Geometric icon `H` with dual title: `HRIS` and `Employee HRIS & Leave Management System`.
- **Navigation Links:** `Home`, `Features`, `How It Works`, `DSA Laboratory`, `Roles`, `About`.
- **Primary Action:** `Sign In` button linking directly to the authentication portal (`prototype/login.html`).
- **Responsive Handling:** Collapsible off-canvas mobile drawer with accessible toggle (`#mobileMenuBtn`).

### Section 2: Two-Column Hero
- **Left Column (Messaging):**
  - Eyebrow tag: `EMPLOYEE MANAGEMENT • LEAVE MANAGEMENT • DSA`
  - Headline: `Simplify HR Management. Organize Every Record.`
  - Narrative: Explains core record management, leave workflows, and DSA practical application.
  - Action buttons: `Sign In` (primary) and `Explore Features` (secondary, smooth scrolls to `#features`).
  - Architecture indicator: `Built with Native PHP 8.x • MySQL PDO • Custom DSA`.
- **Right Column (Product Preview):**
  - High-fidelity browser mockup frame (`● ● ●` window controls, `https://hris.local/admin/dashboard`).
  - Mini sidebar navigation, KPI summary cards (`Total: 8`, `Active: 7`, `Pending: 3`).
  - Pending leave queue table with status badges (`Pending`, `Approved`).
  - Labeled watermark clarifying synthetic demonstration data.

### Section 3: Trust & Value Highlights
Four horizontal cards emphasizing institutional strengths:
1. Organized Employee Records
2. Streamlined Leave Management
3. Clear Leave Balances
4. Practical DSA Learning

### Section 4: Features Section (`#features`)
Six card modules detailing system capabilities:
1. Employee Management (master records, soft deactivation)
2. Department & Position Management (organizational units, foreign key protection)
3. Leave Management (submission, review, Monday–Friday working-day calculation)
4. Leave Balance Tracking (transparent quota breakdown matrix)
5. Reports & Activity Logs (workforce summaries, audit logs)
6. Notifications (in-system status alerts)

### Section 5: How It Works Section (`#how-it-works`)
Three-step operational lifecycle:
- **Step 01:** Organize Employee Information
- **Step 02:** Submit and Review Leave Requests
- **Step 03:** Process and Track Requests (FIFO queue evaluation, transaction-safe deduction)

### Section 6: DSA Laboratory Showcase (`#dsa-lab`)
Academic feature showcase with dark theme and pipeline banner:
- **Pipeline:** `MySQL Database ➔ PHP Object Array ➔ Custom DSA Algorithm ➔ Optimized Result`
- **6 DSA Modules:** Searching (Linear/Binary), Sorting (Bubble/Merge/Quick), LIFO Stack (Activity logs/Undo), FIFO Queue (Leave queue), Linked List & Hash Table (O(1) lookup), BST & Recursion.
- **CTA:** `Explore the DSA Laboratory` (links to `prototype/dsa-laboratory.html`).

### Section 7: Role-Based Access Section (`#roles`)
Side-by-side comparison of user experiences:
- Administrator / HR (directory, FIFO queue, policies, reports, audit logs)
- Authenticated Employee (profile, balance visualizers, leave filing, personal history)
- Note: Access control enforced server-side by PHP session middleware.

### Section 8: Project Overview Section (`#about`)
Clarifies dual project objectives:
- Enterprise HRIS Purpose (workforce management and policy compliance)
- Academic Computer Science Purpose (practical DSA implementation and empirical benchmarking)

### Section 9: Call-to-Action (CTA) Section
High-impact closing banner directing users to `Sign In to HRIS` or `Launch Interactive Prototype Hub`.

### Section 10: Footer
Includes brand area, navigation links, prototype views, technical foundation summary, and dynamic copyright line (`© 2026`).

---

## 4. Frontend Implementation & Interaction Details

- **File Locations:**
  - `public/index.php` (PHP server entry point with dynamic DB connection verification)
  - `public/landing.html` (Static standalone HTML version)
  - `public/assets/css/landing.css` (Dedicated design token stylesheet)
  - `public/assets/js/landing.js` (Navigation and interaction controller)
- **JavaScript Interactions:**
  - Smooth scrolling with an 80px offset for the sticky header.
  - Active navigation link highlighting using `IntersectionObserver`.
  - Accessible mobile off-canvas drawer with ARIA attributes.
  - Browser mockup interactivity with preview tab switching.
  - Reduced-motion support (`prefers-reduced-motion: reduce`).

---

## 5. Verification & Testing Summary

1. **HTTP Status Code Verification:** Both `public/index.php` and `public/landing.html` return clean `200 OK` responses via local Apache/XAMPP.
2. **Asset Loading:** CSS (`landing.css` ~28 KB) and JS (`landing.js` ~4.1 KB) load with zero 404s.
3. **Responsive Breakpoints:** Verified across Desktop (1440px/1200px), Tablet (768px–1024px), and Mobile (375px–480px).
4. **Accessibility (WCAG 2.1 AA):** All text elements meet or exceed 4.5:1 contrast ratios; interactive controls have a minimum touch target of 44px; landmarks and visible focus rings are present.
