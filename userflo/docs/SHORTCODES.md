# Front-end shortcodes

Enable/disable under Settings → Sections → *Front-end shortcodes*. Tags are also accepted as `[UserFlo_Overview]` etc.

| Shortcode | What it shows | Options |
|---|---|---|
| `[userflo_overview]` | key numbers, daily chart, popular pages, traffic sources | `days` (1, 7, 30, 90, 365), `show="kpis,daily,pages,sources"` |
| `[userflo_statistics]` | most viewed pages, clicked buttons, internal/outbound links, downloads, devices, browsers, common journeys | `days`, `show="pages,buttons,links,outbound,downloads,devices,browsers,journeys"` |
| `[userflo_achievements]` | achievement boxes with progress | `status="all|unlocked"`, `category="traffic|engagement|journeys|identity|interactions|devices|rhythm|setup"`, `limit` |
| `[userflo_map]` | static world map with visitor counts (no zoom) | `level="continents|countries|cities"`, `region="world|EU|AS|AF|NA|SA|OC"`, `days` |

Common options: `access="admin|members|public"` and `theme="light|dark"`.

- `admin` (default): only users who may view analytics (capability `userflo_view_capability`, default manage_options) see anything; everyone else gets an empty output.
- `members`: any logged-in user. `public`: everyone.
- Output is rendered on the server from the same cached aggregates as the dashboard (refreshed every 2 minutes). It never contains names, e-mails, IP addresses or visitor ids.
- Non-public output sends `DONOTCACHEPAGE`, so page-cache plugins that honour it will not store it. Public output may be cached by your page cache and then shows older numbers.
- Respect the section switches: a shortcode renders nothing when its section (Statistics, Achievements, Map) is turned off.
- Styling uses `assets/css/share.css` (classes prefixed `us-`) and inherits your theme's font; override the CSS variables `--c-bg`, `--c-text`, `--c-muted`, `--c-border`, `--c-bar` on `.us-sc` to match your design.
