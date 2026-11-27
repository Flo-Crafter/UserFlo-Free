# FloLens configuration reference

Settings are grouped in tabs: **Tracking**, **Privacy** (including data retention), **Components** (per section: Enabled, Show in sidebar, Include in reports), **Geolocation**, **Branding and appearance** (company logo, dashboard style), **Translations** (plugin language and the translation editor), **Help** and **About**. Tables below are named after the old single page; the same fields exist in the tab that fits them.

## Settings → Tracking
| Setting | Default | Notes |
|---|---|---|
| Enable tracking | on | Off = no script, endpoint accepts nothing. |
| Site type | General | Adds a *Focus* section (page groups, visit funnel, event highlights) to the Overview and reports: online store, blog, company site, events, forum, courses, portfolio. Page groups are URL-pattern heuristics (WooCommerce and The Events Calendar URLs are detected when active); the funnel counts sessions that reached each step, not necessarily in order. |
| Attach earlier browsing to the account | on | When a visitor signs in, registers or checks out, earlier anonymous sessions of the same browser cookie are attributed to the account (see PRIVACY.md). Needs *Link visitors to accounts*. |
| Track clicks | on | Links, buttons, ARIA controls, downloads, outbound, form submit (name only), media play. |
| Ignore known bots | on | User-agent based; headless browsers are not treated as bots. |
| Do not track these roles | none | Leave empty while testing so your own visits appear. |
| Excluded paths | `/wp-admin`, `/wp-login.php` | Prefix match, one per line. |
| Session inactivity timeout | 30 min | Sessions end after this much inactivity (cookie lifetime). |
| Scroll depth thresholds | 25,50,75,100 | |
| Download extensions | pdf,zip,doc,… | |
| Also track / never track (CSS selectors) | empty | Plus the `data-userflo-ignore` attribute. |

## Settings → Privacy
| Setting | Default | Notes |
|---|---|---|
| IP address handling | anonymised | off / anonymised / salted hash / full. |
| Visitor IP source | server connection | Only change behind a proxy that always sets the header. |
| Consent | none | none, cookie, JavaScript, Complianz/CookieYes/Cookiebot/Borlabs presets (presets unverified). |
| Respect GPC / Do Not Track | on | No tracking, no third-party tags. |
| Link visitors to accounts | on | Associates a browser with the user after login; never by IP. |
| Show usernames and emails | on | Read from WordPress at display time. |

## Settings → Data retention
Retention days (default 180, 0 = forever) and the uninstall tickbox (off).

## Visitors table: columns
Open **Columns** above the table to show or hide columns and to change their order: drag a row by its handle (mouse, finger or pen) or use the up and down arrows (these also work with a keyboard and screen readers). **Reset to default columns** brings back the original layout. The layout is saved for your own WordPress account (each administrator has their own) and follows you between browsers; sorting, search and filters are not affected.

## Animations
Settings → Branding and appearance → **Animations** (on by default). On: lines draw in from left to right, pie and ring charts sweep round, columns and bars grow, table rows fade in one after another and a country grows under the pointer on the maps. Off: the data appears at once. A device set to "reduce motion" never sees the animations, whatever this says. Animations only change how a screen appears; no data or setting is affected.

## Zooming the maps
On the Map screen: **double-click** zooms in on the spot under the pointer, **Shift + double-click** zooms out, **Ctrl + scroll** (or a pinch on a touch-pad) zooms smoothly, a plain scroll zooms in steps, and the + and − buttons still work. The 3D globe (FloLens Pro) turns to the spot you double-click and zooms in on it.

## Filters (for developers)
`userflo_view_capability` (default `manage_options`), `userflo_tracker_config`, `userflo_query_params`, `userflo_load_adapters`, `userflo_client_ip`, `userflo_rate_limit_visitor` (120/min), `userflo_rate_limit_ip` (600/min), `userflo_new_visitors_per_ip_hour` (300), `userflo_export_formats`, `userflo_integrations`, `userflo_integration_allow_private_hosts`, `userflo_integration_allow_insecure`.
Constant: `define( 'USERFLO_DISABLED', true );` stops the tracker from loading (used for A/B performance tests).

## Custom events
```js
UserFlo.track('signup_clicked', { plan: 'pro' });   // string/number/boolean values only
FloLens.consent(true);                               // when consent mode is "JavaScript"
FloLens.onConsent(function () { /* runs once consent is granted */ });
```

## REST API
Public: `POST /wp-json/userflo/v1/collect`. The signed-in user's own table layout: `POST` or `DELETE /wp-json/userflo/v1/table-prefs/visitors` (`order`, `hidden`). Everything else requires the analytics capability (integration management and "delete all" require administrators). See §9 of `docs/ARCHITECTURE.md` in the [project repository](https://github.com/Flo-Crafter/UserFlo-Free/blob/main/docs/ARCHITECTURE.md).
