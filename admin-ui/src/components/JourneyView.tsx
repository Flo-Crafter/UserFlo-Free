import { h, __, useState, can, sprintf } from '../wp';
import { slots } from '../slots';
import { useApi } from '../hooks';
import type { Journey } from '../types';
import { dateTime, duration, timeOnly } from '../format';
import { Async, Card, LimitNote, Pager, PdfLink, StateBadge } from './ui';

const MODE_KEY = 'uf_journey_mode';
type Mode = 'timeline' | 'visual';
function savedMode(): Mode {
  // A choice remembered while Pro was active simply falls back to the timeline without it; nothing is lost.
  if (!can('journey_visual') || !slots().JourneyVisual) { return 'timeline'; }
  try { return window.localStorage.getItem(MODE_KEY) === 'visual' ? 'visual' : 'timeline'; } catch (e) { return 'timeline'; }
}

/** Session journey: the chronological timeline (default) or the visual journey. Both are paginated server-side, never the whole history at once. */
export function JourneyView(p: { sessionId: number; onSession?: (id: number) => void }) {
  const Visual = slots().JourneyVisual;
  const visualOk = can('journey_visual') && !!Visual;
  const [mode, setMode] = useState<Mode>(savedMode());
  const choose = (m: Mode) => { setMode(m); try { window.localStorage.setItem(MODE_KEY, m); } catch (e) { /* not available: the choice just is not remembered */ } };
  return (
    <Card title={__('Session journey')} id="uf-journey">
      {visualOk ? (
        <div className="uf-vj-toggle" role="group" aria-label={__('Journey view')}>
          <button type="button" className={'button' + (mode === 'timeline' ? ' button-primary' : '')} aria-pressed={mode === 'timeline'} onClick={() => choose('timeline')}>{__('Timeline')}</button>
          <button type="button" className={'button' + (mode === 'visual' ? ' button-primary' : '')} aria-pressed={mode === 'visual'} onClick={() => choose('visual')}>{__('Visual journey')}</button>
        </div>
      ) : null}
      {can('reports') ? <p><PdfLink params={{ type: 'session', id: p.sessionId }} label={__('Session PDF')} /></p> : null}
      {visualOk && mode === 'visual' && Visual ? <Visual sessionId={p.sessionId} onSession={p.onSession || (() => undefined)} /> : <JourneyTimeline sessionId={p.sessionId} />}
      {!visualOk ? <LimitNote feature="journey_visual">{__('The visual journey — connected pages, engaged time per page, the moment a visitor signs in and moving between sessions — is part of Pro.')}</LimitNote> : null}
    </Card>
  );
}

/** Chronological session journey. */
function JourneyTimeline(p: { sessionId: number }) {
  const [page, setPage] = useState(1);
  const PER = 100;
  const q = useApi<Journey>(`sessions/${p.sessionId}`, { page, per_page: PER });
  return (
    <div>
      <Async q={q}>
        {(j) => (
          <div>
            <p>
              <StateBadge state={j.session.state} /> {dateTime(j.session.started_at)} → {dateTime(j.session.last_activity)} · {__('elapsed')} {duration(j.session.elapsed)} · {__('engaged')} {duration(j.session.engaged)}
              {' · '}{[j.session.device, j.session.browser, j.session.os, j.session.screen].filter(Boolean).join(' ')}
            </p>
            <p><small>{__('Elapsed time is last activity minus start. A session end is only “observed” when a logout was recorded; otherwise it is estimated from inactivity.')}</small></p>
            <ol className="uf-timeline">
              {j.items.map((it) => it.kind === 'pageview' ? (
                <li key={'p' + it.id} className="uf-tl-pv">
                  <span className="uf-tl-time">{timeOnly(it.at)}</span>
                  <strong>{it.title || it.url}</strong><br />
                  <code>{it.url}</code> <small>{`${__('engaged')} ${duration(it.engaged)} · ${__('scrolled')} ${it.scroll}%`}</small>
                </li>
              ) : (
                <li key={'e' + it.id} className={'uf-tl-ev uf-tl-' + it.type}>
                  <span className="uf-tl-time">{timeOnly(it.at)}</span>
                  <span className="uf-badge">{it.type}</span> {it.name}
                  {it.value ? <span> <code>{it.value}</code></span> : null}
                  {it.component ? <span className="uf-comp">{it.component}</span> : null}
                  {it.target ? <small> {it.target}</small> : null}
                </li>
              ))}
            </ol>
            <Pager page={page} total={j.total} perPage={PER} onPage={setPage} />
            {j.limited ? <LimitNote feature="history_extended">{sprintf(__('Showing the %1$d most recent of %2$d events in this session. Earlier events are kept and become available with Pro.'), j.total, j.real_total || j.total)}</LimitNote> : null}
          </div>
        )}
      </Async>
    </div>
  );
}
