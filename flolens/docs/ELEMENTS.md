# Elements

**FloLens → Elements** lists the buttons, links (internal, outbound, files, e-mail and phone), forms, images, videos and other controls the website audit found on your pages, together with how visitors use them. It reuses the audit's inventory and the interactions FloLens already records: there is no second scanner and no extra tracking.

## What you see
- **The list**: label, kind, the element's identifier (and address), how many pages it is on, **interactions**, **visitors** and the split **logged-in / anonymous**. Filters: period, kind, audience (everyone, anonymous, logged-in), only elements on one page, search; sortable columns.
- **Repeated elements**: the same navigation link on 30 pages is one element with 30 locations, not 30 rows. Several instances on one page are counted (×3).
- **Not in inventory**: visitors used something the scan did not find (a button added by script, or a page not scanned yet). It is shown, marked, so interactions are never lost from view.
- **An element's page**: totals, an interactions-per-day chart (with a table version), where it is used (pages with the most interactions), where it is found (pages it is on), and the audience split. **Pro** adds the pages visitors came from and went to around it, how often sessions that used it completed your goals, and its audit findings.

## How an interaction is matched to an element
The tracker records, for each click, the element's **identifier** (tag, id/class/data attribute, up to four parents), its label and, for links, files and videos, the destination. The audit computes the same identifier from the page's HTML (a browser test compares the two on a real page), and an interaction belongs to an element when the identifier and, for links, files and media, the address match; buttons are also told apart by their label. **Two elements the tracker cannot tell apart share their numbers**, and a rule that excludes an element by identifier covers every element with that identifier. Identifiers can differ when a script changes classes after the page loads.

## Goals and journeys (Pro): what is claimed
"Sessions that used this element completed *Thanks* in 50% of cases; other sessions in 5%" is an **observed difference**, shown only with at least 30 sessions that used the element, 100 sessions in total and 30 that did not. It is not evidence that the element causes the conversion (people who are about to convert are the ones who use the button). The "where visitors went next" lists describe the most recent 1,500 uses in the period.

## Exclusions
Every row has the exclude button (administrators). The three behaviours are the shared ones: stop tracking the element, leave its interactions out of statistics (it stays in the inventory with no counted interactions), or, for its findings, hide them. All reports, journeys and exports follow the same rules. See *Exclusions*.

## Free and Pro
- **Free**: the full inventory, interaction totals, visitors, trend, the logged-in / anonymous split, all filters, exclusions. Ranges follow the other reports.
- **Pro**: journeys around an element, goal comparison, findings per element, longer ranges.

## Privacy
Elements shows aggregates per element. It never lists visitors, IP addresses, e-mail addresses or form contents; links to e-mail and phone numbers are recorded as the scheme only. Interactions follow consent, Do Not Track/GPC, exclusions and retention exactly as everywhere else.

## Limits
Form *starts* and video *progress* are not recorded by the tracker (only submissions and plays), so Elements shows what exists and does not invent more. Counts of visitors are exact per element; in a list that merges two events with the same identifier the visitor count is the larger of the two (a lower bound).
