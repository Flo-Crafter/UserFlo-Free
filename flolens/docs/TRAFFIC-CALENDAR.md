# Traffic calendar

**FloLens → Calendar** shows your traffic as a calendar of coloured tiles. Quiet days are pale, busy days are vivid. Open any day to see what happened hour by hour.

## Month and week

- The calendar opens on the **current month**. Switch to **Week** with the buttons at the top right.
- The arrows move by a month (Month view) or a week (Week view). **Today** jumps back to the current month or week.
- The heading and the line under it always show the exact date range and the site timezone.
- Weeks start on the day set in **Settings → General → Week Starts On**, dates and numbers follow the site language, and every day is calculated in the **site timezone** (Settings → General → Timezone).

## Choosing what the colours mean

| Metric | What is counted |
|---|---|
| **Page views** (default) | Every page a visitor loads. |
| **Sessions** | Visits, counted on the day and hour the visit *started*. |
| **Unique visitors** | Different visitors whose session started in that day. A returning visitor counts once per day. Hourly values do not add up to the day for this reason. |
| **Events** | Recorded interactions: clicks, form submissions, downloads and custom events. |

These are the same definitions as the Overview, so the numbers agree with it. Only the metric you select is calculated; the others are fetched when you choose them.

### How the colour is chosen

Colours show how a day **ranks among the days on screen** (four steps, darkest = the busiest quarter), not the distance to the maximum. One very busy day therefore cannot make every other day look the same. The legend shows the real value range behind each step. Every tile also carries its number, a tooltip (hover or keyboard focus) and a full text label for screen readers, so colour is never the only signal.

### Days without data

- A **dashed, empty tile** means nothing was recorded for that day: FloLens had not started tracking yet (or older data was removed by your retention setting), or the day is in the future.
- A tile showing **0** is a genuine zero: tracking was running and nothing happened.
- A **dashed outline with a value** is a *partial day*: today, or the day tracking started.

## Opening a day

Select a tile. It grows into the day view, and the same motion plays backwards when you close it. With *reduced motion* switched on in your system settings the view simply appears. Keyboard focus moves to the day heading, **Escape** or **Back to calendar** closes it and returns focus to the tile you opened.

The day view contains:

- the date, timezone, and arrows for the previous and next day;
- the four daily totals (click one to switch the metric);
- a **24-hour heatmap** for the selected metric, coloured with the same method as the calendar. On the two days a year when the clock changes, the heatmap has 23 or 25 cells and a repeated hour is marked;
- **top pages** (with links to the page detail), **busiest hours**, **traffic sources** (where the sessions that started that day came from) and **devices**.

### Selecting an hour

Select an hour (or one of the busiest hours) to see its exact time range, its totals, the top pages and the events recorded in that hour. **Escape** or **Clear hour selection** returns to the full-day summary.

## Keyboard

Tab into the calendar once, then: arrow keys move between days, **Home / End** go to the start / end of the week, **Page Up / Page Down** go to the previous / next month (or week), **Enter** opens the day. The hourly heatmap uses the arrow keys, Home and End in the same way.

## Limits

- Sessions, visitors and hours are attributed to the moment the visit *started* (visitors and sessions) or the page was *viewed* (page views), exactly as elsewhere in FloLens.
- Data older than your retention period no longer exists and shows as "no data".
- A half-hour timezone (for example India) is supported: hours begin at the local hour.
- Revenue, conversions and attribution are not shown because FloLens does not record them.
- The calendar needs the **Statistics** module (Settings → General → Modules).
