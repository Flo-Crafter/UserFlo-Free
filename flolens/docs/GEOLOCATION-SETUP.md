# Geolocation setup: where to get the MMDB file

FloLens finds a visitor's country (and city) by looking the IP address up in a **local database file in MMDB format**. Nothing is sent to an external service while visitors browse. You need one of the following.

## Option 1 — DB-IP Lite, downloaded automatically (easiest)
1. Settings → **Geolocation**: set *Visitor location* to *Country only* or *Country and city*, keep *Database* on **DB-IP Lite**, save.
2. Press **Download / update the database now**. The download runs in the background (WP-Cron). The country file is small; the city file is large (about 100 MB, several minutes).
3. The status line changes to **Ready**. It is re-checked daily and replaced when a new monthly file is published.

Requirements: your server must be allowed to make outbound HTTPS requests to `download.db-ip.com`, WP-Cron must run (a real traffic or a server cron hitting `wp-cron.php`), and the uploads folder must be writable. The file is stored in `wp-content/uploads/userflo-geo/`.

Manual download instead: get the free *IP to City Lite* or *IP to Country Lite* file in **MMDB** format from DB-IP (https://db-ip.com/db/download/ip-to-city-lite), unpack the `.gz`, upload it as `wp-content/uploads/userflo-geo/dbip-city-lite.mmdb` (or `dbip-country-lite.mmdb`). Licence: CC BY 4.0, which requires the attribution "IP Geolocation by DB-IP" (FloLens shows it in Settings).

## Option 2 — MaxMind GeoLite2 (your own file)
1. Create a free account at https://www.maxmind.com/en/geolite2/signup and accept the GeoLite2 terms (they include keeping the data updated and not redistributing it).
2. In your MaxMind account open *Download files* and download **GeoLite2 City** (or **GeoLite2 Country**) in *GZIP/tar.gz* format. Unpack it: you need the file ending in `.mmdb`.
3. Upload it to your server, preferably **outside** the public web folder, for example `/home/youruser/geoip/GeoLite2-City.mmdb`.
4. Settings → Geolocation: *Database* → **My own .mmdb file**, enter the absolute path, save. The status line shows the file name when it is readable.
5. To keep it fresh, MaxMind's free `geoipupdate` tool (with your licence key) can run from a server cron job and overwrite the file.

## Option 3 — any other MMDB
Any MaxMind-format City or Country database works (IPinfo, ip-location-db, a paid GeoIP2 file, …). FloLens understands the MaxMind/DB-IP layout and the flat layout used by ip-location-db.

## Which precision do I need?
- *Country only* works with a **Country** or a **City** database.
- *Country and city* needs a **City** database; with a Country file you will only get countries.

## Checking that it works
1. Settings → Geolocation shows **Ready**.
2. Visit your site from a network outside the server (a phone on mobile data, for example). Local and private addresses (127.0.0.1, 192.168.x.x) are never located.
3. After the first session appears, Visitors shows a *Location* column and the **Map** screen shows a circle. Sessions recorded before you enabled geolocation stay unlocated.

## Troubleshooting
- **"No database yet"**: press the download button again and check *Help → Logs* for the error (blocked outbound requests, unwritable uploads folder, HTTP 404 if the monthly file is not published yet).
- **Everybody is in the same place**: you are behind a proxy or CDN. Set *Privacy → Visitor IP source* to the right header (for example CF-Connecting-IP for Cloudflare).
- **Wrong city**: IP geolocation is approximate; mobile carriers, VPNs and company networks are located where their gateway is. Countries are far more reliable than cities.
- **Privacy**: location is personal data. Mention it in your privacy notice and prefer *Country only* when cities are not needed.

URLs and account steps on the DB-IP and MaxMind sites change from time to time; follow their current instructions if a page looks different.
