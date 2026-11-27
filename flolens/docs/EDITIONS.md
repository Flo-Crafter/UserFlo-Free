# FloLens (free) and FloLens Pro

*Status: implemented in 0.1.0-beta.19. Boundaries marked **provisional** may still move before 1.0.*

FloLens is sold as two plugins:

- **FloLens (free)**: free, standalone, no licence, no licence field. A real analytics product for a small site.
- **FloLens Pro**: a separate paid plugin that *requires* Lite and adds the deeper tools, activated with a licence from the WooCommerce shop.

> **Since 0.39.0 (Lite) / 0.35.0 (Pro)** the matrix below follows the WordPress.org rule that Lite carries no locked code: goals beyond page/event, advanced journeys, the traffic detail views, campaign groups and attribution, advanced visitor filters, city geolocation, branding and custom ranges are implemented only in Pro. Since the first WordPress.org submission **Lite has no commercial limits at all** (no caps on goals, rules, pages, history, ranges or calendar months): Pro adds features, not bigger numbers. See `docs/WPORG-SPLIT.md`.

## 1. Final feature matrix

| Area | Free | Pro |
|---|---|---|
| **Collection** | Everything: page views, visitors, sessions, clicks, outbound links, downloads, scroll depth, engaged time, forms/commerce events from the builder adapters. Never limited by edition | The same engine. Pro adds no tracker, table or collection endpoint |
| **Privacy** | All of it: consent modes, GPC/DNT, IP handling, role/path/selector exclusions, retention, personal-data export and erase | Same; Pro features run *after* these checks |
| **Overview and reports** | Ranges from 24 hours to a year, KPIs, top pages/sources, previous-period comparison | Custom date range, Highlights (movers, engaged pages, exits) |
| **Statistics** | Standard summaries: pages, clicks, links, downloads, forms, custom events, journeys of the first pages | WordPress reports (content types, categories, forms, WooCommerce products, builders), e-mail/phone click report |
| **Visitors** | List, search, type filter, sorting, session list and journey with the recent window below | Advanced filters and facets, full history, lifetime visit calendar |
| **Recent activity** | Every event of a session and every session of a visitor, paginated | The same, plus the lifetime visit calendar |
| **Identity** | Logged-in label, visit counts and the account association itself | Account name/roles, e-mail, IP as stored (where the identity setting allows) |
| **Map** | 2D map, **country totals only** (city-level geolocation cannot be newly enabled) | City level, 3D globe, live feed |
| **Traffic calendar** | **Monthly** view, **page views**, current month + 2 earlier months, no day/hour drill-down | Week view, all four metrics, full history, day and hour detail with the 24-hour heatmap |
| **Campaigns (UTM)** | Campaign report by campaign / source / medium / content / term | Combined source / medium / campaign, comparison with the previous period, Campaign filter in Visitors |
| **Website audit** | Inventory of pages and elements (every page the audit can scan), basic findings (page loads, title, noindex) and basic accessibility checks against WCAG 2.2 A/AA (image alt text, names of links and buttons, form labels, page language) with an Accessibility overview, Health indicator on Pages | The full set of findings including the broader accessibility checks (a per-page accessibility report, contrast from inline colours, ARIA, keyboard and media patterns, accessibility counts in the Audit table and export), *Page health* priority list (accessibility issues on busy or task pages), report per page, hide findings with exclusions |
| **Elements** | Inventory, interaction totals, visitors, logged-in/anonymous split, trend, all filters, exclusions | Journeys around an element, goal comparison, findings per element |
| **Exclusions** | Stop tracking, leave out of statistics, one list for every report, no limit on rules | The same |
| **Goals** | Page and event goals without a limit, completions and conversion rates, split by campaign or source | Value per goal, funnels, split by device / country / new vs returning |
| **WooCommerce sales** | Orders, revenue, average order value, refunds, per currency (Processing + Completed) | Sales by campaign / source / medium, basket sessions, choose counted statuses |
| **Heatmaps** | – | Click heatmaps and scroll depth |
| **Reports / export** | CSV/JSON export (data portability), shortcodes, share links | Report builder (PDF/PNG), e-mail reports on a schedule |
| **Integrations** | – | GA4, Clarity, PostHog import; GTM dataLayer bridge |
| **Advanced tracking** | `UserFlo.track("name")`; exclusion selectors | Selector rules with labels, custom-event properties (allow-listed), disabled event kinds, diagnostics |
| **Journey analytics** | Basic summary: entrances, recorded exits and complete paths (up to 100 each) for the last 24 hours, 7 or 30 days | Full analysis: flow diagram and transition table, exploring by selection, filters (device, country, visitor type, folder grouping), comparison, paths leading to each exit, up to 100 paths, day/week/month and custom ranges up to 366 days |
| **Visitor journey** | Chronological timeline of every event of every session | Visual journey with engaged time per page, the anonymous-to-known identity card and session navigation |
| **Forms** | Every form is counted when submitted; name any form with `[userflo_form name="…"]` | Automatic detection of WPForms, Fluent Forms, Gravity Forms, Forminator, Formidable, Contact Form 7 and the Divi contact module, with confirmed sends and current titles in reports |
| **AI access** | – | Limited accounts for ChatGPT and Claude to read counters-only summaries, prompt personas, example questions, call log (see `AI-ACCESS.md`) |
| **Branding** | – | Company logo on screens, shared links and reports; the starting look of the 3D globe |
| **Settings extras** | Report a bug or request, Support the developer, Free vs Pro | Same |
| **Licence / updates** | None. Quiet "Upgrade to Pro" link | Settings → Licence, authenticated updates |

### Which advanced tracking options are Pro, and why
- **Selector rules + labels, "also track clicks on"**: configuration that reshapes what is recorded; the basic tracker already tags links, buttons and landmarks without any setup. *Exclusion* selectors stay in Lite: they are a privacy control.
- **Custom event properties**: free-form properties are the easiest way to leak personal data into analytics, so they exist only behind an owner-defined allow-list; plain event names stay free.
- **GTM bridge, diagnostics, disabled kinds**: integration and tuning features for professional setups.
- **E-mail and phone clicks** are recorded in both editions as *intent only* (value `mailto:` or `tel:` and a generic name, never the address or number, not even as link text). Pro adds the report by placement and page and the `userflo_email_click` / `userflo_phone_click` dataLayer events.

### Why these limits
Limits are on *inspection*, never on collection, storage or retention.
- **No commercial limits in Lite.** Earlier builds capped goals (3), exclusion rules (10), audited pages (25), report ranges (30 days), a visitor's history (10 sessions, 100 events), calendar months (3) and journey rows (10). WordPress.org guideline 5 treats such caps, which a payment lifts, as locked functionality, so they were removed. The only remaining number is the alert-rule safety bound (50), which is the same in every edition.
- **Country-only map**: lowest privacy impact and no extra database download.
All numbers live in one place (`flolens/src/Edition.php`, `lite_limits()`), are filterable (`userflo_edition_limits`) and are documented here.

### Provisional boundaries
Sharing/feeds, shortcodes, achievements, Pages, Live and the "journeys of the first pages" card stay in Lite for now. Individual and aggregate journey analytics and video watch time are **not implemented** (planned Pro features).

## 2. Architecture

- **Lite owns the engine**: the single tracker (`assets/js/tracker.js`), collector, schema, privacy, settings and menus, plus `UserFlo\Edition`, the one place that decides what an install may do (`Edition::can( $feature )`, `Edition::limit( $name )`).
- **Pro is an add-on** (`userflo-pro/`, slug `userflo-pro`, header `Requires Plugins: userflo`). It contains only the Pro feature classes (which keep their `UserFlo\…` namespaces, served by Pro's own autoloader; a class exists in exactly one plugin), the Pro admin bundle, the licence client and the Pro front-end scripts. It has no tracker, tables, collection endpoint or menu of its own.
- **Enforcement is server-side.** Pro-only REST routes are registered by Pro (absent in Lite) *and* their callbacks check the edition; Lite's own routes clamp ranges and windows through `Edition`. Hiding a control is a convenience, not the protection.
- **Entitlement**: Pro hooks `userflo_edition_features` / `userflo_edition_limits` and switches features on only while the licence entitles the site. No licence, expired licence, missing Lite or a licensing outage all mean "Lite behaviour": collection, stored data and privacy controls are never affected.
- **Admin UI**: Lite's React bundle (`app.js`, 22 kB gzip) has empty *slots*; Pro's bundle (`pro.js`) is loaded first and fills them (full calendar, highlights, WordPress reports, report builder, globe, contact clicks). Lite ships none of that code.
- **Data**: one schema owned by Lite (version 12). Upgrading to Pro reveals existing history; removing Pro erases nothing; Pro's uninstall removes only its own licence state.
- **Packaging**: `sh bin/build-zip.sh` builds `dist/userflo-<v>.zip` (Lite) and `dist/userflo-pro-<v>.zip` (Pro), then `bin/check-packages.sh` fails if Lite contains any Pro code or Pro contains a tracker, tables or tester licences.

## 3. Moving from beta.18
beta.19 is the first split build. Collected data is untouched and no database migration runs. Features that moved to Pro pause in Lite; their settings are preserved (saving the Lite forms never erases them) and a one-time notice lists what paused. Saved custom click selectors keep working (read-only in Lite).
