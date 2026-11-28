---
name: "NEXORA HR"
description: "People. Processes. Progress. — Enterprise HRIS & Leave Management Design System"
colors:
  cobalt-authority: "#1D4ED8"
  cobalt-bright: "#2563EB"
  cobalt-deep: "#1E40AF"
  cobalt-midnight: "#172554"
  cobalt-ice: "#EFF6FF"
  cobalt-border: "#BFDBFE"
  nexora-emblem-azure: "#0066EB"
  nexora-wordmark-navy: "#04132A"
  nexora-tagline-slate: "#1E3A5F"
  operational-teal: "#0D9488"
  operational-teal-deep: "#0F766E"
  operational-teal-tint: "#F0FDFA"
  operational-teal-border: "#99F6E4"
  approval-emerald: "#15803D"
  approval-emerald-deep: "#166534"
  approval-emerald-tint: "#F0FDF4"
  approval-emerald-border: "#BBF7D0"
  review-amber: "#B45309"
  review-amber-tint: "#FFFBEB"
  review-amber-border: "#FDE68A"
  alert-crimson: "#B91C1C"
  alert-crimson-deep: "#991B1B"
  alert-crimson-tint: "#FEF2F2"
  alert-crimson-border: "#FECACA"
  notice-cerulean: "#0369A1"
  notice-cerulean-tint: "#F0F9FF"
  notice-cerulean-border: "#BAE6FD"
  cool-alabaster-canvas: "#F8FAFC"
  pure-ledger-white: "#FFFFFF"
  soft-slate-tint: "#F1F5F9"
  row-stripe-white: "#FBFDFE"
  hairline-slate-border: "#E2E8F0"
  input-slate-border: "#CBD5E1"
  slate-border-hover: "#94A3B8"
  slate-neutral-text: "#475569"
  deep-slate-ink: "#0F172A"
  charcoal-reading-slate: "#334155"
  muted-graphite-slate: "#64748B"
  nocturnal-navy-canvas: "#0B132B"
  dark-page-canvas: "#0B1120"
  dark-header-navy: "#080E20"
  dark-deep-navy: "#050B14"
  dark-navy-surface: "#1C2541"
  dark-navy-hover: "#152238"
  dark-slate-hover: "#293548"
  dark-muted-surface: "#172033"
  dark-row-stripe: "#1B2538"
  elevated-midnight-slate: "#1E293B"
  dark-slate-border: "#334155"
  dark-cobalt-focus: "#3B82F6"
  dark-cobalt-hover: "#60A5FA"
  dark-azure-accent: "#38BDF8"
  dark-emerald-text: "#4ADE80"
  dark-emerald-border: "#047857"
  dark-amber-text: "#FBBF24"
  dark-crimson-text: "#F87171"
typography:
  display:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "-0.02em"
    fontFeature: "tnum"
  h1:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.3
    letterSpacing: "-0.02em"
  headline:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.375rem"
    fontWeight: 700
    lineHeight: 1.3
    letterSpacing: "-0.015em"
  h2:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: 1.3
  h3:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.35
  title:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: 1.35
    letterSpacing: "-0.01em"
  body:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
  body-sm:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 400
    lineHeight: 1.45
  label:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    lineHeight: 1.35
    letterSpacing: "0.04em"
  micro:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "0.6875rem"
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: "0.02em"
  mono:
    fontFamily: "ui-monospace, SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace"
    fontSize: "0.75rem"
    fontWeight: 600
    lineHeight: 1.4
    fontFeature: "tnum"
rounded:
  sm: "4px"
  md: "6px"
  lg: "8px"
  xl: "12px"
  full: "9999px"
spacing:
  space-1: "4px"
  space-2: "8px"
  space-3: "12px"
  space-4: "16px"
  space-5: "20px"
  space-6: "24px"
  space-8: "32px"
  space-10: "40px"
components:
  button-primary:
    backgroundColor: "{colors.cobalt-authority}"
    textColor: "{colors.pure-ledger-white}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "9px 16px"
  button-primary-hover:
    backgroundColor: "{colors.cobalt-deep}"
    textColor: "{colors.pure-ledger-white}"
  button-secondary:
    backgroundColor: "{colors.pure-ledger-white}"
    textColor: "{colors.deep-slate-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "9px 16px"
  button-secondary-hover:
    backgroundColor: "{colors.soft-slate-tint}"
    textColor: "{colors.deep-slate-ink}"
  button-danger:
    backgroundColor: "{colors.alert-crimson}"
    textColor: "{colors.pure-ledger-white}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "9px 16px"
  card-surface:
    backgroundColor: "{colors.pure-ledger-white}"
    textColor: "{colors.charcoal-reading-slate}"
    rounded: "{rounded.xl}"
    padding: "20px 24px"
  summary-strip-cell:
    backgroundColor: "{colors.pure-ledger-white}"
    textColor: "{colors.deep-slate-ink}"
    padding: "20px 24px"
  filter-pill-active:
    backgroundColor: "{colors.cobalt-authority}"
    textColor: "{colors.pure-ledger-white}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "6px 14px"
  filter-pill-inactive:
    backgroundColor: "{colors.soft-slate-tint}"
    textColor: "{colors.charcoal-reading-slate}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "6px 14px"
  notification-row-unread:
    backgroundColor: "{colors.cobalt-ice}"
    textColor: "{colors.deep-slate-ink}"
    padding: "16px 24px"
  modal-dialog:
    backgroundColor: "{colors.pure-ledger-white}"
    textColor: "{colors.charcoal-reading-slate}"
    rounded: "{rounded.xl}"
    padding: "24px"
  input-control:
    backgroundColor: "{colors.pure-ledger-white}"
    textColor: "{colors.deep-slate-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "9px 12px"
  nav-item-active:
    backgroundColor: "{colors.cobalt-ice}"
    textColor: "{colors.cobalt-authority}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "9px 12px"
  status-badge-success:
    backgroundColor: "{colors.approval-emerald-tint}"
    textColor: "{colors.approval-emerald}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "3px 10px"
  status-badge-warning:
    backgroundColor: "{colors.review-amber-tint}"
    textColor: "{colors.review-amber}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "3px 10px"
  status-badge-danger:
    backgroundColor: "{colors.alert-crimson-tint}"
    textColor: "{colors.alert-crimson}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "3px 10px"
  kpi-overview-card:
    backgroundColor: "{colors.pure-ledger-white}"
    textColor: "{colors.deep-slate-ink}"
    rounded: "{rounded.lg}"
    padding: "20px"
  leave-category-card:
    backgroundColor: "{colors.cool-alabaster-canvas}"
    textColor: "{colors.deep-slate-ink}"
    rounded: "{rounded.lg}"
    padding: "20px"
---

# Design System: NEXORA HR

## Overview

**Creative North Star: "The Executive Ledger"**

NEXORA HR is designed around authoritative, calm, high-trust operational clarity where every employee record, leave entitlement, and approval state reads with financial-grade precision. Rather than treating human resource workflows as decorative marketing widgets, the interface behaves like a well-architected executive ledger: calm neutral surfaces allow critical workforce numbers, working-day calculations, and approval statuses to command immediate attention without visual noise.

Density is purposefully calibrated for daily operational velocity across all three platform roles—starting with a modernized, self-service **Employee Workspace** and extending seamlessly to HR Officers and System Administrators. Summary metrics are consolidated into unified horizontal ledger strips divided by hairline borders; entitlement cards present remaining balances, quota utilization, and contextual actions without repeating numbers; profile records separate identity summary from structured definition lists with zero field duplication; and workspace tables pair scannable status pills with instant client-side filtering. Both default Light Mode and optional Dark Mode (`[data-theme="dark"]`) preserve identical structural proportions while adapting surface contrast and brand lockup legibility.

The visual system strictly rejects generic AI-generated dashboard patterns: glowing neon gradients, glassmorphism, `4px` colored side-tab borders (`border-left` stripes on cards or toasts), decorative emoji icons, uppercase eyebrow kickers above headings, and nested 3-stat sub-boxes inside cards are prohibited across the platform.

**Key Characteristics:**
- **Ledger-Grade Numerical Clarity:** Tabular numerals (`font-variant-numeric: tabular-nums`) govern every leave balance, duration count, date range, and reference ID.
- **Single-Signal Information Hierarchy:** Every metric or profile attribute appears once per view; every screen exposes one unmistakable primary action.
- **Theme-Adaptive Brand & Shell:** White/light mode is the default operational environment, paired with a first-class dark mode that adapts both workspace surfaces and the NEXORA HR brand lockup.
- **Crisp Stroke Iconography:** All navigation, status, and action cues use uniform `24x24` viewBox (`2px` stroke) inline SVGs (`icon()`), never platform emojis.
- **WCAG 2.2 AA Accessibility by Default:** High-contrast slate typography, dual-channel status badges (color + dot/icon + label), `:user-invalid` validation timing, and `2px solid` keyboard focus outlines.

## Colors

The palette pairs restrained slate neutrals with authoritative Cobalt and Azure brand accents, reserving saturated hues strictly for primary actions and semantic workflow states across both Light Mode (`:root`) and Dark Mode (`[data-theme="dark"]`).

### Primary
- **Cobalt Authority** (`#1D4ED8` in Light Mode / `#3B82F6` in Dark Mode): Primary interactive buttons, active navigation indicators, selected filter pills, progress bars, and keyboard `:focus-visible` outlines (`4.5:1+` contrast).
- **Cobalt Deep** (`#1E40AF` in Light Mode / `#60A5FA` in Dark Mode): Hover state for primary buttons and interactive links.
- **Cobalt Midnight** (`#172554`): Active press state for primary controls.
- **Cobalt Ice** (`#EFF6FF` in Light Mode / `rgba(59, 130, 246, 0.14)` in Dark Mode): Subtle fill for active sidebar navigation items, unread notification rows, hover states on the sidebar minimize toggle, and highlighted leave-balance cards.
- **Nexora Emblem Azure** (`#0066EB` in Light Mode / `#38BDF8` in Dark Mode): Signature brand accent used for the `HR` wordmark and the delta triangle inside the `A` of the `NEXORA HR` sidebar lockup.
- **Nexora Wordmark Navy** (`#04132A` in Light Mode / `#F8FAFC` in Dark Mode): Primary wordmark ink for `NEXORA` in the brand header, automatically inverting to crisp white in dark mode.

### Secondary
- **Operational Teal** (`#0D9488`): Secondary operational accent used to distinguish HR portal administration contexts and secondary analytical series.

### Tertiary
- **Approval Emerald** (`#15803D` on `#F0FDF4` in Light Mode / `#4ADE80` on `rgba(6, 78, 59, 0.55)` in Dark Mode): Approved leave requests, active employment status badges, and healthy leave balance indicators.
- **Review Amber** (`#B45309` on `#FFFBEB` in Light Mode / `#FBBF24` on `rgba(69, 26, 3, 0.55)` in Dark Mode): Pending leave requests awaiting HR adjudication and low-balance utilization warnings (`>= 75%` used).
- **Alert Crimson** (`#B91C1C` on `#FEF2F2` in Light Mode / `#F87171` on `rgba(69, 10, 10, 0.55)` in Dark Mode): Rejected leave requests, exhausted leave entitlements (`0` days remaining), destructive confirmation actions, and form validation errors.
- **Notice Cerulean** (`#0369A1` on `#F0F9FF` in Light Mode / `#38BDF8` on `rgba(8, 47, 73, 0.55)` in Dark Mode): Informational flash banners, policy guidance callouts, and system notifications.

### Neutral
- **Cool Alabaster Canvas** (`#F8FAFC` in Light Mode / **Nocturnal Navy Canvas** `#0B132B` in Dark Mode): Default page background canvas, table headers, and subtle card footers.
- **Pure Ledger White** (`#FFFFFF` in Light Mode / **Elevated Midnight Slate** `#1E293B` in Dark Mode): Primary surface for cards, summary strips, data tables, modals, top bar, and sidebar.
- **Soft Slate Tint** (`#F1F5F9` in Light Mode / `#0F172A` in Dark Mode): Hover surface for secondary buttons, table rows, filter pill tracks, and monospace employee ID chips.
- **Hairline Slate Border** (`#E2E8F0` in Light Mode / `#334155` in Dark Mode): `1px` structural dividers across cards, summary strips, definition lists, table rows, and shell boundaries.
- **Input Slate Border** (`#CBD5E1` in Light Mode / `#475569` in Dark Mode): Default resting border for form inputs, selects, date pickers, and secondary buttons.
- **Deep Slate Ink** (`#0F172A` in Light Mode / `#F8FAFC` in Dark Mode): Primary heading and metric numeral color (`14.2:1` contrast on white).
- **Charcoal Reading Slate** (`#334155` in Light Mode / `#CBD5E1` in Dark Mode): Default body copy, table cell text, and navigation item labels (`7.6:1` contrast on white).
- **Muted Graphite Slate** (`#64748B` in Light Mode / `#94A3B8` in Dark Mode): Secondary metadata, timestamps, field hints, and table column headers (`4.6:1` contrast on white).

### Named Rules
**The One Primary Action Rule.** Cobalt Authority (`#1D4ED8`) as a solid fill is reserved for at most one primary call-to-action button per screen (such as "File Leave Request" or "Submit Application") and active filter/navigation state. All supporting actions use neutral secondary surfaces.

**The Semantic Restraint Rule.** Emerald, Amber, and Crimson tokens communicate workflow state (Approved, Pending, Rejected, Exhausted) and validation feedback only; they are never used as decorative card themes or random icon backgrounds.

**The Dual-Theme Legibility Rule.** Every brand and UI element must maintain WCAG 2.2 AA contrast in both `[data-theme="light"]` and `[data-theme="dark"]` without relying on hardcoded dark-only or light-only text colors.

## Typography

**Display & UI Font:** System UI Sans Stack (`-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`)
**Monospace / Reference Font:** System UI Mono Stack (`ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace`)

**Character:** Zero-latency native enterprise typography that renders with crisp platform hinting, pairing authoritative geometric headings (`700`–`800` weight, tight `-0.015em` to `-0.02em` tracking) with calm, highly legible body copy and strict tabular numeral alignment.

### Hierarchy
- **Display / Metric Numeral** (`700` weight, `1.75rem` / `28px`, `1.2` line-height, `tabular-nums`): Primary KPI numerals in `.emp-summary-strip` and available day counts in `.emp-entitlement-avail`.
- **Headline / Page Title** (`700` weight, `1.375rem`–`1.5rem` / `22px`–`24px`, `1.3` line-height, `-0.015em` tracking, `text-wrap: balance`): Primary view titles (`.page-title`, `.emp-welcome-meta h1`).
- **Title / Card Title** (`600` weight, `1rem` / `16px`, `1.35` line-height): Section and card headers (`.card-title`, `.modal-title`).
- **Body** (`400`–`500` weight, `0.875rem` / `14px`, `1.5` line-height, `text-wrap: pretty`): Default operational reading size for table cells, form controls, notification messages, and policy descriptions (constrained to `60ch–70ch` for prose).
- **Label / Column Header** (`600` weight, `0.75rem` / `12px`, `1.35` line-height, `0.04em` letter-spacing, uppercase only on table `<th>` and summary strip labels): Table column headers, status badges, definition list labels, and summary metric labels.
- **Monospace Chip** (`600` weight, `0.75rem` / `12px`, `tabular-nums`): Employee IDs (`EMP001`) and request reference numbers (`#10`).

### Named Rules
**The Tabular Ledger Rule.** Every element displaying a leave balance, working-day count, utilization percentage, date range, phone number, or reference ID must apply `font-variant-numeric: tabular-nums` so figures align vertically down columns and never jitter during live calculation.

**The No-Kicker Rule.** Never stack an uppercase eyebrow label (`EMPLOYEE SELF-SERVICE`, `OVERVIEW`, `HUMAN RESOURCE INFORMATION SYSTEM`) immediately above a page heading, hero headline, or card title; let the heading and its concise subtitle speak for themselves.

**The Card Prose Alignment Rule.** On authenticated operational screens, keep labels, checklists, and table cells left-aligned with `text-wrap: balance` on headings; on the public landing page, multi-line card summaries (`.role-summary`, `.security-card-desc`, `.workflow-step-desc`) use justified inter-word alignment (`text-align: justify; text-justify: inter-word`).

## Layout

The authenticated application uses a persistent left sidebar shell (`.app-shell`) paired with a sticky top utility header (`.app-header`, `64px` height) and a fluid main workspace container (`.page-content`, `max-width: 1360px`, `24px`–`32px` padding).

- **Sidebar Dimensions & Collapse:** Expanded desktop width is `260px` (`--sidebar-width`). Activating `#sidebarToggle` smoothly transitions (`0.22s cubic-bezier(0.22, 1, 0.36, 1)`) the sidebar to a `72px` icon-only rail (`.app-shell.sidebar-collapsed`), centering the `38x38px` NEXORA emblem and surfacing native tooltips on navigation icons.
- **Summary Strip & KPI Overview Architecture:** Employee Dashboard top metrics use a 4-column KPI overview grid (`.emp-kpi-grid`, `grid-template-columns: repeat(4, minmax(0, 1fr))`, `16px` gap) pairing informational KPI cards (`.emp-kpi-card`) with interactive link cards (`.emp-kpi-card.emp-kpi-interactive`) for Pending Requests and Unread Alerts. The dedicated Leave Balances page uses a 4-column unified horizontal ledger strip (`.emp-summary-strip`, `grid-template-columns: repeat(4, minmax(0, 1fr))`) inside a single `12px`-radius container divided by `1px solid` vertical hairlines.
- **Workspace Grids:**
  - **Leave Category Grid (`.emp-category-grid`):** Balanced 3-column desktop grid (`repeat(3, minmax(0, 1fr))`, `16px` gap) for Employee Dashboard leave category cards (`.emp-category-card`).
  - **Entitlement Grid (`.emp-entitlement-grid`):** Responsive auto-fill grid (`repeat(auto-fill, minmax(260px, 1fr))`, `16px` gap) for leave category cards on the dedicated Leave Balances page.
  - **Dashboard Split Grid (`.emp-workspace-grid`):** Asymmetric 2-column operational split (`minmax(0, 1.65fr) minmax(300px, 1fr)`, `24px` gap) pairing Recent Leave Applications on the left with Recent Updates on the right.
  - **Profile & Leave Filing Grids (`.emp-profile-grid`, `.emp-apply-grid`):** Focused 2-column layouts (`300px` identity card + `1fr` detail stack on Profile; `1.4fr` form + `1fr` balance/guideline rail on File Leave Request).
- **Responsive Breakpoints:**
  - **Desktop (`>= 1024px`):** Full 4-column summary strip, 2-column asymmetric workspace grids, collapsible `260px` / `72px` sidebar.
  - **Tablet (`< 1024px`):** Summary strip wraps to `2x2` grid with horizontal hairline divider; 2-column workspace, profile, and leave-filing grids stack cleanly into a single column.
  - **Mobile (`< 768px`):** Sidebar converts to an off-canvas slide-over drawer (`transform: translateX(-100%)`); summary strip stacks to 1 column; welcome bar and filter toolbars stack vertically with full-width search inputs; touch targets enforce a `44px` minimum height on `@media (pointer: coarse)`.

## Elevation & Depth

NEXORA HR follows a **Structural Hairline & Whisper Elevation** model. Visual hierarchy comes primarily from crisp `1px solid` slate borders (`#E2E8F0` in light mode, `#334155` in dark mode) and subtle tonal contrast between the Cool Alabaster canvas (`#F8FAFC`) and Pure Ledger White containers (`#FFFFFF`), reinforced by whisper-quiet ambient shadows at rest and purposeful lift on interactive controls.

### Shadow Vocabulary
- **Whisper Rest (`--shadow-xs`)** (`box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.05)`): Applied to resting cards, `.emp-summary-strip`, `.sidebar-toggle`, and `.app-header` to give subtle crispness to `1px` borders.
- **Interactive Lift (`--shadow-sm`)** (`box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.08), 0 1px 2px -1px rgba(15, 23, 42, 0.08)`): Applied on hover to `.emp-entitlement-card` and `.sidebar-toggle`.
- **Dropdown & Popover (`--shadow-lg`)** (`box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -4px rgba(15, 23, 42, 0.03)`): Applied to the real-time notification panel (`.rt-panel`) and floating menus.
- **Modal Dialog (`--shadow-xl`)** (`box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.04)`): Reserved exclusively for modal dialogs (`.modal-content`) over a dimmed backdrop.

### Named Rules
**The Hairline-First Rule.** Never rely on heavy drop shadows to separate sibling regions; use a `1px solid` border (`--color-border`) first and keep resting shadows at `--shadow-xs`.

**The No Side-Tab Stripe Rule.** Never apply thick colored left borders (`border-left: 3px` or `4px solid`) to cards, alerts, notification rows, or toasts. Communicate status through semantic icon badges or status pills instead.

## Shapes

The form language uses disciplined, architectural geometry that balances approachability with enterprise precision:
- **Micro Badges & Progress Tracks (`4px` / `--radius-sm`):** Employee ID chips (`.emp-id-chip`) and `8px`-tall linear progress bars (`.progress-bar`).
- **Interactive Controls (`6px` / `--radius-md`):** Buttons (`.btn`), form inputs (`.form-control`), selects, and sidebar navigation items (`.nav-item`).
- **Tactile Utility & Inner Panels (`8px` / `--radius-lg`):** Sidebar minimize toggle (`.sidebar-toggle`), entitlement cards (`.emp-entitlement-card`), notification icon badges (`.emp-notif-icon`), and live working-day calculator box (`.emp-calc-box`).
- **Primary Containers (`12px` / `--radius-xl`):** Primary section cards (`.card`), unified summary strips (`.emp-summary-strip`), and modal dialogs (`.modal-content`).
- **Status Pills & Avatars (`9999px` / `--radius-full`):** Semantic status badges (`.badge`), filter pills (`.emp-filter-pill`), notification count badges (`.nav-badge`), and user avatars.

## Components

Every component is built to feel **refined, tactile, and unambiguous**, with explicit hover, focus-visible, and validation states.

### Buttons
- **Shape:** Gently rounded corners (`6px` radius / `--radius-md`), inline-flex alignment with `8px` icon-to-label gap and `600` font weight.
- **Primary (`.btn-primary`):** Solid Cobalt Authority (`#1D4ED8`) background with Pure Ledger White (`#FFFFFF`) text and `9px 16px` padding (`6px 12px` for `.btn-sm`).
- **Hover / Focus:** Primary buttons deepen to Cobalt Deep (`#1E40AF`); keyboard focus renders a `2px solid #1D4ED8` outline with `2px` offset.
- **Secondary (`.btn-secondary`) & Destructive Outline (`.btn-outline-danger`):** Secondary buttons use a `#FFFFFF` surface with `1px solid #CBD5E1` border, shifting to `#F1F5F9` on hover; destructive outline buttons use Alert Crimson (`#B91C1C`) text and border for safe secondary actions like cancelling a pending leave request.

### Chips & Status Badges
- **Style:** Full-pill radius (`9999px`), `12px` font size (`600` weight), `1px solid` semantic border, and a `6px` status dot (`.badge-dot`) preceding the label.
- **Filter Pills (`.emp-filter-pill`):** Segmented tab controls inside `.emp-filter-toolbar` with inline count badges (`.emp-filter-count`); active state fills with Cobalt Authority (`#1D4ED8`) and white text.

### Cards / Containers
- **Corner Style:** `12px` outer radius (`--radius-xl`) for `.card` and `.emp-summary-strip`; `8px` radius (`--radius-lg`) for `.emp-entitlement-card`.
- **Background & Border:** Pure Ledger White (`#FFFFFF`) in Light Mode (`#1E293B` in Dark Mode) with a `1px solid #E2E8F0` (`#334155` dark) border.
- **Shadow Strategy:** Resting `--shadow-xs` (`0 1px 2px rgba(15, 23, 42, 0.05)`); `.emp-entitlement-card` elevates to `--shadow-sm` with `#CBD5E1` border on hover.
- **Internal Padding:** Card headers use `16px 24px` with a `1px solid` bottom border; card bodies use `24px` (`0` when housing a full-bleed `.data-table` or `.emp-notif-list`).

### Inputs / Fields
- **Style:** `6px` radius (`--radius-md`), `9px 12px` padding, `1px solid #CBD5E1` border on `#FFFFFF` (`#0F172A` in Dark Mode).
- **Focus:** Border shifts to Cobalt Authority (`#1D4ED8`) with a `3px` soft focus ring (`rgba(29, 78, 216, 0.15)`).
- **Error / Validation:** Uses `:user-invalid` and `[aria-invalid="true"]` (never bare `:invalid` on untouched fields) to apply Alert Crimson (`#B91C1C`) borders and announce errors via `role="alert"` in `#dateValidationError`.

### Navigation & Brand Shell
- **Brand Header (`.sidebar-header`):** Displays the `38x38px` NEXORA emblem (`nexora-logo-icon.png`) paired with the theme-adaptive `NEXORA HR` wordmark (including the azure delta mark inside the `A`) and `— People. Processes. Progress. —` tagline.
- **Sidebar Minimize Button (`#sidebarToggle`):** `38x38px` tactile button (`8px` radius) that switches dynamically between `panel-left-close` ("Minimize sidebar") and `panel-left-open` ("Expand sidebar") and highlights with `.is-collapsed` when pinned to the `72px` rail.
- **Sidebar Items (`.nav-item`):** `6px` radius, `18px` stroke SVG icon + single-line label + optional `.nav-badge` counter. Active state uses Cobalt Ice (`#EFF6FF`) fill with `#1D4ED8` text in Light Mode and `#2563EB` fill with `#FFFFFF` text in Dark Mode.

### Data Tables & Empty States
- **Data Tables (`.data-table`):** Full-width ledger tables with `12px` uppercase column headers (`#F8FAFC` surface in Light Mode, `#0F172A` in Dark Mode), `14px 20px` cell padding, `1px solid` row dividers, subtle row hover (`--color-row-hover`), right-aligned action columns (`.text-right`), and `.tabular-nums` on all date, duration, and ID cells.
- **Empty States (`.empty-state`):** Centered `48px 24px` container featuring a `36px`–`40px` muted stroke SVG icon (`.empty-state-icon`), a clear `16px` title (`.empty-state-title`), a concise `14px` explanation constrained to `45ch`, and an optional primary action button when the user can resolve the empty state directly.

### Signature Employee Workspace Components
- **Refined KPI Overview Cards (`.emp-kpi-grid` & `.emp-kpi-card`):** Four balanced cards displaying Available Leave, Days Used (with proportional `.emp-kpi-meter` and `.emp-kpi-meter-pct`), Pending Requests (`.emp-kpi-card.emp-kpi-interactive`), and Unread Alerts (`.emp-kpi-card.emp-kpi-interactive`).
- **Balanced Leave Category Cards (`.emp-category-grid` & `.emp-category-card`):** Three category cards displaying the leave type header and compact `Apply ->` action (`.emp-category-apply`), primary remaining days (`.emp-category-balance-val`), proportional `.emp-category-meter` progress bar, and centered `.emp-category-ledger` breakdown (`Allocated` &middot; `Used (%)`).
- **Unified Summary Strip (`.emp-summary-strip`):** A single 4-cell horizontal ledger bar at the top of the Leave Balances page (`employee_balances.php`), pairing an uppercase muted label (`.emp-summary-label`), a `28px` tabular value (`.emp-summary-value`) with inline unit (`.emp-summary-unit`), and a concise secondary caption (`.emp-summary-meta`) per cell.
- **Distilled Entitlement Card (`.emp-entitlement-card`):** Displays each leave category's name, a contextual `Apply ->` link, a prominent tabular `days available` figure on the left, `X of Y used` on the right, and an accessible `8px` progress bar underneath—showing each number exactly once.
- **Filter & Search Toolbar (`.emp-filter-toolbar`):** Combines a `role="tablist"` group of status filter pills (`.emp-filter-pill` with `.emp-filter-count` badges) on the left and an icon-prefixed search input (`.emp-search-box`, `260px` desktop width) on the right for instant client-side table/inbox filtering.
- **Notification Inbox Row (`.emp-notif-item`):** Full-bleed list row with a `36x36px` (`8px` radius) semantic SVG icon badge (`.emp-notif-icon-success`, `-warning`, `-danger`, `-info`), an `8px` Cobalt unread dot (`.emp-unread-dot`), bold title with right-aligned tabular timestamp, and an inline `Mark Read` button (`POST /notifications/{id}/read`). Unread rows receive a subtle Cobalt Ice tint (`.is-unread`).
- **Profile Identity & Definition Grid (`.emp-identity-card` & `.emp-dl-grid`):** Pairs a left identity card (`72x72px` avatar initials, name, position, `Active` badge, `EMP` chip, and workspace quicklinks) with a 2-column definition grid (`.emp-dl-grid` / `.emp-dl-row`) for Employment & Organization and Contact Information—ensuring zero field duplication between columns.
- **Live Working-Day Calculator Box (`.emp-calc-box`):** Real-time `aria-live="polite"` feedback panel inside the File Leave Request form that computes business days (excluding Saturdays and Sundays), checks remaining quota, and warns immediately on invalid or weekend-only ranges.
- **Accessible Confirmation Modal (`.modal-backdrop` & `.modal-content`):** Dialog (`role="dialog"`, `aria-modal="true"`, `aria-labelledby`) used for confirming pending leave cancellation (`#cancelLeaveModal`), featuring `12px` radius, `--shadow-xl` elevation, clear consequence copy, `Keep Request` secondary dismissal, and a CSRF-protected `POST` confirmation button.

## Public Landing & Entry Surface (Persuade Mode)

While authenticated dashboards run in **Operate Mode**, the public landing page (`app/views/home/index.php` + `public/assets/css/landing.css` + `public/assets/js/landing.js`) runs in **Persuade Mode**—welcoming prospective HR evaluators and returning staff with the exact same **Executive Ledger** visual authority so signing in feels seamless with zero visual whiplash.

### Token & Brand Parity
- **Unified Palette:** Landing surfaces and controls must use the exact `DESIGN.md` tokens (`#1D4ED8` Cobalt Authority primary, `#1E40AF` Cobalt Deep hover, `#0D9488` Operational Teal accent, `#F8FAFC` Cool Alabaster canvas, `#FFFFFF` Pure Ledger White cards, `#0F172A` Deep Slate Ink headings, `#334155` Charcoal Reading Slate body, `#E2E8F0` / `#CBD5E1` slate hairlines; and `#0B1120` / `#1E293B` / `#334155` / `#3B82F6` in Dark Mode). Rogue off-palette hexes (such as `#153D70`, `#F4F7FB`, or `#1F2937`) are prohibited.
- **Native System Typography:** Use the zero-latency native system sans stack (`-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`) without external font CDN imports or unhosted web-font names.
- **Wordmark Consistency:** The header and footer lockup uses `--logo-word-nexora` (`#04132A` light / `#F8FAFC` dark) for `NEXORA` and `--logo-word-hr` (`#0066EB` light / `#38BDF8` dark) for `HR`.

### Section Architecture & Content Discipline
- **Preserved Vertical Cadence:** Fixed header → Centered Hero (headline, lead copy, primary/secondary CTA pair, brand pillars) → Core Features (4×2 grid covering real shipped modules: Employees, Leave Management, Leave Balances, Departments & Positions, Announcements, Help & Support, Real-Time Notifications, Reporting & Audit Trail) → Product Introduction (3 pillars) → Role Workspaces (2 cards covering HR/Admin Governance and Employee Self-Service, each with a collapsed-by-default capability dropdown) → Security & Reliability (2×2 grid) → How It Works (3 sequential steps without duplicate numbering) → Final CTA Banner → 4-Column Footer.
- **Collapsible Capability Dropdowns (Default Pattern):** Multi-item role or feature checklists inside summary cards use native `<details>`/`<summary>` disclosures **collapsed by default** (non-exclusive, no `name` attribute so users can compare cards side by side; no headings inside `<summary>`). Style the disclosure marker adjacent to the label via a rotating `::before` chevron (`-90deg` closed → `0deg` open) and animate expansion progressively via `::details-content` (`interpolate-size: allow-keywords`, `transition-behavior: allow-discrete`) gated under `@media (prefers-reduced-motion: no-preference)`.
- **Truthful Product Copy:** Every feature and role claim must correspond to live shipped capabilities (Smart OTP & Trusted Devices, Dynamic RBAC, Announcements, Confidential Support Desk, Ratchet WebSocket Notifications, Immutable Audit Trail). Avoid vague placeholders or unauthenticated DB count queries on `/`.

### Performance, Motion & Accessibility Standards
- **LCP & Resource Prioritization:** Keep the hero canvas CSS-native (no heavy hero background raster image), defer non-critical scripts (`defer`), set explicit `width`/`height` + `decoding="async"` on images, and apply `loading="lazy"` on below-the-fold images (such as footer emblems). Never lazy-load above-the-fold hero media.
- **Rendering Containment:** Reserve `content-visibility: auto` (paired with `contain-intrinsic-size`) strictly for large, complex lists or infinite feeds—never on compact landing sections where skipping off-screen subtrees degrades sequential accessibility or immediate DOM text visibility.
- **Scroll & Interaction Efficiency (INP):** Throttle scroll-spy updates via `requestAnimationFrame`, avoid duplicate document/window scroll listeners, batch DOM reads before writes, and only update active indicator geometry when the active section changes.
- **Native Browser Surface Theming:** Synchronize `<meta name="color-scheme">` and CSS `color-scheme` with `[data-theme]` (plus cross-tab `storage` event sync) so scrollbars, text selection, and carets match Light/Dark mode immediately without FOUC.
- **Opt-In Motion From Visible Default (WCAG 2.3.3):** Gate spatial entrance and scroll-reveal animations inside `@media (prefers-reduced-motion: no-preference)` starting from an already-visible state (never hiding sections at `opacity: 0` before scroll), rather than forcing global `animation-duration: 0.01ms !important`.
- **Accessible Navigation & Drawer:** Keep `<nav>` `aria-label` concise without repeating the word "navigation" (`Primary`, `Mobile`); apply the `inert` attribute to the closed mobile drawer so hidden links are excluded from keyboard Tab order; return focus to `#mobileMenuBtn` on `Escape`; include both Sign In and Help & Support inside the mobile drawer.

## Do's and Don'ts

### Do:
- **Do** use `icon()` inline stroke SVGs (`24x24` viewBox, `2px` stroke) for all navigation, action buttons, empty states, alerts, and notification rows.
- **Do** apply `.tabular-nums` (`font-variant-numeric: tabular-nums`) to all leave balances, day durations, percentages, dates, and reference numbers.
- **Do** display each metric or profile field once per screen (e.g., `remaining`, `used of allocated`, and a progress bar on leave cards without repeating the same figures in sub-boxes).
- **Do** keep one primary `.btn-primary` action per view and use `.btn-secondary` or `.btn-outline-danger` for supporting or destructive actions.
- **Do** test every view and brand asset across both `[data-theme="light"]` and `[data-theme="dark"]` to guarantee WCAG 2.2 AA contrast.
- **Do** gate spatial animations behind `@media (prefers-reduced-motion: no-preference)` and mark closed off-canvas drawers with `inert`.

### Don't:
- **Don't** use emoji characters (`📊`, `👤`, `⚖️`, `📅`, `🔔`, `🌙`, `☀️`, `⏳`) anywhere in navigation, buttons, alerts, badges, or headings.
- **Don't** apply thick left accent borders (`border-left: 3px` or `4px solid`) to cards, notification items, or toast alerts.
- **Don't** place uppercase kicker/eyebrow badges (`EMPLOYEE SELF-SERVICE`, `EMPLOYEE ACCESS`, `HUMAN RESOURCE INFORMATION SYSTEM`) above page headings, hero headlines, or card titles.
- **Don't** repeat step numbers inside headings when a `Step N` badge is already rendered.
- **Don't** expose developer-facing controls (such as `JSON API View` buttons or raw internal status type pills) on Employee Workspace screens.
- **Don't** use bare CSS `:invalid` selectors that paint required inputs red before the user has interacted with the form; always use `:user-invalid` or `[aria-invalid="true"]`.
- **Don't** use global `animation-duration: 0.01ms !important` reduced-motion overrides or apply `content-visibility: auto` to above-the-fold hero content.
