import { h, __ } from '../wp';
import { useApi } from '../hooks';
import type { ExternalAggregate } from '../types';
import { dateTime, num } from '../format';
import { Card } from '../components/ui';

/** Imported aggregates from external services, shown separately: never merged with native UserFlo numbers. */
export function External() {
  const q = useApi<{ items: ExternalAggregate[] }>('integrations/data');
  if (q.error || !q.data || q.data.items.length === 0) { return null; }
  return (
    <Card title={__('External analytics (imported aggregates)')}>
      <p className="uf-sub">{__('Cached from other services. Definitions differ from the native numbers above, so these are never added together or used in visitor profiles.')}</p>
      <div className="uf-cols">
        {q.data.items.map((it) => (
          <div key={it.id} className="uf-ext">
            <h3>{it.label} <small>— {it.data.period.label}</small></h3>
            <div className="uf-stats">
              {Object.keys(it.data.metrics).map((k) => (
                <div key={k} className="uf-stat"><div className="uf-stat-v">{num(it.data.metrics[k])}</div><div className="uf-stat-l">{k}</div></div>
              ))}
            </div>
            {it.data.pages && it.data.pages.length ? (
              <table className="widefat striped"><thead><tr><th>{__('Top pages')}</th><th>{__('Views')}</th></tr></thead>
                <tbody>{it.data.pages.slice(0, 5).map((p) => <tr key={p.path}><td><code>{p.path}</code></td><td>{num(p.views)}</td></tr>)}</tbody></table>
            ) : null}
            <p><small>{__('Imported')}: {dateTime(it.fetched_at)} · {it.dashboard_url ? <a href={it.dashboard_url} target="_blank" rel="noopener noreferrer">{__('Open in')} {it.label} ↗</a> : null}</small></p>
          </div>
        ))}
      </div>
    </Card>
  );
}
