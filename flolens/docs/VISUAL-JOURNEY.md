# Visual journey

> **Pro.** The visual journey is part of FloLens Pro. FloLens Lite shows the chronological **Timeline** of every session (every event of the session, and every session of a visitor) and the same recorded sign-in event in it. The visual view is Pro.

**FloLens → Visitors → open a visitor → open a session → Visual journey.** It draws the pages one visit went through as a connected flow. It is a different view of the same data as the **Timeline**, which stays available and is still the default; the toggle remembers your choice in your browser.

Nothing new is collected for it. Everything comes from page views, events and identity records FloLens already stores.

## What you see
- **Page cards**, in the order the pages were viewed, joined by arrows (a moving dot shows direction; it stops with *reduce motion*, when the tab is hidden and for connectors that are off screen).
- On each card: time, title, address, **engaged** and **open** time, how far the visitor scrolled and the events recorded on that page. Select a card (or press **Enter** / **Space**) for the full address, the exact time, the referrer, every event and the time definitions below.
- **Entry page** on the first page and **Last recorded page** on the final one. They are only shown when FloLens really has the start or the end of the session: if a display limit hides the start (see *Lite limits*), no entry page is claimed.
- A connector shows the **elapsed time** to the next page view.

## Time: three different things
| Label | Meaning |
|---|---|
| **Engaged** | Active time on the page: the page was visible and the visitor was active. The same measure as in Statistics and Reports. |
| **Open** | How long the page stayed open, active or not. |
| **Elapsed** (on the arrow) | Time from this page view to the next one. It includes idle time and is **not** reading time. |

If a page never reported back (for example the browser was closed abruptly) FloLens says **Time on page not recorded**; it does not show 0 seconds. The last page of a session has no elapsed time because there is no following page view.

## Anonymous to known
If the visitor signed in or registered during the session, a dashed **Identity change** card appears at the recorded moment, for example *Unknown visitor → Logged in as Ada*. Pages before it are tagged *Anonymous at this point*; pages after it *As Ada*. The text is always written out; colour and icons are never the only signal.

What each wording means, and what it is based on:
| Wording | Based on |
|---|---|
| *Logged in as …* | a login recorded by WordPress during the session, at that exact time |
| *Registered and logged in as …* | the same, and the account was created within about ten minutes before the sign-in (from the account's creation time) |
| *Linked to account …* | the browser was linked to the account at that time without a login form (checkout, registration auto-login, social login) |
| *Registered as …* | the same, and the account was created moments before |
| *Signed in — no account is linked to this browser* | a login was recorded but identity linking is off, so FloLens cannot say which account |
| *Signed in as …* | the browser was already linked to the account before this session |

If a visit happened **before** the browser was linked to the account, it is shown with a note and tagged *Anonymous at the time*. It is attributed to the account only because **Settings → Privacy → Attach earlier browsing to the account** was on when the link happened; with that option off, earlier visits stay anonymous.

Rules that never change:
- Only the tracker's own browser cookie identifies a browser. **IP addresses are never used** to link visits or accounts.
- A browser is linked to the first account that signs in on it. A second account on the same browser does not take over the first one's history.
- The name shown follows **Show who the visitor is** (the privacy setting): when it is off you see *Registered user #id*. E-mail addresses are never shown in the journey.

## Sessions stay separate
Each session is drawn on its own, with explicit start and end. **Earlier session** and **Later session** move through the visitor's sessions; FloLens does not join separate visits into one path.

## Long sessions
Cards are drawn 60 at a time and the session is requested from the server 100 events at a time. Choose **Show next** to reveal more of what is loaded and **Load the next 100 events** to fetch more. Only the session you opened is loaded.

## Free and Pro
The Timeline is the same in both editions and has no edition limit. The **Visual journey**, the **Identity change** card, the notes about earlier visits and **Earlier / Later session** navigation are Pro. In Lite the server does not send the identity and navigation data at all, and a Visual journey choice remembered earlier simply falls back to the Timeline.

## Limits to know about
- Registration is inferred from the account's creation time; FloLens has no separate "registered" record.
- A login event does not say which account logged in; the name comes from the account attached to the session.
- Engaged time is measured by the tracker in the visitor's browser; pages closed abruptly may report none.
