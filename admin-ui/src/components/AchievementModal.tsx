import { h, __, useEffect, useRef } from '../wp';
import { useApi } from '../hooks';
import type { Achievement, AchievementDetail, Tier, Who } from '../types';
import { dateOnly, dateTime, num } from '../format';
import { Async } from './ui';

export const TIER_LABEL: Record<Tier, string> = { copper: __('Copper'), silver: __('Silver'), gold: __('Gold'), diamond: __('Diamond'), olympian: __('Olympian') };

function visitorLink(w: Who): string { return `${window.userfloApp.urls.visitors}#/visitor/${w.visitor_id}${w.session_id ? '/session/' + w.session_id : ''}`; }

function WhoLink(p: { who: Who | null }) {
  if (!p.who) { return <span>—</span>; }
  return <span><a href={visitorLink(p.who)} className="uf-strong">{p.who.label}</a> {p.who.logged_in ? <span className="uf-badge uf-badge-user">{__('Logged-in')}</span> : null}</span>;
}

function Chart(p: { series: { d: string; total: number }[]; daily: boolean; marks: { d: string; label: string }[] }) {
  const s = p.series;
  if (s.length < 2) { return <p className="uf-empty">{__('Not enough history for a chart yet.')}</p>; }
  const W = 640, H = 200, L = 44, B = 24, T = 10, R = 10;
  const max = Math.max(1, ...s.map((x) => x.total));
  const x = (i: number) => L + (i / (s.length - 1)) * (W - L - R);
  const y = (v: number) => T + (1 - v / max) * (H - T - B);
  const pts = s.map((v, i) => `${x(i).toFixed(1)},${y(v.total).toFixed(1)}`).join(' ');
  const area = `${L},${H - B} ${pts} ${x(s.length - 1).toFixed(1)},${H - B}`;
  const idx: Record<string, number> = {};
  s.forEach((v, i) => { idx[v.d] = i; });
  return (
    <figure className="uf-ach-chart">
      <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label={p.daily ? __('Sessions per day') : __('Cumulative total over time')}>
        <polygon points={area} className="uf-ach-area" />
        <polyline points={pts} className="uf-ach-line" fill="none" />
        {p.marks.filter((m) => idx[m.d] !== undefined).map((m, i) => <circle key={i} cx={x(idx[m.d])} cy={y(s[idx[m.d]].total)} r="5" className="uf-ach-mark"><title>{m.label}</title></circle>)}
        <text x={L} y={H - 6} className="uf-ach-axis">{s[0].d}</text>
        <text x={W - R} y={H - 6} textAnchor="end" className="uf-ach-axis">{s[s.length - 1].d}</text>
        <text x={L - 6} y={T + 8} textAnchor="end" className="uf-ach-axis">{num(max)}</text>
        <text x={L - 6} y={H - B} textAnchor="end" className="uf-ach-axis">0</text>
      </svg>
      <figcaption>{p.daily ? __('Sessions per day') : __('Cumulative total over time (circles = milestones)')}</figcaption>
    </figure>
  );
}

export function AchievementModal(p: { a: Achievement; onClose: () => void }) {
  const a = p.a;
  const q = useApi<AchievementDetail>(a.status === 'secret' ? null : 'achievements/' + a.id);
  const box = useRef<HTMLDivElement | null>(null);
  useEffect(() => {
    const prev = document.activeElement as HTMLElement | null;
    if (box.current) { box.current.focus(); }
    const key = (e: KeyboardEvent) => { if (e.key === 'Escape') { p.onClose(); } };
    document.addEventListener('keydown', key);
    document.body.classList.add('uf-modal-open');
    return () => { document.removeEventListener('keydown', key); document.body.classList.remove('uf-modal-open'); if (prev) { prev.focus(); } };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  return (
    <div className="uf-modal-bg" onClick={(e) => { if (e.target === e.currentTarget) { p.onClose(); } }}>
      <div className={'uf-modal uf-tier-' + a.tier} role="dialog" aria-modal="true" aria-labelledby="uf-modal-title" tabIndex={-1} ref={box}>
        <div className="uf-modal-top">
          <div>
            <h2 id="uf-modal-title">{a.name}</h2>
            <p className="uf-ach-cat">{a.kind === 'family' ? `${TIER_LABEL[a.tier]} · ` : ''}{a.desc}</p>
          </div>
          <button className="button" onClick={p.onClose} aria-label={__('Close')}>×</button>
        </div>
        <p><strong>{num(a.value)}</strong> {a.target ? `/ ${num(a.target)}` : ''}</p>
        <Async q={q}>
          {(d) => (
            <div>
              {d.history ? (
                <Chart series={d.series} daily={!!d.daily} marks={d.milestones.filter((m) => m.reached_at).map((m) => ({ d: (m.reached_at as string).slice(0, 10), label: `${TIER_LABEL[m.tier]}: ${m.name}` }))} />
              ) : null}
              {d.first_at && d.total ? <p className="uf-sub">{__('First recorded')}: {dateOnly(d.first_at + 'T00:00:00Z')} · {num(d.total)} {__('so far')}</p> : null}
              {d.milestones.length ? (
                <div className="uf-scroll"><table className="widefat striped uf-table">
                  <thead><tr><th>{__('Tier')}</th><th>{__('Milestone')}</th><th>{__('Reached')}</th><th>{__('By')}</th><th>{__('What')}</th></tr></thead>
                  <tbody>{d.milestones.map((m) => (
                    <tr key={m.tier}>
                      <td><span className={'uf-ach-tier uf-tier-' + m.tier}>{TIER_LABEL[m.tier]}</span></td>
                      <td>{m.name}<br /><small>{num(m.target)}</small></td>
                      <td>{m.reached_at ? dateTime(m.reached_at) : <span className="uf-empty">{__('Not reached yet')}</span>}</td>
                      <td>{m.reached_at ? <WhoLink who={m.who} /> : '—'}</td>
                      <td>{m.what ? <code>{m.what}</code> : '—'}</td>
                    </tr>))}</tbody>
                </table></div>
              ) : null}
              {d.record ? (
                <p><strong>{__('Record')}:</strong> {num(d.record.value)} · {dateTime(d.record.at)} · <WhoLink who={d.record.who} /></p>
              ) : null}
              {!d.history && !d.record ? <p className="uf-empty">{__('No detailed history is available for this achievement.')}</p> : null}
            </div>
          )}
        </Async>
      </div>
    </div>
  );
}
