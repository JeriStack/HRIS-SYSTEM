# Responsive Layout & Accessibility Standards (a11y)
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 4 — UI/UX Design  
**Status:** Approved for Frontend Implementation  

---

## 1. Breakpoints & Viewport Grid System

The responsive interface adapts seamlessly across three core device classes:

| Breakpoint Tier | Viewport Width ($W$) | Layout Behavior | Navigation Architecture |
| :--- | :---: | :--- | :--- |
| **Desktop** | $\ge 1024\text{ px}$ | Multi-column grid (2–4 cards wide), wide tables. | Permanent visible sidebar ($260\text{ px}$). |
| **Tablet** | $768\text{ px} \le W < 1024\text{ px}$ | 2-column cards, form rows stack into 2-columns. | Collapsible sidebar with toggle button. |
| **Mobile** | $< 768\text{ px}$ ($\min 375\text{ px}$) | Single-column stack, tables horizontally scrollable. | Off-canvas drawer navigation overlay. |

---

## 2. Table & Form Mobile Responsiveness

### 2.1 Table Horizontal Scroll Container
* Tables are wrapped in a container with `overflow-x: auto` and `-webkit-overflow-scrolling: touch`.
* A subtle drop-shadow or fade gradient on the right table boundary visually indicates to mobile users that additional columns exist off-screen.

### 2.2 Adaptive Form Row Stacking
* On desktop, multi-part form inputs (e.g. First Name, Middle Name, Last Name) span 3 horizontal columns (`grid-template-columns: repeat(3, 1fr)`).
* On mobile devices ($< 768\text{ px}$), the grid automatically flattens into a single vertical column (`1fr`), ensuring minimum touch target widths and legible input fields.

---

## 3. Web Accessibility (WCAG 2.1 AA Compliance)

### 3.1 Color Contrast Ratios
* All body text meets or exceeds the **$4.5:1$** contrast ratio requirement against background surfaces (Slate 900 `#0F172A` on Slate 50 `#F8FAFC` yields an outstanding contrast ratio of **$14.8:1$**).
* Status indicators pair colors with text labels and unicode/SVG icons to support color-blind users.

### 3.2 Visible Keyboard Focus
* Interactive controls (buttons, links, inputs) have a dedicated high-contrast focus outline:
  ```css
  :focus-visible {
      outline: 2px solid #4F46E5;
      outline-offset: 2px;
  }
  ```

### 3.3 Semantic HTML & ARIA Landmarks
* Semantic layout tags are enforced: `<header>`, `<nav>`, `<main>`, `<aside>`, `<footer>`.
* All form `<input>` and `<select>` elements have associated `<label for="...">` tags.
* Error messages use `aria-describedby="field-error-id"`, and dynamic alerts utilize `role="alert"` for screen reader announcements.

### 3.4 Minimum Touch Target Sizing
* Touch targets (buttons, links, form inputs) provide a minimum clickable area of **$44\text{ px} \times 44\text{ px}$** on touch-enabled viewports.
