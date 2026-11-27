# Website audit and Page Intelligence

FloLens reads **your own pages**, a few at a time and as an anonymous visitor would see them, to list the pages and the elements on them (buttons, links, forms, images, files, videos) and to check what it can. It never changes your site, your SEO settings or any other plugin. The list it builds is shared: **Pages** (health), **Elements** and **Exclusions** all use the same inventory, so a finding, an element and a visitor's click refer to one thing.

## What it does
- **Scans.** A background task (WP-Cron) takes at most **3 pages every 5 minutes**, never two scans at once, and stops for the day after a daily cap of 500 pages, the same in every edition. A page is read again after 14 days, or sooner with *Scan again* (one scan per page every ten minutes). A page whose HTML has not changed is not parsed again. Failures are retried with a growing delay.
- **Which pages.** The pages your visitors actually viewed (most viewed first, last 30 days) and the home page, up to a safety cap on the inventory, the same in every edition. Pages you told FloLens not to track (Exclusions → stop tracking, or Settings → Excluded paths) are not scanned.
- **How it fetches.** Only addresses of this site, built from your home URL and a stored path; no cookies; redirects are followed by hand and only while they stay on your host, so the scanner cannot be pointed at another server. A page that answers 401, 403 or 503 (a login wall, maintenance mode), is not HTML, or is over 2 MB is skipped without making any findings. Sites behind HTTP authentication can adjust the request with the `userflo_audit_http_args` filter; `USERFLO_AUDIT_OFF` stops the scanner entirely.
- **Rendered output.** It judges the HTML the server sends, which includes what Divi, Elementor, shortcodes and your theme output. What a script adds later in the browser is not seen.

## Findings
Each finding has a **class**, so you can see how sure FloLens is:
- **Verified**: a fact read from the page, shown with its evidence (for example *image without alt text*, with the file name).
- **Warning**: usually a problem, not always (a long title, a link that says "read more").
- **Recommendation**: advice that depends on judgement; it never counts as an issue.

A page is **red** when it has an active verified finding, **amber** when it has only warnings, **green** otherwise, and grey when it has not been scanned. The health is always written as text too.

| | Lite | Pro |
|---|---|---|
| Page loads (HTTP status), has a title, is open to search engines | ✔ | ✔ |
| Meta description, canonical, headings, social sharing tags | | ✔ |
| Images without alt text, links and buttons without a name, vague link text, form fields without labels | | ✔ |
| Health indicator on Pages and the *Page health* tab (summary, list, filters) | ✔ | ✔ |
| Prioritised "pages that need attention", findings by kind, a report per page (audit + views + journeys) | | ✔ |

A page that asks not to be indexed (noindex) is not nagged about descriptions or canonical tags.

## Page Intelligence (Pro)
- **Pages that need attention**: (1) busy pages (at least 20 views in the period) with a verified finding about how the page presents itself; (2) landing pages (at least 20 visits started there) where visitors who arrive leave without a second page much more often than on a typical landing page. Each entry shows the numbers it used. These are observations: FloLens does not say that a finding is the reason for how a page performs, and it promises nothing about search rankings.
- **Page report**: the findings (with why they matter and what to do), the page's views, visitors, engagement, entrances and exits, the pages visitors came from and went to, the page's elements, and a link to edit the page.
- **SEO plugins**: Yoast SEO and SEOPress are detected independently and read-only. If both are active FloLens flags it for review and does not guess which is right. The checks work with no SEO plugin at all. (FloLens does not read the plugins' stored settings; the rendered page is what is judged.)

## Exclusions and findings
A finding can be excluded with the exclude button next to it: this element on this page, this element everywhere, this kind of finding on this page, or this kind across the whole website. One rule covers every matching occurrence. Excluded findings do not colour a page, count as issues or appear in the priority list, but stay listed (*Excluded findings*) and are preserved by rescans. Excluding a finding does not stop tracking the element or remove it from Elements. See *Exclusions*.

## Data and privacy
The inventory stores page addresses and titles, element labels, identifiers and (for links) address paths. **No visitor data is stored or used.** For e-mail and phone links only the scheme (`mailto:`, `tel:`) is kept, never the address or number. The tables (`uf_audit_pages`, `uf_audit_elements`, `uf_audit_locations`, `uf_audit_findings`) are kept by "Delete all analytics data" like other configuration-like data and removed by uninstall when you choose to delete data.

## Turning it off
Settings → Components → *Website audit* stops the scanner (the schedule is removed) and what was found is kept. *Elements* and *Page Intelligence* need the audit and stop with it. Hiding an entry from the sidebar changes nothing; the module keeps working. If Pro is deactivated or its licence lapses, Lite shows only the three basic findings and the inventory continues.

## Limits
- Identifiers are generated from the markup the server sends. If a theme adds classes with JavaScript, the element in your browser and in the inventory can differ.
- The scanner reads at most 3 pages per 5 minutes, so a large site takes time; the daily cap applies.
- Core Web Vitals, rankings, keyword research, structured-data validity, broken external links and sitemaps are not checked.
- Not verified here: Yoast SEO and SEOPress themselves (neither could be installed), sites behind a firewall or HTTP authentication, and very large sites.

## Accessibility
The audit also checks some accessibility problems against WCAG 2.2 (levels A and AA): images without alt text, links, buttons and fields without names or labels, the page language and more in Pro. It reports each result as an issue, a potential issue or a manual review item, and never says a page conforms. See the guide **Accessibility checks** (Settings → Help).
