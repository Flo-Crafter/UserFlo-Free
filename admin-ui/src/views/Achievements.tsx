import { h, __, useState } from '../wp';
import { useApi } from '../hooks';
import type { Achievement, Achievements as Data, Tier } from '../types';
import { num } from '../format';
import { Async } from '../components/ui';
import { AchievementModal } from '../components/AchievementModal';

const TIERS: Tier[] = ['copper', 'silver', 'gold', 'diamond', 'olympian'];
const tierLabel: Record<Tier, string> = { copper: __('Copper'), silver: __('Silver'), gold: __('Gold'), diamond: __('Diamond'), olympian: __('Olympian') };
const catLabel: Record<string, string> = {
  traffic: __('Traffic'), engagement: __('Engagement'), journeys: __('Journeys'), identity: __('Visitors & Members'), interactions: __('Interactions'),
  devices: __('Devices'), rhythm: __('Rhythm'), setup: __('Privacy & Setup'), secret: __('Secret'),
};
const statusLabel: Record<string, string> = { unlocked: __('Unlocked'), discovered: __('In progress'), locked: __('Not started'), secret: __('Secret') };

function Card(p: { a: Achievement; onOpen: () => void }) {
  const a = p.a;
  const pct = Math.round(a.progress * 100);
  const secret = a.status === 'secret';
  const done = a.kind === 'family' && !a.next;
  return (
    <li>
      <button type="button" className={`uf-ach uf-ach-${a.status} uf-tier-${a.tier}`} onClick={p.onOpen} disabled={secret}>
        <span className="uf-ach-top">
          <strong className="uf-ach-name">{a.name}</strong>
          <span className="uf-ach-tier">{a.kind === 'family' ? (a.status === 'locked' ? '—' : tierLabel[a.tier]) : statusLabel[a.status]}</span>
        </span>
        <span className="uf-ach-cat">{catLabel[a.category] || a.category}{a.kind === 'family' && a.next ? ` · ${__('Next')}: ${tierLabel[a.next]}` : ''}{done ? ` · ${__('Complete')}` : ''}</span>
        <span className="uf-ach-desc">{a.desc}</span>
        {!secret ? (
          <span className="uf-ach-prog">
            <span className="uf-ach-bar" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={pct} aria-label={`${a.name}: ${pct}%`}><span style={{ width: pct + '%' }} /></span>
            <small className="uf-ach-num">{num(a.value)} / {num(a.target)}</small>
          </span>
        ) : null}
      </button>
    </li>
  );
}

export function Achievements() {
  const q = useApi<Data>('achievements');
  const [cat, setCat] = useState('all');
  const [status, setStatus] = useState('all');
  const [tier, setTier] = useState('all');
  const [open, setOpen] = useState<Achievement | null>(null);
  return (
    <div>
      <h1 className="uf-title">{__('Achievements')}</h1>
      <p className="uf-sub">{__('Badges earned from your own tracking data. Computed from data still within your retention period, so they can move backwards if old data is deleted.')}</p>
      <Async q={q}>
        {(d) => {
          const cats = ['all'].concat(Array.from(new Set(d.items.map((i) => i.category))));
          const shown = d.items.filter((i) => (cat === 'all' || i.category === cat) && (status === 'all' || i.status === status) && (tier === 'all' || i.tier === tier));
          const s = d.summary;
          return (
            <div>
              <div className="uf-stats">
                <div className="uf-stat"><div className="uf-stat-v">{s.unlocked} / {s.total}</div><div className="uf-stat-l">{__('Unlocked')}</div><div className="uf-stat-h">{__('earned tiers')} · {s.boxes} {__('boxes')}</div></div>
                <div className="uf-stat"><div className="uf-stat-v">{s.discovered}</div><div className="uf-stat-l">{__('In progress')}</div><div className="uf-stat-h">{__('started, not yet earned')}</div></div>
                <div className="uf-stat"><div className="uf-stat-v">{s.secret}</div><div className="uf-stat-l">{__('Secrets')}</div><div className="uf-stat-h">{__('hidden until the first signal')}</div></div>
                <div className="uf-stat"><div className="uf-stat-v">{s.highest_tier ? tierLabel[s.highest_tier] : '—'}</div><div className="uf-stat-l">{__('Highest tier')}</div><div className="uf-stat-h">{TIERS.map((t) => tierLabel[t]).join(' → ')}</div></div>
              </div>
              <div className="uf-filter" role="group" aria-label={__('Filters')}>
                <label>{__('Category')} <select value={cat} onChange={(e) => setCat((e.target as HTMLSelectElement).value)}>{cats.map((c) => <option key={c} value={c}>{c === 'all' ? __('All') : catLabel[c] || c}</option>)}</select></label>
                <label>{__('Status')} <select value={status} onChange={(e) => setStatus((e.target as HTMLSelectElement).value)}><option value="all">{__('All')}</option>{['unlocked', 'discovered', 'locked', 'secret'].map((x) => <option key={x} value={x}>{statusLabel[x]}</option>)}</select></label>
                <label>{__('Tier')} <select value={tier} onChange={(e) => setTier((e.target as HTMLSelectElement).value)}><option value="all">{__('All')}</option>{TIERS.map((t) => <option key={t} value={t}>{tierLabel[t]}</option>)}</select></label>
                <span className="uf-sub">{shown.length} {__('shown')}</span>
              </div>
              <ul className="uf-ach-grid">{shown.map((a) => <Card key={a.id} a={a} onOpen={() => setOpen(a)} />)}</ul>
              {open ? <AchievementModal a={open} onClose={() => setOpen(null)} /> : null}
            </div>
          );
        }}
      </Async>
    </div>
  );
}
