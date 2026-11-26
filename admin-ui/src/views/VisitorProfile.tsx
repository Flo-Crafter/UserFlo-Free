import { h, __, useState, can, sprintf } from '../wp';
import { useApi } from '../hooks';
import { del } from '../api';
import type { Paged, Session, VisitorDetail } from '../types';
import { dateTime, duration, todayKey } from '../format';
import { Async, Badge, Card, LimitNote, Pager, PdfLink, Stat } from '../components/ui';
import { slots } from '../slots';
import { SessionsTable } from '../components/SessionsTable';
import { JourneyView } from '../components/JourneyView';

export interface ProfileState { month?: string; day?: string; session?: number }

export function VisitorProfile(p: { id: number; state: ProfileState; onState: (s: ProfileState) => void; onBack: () => void }) {
  const month = p.state.month || todayKey().slice(0, 7);
  const day = p.state.day || '';
  const [page, setPage] = useState(1);
  const [deleting, setDeleting] = useState(false);
  const [delErr, setDelErr] = useState('');
  const PER = 20;

  const VisitorCalendar = slots().VisitorCalendar;
  const v = useApi<VisitorDetail>(`visitors/${p.id}`);
  const sess = useApi<Paged<Session>>(`visitors/${p.id}/sessions`, { page, per_page: PER });

  const open = (s: Session) => { p.onState({ ...p.state, session: s.id }); setTimeout(() => { const el = document.getElementById('uf-journey'); if (el && el.scrollIntoView) { el.scrollIntoView({ behavior: 'smooth' }); } }, 50); };

  async function remove() {
    if (!window.confirm(__('Delete this visitor and all recorded activity? This cannot be undone.'))) { return; }
    setDeleting(true);
    try { await del(`visitors/${p.id}`); p.onBack(); } catch (e) { setDelErr((e as Error).message); setDeleting(false); }
  }

  return (
    <div>
      <p><button className="button-link" onClick={p.onBack}>{'« ' + __('All visitors')}</button></p>
      <Async q={v}>
        {(d) => (
          <div>
            <Card className="uf-profile">
              <div className="uf-profile-head">
                <div className="uf-avatar" aria-hidden="true">{d.label.charAt(0).toUpperCase()}</div>
                <div>
                  <h1 className="uf-title uf-h-sm">{d.label}</h1>
                  {d.logged_in ? <Badge kind="user">{__('Logged-in user')}</Badge> : <Badge>{__('Anonymous visitor')}</Badge>} {d.returning ? <Badge kind="ret">{__('Returning')}</Badge> : null}
                  {d.profile_url ? (
                    <a className="button button-small uf-userlink" href={d.profile_url} {...(d.profile_target === 'new' ? { target: '_blank', rel: 'noopener noreferrer' } : {})}>
                      <span className="dashicons dashicons-admin-users" aria-hidden="true" /> {__('Open WordPress profile')}{d.profile_target === 'new' ? <span className="screen-reader-text"> {__('(opens in a new tab)')}</span> : null}
                    </a>
                  ) : null}
                  {d.username ? <small> {[d.username, d.email, d.roles.join(', ')].filter(Boolean).join(' · ')}</small> : null}
                  <div><small>{__('ID')}: <code>{d.uid}</code></small></div>
                  {can('reports') ? <p><PdfLink params={{ type: 'visitor', id: d.id }} label={__('Visitor PDF report')} /></p> : null}
                </div>
              </div>
              <div className="uf-stats">
                <Stat label={__('First seen')} value={dateTime(d.first_seen)} small />
                <Stat label={__('Last seen')} value={dateTime(d.last_seen)} small />
                <Stat label={__('Sessions')} value={String(d.sessions)} />
                <Stat label={__('Page views')} value={String(d.pageviews)} />
                <Stat label={__('Engaged time')} value={duration(d.engaged)} hint={__('active only')} />
                <Stat label={__('Visit days')} value={String(d.return.active_days)} hint={d.return.avg_gap_days === null ? __('single day so far') : `${__('every')} ${d.return.avg_gap_days} ${__('days on average')}`} />
              </div>
              <table className="widefat striped uf-kv"><tbody>
                <tr><th>{__('First landing page')}</th><td><code>{d.first_landing}</code></td></tr>
                <tr><th>{__('First referrer')}</th><td>{d.first_referrer || __('Direct / none')}</td></tr>
                <tr><th>{__('Device')}</th><td>{[d.device, d.browser, d.os].filter(Boolean).join(' · ')}</td></tr>
                {can('identity_detail') ? <tr><th>{__('IP (as stored)')}</th><td>{d.ip || __('Not stored (disabled in privacy settings)')}</td></tr> : null}
                {d.identified_at ? <tr><th>{__('Identified as account since')}</th><td>{dateTime(d.identified_at)} <small>({__('earlier visits from this browser were attributed to the account')})</small></td></tr> : null}
                <tr><th>{__('Approx. location')}</th><td>{d.location || __('Unknown (geolocation not enabled)')}</td></tr>
              </tbody></table>
            </Card>

            {VisitorCalendar ? <VisitorCalendar id={p.id} month={month} day={day} session={p.state.session} onMonth={(m) => p.onState({ ...p.state, month: m, day: '' })} onDay={(dd) => p.onState({ ...p.state, day: dd })} onOpen={open} /> : null}

            <Card title={can('history_extended') ? __('Session history') : __('Recent sessions')}>
              <Async q={sess} empty={(x) => x.items.length === 0} emptyText={__('No sessions.')}>
                {(x) => (<div><SessionsTable items={x.items} onOpen={open} selected={p.state.session} /><Pager page={page} total={x.total} perPage={PER} onPage={setPage} />{x.limited ? <LimitNote feature="history_extended">{sprintf(__('Showing the %1$d most recent of %2$d sessions. Older sessions are kept and become available with Pro.'), x.total, x.real_total || x.total)}</LimitNote> : null}</div>)}
              </Async>
            </Card>

            {p.state.session ? <JourneyView key={p.state.session} sessionId={p.state.session} onSession={(id) => p.onState({ ...p.state, session: id })} /> : null}

            <Card className="uf-danger" title={__('Delete this visitor')}>
              <p>{__('Permanently deletes this visitor profile with all sessions, page views and events.')}</p>
              {delErr ? <p className="uf-err" role="alert">{delErr}</p> : null}
              <button className="button button-link-delete" disabled={deleting} onClick={remove}>{__('Delete visitor history')}</button>
            </Card>
          </div>
        )}
      </Async>
    </div>
  );
}
