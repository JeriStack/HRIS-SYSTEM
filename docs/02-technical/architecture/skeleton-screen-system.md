# NEXORA HR — Skeleton Screen Loading System

## 1. Overview & Architecture

The Skeleton Screen system in NEXORA HR provides a consistent, accessible loading state across all administrative, HR, employee self-service, and analytical views. It replaces jarring blank canvases or generic spinners with subtle shimmer surfaces matching actual UI layouts, preventing Cumulative Layout Shift (CLS) and optimizing perceived performance.

The system is split into two complementary layers with zero external dependencies:
1. **Server-Side Helpers & Partials (`render_skeleton()`)**: Allows PHP templates to embed accessible skeleton placeholders during initial view assembly or server-rendered loading cycles.
2. **Client-Side Engine (`window.Skeleton`)**: A lightweight ES6 engine in `public/assets/js/skeleton.js` that provides programmatic `show()`, `hide()`, and `wrapAsync()` capabilities with template generators for dynamic AJAX/WebSocket views.

---

## 2. Design Tokens & CSS Specification

Defined in `public/assets/css/skeleton.css`:

### Variables
- `--skeleton-base`: `#E2E8F0` (Light) / `#1E293B` (Dark)
- `--skeleton-highlight`: `#F1F5F9` (Light) / `#334155` (Dark)
- `--skeleton-speed`: `1.5s`

### Pure CSS Shimmer Animation
```css
@keyframes skeleton-shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
```

### Accessibility & Reduced Motion
- Decorator elements enforce `aria-hidden="true"`, `pointer-events: none;`, and `user-select: none;`.
- Outer containers automatically receive `role="status"` and `aria-busy="true"` accompanied by a screen-reader-only message (`.sr-only`).
- Full `@media (prefers-reduced-motion: reduce)` support: disables animation and sets a static neutral background.

---

## 3. Server-Side PHP Helper (`render_skeleton`)

Defined in `core/functions.php`:

```php
render_skeleton(string $component, array $options = []): string
```

### Available Partials (`app/views/partials/skeletons/`)
- `stat_card`: Metric cards. Options: `count` (default 4), `standalone` (bool).
- `table`: Tables with data rows. Options: `rows` (5), `columns` (5), `hasToolbar` (bool), `standalone` (bool).
- `card`: Panel cards. Options: `lines` (3), `hasHeader` (bool), `hasFooter` (bool), `hasAvatar` (bool).
- `chart`: Chart container with varied-height simulated bars. Options: `bars` (7), `height` (220), `hasHeader` (bool).
- `list`: Activity or notification items. Options: `count` (5), `hasAvatar` (bool), `hasBadge` (bool).
- `profile`: Employee or user profile header with 6-field metadata grid. Options: `fields` (6).
- `form`: Form inputs and labels. Options: `fields` (4), `columns` (1 or 2), `hasButton` (bool).

### PHP Usage Example
```php
<!-- Embedded within a dashboard or report view -->
<div id="metricsContainer">
    <?php if (empty($metrics)): ?>
        <?= render_skeleton('stat_card', ['count' => 4, 'loading_text' => 'Loading workforce metrics...']) ?>
    <?php else: ?>
        <!-- Actual metrics content -->
    <?php endif; ?>
</div>
```

---

## 4. Client-Side Engine (`window.Skeleton`)

Available globally in all portal views (`app/views/layouts/hr.php` and `main.php`):

### Methods
- `Skeleton.show(target, templateNameOrHtml, options)`: Injects the skeleton layout and sets `aria-busy="true"`.
- `Skeleton.hide(target, replacementContent)`: Removes the skeleton and sets `aria-busy="false"`. If replacement HTML is omitted, restores original preserved HTML.
- `Skeleton.wrapAsync(target, asyncFn, templateName, options)`: Automatically shows skeleton, executes `asyncFn()`, and hides skeleton in a `finally` block.

### Client-Side Templates (`Skeleton.templates`)
- `table(options)`: Generates table markup (`cols`, `rows`, `hasToolbar`).
- `statCards(options)`: Generates stat cards (`count`, `standalone`).
- `card(options)`: Generates panel card (`lines`, `hasHeader`, `hasFooter`).
- `chart(options)`: Generates chart placeholder (`bars`, `height`).
- `list(options)`: Generates list items (`count`, `hasAvatar`, `hasBadge`).
- `profile(options)`: Generates profile header and metadata grid (`fields`).
- `form(options)`: Generates form fields (`fields`, `columns`).

### JavaScript Usage Examples

#### Example 1: Loading Dynamic Positions on Department Change
```javascript
// Automatically handled in public/assets/js/forms.js
Skeleton.show(posSelectContainer, 'form', { fields: 1, hasButton: false });
```

#### Example 2: Dynamic Notification Drawer Fetch
```javascript
// Automatically handled in public/assets/js/realtime.js
Skeleton.show(rtList, 'list', { count: 3, hasBadge: false, loadingText: 'Loading notification history...' });
```

#### Example 3: Async Operation Wrapper
```javascript
await Skeleton.wrapAsync('#analyticsWrapper', async () => {
    const res = await fetch('/api/reports/live');
    const data = await res.json();
    renderCharts(data);
}, 'chart', { height: 260 });
```

---

## 5. Verification & Testing

Unit test suite in `tests/Unit/SkeletonRendererTest.php`:
- Verified component fallback safety.
- Verified ARIA attributes (`aria-hidden="true"`, `role="status"`, `aria-busy="true"`).
- Verified template dimension parameters and PSR-12 code standards.

