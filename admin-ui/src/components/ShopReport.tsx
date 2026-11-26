import { h, __, useState, cfg, sprintf } from '../wp';
import { useApi } from '../hooks';
import { send } from '../api';
import type { CommerceSummary } from '../types';
import { num } from '../format';
import { slots } from '../slots';
import { Async, Card, DeltaChip, Empty, LimitNote } from './ui';

/** Money in the order's own currency; falls back to "123.45 EUR" for a code the browser does not know. */
export function money(v: number, currency: string): string {
  try { return new Intl.NumberFormat(cfg().locale || undefined, { style: 'currency', currency }).format(v); } catch (e) { return num(Math.round(v * 100) / 100) + ' ' + currency; }
}

/** Sales from your WooCommerce orders. Definitions are in the WooCommerce help guide. */
export function ShopReport(p: { days: number }) {
  const params = { days: p.days };
  const CommerceAttribution = slots().CommerceAttribution;
  const q = useApi<CommerceSummary>('commerce/summary', params);
  const [msg, setMsg] = useState('');
  const resync = async () => {
    setMsg(__('Reading orders…'));
    try {
      let n = 0;
      for (let page = 1; page < 500; page++) {
        const r = await send<{ done: boolean; count: number }>('POST', 'commerce/resync', { page, days: 90 });
        n += r.count;
        if (r.done) { break; }
      }
      setMsg(sprintf(__('Done. %s orders from the last 90 days were read.'), num(n))); q.reload();
    } catch (e) { setMsg((e as Error).message); }
  };
  return (
    <div>
      <Card title={__('WooCommerce sales')}>
        <p className="uf-sub">{__('Read from your WooCommerce orders. Revenue is the order total minus refunds, in each order’s own currency. Orders are counted on the day they were placed.')}</p>
        <Async q={q} empty={(d) => d.currencies.length === 0 && Object.keys(d.excluded).length === 0} emptyText={__('No orders in this period yet. Orders appear as they are placed; use “Read orders again” to load the last 90 days.')}>
          {(d) => (
            <div>
              {d.currencies.length === 0 ? <Empty>{__('No orders in a counted status in this period.')}</Empty> : d.currencies.map((c) => (
                <table key={c.currency} className="widefat striped uf-compact">
                  <caption className="screen-reader-text">{c.currency}</caption>
                  <thead><tr><th>{c.currency}</th><th>{__('This period')}</th></tr></thead>
                  <tbody>
                    <tr><td>{__('Orders')}</td><td>{num(c.orders)} {c.prev ? <DeltaChip now={c.orders} before={c.prev.orders} vs={__('previous period')} /> : null}</td></tr>
                    <tr><td>{__('Revenue (after refunds)')}</td><td>{money(c.revenue, c.currency)} {c.prev ? <DeltaChip now={c.revenue} before={c.prev.revenue} vs={__('previous period')} /> : null}</td></tr>
                    <tr><td>{__('Average order value')}</td><td>{money(c.aov, c.currency)}</td></tr>
                    <tr><td>{__('Net sales (without tax and shipping)')}</td><td>{money(c.net, c.currency)}</td></tr>
                    <tr><td>{__('Refunded')}</td><td>{money(c.refunded, c.currency)}</td></tr>
                    <tr><td>{__('Discounts given')}</td><td>{money(c.discount, c.currency)}</td></tr>
                  </tbody>
                </table>
              ))}
              {Object.keys(d.excluded).length ? <p className="uf-sub">{__('Not counted as sales in this period:')} {Object.entries(d.excluded).map(([s, n]) => `${s} ${num(n)}`).join(' · ')}</p> : null}
              <p className="uf-sub">{sprintf(__('Counted statuses: %s.'), d.counted.join(', '))} {d.sessions ? sprintf(__('%1$s orders for %2$s sessions in the same period (different people may be involved; this is not a conversion rate of the same visitors).'), num(d.orders), num(d.sessions)) : null}</p>
              {d.advanced && d.cart_sessions !== undefined ? <p className="uf-sub">{sprintf(__('%s sessions added something to the basket in this period.'), num(d.cart_sessions))}</p> : null}
            </div>
          )}
        </Async>
        <p><button className="button" onClick={resync}>{__('Read orders again')}</button> {msg ? <span role="status">{msg}</span> : null}</p>
      </Card>
      <LimitNote feature="commerce_attribution">{__('Sales by campaign, basket numbers and choosing which statuses count are part of Pro.')}</LimitNote>
      {CommerceAttribution ? <CommerceAttribution params={params} /> : null}
    </div>
  );
}
