import { h, __ } from '../wp';
import { useApi } from '../hooks';
import type { Focus } from '../types';
import { duration, num } from '../format';
import { Async, Empty } from './ui';
import { CycleCard } from './CycleCard';

/** Site-type focus: the page groups and funnel that matter for the chosen kind of site (shown only when a site type is set). */
export function FocusPanel(p: { params: Record<string, string | number | undefined> }) {
  const q = useApi<Focus>('stats/focus', p.params);
  if (q.data && q.data.groups.length === 0) { return null; }
  return (
    <Async q={q}>
      {(d) => {
        const maxF = Math.max(1, ...d.funnel.map((s) => s.sessions));
        return (
          <section aria-label={__('Focus')}>
            <h2 className="uf-focus-title">{__('Focus')}: {d.label}</h2>
            <div className="uf-stats">
              {d.groups.map((g) => (
                <div key={g.id} className="uf-stat" title={g.top.map((t) => `${t.url} (${t.views})`).join('\n')}>
                  <div className="uf-stat-v">{num(g.views)}</div>
                  <div className="uf-stat-l">{g.label}</div>
                  <div className="uf-stat-h">{num(g.sessions)} {__('sessions')} · {duration(g.avg_engaged)} {__('avg. engaged')}</div>
                </div>
              ))}
            </div>
            <div className="uf-cols">
              {d.funnel.length ? (
                <CycleCard id="focus_funnel" title={__('Visit funnel')} order={['list', 'columns', 'donut', 'pie']} centre={num(d.funnel[0].sessions)} centreLabel={__('sessions')}
                  rows={d.funnel.map((s) => ({ key: String(s.id), label: s.label, value: s.sessions }))} head={<p className="uf-sub">{__('Sessions that reached each step (not necessarily in order), as a share of the previous step.')}</p>}
                  list={(<ol className="uf-rank">
                    {d.funnel.map((s) => (
                      <li key={s.id}>
                        <div className="uf-rank-top"><span className="uf-rank-label">{s.label}</span><span className="uf-rank-n">{num(s.sessions)}{s.pct_prev !== null ? ` · ${Math.min(999, s.pct_prev)}%` : ''}</span></div>
                        <div className="uf-rank-bar" aria-hidden="true"><span style={{ width: Math.max(2, (s.sessions / maxF) * 100) + '%' }} /></div>
                      </li>
                    ))}
                  </ol>)} />
              ) : null}
              {d.highlights.map((hl) => (
                <CycleCard key={hl.id} id={'focus_' + hl.id} title={hl.label} rows={hl.items.map((it, i) => ({ key: String(i), label: it.label, value: it.count }))}
                  list={hl.items.length ? (
                    <ol className="uf-rank">{hl.items.map((it, i) => {
                      const max = Math.max(1, ...hl.items.map((x) => x.count));
                      return <li key={i}><div className="uf-rank-top"><span className="uf-rank-label" title={it.label}>{it.label}</span><span className="uf-rank-n">{num(it.count)}</span></div><div className="uf-rank-bar" aria-hidden="true"><span style={{ width: Math.max(2, (it.count / max) * 100) + '%' }} /></div></li>;
                    })}</ol>
                  ) : <Empty>{__('No data in this period.')}</Empty>} />
              ))}
            </div>
          </section>
        );
      }}
    </Async>
  );
}
