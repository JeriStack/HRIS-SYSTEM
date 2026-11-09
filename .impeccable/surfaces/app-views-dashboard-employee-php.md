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

THESIS: The Employee Dashboard operates as an interactive "Executive Ledger" workbench—combining high-precision annual quota balances with inline status filtering, pending request cancellation, and instant alert triage—refusing passive read-only stat cards and duplicated 3-box sub-cards.

OWN-WORLD: Cool Alabaster (`#F8FAFC`) and Pure Ledger White (`#FFFFFF`) in Light Mode (inverting to Nocturnal Navy `#0B132B` and Midnight Slate `#1E293B` in Dark Mode), `1px solid` hairline dividers, Cobalt Authority (`#1D4ED8`) primary action and active pills, `tabular-nums` display figures (`1.75rem`), and `24x24` `2px`-stroke inline SVGs.

STORY: An employee arrives, immediately sees their exact available leave balance and annual utilization percentage in the unified 4-cell ledger strip, clicks `Apply ->` on any active leave category to pre-fill a request, or filters/cancels pending requests and marks notifications read directly in place.

FIRST VIEWPORT: Top identity welcome bar with monospace `EMP` badge, position/department context, and the primary `File Leave Request` CTA on the right; directly below sits the 4-cell horizontal `.emp-summary-strip` (Available Leave, Annual Quota Used with live `%` meter, Pending Requests, Unread Alerts), followed by the interactive Leave Entitlement Ledger cards.

FORM: Interactive Leave Quota & Request Workbench (#1 user-confirmed structure inside DESIGN.md's Executive Ledger); `seed: none (unavailable)`.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
