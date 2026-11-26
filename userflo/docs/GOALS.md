# Goals and conversions

A goal is an outcome you care about: a visitor reaches a thank-you page, submits a form, downloads a brochure. **UserFlo → Goals** lists your goals with how many sessions completed each and the conversion rate. The Overview shows the same numbers in a *Goals* card, and the Campaigns report shows how many sessions of each campaign completed a goal.

## Goal types
- **A page is viewed** — compares the page path. *is exactly* (a trailing slash and upper/lower case make no difference), *starts with*, or *contains*. The query string is ignored unless your pattern contains a `?`. UserFlo only records `utm_*` and a few WordPress parameters (`p`, `page_id`, `cat`, `tag`, `post_type`, `product`, `paged`), so a pattern can only refer to those.
- **An event happens** — an event type (form, custom, click, download, outbound, media, commerce) and optionally a name. Forms and custom events (`UserFlo.track("name")`) are matched on their name; **downloads and outbound links are matched on the file path or the destination address**, not on the link text. An empty name means "any event of this type".
- **Funnel (Pro)** — 2 to 5 of your goals in order.

Scroll depth is not a goal type: it is recorded as the deepest point of each page view, not as a moment. Time on site is not a goal type either, because it is only known as a running total.

## Counting rules
1. **A session completes a goal at most once.** Further matching pages or events in the same session are ignored. This is also what makes retried or duplicated beacons harmless.
2. Goals are checked **as visits are recorded**, so only new page views and events are compared. Pausing a goal stops counting; resuming continues from then. Editing a goal changes what counts from then on.
3. **Editing is not retroactive.** To make the stored history agree with a changed goal use *Re-read history* on that goal: it deletes the goal's completions and rebuilds them from the page views and events you still have (your retention setting applies). It can take a while on a large site and can be repeated safely.
4. **Deleting a goal deletes its completions** (and any funnel that used it). Your analytics data is not touched.
5. **Period and rate.** The completions of a period are the sessions that *started* in the period and completed the goal at any time in the session. The conversion rate is that number divided by **all sessions that started in the period**, so it never exceeds 100% and a session that crosses midnight counts on the day it started. Periods use the site time zone, start inclusive and end exclusive.
6. **Attribution.** A completion belongs to the session. Splitting by campaign or source uses the campaign the session arrived with (see Campaigns). There is no cross-session, cross-device or multi-touch credit: a visitor who sees an advert today and converts next week in a new session is counted for the second session's own source.
7. **Consent and privacy.** A goal needs a recorded visit, so visitors who are not tracked (no consent, GPC/DNT, excluded roles or pages) are never counted. A completion stores only internal ids and the time. It is removed with its session by retention, *Delete visitor* and WordPress' erase tool, and "Delete all analytics data" removes all completions but keeps your goal definitions.

## Amounts, time frames and targets
- **Amount (every edition):** *Count as completed after N times in one session*. With 3, a session completes the goal when it has opened the page (or triggered the event) three times. The amount is counted from the stored page views and events of that session; with 1 the first time counts, as before.
- **Time frame (Pro):** an optional first and last day (site time zone). Outside it the goal records nothing. *Re-read history* only reads that period.
- **Finish within (Pro):** the matches must all happen within X minutes of the session starting.
- **Targets (Pro):** choose *Target for a time window* to compare a count with a number, for example "on Tuesdays more than 500 downloads" or "every evening 18:00-21:00 fewer than 200 visitors". Choose what to count (page views or events, sessions, or distinct visitors), *more than / at least / less than / at most*, the number, the weekdays (none ticked = every day) and an optional time of day. Each matching day is judged on its own and the Targets card shows how many of them met the target. A window still running is not judged; a window before UserFlo had data says so. Only the newest 60 windows of a period are judged. A window that crosses midnight needs two goals. To count every page use *A page is viewed*, *starts with*, `/`.
- A funnel step that needs an amount is reached when the amount is reached; its moment is the last match counted.

## Free and Pro
- **Lite:** up to 3 active page and event goals, completions and conversion rates (Overview, Goals, Campaigns, e-mail report section), split by campaign or source.
- **Pro:** time frames, finishing within a time, targets for a weekday and time window, an optional **value** per goal (shown as completions × value; it is not a revenue figure), **funnels**, and splitting by device, country and new or returning visitors. Pro integrations can use the `userflo_goal_completed` action (below).

## For developers
`do_action( 'userflo_goal_completed', $goal, $session )` fires once when a session first completes a goal. `$goal` has `id`, `name`, `type` and `value`; `$session` has `session_id`, `visitor_id` and `at` (UTC). No personal data is passed. Forwarding completions to GA4, GTM or Clarity is not part of this release; it will use this action and follow the visitor's consent.

## Limits
- A funnel reads the stored page views and events of the sessions in the period (up to 200,000 per step) and says when it was cut.
- A funnel step is "reached", not "reached directly after": other pages may come in between.
- Goal checks add a few comparisons per new page view or event and no database query when you have no goals.
