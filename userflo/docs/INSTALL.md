# Installing UserFlo

## Requirements
- WordPress 6.0 or newer, PHP 7.4 or newer (only some combinations have been run; the verified and untested lists are in `docs/AUDIT.md` in the [project repository](https://github.com/Flo-Crafter/UserFlo/blob/main/docs/AUDIT.md)), MySQL 5.7+/MariaDB 10.3+ (InnoDB, utf8mb4). The PHP `mbstring` extension is optional: without it UserFlo shortens text with a UTF-8 safe fallback.
- The PHP `sodium` and `openssl` extensions are needed only for the optional GA4 integration (both ship with PHP 7.2+/7.4+ by default).
- WP-Cron should run (real cron recommended on busy sites) for retention cleanup, export housekeeping and integration syncs.

## Install
1. Download `userflo-<version>.zip` (the `beta` branch carries the latest build in `dist/`).
2. **Plugins → Add New → Upload Plugin**, choose the zip, **Install Now**, **Activate**. (Or unzip into `wp-content/plugins/`.)
3. On activation UserFlo creates four tables (`{prefix}uf_visitors`, `uf_sessions`, `uf_pageviews`, `uf_events`). On multisite, each site creates its own tables the first time it loads after network activation.
4. Open **UserFlo → Settings** and review: IP handling, consent mode, excluded roles/paths, retention (default 180 days) and the uninstall tickbox. Then browse the front end and open **UserFlo → Overview**.

## Upgrading
Install the new zip over the old one (Plugins → Add New → Upload → "Replace current with uploaded"). Database changes run automatically once, the first time the new version loads; existing data is preserved. Take a database backup first on production sites.

## Deactivating / uninstalling
- **Deactivate**: stops tracking and unschedules background jobs. Data is kept.
- **Delete (uninstall)**: removes integration credentials, cached third-party data and export files **always**; removes analytics tables and settings **only if** "Delete all UserFlo data when the plugin is uninstalled" is ticked (off by default). On multisite every site is processed.

## Page caching / CDNs
The tracker is a static script with its configuration inline, so it works on fully cached pages. The collection endpoint (`/wp-json/userflo/v1/collect`, or `?rest_route=/userflo/v1/collect`) must not be cached and must be reachable by `POST` from visitors (some security plugins block the REST API for anonymous users: allow this route). If you use a reverse proxy/CDN, set **Settings → Privacy → Visitor IP source** to the header your proxy sets, otherwise all visitors look like the proxy and abuse limits apply to the proxy as a whole.

## Uninstall data checklist
Export first (Tools → Export data) if you need the data. Remember WordPress' own privacy tools can export/erase data for logged-in users.
