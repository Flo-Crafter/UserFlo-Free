# UserFlo

Visitor tracking and analytics that stay on your own WordPress site: visitor profiles, session history, page views, journeys, goals, a website audit, maps and more. Privacy-first, no external service, no limits in the free plugin.

This repository is the **free plugin** (GPL-2.0-or-later). Website: <https://flocrafter.com/userflo/>

## Layout

| Folder | What it is |
|---|---|
| `userflo/` | The plugin as installed on a site (PHP, tracker script, translations, built admin JavaScript). Zip this folder to install it. |
| `admin-ui/` | Source of the admin screens (TypeScript + React via WordPress `wp-element`). `userflo/assets/admin/app.js` is built from it. |

## Building the admin JavaScript

```bash
cd admin-ui
npm ci
npm run typecheck   # tsc --noEmit
npm test            # node --test
npm run build       # writes ../userflo/assets/admin/app.js
```

The other JavaScript files in `userflo/assets/` (tracker, settings, setup) are plain, unminified source.

## Branches

- `main`: released code.
- `develop`: work in progress; merged into `main` for a release, which is tagged `vX.Y.Z`.

## Extending

UserFlo Pro is a separate add-on plugin that attaches to this one through documented filters, actions and admin "slots" (`admin-ui/src/slots.ts`). It is sold outside WordPress.org.

## License

GPL-2.0-or-later, see `LICENSE`. Bundled third-party code and data are listed in `userflo/readme.txt`.
