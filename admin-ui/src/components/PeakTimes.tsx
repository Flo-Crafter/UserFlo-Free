import { h, __, sprintf, useState } from '../wp';
import { useApi } from '../hooks';
import { num } from '../format';
import { Async } from './ui';

/** [anonymous, logged-in] session counts. */
type Pair = [number, number];
interface Peaks { grid: Pair[][]; days: Pair[]; hours: Pair[]; totals: Pair; sessions: number; utc_offset: number }
type Who = 'all' | 'in' | 'out';
type Mode = 'heat' | 'days' | 'hours';

const pick = (p: Pair, w: Who): number => (w === 'in' ? p[1] : w === 'out' ? p[0] : p[0] + p[1]);
const hourLabel = (h0: number): string => String(h0).padStart(2, '0') + ':00';
/** Weekday names in the viewer's language, Monday first (index 0 = Monday, as the server counts). */
function weekdays(long: boolean): string[] {
  const loc = document.documentElement.lang || undefined;
  return [0, 1, 2, 3, 4, 5, 6].map((i) => { try { return new Date(2024, 0, 1 + i).toLocaleDateString(loc, { weekday: long ? 'long' : 'short' }); } catch (e) { return String(i); } });
}
const maxIndex = (vals: number[]): number => vals.reduce((b, v, i) => (v > vals[b] ? i : b), 0);

function Summary(p: { d: Peaks }) {
  const long = weekdays(true), out = (w: Who) => {
    const days = p.d.days.map((x) => pick(x, w)), hours = p.d.hours.map((x) => pick(x, w));
    const di = maxIndex(days), hi = maxIndex(hours);
    return days[di] === 0 ? '—' : `${long[di]} · ${hourLabel(hi)}–${hourLabel((hi + 1) % 24)}`;
  };
  return (
    <ul className="uf-peak-sum">
      <li><strong>{__('Peak day · peak hour')}:</strong> {out('all')}</li>
      <li><span className="uf-peak-key is-out" aria-hidden="true" /> <strong>{__('Anonymous')}:</strong> {out('out')}</li>
      <li><span className="uf-peak-key is-in" aria-hidden="true" /> <strong>{__('Logged-in')}:</strong> {out('in')}</li>
    </ul>
  );
}

function Heat(p: { d: Peaks; who: Who }) {
  const names = weekdays(false), long = weekdays(true);
  const max = Math.max(1, ...p.d.grid.map((r) => Math.max(...r.map((c) => pick(c, p.who)))));
  return (
    <div className="uf-scroll" tabIndex={0} role="region" aria-label={__('Peak times, scrollable')}>
      <table className="uf-peak-heat">
        <caption className="screen-reader-text">{__('Sessions by weekday and hour of the day')}</caption>
        <thead><tr><td />{Array.from({ length: 24 }, (_, i) => <th key={i} scope="col">{i % 3 === 0 ? String(i).padStart(2, '0') : ''}<span className="screen-reader-text">{hourLabel(i)}</span></th>)}</tr></thead>
        <tbody>{p.d.grid.map((row, d) => (
          <tr key={d}><th scope="row">{names[d]}</th>{row.map((c, i) => {
            const v = pick(c, p.who);
            return <td key={i} className={'uf-peak-cell' + (v ? '' : ' is-zero')} style={{ ['--t' as string]: String(Math.sqrt(v / max)) }} title={`${long[d]} ${hourLabel(i)}–${hourLabel((i + 1) % 24)}: ${num(v)} ${__('sessions')}${p.who === 'all' ? ` (${__('logged-in')} ${num(c[1])}, ${__('anonymous')} ${num(c[0])})` : ''}`}><span className="screen-reader-text">{v}</span></td>;
          })}</tr>))}
        </tbody>
      </table>
    </div>
  );
}

/** Stacked columns: anonymous at the bottom, logged-in on top (or just the chosen audience). */
function Cols(p: { rows: Pair[]; labels: string[]; who: Who; sparse?: boolean }) {
  const max = Math.max(1, ...p.rows.map((r) => pick(r, p.who)));
  const pk = maxIndex(p.rows.map((r) => pick(r, p.who)));
  return (
    <div className={'uf-peak-cols' + (p.sparse ? ' is-dense' : '')} role="img" aria-label={p.rows.map((r, i) => `${p.labels[i]}: ${num(pick(r, p.who))}`).join(', ')}>
      {p.rows.map((r, i) => {
        const tot = pick(r, p.who), a = p.who === 'in' ? 0 : r[0], l = p.who === 'out' ? 0 : r[1];
        return (
          <div key={i} className={'uf-peak-col' + (i === pk && tot > 0 ? ' is-peak' : '')} title={`${p.labels[i]}: ${num(tot)} ${__('sessions')} (${__('logged-in')} ${num(r[1])}, ${__('anonymous')} ${num(r[0])})`}>
            <span className="uf-peak-v">{p.sparse ? '' : num(tot)}</span>
            <span className="uf-peak-stack" style={{ height: (tot ? Math.max(3, (tot / max) * 100) : 0) + '%' }}>
              {l ? <span className="uf-peak-in" style={{ flexGrow: l }} /> : null}
              {a ? <span className="uf-peak-out" style={{ flexGrow: a }} /> : null}
            </span>
            <span className="uf-peak-l">{p.sparse ? (i % 3 === 0 ? String(i).padStart(2, '0') : '') : p.labels[i]}</span>
          </div>
        );
      })}
    </div>
  );
}

/** Peak times and peak days of the site, with the logged-in and anonymous audience told apart. */
export function PeakTimes(p: { days: number }) {
  const [who, setWho] = useState<Who>('all');
  const [mode, setMode] = useState<Mode>('heat');
  const q = useApi<Peaks>('stats/peaks', { days: p.days });
  return (
    <Async q={q}>
      {(d) => d.sessions === 0 ? <p className="uf-empty">{__('No sessions in this period.')}</p> : (
        <div>
          <div className="uf-viz-switch" role="group" aria-label={__('Chart type')}>
            {([['heat', __('Week heatmap')], ['days', __('By day')], ['hours', __('By hour')]] as [Mode, string][]).map(([m, l]) => <button key={m} type="button" className={'button button-small' + (mode === m ? ' button-primary' : '')} aria-pressed={mode === m} onClick={() => setMode(m)}>{l}</button>)}
            <span className="uf-sep" aria-hidden="true" />
            {([['all', __('Everyone')], ['out', __('Anonymous')], ['in', __('Logged-in')]] as [Who, string][]).map(([w, l]) => <button key={w} type="button" className={'button button-small' + (who === w ? ' button-primary' : '')} aria-pressed={who === w} onClick={() => setWho(w)}>{l}</button>)}
          </div>
          {mode === 'heat' ? <Heat d={d} who={who} /> : null}
          {mode === 'days' ? <Cols rows={d.days} labels={weekdays(false)} who={who} /> : null}
          {mode === 'hours' ? <Cols rows={d.hours} labels={d.hours.map((_, i) => hourLabel(i))} who={who} sparse /> : null}
          <p className="uf-peak-legend"><span className="uf-peak-key is-out" aria-hidden="true" /> {__('Anonymous')} · <span className="uf-peak-key is-in" aria-hidden="true" /> {__('Logged-in')}</p>
          <Summary d={d} />
          <p className="uf-sub">{sprintf(__('Sessions counted in the hour they began, in the site’s time zone (UTC%s). Darker means busier.'), (d.utc_offset >= 0 ? '+' : '−') + Math.abs(d.utc_offset / 3600))}</p>
        </div>
      )}
    </Async>
  );
}
