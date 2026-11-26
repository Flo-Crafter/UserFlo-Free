import { h, __, screen } from '../wp';
import { useApi } from '../hooks';
import type { CommerceSummary } from '../types';
import { num } from '../format';
import { Async, Card, DeltaChip, Empty } from './ui';
import { money } from './ShopReport';

/** Orders and revenue for the Overview period (Lite shows totals; attribution is in Statistics → Shop). */
export function CommerceCard(p: { params: Record<string, string | number | undefined> }) {
  const q = useApi<CommerceSummary>('commerce/summary', p.params);
  return (
    <Card title={__('Sales')} more={{ href: screen('stats'), label: __('Shop report') }}>
      <Async q={q}>
        {(d) => d.currencies.length === 0 ? <Empty>{__('No sales in this period.')}</Empty> : (
          <div>
            <table className="widefat striped uf-compact">
              <thead><tr><th>{__('Currency')}</th><th>{__('Orders')}</th><th>{__('Revenue')}</th><th>{__('Average order')}</th></tr></thead>
              <tbody>{d.currencies.map((c) => (
                <tr key={c.currency}><td>{c.currency}</td>
                  <td>{num(c.orders)} {c.prev ? <DeltaChip now={c.orders} before={c.prev.orders} vs={__('previous period')} /> : null}</td>
                  <td>{money(c.revenue, c.currency)} {c.prev ? <DeltaChip now={c.revenue} before={c.prev.revenue} vs={__('previous period')} /> : null}</td>
                  <td>{money(c.aov, c.currency)}</td></tr>))}</tbody>
            </table>
            <p className="uf-ov-note">{__('From your WooCommerce orders; revenue is after refunds, per currency. Details: Statistics → Shop.')}</p>
          </div>
        )}
      </Async>
    </Card>
  );
}
