---
version: 1
slug: "app-views-dashboard-employee-php"
primary_target: "app/views/dashboard/employee.php"
related_targets: ["app/views/partials/sidebar.php","app/views/partials/navbar.php"]
---

# Surface Brief: Employee Self-Service Dashboard (`app/views/dashboard/employee.php`)

- **Scope & Mode:** Authenticated Employee Self-Service Hub (`/employee/dashboard`), `Operate` mode.
- **Audience & Job:** NEXORA HR employees checking annual leave entitlements, filing new time-off requests, tracking or cancelling pending applications, and triaging approval alerts without leaving the overview.

## Direction contract

THESIS: The Employee Dashboard operates as an interactive "Executive Ledger" workbench—combining a refined 4-column KPI overview and balanced 3-card leave entitlement breakdown with inline status filtering, pending request cancellation, and instant alert triage—refusing passive read-only filler and duplicated figures.

OWN-WORLD: Cool Alabaster (`#F8FAFC`) and Pure Ledger White (`#FFFFFF`) in Light Mode (inverting to Nocturnal Navy `#0B132B` / `#0B1120` and Elevated Midnight Slate `#1E293B` in Dark Mode), `1px solid` hairline dividers, Cobalt Authority (`#1D4ED8`) primary action and active pills, `tabular-nums` display figures (`1.75rem`), and `24x24` `2px`-stroke inline SVGs.

STORY: An employee arrives, immediately sees their exact available leave balance, Days Used meter (`%`), Pending Requests, and Unread Alerts in the 4-column `.emp-kpi-grid`, clicks `Apply ->` on any active leave category card (`.emp-category-card`), or filters/cancels pending requests and marks notifications read directly in place.

FIRST VIEWPORT: Top identity welcome bar with monospace `EMP` badge, position/department context, and the primary `File Leave Request` CTA on the right; directly below sits the 4-column `.emp-kpi-grid` (Available Leave, Days Used with proportional `.emp-kpi-meter`, interactive Pending Requests card, and interactive Unread Alerts card via `.emp-kpi-card.emp-kpi-interactive`), followed by the 3-column `My Leave Balances` `.emp-category-grid` cards with `.emp-category-meter` and `.emp-category-ledger` breakdown.

FORM: Interactive Leave Quota & Request Workbench (#1 user-confirmed structure inside DESIGN.md's Executive Ledger); `seed: none (unavailable)`.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
