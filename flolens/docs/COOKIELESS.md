# Session-only (cookieless) tracking

Settings → Privacy → **Tracking mode**. It is available in every edition because it is a privacy control.

## The two modes
| | Standard (default) | Session-only |
|---|---|---|
| Cookies | Two first-party cookies once tracking starts: `uf_vid` (random id, about 400 days) and `uf_sid` (30 minutes, renewed while the visitor is active) | **None.** FloLens neither writes nor reads a cookie, and it removes `uf_vid` / `uf_sid` left by the earlier mode |
| How a visit is kept together | The session cookie | A random id in the browser tab's *session storage*, gone when the tab closes; renewed after the inactivity time |
| Returning visitors | Recognised across visits and days | **Not recognised.** Coming back later, or opening a new tab, is a new visitor and a new session |
| IP address | Stored as your IP setting says (anonymised by default) | **Not stored** (raw IP is used only transiently for rate limiting and, if you turned geolocation on, the country lookup) |
| Location | Country (city in Pro, if enabled) | Country only |
| Logged-in users | Can be linked to the visitor (setting) | **Never linked** and not stored; role exclusions still work |
| Consent, GPC / Do Not Track, exclusions | Applied | **Applied, unchanged.** The mode does not bypass or replace them |

The server enforces the main promise too: in session-only mode it replaces the visitor id with one derived from the session id, so even a cached page that still runs the previous tracker (and sends its old persistent id) is stored as one new visitor per session.

## What still works
Page views, entry and exit pages, referrers, campaigns (UTM), clicks, downloads, outbound links, forms, scroll depth, engaged time, custom events, goals and conversion rates (per session), device class, country, the live view, heatmaps (they never used ids) and reports.

## What changes or stops
- **Visitors and "returning visitors"**: a visitor is one session in one tab, so *Visitors* ≈ *Sessions*. "New vs returning" and visit calendars per person lose their meaning. Pro's visitor history shows single-session visitors.
- **Journeys** end with the tab or the inactivity time; a visit that continues in a new tab is a second visit.
- **Identity**: no association between a browser and a WordPress account.
- **WooCommerce attribution**: orders cannot be tied to a visit (no session cookie reaches checkout), so they appear as *Unknown*. Order counts and revenue are unaffected.
- **Unique visitors across days or months** cannot be measured. Totals of visitors over a period count sessions.
- If the browser blocks session storage, the ids live in memory and a new page load becomes a new visit.

## What it is not
- It is **not** a statement that your site is compliant with the GDPR, ePrivacy rules or any other law. Whether consent is required depends on your country, on what you track and on your other tools. Session-only mode still keeps an identifier in the browser (in session storage, not a cookie) for the length of the visit, and the server still sees the visitor's IP address while handling the request. Ask your legal adviser before you remove a consent banner.
- It does not change **other tools**. The optional Google Analytics 4, Microsoft Clarity and PostHog integrations load their own scripts and set their own cookies; Page builders, caching and security plugins may too.
- It is not fingerprinting: FloLens does not derive an identifier from the browser, screen, fonts or IP address. The only identifier is the random one created for the tab.

## Switching modes
Switching is safe and loses nothing: existing data stays as it was recorded. Pages cached with the old setting may keep the previous tracker for a while; the server protects the new promise as described above, but clear your page cache so the browser side matches. Existing standard-mode cookies are removed from a visitor's browser the next time they visit.

## For developers
`window.userfloConfig.sm` is `1` in session-only mode. The tracker's public API and events are unchanged.
