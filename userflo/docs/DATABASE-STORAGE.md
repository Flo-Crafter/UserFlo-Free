# Database & Storage

**UserFlo → Tools → Database & Storage** shows how much space UserFlo's tables use, how long detailed data is kept, what stays afterwards, and lets you run the cleanup or rebuild summaries. It does nothing by itself: opening it measures nothing, and every action that deletes or rewrites data asks you to confirm.

## What the numbers mean

- **Total, data, indexes, free space.** What your database reports for UserFlo's own tables, taken at the time shown. These are measurements, but of a moment; MySQL 8 may cache them for up to a day. Use **Refresh measurement** to ask again. UserFlo also measures once a day, during the cleanup.
- **Rows** are estimates. The database does not count every row of a large table, and neither does UserFlo (counting a table with millions of rows would slow your site). An estimate is shown with ≈.
- **Free space inside tables** is space left by deleted rows. The database reuses it for new rows, so table files often stay the same size after a cleanup. See *Rebuilding a table* below.
- **Unavailable** means your host does not let the plugin read that figure. Nothing is guessed or shown as zero.
- **Growth** appears after at least five daily measurements spread over a week. It is an estimate from your own history: traffic changes, and when old data starts to expire the tables level off.

## Detailed data and summaries

- **Detailed data** (deleted after the retention period): visitors, visits (sessions), page views, interactions (clicks, scrolls, downloads, forms, custom events), goal completions and the sales mirror.
- **Daily summaries** (kept for the number of years in Settings → Privacy, default 3): one set of counts per day. Totals (visits, page views, visitors that day, new visitors, logged-in visits, engaged time, one-page visits), top pages, sources, countries, devices, channels, **goal completions and their value, sales per currency (orders and revenue), interactions by type and custom events by name**. Counts only: no visitor, session, address or user agent.
- Heatmap counters and bot counters have their own periods.

What this means for screens: anything built from individual visits (journeys, visitor profiles, per-element click lists) only reaches back as far as the retention period. Year-on-year comparisons keep working from the summaries. A day's summary is written once the day is over, written again once its late-arriving data has settled, and **checked against the stored visits before any detailed data of that day is deleted**. If a day cannot be summarised, its detail is kept and the problem is shown on this screen.

## Retention profiles

| Profile | Detailed data kept |
|---|---|
| Minimal | 30 days |
| Standard | 90 days |
| Long | 365 days |
| Custom | any number from 1 to 3650 |
| Keep forever | nothing is deleted |

Your current setting is the one selected (the shipped default of 180 days shows as Custom). Choosing a profile changes nothing until you press **Save**, and if saving would make stored data eligible for deletion you see how many rows are affected and must confirm. Saving never deletes anything itself; the deletion happens at the next cleanup run. It cannot be undone, so keep a backup if you may need the data.

**Clicks and scrolls** can have a shorter period of their own (Settings → Privacy → "Keep clicks and scroll events for", or on this screen). They are the most numerous rows. Their daily totals stay in the summaries; the per-element click lists reach back only as far as this shorter period. The default, 0, keeps them as long as everything else.

## Actions

- **Run cleanup now.** The same cleanup as the daily one: missing daily summaries first, then deletion in batches of 1000 rows within a time budget. Safe to repeat; a second run while one is working does nothing.
- **Delete detailed data older than a date.** Choose a date at least 7 days ago. You see the cutoff and how many rows are affected, and must confirm. It runs in batches in the background; you can leave the page and return, and it continues. Daily summaries are never deleted by it. A day that is not safely summarised yet is held back.
- **Rebuild daily summaries.** Writes the summaries of the days you choose again from the detailed data that is **still stored**. Days whose detail is gone are skipped and their summaries are not touched; the oldest stored day (which may be partial) is skipped too. It uses your exclusion rules as they are today. Safe to repeat. Large ranges run in steps.
- **Rebuilding a table** (button per table, only when it has free space). Copies the table to give the space back to the server. This can take minutes on a large table and slows reports meanwhile (tracking keeps working). Files may not shrink on every database. UserFlo never does this automatically.

## If something goes wrong

The *Latest results* card shows the last successful cleanup and aggregation, and any outstanding problem. A failed step keeps the data it could not safely delete and the next run (daily, or "Run cleanup now") tries again. The plugin log (Settings → Help → Logs) has the details. If scheduled cleanup does not run on a quiet site (WP-Cron needs visits), opening the UserFlo admin queues a run when the last one is over 36 hours old.

## Developers

Code: `Privacy\Retention` (run, state, lock, jobs), `Insights\Summary` (roll, verify, rebuild), `Storage\Measure`, `Storage\Maintenance`, `Rest\StorageRoutes`, `Core\Lock`. REST: `GET /userflo/v1/storage` (figures from stored values), `POST` for `refresh`, `retention`, `run`, `purge`, `rebuild`, `step`, `optimize`. Reading needs the analytics capability; changes need `manage_options` and the REST nonce; destructive routes need `confirm: true`. Schema 20 adds `uf_summary.amount` and drops two redundant indexes (`sessions.started_at`, `pageviews.viewed_at`); to undo: `ALTER TABLE ... ADD INDEX`. Audit and measurements: `docs/DATABASE-AUDIT.md`, `docs/benchmarks/storage-retention.md`.
