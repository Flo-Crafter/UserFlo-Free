import { h, __, can, rangePresets, useState, sprintf } from '../wp';
import { useApi } from '../hooks';
import type { Journeys as JY } from '../types';
import { num } from '../format';
import { Async, Card, Empty, LimitNote, Stat } from '../components/ui';
import { Board } from '../components/Board';
import { slots } from '../slots';

const pct = (x: number): string => `${Math.round(x * 1000) / 10}%`;

/**
 * Journey analytics. With Pro active and entitled the Pro bundle supplies the full screen (flow diagram, exploring by selection, filters,
 * comparison, day/week/month/custom periods); this is the basic summary every edition has: the top entrances, recorded exits and complete
 * paths for the usual rolling periods, from the same shared data.
 */
export function Journeys() {
  const Full = slots().JourneysFull;
  if (Full && can('journey_analysis')) { return <Full />; }
  return <JourneysBasic />;
}

function JourneysBasic() {
  const [days, setDays] = useState(7);
  const q = useApi<JY>('stats/journeys', { days });
  const presets = rangePresets([[1, __('24 hours')], [7, __('7 days')], [30, __('30 days')]]);
  return (
    <div className="uf-jy">
      <h1 className="uf-title">{__('Journeys')}</h1>
      <p className="uf-sub">{__('Where visits begin, the pages they move through and where they end. Counts are sessions.')}</p>
      <div className="uf-jy-bar"><div role="group" aria-label={__('Period')}>
        {presets.map(([n, l]) => <button key={n} type="button" className={'button' + (days === n ? ' button-primary' : '')} aria-pressed={days === n} onClick={() => setDays(n)}>{l}</button>)}
      </div></div>
      <div className={q.loading && q.data ? 'uf-stale' : ''}>
        <Async q={q}>
          {(r) => r.state === 'nodata' ? (
            <Card><Empty>{__('No sessions with recorded page views in this period. Try a longer period, or check that tracking was running then.')}</Empty></Card>
          ) : (
            <div>
              <div className="uf-stats">
                <Stat label={__('Sessions in period')} value={num(r.summary.sessions)} hint={__('with a recorded page view')} />
                <Stat label={__('One-page sessions')} value={r.summary.sessions ? pct(r.summary.single_page / r.summary.sessions) : '—'} hint={__('only one page recorded')} />
                <Stat label={__('Different entrance pages')} value={num(r.entrances.distinct || r.entrances.items.length)} />
              </div>
              {r.summary.sampled ? <p className="uf-jy-note" role="note">{sprintf(__('This period has %1$s sessions; the most recent %2$s are analysed. Choose a shorter period for every session.'), num(r.summary.sessions_in_period), num(r.summary.analysed))}</p> : null}
              {r.summary.excluded_retention ? <p className="uf-jy-note" role="note">{sprintf(__('%d sessions that began before your data-retention cutoff are left out, because their first pages may already be deleted.'), r.summary.excluded_retention)}</p> : null}

              <Board storeKey="journeys-basic" panels={[
                { id: 'entrances', title: __('Entrances'), node: (<div>
                <p className="uf-sub">{sprintf(__('The first page recorded in a session. %s sessions in this view.'), num(r.entrances.total))}</p>
                <div className="uf-scroll"><table className="widefat striped uf-compact">
                  <thead><tr><th scope="col">{__('Entrance page')}</th><th scope="col">{__('Sessions')}</th><th scope="col">{__('Share of sessions')}</th><th scope="col">{__('Most common next step')}</th></tr></thead>
                  <tbody>{r.entrances.items.map((e) => (
                    <tr key={e.page}>
                      <td>{r.titles && r.titles[e.page] ? r.titles[e.page] : e.page}{r.titles && r.titles[e.page] ? <div><small><code>{e.page}</code></small></div> : null}</td>
                      <td>{num(e.count)}</td><td>{pct(e.share)}</td>
                      <td>{e.next.length ? <small>{e.next[0].page === null ? __('session ended') : '→ ' + e.next[0].page} · {num(e.next[0].count)} ({pct(e.next[0].share)})</small> : '—'}</td>
                    </tr>
                  ))}</tbody>
                </table></div>
                {(r.entrances.distinct || 0) > r.entrances.items.length ? <p><small>{sprintf(__('Showing the top %1$d of %2$d.'), r.entrances.items.length, r.entrances.distinct)}</small></p> : null}
              </div>) },
                { id: 'paths', title: __('Most common complete paths'), node: (<div>
                <p className="uf-sub">{__('The first five pages of each session, in order. A path ending in “…” continued with more pages.')}</p>
                {(() => {
                  const items = Array.isArray(r.paths) ? [] : r.paths.items;
                  return (
                    <ol className="uf-jy-pathlist">{items.map((p, i) => (
                      <li key={i}><span className="uf-jy-pathsteps">{p.steps.map((s, k) => <span key={k}><code>{s}</code>{k < p.steps.length - 1 ? <span aria-label={__('then')}> → </span> : null}</span>)}{p.complete ? <small> ({__('session ended')})</small> : <span> …</span>}</span>
                        <span className="uf-jy-pathn">{num(p.count)} · {pct(p.share)}</span></li>
                    ))}</ol>
                  );
                })()}
              </div>) },
                { id: 'exits', title: __('Recorded session exits'), node: (<div>
                <p className="uf-sub">{sprintf(__('The last page recorded in a session (%s sessions in this view). FloLens only knows that no later page view was recorded; it cannot tell whether the visitor decided to leave.'), num(r.exits.total))}</p>
                <div className="uf-scroll"><table className="widefat striped uf-compact">
                  <thead><tr><th scope="col">{__('Last recorded page')}</th><th scope="col">{__('Recorded exits')}</th><th scope="col">{__('Share of all recorded exits')}</th><th scope="col">{__('Exits per view of this page')}</th></tr></thead>
                  <tbody>{r.exits.items.map((e) => (
                    <tr key={e.page}>
                      <td>{r.titles && r.titles[e.page] ? r.titles[e.page] : e.page}{r.titles && r.titles[e.page] ? <div><small><code>{e.page}</code></small></div> : null}</td>
                      <td>{num(e.count)}</td><td>{pct(e.share)}</td>
                      <td>{e.rate === null ? '—' : <span>{pct(e.rate)} <small>{sprintf(__('of %s views'), num(e.views))}</small></span>}</td>
                    </tr>
                  ))}</tbody>
                </table></div>
                {(r.exits.distinct || 0) > r.exits.items.length ? <p><small>{sprintf(__('Showing the top %1$d of %2$d.'), r.exits.items.length, r.exits.distinct)}</small></p> : null}
              </div>) },
              ]} />

              <details className="uf-jy-defs">
                <summary>{__('How these numbers are counted')}</summary>
                <ul>
                  <li>{__('Everything counts sessions: a visit is counted once however many pages it viewed. A session belongs to the day it started.')}</li>
                  <li>{__('A session’s path is its page views in time order. Reloading the same page does not add a step; coming back to a page later does.')}</li>
                  <li>{__('The entrance is the first page recorded, the exit the last. They come from the ordered page views, so they can differ slightly from the Statistics screen, which reads the address of the last report received. Several tabs in one session are not told apart.')}</li>
                  <li>{__('No conversions, revenue or attribution are shown: FloLens does not record them for these paths.')}</li>
                </ul>
              </details>
            </div>
          )}
        </Async>
      </div>
      <LimitNote feature="journey_analysis">{__('The flow diagram, exploring by selection, filters, comparison, the paths leading to each exit, and day, week, month and custom periods are part of Pro.')}</LimitNote>
    </div>
  );
}
