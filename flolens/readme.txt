=== FloLens – Visitor Journeys & Analytics ===
Contributors: flocrafter
Tags: analytics, visitor tracking, user journey, sessions, privacy
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.47.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Visitor tracking and analytics on your own site: visitor profiles, session history, page views and journeys. Privacy-first.

== Description ==

FloLens shows you how people use your WordPress site, and keeps everything on your own server. It records anonymous and logged-in visitors, builds a profile, a session history and a chronological journey for each one, and gives you simple reports. No external service is needed to run it.

**What you get**

* Overview with visitors, sessions, page views, engaged time, top pages and sources, any range from 24 hours to a year and a comparison with the previous period.
* Visitor profiles (anonymous and logged-in), with returning-visit history, sessions and the full timeline of one visit.
* Journeys: where visits start, the most common paths and where they end.
* Clicks, downloads, outbound links, scroll depth, forms and custom events.
* Goals for a page or an event, with an optional amount per session, completion counts and conversion rates.
* Campaign (UTM) report, traffic calendar by month, WooCommerce sales totals, a 2D visitor map (country level), live visitors.
* Human, AI-crawler and bot counters, with the AI assistant behind each crawler and the visitors AI assistants sent you.
* A history menu in the WordPress toolbar for the page you are looking at, peak times and days, and a comparison with the same dates last year from daily summaries that outlive the detailed data.
* Website audit: reads your own pages and reports basic accessibility problems against WCAG 2.2 (images without alt text, links, buttons and form fields without names, missing page language), each marked as a detected issue, a potential issue or something to review manually. Automated checks cannot establish WCAG conformance.
* Exclusions (stop tracking a page, leave something out of the statistics), CSV and JSON export, read-only share links, shortcodes.
* Privacy controls: IP address handling, consent modes, Global Privacy Control, role and path exclusions, retention, a session-only mode without cookies, personal-data export and erase.
* A lightweight tracker (about 4 KB gzipped, no dependencies).

**FloLens Pro** (a separate plugin, sold separately) adds more: funnels and target goals, custom date ranges and longer periods, advanced visitor filters, the full journey analysis and visual journey, city-level map and the 3D globe, heatmaps, scheduled e-mail and PDF reports, a dashboard builder, alerts, Copy for AI (copy a counters-only summary of any screen into ChatGPT, Claude or another AI chat), GA4 / Clarity / PostHog import and more. FloLens works fully without it.

FloLens is under active development and is versioned 0.x until the first stable release.

== Installation ==

1. Upload the `userflo` folder to `/wp-content/plugins/` (or install the zip via Plugins > Add New > Upload).
2. Activate the plugin.
3. Open FloLens > Settings to review the privacy options, then browse your site and watch FloLens > Overview.

== Frequently Asked Questions ==

= Does FloLens send my visitors' data anywhere? =

No. Everything is stored in your own WordPress database. The only outside request FloLens can make is the optional download of a free location database (see External services below), and only after you switch geolocation on.

= Does it set cookies? =

Yes, by default two first-party cookies: a random visitor ID (kept for up to 13 months) and a session ID (kept while the visitor is active). A session-only mode that sets no cookies is available under Settings, and consent modes let you wait for your consent plugin. You should describe this in your privacy policy; FloLens adds a suggested text to the WordPress privacy policy guide.

= Is tracking on by default? =

Yes, once activated, for everyone except the roles and paths you exclude. Review Settings > Privacy first if your site needs consent before tracking.

= What is stored about a visitor? =

A random ID, the pages and interactions, the referrer, device, browser and operating system, and (if geolocation is on) the country. IP addresses are not stored, stored truncated or stored as a salted hash, as you choose. Logged-in visitors are linked to their account only when identity linking is on.

= How long is the data kept? =

180 days by default (0 keeps it for ever). After that only a small daily row of counts per day (totals, top pages, sources, countries, devices) is kept, for 3 years by default, so you can compare with last year. It contains no visitor ID, session or address.

= Will it slow my site down? =

The tracker is small and loads asynchronously, collection is a single short request, and reports are calculated from indexed tables in bounded batches.

== External services ==

FloLens does not call any external service on its own. There are two optional, user-started exceptions:

* **Location database (db-ip.com).** If you switch on geolocation and choose the downloaded database, FloLens downloads the free "IP to Country Lite" file from https://download.db-ip.com/ (the "IP to City Lite" file in FloLens Pro) and refreshes it monthly unless you turn that off. Only the file is requested; no visitor data is sent. Licence: Creative Commons Attribution 4.0 (https://creativecommons.org/licenses/by/4.0/); service by DB-IP.com (https://db-ip.com), terms and privacy policy at https://db-ip.com/privacy and https://db-ip.com/tos. You can use your own MaxMind-format file instead.
* **Your own site (website audit).** The audit reads your own pages, a few at a time, from your own address, to list the pages and elements on them. It contacts no other server.

== Privacy ==

FloLens stores analytics data in your WordPress database. See the FAQ above and Settings > Privacy for what is stored and how to limit it. FloLens registers a suggested privacy-policy text and supports the WordPress personal-data export and erase tools.

== Custom events ==

`UserFlo.track('signup_clicked');`

== Source code ==

The admin screens (`assets/admin/app.js`) and the front-end tracker (`assets/js/tracker.min.js`) are compiled or minified. The human-readable source is public: the admin interface (TypeScript/React) is in the `admin-ui/` folder at https://github.com/Flo-Crafter/UserFlo-Free and is built with `npm run build`; the tracker source is `assets/js/tracker.js`, minified with `bin/build-js.sh`.

== Third-party data and licences ==

* Country names, continent codes, map points and the map outlines come from Natural Earth (public domain, https://www.naturalearthdata.com), drawn with the "world-atlas" and "topojson-client" packages (ISC licence). Details in `assets/geo/NOTICE.txt`; the data files are built by `bin/build-geo-data.js` in the source repository.
* The geolocation reader is maxmind-db/reader (Apache-2.0).
* The national flags (`assets/flags/flags.svg`) are from country-flag-icons by catamphetamine (MIT, licence in `assets/flags/LICENSE.txt`).

== Changelog ==

= 0.47.0 =
* Changed: the plugin is now called FloLens – Visitor Journeys & Analytics (it was UserFlo). Plugin folder, slug and text domain are `flolens`. Your settings and data are untouched: the internal option, table and hook names keep their old `userflo` prefix.

= 0.46.3 =
* Changed: charts and tables now start their load animation when they scroll into view, not when the page opens.

= 0.46.2 =
* Fixed: "Download / update the database now" on the Geolocation tab sent you back to the first settings page and did not download. The country database now downloads straight away, you return to the Geolocation tab and see whether it worked.
* Changed: the author is shown as FloCrafter and the plugin site link goes to flocrafter.com.
* Fixed: the setup wizard asks again after the plugin is deleted and installed again. The setup wizard now also detects Complianz, CookieYes, Cookiebot and Borlabs Cookie and follows them.

= 0.46.1 =
* Pro-only screens (advanced visitor filters, visit calendar, sales by campaign, custom date range) moved out of the free plugin's JavaScript into UserFlo Pro.

= 0.46.0 =
* Changed: UserFlo has no limits of its own any more. Goals, exclusion rules, audited pages, report ranges, a visitor's history and the traffic calendar are all unlimited. UserFlo Pro adds features (filters, funnels, heatmaps, alerts, reports and more), not bigger numbers.

= 0.45.3 =
* Changed: the country list (names, continents, map points) is now built from Natural Earth, which is public domain, instead of a database under the ODbL, so everything shipped with UserFlo is GPL-compatible. The map outlines are unchanged; map points for countries moved by a few degrees at most.
* Changed: the Support tab shows donation buttons, and Settings shows the "Report a bug or request" tab, only once real addresses are configured, so no placeholder link is ever shown.

= 0.45.2 =
* Changed: code-review annotations only (no change in behaviour): every database query that builds a table name from UserFlo's own table helper, and every file or stream UserFlo creates itself, now carries a correctly named explanation for the WordPress coding-standard checks.

= 0.45.1 =
* Added: on the Map screen, double-click zooms in on the spot under the pointer (Shift + double-click zooms out), and Ctrl + scroll (or a touch-pad pinch) zooms smoothly without zooming the whole page. The 3D globe (Pro) does the same: double-click turns it to the spot and zooms in.
* Fixed: on the Map screen, scrolling over the map sometimes did not zoom it, depending on which data finished loading first.

= 0.45.0 =
* Added: animations. Lines in Traffic over time draw in from left to right, pie, donut and rose charts sweep round, columns and bars grow, table rows fade in one after another and a country grows under the pointer on the maps. Switch them off in Settings → Branding and appearance to show the data instantly. Devices set to reduce motion never see them.

= 0.44.0 =
* Added: Visitors table: change the order of the columns (drag a row by its handle in the Columns menu, or use the arrows; it works with a finger too). The order and the hidden columns are saved per WordPress user and "Reset to default columns" brings back the original layout.
* Added: Visitors table: the country's flag beside the location, with a text alternative for screen readers. The flags are a local file shipped with UserFlo: no outside requests and nothing new is collected.

= 0.43.4 =
* Changed: the source-code links point to the renamed repository github.com/Flo-Crafter/UserFlo.

= 0.43.3 =
* Fixed: in the dark theme the tiles in "Humans, AI crawlers and bots" stayed white with unreadable text; they now follow the theme.
* Changed: the website and "Upgrade to Pro" links point to flocrafter.com/flolens.

= 0.43.2 =
* New: the Top countries box on the Overview can also be shown as the flat world map, with each country shaded and its number printed on it.
* Fixed: long names (such as file addresses) no longer run into the numbers in the pie, donut and star legends; they are shortened with the full name on hover.

= 0.43.1 =
* New: on the Overview, click a box title (Traffic sources, Popular pages, Devices, Top countries, the Focus funnel and highlights) to cycle through ways of showing it: list, pie, donut, columns, stacked bar, rose and star. Each box changes on its own and remembers its view in your browser. "Traffic over time" cycles through flowing lines, sharp lines, steps, dots, bars and candles.

= 0.43.0 =
* New: Tools → Database & Storage shows how much space UserFlo's tables use (measured, with row counts labelled as estimates), what is detailed data and what stays in the daily summaries, and lets administrators run the cleanup, delete detailed data older than a date, or rebuild the summaries, always with a confirmation.
* New: retention profiles (Minimal 30 days, Standard 90, Long 365, Custom). Existing settings are not changed. An optional shorter period for clicks and scrolls (off by default).
* Improved: daily summaries are written all-or-nothing, written again after late visits arrive, and checked before any detailed data is deleted. They now also keep goal completions, sales per currency and interaction counts per day, so these survive the retention period.
* Improved: the cleanup cannot run twice at once, records errors and can be resumed; it is queued again if WP-Cron missed it for a day and a half.
* Improved: two redundant database indexes were removed (about 5 % less space and cheaper inserts). Existing data is kept.
* Fixed: "Delete all data on uninstall" now also removes the daily summaries table.

= 0.42.0 =
* New: accessibility checks in the website audit, mapped to WCAG 2.2 levels A and AA. UserFlo (free) checks images without alt text, links and buttons without a name, form fields without labels and the page language, with an Accessibility overview on Page health. Results are marked as Issue detected, Potential issue or Manual review required; nothing is claimed as compliant. New guide: Accessibility checks.
* New (Pro): about 20 more checks (headings, landmarks, ARIA references and roles, focus, zoom, frames, media, contrast from inline colours), an accessibility report on each page, accessibility counts in the Audit table and its CSV export, and accessibility issues in the Pages that need attention list.
* Fixed: a submit or reset button without a value, and an image button with alt text, are no longer reported as having no name.
* Pages scanned before this release are read again once to collect the new data.

= 0.41.7 =
* Changed: the UserFlo toolbar menu has two tabs, All history and This page (they work without any script), and one row of short links: Live, Map, Visitors, Page info, Elements (and Audit with Pro). The Settings link is gone.

= 0.41.6 =
* Changed: the UserFlo toolbar menu is more compact: one panel with the latest activity in a short scrolling list and the quick links in a footer, instead of a tall list of links above a long history.

= 0.41.5 =
* New: Settings → Privacy can show or hide the two parts of the UserFlo toolbar menu on their own: the quick links and the latest activity. With both off the menu is not shown, and with only the links on the menu loads no script.

= 0.41.4 =
* New: Settings → Branding and appearance can show or hide the Guide, Copy for AI (Pro) and Export CSV buttons at the top of the screens, each on its own.

= 0.41.3 =
* Changed: the toolbar menu is now called UserFlo. It opens quick links (Live view, Map, Latest visitors, this page in Pages, its Elements and, with Pro, its page audit with the audit dot next to the name) and the last ten visits and interactions, which are written into the page so they show even when a speed or cache plugin delays the menu's script.

= 0.41.2 =
* Fixed: the History menu in the toolbar could stay on "Loading…" for ever when its script was blocked or the request never answered. It now shows a link to the Live view without JavaScript, and a request that does not answer within 20 seconds ends with "The history could not be loaded."

= 0.41.1 =
* New (Pro): Copy for AI button next to Guide on the main screens, and Settings → AI Personas. The AI screen was removed from the sidebar.

= 0.41.0 =
* New plugin name: FloLens – Visitor Journeys & Analytics. "Lite" wording replaced by "free version" on screen and in the guides.

= 0.40.3 =
* Fixed: the Export CSV button no longer overlaps the distraction-free button.

= 0.40.2 =
* Fixed: upgrading from an earlier version now adds database columns and tables that were added since (schema 19).

= 0.40.1 =
* Two new minimalist grayscale dashboard styles: Mono (white) and Noir (black).

= 0.40.0 =
* Edit layout (wireframe) on Overview and Statistics; Export CSV with date range and column choice on the main screens.
* Overview boxes link to their sections; larger Devices chart; "Issues" on Pages opens the page audit.
* The audit scan keeps heading, link and image counts per page (used by the Audit screen in UserFlo Pro).

= 0.39.0 =
* Prepared for WordPress.org: no locked code in this plugin; paid features live in UserFlo Pro.
* Credit link on shared pages is opt-in. readme: External services and privacy sections.

= 0.38.2 =
* Fixed: a page left open in a browser tab for hours or days kept one session alive (and "Active") the whole time. A visit now ends after the inactivity time; coming back to the tab starts a new one. A visit that began with a login and later arrives without a signed-in user (for example after an inactivity-logout plugin) is shown as logged out.
* Fixed: the Devices chart on the Overview was small in a wide card.

= 0.38.1 =
* A logged-in visitor's page has a button to open their WordPress profile. Setting (Privacy): hide it, or open in the same or a new tab. Only shown to people who may edit that user.

= 0.38.0 =
* Daily summaries are kept for years (counts only, no personal data) after the detailed data is deleted, so Statistics can compare a period with the same dates last year. Setting: Privacy → Keep daily summaries for (default 3 years). CSV download under Tools.

= 0.37.1 =
* A short "Saved" message now floats up in the middle of the screen and fades out when you save settings.

= 0.37.0 =
* New: goals can need an amount (open a page 3 times in one session). Pro adds a time frame, finishing within some minutes, and targets for a weekday and time window (for example more than 500 downloads on Tuesdays), and goals, funnels and targets can be added to reports.

= 0.36.0 =
* New: see which AI crawled your site (ChatGPT, Claude, Perplexity, Gemini and more, and what each crawler does) and how many visitors were sent by AI assistants, with settings to add crawlers and assistants of your own.

= 0.35.0 =
* New: human visitors next to AI crawlers, search engines and other bots on the Overview and Statistics, and a switch on Pages that highlights pages AI and bots requested. Bots are counted on the server by name, with no IP address and no full user agent stored.

= 0.34.0 =
* New: a History menu in the WordPress toolbar with the latest visits and interactions, for all pages or just the one you are on, and Peak times and peak days on the Statistics screen, split by logged-in and anonymous visitors.

= 0.33.0 =
* New: donut, pie, star, rose and column views of splits (devices, browsers, new and returning, logged-in and anonymous), chart options in Reports (Pro), names and notes for elements, heatmap hover details and pop-ups (Pro), a clearer Alerts screen with test results (Pro). The sidebar now starts with Overview, Visitors and Journeys.

= 0.32.0 =
* New (Pro): Statistics Builder. Create your own dashboards from widgets, switch between them on the Statistics page and share each one on its own with an expiring read-only link.

= 0.31.0 =
* New (Pro): Alerts. Get an e-mail or a webhook message when your site reaches a milestone (every N additional visitors, sessions or page views), when a goal, form, download or custom event happens, or when a day's visitors fall below (or above) a number you set. Alerts are sent in the background and contain numbers and public names only.

= 0.30.0 =
* New (Pro): the Studio connection can be ended or re-keyed by your UserFlo Studio site. UserFlo Studio, the central plugin for managing client sites, is a separate download.

= 0.29.0 =
* New (Pro): Settings → Studio lets a central UserFlo Studio site you pair with read summary figures from this site (no visitor data, read only, revocable at once).

= 0.28.0 =
* New (Pro): scheduled e-mail reports can attach the summary figures as CSV files for the same period. Off by default; no visitor-level data is ever attached.

= 0.27.0 =
* New: a website audit. UserFlo reads your own pages a few at a time, as a visitor would see them, and keeps a list of the pages and elements on them. It never changes your site.
* New: a Health indicator on Pages and a Page health tab. Lite checks that pages load, have a title and are open to search engines; Pro adds descriptions, headings, image alt text, links and forms, a prioritised list of pages that need attention and a report for each page.
* New: Elements. Buttons, links, forms, images and files on your pages with how visitors use them (interactions, visitors, logged-in and anonymous, trend); Pro adds journeys around each element, goal comparison and audit findings.
* Findings can be hidden with the exclude button (Pro); nothing is deleted.
* Database schema 12 (four new tables; existing data is kept). Spanish and Hungarian translations are machine-made.

= 0.26.0 =
* New: Exclusions (Settings > Exclusions). Stop tracking a page or interaction, leave it out of statistics while it is still collected, or (once an audit exists) hide findings you have accepted. One list for every report, a preview of the effect before you confirm, and an exclude button at the end of rows on Pages and Statistics. Lite: 10 active rules; Pro: unlimited.
* Database schema 11 (one new table; existing data is kept). Nothing is ever deleted by an exclusion.

= 0.25.1 =
* Fixed: the Compare with row on the Overview was hidden behind the first cards.

= 0.25.0 =
* Version numbering changes from 0.1.0-beta.N to 0.N.0: this is the successor of 0.1.0-beta.25. Nothing in your data or settings needs to be changed.
* New: Settings > Components manages every section with three switches (enabled, show in sidebar, include in reports).
* New: Campaigns (UTM) report, Goals and conversions, WooCommerce sales (orders and revenue), and an optional session-only tracking mode that uses no cookies.
* Database schema 10 (additive; existing data is kept). Older visits are read for campaign values by a small background job.
* Fixed: two source files whose names differed only by letter case (a problem on Windows and macOS) are now one file with a distinct name.
* Added the licence notice for the bundled country data.

= 0.1.0-beta.25 =
* Map globe: finer hexagons when zoomed in and an optional vector look (Pro). Sortable and hideable columns, collapsible and rearrangeable cards, new Settings tabs (report a bug, support, Lite vs Pro) and a tidier sidebar.

= 0.1.0-beta.24 =
* Pro: optional key and keyholder unlock (a small signed file plus its code).

= 0.1.0-beta.23 =
* Journey features split between editions: Lite keeps the basic journey summary and the session timeline; the flow diagram, exploring, filters and the visual journey are in Pro.

= 0.1.0-beta.22 =
* Journey analytics: entrances, common journeys (flow diagram plus table) and recorded session exits for a day, week or month, with exact filtering across the three sections.

= 0.1.0-beta.21 =
* Visual journey: a connected, keyboard-accessible view of one visit with engagement time and the moment a visitor signs in, next to the existing timeline.

= 0.1.0-beta.20 =
* Forms: name any form with [userflo_form name="…"]. UserFlo Pro detects WPForms, Fluent Forms, Gravity Forms, Forminator, Formidable, Contact Form 7 and the Divi contact module and records confirmed sends.

= 0.1.0-beta.19 =
* First Lite / Pro split: UserFlo stays free and standalone; heatmaps, e-mail reports, integrations, the report builder, WordPress reports, the globe, the full calendar and advanced tracking are in UserFlo Pro. Your data and settings are kept. See docs/EDITIONS.md.

= 0.1.0-beta.18 =
* Traffic calendar: month and week heatmap of page views, sessions, unique visitors or events; select a day to open it with a morph animation, an hourly heatmap, top pages, busiest hours, sources and devices; select an hour for its pages and events.

= 0.1.0-beta.17 =
* Distraction-free view: hide the WordPress menu, use the full width with a horizontal UserFlo menu; icon top right returns to normal.

= 0.1.0-beta.16 =
* Globe: frosted-glass look with a hexagonal dot map, slow automatic rotation that pauses on hover, click a number to zoom to it and click elsewhere to zoom back out; fixed sticking after full turns and the stretched shape near the date line.

= 0.1.0-beta.15 =
* Map: optional 3D globe with visitor-count markers, soft pulses for new live activity and an optional live feed panel. The 2D map is unchanged and remains the default.

= 0.1.0-beta.14 =
* Visitor search with combinable filters (dates, page, event, referrer, new/returning, device, country).
* Overview compares with the previous period and shows pages gaining/losing visitors, engaging pages and exit pages.
* Heatmaps screen with explanations, page/device/date filters, summary numbers and an accessible table; phone-friendly.
* WordPress reports: content types and categories, forms, WooCommerce products, Divi/Elementor/Bricks modules.
* Scheduled e-mail reports over SMTP: daily/weekly/monthly, chosen elements in your order, test and send-now buttons, delivery log, retries without duplicates.

= 0.1.0-beta.13 =
* First-run setup wizard (re-runnable from Settings) explaining every option, and a separate report language chosen from the available languages.
* Phone layout fixes on Reports, Integrations, Sharing, Tools and the wizard; contrast fixes in the Elegant and Fancy styles; settings and translation screens now load their translations.

= 0.1.0-beta.12 =
* Tabbed Settings (Tracking, Privacy, Components, Geolocation, Branding, Translations, Help, About), in-admin translation editor, help guides and logs.
* Site type setting with a focus section (store, blog, events, forum…) in the Overview and reports.
* Earlier anonymous browsing is attributed to the account when the same browser signs in or registers (switchable).

= 0.1.0-beta.11 =
* Geolocation and a zoomable Map with details, map in reports, front-end shortcodes, detection of Site Kit / Clarity / PostHog plugins.

= 0.1.0-beta.10 =
* Share links with expiry (statistics, live view, data feeds), Excel/Google Sheets export, section on/off switches, company logo, one-box-per-family achievements with detail modal.

= 0.1.0-beta.9 =
* New Reports screen (PDF/PNG export with your logo and colours), Spanish and Hungarian translations with a language setting, redesigned Dark and Glass styles, new Fancy style, rounded click-count heatmaps.

= 0.1.0-beta.8 =
* New Live, Pages, Statistics and Achievements screens; dashboard styles (Dark, Glass, Elegant); fixed saving of the heatmap setting.

= 0.1.0-beta.7 =
* Optional click heatmaps (off by default): anonymous aggregate counters, device buckets, scroll reach and element summaries. Session replay evaluated but not included.

= 0.1.0-beta.6 =
* Performance and hardening for large sites: new indexes, cached overview, batched self-rescheduling retention, flood protection, trusted IP header setting.
* Complete uninstall of credentials, caches and exports; accessibility fixes.

= 0.1.0-beta.5 =
* Optional integrations: Google Analytics 4, Microsoft Clarity and PostHog (consent-gated tags, cached aggregate import, encrypted credentials). Native tracking stays independent.

= 0.1.0-beta.4 =
* Exports: resumable background jobs with progress, JSON (relationships preserved), CSV, PDF reports (visitor/session/period), anonymisation options, WordPress personal-data export/erase, data-management tools, consent presets (Complianz, CookieYes, Cookiebot, Borlabs).

= 0.1.0-beta.3 =
* New React/TypeScript dashboard (overview with custom date ranges, visitor list, visitor profile, calendar, journey) on a permissioned REST read API.

= 0.1.0-beta.2 =
* Builder-aware click detection (components, Divi/Elementor/Bricks/WooCommerce adapters), form/media/add-to-cart events, include/exclude selectors, Complianz consent mode (experimental).

= 0.1.0-beta.1 =
* First beta: tracking engine, visitor profiles, calendar, settings, CSV export.
