# Setup wizard

On first activation FloLens opens a short wizard (about two minutes). It asks only for the basics and explains each option: **what it does, why you might want it, and how much it affects your site**. Nothing is saved until you press **Finish setup** on the last step; **Skip the wizard** changes nothing.

Run it again at any time from **FloLens → Settings → Run the setup wizard again**. It opens pre-filled with your current settings, and it only changes the options it asks about (everything else, such as excluded paths or consent settings, is kept).

| Step | Options | Effect on your site |
|---|---|---|
| Logo and style | Company logo, dashboard style (live preview) | None on the public site: admin screens, shared links and reports only |
| Language | Dashboard language, **report language** | Admin and exported reports only |
| Site type | General, store, blog, company, events, forum, courses, portfolio | Display only: adds a Focus section to the Overview and reports |
| Privacy | IP handling, retention days, privacy signals, linking visitors to accounts (and earlier browsing) | Lower data volume; fewer visitors counted when privacy signals are respected |
| Features | Geolocation (off, country, city), heatmaps, dashboard sections | Geolocation downloads a database in the background (country: a few MB; city: over 100 MB, roughly); heatmaps add a ~1.5 KB script and one small request per page view |
| Integrations | Reuse settings of Google Site Kit, Clarity or PostHog plugins if found | No extra tags; keys for other services are entered later under Integrations |

## Report language

Reports (PDF and PNG) can be in a different language from the dashboard. Set the default under **Settings → Translations → Report language** (or in the wizard) and override it for a single report in **Reports → Report language**. Only languages that are installed (English, Spanish, Hungarian) or that you added under Translations are offered. Text, dates and numbers in the report use that language; names of pages, buttons and links are your own data and are not translated. Wording edited in the translation editor is used in reports too.
