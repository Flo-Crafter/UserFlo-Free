import { h, __, sprintf } from '../wp';
import { useApi } from '../hooks';
import { num, dayLabel } from '../format';
import { Async } from './ui';

interface Totals { sessions: number; pageviews: number; visitors: number; new_visitors: number; logged_in: number; engaged: number; single: number }
interface Side { days: number; totals: Totals }
interface Row { k: string; now: number; before: number }
export interface YearData {
  enabled: boolean; years: number; first_day: string; from: string; to: string;
  previous_from?: string; previous_to?: string; expected_days: number; complete: boolean;
  current: Side | null; previous: Side | null;
  rows?: Record<string, Row[]>;
  months: { month: string; sessions: number; pageviews: number; visitors: number; days: number }[];
}

/** "+12%" / "−8%" / "new" against last year; nothing when there is nothing to compare with. */
function change(now: number, before: number): { text: string; cls: string } {
  if (before <= 0) { return { text: now > 0 ? __('new') : '—', cls: '' }; }
  const d = Math.round(((now - before) / before) * 1000) / 10;
  return { text: (d > 0 ? '+' : d < 0 ? '−' : '') + Math.abs(d) + '%', cls: d > 0 ? 'is-up' : d < 0 ? 'is-down' : '' };
}

function Tile(p: { label: string; now: number; before: number }) {
  const c = change(p.now, p.before);
  return (
    <div className="uf-yoy-tile">
      <div className="uf-yoy-l">{p.label}</div>
      <div className="uf-yoy-v">{num(p.now)}</div>
      <div className="uf-yoy-b">{sprintf(__('Last year: %s'), num(p.before))} <span className={'uf-yoy-d ' + c.cls}>{c.text}</span></div>
    </div>
  );
}

function Table(p: { title: string; rows: Row[] }) {
  if (!p.rows.length) { return null; }
  return (
    <div>
      <h3>{p.title}</h3>
      <table className="widefat striped uf-yoy-table">
        <thead><tr><th scope="col">{__('Name')}</th><th scope="col">{__('This period')}</th><th scope="col">{__('Last year')}</th><th scope="col">{__('Change')}</th></tr></thead>
        <tbody>{p.rows.map((r) => { const c = change(r.now, r.before); return <tr key={r.k}><td title={r.k}>{r.k || __('Direct / internal')}</td><td>{num(r.now)}</td><td>{num(r.before)}</td><td className={'uf-yoy-d ' + c.cls}>{c.text}</td></tr>; })}</tbody>
      </table>
    </div>
  );
}

/** One column per summarised month, oldest first (a time series keeps its order; the composition charts sort by size). */
function Months(p: { months: YearData['months'] }) {
  const max = Math.max(1, ...p.months.map((m) => m.sessions));
  return (
    <div className="uf-yoy-months" role="img" aria-label={p.months.map((m) => `${m.month}: ${num(m.sessions)}`).join(', ')}>
      {p.months.map((m) => (
        <div key={m.month} className="uf-yoy-month" title={`${m.month}: ${num(m.sessions)} ${__('sessions')} (${num(m.days)} ${__('days')})`}>
          <span className="uf-yoy-mv">{num(m.sessions)}</span>
          <span className={'uf-yoy-bar' + (m.days < 28 ? ' is-part' : '')} style={{ height: Math.max(2, (m.sessions / max) * 100) + '%' }} />
          <span className="uf-yoy-ml">{m.month + (m.days < 28 ? ' *' : '')}</span>
        </div>
      ))}
    </div>
  );
}

/**
 * This period next to the same dates one year earlier, from the daily summaries that outlive the detailed data. Says plainly when
 * there is nothing to compare with yet (summaries only exist from the day they were switched on) and never fills a gap with a guess.
 */
export function YearCompare(p: { days: number }) {
  const q = useApi<YearData>('stats/year', { days: p.days });
  return (
    <Async q={q}>
      {(d: YearData) => {
        if (!d.enabled) {
          return <p className="uf-empty">{__('Daily summaries are switched off, so there is nothing to compare with last year. Turn them on under Settings → Privacy → Keep daily summaries for.')}</p>;
        }
        if (!d.current || !d.previous || d.previous.days === 0) {
          return (
            <p className="uf-empty">
              {d.first_day
                ? sprintf(__('There is no summary for the same dates last year yet. Summaries start on %s, so this comparison appears when the dates a year later are reached.'), dayLabel(d.first_day, true))
                : __('Daily summaries are being built from the data recorded so far. Check back after the next daily cleanup run.')}
            </p>
          );
        }
        const c = d.current.totals, b = d.previous.totals, r = d.rows || {};
        return (
          <div className="uf-yoy">
            <p className="uf-sub">
              {sprintf(__('%1$s to %2$s compared with %3$s to %4$s.'), dayLabel(d.from, true), dayLabel(d.to, true), dayLabel(d.previous_from || '', true), dayLabel(d.previous_to || '', true))}
              {' '}{!d.complete ? sprintf(__('Partial: summaries exist for %1$s of %2$s days last year and %3$s of %4$s days this period.'), num(d.previous.days), num(d.expected_days), num(d.current.days), num(d.expected_days)) : ''}
            </p>
            <div className="uf-yoy-tiles">
              <Tile label={__('Sessions')} now={c.sessions} before={b.sessions} />
              <Tile label={__('Page views')} now={c.pageviews} before={b.pageviews} />
              <Tile label={__('Visitors (per day, summed)')} now={c.visitors} before={b.visitors} />
              <Tile label={__('New visitors')} now={c.new_visitors} before={b.new_visitors} />
            </div>
            {d.months.length > 1 ? (
              <div>
                <h3>{__('Sessions per month, all summarised months')}</h3>
                <Months months={d.months} />
                <p className="uf-sub">{__('* a month with fewer than 28 summarised days.')}</p>
              </div>
            ) : null}
            <div className="uf-yoy-tables">
              <Table title={__('Pages')} rows={r.page || []} />
              <Table title={__('Traffic sources')} rows={r.source || []} />
              <Table title={__('Countries')} rows={r.country || []} />
              <Table title={__('Devices')} rows={r.device || []} />
            </div>
          </div>
        );
      }}
    </Async>
  );
}
