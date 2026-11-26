import { h, __, cfg, can, limit, useState, useMemo } from '../wp';
import { Empty, LimitNote } from '../components/ui';
import { addDays, addMonths, monthEnd, monthKey, monthStart, scale } from '../traffic';
import { slots } from '../slots';
import { Grid, Legend, METRIC_INFO, fmtDate, siteToday, useCalendar, val } from '../components/calendarShared';
import { num } from '../format';
import type { TrafficDay } from '../types';

/**
 * Traffic calendar in UserFlo Lite: one month at a time, page views, the current month and the two before it, no day or hour detail.
 * With Pro active and entitled, the Pro bundle supplies the full calendar and this wrapper hands over to it.
 */
export function TrafficCalendar() {
  const Full = slots().TrafficCalendarFull;
  if (Full && can('calendar_week')) { return <Full />; }
  return <LiteCalendar />;
}

function LiteCalendar() {
  const c = cfg();
  const [anchor, setAnchor] = useState<string>(siteToday());
  const from = monthStart(anchor);
  const to = monthEnd(anchor);
  const q = useCalendar(from, to, 'pageviews');
  const cal = q.data;
  const today = cal ? cal.today : siteToday();
  const days = cal ? cal.days : ({} as Record<string, TrafficDay>);
  const keys = useMemo(() => { const out: string[] = []; for (let k = from; k <= to; k = addDays(k, 1)) { out.push(k); } return out; }, [from, to]);
  const sc = useMemo(() => scale(keys.map((k) => val(days[k], 'pageviews'))), [keys, days]);
  const hasPartial = keys.some((k) => days[k] && days[k].partial && days[k].state === 'data');
  const months = limit('calendar_months');
  const earliest = months ? addMonths(monthStart(today), -(months - 1)) : '';
  const canPrev = !earliest || from > earliest;
  const canNext = monthKey(from) < monthKey(today);
  const title = fmtDate(from, { month: 'long', year: 'numeric' });
  const summary = !cal ? '' : sc.max === 0 ? __('No activity recorded in this period.') : `${__('Total')}: ${num(sc.total)} ${sc.total === 1 ? __('page view') : __('page views')}`;

  return (
    <div className="uf-tc">
      <h1 className="uf-title">{__('Traffic calendar')}</h1>
      <p className="uf-sub">{__('Spot busy and quiet days at a glance.')}</p>
      <div className="uf-tc-stage"><div className="uf-tc-cal">
        <div className="uf-tc-bar">
          <div className="uf-tc-nav" role="group" aria-label={__('Calendar navigation')}>
            <button type="button" className="button" disabled={!canPrev} onClick={() => setAnchor(addMonths(from, -1))} aria-label={__('Previous month')}><span aria-hidden="true">‹</span></button>
            <button type="button" className="button" onClick={() => setAnchor(today)}>{__('Today')}<span className="screen-reader-text"> ({__('go to the current month')})</span></button>
            <button type="button" className="button" disabled={!canNext} onClick={() => setAnchor(addMonths(from, 1))} aria-label={__('Next month')}><span aria-hidden="true">›</span></button>
          </div>
          <div className="uf-tc-range">
            <h2 aria-live="polite">{title}</h2>
            <span className="uf-tc-sub">{fmtDate(from, { dateStyle: 'medium' })} – {fmtDate(to, { dateStyle: 'medium' })} · {c.timezone}</span>
          </div>
        </div>
        <p className="uf-tc-hint" id="uf-tc-metric-hint">{METRIC_INFO.pageviews.hint()}</p>
        {q.error ? (
          <div className="notice notice-error inline" role="alert"><p>{__('Could not load data:')} {q.error.message} <button type="button" className="button" onClick={q.reload}>{__('Retry')}</button></p></div>
        ) : null}
        <div className={'uf-tc-gridwrap' + (q.loading && cal ? ' uf-stale' : '')}>
          <Grid view="month" anchor={from} weekStart={c.weekStart} today={today} metric="pageviews" days={days} sc={sc} keys={keys}
            focusKey="" selected={null} busy={q.loading} interactive={false} onFocusKey={() => undefined} onOpen={() => undefined} onMove={() => undefined} />
        </div>
        <p className="uf-tc-summary" role="status">{q.loading && !cal ? __('Loading…') : summary}</p>
        <Legend sc={sc} metric="pageviews" partial={hasPartial} />
        <LimitNote feature="calendar_detail">{__('The free calendar shows one month at a time. Week view, more metrics and day and hour detail are part of Pro.')}</LimitNote>
      </div></div>
      {cal && !Object.keys(days).length ? <Empty>{__('Nothing recorded yet.')}</Empty> : null}
    </div>
  );
}
