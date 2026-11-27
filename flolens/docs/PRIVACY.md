# FloLens — Privacy guide

FloLens is self-hosted: **all analytics data stays in your WordPress database.** Visitor data is never sent to FloLens or any third party. Two optional, owner-initiated features do contact outside servers, and nothing else does: downloading the free DB-IP geolocation database (only when geolocation is switched on; see *Geolocation* below) and the bug and request form (it sends the message you write, and the technical details only if you tick that box, to the developer by e-mail). FloLens Pro's optional integrations send data to the services you connect, described in the Pro documentation. This document describes what is collected so you can write your own notice; it is not legal advice and FloLens does not make a site compliant by itself.

## What is collected

| Data | Why | Where | Notes |
|---|---|---|---|
| Random visitor ID (`uf_vid` cookie, 400 days) and session ID (`uf_sid` cookie, expires after the inactivity timeout, default 30 min) | Recognise returning visitors, group page views into sessions | Browser cookies + `uf_visitors` / `uf_sessions` | First-party, random, not derived from IP/UA/fingerprint. Not set until consent when a consent mode is configured. |
| Page URL path, page title, referrer, entry/exit page | Journeys, popular pages, sources | `uf_pageviews`, `uf_sessions` | Query strings are dropped except `utm_*` and a short allow-list. |
| Clicks on links/buttons (label, selector, component), downloads, outbound links, form submit (form name only), media play, scroll depth, custom events | Interaction analytics | `uf_events` | Input values, keystrokes and typed text are never read. Disable per element with `data-userflo-ignore` or by selector in Settings. |
| Engaged time, elapsed time | Engagement | counters on sessions/page views | |
| Device category, browser, OS, screen size | Context | visitors/sessions | From the user-agent header and `screen`. |
| IP address | Optional | visitors/sessions | **Setting:** not stored / anonymised (default: last IPv4 octet, last 80 bits of IPv6) / salted hash / full. Never sent to an external service. No geolocation lookup is performed. |
| WordPress user ID (when logged in and *Link visitors to accounts* is on) | Show which account a visitor is | `user_id` columns | Username/e-mail/role are **not copied**: they are read from WordPress at display time, and hidden entirely when *Show usernames and emails* is off. |
| Login / logout timestamps | Session lifecycle | `uf_sessions` | Logout is only "observed" if the user logged out explicitly. |

Not collected: form field contents, passwords, keystrokes, mouse movement, fingerprints, precise location.

## Controls (FloLens → Settings / Tools)
- Consent gating: none / cookie / JavaScript call, plus format-based presets for Complianz, CookieYes, Cookiebot and Borlabs (**presets are not verified against those plugins**). Until consent the tracker sets nothing and sends nothing. Browser Global Privacy Control / Do Not Track can be honoured.
- Exclusions: user roles, URL paths, CSS selectors.
- Retention: default 180 days, daily batched cleanup, "run now" button; 0 = keep forever.
- Deletion: per visitor (profile page), everything (Tools, typed confirmation, administrators only), and automatically when WordPress' personal-data **Erase** is run for an e-mail address that belongs to a linked visitor.
- Access/portability: WordPress **Export Personal Data** includes the visitor profile, sessions, page views and events linked to that account; dashboard exports (CSV/JSON/PDF) with optional anonymisation (strip IDs/IP, or pseudonymise identifiers).
- Uninstall: data is **kept** unless "Delete all FloLens data when the plugin is uninstalled" is ticked.
- Suggested privacy-policy text is added under Settings → Privacy → Policy Guide.

## Anonymous visitors and data-subject requests
**Retroactive linking.** With *Attach earlier browsing to the account* on (default), when a person signs in, registers or checks out, the anonymous sessions previously recorded for the same browser cookie are attributed to that account, so the account's history shows what it did before signing in. It relies only on FloLens's own cookie (never IP address or fingerprinting) and is not applied to a second account on a shared browser (the first account that signs in on a browser keeps it). Mention it in your privacy notice and switch it off if you do not want pre-login activity connected to accounts. Erasure of a user removes the linked sessions together with the account's data.

Anonymous visitors cannot be tied to an e-mail address, so WordPress' request tools cannot find them. A person can only be located if they logged in on that browser and *Link visitors to accounts* was enabled. Consider your obligations accordingly.

## Exports
Export files are created in `wp-content/uploads/userflo-exports/` (direct web access denied via `.htaccess`/`web.config`; on other servers block this directory yourself), downloaded through an authenticated, nonce-protected request, and deleted after 24 hours. CSV cells beginning with `= + - @` are prefixed to prevent spreadsheet formula injection.

## Geolocation
**Optional and off by default** (Settings → Geolocation). A visitor's IP address is looked up in a database file stored on your own server and is never sent to an external geolocation service; only the result (country, and city in FloLens Pro) is stored, and the IP address itself only if your IP setting says so. The database is either the free DB-IP Lite file that FloLens downloads from `download.db-ip.com` once geolocation is on (the request is an ordinary file download from your server and carries no visitor data; the data is CC BY 4.0, attribution is shown in Settings) or a file you supply yourself (for example MaxMind GeoLite2, which needs your own account and licence). See `GEOLOCATION.md`.

## Limitations
- Estimated vs observed: a session end is only observed on explicit logout.
- PDF reports use core PDF fonts: characters outside Windows-1252 (e.g. CJK, Cyrillic) are transliterated or replaced with `?`.
- IP truncation is applied before storage; raw IPs may still appear in your web-server logs.

## Geolocation (optional)
When enabled, the visitor's IP address is looked up in a local database once per session; the country (and, in city mode, the city and approximate coordinates) is stored with the session and visitor. The IP address itself is only stored if the IP setting allows it. See docs/GEOLOCATION.md.

## Heatmaps (optional)
When enabled, anonymous click positions are stored as aggregate counters per day, page, device and grid cell. No cookie, visitor id, session id, IP address or element content is stored for heatmaps, and collection follows the same consent settings as tracking. Scroll depth and element summaries reuse ordinary tracking data. Heat counters are deleted after the heatmap retention period (default 90 days). Session replay is not offered.

## Campaign values
The five `utm_*` values of a session's landing page are stored with the session (cleaned, at most 100–150 characters each). No other query parameter is stored. They are removed with the session by retention, visitor deletion and WordPress' erase tool, and are included in exports. See `CAMPAIGNS.md`.

## Goals
When a visit completes a goal, FloLens stores the goal, the session (internal ids) and the time. Nothing else is added. Completions are removed with their session by retention, visitor deletion and WordPress' erase tool; "Delete all analytics data" removes all of them. See `GOALS.md`.

## WooCommerce orders
With WooCommerce active, FloLens stores for each order only: order id, time, status, currency, total, tax, shipping, discount and refunded amounts, and (if the buyer's browser sent FloLens's session cookie at checkout) that session id. No names, e-mail addresses, addresses, phone numbers, customer ids, notes or products. Orders follow the retention setting and are removed with "Delete all analytics data" and when WooCommerce deletes the order. See `WOOCOMMERCE.md`.

## Session-only tracking
With Settings → Privacy → Tracking mode set to Session-only no cookie is used and no IP address is stored; see `COOKIELESS.md` for exactly what is measured and the legal caveats. It does not make a site compliant with any law by itself.
