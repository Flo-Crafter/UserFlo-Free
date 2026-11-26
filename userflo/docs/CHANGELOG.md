# Changelog

All notable changes to UserFlo. Format: [Keep a Changelog](https://keepachangelog.com), versions follow SemVer (pre-releases as `-beta.N`).

## [0.46.3] - 2026-11-26 (UserFlo Pro 0.40.9, Studio 0.5.5)
### Changed
- **Load animations start when the chart is visible.** A chart, ring, bar list or table below the fold used to play its animation while still off screen, so you saw only the end. It now waits at its first frame and draws itself when it scrolls into view (about 12 % visible). Charts already on screen start at once. Animations off or reduced motion: unchanged. Applies to Lite and to the Pro screens (same classes). `playWhenVisible()` in `admin-ui/src/motion.ts`, one CSS rule (`.uf-wait`), and `dev/e2e-animate-visible.js`.

## [0.46.2] - 2026-11-24 (UserFlo Pro 0.40.9, Studio 0.5.5)
### Fixed
- **Geolocation: "Download / update the database now" did nothing visible.** The button queued a WP-Cron job and sent you to the first settings tab, where nothing showed the result. The country database (small) is now downloaded immediately, you return to **Settings → Geolocation**, and a message says whether it worked (the reason is shown on failure). The large city database still runs in the background.
- **Setup wizard after a reinstall.** The "setup finished" flag survived deleting the plugin, so a reinstall on the same site skipped the wizard and its welcome notice. Uninstall now removes it, so a fresh install opens the wizard after activation.
### Added
- **The setup wizard detects a cookie consent plugin** (Complianz, CookieYes, Cookiebot, Borlabs Cookie) and, when consent is not set up yet, switches UserFlo to wait for its choice. The Privacy step says so.
### Changed
- The plugin author is **FloCrafter** (Author URI https://flocrafter.com/); Lite's plugin site link goes to https://flocrafter.com/. Pro 0.40.9 and Studio 0.5.5 carry the same author line.
- Tests: `tests/integration/FirstRunTest.php`.

## [0.46.1] - 2026-11-24 (UserFlo Pro 0.40.8, Studio 0.5.4)
### Changed
- **Pro-only screens moved out of the free plugin's JavaScript (guideline 5, Tier 2).** The advanced visitor filters (More filters panel and chips), the visitor's visit calendar and day view, the WooCommerce sales-by-campaign report and the Overview custom date range are no longer part of the free admin bundle. They now live in Pro's bundle and attach through new slots (`VisitorFilters`, `VisitorCalendar`, `CommerceAttribution`, `CustomRange`). Free sites see the same screens as before; the free Visitors screen keeps the type filter, search, sortable columns and column menu.
- Pro 0.40.8: registers the four new slots. Update Pro together with Lite: with an older Pro the four extras are missing until Pro is updated.
- Still open from the plan (`docs/WPORG-REMOVE-FREE-LIMITS.md`): the Pro settings sections in Lite's PHP (settings page, setup wizard, sanitizer) still use `can()` checks and keep saved values untouched; moving them needs a separate, larger change.

## [0.46.0] - 2026-11-24 (UserFlo Pro 0.40.7, Studio 0.5.4)
### Changed
- **No commercial limits in the free plugin.** Earlier builds capped goals (3), exclusion rules (10), audited pages (25), report ranges (30 days), a visitor's history (10 sessions and 100 events per session), calendar months (3) and journey rows (10), and UserFlo Pro lifted the caps. WordPress.org guideline 5 treats a cap that a payment lifts as locked functionality, so all eight are now unlimited (`Edition::lite_limits()`). The Overview offers the same range buttons to everyone (24 hours to 90 days; Statistics, Pages and Map go to a year). The journey summary lists up to 100 entrances, exits and paths, the audit's daily scan cap is a plain 500 for everyone, and the free-calendar notes no longer mention months. Pro keeps what only Pro implements: custom date ranges, highlights, advanced visitor filters, the lifetime calendar, funnels and goal values, heatmaps, alerts, reports, the flow diagram and visual journey, the broader audit checks, city map and globe, and Studio.
- The limit mechanism stays in the code as a safety bound a site owner can set with the `userflo_edition_limits` filter (the alert-rule bound of 50 still applies to every edition). Tests cover both "no limit by default" and "a configured bound still works".
- Updated to match: the in-plugin Free vs Pro table, `docs/EDITIONS.md`, `docs/WPORG-SPLIT.md`, the Elements, Exclusions, Goals, Journey analytics, Visual journey and Website audit guides, the readme, the three website pages and the video scripts. Translations (Spanish, French, Hungarian) for the changed strings.
- Pro 0.40.7: rebuilt admin bundle (it shares the notes and range code).

## [0.45.3] - 2026-11-10 (UserFlo Pro 0.40.6, Studio 0.5.4)
### Changed
- **Country data is public domain.** `src/Geo/country-data.php` (names, continents, one representative point per country) was derived from the ODbL "Countries" database, which is not GPL-compatible and so not accepted in a WordPress.org plugin. `bin/build-geo-data.js` now builds it from Natural Earth's `ne_10m_admin_0_countries` (public domain) plus a short hand-written list for 11 territories Natural Earth counts inside another country (Bouvet, Cocos, Christmas Island, French Guiana, Guadeloupe, Martinique, Réunion, Svalbard and Jan Mayen, Tokelau, Mayotte, Caribbean Netherlands). Same 250 codes and the same continents; three fallback names differ (DR Congo, South Georgia, Saint Helena; translated names still come from PHP's locale data) and each point is the centre of the country's largest piece, which moved most countries by a few degrees at most. `world.json` (the outlines) is byte-for-byte unchanged. The ODbL licence file is gone; `assets/geo/NOTICE.txt` names the sources. `tests/unit/CountryDataTest.php` checks the table and that no ODbL material is shipped.
- **No placeholder links are shown.** The Support tab's Ko-fi, Buy Me a Coffee and PayPal buttons and its appeal for donations appear only when a real address is set (Links::donate() leaves placeholders out), and the "Report a bug or request" tab appears only when the developer's mailbox is real; the form itself still refuses to send while it is a placeholder.

## [0.45.2] - 2026-11-10 (UserFlo Pro 0.40.6, Studio 0.5.4)
### Changed
- Plugin Check (231 errors, 743 warnings on 0.45.1) triage. No behaviour change: the findings were queries that put UserFlo's own table names into the SQL (values are always prepared), writes to files and streams UserFlo creates itself, the MaxMind reader library, a few WordPress core hooks and constants, and some `phpcs:ignore` comments that named a different sniff than the one that fired (for example `InterpolatedNotPrepared` where Plugin Check reports `NotPrepared`). Each is now annotated with the exact code and a one-line reason; where a flagged line sits inside a multi-line SQL string the annotation is a `phpcs:disable` / `phpcs:enable` pair around the statement, never inside the string. The bundled MaxMind library gets a file-level notice instead of edits. Tested by the whole suite and by a check that no annotation sits inside a string literal.

## [0.45.1] - 2026-11-10 (UserFlo Pro 0.40.6, Studio 0.5.4)
### Added
- **Maps: double-click and Ctrl + scroll.** On the Map screen a double-click zooms in on the spot under the pointer (Shift + double-click zooms out; a double-click on a number still just opens it). Ctrl + scroll and a touch-pad pinch zoom smoothly by how far the wheel moved (at most about one and a half times per event) and no longer zoom or scroll the browser page; a plain scroll steps the zoom as before. The 3D globe (Pro) does the same: a double-click turns it to the spot under the pointer and zooms in, Shift zooms out. The globe's hint text mentions double-click.
- Tests: `admin-ui/test/zoom.test.mjs` (wheel factor, double-click factor, the globe's inverse projection) and `dev/e2e-zoom.js`.

### Fixed
- The Map screen attached its wheel listener when the world outlines loaded, but the map itself is drawn only after the visitor data has arrived as well; when the visitor data came last the listener found no map and scrolling did nothing. The listeners now follow the map element.

### Changed
- Pro 0.40.6: rebuilt globe and admin bundles.

## [0.45.0] - 2026-11-10 (UserFlo Pro 0.40.5, Studio 0.5.4)
### Added
- **Animations.** Traffic over time (every style) and the small KPI lines draw in from left to right; pie, donut and rose charts sweep round clockwise (a gentle turn-in where the browser cannot animate that); the star grows from the centre; columns, stacked bars and ranked bars grow; table rows fade in one after another (the first sixteen); on the Map screen and the Top countries map a country grows under the pointer. All of it is CSS under one class (`uf-anim`) on the app's root, so a chart redraws its animation when its style is changed and nothing runs while the page is idle.
- **Settings -> Branding and appearance -> Animations** (on by default; sites that update keep them on). Untick it and the data appears instantly. A device set to "reduce motion" never sees the animations, whatever the setting says. The calendar's day panel (which already animated) follows the same switch.
- Tests: `tests/integration/AnimationsSettingTest.php`, `admin-ui/test/motion.test.mjs`, `dev/e2e-animations.js`.

### Changed
- Pro 0.40.5: rebuilt admin bundle (it shares the chart and table components).

## [0.44.0] - 2026-11-09 (UserFlo Pro 0.40.4, Studio 0.5.4)
### Added
- **Visitors: column order.** In the Columns menu each column has a drag handle (mouse, finger or pen) and up/down buttons (keyboard and screen readers); the table follows at once. The order and the hidden columns are saved per WordPress user on the server (user meta `userflo_table_prefs`), so each administrator has their own layout and it follows them between browsers. Hidden columns chosen earlier in a browser are picked up until the layout is changed; a column added by a later update appears next to its default neighbour. **Reset to default columns** removes the saved layout. Sorting, searching, filters, paging and showing or hiding columns work as before. New route `POST/DELETE /userflo/v1/table-prefs/visitors` (the signed-in user's own layout only).
- **Visitors: country flags.** The Location column shows the national flag before the name, as an image with the text "Flag of <country>". The flags are one local SVG file (`assets/flags/flags.svg`, from the MIT-licensed country-flag-icons, built by `bin/build-flags.py`), fetched once when the page shows a flag: no outside requests, no emoji fonts, the same look in every browser and system. A missing, unknown or malformed country code shows no flag. Nothing new is collected: the country already stored with the visitor is used.
- Both are part of UserFlo itself, so they work the same without and with UserFlo Pro. Tests: `tests/integration/VisitorTableLayoutTest.php`, `admin-ui/test` (`npm test`), `dev/e2e-visitor-columns.js`.

### Changed
- Pro 0.40.4: rebuilt admin bundle (it shares the table component). The version constant `USERFLO_PRO_VERSION` now equals the plugin header again (it still said 0.39.0); it sets the cache-busting version of Pro's scripts, the version the licence client sends and the version Studio reports. A unit test (`tests/unit/VersionConsistencyTest.php`) keeps the header and constant of Lite, Pro and Studio equal.

## Tools
- **UserFlo Demo Data 0.1.0** (separate test plugin, `userflo-demo-data/`, not part of the UserFlo packages): creates synthetic visitors, sessions, page views and events from Tools -> UserFlo Demo Data and removes exactly those rows again.

## [0.43.4] - 2026-10-28 (UserFlo Pro 0.40.2, Studio 0.5.4)
### Changed (Pro 0.40.2)
- New keyholder public key: keyholder files issued before this version no longer unlock Pro; issue new ones with `dev/keyholder.php`.

### Changed
- The source-code links (Settings -> Help, readme, install and configuration guides) point to the renamed repository github.com/Flo-Crafter/UserFlo.

## [0.43.3] - 2026-10-28 (UserFlo Pro 0.40.1, Studio 0.5.4)
### Fixed
- Dark theme: the tiles in "Humans, AI crawlers and bots" had a white background and unreadable text; they now follow the theme colours.

### Changed
- The website, plugin and "Upgrade to Pro" addresses point to flocrafter.com/userflo (Lite, Pro and Studio headers and links).

## [0.43.2] - 2026-10-26 (UserFlo Pro 0.40.0)
### Added
- Overview, Top countries: a **Map** view in the cycle, the flat world map of the Map screen with each country shaded by its share and its number printed on it.

### Fixed
- Long labels (for example file addresses in "File downloaded") overlapped the numbers in the chart legends; they are now cut with an ellipsis and show the full text on hover.

## [0.43.1] - 2026-10-26 (UserFlo Pro 0.40.0)
### Added
- Overview: the title of a box is a button that cycles that box through its views. Traffic sources, Popular pages, Devices, Top countries and the Focus funnel and highlights go list, pie, donut, columns, stacked bar, rose and star (a view that does not suit the data is skipped); Traffic over time goes flowing lines, sharp lines, steps, dots, bars and candles. Each box changes only itself and remembers its view in this browser; the figures stay readable in every view.

## [0.43.0] - 2026-10-26 (UserFlo Pro 0.40.0)
### Added
- **Tools → Database & Storage.** Total, data, index and free space of UserFlo's tables (measured by the database, refreshed daily and on request, never while the page loads), row counts labelled as estimates, a table inventory with each table's purpose, the retention setting, the last successful cleanup and aggregation, any outstanding problem, a growth estimate (only from at least five daily measurements over a week) and an explanation of what is deleted and what stays.
- Retention profiles Minimal (30 days), Standard (90), Long (365), Custom and Keep forever. The current setting is the one selected; nothing changes until Save, and shortening the period shows how many rows become eligible and asks for confirmation.
- Manual actions with confirmation: run the cleanup now, delete detailed data older than a date (at least 7 days ago, in batches, resumable after leaving the page), rebuild daily summaries for a range of days (days whose detail is gone are skipped, their summaries are kept), and rebuild a single table to give back free space.
- Optional shorter period for clicks and scroll events (Settings → Privacy, off by default).
- Daily summaries now also keep goal completions (with their value), sales per currency (orders and revenue), interaction counts per type and custom events by name, so conversion and sales history survives the retention period. New column `uf_summary.amount` (schema 20); days summarised before are written again once, while their detail still exists.
- Guide **Database & Storage**, `docs/DATABASE-AUDIT.md`, `docs/benchmarks/storage-retention.md`, benchmark scripts `dev/bench-storage-*`.

### Changed
- Daily summaries are written all-or-nothing (one transaction, the `total` row last), written again once the late-arrival period of the two latest days has passed, and every day is checked against the stored visits before its detailed rows can be deleted. A day that cannot be summarised keeps its detail, and the problem is shown.
- The cleanup takes a lock (no two runs at once), stores its state (last run, last success, last error, open job), records errors in the plugin log, and a lost WP-Cron event is queued again from the admin when the last run is over 36 hours old.
- Removed two indexes that a longer index already provides (`sessions.started_at`, `pageviews.viewed_at`): about 5 % less space, cheaper inserts, reads unchanged within measurement noise. To undo: `ADD INDEX`.
- The Tools status table shows estimated row counts from the stored measurement instead of counting every table when the page opens.

### Fixed
- "Delete all data on uninstall" now also drops `uf_summary`.
- Resetting all data also clears the retention and summary state.

## [0.42.0] - 2026-10-26 (UserFlo Pro 0.40.0)
### Added
- **Accessibility checks in Page Intelligence** (WCAG 2.2, levels A and AA). They reuse the website audit: the same page fetch (this site only, as a visitor), the same parser, findings table, exclusions and screens. No new table, no new dependency and no extra network request.
  - Every result says how sure it is: **Issue detected** (a specific problem read from the HTML), **Potential issue** (a pattern that cannot be confirmed from HTML) or **Manual review required**. Each check also records, per scan, what it tested, so a page can show **Passed automated check**, **Not tested** or **Nothing to test**. Nothing untested is shown as passed, and no result claims WCAG conformance or gives a score.
  - **UserFlo (free):** missing alt text, links and buttons without a name, form fields without labels, missing page language (and the page title, WCAG 2.4.2), plus an Accessibility overview on Page health with WCAG references and the list of things only a person can check.
  - **Pro:** invalid language, file-name alt text, decorative images with a title, empty headings, no main landmark, duplicate IDs used by labels or ARIA, missing label or ARIA targets, unknown ARIA roles, aria-hidden on focusable content, positive tabindex, removed focus outline (inline CSS and style blocks only), zoom blocked by the viewport tag, frames without a title, clickable elements that are not controls, links acting as buttons, required fields not marked, e-mail and telephone fields without autocomplete, media without captions or embedded players (manual review), autoplay, and text contrast calculated from inline colours only (always a potential issue; style sheets, images and overlays are never guessed). A page report with findings by certainty, passed and untested checks and the manual-review list; accessibility counts in the Audit table and its CSV export; accessibility issues on busy pages, or on checkout, login, contact and booking pages however few visit them, in "Pages that need attention" (traffic only orders the list, it never changes severity).
  - New guide **Accessibility checks** (Settings → Help) with the checks, WCAG references, limits and remedies.
- Findings now carry the WCAG criteria, the certainty and a "when it can be wrong" note; the finding details show them with the first and last seen dates.

### Changed
- Four findings that were Pro-only (image without alt, link without a name, button without a name, form fields without labels) are now made by the free plugin, because they are factual and reliable. They are no longer repeated by Pro.
- Pages scanned before this release, or scanned in the free version before an upgrade to Pro, are read again once.

### Fixed
- A submit or reset button without a value, and an image button with alt text, were reported as having no name.

## [0.41.7] - 2026-10-26
### Changed
- The UserFlo toolbar menu has two tabs above the events, **All history** and **This page**, which also work when the menu's script is blocked, and one row of short links: Live, Map, Visitors, Page info, Elements (and Audit with the dot, with Pro). The Settings link was removed.

## [0.41.6] - 2026-10-26
### Changed
- The UserFlo toolbar menu is more compact: one panel with the latest activity in a short, scrolling list with tight rows, and the quick links (and Settings) in a footer row, instead of a full-height column of links above a long history.

## [0.41.5] - 2026-10-26
### Added
- **Toolbar menu parts**: Settings → Privacy has a tick box each for the quick links (with the Pro audit dot) and the latest activity in the UserFlo toolbar menu. With both unticked the menu is not shown; with only the links the menu loads no script.

## [0.41.4] - 2026-10-26
### Added
- **Show or hide the screen buttons**: Settings → Branding and appearance has a tick box for each of the Guide, Copy for AI (Pro) and Export CSV buttons at the top of the screens. Each can be hidden on its own or all together; all three stay on by default.

## [0.41.3] - 2026-10-26
### Changed
- **The toolbar History menu is now the UserFlo menu.** It shows quick links (Live view, Map, Latest visitors, This page in Pages, Elements on this page) and the latest ten visits and interactions, both written into the page by the server, so they appear even when an optimisation plugin blocks or delays the menu's script (which then upgrades the panel with tabs and Reload). With Pro, a dot next to "UserFlo" shows the audit health of the page you are on, with a Page audit link.

## [0.41.2] - 2026-10-26
### Fixed
- The History menu in the WordPress toolbar could stay on "Loading…" for ever when its script was blocked, delayed or combined by an optimisation plugin, or when the request never answered. Without JavaScript it now shows a link to the Live view, and a request that does not answer within 20 seconds ends with "The history could not be loaded." (with Reload).

## Launch release set - 2026-10-25
Released together on `main`: **UserFlo – Visitor Tracking & Analytics** (free) **0.41.1**, **UserFlo Pro 0.39.0** (requires the free plugin 0.41.1 or newer) and **UserFlo Studio 0.5.3** (unchanged since its 0.5.3 release). Database schema version 19. Since the 0.38 series the free plugin gained the WordPress.org readiness work, Export CSV on the main screens, the Mono and Noir styles, per-screen Guide buttons and the new name; Pro gained the Audit table, the Statistics Builder, Copy for AI and AI Personas. Entries for each step follow.

## [0.41.1] - 2026-10-25 (UserFlo Pro 0.39.0)
### Added
- **Copy for AI** (Pro): a button next to Guide on Overview, Visitors, Journeys, Statistics, Map, Live, Pages, Calendar, Goals, Elements and Audit. It opens a window with the persona, task and data that suit the screen already chosen (all changeable), builds one counters-only block of text and copies it for any AI chat.
- **Settings → AI Personas** (Pro): edit the built-in personas, add your own and describe your website.

### Changed
- The AI screen is gone from the sidebar, together with Connect, Example questions and Log and preview. The live connection (AI accounts, MCP endpoint, ChatGPT plugin ZIP, call log) is built and tested but switched off unless the `userflo_ai_live` filter turns it on; existing AI accounts stop signing in.
- The Pro module is now called Copy for AI.

## [0.41.0] - 2026-10-24 (UserFlo Pro 0.38.2)
### Changed
- **New name for the free plugin: "UserFlo – Visitor Tracking & Analytics"** (the folder, text domain and code names are unchanged). Everything on screen and in the guides that said "UserFlo Lite" or "Lite" now says "the free version", "UserFlo (free)" or "Free vs Pro". Developer documentation and this changelog keep the older wording.
- Plugin description and readme summary rewritten around visitor tracking and analytics.

## [0.40.3] - 2026-10-24 (UserFlo Pro 0.38.1)
### Added
- **Guide** button next to Export CSV in the top bar: opens the guide for the screen you are on (for example Journeys opens Journey analytics) in a dialog, without leaving the page. Pro adds a guide for AI access.

### Changed
- Screen titles no longer repeat the name: the logo bar already says UserFlo, so the titles read just "Goals", "Journeys", "Settings" and so on.

### Fixed
- **Export CSV button** now sits in the UserFlo bar next to the distraction-free button instead of on top of it.
- **Pro: "Open in Elements"** on a page's audit opened a WordPress "not allowed to access this page" error (the link overwrote the screen's own `page` parameter). It now opens Elements filtered to that page.

## [0.40.2] - 2026-10-24
Database schema 19 (new table `uf_ai_log`, used by UserFlo Pro's AI access).

### UserFlo Pro 0.38.0 (needs this version)
- **AI access:** connect ChatGPT or Claude to counters-only summaries of the site through limited, revocable accounts (all packs for a master account, or chosen packs), with editable personas, an "about the site" text, example questions, a help call, a preview of exactly what leaves the site and a call log. See `docs/AI-ACCESS.md`. 0.37.1 adds a generated ChatGPT plugin (ZIP) for the site, because OpenAI retires custom GPTs on 11 December 2026, and accepts the sign-in header on hosts that do not pass it to PHP. 0.37.2: the ChatGPT plugin ZIP now has its files at the root, the skill says plainly that failed tool calls must be reported and figures never invented, a refused sign-in answers with a `WWW-Authenticate: Basic` challenge, and the key is also accepted as a bearer token. 0.37.3: an AI account is recognised by its stored pack list as well as its role and has no capabilities whatever role another plugin gives it (so it is listed and locked down on every site); a "Check my site" button signs in to the site's own AI address with a temporary account and shows which step fails; refused sign-in attempts are noted in the log. 0.37.4: the AI screens ask for live data (a cache plugin or CDN can no longer hand back an old accounts list), a new account appears in the list straight away, and AI answers are marked `no-store`. 0.38.0: **Copy for any AI** (UserFlo → AI): builds one block of text (persona, your site description, rules, task, your question and the chosen packs' counters-only data) to paste into any chat on any plan, with its size shown; the ChatGPT instructions now say a live connection needs a plan that allows custom MCP servers.

### Fixed
- **Upgrades from 0.38 and earlier did not add new database columns.** The migration only re-read the schema when upgrading from before version 17, so the `metrics` column added in 0.40.0 (and the new AI log table) were missing on sites that upgraded rather than installed fresh, and a page scan could not be saved. Every schema upgrade now adds what is missing. Test sites upgraded to 0.40.0 or 0.40.1 are repaired when 0.40.2 is activated.

## [0.40.1] - 2026-10-24
### Added
- Two minimalist grayscale dashboard styles with smooth, rounded edges: **Mono** (white) and **Noir** (black). Choose them in Settings or the setup wizard. Checked for colour contrast on the Overview, Statistics and Pages screens.

## [0.40.0] - 2026-10-24
Database schema 18 (new column `metrics` on the audit pages table). UserFlo Pro 0.36.0 adds the Audit screen and requires this version.

### Added
- **Edit layout** on the Overview and on Statistics: a wireframe view of the page (one small tile per block) where blocks are dragged, or moved with the arrow keys, into a new order and hidden or shown. The layout is remembered in this browser. The Overview now follows the same layout.
- **Export CSV** on Visitors, Journeys, Statistics, Pages, Calendar, Goals and Elements (and Audit in Pro): a dialog with the date range and the columns to include. Cells that start with = + - or @ are escaped so a spreadsheet never runs them.
- Each box on the Overview links to its section: Popular pages and What to look at → Pages, Top countries → Map, Goals → Goals, Recent sessions → Visitors, Traffic sources, Devices and bots → Statistics.
- The audit scan now keeps counts for every page (headings H1–H6, internal and external links, images, images without alt text or size, buttons, forms, title and description length).

### Changed
- The Devices donut on the Overview is larger.
- On Pages, the word "Issues" (and the issue count on Page health) opens that page's audit.
- Pages scanned before this version are read again once so their counts are filled in.

## [0.39.0] - 2026-10-23
**Lite prepared for WordPress.org.** Lite no longer contains locked Pro code: where a feature has a free limit it stays in Lite with that limit, everything else lives only in UserFlo Pro (0.35.0, which now requires this version).

### Changed
- Goals beyond page/event goals (amount, time frame, day/time, more/less than, rebuild, funnels, targets), the full journey analysis and visual journey, the weekly/hourly traffic calendar, combined campaign groups and attribution, advanced visitor filters, city-level geolocation, company branding and custom date ranges are implemented only in UserFlo Pro. Lite exposes filters for them (see `docs/WPORG-SPLIT.md`).
- The "powered by UserFlo" credit on shared pages is now opt-in (off by default).
- readme.txt rewritten: External services (db-ip.com location database download), cookies and privacy, FAQ.
- Escaping and asset enqueueing tightened for the shared page.

## [0.38.2] - 2026-10-23
### Fixed
- **Sessions that never ended.** A page left open in a tab kept its session id in memory and every tab switch extended the same session (one showed 25 hours and "Active"). The tracker now treats a return after the inactivity time as a new visit and does not extend the old session when the tab is hidden after a long idle time. The server also starts a follow-on session when a message arrives for a session that has been idle longer than the inactivity time, so pages cached with the old tracker are corrected too (the follow-on ids are derived from the old ones, so retries never make duplicates).
- **Logged-in sessions after an automatic logout.** A visit that began with a login and later arrives without a signed-in user is recorded as logged out. UserFlo also listens for the cookie being cleared, which inactivity-logout plugins often do without calling the normal logout.
- The Devices chart on the Overview scales with its card.

## [0.38.1] - 2026-10-22
### Added
- **Open WordPress profile** button on a logged-in visitor's page. Setting: hide it, or open in the same or a new tab (default new tab). Shown only to people allowed to edit that user and only when usernames are shown in the dashboard.

## [0.38.0] - 2026-10-22
**Daily summaries that outlive the detailed data, and a comparison with last year.** Database schema 17 (new table `uf_summary`).

### Added
- **Daily summaries:** when a local day is over, UserFlo writes one row of counts for it (totals, and the top pages, sources, countries, devices and channels). Counts only: no visitor, session, IP address or user agent, so keeping them for years does not keep personal data. The first run after the update summarises the days already recorded, in small batches.
- **Keep daily summaries for (Settings → Privacy):** 0 to 10 years, default 3. 0 switches summaries off and deletes them. The detailed data keeps its own retention period (default 180 days).
- **Compared with last year (Statistics):** the chosen period next to the same dates a year earlier (sessions, page views, visitors, new visitors, and the top pages, sources, countries and devices), plus a column per summarised month. It says when last year is only partly covered and never guesses; summaries start on the day this version first ran, so the first comparison appears a year later.
- **Download as CSV (Tools → Daily summaries).**
- The suggested privacy-policy text mentions the daily summaries.

### Changed
- The retention cleanup summarises finished days first and never deletes the detailed rows of a day that is not summarised yet.

## [0.37.1] - 2026-10-21
### Added
- A short **"Saved"** cue floats up in the middle of the screen and fades out after settings are saved (also when a screen shows a success notice). Respects reduced motion.

## [0.37.0] - 2026-10-21
**Goals: amounts, time frames, targets, and goals and funnels in reports.** Database schema 16 (new columns on the goals table; existing goals behave as before). Pro 0.34.0 requires Lite 0.37.0.

### Added
- **Amount per session (every edition):** a goal can need an amount, for example "open this page 3 times in one session". A session completes it once it has matched that many times; history re-reading and funnels follow the same rule.
- **Time frame (Pro):** a goal only counts between a start and an end day (site time zone).
- **Finish within (Pro):** the goal counts only if it is completed within a number of minutes of the session starting (with an amount, all the times must fall inside it).
- **Targets for a weekday and time window (Pro):** count page views or events, the sessions they belong to, or distinct visitors in a weekly window (chosen weekdays and a time of day) and compare with a number: *more than*, *at least*, *less than* or *at most*. "On Tuesdays more than 500 downloads", "every evening 18:00-21:00 fewer than 200 visitors". Each occurrence is judged on its own ("met in 5 of 8 windows", with the count of each), windows still running are not judged, windows before UserFlo had data say so, and the newest 60 are judged. Read from the stored data when asked; nothing is added at ingest. New Targets card on the Goals screen.
- **Goals, funnels and targets in reports:** the Reports screen (PNG/PDF) gets *Goals*, *Funnels* (Pro) and *Targets* (Pro) sections, and the e-mail report gets *Funnels* and *Targets* next to *Goals* (Pro).
- Help guide: amounts, time frames and targets.

### Notes
- A window that crosses midnight needs two goals. A funnel step that needs an amount is reached at the last match counted.

## [0.36.0] - 2026-10-21
**Which AI, and visitors sent by AI assistants.** No database change.

### Added
- **Which AI crawled your site**: crawler visits are grouped by assistant (ChatGPT / OpenAI, Claude / Anthropic, Perplexity, Gemini / Google AI, Meta AI, Apple, Amazon, ByteDance, Mistral, Cohere, DuckDuckGo AI, You.com, Common Crawl), switchable to *By crawler*, where each crawler says what it does: collects pages for training, builds an answer-search index, or fetches a page because someone just asked. Names are the ones the vendors document (OpenAI: GPTBot, OAI-SearchBot, ChatGPT-User; Anthropic: ClaudeBot, Claude-SearchBot, Claude-User; Perplexity: PerplexityBot, Perplexity-User). Crawlers whose names are not documented (DeepSeek, Grok/xAI, Qwen, Kimi) are not guessed: add them under Settings → *More AI crawlers to recognise* (`DeepSeekBot = DeepSeek`).
- **Visitors sent by AI assistants** (Overview tile, Statistics section): visits whose referrer is an assistant's own site or whose `utm_source` names one (ChatGPT tags its links with `utm_source=chatgpt.com`): ChatGPT, Claude, Perplexity, Gemini app, Copilot, Grok, DeepSeek, Mistral, Meta AI, Qwen, Kimi, Poe, You.com, Phind, DuckDuckGo AI. The list is a starter list; add your own under Settings → *More AI assistants that send visitors*.
- **Limits, stated on screen:** a minimum, not an exact count. Visits from Google's AI Overviews and AI Mode look like ordinary Google traffic and cannot be separated; apps that hide the referrer, and copied links, show up as direct visits; crawler names can be faked.

## [0.35.0] - 2026-10-21
**Humans, AI crawlers and bots.** Database schema 15 (one counters table). Pro 0.33.0 keeps working unchanged.

### Added
- **Humans, AI crawlers and bots** on the *Overview* and *Statistics*: four tiles (human visitors, AI crawlers, search engines, other bots), a bar comparing page loads, and on Statistics which bots came and the pages AI crawlers requested most (as bars, donut, pie or columns).
- **Highlight AI and bot visits** on *Pages*: a switch (on by default, remembered in this browser) that marks each page with AI, search and bot request counts, tints pages AI crawlers requested, and adds pages only bots requested. A second switch shows only those pages. The toolbar History menu shows the bot counts for the page you are on.
- **How it counts:** most bots never run JavaScript, so the requests are counted on the server (`template_redirect`), as one daily counter per bot, page (without query string) and kind: no IP address, no full user agent, no cookie. About 70 AI, search and other bots are recognised by name (GPTBot, ClaudeBot, PerplexityBot, Bytespider, CCBot, Googlebot, Bingbot, AhrefsBot, …); everything else that looks like a bot is "Other bots". At most 500 pages per bot per day get their own line; the rest share one. Follows the data retention period, the path exclusions and the "delete all analytics data" tool. New setting *Count visits by AI crawlers and other bots* (on by default).
- Limits stated on screen: bots are recognised by the name they give themselves, which can be faked, and pages served from a full-page cache never reach PHP and are not counted, so bot numbers are a minimum.

## [0.34.0] - 2026-10-21
**History menu in the toolbar and peak times.** No database change. Pro 0.33.0 keeps working unchanged.

### Added
- **History in the WordPress toolbar** (Lite, needs the Live view component): on your site's pages, people who may see the analytics get a *History* menu with two tabs, *All history* and *This page*: the latest visits and interactions of the last 7 days (who, what, how long ago), a one-line total, and links to the visitor, the page in *Pages*, *Elements* filtered to the page, the Live view and Settings. It loads nothing until opened, reads through the Live view's own route and permission (`GET /userflo/v1/history`), shows names only as the "show usernames" setting allows, never returns addresses, e-mails or link values, and can be switched off in Settings → Privacy. Opening it is not counted as a visitor interaction.
- **Peak times and peak days** on *Statistics*: a weekday-by-hour heatmap and column charts by day and by hour, each for everyone, anonymous or logged-in sessions, with the busiest day and hour for each audience. Sessions are counted in the hour they began, in the site's time zone (`GET /userflo/v1/stats/peaks`).
- Elements accepts `&for=/path` to open already filtered to a page.

## [0.33.0] - 2026-10-21
**Feedback round: charts, notes, heatmap detail, Alerts clarity, Studio 0.5.0.** No database change in Lite or Pro. Pro now requires Lite 0.33.0 or newer (shared styles).

### Added
- **More ways to see data** (Lite): every panel that shows a split can be viewed as bars, donut, pie, star (radar), rose, columns or a stacked bar, remembered per panel in this browser. New panels on *Statistics*: devices and browsers as rings, *New and returning visitors*, *Logged-in and anonymous sessions*, *Single-page and multi-page sessions*.
- **Reports: show as a list or a chart** (Pro): each list in *Data to include* (pages, sources, buttons, links, devices, forms, focus) can be drawn as a list, donut, pie, star, rose or column chart in the preview, PNG and PDF. Statistics Builder rankings offer the same chart choices (the shared page keeps bars).
- **Elements: your own names and notes**: rename an element or add a note so several buttons with the same label ("Downloads") can be told apart; names and notes are searchable. Stored per element (display only, administrators only, up to 500). A *Show technical details* switch hides the selector line (remembered in this browser).
- **Heatmaps**: pointing at a spot shows its clicks, share, rank and position; clicking it opens a detail pop-up; each summary figure (recorded clicks, page views, clicks per 100 views, scroll reach) opens a pop-up with the data behind it; the legend now says what the colours mean.
- **Alerts**: a *Create rule* button is always shown, with a short explanation and a first step that describes the three kinds of rule. The destinations list shows the result of the latest test (delivered or failed, with a plain-language reason), the test button shows *Sending…*, and the result message is placed at the top and announced to screen readers.

### Changed
- Sidebar order: Overview, Visitors, Journeys, then Statistics, Map, Live and the rest.
- **UserFlo Studio 0.5.0**: UserFlo look with five styles and a Settings screen (defaults, changelog, logs), clients (renamed from groups) with contact details, *Edit connection* link, sortable columns with newest-first default, tabs on the site page, and its own menu address (it no longer shows UserFlo Pro's Studio screen underneath).

### Fixed
- Studio: the UserFlo Pro "Studio" settings screen was rendered under Studio's own sites table when both plugins were active on one site (same page address).

## [0.32.0] - 2026-10-20
**Statistics Builder (Pro).** Database schema 14 (one configuration table). Pro now requires Lite 0.32.0 or newer. User guide: *Statistics Builder (Pro)* in Help; audit and plan in `docs/phase6/01-statistics-builder-audit-and-plan.md`.

### Added
- **Statistics Builder** (new Pro module `dashboards`): several named dashboards, each with its own widgets, layout, period and comparison. A *Dashboard* switcher on the Statistics page (the standard page is always there; the default dashboard opens first), *Manage dashboards* (create, rename, describe, duplicate, set as default, delete with confirmation) and an editor with a searchable, categorised widget library, drag-and-drop and keyboard-operable move buttons, five widths, per-widget settings, dashboard-wide filters, preview, save, cancel and reset. Two people saving the same dashboard cannot overwrite each other silently (revision check).
- **Widget library** with an extensible registry (filter `userflo_dashboard_widgets`): key figures with comparison, traffic over time, most viewed pages, entry and exit pages, traffic sources, devices and browsers, countries, campaigns, interactions, common journeys, audience split, goals, shop performance and a website audit summary. Widgets reuse the existing statistics services (same numbers, Exclusions and edition limits); one that cannot show data says why instead of showing zeros.
- **Share one dashboard**: the Sharing screen offers *One dashboard* (and the dashboard's *Share…* button preselects it). Same expiry, revoke, rate limit, no-store and privacy rules as other links; the shared page is read only, shows only that dashboard, and the server withholds widgets that must not be shared. Renaming keeps links working, a duplicate has none, deleting a dashboard removes its links. Existing statistics, live and data links are unchanged.
- Lite: schema 14 table `uf_dashboards` (kept by "Delete all analytics data"; dropped on uninstall with "delete on uninstall"), edition feature `statistics_builder`, module entry, share filters, and an empty `StatsDashboards` slot; Lite's Statistics page is unchanged.
- **French (fr_FR)** is now bundled next to Spanish and Hungarian: every string (about 2,700) in the plugin screens, the scripts, reports and the shared pages; a WordPress site in `fr_FR`, `fr_CA`, `fr_BE` or `fr_CH` picks it automatically, and it can be chosen in Settings and for exported reports. Like the other languages it is a machine translation awaiting native review. The “custom language” examples now suggest `it_IT` instead of `fr_FR`.
- `dev/e2e-dashboards.js` (Chromium: create, edit, drag, preview, save, cancel, default, share, shared page without login, revoke, delete; axe-core WCAG 2 A/AA, desktop and 375 px, no sideways scroll).

### Fixed
- Pro: the **E-mail reports** settings screen did not render (a script error since 0.28.0 left the form empty). Found while checking the French translation in a real browser; `dev/e2e-mail.js` passes again except its known 390 px sideways-scroll check.

### Notes
- Not offered because the data does not exist: tracking-health and video-engagement widgets, page/audience filters.
- Dashboards are site-wide (any administrator can edit them).

## [0.31.0] - 2026-10-19
**Alerts (Pro).** Database schema 13 (three configuration tables). Pro now requires Lite 0.31.0 or newer (it needs the new tables, the `alerts` edition feature and the `userflo_ingested` hook). User guide: *Alerts (Pro)* in Help (`userflo-pro/docs/ALERTS.md`).

### Added
- **Alerts** (new Pro module `alerts`, UserFlo → Alerts): three rule types and two destination types.
  - *Milestones*: every N additional visitors, sessions or page views, **counted from when the rule is created** (the counter starts at the current total); one alert per milestone even under concurrent requests (the crossing is a single conditional `UPDATE`), pausing freezes the counter, a jump over several steps sends one alert for the highest.
  - *Events*: a goal is completed, a **form** is sent (by the builder's stable form id such as `wpforms:12`, with a backwards-compatible name fallback), a file is downloaded, a custom event happens; a pause between alerts (default 5 minutes) and a cap of 60 alerts an hour per rule.
  - *Daily thresholds*: fewer or more than N visitors, sessions or page views on a day, judged after local midnight plus 30 minutes in the site's time zone, only for days with complete data coverage (no alert when tracking had not begun, no separate "no data" alert), first day of a streak or every day, at most three days of catch-up, no back-fill after a pause.
  - *Destinations*: e-mail (up to ten recipients) and a generic **HTTPS webhook** (JSON `userflo.alert.v1`, optional HMAC-SHA256 signature headers). Webhook addresses are stored encrypted and never shown again.
  - *Delivery*: asynchronous (the collector only writes a queue row), exactly once per rule, occurrence and destination (unique key), retries after 1 minute, 10 minutes and 1 hour then *failed* with a manual retry, stale-claim take-over, **30-day / 1,000-row delivery log**, test sends limited to one per destination every 10 seconds and six a minute.
  - *Limits*: 50 active rules per site (edition limit `alert_rules`; Pro keeps it), 20 destinations.
- Lite: the collector fires `userflo_ingested` after a payload is stored (summary, path, events, page-view id) for add-ons; schema 13 tables `uf_alert_rules`, `uf_alert_state`, `uf_alert_deliveries` (kept by "Delete all analytics data"; dropped on uninstall only with "delete on uninstall"; destinations always removed); edition feature `alerts` (Pro only); module registry entry.
- `dev/bench-alerts.py` + `dev/alerts-setup.php`: live concurrency run against a real server (about 500 beacons, 32 parallel, each sent twice).
- Spanish and Hungarian strings for the Alerts screen and messages (machine translations, native review pending).

### Security
- Webhook boundary: https only on port 443, host names only (no IP literals, `localhost`, single-label or `.local` names), no credentials or fragments, re-validated at every send, host resolved and refused if **any** answer is private, loopback, link-local, CGNAT, reserved, multicast or tunnelled, connection pinned to the checked addresses, `wp_safe_remote_post`, redirects never followed, 8-second timeout, 64 KB response limit, response bodies never stored. **This is not complete SSRF protection**: see *Webhook security* in the guide (pinning needs the cURL transport; trusted resolver). Review again before wide public release.

### Notes
- The Alerts screen is server-rendered (plain forms with a nonce per action) instead of the React/REST screen sketched in the audit; no bundle changes.
- Not included (by decision): Telegram and other provider channels, recovery alerts, tracking-health alerts, achievement alerts and any stored achievement-unlock records.

## [0.30.2] - 2026-10-19
Security release for the Studio connection; full audit in `docs/phase5/07-security-and-release-audit.md`. Pro requires Lite 0.29.0 or newer (unchanged).

### Security
- Pro: the replay guard for signed Studio requests is now one atomic insert (two identical requests arriving together can no longer both pass); old rows are purged and removed on uninstall.
- Pro: the one-time pairing code is shown only in the response that creates it and is no longer kept in the database in clear (only its hash is).
- Pro: WordPress passes the page hook to menu callbacks; the pairing page now copes with that (found in the real browser run).

### Added
- **UserFlo Studio 0.4.1 — security hardening** (guide section *Security, privacy and troubleshooting*): before every request the client host is resolved and refused if any answer is private, loopback, link-local, CGNAT, reserved, multicast or tunnelled, and the connection is pinned to the checked addresses (DNS rebinding); a pairing code may only point at the Studio routes of the entered site; the same website can no longer be added twice under another spelling of its address (no double counting); reconnecting a live site is refused and a failing site's old credential is revoked first; two accessibility fixes found with axe in a real browser; uninstall removes transients.
- `dev/e2e-studio.js` (real browser run over two real sites, axe-core, phone width) and 10 more package checks (72 in all): boundaries between the three packages, direct-access guard in every source file, no development files or secrets.

## [Unreleased]

## [Unreleased]
### Added
- **UserFlo Studio 0.4.0 — scheduled reports** (database schema 4: `uf_studio_schedules`, `uf_studio_deliveries`; guide section *Scheduled reports* in `userflo-studio/STUDIO.md`). Monthly (default) or weekly schedules for a client's sites or a single site, with a validated day, time and delivery time zone, up to ten validated recipients, an optional PDF/CSV attachment and a policy for incomplete data. One hourly cron event serves all schedules; each period is claimed in a table unique on (schedule, period) so retries, overlapping ticks and manual retries can never send it twice; failed sends retry after 15 min, 1 h and 4 h reusing the same stored report, then wait for a manual retry. Reports come from synced data only; nothing is sent when no site has current figures, and incomplete reports are either marked “incomplete data” or held. Pause, edit, send a test to yourself, retry and delete; delivery status, last/next run and safe failure reasons are shown. Separate permissions to view and manage schedules and to add external recipients; client separation is re-checked at send time; the full-report link needs a sign-in; the log holds codes only. No new dependency.
- **UserFlo Studio 0.3.0 — client reports** (database schema 3: `uf_studio_reports`; guide section *Client reports* in `userflo-studio/STUDIO.md`). Generate a report for one client's sites (never mixing clients) or one site, for the previous calendar month, month to date, 30/90 days or a custom range inside the 92 days Studio keeps; built from the synced figures with no request to any client site; stored as a frozen snapshot so preview, PDF, CSV and the printable page show identical figures; period, time zone, generation date, source sites, freshness, missing days, sites disconnected during the period and incomparable settings are labelled; traffic sources, popular pages and conversions are listed as unavailable (not synced yet). History with 365-day retention, separate permissions to view, generate, export and delete, a nonce on every action, escaped output, cross-client leakage tests. The PDF writer is a copy of Pro's dependency-free one (no new dependency).

## [0.30.1] - 2026-10-19
### Fixed
- Pro: the Studio connection now verifies signed requests on sites without pretty permalinks (WordPress carries the route in `rest_route`, which the central site does not sign). Studio 0.2.0 requires Pro 0.30.1.

### Added
- **UserFlo Studio 0.2.0 — sync, cache and portfolio** (database schema 2: `uf_studio_daily`, `uf_studio_sync`; guide `userflo-studio/STUDIO.md`). An hourly WP-Cron event refreshes the sites that are due (every 6 hours, at most 3 per run, 40 s budget); counts per site and local day are cached centrally with the site's time zone, definition version and sync time; idempotent upserts; a per-site lock prevents overlapping syncs; failures retry after 15 min, 1 h, 4 h and 12 h and then mark the site *Needs attention* (a rejected credential is not retried); a changed client revision triggers one full re-read. The dashboard shows a date range (up to 92 days), per-site figures with *Up to date* / *Not current* / *No figures yet*, retry state, a portfolio total over compatible sites only (visitors are never added; averages are totals over totals; mixed time zones or settings are called out; excluded sites are listed with the reason), and manual refresh (per site once a minute, all once in five minutes). Pause and disconnect stop syncing; disconnect keeps the figures 30 days labelled not current; figures older than 400 days and figures of deleted sites are removed daily; delete record removes everything.

## [0.30.0] - 2026-10-19
Pro requires Lite 0.29.0 or newer (unchanged).

### Added
- **UserFlo Studio 0.1.0 — the central plugin** (new folder `userflo-studio/`, new package `userflo-studio-<version>.zip`, separate download included with Pro; guide `userflo-studio/STUDIO.md`). Runs on the agency's own site; does not need UserFlo there and has no tracker. Add a client site with its address and a pairing code (validated: https, code belongs to that website, the answering site is the one entered, supported connection version, Pro 0.30.0+, signed request works, otherwise nothing is saved and the client's connection is undone); site name, address, client/group, notes, status and last successful contact are stored; **test connection**, **pause / resume sync**, **replace credential**, **disconnect** (asks the client to revoke, deletes Studio's key, keeps the record marked *not current*), **reconnect** with a new code and **delete record** (asks the client to revoke first, explicit confirmation). Groups can be created, renamed and deleted; deleting a group moves its sites to “No client”. Dashboard with empty, warning and error states, identity banner (“You are managing from …”), keyboard-friendly server-rendered forms. Private keys are encrypted at rest; messages are fixed texts, so remote answers, keys and codes never reach the screen or the log.
- Pro: signed `POST /disconnect` and `POST /rotate` on the Studio connection so Studio can revoke and replace its credential (documented in `userflo-pro/docs/STUDIO-API.md`).
- Package checks cover the Studio package (62 checks).

### Notes
- Version 0.1 of Studio shows no analytics yet; figures and reports arrive in the next phases. Studio is English only for now.

## [0.29.0] - 2026-10-19
Pro requires Lite 0.29.0 or newer (new edition feature `studio_remote`).

### Added
- **Studio connection** (Pro; new module *UserFlo Studio connection*, Settings → Studio; contract in `userflo-pro/docs/STUDIO-API.md`). A central UserFlo Studio site you pair with can read **summary figures only**: totals, daily numbers, top pages (path and title) and audit counts, through the versioned, read-only API `userflo-studio/v1` (`/hello`, `/sync-status`, `/summary`, `/pair`). No cookie, no WordPress login, no capability: requests carry an Ed25519 signature checked against the public key stored at pairing (a one-time code, 15 minutes, only its hash is stored). Timestamp window, single-use nonces, API-version negotiation, input validation (dates, 92-day cap, fixed metric list, timezone, page limit), rate limits per connection and per address, short answer cache, generic 401 for every kind of bad credential, log entries without secrets. Revoke at once from Settings → Studio; uninstall removes all connections. The page also links to the Studio download (placeholder address until the company website is known; filter `userflo_studio_download_url`).
- Every response carries the site's *definition* (timezone, consent and IP mode, retention, active exclusions) and a `data_revision`, so a central site can tell when two sites are not directly comparable and when history must be re-read.

### Notes
- The central Studio plugin itself is a later phase; nothing is sent anywhere by this release. The API is inert until an administrator creates a pairing code.
- Visitors are distinct browsers per site and period and are not additive across sites; the API documentation says so.

## [0.28.0] - 2026-10-19
Pro requires Lite 0.28.0 or newer (the attachments reuse a new option of Lite's data feeds).

### Added
- **CSV attachments on scheduled e-mail reports** (Pro; Settings → Email reports → *CSV attachments*). Tick up to six of the summary data sets (summary, daily, pages, …) and each scheduled, retried or "send now" report carries them as CSV files for **exactly the same period as the e-mail** (site timezone). Summary figures only, never visitor-level rows; spreadsheet formulas are neutralised; total size capped at 2 MiB; files live in a random temporary folder only while the message is sent. Off by default.
- `Feed::rows()` accepts an explicit period (internal; share links and the Excel download still use "the last N days").
- Tests: failed export jobs (visible, free their slot, expire), site-timezone ranges across a day and a clock change, attachments, header-injection in recipients.

### Notes
- Phase 4 / 01 inventory: `docs/phase4/01-export-inventory-and-gap-analysis.md`. The existing exports (background CSV/JSON jobs, feeds, workbook, PDF, e-mail reports) were found complete and were left unchanged.

## [0.27.0] - 2026-10-19
Pro requires Lite 0.27.0 or newer (Pro adds its findings and screens to Lite's audit).

### Added
- **Website audit** (database schema 12: `uf_audit_pages`, `uf_audit_elements`, `uf_audit_locations`, `uf_audit_findings`; new module *Website audit*, on by default; see `userflo/docs/WEBSITE-AUDIT.md`). One scanner reads the site's own pages as an anonymous visitor (same-host only, redirects followed by hand on the host only, no cookies), at most 3 pages every 5 minutes under a lock, with a daily cap, growing back-off after failures and no re-parse of unchanged pages. It lists the pages visitors viewed (Lite: 25 pages; Pro: unlimited) and, per page, the elements (buttons, internal/outbound links, files, e-mail/phone links (scheme only), forms, images, media), each with the **same identifier the tracker computes** (a port of `selector()`; a browser test compares the two on a real theme). Repeated elements are one element with many locations.
- **Findings**, classed *verified* / *warning* / *recommendation* with evidence, an explanation and advice. Lite: page did not load, no title, noindex. Pro (module *Page Intelligence*): meta description, canonical, headings, social tags, image alt text, links and buttons without a name, vague link text, form fields without labels. A noindex page is not nagged about search metadata.
- **Pages → Health** column (dot **and** text) and **Page health** tab (scan summary, list with filters and search; Pro: *Pages that need attention* — busy pages with a verified problem, landing pages where visitors leave without a second page — with the numbers used, and findings by kind) and a **page report** (Pro: findings, views/visitors/engagement/entrances/exits, pages before and after, elements, edit link). Yoast SEO and SEOPress are detected read-only and flagged when both are active.
- **Elements** (Lite + Pro; module *Elements*, needs the audit): list with kind/audience/page/search filters and sorting, per-element totals, visitors, logged-in/anonymous split, daily trend, where it is used and found; "Not in inventory" for interactions on elements the scan did not find. Pro: pages before/after an interaction, goal completion of sessions that used the element vs the others (an observed difference, with minimum sample sizes, never a cause), findings per element.
- **Exclusions meet the audit**: audit findings can be hidden from the exclude button on a finding (this element/this kind, on this page/everywhere); one rule covers every matching occurrence, excluded findings are listed separately and survive rescans, and nothing is removed from analytics. Elements rows have the exclude button for the other two modes. The Exclusions audit mode is offered with Pro's findings.
- REST: `GET /audit/summary`, `/audit/pages`, `POST /audit/scan` (administrators, one scan per page per ten minutes), Pro `GET /audit/page`, `/audit/findings`, `/audit/insights`; `GET /elements`, `/elements/{id}`. Help guides *Website audit and Page Intelligence* and *Elements*.
- Edition: limit `audit_pages` (Lite 25), features `page_audit` and `elements_advanced` (Pro). `USERFLO_AUDIT_OFF`, `userflo_audit_http_args`, `userflo_audit_findings`, `userflo_audit_daily_scans`, `userflo_audit_batch`, `userflo_audit_rescan_seconds`.
- Developer: `dev/e2e-audit.js`, tests `AuditParserTest` (unit), `AuditTest`, `ElementsTest`, `AuditProTest`, `ElementsProTest`.

### Changed
- Sidebar gains *Elements* (after Goals). `.uf-scroll` is a positioned container so hidden table headers cannot widen the page on phones.

### Notes
- On upgrade the audit starts scanning the site's own pages in the background (Lite: at most 25 pages). Switch it off in Settings → Components → *Website audit*.

## [0.26.0] - 2026-10-19
Pro requires Lite 0.26.0 or newer (it appends Lite's exclusion filter to its report queries).

### Added
- **Exclusions** (Settings → Exclusions; database schema 11: table `uf_exclusions`, configuration only). One shared mechanism with three independent behaviours:
  - **Stop tracking** for a page (path: exactly / starts with / contains), an element identifier, a label, a link address or file, a section, or a CSS selector. Pages are skipped by the collector and the script is not loaded; the browser does not report CSS-selector and exact-identifier matches; the server drops everything else before it is stored or counted (so it also never reaches goals or the session's event counter).
  - **Measure, but leave out of statistics**: collection continues, but matching page views and interactions are left out, in SQL, of the Overview, Statistics, Journeys, Calendar (and Pro day/hour detail), Highlights, Map details, Focus, e-mail reports, shared pages and feeds, Pro WordPress and contact-click reports, and the CSV/JSON dataset exports. Pausing or deleting a rule brings the numbers back, history included; nothing is ever deleted. Cached reports are cleared when a rule changes.
  - **Hide audit findings**: the matching service (`Exclusions\Service::suppresses()`), rules and storage are in place for the website audit; the choice is shown only once an audit registers itself (`userflo_exclusions_audit_available`).
- An exclude button at the end of rows on **Pages** and **Statistics** (appears on hover and on keyboard focus, always visible on touch screens) opens a dialog that names the target, offers the scope (this element or page, everywhere or only here, everything below a page, everything with this label or address), explains the effect and previews what the rule would have covered in the last 30 days before anything is applied.
- The Exclusions screen: search, filters, add, edit, pause, resume, delete with confirmation, a per-rule "show effect", notes and dates; the older Settings-based exclusions (paths, roles, selectors) are listed read-only and keep working unchanged.
- REST `GET/POST /exclusions`, `PUT/DELETE /exclusions/{id}`, `POST /exclusions/preview` (administrators only). Help guide *Exclusions*; Spanish and Hungarian strings (machine translations, not reviewed by native speakers).
- Edition limit `exclusions`: Lite 10 active rules (paused rules do not count), Pro unlimited.
- `Goals\Store::condition()` accepts the address column, so goals and exclusions share one definition of "this page".

### Changed
- The tracker (4,333 B gzip; budget 4,608) ignores exact element identifiers sent in its configuration (`xt`) and the CSS selectors of "stop tracking" rules are added to `xs`.

### Not changed on purpose
Sessions, visitors, sources, devices, countries, goal completions, WooCommerce orders, the visitor profile and session timeline, Live, achievements and the personal-data export/erase tools are not filtered by "leave out of statistics" (see `userflo/docs/EXCLUSIONS.md`).

## [0.25.1] - 2026-10-19
### Fixed
- Overview: the *Compare with* row was squeezed to 4 px and hidden behind the first cards because the campaign table's bar used the same CSS class name; the campaign bar is now `uf-camp-bar`.

## [0.25.0] - 2026-10-19
Version numbering changes from `0.1.0-beta.N` to `0.N.0`; 0.25.0 follows 0.1.0-beta.25 (WordPress compares `0.25.0` as newer, so sites update normally). Earlier entries keep their old numbers.

### Changed (release)
- The word *beta* is dropped from version numbers, the readme and the documentation.
- `Geo/countries.php` (data) renamed `Geo/country-data.php`: it differed from the class file `Geo/Countries.php` only by letter case, which breaks on Windows and macOS and is not accepted by wordpress.org. Packages are now checked for case-colliding names.
- Pro requires Lite 0.25.0 or newer (it uses code added after beta.25).

### Added (release)
- `assets/geo/LICENSE-countries-ODbL.txt`: attribution notice, the changes made and the ODbL 1.0 text for the country data derived from mledoze/countries; a *Third-party data and licences* section in the readme.

### Added
- **Session-only (cookieless) tracking mode** (Settings → Privacy → Tracking mode; every edition; no database change). Writes and reads no cookie and removes `uf_vid` / `uf_sid` left by the earlier mode; a visit is kept together by random ids in the browser tab's session storage (gone when the tab closes, renewed after the inactivity time). It does not recognise returning visitors (visitors ≈ sessions), stores no IP address, links no visit to a user account, keeps country-level location only and cannot tie WooCommerce orders to a visit. The server derives the visitor id from the session id in this mode, so a cached page still running the previous tracker cannot store a persistent id. Consent, GPC/Do Not Track and exclusions are unchanged; the mode does not make a site compliant with any law (see `userflo/docs/COOKIELESS.md`). Tracker size 4,289 B gzip (budget 4,608).
- **WooCommerce sales** (database schema 10: table `uf_orders`, numbers only). When WooCommerce is active: a *Sales* card on the Overview and a *Shop* tab under Statistics with orders, revenue (order total minus refunds), average order value, net sales, refunds and discounts per currency, with the previous period and the orders that are not counted. Orders are read through WooCommerce's own order objects (compatible with High-Performance Order Storage and the block checkout; both declared), kept as an idempotent mirror, updated on create, status change, refund and delete, and re-readable from Statistics → Shop. No customer data is read or stored. Lite counts Processing and Completed orders; Pro adds sales by campaign, source and medium (the visit that was active at checkout, from the buyer's own `uf_sid` cookie; otherwise "Unknown"), basket sessions and a choice of counted statuses. Needs UserFlo's capability plus WooCommerce's (or administrator). New module *WooCommerce sales* (only present while WooCommerce is active). **Tested against stand-in order objects only; see `userflo/docs/WOOCOMMERCE.md` for what to verify on a real store.**
- **Goals and conversions** (database schema 9: tables `uf_goals` and `uf_goal_hits`; nothing existing is changed). Define goals for a page view (exactly / starts with / contains) or an event (type and name); a session completes a goal at most once, checked as visits are recorded. *Goals* screen with create, edit, pause, delete, a "does this match anything?" check, completions and conversion rate per period with the previous period, a split by campaign or source, and *Re-read history* to rebuild one goal from stored data. A *Goals* card on the Overview, a *Completed a goal* column in the Campaigns report and a Goals section for e-mail reports. Lite: 3 active goals. Pro: unlimited goals, a value per goal, funnels (2–5 ordered steps) and splits by device, country and new/returning. New module *Goals* (can be switched off in Settings → Components), `userflo_goal_completed` action, `Edition` feature `goals_advanced` and limit `goals`. Counting and attribution rules: `userflo/docs/GOALS.md`.
- **Campaigns (UTM).** Sessions now store `utm_source`, `utm_medium`, `utm_campaign`, `utm_term` and `utm_content` from their landing page (database schema 8: five columns and an index on `uf_sessions`; rows are not rewritten). New *Statistics → Campaigns* report (Lite: by campaign, source, medium, content or term; Pro: combined source / medium / campaign and comparison with the previous period) and a Pro *Campaign* filter in Visitors. Existing sessions are filled from their stored landing page by a small background job in batches of 500 that removes itself when done (progress under Tools → Status). One attribution rule: a session belongs to the campaign it arrived with. See `userflo/docs/CAMPAIGNS.md` for what it measures and how it differs from GA4.
- Exports of the sessions dataset include the five campaign columns.
- **Overview redesign.** KPI cards with an accent edge, change chips and trend lines; a new traffic chart (smooth lines, axis labels, a crosshair tooltip that follows the pointer or the arrow keys, switchable Page views / Sessions / Visitors per day, a table version, weekly grouping for ranges over three months); ranked bars with share of total for Popular pages and Traffic sources; **Devices** and **Top countries** cards (countries appear only while geolocation is on; nothing is shown for visits that were not located). Colours come from per-style tokens, so every UI style (default, dark, glass, elegant, fancy) gets matching charts; axe-core finds no WCAG A/AA violations in any of them.
- **Traffic channels** (Organic search, Direct, Social, Referral, Email, Paid), derived from each visit's referrer and `utm_*` parameters. A grouping heuristic, labelled as such; extend the rules with the `userflo_channel_rules` filter. The raw source list stays on the Sources tab.
- **Compare with**: previous period (default), same period last week, last month or last year, or no comparison. The comparison appears in the KPI chips and as a dashed line on the chart, and is remembered per browser. It is withheld, with the reason shown, when the comparison period is not fully covered by recorded data or would overlap the selected dates. Available in Lite and Pro.
- Developer tools: `dev/seed-overview.php`, `dev/bench-overview.php`, `dev/shot-overview.js`, `dev/e2e-overview.js`, `dev/unit-chart.mjs`.

- **Sections manager (Settings → Components).** Every section now has three separate switches: *Enabled* (does it run at all), *Show in sidebar* and *Include in reports*. A section that is on but hidden keeps collecting and appears in reports; a section that is off but still shown in the sidebar opens a short notice with a *Turn on* button (administrators only, nonce-checked); a section can also be excluded from scheduled reports without being switched off. The screen lists what each section costs while on, what it needs and what stops if it is switched off.
- **Scheduled e-mail reports, Advanced tracking (selector rules, event properties, form detection and the Google Tag Manager data layer) and WordPress reports are now sections** that can be switched off (Pro). Off means: no hooks, no routes, no scripts on the page, the schedule is cleared (and restored when switched back on); settings and recorded data are kept. Everything stays on by default for existing sites.
- `userflo_module_toggled` action (`$key`, `$is_on`) fires when a settings save switches a section.
- Developer: `dev/e2e-modules.js`; `UserFloTestCase::save_settings()` and `died()`.

### Changed
- The server now drops every query parameter from a recorded URL except `utm_*` and the same short allow-list the tracker already applied (`userflo_query_params`), so a hand-made request cannot store click ids, tokens or e-mail addresses. Normal tracker traffic is unchanged.
- Scheduled e-mail reports skip the sections whose module was left out of reports and say "turned off" for a section whose module is off, instead of silently omitting it.
- Turning a section off no longer removes its sidebar entry by itself: the entry follows *Show in sidebar*. Sites that never saved the new screen keep the old behaviour (off ⇒ no entry).
- **Release preparation (packaging).** The admin translation files are now generated per script: each of the nine script handles gets only the strings its own scripts use, instead of the full catalogue repeated nine times per language (the Lite package shrinks from 1.15 MB to 0.80 MB; 70 KB to 0.4-30 KB per file). `bin/check-i18n.py` verifies every handle against the sources (run by the package build and by the test suite); `SOURCE_DATE_EPOCH` makes the files reproducible.
- Optional PHP extensions: UserFlo Pro's text shortening (advanced tracking, e-mail reports) uses the same UTF-8 safe helper as Lite, `ctype_digit()` is no longer needed, and the GA4 integration reports a clear error instead of failing when OpenSSL is missing. **UserFlo Pro now needs the Lite version that contains these helpers and `Modules::needed()` (the next release); raise `USERFLO_PRO_MIN_LITE` together with the version.**
- **Modules now describe runtime behaviour, not only the menu.** `Modules::spec()` declares what each module owns and `Modules::needed()` handles the one dependency (Reports builds on the statistics and map data). A module that is off no longer attaches its work: Sharing's page, feed and CSV hooks (Lite); the PDF handler and Tools card (Reports), the sync schedule, footer scripts and routes (Integrations), and the click-collection route, front-end script and tracker setting (Heatmaps) (Pro). Collection, retention (including heatmap counters), exports, privacy tools, translations and settings stay on whatever the modules are set to. Settings and stored data are unchanged; there is no migration.
- With heatmaps off, `/userflo/v1/heat` no longer exists (it answered 204 before). A cached page that still sends a click beacon gets a 404, which is ignored.
- The PDF buttons on a visitor or session no longer appear when the Reports module is off.

### Fixed
- Privacy wording: the privacy guide no longer says nothing leaves the site or that geolocation is unsupported; it names the two optional features that contact outside servers (the DB-IP database download and the bug and request form). The Settings text "Geolocation is not enabled in this version" is gone.
- Documentation: Pro globe script size (about 6.7 KB gzip), a reference to a repository-only file in the configuration guide.
- Shortening of text (log messages, share and translation labels, form names, city names, journey titles, XLSX sheet names) no longer calls `mb_substr()` directly: a shared helper (`UserFlo\Core\Text::substr`) uses mbstring when present and otherwise a UTF-8 safe fallback, so the optional `mbstring` extension is not required. The two existing byte-wise fallbacks (tracker field validation, bug-report message) now cut on character boundaries too.
- Documentation: the tracker size is stated as about 4 KB gzipped (4,148 bytes measured at beta.25; it was "< 3 KB"). The install guide points to `docs/AUDIT.md` in the project repository instead of a file the package does not contain.

## [0.1.0-beta.25] — 2026-10-16

### Added
- **Globe, vector look (Pro)**: the 3D globe can draw land as smooth, sharp outlines from a detailed equirectangular map (`userflo-pro/assets/geo/world-land.svgz`, about 53 KB, unpacked by the browser itself and fetched only when that look is used), with the countries that have visitors tinted on top. Choose the starting look in **Settings → Branding and appearance → 3D globe style**; each person can switch on the Map screen (remembered in the browser).
- **Sortable columns** (click a header; click again to reverse) and a **Columns** menu to hide any column (the one naming the row stays) on **Visitors** and **Pages**. Visitors can now also be sorted by Visitor, Type, Location (by the text shown, unknown last) and Device, on the server.
- **Statistics and Journeys**: every card can be collapsed and rearranged (drag its handle, or focus it and use the arrow keys); the layout is remembered per browser, with Collapse all / Reset layout. In the full Journeys view the complete paths are now their own card.
- **Settings**: tabs **Report a bug or request** (a form that sends one e-mail to the developer; nothing else is sent unless "technical details" is ticked), **Support the developer** (donation and website links) and, in Lite, **Lite vs Pro** (feature table and upgrade links). All addresses are `.invalid` placeholders in `userflo/src/Links.php` until set (or use the filters).
- **Settings sections**: Achievements, Sharing, Integrations, Tools and Licence are no longer in the sidebar; they are listed under Settings ("More in Settings") and a strip at the top of each links the sections.

### Changed
- **Sidebar order**: Overview, Statistics, Map, Live, Visitors, Journeys, Pages, Calendar, Heatmaps, Reports, Settings.
- **Lite settings** no longer show Pro-only controls: scroll-depth steps, company logo (also in the setup wizard), report language and the integrations help card. Saved values are kept and still applied.
- The globe's hexagons now get **finer as you zoom in** (five grid levels), so continents stay readable.
- Edition feature `branding` (company logo) is Pro.
- **Lite map**: zoom stops at 5 (country level, where the numbers are), and the "Cities" detail option is hidden, because Lite has no city data.

### Fixed
- Journey flow diagram: the last column's labels no longer overlap the previous column's text, and nodes are spaced so two-line labels cannot touch.
- Visitors filter panel: the direction control is labelled "Order by" and its text is no longer cut off.
- The calendar day panel closes when you click outside it.
- The sidebar icon is a single-colour glyph (it showed as a white block).
- The Columns menu on Visitors and Pages has a frosted, blurred background in every dashboard style, so the text behind it no longer shows through.

### Credits
- The vector globe's land outline is "Equirectangular projection world map without borders" from Wikimedia Commons, **CC BY-SA 4.0**, compressed to .svgz and otherwise unchanged. It is credited in Settings → About, on the Map screen and in `userflo-pro/assets/geo/LICENSE-world-land.txt`, and stays under its own licence as a separate data file.

## [0.1.0-beta.24] — 2026-10-15

### Added
- **Key and keyholder** (Pro): a second way to unlock UserFlo Pro. Copy the small keyholder file `userflo-keyholder.php` into the plugin folder (or into `wp-content/`, which survives plugin updates), then enter the code on **UserFlo → Licence → Key and keyholder**. Pro turns on only while both are present: the plugin without the file does nothing, and the file without the code does nothing. The file holds a signed, salted, slow hash of the code (never the code), so nobody can make a keyholder for a code of their own without the owner's secret key; the unlock is bound to the site, so the file and settings copied to another site do not work. Five wrong codes close code entry for 15 minutes. The shop licence is unchanged and works alongside. Tracking, data and Lite are never affected.
- `dev/keyholder.php` (development only, not packaged) creates the keypair and the keyholder files; the production ZIP and the tester ZIP never contain a keyholder file (checked by `bin/check-packages.sh`).

## [0.1.0-beta.23] — 2026-10-15

### Changed (Lite / Pro split of the journey features)
- **Journey analytics** (Journeys screen): every edition keeps the **basic summary** — top-10 entrances (with the most common next step), recorded session exits and most common complete paths — for the last 24 hours, 7 or 30 days. The **flow diagram and transition table, exploring by selection, filters, comparison, the paths leading to each exit, up to 100 paths and day/week/month/custom periods** are now **Pro**. Counting rules and the data behind them are identical.
- **Visual journey** (one session): now **Pro**, with the identity-change card and earlier/later session navigation. Lite keeps the chronological **Timeline** with its existing limits (latest 100 events, latest 10 sessions) and shows the standard Pro upgrade prompt. A remembered "visual" choice falls back to the timeline.
- Enforced on the server: `GET stats/journeys` refuses filters, selections and comparison in Lite with the standard upgrade error (403 `userflo_pro_required`) and clamps list lengths with the new limit `journey_rows` (10); `GET sessions/{id}` sends `identity` and `nav` only with Pro. New edition features `journey_analysis` and `journey_visual`. Nothing about tracking, collection, retention or stored data changes, and nothing is deleted.
- The Lite admin script is about 8 KB gzipped smaller: the flow and visual-journey interface now ships in the Pro bundle.

## [0.1.0-beta.22] — 2026-10-15

### Added
- **Journey analytics** (UserFlo → Journeys): entrances, common journeys and recorded session exits for a day, week, month or custom range, built only from the page views UserFlo already stores.
  - **Entrances**: the first recorded page of each session, with its share and the most common next steps; optional change in share against the previous period, shown only when that period is fully covered, not sampled and large enough.
  - **Common journeys**: a Sankey-style flow of the first five pages of each session (top seven pages per step, the rest grouped), with sessions that stopped shown as recorded exits and longer ones as "continues". Every number counts sessions, once per node and link. A full table of transitions and a list of the most common complete paths are the accessible alternative.
  - **Recorded session exits**: the last recorded page, with the share of all exits and exits per view of the page, worded as "recorded exits" (UserFlo cannot know the visitor decided to leave), plus the common paths leading there.
  - **Exploring**: choose an entrance, a page or connection in the flow, or an exit; the other sections update with exact server-side filters (one request per choice, none on hover). Selections combine and reset in one click; the period and filters are kept.
  - Filters: device, country, logged-in or anonymous visitors, and grouping pages by first folder.
  - Periods follow the existing rules: Pro gets day/week/month with previous/next and custom ranges; Lite gets the same rolling 24 hours, 7 and 30 days as the other reports.
- API: `GET stats/journeys`; help guide *Journey analytics*. No database change: the queries use the existing session and page-view indexes.

### Notes
- Entrances and exits are derived from the ordered page views, not from each session's stored exit address, so a few exits can differ from the Statistics screen when a visitor has several tabs open.
- Up to 50,000 sessions are analysed per request (the most recent first); a larger period says so. Sessions that began before the retention cutoff are left out.
- The admin script grows by about 6 KB gzipped.

## [0.1.0-beta.21] — 2026-10-15

### Added
- **Visual journey**: a second view of a session next to the Timeline (Visitors → visitor → session → *Visual journey*; the Timeline stays the default). Page cards in visit order joined by directional connectors, with time, engaged vs open time, scroll depth, events per page, entry/last-page markers, and a details panel per card (keyboard accessible). Connectors show elapsed time to the next page view, explicitly labelled as such.
- **Anonymous → known**: a clear *Identity change* card at the recorded moment of a login, or of a link made by checkout/registration/social login, using only existing identity records. Visits that were attributed afterwards (with *Attach earlier browsing to the account* on) are explained and tagged *Anonymous at the time*. IP addresses are never used; the visitor name follows the *show identity* setting.
- **Earlier session / Later session** buttons and a position counter (sessions are never joined). Long sessions load 100 events at a time and draw 60 cards at a time.
- API: `GET sessions/{id}` additionally returns `identity`, `nav` and `pv` on events (existing fields unchanged); no database change.
- Documentation: help guide *Visual journey*.

### Notes
- Registration wording is inferred from the account's creation time (within ten minutes before the sign-in); UserFlo has no separate registration record.
- Bundle size: the admin script grows by about 3.4 KB gzipped.

## [0.1.0-beta.20] — 2026-10-15

### Added
- **Forms in Lite**: `[userflo_form name="…"]…[/userflo_form]` names any form (a form plugin's shortcode, or hand-written HTML) in the reports. The wrapper is invisible and collects nothing itself. New help guide *Tracking forms*.
- **Automatic form detection in Pro**: WPForms, Fluent Forms, Gravity Forms, Forminator, Formidable Forms, Contact Form 7 and the Divi contact module are recognised from their markup; submits are named after the form, and where the builder reports success a separate *Form sent* event is recorded. Statistics → WordPress → Forms shows Submissions and Confirmed sent, with the form's current title from the builder. `success=".selector"` on the shortcode confirms any other form. Settings → Advanced tracking → Form detection switches it off.
- Tracker hook `UserFlo.onForm()`; PHP filter `userflo_form_shortcode_attributes`.

### Notes
- Detection and confirmation rely on each builder's markup and public events and were checked against reproductions of that markup in a real browser, not against the plugins themselves. Re-check after a major builder update.
- Pro 0.1.0-beta.20 needs UserFlo (Lite) 0.1.0-beta.20.

## [0.1.0-beta.19] — 2026-10-15

**First Lite / Pro split build.** UserFlo is now two plugins: **UserFlo** (free, standalone, no licence) and **UserFlo Pro** (separate paid plugin that requires UserFlo). Your collected data is untouched, there is no database migration, and the settings of features that moved are preserved. Read `docs/EDITIONS.md` before updating a site that uses heatmaps, e-mail reports or integrations.

### Changed (read this if you use beta.18 features)
- **Moved to UserFlo Pro** (paused in Lite, settings and data kept, a one-time notice lists what paused): heatmaps, e-mail reports, GA4/Clarity/PostHog integrations (including the tags they print), the report builder and PDF reports, WordPress reports, Overview highlights, the 3D globe and live feed, city-level map detail, the calendar's week view, extra metrics, longer history and day/hour detail, advanced visitor filters, the lifetime visit calendar, e-mail/IP in visitor profiles.
- **Lite limits** (display and analysis only, never collection or retention): reports cover 7 or 30 days; each session shows its latest 100 events and each visitor their latest 10 sessions (older rows are kept and visible with Pro); the calendar is monthly, page views, the last 3 months; the map shows countries only.
- **Selectors**: exclusion selectors stay in Lite (a privacy control); include-selectors and labels are Pro. A value saved in beta.18 keeps working in Lite, read-only.
- **Custom events**: `UserFlo.track("name")` is unchanged. Properties are Pro-only and allow-listed; Lite stores the event name and no properties.
- **Privacy fix in both editions**: a click on a `mailto:` or `tel:` link is now recorded as *Email link* / *Phone link* with only the scheme, never the address or number (before, link text showing the address could end up in the event name).
- The admin app for Lite shrank from 113 KB to 73 KB (22.6 KB gzip) because Pro views are no longer part of it.

### Added
- **UserFlo Pro plugin** with Settings → *Advanced tracking* (selector rules with labels, allow-listed event properties, disabled event kinds, console diagnostics), the **Google Tag Manager dataLayer bridge** (off by default; `userflo_*` events with a namespaced payload, only after consent), and the **e-mail/phone link click report**.
- **Edition layer** in Lite (`UserFlo\Edition`): one place for feature checks and limits, enforced in the REST layer and queries; filters `userflo_edition_features`, `userflo_edition_limits`, `userflo_upgrade_url`.
- **Licence and updates** for Pro through the official WooCommerce API Manager PHP library: activation, deactivation, authenticated updates, a twice-daily verification, a 14-day outage grace, and a clear policy when a licence lapses (Pro pauses, data and Lite are untouched).
- **Time-limited tester licences** (tester build only): signed, host-bound, single-site or five-site, 7 or 30 days, not reusable.
- Packaging: `bin/build-zip.sh` builds both ZIPs and `bin/check-packages.sh` verifies them. Test suites split into `unit`, `integration` (Lite) and `pro`.
- Guides: *UserFlo Lite and Pro*, *Advanced tracking*, *Pro licence, plans and updates*; Spanish and Hungarian translations.

## [0.1.0-beta.18] — 2026-10-14

### Added
- **Traffic calendar** (*UserFlo → Calendar*): a month view (default) and a week view of your traffic as rounded tiles whose colour deepens with activity, with previous/next navigation, a **Today** button, and the date range and site timezone always shown. Choose what the colours mean: **page views** (default), **sessions**, **unique visitors** or **events**, each explained in place and using the same definitions as the Overview. Colours rank each day among the days shown (so one outlier cannot flatten the rest) and the legend lists the real value ranges. Days with no recorded data, future days, zeros and partial days are all told apart, and every tile has a visible value, a tooltip on hover and keyboard focus, and a full text label.
- **Tile-to-day morph**: selecting a day grows the tile into a day view (and back again) with a small custom animation, no library. It respects *reduced motion*, moves focus to the day heading, returns focus and scroll position to the tile, and closes with Escape or *Back to calendar*.
- **Day view**: totals, previous/next day, a 24-cell hourly heatmap that follows the chosen metric, top pages (linked to the page detail), busiest hours, traffic sources and devices. Selecting an hour shows its time range, totals, top pages and events. Daylight-saving days show 23 or 25 hourly cells.
- New REST endpoints `stats/traffic/calendar`, `stats/traffic/day` and `stats/traffic/hour` (analytics capability, Statistics module). Only the visible period and the selected metric are queried, and day and hour details load on demand. No new tracking and no database changes.
- Spanish and Hungarian translations for the new screens; user guide *Traffic calendar*.

## [0.1.0-beta.17] — 2026-10-14

### Added
- **Distraction-free view**: an icon at the top right of every UserFlo screen hides the WordPress menu and admin bar, gives UserFlo the whole width and shows UserFlo's own menu as a horizontal bar at the top; the same icon returns to the normal view. Remembered in the browser, applied before the page paints (no flash), works with all styles and on phones.

## [0.1.0-beta.16] — 2026-10-14

### Changed
- **Globe look**: a frosted-glass sphere with a soft glow and a hexagonal dot map (teal to blue, tinted for countries with visitors) in place of filled country shapes. This also removes the stretched shape between Canada and Asia (shapes cut at the date line were being joined across the globe).
- **Globe behaviour**: it now turns slowly by itself and stops while the pointer is over it, while dragging, while a place is selected and while the tab is hidden; *Auto-rotate: on/off* in the toolbar (remembered, off with *reduce motion*). Clicking a number turns to the place and zooms in a little, with a small card (visitors, sessions, *Details*, *Zoom out*); clicking anywhere else, or Escape, zooms back out and carries on turning. *Details* opens the same location dialog as the 2D map.

### Fixed
- The globe could stick after turning more than 360°: longitudes are now wrapped on every turn and the land is no longer polygons. Focusing a button can no longer scroll the clipped globe box and shift the markers.
- Only markers on screen are created; hexagons off the canvas are skipped.

## [0.1.0-beta.15] — 2026-10-14

### Added
- **3D globe on the Map screen** (Map → *3D Globe*; *2D Map* stays the default and unchanged). A canvas globe (no WebGL, no dependencies, 3.7 KB gzip, downloaded only when opened) with drag/pinch/scroll/keyboard control, countries shaded by visitors, numbered red circles with the same totals as the 2D map (clustered when close, capped at 120, selectable for the same location details), date range / detail level / totals / table shared with the 2D view, and a recoverable message with a *Back to the 2D map* button if it cannot start.
- **Soft pulses for new live activity** (translucent ring that grows and fades; still ring with *reduce motion*), distinct from the visitor-count circles; historical data never pulses. **Live activity** toggle (pause polling and pulses) and **Show / Hide live feed** (compact panel beside or below the globe, remembered in the browser). Feed items with a place turn the globe to it; selecting a place filters the feed.
- Live items now carry an approximate place (country centroid, or city rounded to 0.1°) when geolocation is on; never an IP address.
- Filter `userflo_feature_globe` (default on) as the single switch a future Lite/Pro licence layer can use; see *3D globe and live activity* in Help.

### Changed
- The Live screen and the Map screen share one live-data poll (`useLive`); behaviour of the Live screen is unchanged.

### Tests
- `Phase25Test` (feature flag independence, place precision per geolocation mode, no IP in live items, permissions) and the browser test `dev/e2e-globe.js` (46 checks: 2D unchanged, view switch keeps range and detail, real counts, no historical pulses, a real new event pulses and fades, feed show/hide and width, feed→globe highlight, filters, keyboard, pause stops polling, cleanup on switching back, download failure, empty data, reduced motion, ~400 places).

## [0.1.0-beta.14] — 2026-10-14

### Added
- **Advanced visitor search and filters** (Visitors → More filters): visited between dates, viewed a page starting with a path, did an event (any type, or a named form/download/custom/commerce event that exists in your data), came from a referrer (text, or *direct*), new or returning, device, country, minimum sessions, sorting. Filters combine, show as removable chips, and the option lists load only when the panel is opened. Reuses the existing visitor list, search and pager; the date range uses the shared 366-day rules.
- **Overview comparison**: every KPI shows the change against the previous period of equal length (only when that period is fully covered by recorded data; otherwise a note says why), plus *Returning visitors* and *One-page sessions*. A lazily loaded **What to look at** panel shows pages gaining and losing visitors, the most engaging pages (active time and scroll depth) and where visits end (exit pages with the share of that page's views).
- **Heatmaps screen**: short explanation of how to read it, page (type or pick), device, period (7/30/90 days, a year, custom dates) and compare filters, a summary (clicks, page views, clicks per 100 views, how far half of visitors scroll, how many reach the end), a text table of the busiest click spots as an accessible alternative to the canvas, deep links from a page's detail view, and scaling of the preview on phones. Still anonymous counters only; nothing per click or per person is collected.
- **WordPress reports** (Statistics → WordPress content, forms and shop): views by post type, front page, archives and category; form submissions per form and page; WooCommerce products with page views and add-to-basket clicks and the shop page funnel; interactions by Divi, Elementor and Bricks module. Built only from data UserFlo already records; each tab loads on demand. No orders, revenue or attribution are claimed. See *WordPress reports* in Help.

- **Scheduled e-mail reports** (Settings → Email reports): switch on to reveal SMTP (host, port, encryption, username, password, sender) or use the site's own mail set-up; recipients (site administrator or up to 10 addresses); daily, weekly or monthly delivery at a chosen time and timezone; choose and order the report elements (14 elements reusing the dashboard queries); responsive HTML e-mail with a plain-text alternative and links back to the dashboard; **Send test e-mail** and **Send report now**; delivery log with period, recipients, time, attempt and status. Duplicate-proof (period keys plus an atomic lock), at most three retries, no backlog after downtime, password stored encrypted and never shown or logged. See *E-mail reports* in Help.

### Changed
- Schema version 7: two indexes on events (`type_name_visitor` for the event filters, `pageview_id` for the heatmap element list). The most-clicked-elements query on the Heatmaps screen dropped from about 1.8 s to 18 ms on 450,000 events.
- New filter hook `userflo_builder_labels` to add builders to the report.
- New options `userflo_mail`, `userflo_mail_state`, `userflo_mail_log` (removed on uninstall) and cron events `userflo_mail_tick` / `userflo_mail_retry` (removed on deactivation).

### Tests
- New PHPUnit suites for visitor filters (injection attempts, escaping, validation), overview numbers against hand-computed data, heatmap filters and report accuracy, and the WordPress reports; browser tests `e2e-visitor-filters`, `e2e-overview`, `e2e-heatmap-ui`, `e2e-wordpress`, `e2e-mobile`.

## [0.1.0-beta.13] — 2026-10-14

### Added
- **Setup wizard**: opens on first activation (and from a notice on existing sites); eight steps (logo and style with live preview, language, site type, privacy, features incl. geolocation and heatmaps, integrations found on the site, review). Every option explains what it does, why, and how much it affects the site. Nothing is saved until the end; it can be started again from Settings and keeps settings it does not ask about. See `userflo/docs/SETUP-WIZARD.md`.
- **Report language**: reports can be exported in a different language from the dashboard (any available language, including custom ones). Default in Settings → Translations, per-report override in Reports; dates, numbers and the site-focus labels follow it.

### Fixed
- Phone layout: Reports, Integrations, Sharing, Tools and wizard step 3 no longer scroll sideways at 360–390 px (inputs and form rows are capped to the screen width). Checked with `dev/e2e-mobile.js` at 360, 390 and 768 px.
- Contrast: error text in the Elegant and Fancy styles now meets WCAG AA; wizard current-step marker readable in the dark styles.
- The Settings, Translations and Setup screens now receive their script translations (they were missing from the translation file list).
- `Geo/countries.php` has the direct-access guard like every other source file.

### Changed
- `dev/e2e-a11y.js` also scans the wizard steps; new `dev/e2e-mobile.js`.

## [0.1.0-beta.12] — 2026-10-14

### Changed
- **Settings reorganised into tabs**: Tracking, Privacy (incl. data retention), Components (was “Sections”), Geolocation, Branding and appearance (logo and dashboard style), Translations, Help, About. One form still saves everything; the open tab is remembered after saving.

### Added
- **Site type** setting (general, store, blog, company, events, forum, courses, portfolio): adds a *Focus* section to the Overview and a *Site focus* section to reports with the page groups, a visit funnel and event highlights that matter for that kind of site (e.g. shop, product, basket, checkout, order confirmation and account pages for a store; event listings, event pages, outbound ticket-provider links and calendar downloads for an events site). WooCommerce and The Events Calendar page URLs are picked up when those plugins are active. Page groups are URL-pattern heuristics; the funnel counts sessions that *reached* each step, not necessarily in order.
- **Retroactive identity linking**: when a visitor signs in, registers or checks out (any method that sets the WordPress login cookie), their earlier anonymous sessions from the same browser cookie are attributed to the account, and later visits from that browser too. Never by IP, never reassigned to a second account on a shared browser; can be switched off (*Attach earlier browsing to the account*). Visitor profiles show *Identified as account since*. Schema v6.
- **Translations editor** (Settings → Translations): language list with progress, per-string editor in collapsible sections with search and “only what is missing”, new languages from a locale code, English wording overrides, `.po` download, reset. Stored in the database on top of the shipped translations (survive updates); placeholders are validated.
- **Help**: guides open in a modal (getting started, configuration, geolocation setup incl. where to get the MMDB file, connecting GA4/Clarity/PostHog, sharing, shortcodes, heatmaps, privacy, translations, change log), **Logs** (downloads, integration syncs, retention, share links) and quick setup cards.
- **About**: version, licence (GPL-2.0-or-later, full text), credits, source link, copy-able system information.
- Bundled guides moved to `userflo/docs/` so they ship with the plugin; new `GEOLOCATION-SETUP.md` and `CONNECTING-INTEGRATIONS.md`.

### Fixed
- Settings defaults no longer call translation functions (could recurse when translations were overridden).

## [0.1.0-beta.11] — 2026-10-14

### Added
- **Geolocation** (off by default): country or country+city per session from a local MMDB database (DB-IP Lite downloaded in the background, or your own file); locations shown in Visitors, visitor profiles and Live. Schema v5. See `userflo/docs/GEOLOCATION.md`.
- **Map** screen: zoomable world map with numbers per continent / country / city, click for a modal with top pages, top logged-in users, top weeks, days, days of week and times of day.
- **Map in reports** with zoom level (continents / countries / cities) and region options.
- **Shortcodes** `[userflo_overview]`, `[userflo_statistics]`, `[userflo_achievements]`, `[userflo_map]` (also `[UserFlo_Overview]`…): server-rendered, `access="admin|members|public"`, aggregates only. See `userflo/docs/SHORTCODES.md`.
- **Integrations detect existing plugins** (Google Site Kit, Microsoft Clarity plugin, PostHog plugin) and can reuse their settings; a *Google Site Kit (GA4 reporting)* provider pulls GA4 aggregates through Site Kit with no service account. No duplicate tags are added. Option names and Site Kit's internal API are unverified (see docs/INTEGRATIONS.md).
- New section switches **Map** and **Front-end shortcodes**.

## [0.1.0-beta.10] — 2026-10-14

### Added
- **Share links with expiry**: share the Statistics page or the Live view (read-only, no WordPress login) or a **data feed** with a link that expires (1/7/30/90 days or a chosen date) and can be revoked. Only a hash of each token is stored; shared pages never show names, emails, IPs or visitor ids; feeds contain aggregates only; links are rate-limited, `noindex` and uncached. New **Sharing** screen (admins).
- **Excel / Google Sheets**: download a statistics workbook (.xlsx, one sheet per dataset) and live CSV/JSON feeds (`=IMPORTDATA("…")` for Google Sheets, any system that can fetch a URL). Formula-injection-safe.
- **Sections** setting: switch off Live, Pages, Statistics, Reports, Achievements, Sharing, Integrations or Heatmaps. Disabled sections disappear from the menu and their API routes are removed; data is kept.
- **Company logo** setting (media library): shown beside the UserFlo mark on UserFlo screens, on shared pages and as the default report logo. The UserFlo icon/logo stays visible everywhere; new UserFlo icon also used as the menu icon.
- **Achievements** are now one box per family whose label climbs Copper → Silver → Gold → Diamond → Olympian; clicking opens a modal with a history chart, the date each milestone was reached, who triggered it (logged-in users link to their visitor profile) and the record holder for record-type achievements.

### Fixed
- Translation loading on WordPress 6.0 (the bundled .mo files are now read by the older parser too).

## [0.1.0-beta.9] — 2026-10-14

### Added
- **Reports** screen: build a report from a date range (presets or custom), the data sections you want (key numbers, daily chart, pages, sources, buttons, links/downloads, journeys, devices, forms/events), your own logo, a style preset (Light, Dark, Fancy, Glass, Elegant) and custom colours; export as **PDF** (A4, multi-page) or **PNG**. Rendered in the browser with no external libraries; settings and logo are remembered in the browser only.
- **Translations**: Spanish (es_ES) and Hungarian (hu_HU) for all PHP and dashboard strings, a "Plugin language" setting (Automatic / English / Español / Magyar), `bin/make-pot.py` and `bin/compile-i18n.py`, `userflo/docs/TRANSLATING.md`. Translations are machine-produced and not yet reviewed by native speakers.
- New dashboard style **Fancy** (lavender canvas, indigo/orange/coral accents, rounded cards).

### Changed
- **Dark** style redesigned (deep navy, violet glow, rounded cards); **Glass** is now the EiraFlow Glass Light look (light frosted panels, soft blue accent).
- **Heatmaps**: highlights are rounded, glowing blobs whose colour and size grow with the number of clicks, with a colour legend.

## [0.1.0-beta.8] — 2026-10-14

### Added
- **Live** screen: active visitors (last 5 minutes) and a real-time activity feed of page views and events for anonymous and logged-in visitors; polls every 5 s while the tab is visible, with pause.
- **Pages** screen: list of pages; click one to see who viewed it and when (anonymous and logged-in, filterable, paginated, linked to the visitor's journey).
- **Statistics** screen: most viewed pages, most clicked buttons, internal links, outbound links, downloads, forms, custom events, most common user journeys, entry/exit pages, devices, browsers.
- **Achievements** screen: 146 badges across 9 categories and 5 tiers (copper to olympian) built from your own tracking data, with progress bars and hidden secret badges.
- **Dashboard style** setting: Default, Dark, Glass (glassmorphism) and Elegant. Applies on UserFlo screens only.

### Fixed
- The "Enable click heatmaps" setting could not be saved (it was missing from the settings sanitiser); now covered by tests and a browser test of the real form.
- Accessibility: contrast of code text inside link buttons and locked achievement cards.

## [0.1.0-beta.7] — 2026-10-14

### Added
- Phase 10 (optional, off by default): **click heatmaps** with device buckets (desktop/tablet/mobile), date range, page selector and side-by-side page comparison; **scroll-depth reach** and **most-clicked elements** computed from normal tracking data. Clicks are aggregated into counters (`uf_heat`, schema v4); no per-click rows and no visitor/session/IP data. Separate retention setting (default 90 days). ~0.9 KB gzip extra script only when enabled.
- `userflo/docs/HEATMAPS.md`; `docs/REPLAY-EVALUATION.md` (rrweb measured at 22.9 KB gzip; session replay deliberately **not** implemented, with reasons and alternatives).
- 12 integration tests and a browser test for heatmaps.

### Fixed
- Accessibility with realistic data: calendar day buttons' accessible names now contain their visible text; links in tables are underlined.

## [0.1.0-beta.6] — 2026-10-14

### Added
- Phase 9: schema v3 (in-place upgrade) adds indexes for referrers and top pages; `ip_header` setting (trusted proxy header for the visitor IP); selector include/exclude settings.
- New-visitor flood protection and per-visitor / per-IP rate limits (filterable); `Sec-Fetch-Site: cross-site` rejected on `/collect`.
- Overview aggregates cached 2 minutes (`userflo_overview_cache_ttl`); live "active now" and recent sessions stay uncached.
- Retention runs in 1,000-row batches and reschedules itself while expired rows remain.
- Uninstall now always removes integration credentials, caches, export files and crons; tables/settings only when "delete on uninstall" is ticked; multisite aware.
- `docs/AUDIT.md` (verified vs. untested) and `docs/RELEASE-CHECKLIST.md`.

### Changed
- Top pages, recent sessions, visitor search and journey queries rewritten for large tables (3 M-event dataset: overview 20–47 s -> 2–9 s cold, ~2 ms cached; search ~850 ms -> ~2 ms).

### Fixed
- Accessibility: contrast of hint text and calendar buttons, missing label on the "Ignore known bots" checkbox (axe-core, WCAG 2.1 AA: 0 automated violations on all screens at 1280 and 390 px).

### Known limitations
- Only PHP 8.3 was executed; no screen-reader audit; `/collect` median latency is above the 40 ms goal (SHORTINIT not implemented); see `docs/AUDIT.md`.

## [0.1.0-beta.5] — 2026-10-14

### Added
- Phase 8: integration framework (`IntegrationInterface`, `AbstractProvider`, registry filter `userflo_integrations`) with isolated providers for **Google Analytics 4**, **Microsoft Clarity** and **PostHog** (cloud or self-hosted): consent-gated front-end tags, cached aggregate imports via cron / "Sync now", connection status, last sync, actionable errors, rate-limit back-off, disconnect.
- Credentials encrypted at rest (libsodium) and never returned to the browser; SSRF-safe HTTPS-only HTTP client with timeouts and bounded retries.
- Integrations admin page; "External analytics" card on the Overview, kept separate from native numbers (no double counting).
- `UserFlo.onConsent(cb)` in the tracker API.
- `userflo/docs/INTEGRATIONS.md`, including what each provider cannot do and what could not be verified against live documentation.
- 30 mocked-API integration tests and a browser test of the UI and consent gating.

### Known limitations
- Provider API details are only partly verified (documentation sites were unreachable); nothing was tested against a real GA4/Clarity/PostHog account.

## [0.1.0-beta.4] — 2026-10-14

### Added
- Phase 7: export framework (`WriterInterface`, `userflo_export_formats` filter for XLSX/ZIP later). CSV and JSON exports run as resumable background jobs (keyset batches, committed byte offsets, crash recovery, progress bar, 24 h private storage, owner-only authenticated downloads, max 2 running jobs/user, 5 M-row cap).
- Filters: date range, visitor, event types; anonymisation: none / strip user IDs + IP / pseudonymise identifiers.
- JSON bundle with `relations` map so records can be re-linked.
- Dependency-free PDF reports (visitor, session, period) with download buttons on profile/journey and Tools.
- WordPress Export/Erase Personal Data integration for visitors linked to an account; suggested privacy-policy text.
- Tools page: retention "run now", administrator-only "delete all data" with typed confirmation.
- Consent presets: Complianz, CookieYes, Cookiebot, Borlabs (format-based, unverified against the plugins).
- `userflo/docs/PRIVACY.md`.

### Fixed
- Starting a new export no longer leaves the previous download link clickable.

### Changed
- Removed the old one-shot CSV download endpoint (replaced by export jobs).

## [0.1.0-beta.3] — 2026-10-14

### Added
- Phase 6: React + TypeScript admin dashboard (`admin-ui/`, built bundle committed to `userflo/assets/admin/`, 6.6 KB gzip). React is WordPress's own `wp-element`; the public tracker is unchanged and independent.
- REST read API `userflo/v1`: `/stats/overview`, `/visitors`, `/visitors/{id}`, `/visitors/{id}/sessions|calendar|day`, `/sessions/{id}` (journey), `DELETE /visitors/{id}`. All require the analytics capability (`userflo_view_capability` filter), validate inputs and cap `per_page`.
- Overview: custom date ranges, KPIs, accessible daily chart (with table fallback), popular pages, traffic sources, recent sessions.
- Visitor list: server-side paging, anonymous/logged-in filter, debounced search. Profile: identity status, totals, return frequency, calendar with month navigation and lifetime history, day → sessions → journey drill-down with URL state, paginated journey, delete.
- Dev-only seeder (`dev/seed.php`) and dashboard browser test (`dev/e2e-dashboard.js`).

### Changed
- The server-rendered Overview/Visitors pages were replaced by the React app. Journey queries are now paginated in SQL.

## [0.1.0-beta.2] — 2026-10-14

### Added
- Phase 5: builder-agnostic interaction detection. Each click now carries a *component* (landmark, Gutenberg block, or builder module) via a new `events.component` column (schema migration 2, tested as an in-place upgrade from v1).
- Optional adapters for Divi, Elementor, Bricks and WooCommerce (loaded only when detected; 0.9 KB gzip); WooCommerce `add_to_cart` events (`commerce` type).
- Form submit (name only, never values) and media play events; ARIA toggles/tabs/menus/accordions/dialogs recognised.
- Settings: "Also track clicks on" / "Never track clicks inside" CSS selectors; `data-userflo-ignore` attribute.
- Settings: Complianz consent mode (experimental, not tested against the Complianz plugin).
- `userflo/docs/BUILDERS.md` compatibility matrix; builder fixture page and browser test (`dev/e2e-builders.js`).

### Fixed
- Internal link values no longer lose their query string (`/?page_id=2` was recorded as `/`).
- `data-userflo-ignore` wrappers are honoured when the interactive element is nested inside them.

## [0.1.0-beta.1] — 2026-10-14

First installable beta (MVP covering roadmap phases 1–4 plus a minimal dashboard).

### Added
- Technical blueprint, performance budgets and decision log (`docs/`).
- Plugin foundation: namespaced PHP, versioned migrator, four indexed tables (`uf_visitors`, `uf_sessions`, `uf_pageviews`, `uf_events`), settings with sanitisation, activation/deactivation/uninstall handling (data kept unless the admin opts in to deletion), daily batched retention cleanup.
- Vanilla JS tracker (2.9 KB gzip): page views, SPA navigation, clicks, downloads, outbound links, scroll depth, engaged time, custom events API, batching, `sendBeacon`, duplicate protection, bounded queue, consent gating, GPC/DNT.
- Public ingestion endpoint `POST /wp-json/userflo/v1/collect` with strict validation, size limit, bot filter, rate limiting.
- Identity and lifecycle: visitor/session cookies, login/logout capture, anonymous → logged-in association (never by IP), observed/estimated/active session states.
- Admin: overview, visitor list (filters, search, pagination), visitor profile (calendar, session history, journey timeline), settings, tools (CSV export with formula-injection protection, per-visitor deletion).
- 90 automated tests (unit + integration) and a Playwright end-to-end script.

### Known limitations
See README "Beta status".
