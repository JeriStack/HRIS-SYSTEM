# UI/UX Design System Specification
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Status:** Approved for Frontend Implementation  

---

## 1. Visual Philosophy & Design Principles

The design system establishes a **clean, accessible, high-efficiency enterprise aesthetic** tailored for Human Resource management and Computer Science laboratory demonstrations. It avoids frivolous visual clutter, focusing instead on information density, high text legibility, clear visual hierarchy, and predictable interaction flows.

---

## 2. Color Palette & Semantic Tokens

### 2.1 Core Neutral Palette (Deep Slate Foundation)
* **Background Canvas:** `#F8FAFC` (Slate 50) — Crisp, glare-free page background.
* **Surface / Card Background:** `#FFFFFF` (Pure White) — Elevated container surfaces.
* **Surface Secondary / Sidebar:** `#0F172A` (Slate 900) — Deep slate container providing authoritative contrast for primary navigation.
* **Border Color:** `#E2E8F0` (Slate 200) — Subtle dividing borders between cards, tables, and form sections.
* **Text Primary:** `#0F172A` (Slate 900) — High contrast text for headings and primary table values (Contrast ratio $> 14:1$).
* **Text Secondary:** `#475569` (Slate 600) — Subheadings, table headers, and helper descriptions.
* **Text Muted:** `#94A3B8` (Slate 400) — Disabled items and placeholder text.

### 2.2 Brand & Interactive Accent (Indigo Trust)
* **Primary Accent:** `#4F46E5` (Indigo 600) — Action buttons, active navigation indicators, and key focus rings.
* **Primary Hover:** `#4338CA` (Indigo 700) — Hover/active state for primary interactive elements.
* **Primary Light Tint:** `#EEF2FF` (Indigo 50) — Background highlight for active sidebar links and selected rows.

### 2.3 Semantic Status & Feedback Palette
Colors are **never** used as the sole indicator of status. All badges, alerts, and state cards pair these tokens with descriptive text and distinct iconography.

| Status | Border / Base Color | Background Tint | Text Color | Icon Indicator | Usage Scenarios |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Success** | `#10B981` (Emerald 500) | `#ECFDF5` (Emerald 50) | `#065F46` (Emerald 800) | `✓` Check Circle | Approved leaves, successful creation, data saves. |
| **Warning** | `#F59E0B` (Amber 500) | `#FFFBEB` (Amber 50) | `#92400E` (Amber 800) | `!` Alert Triangle | Pending requests in queue, inactive employee status. |
| **Error** | `#EF4444` (Red 500) | `#FEF2F2` (Red 50) | `#991B1B` (Red 800) | `✕` Ban / X Circle | Rejected leaves, validation failures, CSRF errors. |
| **Info / Neutral**| `#3B82F6` (Blue 500) | `#EFF6FF` (Blue 50) | `#1E40AF` (Blue 800) | `ℹ` Info Circle | Cancelled leaves, workflow notifications, general notices. |

---

## 3. Typography Scale & Font Stack

* **Primary Font Family:** System UI Sans-Serif font stack ensuring instant rendering without webfont latency:
  `font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;`
* **Monospace Font Family (DSA Code & Metrics):**
  `font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;`

### Typographic Hierarchy

| Scale Token | Font Size | Line Height | Font Weight | Usage Example |
| :--- | :---: | :---: | :---: | :--- |
| `text-xs` | $12\text{ px}$ ($0.75\text{ rem}$) | $16\text{ px}$ | Regular ($400$) | Helper text, small status badges, timestamps. |
| `text-sm` | $14\text{ px}$ ($0.875\text{ rem}$) | $20\text{ px}$ | Regular / Medium ($500$) | Table cell text, form labels, sidebar links. |
| `text-base` | $16\text{ px}$ ($1.00\text{ rem}$) | $24\text{ px}$ | Regular ($400$) | Default body copy, form input values, modal text. |
| `text-lg` | $18\text{ px}$ ($1.125\text{ rem}$) | $28\text{ px}$ | Medium ($500$) | Card titles, subsection headers. |
| `text-xl` | $20\text{ px}$ ($1.25\text{ rem}$) | $28\text{ px}$ | Semi-Bold ($600$) | Section headers, modal dialog titles. |
| `text-2xl` | $24\text{ px}$ ($1.50\text{ rem}$) | $32\text{ px}$ | Bold ($700$) | Page titles, primary dashboard metric values. |
| `text-3xl` | $30\text{ px}$ ($1.875\text{ rem}$) | $36\text{ px}$ | Bold ($700$) | Top-level executive metric callouts. |

---

## 4. Spacing Scale, Elevation & Layout Grid

* **Base Unit:** $4\text{ px}$ ($0.25\text{ rem}$)
  * `space-1` = $4\text{ px}$, `space-2` = $8\text{ px}$, `space-3` = $12\text{ px}$, `space-4` = $16\text{ px}$, `space-6` = $24\text{ px}$, `space-8` = $32\text{ px}$.
* **Border Radii:**
  * Small elements (badges, buttons, inputs): `rounded-md` ($6\text{ px}$).
  * Container cards, tables, modal dialogs: `rounded-lg` ($8\text{ px}$ to $12\text{ px}$).
* **Elevation & Box Shadows:**
  * `shadow-sm`: `0 1px 2px 0 rgba(0, 0, 0, 0.05)` (Form controls, cards).
  * `shadow-md`: `0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)` (Dropdowns, floating cards).
  * `shadow-lg`: `0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)` (Modal dialogs).

---

## 5. Standard Component Styling Specifications

### 5.1 Form Controls
* **Inputs & Selects:** Height $40\text{ px}$, padding `8px 12px`, border `1px solid #CBD5E1`, background `#FFFFFF`, border-radius `6px`.
* **Focus State:** Border color `#4F46E5`, outline ring `2px solid rgba(79, 70, 229, 0.2)`.
* **Validation Error State:** Border color `#EF4444`, ring `2px solid rgba(239, 68, 68, 0.2)`, inline error message rendered below in `text-xs` `#EF4444`.

### 5.2 Button Variants
* **Primary Button:** Background `#4F46E5`, text `#FFFFFF`, font-weight `500`, border `none`, padding `8px 16px`. Hover: `#4338CA`.
* **Secondary / Outline Button:** Background `#FFFFFF`, text `#334155`, border `1px solid #CBD5E1`. Hover: `#F1F5F9`.
* **Danger Button:** Background `#EF4444`, text `#FFFFFF`, border `none`. Hover: `#DC2626`.
* **Success Button:** Background `#10B981`, text `#FFFFFF`, border `none`. Hover: `#059669`.

### 5.3 Data Tables
* **Container:** Card wrapper with border `1px solid #E2E8F0`, background `#FFFFFF`, overflow-x auto.
* **Table Header (`<th>`):** Height $44\text{ px}$, background `#F8FAFC`, text `#475569`, uppercase `text-xs`, font-weight `600`, tracking `0.05em`, border-bottom `1px solid #E2E8F0`.
* **Table Rows (`<tr>`):** Height $52\text{ px}$, border-bottom `1px solid #F1F5F9`. Hover state: background `#F8FAFC`.
* **Action Buttons:** Compact outline icons or buttons (Height $32\text{ px}$).

### 5.4 Status Badges
* Displayed as inline-flex containers with padding `3px 8px`, border-radius `9999px` (pill), font-size `12px`, font-weight `600`.
* **Active / Approved:** Background `#ECFDF5`, text `#065F46`, border `1px solid #A7F3D0`.
* **Pending:** Background `#FFFBEB`, text `#92400E`, border `1px solid #FDE68A`.
* **Rejected / Inactive:** Background `#FEF2F2`, text `#991B1B`, border `1px solid #FECACA`.
* **Cancelled:** Background `#F1F5F9`, text `#475569`, border `1px solid #CBD5E1`.
