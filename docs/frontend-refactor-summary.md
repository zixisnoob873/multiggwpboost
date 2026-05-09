# Frontend refactor notes

## CSS architecture

`resources/css/app.css` now acts as a layered manifest. The previous 15k-line stylesheet was retained as `resources/css/legacy.css` for compatibility and new tokens/base/layout/component/page files override it in explicit cascade layers.

Key folders:

- `resources/css/tokens.css`: colors, spacing, radii, shadows, typography and Bootstrap variable bridge.
- `resources/css/base.css`: body background, typography, focus states, skip links, reduced motion and print basics.
- `resources/css/components/*`: buttons, cards, forms, modals, tabs, tables, rank picker, chat, dashboards and empty states.
- `resources/css/pages/*`: home, marketplace, checkout, auth, customer, booster, admin, order and legal page polish.

## JavaScript architecture

`resources/js/app.js` now loads common UI immediately, then dynamically imports page modules only when their DOM roots are present. Bootstrap is imported through Vite in `resources/js/bootstrap.js` and exposed as `window.bootstrap` for existing modules.

## Accessibility and safety

- Native confirm dialogs are replaced by a reusable Bootstrap confirmation modal.
- Checkout payment cards are full-label controls with descriptions and disabled reasons.
- Rank picker rendering no longer builds option HTML with dynamic strings; DOM nodes and `textContent` are used.
- Rank picker tiers/divisions support arrow/Home/End keyboard movement.
- Local fallback assets avoid production hotlinks for rank imagery.
