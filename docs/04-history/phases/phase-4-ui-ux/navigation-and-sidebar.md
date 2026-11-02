# Navigation & Persistent Sidebar Specification
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Status:** Approved for Frontend Implementation  

---

## 1. Global Application Shell Layout

The authenticated layout utilizes a standard **persistent sidebar navigation shell** with a fixed top utility header and a flexible scrollable content canvas:

```
+-----------------------------------------------------------------------------------+
|  BRAND LOGO   |  TOP HEADER: Page Title / Breadcrumbs   [Alerts Badge] [User Profile] |
+---------------+-------------------------------------------------------------------+
|  [SIDEBAR]    |  MAIN CONTENT CANVAS                                              |
|               |                                                                   |
|  - Dashboard  |  [Page Header & Action Buttons]                                   |
|  - Employees  |  +-------------------------------------------------------------+  |
|  - Org Units  |  | Cards / Tables / Form Controls                              |  |
|  - Leaves     |  |                                                             |  |
|  - Reports    |  |                                                             |  |
|  - DSA Lab    |  |                                                             |  |
|  - Logs       |  +-------------------------------------------------------------+  |
|  - Logout     |                                                                   |
+---------------+-------------------------------------------------------------------+
|               |  FOOTER: System Version & Academic Information                    |
+-----------------------------------------------------------------------------------+
```

---

## 2. Administrator / HR Sidebar Structure

The administrative sidebar is grouped into logical operational clusters with SVG icons:

```
[HRIS Enterprise Engine]
│
├── EXECUTIVE
│   └── 📊 Dashboard (/admin/dashboard)
│
├── WORKFORCE MANAGEMENT
│   ├── 👥 Employees Directory (/employees)
│   ├── ➕ Add Employee (/employees/create)
│   ├── 🏢 Departments (/departments)
│   └── 💼 Job Positions (/positions)
│
├── LEAVE OPERATIONS
│   ├── ⏳ Pending Leave Queue (/leaves/manage) [Badge: Pending Count]
│   ├── 📋 Leave Policies & Types (/leaves/types)
│   ├── 💳 Employee Balances (/leaves/balances)
│   └── 📜 Global Leave History (/leaves/history/all)
│
├── ANALYTICS & AUDIT
│   ├── 📈 Workforce Reports (/reports/employee)
│   ├── 📅 Leave Reports (/reports/leave)
│   ├── 🏛️ Department Summary (/reports/department)
│   └── 🔒 Activity Audit Logs (/activity-logs)
│
├── DSA ACADEMIC LABORATORY
│   ├── 🧪 Algorithm Playground (/dsa/playground)
│   └── ⚡ Benchmark Visualizer (/dsa/visualizer)
│
└── USER SESSION
    ├── 🔔 Notifications (/notifications) [Badge: Unread Count]
    └── 🚪 Sign Out (POST /logout)
```

---

## 3. Employee Self-Service Sidebar Structure

The regular employee sidebar provides a streamlined self-service menu:

```
[HRIS Portal]
│
├── MY WORKSPACE
│   ├── 🏠 Dashboard (/dashboard)
│   └── 👤 My Profile (/profile)
│
├── MY LEAVES
│   ├── 💳 Leave Balances (/leaves/balance)
│   ├── 📝 Apply for Leave (/leaves/apply)
│   └── 📜 Leave History & Status (/leaves/history)
│
└── NOTIFICATIONS & SESSION
    ├── 🔔 My Notifications (/notifications) [Badge: Unread Count]
    └── 🚪 Sign Out (POST /logout)
```

---

## 4. Responsive Viewport Adaptations

### 4.1 Desktop Viewports ($\ge 1024\text{ px}$)
* Sidebar is permanently visible with width $260\text{ px}$.
* Left margin of main canvas offset by $260\text{ px}$.
* High contrast deep slate background (`#0F172A`) with light gray text (`#94A3B8`). Active item highlighted with `#4F46E5` background and pure white text.

### 4.2 Mobile & Tablet Viewports ($< 1024\text{ px}$)
* Sidebar is off-canvas by default (`transform: translateX(-100%)`).
* Top header displays a hamburger toggle button (`☰`).
* When tapped, the sidebar slides into view as an elevated drawer with an overlay backdrop (`rgba(15, 23, 42, 0.5)`). Tapping outside the drawer closes the sidebar.
