# Geolocation and the Map

Off by default (Settings → Geolocation).

## How it works
1. A visitor's IP address is looked up **once per session** in a local MMDB database (never per page view, never via an external web service).
2. Only the result is stored on the session and the visitor: country (ISO code), and in *Country and city* mode also city name and approximate coordinates (3 decimals ≈ 100 m of the city centre, not the visitor's position). The IP address is not stored unless the IP setting says so; the lookup happens in memory.
3. Without a database nothing is stored (locations stay empty); no errors are shown to visitors.

## Databases
- **DB-IP Lite** (default): UserFlo downloads `dbip-country-lite` or `dbip-city-lite` (free, CC BY 4.0, no key) from `download.db-ip.com` into `wp-content/uploads/userflo-geo/` (folder protected against listing). The download runs in the background through WP-Cron (the city file is large: several minutes and ~100 MB of disk). It is re-checked daily and replaced when a new monthly file exists. Attribution: "IP Geolocation by DB-IP" (shown in Settings).
- **My own .mmdb**: set an absolute path to any MaxMind-format City or Country file (MaxMind GeoLite2 with your own licence/updater, DB-IP, …).
- The bundled reader is the open-source `maxmind-db/reader` (Apache-2.0, `userflo/lib/maxmind-db`). Both MaxMind/DB-IP schema and the flat "ip-location-db" layout are understood.

## Map screen (UserFlo → Map)
World map with zoom (buttons, mouse wheel, drag). Zoomed out it shows numbers per continent, zoomed in per country, and per city (city mode). Click a number for a modal with the top 6 pages, top 3 logged-in users (linking to their visitor profile), visits by hour of day and day of week, and the top times of day, days and ISO weeks. "Show as table" lists the same places for keyboard and screen-reader use. Visitors who used several locations in the period are counted in each.

Reports can include the map with a chosen detail level (continents, countries, cities) and region (world or one continent).

## Flags in the Visitors table
The Location column shows the country's flag before the name. The flags come with UserFlo as one local file: the browser never contacts another site for them, and nothing extra is collected (the country already stored with the visitor is used). Each flag has the text alternative "Flag of <country>" for screen readers. When the country is missing or not recognised, only the text (or a dash) is shown.

## Privacy
Location is personal data under GDPR-style laws, and city-level precision more so. Mention it in your privacy notice; prefer *Country only* unless you need cities. Location data follows the normal retention setting and is removed with visitors (erasure, retention, uninstall with "delete data").

## Limits
IP geolocation is approximate: VPNs, mobile carriers and corporate networks put visitors in the wrong place; countries are far more reliable than cities. Behind a proxy/CDN set Settings → Visitor IP source correctly or every visitor will be located at the proxy. Server-side lookup needs `FILTER_FLAG` public IPs, so local/private addresses are never located. Sessions recorded before geolocation was enabled stay unlocated.

## Credits
Country names, continents, map points and outlines: Natural Earth (public domain), converted by `bin/build-geo-data.js` with the `world-atlas` and `topojson-client` packages (ISC); notice in `assets/geo/NOTICE.txt`. Flags: country-flag-icons (MIT, `assets/flags/LICENSE.txt`). The generated `src/Geo/country-data.php` has no licence conditions.
