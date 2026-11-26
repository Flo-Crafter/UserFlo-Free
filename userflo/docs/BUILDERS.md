# Builder compatibility (Phase 5)

How detection works: one delegated click listener finds the nearest *meaningful* element (links, buttons, ARIA controls/tabs/menus, summary, `[data-userflo]`, plus admin-configured selectors), describes it from semantics (accessible name, id, `name`/`data-*`/`aria-controls`, stable non-numbered classes) and attaches a **component** string. Core knows landmarks (`nav`, `header`, `dialog`, …) and Gutenberg blocks; optional adapters (`assets/js/adapters.js`, loaded only when Divi/Elementor/Bricks/WooCommerce is detected) add `divi:button`, `elementor:button`, `bricks:button`, `woocommerce:product`, etc. Nothing reads input values.

| Target | Evidence | Status |
|---|---|---|
| WordPress default theme (Twenty Twenty-Five) + Gutenberg core blocks | Real site; automated browser test (`dev/e2e-builders.js`) | **Verified** (core/button => `gutenberg:button`) |
| Divi 5.15.1 theme with legacy module shortcodes (`et_pb_button`, `et_pb_blurb`) | Real Divi 5 theme rendering real module markup; automated test | **Verified** for those modules (`divi:button`, `divi:blurb`) |
| Divi 5 Visual Builder–authored pages (native D5 blocks/modules, theme-builder headers, popups, forms module) | Not tested: no authored D5 layouts available here | **Untested** |
| Divi 4 | Adapter targets the same `et_pb_*_N` class convention, no Divi 4 install | **Untested** |
| Elementor | Hand-written markup mimicking widget output (`data-widget_type`) — plugin not installed | **Simulated only** |
| Bricks 2.4.3 | Hand-written `brxe-*` markup — theme zip provided but not installed (deferred) | **Simulated only** |
| WooCommerce add-to-cart / cart / checkout context | Hand-written product markup + `woocommerce` body class — plugin not installed | **Simulated only** (AJAX `added_to_cart` events not handled; click-based) |
| Classic Editor / custom themes | Plain HTML content on both test themes | **Verified** for ordinary markup |
| Forms, media (`play`), accordion (`summary`), tabs, menus (`aria-expanded`), dialogs | Fixture page on both themes | **Verified** (form: name only, never values) |
| SPA/history navigation | `pushState` hook implemented; **no automated test** | **Untested** |

Known limitations: clicks on elements that are *not* semantically interactive (e.g. a `div` with a JS handler and no role) are not recorded unless added via *Also track clicks on* or `data-userflo`; iframes (embedded video players, payment frames) are invisible to the page script; component names rely on class conventions that builders may change.
