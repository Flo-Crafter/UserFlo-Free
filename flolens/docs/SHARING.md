# Sharing, feeds and branding

## Share links (FloLens → Sharing, administrators only)
Create a link for the **Statistics** page, the **Live view** or a **Data feed**, with a label and an expiry (1, 7, 30, 90 days or a chosen date, at most one year). Whoever has the link sees only that content, read-only, without a WordPress login. Revoke it any time.

Security design
- The token is 128 random bits; only its SHA-256 hash is stored (`userflo_shares` option), so a leaked database does not leak usable links. The full link is shown **once**, at creation.
- A link only opens its own type: a statistics link cannot read the live view or feeds, and vice versa. No other FloLens or WordPress endpoint accepts a share token.
- Shared pages and endpoints omit visitor ids, usernames, emails, IP addresses and element targets; logged-in visitors appear only as "Logged-in user". Feeds contain aggregates only.
- Responses are `no-store`, `noindex`, `Referrer-Policy: no-referrer`; per-IP rate limit (filter `userflo_rate_limit_share`, default 120/min); expired links answer 404.
- Not covered: anyone who receives a link can forward it until it expires or is revoked; choose short expiries for sensitive data.

## Excel and Google Sheets
- **Download .xlsx** (Sharing → Excel / Google Sheets): one workbook with a sheet per dataset (summary, per day, pages, sources, buttons, links, outbound, downloads, journeys, devices, browsers). Needs the PHP `zip` extension.
- **Data feed**: create a *Data feed* link; FloLens shows one `=IMPORTDATA("…")` formula per dataset to paste into Google Sheets (refreshes automatically; Google caches roughly hourly). Change `.csv` to `.json` for other systems, and add `?days=7` (1–366) to change the period. Zapier/Make/Power BI can fetch the same URLs.
- Cells that start with `=`, `+`, `-` or `@` are prefixed with `'` to prevent spreadsheet formula injection.
- Raw visitor/session/event exports remain on the Tools page (CSV/JSON, with anonymisation options).

## Sections
Settings → *Sections* turns off Live, Pages, Statistics, Reports, Achievements, Sharing, Integrations or Heatmaps. A disabled section is removed from the menu and its REST routes are not registered; stored data is not deleted. (Turning Heatmaps off also stops click collection.)

## Company logo
Settings → *Branding*. The logo appears next to the FloLens mark on FloLens screens, on shared pages and as the default logo of new reports. The FloLens icon and name are always shown (on shared pages in the footer, "Powered by FloLens").
