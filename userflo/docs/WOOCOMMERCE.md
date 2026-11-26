# WooCommerce sales

When WooCommerce is active, UserFlo adds a **Sales** card to the Overview and a **Shop** tab under Statistics. Without WooCommerce none of this exists: no hooks, no routes, no queries, no scripts.

## Where the numbers come from
**WooCommerce orders are the source of truth.** UserFlo keeps a small mirror of each order's *numbers* (status, currency, total, tax, shipping, discount, amount refunded, creation time) so reports are fast. It reads orders only through WooCommerce's own order objects, so it works with the classic posts storage and with High-Performance Order Storage, and it declares compatibility with both and with the block checkout. It does **not** read or store names, e-mail addresses, addresses, phone numbers, customer ids, notes or products, and none of that is sent to any analytics event.

The mirror is updated when an order is created, changes status, is refunded, has a refund removed or is deleted. Updating is idempotent: processing the same order again, however often a hook fires, gives the same row. **Tools: "Read orders again"** (Statistics → Shop) re-reads the last 90 days, for example after UserFlo was switched off for a while. Orders read this way have their numbers but no visit (see attribution).

## Definitions
- **A sale** is an order in a counted status, placed in the period (the order's creation time, site time zone, start inclusive and end exclusive). Lite counts **Processing** and **Completed**. Pro can choose other statuses (Settings → Components → WooCommerce sales).
- **Revenue** = order total − amount refunded so far, in the order's own currency. A partial refund lowers revenue and keeps the order; a fully refunded order is in the *Refunded* status and no longer counts. A refund lowers the revenue of the period the **order** was placed in, not the refund date.
- **Net sales** = revenue − tax − shipping, where tax and shipping are what was not refunded.
- **Average order value** = revenue ÷ sales.
- **Discounts** = coupon discounts given on counted orders. **Tax** and **shipping** are shown from the order, not recalculated.
- **Currency.** Every figure is per currency. UserFlo never converts or adds different currencies; a multi-currency store gets one table per currency. If a plugin changes the currency of an existing order the order's current currency is used.
- Orders in other statuses (pending, failed, cancelled, refunded, on hold, custom) are listed as "not counted" and never added.
- These numbers will differ from WooCommerce's own analytics wherever its settings differ (statuses, refund timing, taxes included or not, time zone handling). They are not meant to replace WooCommerce Reports for accounting.

## Attribution (Pro): what it does and does not say
At checkout the buyer's browser sends UserFlo's own session cookie (`uf_sid`) with the order request. UserFlo stores that session id next to the order (once) and later looks up how that **visit** started: its campaign, source, medium. The result is "sales by the campaign of the visit that was active when the order was placed".

- It does **not** say the campaign *caused* the sale. The visitor may have come from several places over several days; UserFlo credits the visit that was running at checkout.
- No cookie, no attribution: the order shows as **Unknown (no recorded visit)**. This happens when the buyer did not consent or is not tracked (GPC/DNT), when a cache, CDN or privacy tool hid the cookie, when the order was placed in the admin or through the API, for orders read again from history, and in session-only (cookieless) tracking. UserFlo does not guess a source.
- A recorded visit without a value (for example no `utm_campaign`) is shown as **Visit without this value**.
- Sessions are removed by your retention setting; the order keeps its number and shows as Unknown afterwards. Orders themselves are kept for the same retention period.
- The basket line (Pro) is the number of sessions with an add-to-cart click in the period. It is a count of intent from the browser; no ratio between it and orders is claimed.

## Permissions
The Shop report needs UserFlo's analytics capability **and** WooCommerce's (`manage_woocommerce` or `view_woocommerce_reports`) or administrator rights. Re-reading orders needs administrator rights.

## Free and Pro
- **Lite:** orders, revenue, average order value, refunds, discounts, net sales, per currency, with the previous period; the Overview card.
- **Pro:** sales by campaign, source and medium, basket sessions, choosing which statuses count.

## What has not been verified (read this before relying on it)
WooCommerce itself was **not available** in the environment this was built in. The order handling was tested with stand-in order objects that offer the same getters, and the hook wiring by firing the same hooks. Before using it on a live store please check on a staging copy: (1) place a test order with the classic checkout and with the block checkout, then confirm a row appears (Statistics → Shop) and, with consent given, that it is attributed to the right visit; (2) change its status and refund part of it; (3) switch High-Performance Order Storage on and off (WooCommerce → Settings → Advanced → Features) and repeat; (4) confirm the plugin list shows no "incompatible with HPOS" warning.
