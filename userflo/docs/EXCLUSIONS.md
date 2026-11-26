# Exclusions

One place to decide what UserFlo tracks, what it counts, and which audit findings you have already accepted. **UserFlo → Settings → Exclusions** lists every rule; the same dialog opens from the small exclude button at the end of a row on **Pages** and **Statistics** (it appears when you hover the row or focus it with the keyboard, and is always visible on touch screens). Nothing is applied until you confirm, and the dialog shows what the rule would have covered in the last 30 days.

## The three kinds of exclusion
They are independent. Choosing one never switches another on.

| Kind | What happens | What it does **not** do |
|---|---|---|
| **Stop tracking** | From now on the matching page or interaction is not recorded. For a page the script is not even loaded; for an element the browser does not report it (CSS selectors and exact element identifiers) and the server drops it before it is stored or counted (also labels, addresses and sections). | It does not remove data that was already collected. That data still counts in statistics unless you also add a "leave out of statistics" rule. |
| **Measure, but leave out of statistics** | Collection continues, subject to your consent, privacy and retention settings. The data is left out of totals, rankings, rates, trends, reports, exports and journeys, at query level. | It does not stop collection and it does not delete anything. Pause or delete the rule and the numbers come back, history included. |
| **Hide audit findings** | The scan and the inventory stay as they are; matching findings stop counting as issues, do not colour a page, do not appear in the priority list and are listed as excluded. | It never removes a page or element from analytics. *Offered with Pro's audit findings (Lite's three basic findings are not worth hiding).* |

Rules for a page or an interaction apply to logged-in and anonymous visitors alike. An exclusion can only ever collect **less**: it cannot switch consent off, bypass Global Privacy Control or collect anything that would not be collected otherwise.

## What a rule matches
- **A page**: by path. *is exactly* (a trailing slash and upper/lower case make no difference, the query string is ignored), *starts with*, or *contains*. Rules that would cover the whole site (`/` with starts with/contains) are refused; to stop tracking altogether use Settings → Tracking.
- **An interaction** (click, link, download, outbound link, form, media, custom event): by one of
  - **element identifier**: the stable identifier the tracker builds from tag, id, class or data attribute and a few parents (for example `div#box > button.beta-btn`). Preferred: it does not depend on wording;
  - **label** (the visible text), **link address or file**, or **section** it sits in;
  - **CSS selector**: applied in the visitor's browser, so it can only *stop tracking*. Selectors using `:has()`, `:is()`, `:where()`, `:not()` or `:nth-…()`, very long selectors, or selectors that would match the whole page are refused.
  A rule can be narrowed to one kind of interaction and to pages under a path.
- **An audit finding** (when available): by finding type, optionally for one element or for pages under a path. One site-wide rule suppresses every matching occurrence and leaves unrelated findings visible.

Matching is exact, *starts with* or *contains*, always case-insensitive. There are no wildcards and no regular expressions, so a stored rule cannot be slow or unsafe to evaluate. Overlapping rules simply add up: data is left out when **any** active rule matches it; there is no precedence to remember. An identical rule cannot be added twice.

## Where "leave out of statistics" applies
Applied in the queries, not in the interface:
- Overview: page views, daily chart, most viewed pages, devices and sources are unchanged because they count visits; page views and the page ranking leave excluded pages out.
- Statistics: most viewed pages, clicked buttons and elements, internal and outbound links, downloads, forms, custom events, entry and exit pages, common journeys.
- Journeys (entrances, exits, flow, paths) and the Calendar (page views and events), including the Pro day and hour detail; Highlights; the Map's location details; the Focus section; scheduled e-mail reports (they use the same queries); the Pro WordPress reports and e-mail/phone click report; the CSV and JSON dataset exports; shared pages and data feeds (they call the same queries).
- A rule for a page also leaves out the interactions that happened **on that page**.

**Not changed** (by design): sessions, visitors and new visitors, traffic sources, countries and devices (a visit is a visit, even if one of its pages is excluded); goal completions and WooCommerce orders (goals are rules you wrote yourself; orders come from WooCommerce); the visitor profile, session timeline and the list of visits to one page (records of what one person did); Live; achievements; and the personal-data export and erase tools (they always cover everything). Heatmap counters follow the *stop tracking* rules for pages and CSS selectors.

Cached reports (two minutes) are cleared when a rule changes, so a new rule shows at once.

## Historical data
- Creating, changing, pausing or deleting a rule **never deletes analytics data**.
- *Stop tracking* cannot recover what was never collected; deleting the rule only starts collecting again from then on.
- *Leave out of statistics* is applied when a report is calculated, so it works on history too and is fully reversible.

## Free and Pro
- **Lite:** the full feature, with no limit on rules.
- **Pro:** the same; Pro adds the audit-finding exclusions that go with its fuller audit.
Settings made before this screen existed (excluded paths, excluded roles, "never track clicks inside" selectors in Settings → Tracking and Privacy) keep working exactly as before and are listed read-only on the Exclusions screen. They were not copied into the new list.

## Permissions
Only administrators (`manage_options`) can see or change rules; the exclude buttons are shown to them only. Every change goes through the REST API with a nonce.

## For developers
- `UserFlo\Exclusions\Service::suppresses( array( 'type' => $code, 'url' => $path, 'element' => $id ) )` is how an audit asks whether a finding is suppressed. Make the audit mode visible with `add_filter( 'userflo_exclusions_audit_available', '__return_true' )`.
- `UserFlo\Exclusions\Sql::events( $alias )`, `::pageviews( $alias )` and `::urls( $alias, $column )` return the ` AND NOT (…)` fragment a new query over those tables must append; they return an empty string while no rule applies.
- `do_action( 'userflo_exclusions_changed', $rule_id )` fires after every change. Rules live in the `uf_exclusions` table (configuration, kept by "Delete all analytics data") and are compiled into one autoloaded option, so tracking does no extra query.

## Limits
- CSS selectors and exact element identifiers reach the browser inside the page's inline configuration. A page served from a page cache keeps the list it was cached with until the cache is cleared, so a new **CSS-selector** rule applies to such a page only after that. Rules by identifier, label, address or section are also enforced by the server, so they take effect immediately even on cached pages.
- Element identifiers are generated from the markup; if a theme changes class names, the rule no longer matches that element. The dialog shows the identifier so you can see what is being matched.
- Page rules for *stop tracking* compare the path; they do not see a page's title.
