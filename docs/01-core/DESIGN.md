---
name: NEXORA HR — UI/UX Design System
tagline: People. Processes. Progress.
version: "3.1"
status: Authoritative Production Design Specification
colors:
  primary: "#1D4ED8"
  primary-hover: "#1E40AF"
  primary-active: "#172554"
  primary-light: "#EFF6FF"
  primary-border: "#BFDBFE"
  accent: "#0D9488"
  accent-hover: "#0F766E"
  accent-light: "#F0FDFA"
  navy-900: "#0F172A"
  navy-800: "#1E293B"
  navy-700: "#334155"
  bg-page: "#F8FAFC"
  bg-surface: "#FFFFFF"
  bg-muted: "#F1F5F9"
  text-main: "#0F172A"
  text-body: "#334155"
  text-muted: "#64748B"
  text-inverse: "#FFFFFF"
  border: "#E2E8F0"
  border-dark: "#CBD5E1"
  success: "#15803D"
  success-bg: "#F0FDF4"
  success-border: "#BBF7D0"
  warning: "#B45309"
  warning-bg: "#FFFBEB"
  warning-border: "#FDE68A"
  error: "#B91C1C"
  error-bg: "#FEF2F2"
  error-border: "#FECACA"
  info: "#0369A1"
  info-bg: "#F0F9FF"
  info-border: "#BAE6FD"
typography:
  display:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.3
    letterSpacing: "-0.015em"
  headline:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: 1.3
  title:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.3
  body:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "0.05em"
rounded:
  sm: "4px"
  md: "6px"
  lg: "8px"
  xl: "12px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.text-inverse}"
    rounded: "{rounded.md}"
    padding: "0.55rem 1.15rem"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    textColor: "{colors.text-inverse}"
  button-secondary:
    backgroundColor: "{colors.bg-surface}"
    textColor: "{colors.text-main}"
    rounded: "{rounded.md}"
    padding: "0.55rem 1.15rem"
  card:
    backgroundColor: "{colors.bg-surface}"
    rounded: "{rounded.lg}"
    padding: "1.5rem"
  input:
    backgroundColor: "{colors.bg-surface}"
    textColor: "{colors.text-main}"
    rounded: "{rounded.md}"
    padding: "0.6rem 0.85rem"
---

# NEXORA HR — UI/UX Design System & Specification

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.1  
**Status:** Authoritative Design Specification  
**Date:** November 2026  
**Audience:** Frontend Engineers, UI/UX Designers, AI Coding Agents  

---

## 1. Brand Identity & Visual Direction

**NEXORA HR** (*People. Processes. Progress.*) embodies an enterprise, reliable, and modern aesthetic designed for clarity, data density, and effortless workflow completion. The interface adopts a restrained corporate palette centered on **Cobalt Blue** (`#1D4ED8`) for primary actions, **Deep Slate Charcoal** (`#0F172A` / `#1E293B`) for structural navigation surfaces, and **Calm Slate-Gray** (`#F8FAFC`) for page backgrounds.

### Core Visual Principles
* **High Information Hierarchy:** Clean typography, consistent spatial rhythms, and clear data cards emphasize high-frequency human resource tasks.
* **Accessible by Default:** All text, status badges, and interactive controls strictly comply with **WCAG 2.1 AA** color contrast standards (4.5:1+ for normal text, 3:1+ for large text).
* **Restrained Semantics:** Color is applied purposefully for state indicators (Pending, Approved, Rejected, Active, Inactive) rather than decorative excess.
* **Responsive App Shell:** Seamlessly transitions between an expansive desktop dashboard and a mobile off-canvas drawer layout without losing data density.

---

## 2. Design Tokens & Variables

All design tokens are maintained in `public/assets/css/style.css` under the `:root` scope.

### 2.1 Color Palette
```css
:root {
    /* Core Brand & Structural Palette */
    --color-primary: #1D4ED8;         /* Reliable Cobalt Blue (4.5:1+ on white) */
    --color-primary-hover: #1E40AF;   /* Darker Cobalt */
    --color-primary-active: #172554;
    --color-primary-light: #EFF6FF;   /* Subtle blue tint */
    --color-primary-border: #BFDBFE;

    /* Accent & Purposeful Highlights */
    --color-accent: #0D9488;          /* Enterprise Teal */
    --color-accent-hover: #0F766E;
    --color-accent-light: #F0FDFA;

    /* Structural Dark Surfaces (Sidebar & Navigation) */
    --color-navy-900: #0F172A;        /* Deep Slate / Charcoal */
    --color-navy-800: #1E293B;        /* Primary Sidebar Surface */
    --color-navy-700: #334155;        /* Sub-surfaces & Dark Borders */
    --color-navy-hover: #293548;

    /* Neutral Surfaces */
    --color-bg-page: #F8FAFC;         /* Calm Slate Canvas */
    --color-bg-surface: #FFFFFF;      /* Content Cards, Tables & Modals */
    --color-bg-muted: #F1F5F9;        /* Input backgrounds & table headers */

    /* Typography Colors */
    --color-text-main: #0F172A;       /* Primary Reading Text (14.2:1 contrast) */
    --color-text-body: #334155;       /* Secondary Body Text (7.6:1 contrast) */
    --color-text-muted: #64748B;      /* Meta labels, timestamps (4.6:1 contrast) */
    --color-text-inverse: #FFFFFF;    /* White text on dark elements */

    /* Borders & Dividers */
    --color-border: #E2E8F0;          /* Subtle Component Dividers */
    --color-border-dark: #CBD5E1;     /* Input & Emphasis Borders */
    --color-border-focus: #1D4ED8;

    /* Semantic Status & Feedback Colors */
    --color-success: #15803D;         /* Accessible Green */
    --color-success-bg: #F0FDF4;
    --color-success-border: #BBF7D0;

    --color-warning: #B45309;         /* Accessible Amber */
    --color-warning-bg: #FFFBEB;
    --color-warning-border: #FDE68A;

    --color-error: #B91C1C;           /* Accessible Crimson Red */
    --color-error-bg: #FEF2F2;
    --color-error-border: #FECACA;

    --color-info: #0369A1;            /* Accessible Cerulean Blue */
    --color-info-bg: #F0F9FF;
    --color-info-border: #BAE6FD;
}
```

### 2.2 Typography Scale
```css
:root {
    --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    --font-mono: ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace;

    --font-size-xs: 0.75rem;    /* 12px - Timestamps, Meta labels */
    --font-size-sm: 0.8125rem;  /* 13px - Secondary body, table cells */
    --font-size-base: 0.875rem; /* 14px - Primary standard reading size */
    --font-size-md: 1rem;       /* 16px - Form controls, subheaders */
    --font-size-lg: 1.125rem;   /* 18px - Card titles, section headers */
    --font-size-xl: 1.25rem;    /* 20px - Modal headers */
    --font-size-2xl: 1.5rem;    /* 24px - Dashboard page titles */
    --font-size-3xl: 1.875rem;  /* 30px - KPI metric displays */
}
```

### 2.3 Spatial Grid, Radii & Shadows
```css
:root {
    --space-1: 0.25rem;  /* 4px */
    --space-2: 0.5rem;   /* 8px */
    --space-3: 0.75rem;  /* 12px */
    --space-4: 1rem;     /* 16px */
    --space-5: 1.25rem;  /* 20px */
    --space-6: 1.5rem;   /* 24px */
    --space-8: 2rem;     /* 32px */
    --space-10: 2.5rem;  /* 40px */

    --radius-sm: 4px;    /* Badges, tags */
    --radius-md: 6px;    /* Buttons, form inputs */
    --radius-lg: 8px;    /* Cards, dropdowns */
    --radius-xl: 12px;   /* Modals, major panels */
    --radius-full: 9999px; /* Pill badges, round avatars */

    --shadow-xs: 0 1px 2px 0 rgba(15, 23, 42, 0.05);
    --shadow-sm: 0 1px 3px 0 rgba(15, 23, 42, 0.08);
    --shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.07);
    --shadow-lg: 0 10px 15px -3px rgba(15, 23, 42, 0.08);
}
```

---

## 3. Layout Principles & App Shell

The application interface follows a resilient, responsive layout structure implemented in `app/views/layouts/hr.php`:

```
┌────────────────────────────────────────────────────────────────────────┐
│                              .app-shell                                │
│                                                                        │
│ ┌──────────────────────┐ ┌───────────────────────────────────────────┐ │
│ │     .app-sidebar     │ │                    .app-header            │ │
│ │   (Width: 260px)     │ │           (Height: 64px, Sticky Top)      │ │
│ │                      │ ├───────────────────────────────────────────┤ │
│ │ • Brand Logo         │ │                   .app-content            │ │
│ │ • Role Identifier    │ │              (Max Width: 1360px)          │ │
│ │ • Navigation Items   │ │                                           │ │
│ │ • Logout Trigger     │ │  [Global Flash Alerts (.alert)]           │ │
│ │                      │ │  [Page Title & Action Group]              │ │
│ │                      │ │  [Dashboard KPI Metric Grid]              │ │
│ │                      │ │  [Data Cards & Tables (.card)]            │ │
│ │                      │ ├───────────────────────────────────────────┤ │
│ │                      │ │                    .app-footer            │ │
│ └──────────────────────┘ └───────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────────┘
```

### Layout Elements
1. **App Sidebar (`.app-sidebar`):**
   * Dark Slate background (`--color-navy-800`), persistent on desktop screens (`>1024px`), width `260px`.
   * Displays application logo, version badge, authenticated user role badge, and role-filtered navigation links.
   * On mobile devices (`<768px`), collapses off-canvas and toggles via `#sidebarToggle` with an accessible scrim backdrop.
2. **Top Application Header (`.app-header` / `.top-bar`):**
   * Sticky top bar (`height: 64px`) with white background, subtle border, and breadcrumb indicator.
   * Right side hosts real-time notification bell dropdown (`#rtNotify`), user identity metadata, role access badge, and sign-out button.
3. **Main Content Container (`.app-content`):**
   * Contained inside `.content-container` with max-width `1360px` and fluid horizontal padding (`--space-6`).
   * Houses global flash messages (`partials/alerts.php`), page title actions, metric summaries, and content cards.
4. **Application Footer (`.app-footer`):**
   * Clean minimal footer providing version info, copyright, and system status indicator.

---

## 4. Reusable UI Components

### 4.1 Buttons
Buttons provide distinct visual hierarchy, clear hover/focus states, and accessibility focus rings:
* `.btn-primary`: High-contrast cobalt blue (`#1D4ED8`) for primary submissions (e.g., "File Leave Request", "Approve", "Save Changes").
* `.btn-secondary`: Crisp neutral border (`#CBD5E1`) on white surface for non-destructive secondary tasks (e.g., "Cancel", "Back to Dashboard", "View Profile").
* `.btn-danger`: Accessible crimson red (`#B91C1C`) for destructive actions (e.g., "Reject Request", "Deactivate Employee").
* `.btn-sm`: Compact padding (`0.4rem 0.85rem`) for table action rows and header controls.

### 4.2 Status & Role Badges
Badges communicate immediate status using semantic tokens:
* `.role-badge.hr`: Slate/Navy container with enterprise blue label (`HR ACCESS`).
* `.role-badge.emp`: Light blue container (`EMPLOYEE SELF-SERVICE`).
* `.badge-active`: Light green container (`#F0FDF4`), border (`#BBF7D0`), green text (`#15803D`).
* `.badge-pending`: Light amber container (`#FFFBEB`), border (`#FDE68A`), amber text (`#B45309`).
* `.badge-approved`: Green indicator for confirmed approvals.
* `.badge-rejected`: Light red container (`#FEF2F2`), border (`#FECACA`), crimson text (`#B91C1C`).

### 4.3 Data Cards & Tables
* **Cards (`.card`):** White surface (`--color-bg-surface`), rounded borders (`--radius-lg`), subtle divider between `.card-header` and `.card-body`.
* **Tables (`.data-table`):** Wrapped inside `.table-responsive` to prevent layout overflow. Feature muted slate headers (`--color-bg-muted`), subtle row dividers, hover highlighting (`#F8FAFC`), and aligned numeric columns.

### 4.4 Real-time Notification Dropdown (`#rtNotify`)
* Bell icon with dynamic numeric unread badge (`.rt-count`).
* Real-time status dot (`.rt-dot`) reflecting live WebSocket states:
  * Green pulse: `connected`
  * Amber pulse: `connecting`
  * Gray: `disabled`
* Dropdown panel (`.rt-panel`) displaying up to 8 recent notifications with quick "Mark All as Read" and direct link to `/notifications`.

---

## 5. Screen-by-Screen Specifications

### 5.1 Landing & Public Pages (`/`)
* **Hero Banner:** Minimalist enterprise introduction featuring NEXORA HR branding and core tagline *People. Processes. Progress.*
* **Authentication CTA:** Direct primary button linking to `/login`.

### 5.2 Sign-In & Authentication Flow (`/login`, `/verify-otp`)
* **Login Form:** Clean centered authentication card (`max-width: 440px`), with clear labels, password visibility toggle, CSRF protection, and error alerts.
* **OTP Verification:** Dedicated two-factor screen displaying recipient email address (masked), 6-digit numeric input with auto-focus, 10-minute countdown indicator, and resend trigger.

### 5.3 HR Dashboard (`/hr/dashboard`)
* **Metric Overview:** Four top cards displaying Total Workforce, Active Employees, Pending Leave Requests, and Employees on Leave Today.
* **Pending Approval Queue:** FIFO-sorted table displaying applicant name, leave type, date range, requested business days, and inline "Approve" / "Reject" modal triggers.
* **Workforce Activity Stream:** Recent audit events and leave submissions.

### 5.4 Employee Self-Service Dashboard (`/employee/dashboard`)
* **Personal Welcome Header:** Displays dynamic employee name, Employee ID badge (`EMP012`), and quick action buttons ("View My Profile", "File Leave Request").
* **Leave Balances Grid:** Visual progress bars for each leave entitlement (Vacation, Sick, Emergency, Maternity/Paternity, Bereavement) showing Allocated, Used, and Remaining days.
* **Recent Leave History:** Table displaying the employee's 5 most recent submissions with real-time status badges.

### 5.5 Employee Profile View (`/employee/profile`)
* **Header Card:** Round avatar with initials, full employee name, job title, organizational department, and hire date.
* **Details Grid:** Two-column cards detailing personal information (Email, Phone, Birth Date, Address) and organizational information (Employee Number, Department, Position, Status).

---

## 6. Accessibility & Motion Guidelines

1. **Focus States:** All interactive elements must maintain a visible focus ring (`outline: 2px solid var(--color-primary); outline-offset: 2px;`) when navigated via keyboard.
2. **Keyboard Navigation:** Modals must trap focus, support `Tab` / `Shift+Tab` cycling, and dismiss on `Escape`.
3. **Screen Readers:** All non-text icons (SVG icons, notification bell, system indicator) must declare explicit `aria-hidden="true"` or descriptive `aria-label` tags.
4. **Reduced Motion:** All transitions and animations must observe the user's operating system preferences:
   ```css
   @media (prefers-reduced-motion: reduce) {
       *, *::before, *::after {
           animation-duration: 0.01ms !important;
           transition-duration: 0.01ms !important;
           scroll-behavior: auto !important;
       }
   }
   ```
