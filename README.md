# FloLens – Visitor Journeys & Analytics

Visitor tracking and analytics that stay on your own WordPress site: visitor profiles, session history, page views, journeys, goals, a website audit, maps and more. Privacy-first, no external service, no limits in the free plugin.

This repository is the **free plugin** (GPL-2.0-or-later). Website: <https://flocrafter.com/flolens/>

## Layout

| Folder | What it is |
|---|---|
| `flolens/` | The plugin as installed on a site (PHP, tracker script, translations, built admin JavaScript). Zip this folder to install it. |
| `admin-ui/` | Source of the admin screens (TypeScript + React via WordPress `wp-element`). `flolens/assets/admin/app.js` is built from it. |

## Building the admin JavaScript

```bash
cd admin-ui
npm ci
npm run typecheck   # tsc --noEmit
npm test            # node --test
npm run build       # writes ../flolens/assets/admin/app.js
```

The other JavaScript files in `flolens/assets/` (tracker, settings, setup) are plain source.

## Branches

- `main`: released code.
- `develop`: work in progress; merged into `main` for a release, which is tagged `vX.Y.Z`.

## Extending

FloLens Pro is a separate add-on plugin that attaches to this one through documented filters, actions and admin "slots" (`admin-ui/src/slots.ts`). It is sold outside WordPress.org.

## Naming note

The plugin was called UserFlo before 0.47.0. Internal names (PHP namespace `UserFlo`, `userflo_*` options and hooks, the `uf_` tables) keep the old prefix so existing data and add-ons keep working.

## License

GPL-2.0-or-later, see `LICENSE`. Bundled third-party code and data are listed in `flolens/readme.txt`.
