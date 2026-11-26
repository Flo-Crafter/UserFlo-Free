# Journey analytics

> **Lite and Pro.** Every edition has the **basic summary** below (entrances, recorded exits and most common complete paths, each as a list of up to 100, for the last 24 hours, 7 or 30 days). Everything marked **(Pro)** is the full analysis.

**UserFlo → Journeys.** Where visits begin (entrances), the pages they move through (common journeys) and where they end (recorded exits), for a day, week, month or custom range. It reads the page views UserFlo already stores; it adds no tracking.

## What is counted
- **Sessions.** Every number is a count of sessions, never page views, unless a label says "views". A session is counted once however many pages it viewed and once in every step it reaches.
- **Which sessions.** Sessions that **started** in the period (the same rule as the rest of UserFlo) and have at least one recorded page view. Sessions with none (for example a visitor who left within the first couple of seconds, before the tracker reported) are mentioned but not included.
- **Path.** A session's page views in time order. Reloading the same page does not add a step; coming back to a page later does (A → B → A is three steps).
- **Time zone.** Day, week and month boundaries are your site's time zone (Settings → General), as in the Overview and the Calendar.

## Entrances
The first page recorded in a session. For each: how many sessions began there, their share of all sessions, and the most common next steps (or "session ended"). With **Compare with the previous period** on, the change in each page's share (in percentage points) is shown, but only when the previous period of equal length is fully covered by recorded data, was not sampled and had enough sessions. Otherwise the screen says why it is not shown.

"First recorded page" is exactly that: if a visitor left a page before the tracker's first report (about two seconds), that page is not recorded and the next one counts as the entrance.

## Common journeys (Pro: the flow)
Every edition lists the **most common complete paths** (up to 100 in both editions). The flow diagram and the transition table are Pro.

Columns are the first, second, third… page of a session (five steps). A bar's height and a link's thickness show how many sessions reached that step or took that connection. Sessions that stopped appear as **Recorded session exit** in the next column; sessions longer than five pages end in **Continues beyond step 5**. Each column shows its seven busiest pages and groups the rest as **Other pages**. The same numbers are in the **Table** view and in the list of most common complete paths, which is the accessible alternative to the diagram.

Choose a page to keep only the sessions that viewed it at that step, or a connection (in the diagram or with **Filter** in the table) to keep only those that went on to the next page. Hovering only highlights; nothing is requested from the server until you choose.

## Recorded session exits
The last page recorded in a session. UserFlo only knows that **no later page view was recorded**; it cannot tell whether the visitor decided to leave, the page was the goal, or the browser was closed. Two numbers are shown with their denominators:
- **Share of all recorded exits**: exits on this page ÷ all recorded exits in the view.
- **Exits per view of this page**: exits on this page ÷ page views of this page in these sessions.

"Common paths leading here" lists the last three pages of the most frequent routes to that exit.

## Working together (Pro)
- Choosing an **entrance** filters the flow and the exits; the entrance list keeps showing every entrance so you can switch.
- Choosing an **exit** filters the flow and the entrances; the exit list keeps its alternatives.
- Choosing a **page or connection in the flow** filters entrances and exits.
- Selections combine. The chips above the results show them; **Reset selection** removes them all. The period and the filters stay as they are.

## Filters (Pro)
Device, country (when geolocation is on), logged-in or anonymous visitors, and **Group pages by first folder** (`/blog/post/` and `/blog/other/` both count as `/blog/`, which is useful on large sites).

## Periods
- **Lite:** the same rolling periods as the other reports (last 24 hours, 7 days, 30 days).
- **Pro:** Day, Week and Month with previous/next buttons, and Custom ranges up to 366 days, so older history can be analysed. The comparison with the previous period is Pro.

Older data is never deleted by the edition; Lite simply does not offer the longer periods.

## What each edition shows
| | Lite | Pro |
|---|---|---|
| Entrances | up to 100, with the most common next step | top 15, with up to three next steps and the change against the previous period |
| Recorded session exits | up to 100, share of exits and exits per view | top 15, plus the common paths leading to each exit |
| Most common complete paths | up to 100 | up to 100 |
| Flow diagram and transition table | – | yes |
| Exploring by selecting an entrance, page, connection or exit | – | yes |
| Filters (device, country, logged-in/anonymous, group by folder) | – | yes |
| Periods | 24 hours, 7 and 30 days | day, week, month, custom (366 days) |

The counting rules are identical in both editions, and so is the data behind them: only what is shown differs.

## Differences from the Statistics screen
The Statistics screen reads each session's stored entry and exit address (the address of the first and of the *last report received*). Journey analytics derives both from the ordered page views, which is more precise when a visitor has several tabs open or a delayed report arrives late, so a few exits can differ between the two screens. Journey analytics also looks at every session of the period, not a sample of recent ones.

## Limits
- **Large sites.** Up to 50,000 sessions are analysed per request, the most recent first. When a period has more, the screen says so and suggests a shorter period.
- **Retention.** Sessions that began before your data-retention cutoff are left out, because their first page views may already be deleted.
- **Tabs.** Several tabs in one session are interleaved by time; they are not told apart.
- **Outcomes.** No conversions, revenue or attribution are shown: UserFlo does not record them for these paths.
- **Caching.** Results are cached for two minutes per period, filters and selection.
