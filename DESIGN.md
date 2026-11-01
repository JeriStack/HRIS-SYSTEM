---
name: Employee HRIS & Leave Management System
description: Production-grade enterprise design system for HR management, transactional leave approvals, and algorithm benchmarking
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

# Design System: Employee HRIS & Leave Management System

## Overview

**Creative North Star: "The Reliable Instrument"**

The visual language of the Employee HRIS & Leave Management System is calibrated for high-consequence administrative reliability and employee self-service efficiency. Operating primarily in **Operate mode**, the system treats screen real estate as a precision tool: typography is legible and compact, spatial margins are rhythmic and predictable, and cognitive friction is minimized through strict visual hierarchy.

The aesthetic philosophy is grounded in quiet institutional competence. Screens avoid decorative distractions, relying on neutral slate canvases, crisp hairline borders, and deliberate typographic rhythm to anchor complex data tables, leave ledgers, and approval queues. Brand presence is expressed through purposeful micro-details—tactile buttons, accessible focus halos, and instant visual state confirmations—rather than flashy ornamentation.

**Key Characteristics:**
- **Restrained Density:** Information-rich layouts that stay uncluttered through consistent 4px-based spatial multiples.
- **Calm, High-Contrast Palette:** Slate neutrals paired with a decisive Cobalt Blue accent and WCAG AA-compliant semantic alerts.
- **Tactile State Elevation:** Flat-at-rest surfaces that lift delicately on hover and state transitions to provide tactile confidence.
- **Dual-Channel Status Clarity:** Every critical status indicator combines color, text, and shape to guarantee accessibility.

## Colors

The palette is engineered for high data clarity, strict WCAG 2.1 AA+ contrast ratios (exceeding 4.5:1 for all text), and purposeful interactive accentuation.

### Primary
- **Reliable Cobalt Blue** (`#1D4ED8`): The normative brand accent. Dedicated strictly to primary user actions (submitting leave, saving records), active navigation links, and focus rings.
- **Deep Cobalt Hover** (`#1E40AF`): Interactive hover state for primary controls.
- **Midnight Cobalt Active** (`#172554`): Pressed and active states for primary controls.
- **Soft Cobalt Wash** (`#EFF6FF`): Background fill for active table rows, role badges, and primary callouts.
- **Cobalt Boundary** (`#BFDBFE`): 1px border accent for selected cards and active input rings.

### Secondary
- **Enterprise Teal** (`#0D9488`): Secondary interactive accent used for administrative utilities, HR operational actions, and secondary highlight chips.
- **Deep Teal Hover** (`#0F766E`): Hover state for secondary teal actions.
- **Teal Subtle Tint** (`#F0FDFA`): Background tint for HR status chips and analytical badges.

### Neutral
- **Deep Slate Navy** (`#0F172A`): Primary headings, card titles, and high-emphasis labels (14.2:1 contrast against white).
- **Sidebar Charcoal** (`#1E293B`): Persistent dark sidebar navigation surface providing authoritative structural anchoring.
- **Slate Sub-surface** (`#334155`): Secondary body copy, table labels, and dark container borders (7.6:1 contrast).
- **Muted Slate Meta** (`#64748B`): Timestamps, form helper text, table column headers, and breadcrumb dividers (4.6:1 contrast).
- **Hairline Border** (`#E2E8F0`): Default 1px structural dividing lines for cards, tables, and nav headers.
- **Input Border Slate** (`#CBD5E1`): Resting boundary for form inputs, dropdowns, and checkboxes.
- **Soft Table Shading** (`#F1F5F9`): Table header background, neutral badges, and input hover fills.
- **Neutral Canvas** (`#F8FAFC`): Background canvas for authenticated dashboard pages and application shells.
- **Pure White** (`#FFFFFF`): Elevated card surfaces, form inputs, modal dialogs, and button labels.

### Semantic Status
- **Accessible Green** (`#15803D`, bg: `#F0FDF4`, border: `#BBF7D0`): Approved leave requests, active employees, and success toasts.
- **Accessible Amber** (`#B45309`, bg: `#FFFBEB`, border: `#FDE68A`): Pending approvals, cautionary alerts, and rate-limit notices.
- **Accessible Crimson** (`#B91C1C`, bg: `#FEF2F2`, border: `#FECACA`): Rejected applications, validation errors, and destructive actions.
- **Accessible Cerulean** (`#0369A1`, bg: `#F0F9FF`, border: `#BAE6FD`): Informational notices and system notifications.

### Named Rules
**The 10% Intentionality Rule.** Cobalt Blue is reserved exclusively for the single primary page action, active navigation link, or focus ring. It must never exceed 10% of any screen's surface area. Its rarity is the visual signal of importance.

**The Dual-Channel Status Rule.** No status may ever be communicated by color alone. Every badge, chip, and banner must combine a semantic background, accessible foreground text, and an explicit icon or geometric dot.

## Typography

**Display & Body Font:** `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`  
**Monospace Font:** `ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace`  

**Character:** Clean, objective system sans-serif maximizing native platform rendering speed and typographic clarity across Windows 11 and macOS environments.

### Hierarchy
- **Display / Page Header** (Bold 700, 1.5rem / 24px, line-height 1.3, letter-spacing -0.015em): Top-level screen titles and dashboard page headers.
- **Headline / Section Header** (Semi-bold 600, 1.25rem / 20px, line-height 1.3): Major card headers, modal titles, and statutory sections.
- **Title / Card Header** (Semi-bold 600, 1.125rem / 18px, line-height 1.3): Metric card labels, sub-section headers, and form group titles.
- **Body / Main Text** (Regular 400, 0.875rem / 14px, line-height 1.5): Table rows, form inputs, modal body text, and descriptive paragraphs.
- **Label / Metric Caps** (Bold 700, 0.75rem / 12px, line-height 1.2, letter-spacing 0.05em, uppercase): Stat card descriptors, table headers, and status badges.
- **Meta / Timestamp** (Regular 400, 0.75rem / 12px, line-height 1.4): Timestamps, audit log metadata, and inline validation helper messages.

### Named Rules
**The System Native Rule.** Do not load external web fonts (e.g. Google Fonts CDN) for authenticated application screens. System fonts guarantee sub-millisecond initial paint times, zero layout shifts (CLS), and offline resilience.

## Layout

The spatial model uses an 8-point grid with a 4px sub-grid for tight control pairings:
- **Application Shell**: Two-pane structure consisting of a fixed 260px dark navigation sidebar (`#1E293B`) and a flexible main content area (`#F8FAFC`).
- **Main Container**: Maximum content width clamped to 1360px (`--content-max-width`) with responsive lateral padding (1.5rem / 24px on desktop, 1rem / 16px on mobile).
- **Rhythm**: Standard 16px (`--space-4`) between related form controls, 24px (`--space-6`) between cards and major layout blocks, and 32px (`--space-8`) under primary page headers.
- **Responsive Breakpoints**:
  - `sm` (640px): Form rows collapse from side-by-side grids into single columns; table horizontal scroll activates.
  - `md` (768px): Navigation sidebar transitions to collapsible off-canvas drawer.
  - `lg` (1024px): Standard desktop two-pane layout with multi-column metric grids.
  - `xl` (1280px): Full 3- or 4-column metric card grid.

## Elevation & Depth

Surfaces in the HRIS are **flat-at-rest with subtle tonal layering**. Contrast between containers is primarily established via hairline borders (`#E2E8F0`) and surface fills rather than heavy drop shadows. Shadows serve exclusively as state indicators during hover, dropdown reveal, and modal presentation.

### Shadow Vocabulary
- **Resting Surface** (`--shadow-sm: 0 1px 3px 0 rgba(15, 23, 42, 0.08), 0 1px 2px -1px rgba(15, 23, 42, 0.08)`): Applied to resting content cards, metric panels, and tables.
- **Interactive Lift** (`--shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.07), 0 2px 4px -2px rgba(15, 23, 42, 0.05)`): Applied on stat card hover and active dropdown menus.
- **Floating Overlays** (`--shadow-lg: 0 10px 15px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -4px rgba(15, 23, 42, 0.03)`): Applied to notification flyouts and popover panels.
- **Modal Dialogs** (`--shadow-xl: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.04)`): Applied to centered confirmation dialogs and approval review modals.

### Named Rules
**The Flat-At-Rest Rule.** All cards, tables, and buttons rest flat with a 1px border. Shadows are never decorative; they appear only in response to user state (hover, dropdown menu trigger, modal launch).

## Shapes

- **Corner Radius Scale**:
  - `sm` (4px): Small badges, compact icon buttons, and table tag chips.
  - `md` (6px): Standard form inputs, buttons, select boxes, and alert banners.
  - `lg` (8px): Cards, metric panels, modal containers, and table wrapper boxes.
  - `full` (9999px): Pill badges, status indicator dots, and user profile avatar circles.
- **Border Treatment**: Clean, solid 1px hairline borders (`#E2E8F0`). Darker 1px border (`#CBD5E1`) for form inputs to ensure immediate affordance.

## Components

### Buttons
- **Shape:** Medium radius (`6px` / `--radius-md`).
- **Primary:** Cobalt Blue background (`#1D4ED8`), white text (`#FFFFFF`), bold typography, padding `0.55rem 1.15rem`.
- **Hover / Focus:** Deepens to `#1E40AF` with a 2px offset focus ring (`outline: 2px solid #1D4ED8; outline-offset: 2px;`).
- **Secondary:** White surface background (`#FFFFFF`), slate text (`#0F172A`), 1px border (`#CBD5E1`), hover background `#F1F5F9`.
- **Danger:** Crimson background (`#B91C1C`), white text, hover background `#991B1B`. Reserved for rejections and destructive actions.
- **Loading State:** Disabled pointer events, 65% opacity, and embedded SVG loading spinner replacing the action icon.

### Cards / Containers
- **Corner Style:** Large radius (`8px` / `--radius-lg`).
- **Background:** Crisp pure white (`#FFFFFF`).
- **Border:** 1px hairline (`#E2E8F0`).
- **Header:** Padding `1rem 1.5rem` (`--space-4` `--space-6`) with bottom border divider.
- **Body:** Generous `1.5rem` (`--space-6`) internal padding.

### Inputs / Form Fields
- **Style:** Height ~42px, radius `6px`, 1px border (`#CBD5E1`), white background, text `#0F172A`.
- **Focus:** Border shifts to Cobalt Blue (`#1D4ED8`) accompanied by a 3px ambient glow (`box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15);`).
- **Invalid State:** Crimson border (`#B91C1C`), light red wash background (`#FEF2F2`), accompanied by an inline `.form-error` message.

### Tables & Data Presentation
- **Header:** Uppercase 11px bold text (`#64748B`), letter-spacing `0.06em`, subtle gray background (`#F1F5F9`), bottom border.
- **Rows:** Minimum height 48px, vertical-align middle, subtle hover highlight (`#F8FAFC`).
- **Actions:** Grouped inline flex with compact icon buttons (`34px x 34px`) with tooltips.

### Status Badges
- **Pill Shape:** Fully rounded (`9999px`), padding `0.25rem 0.65rem`, uppercase 11px bold text.
- **Structure:** 6px geometric dot (`.badge-dot`) positioned before label text.
- **Approved:** Green tint (`#F0FDF4`), text `#15803D`, border `#BBF7D0`.
- **Pending:** Amber tint (`#FFFBEB`), text `#B45309`, border `#FDE68A`.
- **Rejected:** Red tint (`#FEF2F2`), text `#B91C1C`, border `#FECACA`.

## Do's and Don'ts

### Do:
- **Do** preserve the strict 2-Role boundary between HR administrative oversight and Employee self-service views.
- **Do** ensure every interactive element has an accessible `:focus-visible` ring (`2px solid #1D4ED8`, offset 2px).
- **Do** pair every semantic color status badge with an explicit 6px shape dot and readable text.
- **Do** keep cards and table surfaces clean and flat-at-rest, using shadows only for interactive elevation.
- **Do** maintain a minimum 4.5:1 text-to-background contrast ratio on all informational text.

### Don't:
- **Don't** introduce flashy neon gradients, floating glassmorphic blobs, or playful consumer SaaS micro-copy.
- **Don't** use Cobalt Blue for generic non-actionable decoration or non-clickable text.
- **Don't** allow leave approval or rejection actions without an immediate disabled state and loading confirmation.
- **Don't** hide table columns on mobile without providing a clean, accessible stacked-card fallback.
- **Don't** load heavy external client-side CSS or font frameworks; rely on native system fonts and local CSS tokens.

