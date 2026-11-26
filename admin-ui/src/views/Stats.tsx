import { h, __, useState, can, modOn, rangePresets } from '../wp';
import { useApi } from '../hooks';
import type { Insights, InsightRow, Overview as OverviewData } from '../types';
import { Viz } from '../components/Viz';
import { PeakTimes } from '../components/PeakTimes';
import { BotCounter } from '../components/BotCounter';
import { YearCompare } from '../components/YearCompare';
import { duration, num } from '../format';
import { Async, Empty } from '../components/ui';
import { Board } from '../components/Board';
import type { PanelDef } from '../components/Board';
import { slots } from '../slots';
import { CampaignReport } from '../components/CampaignReport';
import { ShopReport } from '../components/ShopReport';
import { ExcludeButton } from '../components/Exclude';
import type { ExcludeSubject } from '../components/Exclude';

function Bars(p: { rows: { label: string; sub?: string; count: number; ex?: ExcludeSubject }[]; empty: string; onDone?: () => void }) {
  if (!p.rows.length) { return <Empty>{p.empty}</Empty>; }
  const max = Math.max(1, ...p.rows.map((r) => r.count));
  return (
    <ol className="uf-rank">
      {p.rows.map((r, i) => (
        <li key={i}>
          <div className="uf-rank-top"><span className="uf-rank-label" title={r.label}>{r.label}</span><span className="uf-rank-n">{num(r.count)}</span>{r.ex ? <ExcludeButton subject={r.ex} label={r.label} onDone={p.onDone} /> : null}</div>
          <div className="uf-rank-bar" aria-hidden="true"><span style={{ width: Math.max(2, (r.count / max) * 100) + '%' }} /></div>
          {r.sub ? <small>{r.sub}</small> : null}
        </li>
      ))}
    </ol>
  );
}

/** Rows of an interaction ranking. `by` says which recorded value the row is identified by, so the exclude dialog offers the right choices. */
const ev = (rows: InsightRow[], etype: string, by: 'target' | 'value' | 'name', useName = false) => rows.map((r) => ({
  label: useName ? r.name || r.key : r.key,
  sub: useName ? undefined : [r.name, r.component].filter(Boolean).join(' · '),
  count: r.count,
  ex: { kind: 'element' as const, etype, name: r.name, component: r.component, target: by === 'target' ? r.key : undefined, value: by === 'value' ? r.key : undefined },
}));
const pg = (rows: { key: string; count: number }[]) => rows.map((r) => ({ label: r.key, count: r.count, ex: { kind: 'page' as const, url: r.key } }));

/** Statistics: with the Pro Statistics Builder on, a switcher offers the standard page and the user's own dashboards. */
export function Stats() {
  const D = slots().StatsDashboards;
  if (D && can('statistics_builder') && modOn('dashboards')) { return <D classic={<ClassicStats />} />; }
  return <ClassicStats />;
}

function ClassicStats() {
  const [days, setDays] = useState(30);
  const [view, setView] = useState<'behaviour' | 'campaigns' | 'shop' | 'wordpress'>('behaviour');
  const q = useApi<Insights>(view === 'behaviour' ? 'stats/insights' : null, { days });
  const ov = useApi<OverviewData>(view === 'behaviour' ? 'stats/overview' : null, { days, compare: 'none' });
  const kp = ov.data ? ov.data.kpis : null;
  const presets = rangePresets([[7, __('7 days')], [30, __('30 days')], [90, __('90 days')], [366, __('1 year')]]);
  return (
    <div>
      <h1 className="uf-title">{__('Statistics')}</h1>
      <p className="uf-sub">{__('What visitors view, click and do across the whole site. Cached for 2 minutes.')}</p>
      <div className="uf-filter" role="group" aria-label={__('Date range')}>
        {presets.map(([d, l]) => <button key={d} className={'button' + (days === d ? ' button-primary' : '')} onClick={() => setDays(d)}>{l}</button>)}
      </div>
      <div className="uf-subtabs" role="group" aria-label={__('Report type')}>
        <button className={'button' + (view === 'behaviour' ? ' button-primary' : '')} aria-pressed={view === 'behaviour'} onClick={() => setView('behaviour')}>{__('Behaviour')}</button>
        <button className={'button' + (view === 'campaigns' ? ' button-primary' : '')} aria-pressed={view === 'campaigns'} onClick={() => setView('campaigns')}>{__('Campaigns')}</button>
        {modOn('woocommerce') ? <button className={'button' + (view === 'shop' ? ' button-primary' : '')} aria-pressed={view === 'shop'} onClick={() => setView('shop')}>{__('Shop')}</button> : null}
        {can('wordpress_reports') && modOn('wp_reports') ? <button className={'button' + (view === 'wordpress' ? ' button-primary' : '')} aria-pressed={view === 'wordpress'} onClick={() => setView('wordpress')}>{__('WordPress content, forms and shop')}</button> : null}
      </div>
      {view === 'campaigns' ? <CampaignReport days={days} /> : null}
      {view === 'shop' && modOn('woocommerce') ? <ShopReport days={days} /> : null}
      {view === 'behaviour' && can('advanced_tracking') && modOn('advanced_tracking') && slots().ContactClicks ? (() => { const P = slots().ContactClicks!; return <P params={{ days }} />; })() : null}
      {view === 'wordpress' && slots().WordPressReport ? (() => { const P = slots().WordPressReport!; return <P params={{ days }} />; })() : null}
      {view === 'behaviour' ? <Async q={q}>
        {(d) => {
          const panels: PanelDef[] = [
            { id: 'pages', title: __('Most viewed pages'), node: <Bars rows={d.pages.map((p) => ({ label: p.url, sub: `${p.title ? p.title + ' · ' : ''}${num(p.visitors)} ${__('visitors')} · ${duration(p.avg_engaged)} ${__('avg. engaged')}`, count: p.views, ex: { kind: 'page' as const, url: p.url } }))} empty={__('No page views in this period.')} onDone={q.reload} /> },
            { id: 'buttons', title: __('Most clicked buttons and elements'), node: <Bars rows={ev(d.buttons, 'click', 'target')} onDone={q.reload} empty={__('No button clicks recorded.')} /> },
            { id: 'links', title: __('Most clicked internal links'), node: <Bars rows={ev(d.links, 'click', 'value')} onDone={q.reload} empty={__('No internal link clicks recorded.')} /> },
            { id: 'outbound', title: __('Most visited outbound links'), node: <Bars rows={ev(d.outbound, 'outbound', 'value')} onDone={q.reload} empty={__('No outbound clicks recorded.')} /> },
            { id: 'downloads', title: __('Most downloaded files'), node: <Bars rows={ev(d.downloads, 'download', 'value')} onDone={q.reload} empty={__('No downloads recorded.')} /> },
            { id: 'forms', title: __('Forms submitted'), node: <Bars rows={ev(d.forms, 'form', 'target')} onDone={q.reload} empty={__('No form submissions recorded.')} /> },
            { id: 'custom', title: __('Custom events'), node: <Bars rows={ev(d.custom, 'custom', 'name', true)} onDone={q.reload} empty={__('No custom events recorded.')} /> },
            { id: 'journeys', title: __('Most common user journeys'), wide: true, node: (
              <div>
                <p className="uf-sub">{__('First pages visited in a session (up to 4 steps), from the newest 3,000 sessions in the period.')} {d.journeys.sessions ? `${num(d.journeys.single_page)} / ${num(d.journeys.sessions)} ${__('sessions viewed only one page.')}` : ''}</p>
                {d.journeys.top.length ? (
                  <ol className="uf-journeys">
                    {d.journeys.top.map((j, i) => (
                      <li key={i}><span className="uf-rank-n">{num(j.count)}×</span> {j.steps.map((s, k) => <span key={k}>{k ? <span aria-hidden="true"> → </span> : null}<code>{s}</code></span>)}</li>
                    ))}
                  </ol>
                ) : <Empty>{__('No multi-page journeys in this period.')}</Empty>}
              </div>
            ) },
            { id: 'entry', title: __('Top entry pages'), node: <Bars rows={pg(d.entry)} onDone={q.reload} empty={__('No data.')} /> },
            { id: 'exit', title: __('Top exit pages'), node: <Bars rows={pg(d.exit)} onDone={q.reload} empty={__('No data.')} /> },
            { id: 'devices', title: __('Devices'), node: <Viz id="stats-devices" initial="donut" rows={d.devices.map((r) => ({ key: r.key, label: r.key, value: r.count }))} centreLabel={__('sessions')} empty={__('No data.')} /> },
            { id: 'browsers', title: __('Browsers'), node: <Viz id="stats-browsers" initial="pie" rows={d.browsers.map((r) => ({ key: r.key, label: r.key, value: r.count }))} centreLabel={__('sessions')} empty={__('No data.')} /> },
            { id: 'bots', title: __('Humans, AI crawlers and bots'), wide: true, node: <BotCounter params={{ days }} full humans={{ visitors: kp ? kp.visitors : 0, pageviews: kp ? kp.pageviews : 0 }} /> },
            { id: 'peaks', title: __('Peak times and peak days'), wide: true, node: <PeakTimes days={days} /> },
            { id: 'year', title: __('Compared with last year'), wide: true, node: <YearCompare days={days} /> },
            ...(kp ? [
              { id: 'newret', title: __('New and returning visitors'), node: <Viz id="stats-newret" initial="donut" centreLabel={__('visitors')} rows={[{ key: 'new', label: __('New visitors'), value: kp.new_visitors }, { key: 'ret', label: __('Returning visitors'), value: kp.returning }]} empty={__('No visitors in this period.')} /> },
              { id: 'loggedin', title: __('Logged-in and anonymous sessions'), node: <Viz id="stats-loggedin" initial="stack" centreLabel={__('sessions')} rows={[{ key: 'in', label: __('Logged-in sessions'), value: kp.logged_in }, { key: 'anon', label: __('Anonymous sessions'), value: Math.max(0, kp.sessions - kp.logged_in) }]} empty={__('No sessions in this period.')} /> },
              { id: 'depth', title: __('Single-page and multi-page sessions'), node: <Viz id="stats-depth" initial="donut" centreLabel={__('sessions')} rows={[{ key: 'single', label: __('Viewed one page'), value: kp.single_page }, { key: 'multi', label: __('Viewed more pages'), value: Math.max(0, kp.sessions - kp.single_page) }]} empty={__('No sessions in this period.')} /> },
            ] : []),
          ];
          return <Board storeKey="stats" grid wireframe panels={panels} />;
        }}
      </Async> : null}
    </div>
  );
}
