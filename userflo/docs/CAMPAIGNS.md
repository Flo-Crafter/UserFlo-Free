# Campaigns (UTM)

UserFlo reads the standard campaign parameters from the link a visitor arrived with and shows which campaigns bring traffic and how that traffic behaves. It is on **Statistics → Campaigns**.

## What is captured
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, from the **first page of a session** (the landing page). Later pages never change a session's campaign.
- Values are lower-cased, decoded, cleaned of markup and control characters, and cut to 100 (source, medium) or 150 characters (campaign, term, content). If a key appears twice, the first one wins.
- Nothing else from the address is kept. UserFlo has always dropped every query parameter except `utm_*` and a short list (`p`, `page_id`, `cat`, `tag`, `post_type`, `product`, `paged`); the server now applies the same rule, so click ids (`gclid`, `fbclid`), tokens or e-mail addresses in a link are never stored. Developers can change the allowed list with the `userflo_query_params` filter.
- It follows your consent settings. If tracking does not start (no consent, GPC/DNT, excluded page or role) nothing is recorded, including the campaign. The values are part of the session row, so retention, "Delete visitor" and WordPress' personal data tools remove them with it, and exports include them.

## Attribution rule (the only one)
A session belongs to the campaign it **arrived with**. There is no first-touch or last-touch across visits and no credit sharing. If a visitor returns later through another campaign, that visit is counted for the other campaign. A visitor's first landing page is still available on their profile.

## Reports
- **Lite:** sessions, visitors, pages per session, single-page rate and average engaged time, grouped by campaign, source, medium, content or term. A visit with no utm values is not a row; the report says how many sessions in the period carry a tag.
- **Pro:** combined groupings (source / medium and source / medium / campaign) and a comparison with the previous period of the same length.
- **Visitors → Filters → Campaign (Pro):** visitors who had a session from a campaign.
- The Overview's *Channels* still come from the referrer and utm values (a grouping heuristic, see the Overview help); campaign rows use your own utm text.

## Older visits
Visits recorded before this feature are read from the landing page UserFlo already stored. After the update a small background job fills them in 500 at a time (Tools → Status shows its progress) and removes itself when it is done. Until then the report shows a notice and earlier periods can grow. Nothing is lost if it is interrupted; it resumes.

## How this differs from Google Analytics 4
- A session has **one** campaign, taken from its landing page. GA4 attributes sessions and conversions with its own models (data-driven, last click, across sessions and devices).
- UserFlo only sees visitors it is allowed to track; GA4 uses its own consent and modelling.
- Values are exactly the text in your links (lower-cased and cleaned). There is no campaign matching, channel rules for the campaign list, cost data or auto-tagging.
- Visits that arrive without utm values are not "direct" in this report; use the Overview channels for that.
- Conversions per campaign appear once goals exist (a later release); UserFlo does not estimate them.

## Known limits
- If the first beacon of a session comes from a page without utm values (for example consent was given on the second page), the session has no campaign.
- Sessions that started with a utm-tagged URL longer than 500 characters before this release may have lost the tail of the query.
- Reports are cached for two minutes.
