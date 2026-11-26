import { h, __, cfg, Fragment, useState, can, modOn, rangePresets, sprintf, screen } from '../wp';
import { useApi } from '../hooks';
import type { CompareMode, Overview as OverviewData } from '../types';
import { dateTime, duration, num } from '../format';
import { Async, Card, DeltaChip, Empty, Kpi } from '../components/ui';
import { GoalsCard } from '../components/GoalsCard';
import { CommerceCard } from '../components/CommerceCard';
import { slots } from '../slots';
import { BarList, Sparkline, TrendChart } from '../components/Chart';
import { External } from './External';
import { FocusPanel } from '../components/FocusPanel';
import { BotCounter } from '../components/BotCounter';
import { Board } from '../components/Board';
import { CycleCard } from '../components/CycleCard';
import type { PanelDef } from '../components/Board';

type Range = { kind: 'days'; days: number } | { kind: 'custom'; from: string; to: string };

const CMP_KEY = 'userflo_ov_compare';
const MODES: CompareMode[] = ['previous', 'week', 'month', 'year', 'none'];
const loadMode = (): CompareMode => {
  try { const v = window.localStorage.getItem(CMP_KEY) as CompareMode | null; return v && MODES.indexOf(v) >= 0 ? v : 'previous'; } catch (e) { return 'previous'; }
};
const saveMode = (m: CompareMode): void => { try { window.localStorage.setItem(CMP_KEY, m); } catch (e) { /* per-browser convenience only */ } };

/** Channel keys (derived on the server from referrer and utm_* parameters) with their label and colour slot. */
const CHANNELS: Record<string, [string, number]> = {};
const channelInfo = (key: string): [string, number] => {
  if (!CHANNELS.organic_search) {
    CHANNELS.organic_search = [__('Organic search'), 1];
    CHANNELS.direct = [__('Direct'), 2];
    CHANNELS.social = [__('Social'), 3];
    CHANNELS.referral = [__('Referral'), 4];
    CHANNELS.paid = [__('Paid'), 5];
    CHANNELS.email = [__('Email'), 6];
  }
  return CHANNELS[key] || [key, 0];
};
const deviceInfo = (key: string): [string, number] => {
  const m: Record<string, [string, number]> = { desktop: [__('Desktop'), 1], mobile: [__('Mobile'), 2], tablet: [__('Tablet'), 3] };
  return m[key] || [key.charAt(0).toUpperCase() + key.slice(1), 4];
};

export function Overview() {
  const [range, setRange] = useState<Range>({ kind: 'days', days: 30 });
  const CustomRange = slots().CustomRange;
  const [mode, setMode] = useState<CompareMode>(loadMode);
  const [srcTab, setSrcTab] = useState<'channels' | 'sources'>('channels');
  const params = range.kind === 'days' ? { days: range.days } : { from: range.from, to: range.to };
  const q = useApi<OverviewData>('stats/overview', { ...params, compare: mode });
  const presets = rangePresets([[1, __('24 hours')], [7, __('7 days')], [30, __('30 days')], [90, __('90 days')]]);
  const vsLabels: Record<CompareMode, string> = {
    previous: __('vs. previous period'), week: __('vs. same period last week'), month: __('vs. same period last month'), year: __('vs. same period last year'), none: '',
  };
  const modeLabels: Record<CompareMode, string> = {
    previous: __('Previous period'), week: __('Same period last week'), month: __('Same period last month'), year: __('Same period last year'), none: __('No comparison'),
  };
  const cmpNames: Record<CompareMode, string> = {
    previous: __('previous period'), week: __('same period last week'), month: __('same period last month'), year: __('same period last year'), none: '',
  };
  const pick = (m: CompareMode) => { setMode(m); saveMode(m); };
  // A shift longer than the selected period would count the same days twice (the server refuses it too), so those choices are greyed out.
  const len = range.kind === 'days' ? range.days : Math.max(1, Math.round((Date.parse(range.to) - Date.parse(range.from)) / 86400000) + 1);
  const fits: Record<CompareMode, boolean> = { previous: true, none: true, week: len <= 9, month: len <= 31, year: len <= 366 };

  return (
    <div>
      <h1 className="uf-title">{__('Overview')}</h1>
      <p className="uf-sub">{__('Native analytics stored on this site. Times are shown in the site timezone.')}</p>
      <div className="uf-filter" role="group" aria-label={__('Date range')}>
        {presets.map(([d, l]) => (
          <button key={d} className={'button' + (range.kind === 'days' && range.days === d ? ' button-primary' : '')} onClick={() => setRange({ kind: 'days', days: d })}>{l}</button>
        ))}
        {CustomRange ? <CustomRange active={range.kind === 'custom'} onApply={(from, to) => setRange({ kind: 'custom', from, to })} /> : null}
      </div>
      <div className="uf-cmp-bar">
        <label>{__('Compare with')}
          <select value={mode} onChange={(e) => pick((e.target as HTMLSelectElement).value as CompareMode)}>
            {MODES.map((m) => <option key={m} value={m} disabled={!fits[m] && m !== mode}>{modeLabels[m] + (fits[m] ? '' : ' — ' + __('needs a shorter range'))}</option>)}
          </select>
        </label>
      </div>
      <Async q={q}>
        {(d) => {
          const pv = d.previous;
          const vs = vsLabels[d.compare] || vsLabels.previous;
          const cmpName = cmpNames[d.compare] || cmpNames.previous;
          const k = d.kpis;
          const days = d.daily;
          const chip = (now: number, before: number | undefined, invert?: boolean) => <DeltaChip now={now} before={pv ? before : null} invert={invert} vs={vs} />;
          const onePct = k.sessions ? Math.round((100 * k.single_page) / k.sessions) : null;
          const prevOnePct = pv && pv.sessions ? Math.round((100 * pv.single_page) / pv.sessions) : undefined;
          const channels = d.channels.map((c) => { const [label, ck] = channelInfo(c.key); return { key: c.key, label, value: c.sessions, k: ck }; });
          const counted = channels.reduce((a, c) => a + c.value, 0);
          if (k.sessions > counted) { channels.push({ key: 'other', label: __('Other'), value: k.sessions - counted, k: 0 }); }
          const devTotal = d.devices.reduce((a, r) => a + r.sessions, 0);
          const spark = (f: (r: (typeof days)[number]) => number, name: string, kk: number) => <Sparkline values={days.map(f)} k={kk} label={`${name}: ${__('trend over the selected period')}`} />;
          const more = (slug: string, label: string) => ({ href: screen(slug), label });
          const panels: PanelDef[] = [
            { id: 'highlights', title: __('What to look at'), wide: true, node: can('highlights') && slots().HighlightsPanel ? (() => { const P = slots().HighlightsPanel!; return <P params={params} />; })() : null },
            { id: 'focus', title: __('Focus'), wide: true, node: window.userfloApp.siteType && window.userfloApp.siteType !== 'general' ? <FocusPanel params={params} /> : null },
            { id: 'bots', title: __('Humans, AI crawlers and bots'), wide: true, node: <Card title={__('Humans, AI crawlers and bots')} more={more('stats', __('Statistics'))}><BotCounter params={params} humans={{ visitors: k.visitors, pageviews: k.pageviews }} /></Card> },
            { id: 'trend', title: __('Traffic over time'), wide: true, node: <Card className="uf-ov-trend"><TrendChart title={__('Traffic over time')} rows={d.daily} prev={pv && pv.daily ? pv.daily : null} compareLabel={cmpName} /></Card> },
            { id: 'sources', title: __('Traffic sources'), node: (
              <CycleCard id={'sources_' + srcTab} title={__('Traffic sources')} more={more('stats', __('Statistics'))}
                rows={srcTab === 'channels' ? channels.map((c) => ({ key: c.key, label: c.label, value: c.value })) : d.referrers.map((r) => ({ key: r.source, label: r.source, value: r.sessions }))}
                centre={num(k.sessions)} centreLabel={__('sessions')}
                head={(
                  <div className="uf-seg" role="group" aria-label={__('Group traffic by')}>
                    <button type="button" aria-pressed={srcTab === 'channels'} onClick={() => setSrcTab('channels')}>{__('Channels')}</button>
                    <button type="button" aria-pressed={srcTab === 'sources'} onClick={() => setSrcTab('sources')}>{__('Sources')}</button>
                  </div>)}
                list={srcTab === 'channels' ? (
                  channels.length ? (<>
                    <BarList rows={channels} total={k.sessions} swatch />
                    <p className="uf-ov-note">{__('Channels are grouped from each visit\'s referrer and utm_* parameters. They are a heuristic grouping, not a measured value.')}</p>
                  </>) : <Empty>{__('No data recorded for this period yet.')}</Empty>
                ) : d.referrers.length ? (
                  <BarList rows={d.referrers.map((r) => ({ key: r.source, label: r.source, value: r.sessions }))} total={k.sessions} k={2} />
                ) : <Empty>{__('No data recorded for this period yet.')}</Empty>} />) },
            { id: 'popular', title: __('Popular pages'), node: (
              <CycleCard id="popular" title={__('Popular pages')} more={more('pages', __('Open Pages'))} centre={num(k.pageviews)} centreLabel={__('page views')}
                rows={d.pages.map((p) => ({ key: p.url, label: p.url, value: p.views }))}
                list={d.pages.length ? (
                  <BarList k={1} total={k.pageviews}
                    rows={d.pages.map((p) => ({ key: p.url, label: <code>{p.url}</code>, sub: `${p.title ? p.title + ' · ' : ''}${num(p.visitors)} ${__('visitors')} · ${duration(p.avg_engaged)} ${__('avg. engaged')}`, value: p.views }))} />
                ) : <Empty>{__('No data recorded for this period yet.')}</Empty>} />) },
            { id: 'devices', title: __('Devices'), node: (
              <CycleCard id="devices" title={__('Devices')} more={more('stats', __('Statistics'))} order={['donut', 'pie', 'list', 'columns', 'stack', 'rose', 'radar']}
                rows={d.devices.map((r) => ({ key: r.key, label: deviceInfo(r.key)[0], value: r.sessions }))} centre={num(devTotal)} centreLabel={__('sessions')}
                list={d.devices.length ? (
                  <BarList total={devTotal} rows={d.devices.map((r) => { const [label, kk] = deviceInfo(r.key); return { key: r.key, label, value: r.sessions, k: kk }; })} swatch />
                ) : <Empty>{__('No data recorded for this period yet.')}</Empty>} />
              ) },
            { id: 'countries', title: __('Top countries'), node: (
              <CycleCard id="countries" title={__('Top countries')} more={more('map', __('Open Map'))} centre={num(d.countries.reduce((a, c) => a + c.sessions, 0))} centreLabel={__('sessions')}
                rows={d.countries.map((c) => ({ key: c.code, label: c.name || c.code, value: c.sessions }))}
                mapRows={d.geo ? d.countries.map((c) => ({ code: c.code, label: c.name || c.code, value: c.sessions })) : undefined} mapUnit={__('sessions')}
                note={d.geo && d.countries.length ? <p className="uf-ov-note">{__('Share of the sessions that could be located.')}</p> : null}
                list={!d.geo ? (
                  <Empty>{__('Visitor locations are not being recorded.')} <a href={cfg().urls.settings}>{__('Turn on geolocation in Settings')}</a></Empty>
                ) : d.countries.length ? (
                  <BarList k={3} total={d.countries.reduce((a, c) => a + c.sessions, 0)} rows={d.countries.map((c) => ({ key: c.code, label: c.name || c.code, value: c.sessions }))} />
                ) : <Empty>{__('No located visits in this period yet.')}</Empty>} />) },
            { id: 'sales', title: __('Sales'), wide: true, node: modOn('woocommerce') ? <CommerceCard params={params} /> : null },
            { id: 'goals', title: __('Goals'), wide: true, node: modOn('goals') ? <GoalsCard params={params} /> : null },
            { id: 'recent', title: __('Recent sessions'), wide: true, node: (
              <Card title={__('Recent sessions')} more={more('visitors', __('All visitors'))}>
                {d.recent.length ? (
                  <div className="uf-scroll"><table className="widefat striped">
                    <thead><tr><th>{__('Visitor')}</th><th>{__('Started')}</th><th>{__('Elapsed')}</th><th>{__('Pages')}</th><th>{__('Entry')}</th><th>{__('Status')}</th></tr></thead>
                    <tbody>{d.recent.map((s) => (
                      <tr key={s.id}>
                        <td><a href={`${cfg().urls.visitors}#/visitor/${s.visitor_id}/session/${s.id}`}>{s.label}</a> {s.logged_in ? <span className="uf-badge uf-badge-user">{__('Logged-in')}</span> : null}</td>
                        <td>{dateTime(s.started_at)}</td><td>{duration(s.elapsed)}</td><td>{s.pageviews}</td><td><code>{s.entry}</code></td>
                        <td><span className={'uf-badge uf-badge-' + (s.state === 'active' ? 'live' : s.state === 'logged_out_observed' ? 'obs' : 'est')}>{s.state === 'active' ? __('Active') : s.state === 'logged_out_observed' ? __('Logged out (observed)') : __('Ended (estimated)')}</span></td>
                      </tr>))}</tbody>
                  </table></div>
                ) : <Empty>{__('No sessions recorded yet. Browse the front-end of your site and reload this page.')}</Empty>}
              </Card>) },
            { id: 'external', title: __('External analytics'), wide: true, node: <External /> },
          ];
          return (
          <div>
            <div className="uf-kpis">
              <Kpi k={3} label={__('Visitors')} value={num(k.visitors)} hint={`${num(k.new_visitors)} ${__('new')}`} chip={chip(k.visitors, pv ? pv.visitors : undefined)} spark={spark((r) => r.visitors, __('Visitors'), 3)} />
              <Kpi k={4} label={__('Returning visitors')} value={num(k.returning)} hint={k.visitors ? `${Math.round((100 * k.returning) / k.visitors)}% ${__('of visitors')}` : undefined} chip={chip(k.returning, pv ? pv.returning : undefined)} />
              <Kpi k={2} label={__('Sessions')} value={num(k.sessions)} hint={`${num(k.logged_in)} ${__('logged-in')}`} chip={chip(k.sessions, pv ? pv.sessions : undefined)} spark={spark((r) => r.sessions, __('Sessions'), 2)} />
              <Kpi k={1} label={__('Page views')} value={num(k.pageviews)} chip={chip(k.pageviews, pv ? pv.pageviews : undefined)} spark={spark((r) => r.pageviews, __('Page views'), 1)} />
              <Kpi k={5} label={__('Avg. session')} value={duration(k.avg_session)} hint={__('elapsed')} chip={chip(k.avg_session, pv ? pv.avg_session : undefined)} />
              <Kpi k={4} label={__('Engaged time')} value={duration(k.engaged)} hint={__('total, active only')} chip={chip(k.engaged, pv ? pv.engaged : undefined)} spark={spark((r) => r.engaged, __('Engaged time'), 4)} />
              <Kpi k={6} label={__('One-page sessions')} value={onePct === null ? '—' : onePct + '%'} hint={__('left after a single page view')} chip={onePct === null ? null : chip(onePct, prevOnePct, true)}
                spark={spark((r) => (r.sessions ? Math.round((100 * r.single) / r.sessions) : 0), __('One-page sessions'), 6)} />
              <Kpi k={2} label={__('Active now')} value={num(k.active_now)} hint={__('last 5 minutes')} />
            </div>
            <p className="uf-ov-note">{pv
              ? sprintf(__('Chips compare with the %s.'), cmpName)
              : d.compare === 'none' ? __('Comparison is switched off.')
                : __('No comparison shown: the comparison period is not fully covered by recorded data, or it overlaps the selected dates too much.')}</p>
            <Board storeKey="overview" bare wireframe gridClass="uf-boardgrid uf-ov-board" panels={panels} />
          </div>
        ); }}
      </Async>
    </div>
  );
}
