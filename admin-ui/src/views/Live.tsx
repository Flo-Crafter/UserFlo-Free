import { h, __, useState } from '../wp';
import { duration, num, timeOnly } from '../format';
import { Badge, Card, Empty, Stat } from '../components/ui';
import { describe, useLive, visitorLink } from '../components/useLive';

export function Live() {
  const [paused, setPaused] = useState(false);
  const { active, items, fresh, hour, error, loaded, updated } = useLive(!paused);

  const loggedIn = active.filter((s) => s.logged_in).length;
  return (
    <div>
      <h1 className="uf-title">{__('Live')}</h1>
      <p className="uf-sub">{__('Updates every 5 seconds while this tab is visible. “Active” means activity in the last 5 minutes.')}</p>
      <div className="uf-filter">
        <button className="button" onClick={() => setPaused(!paused)} aria-pressed={paused}>{paused ? __('Resume') : __('Pause')}</button>
        <span className={'uf-badge ' + (paused ? 'uf-badge-est' : 'uf-badge-live')} role="status">{paused ? __('Paused') : __('Live')}</span>
        {updated ? <small className="uf-sub">{__('Last update')}: {timeOnly(updated)}</small> : null}
      </div>
      {error ? <div className="notice notice-error inline" role="alert"><p>{error}</p></div> : null}
      <div className="uf-stats">
        <Stat label={__('Active now')} value={num(active.length)} hint={__('last 5 minutes')} />
        <Stat label={__('Logged-in')} value={num(loggedIn)} hint={__('of active')} />
        <Stat label={__('Anonymous')} value={num(active.length - loggedIn)} hint={__('of active')} />
        <Stat label={__('Page views')} value={num(hour)} hint={__('last hour')} />
      </div>
      <div className="uf-cols">
        <Card title={__('Active visitors')}>
          {!loaded ? <p className="uf-loading">{__('Loading…')}</p> : active.length === 0 ? <Empty>{__('Nobody active right now.')}</Empty> : (
            <div className="uf-scroll"><table className="widefat striped uf-table">
              <thead><tr><th>{__('Visitor')}</th><th>{__('Current page')}</th><th>{__('Time on site')}</th><th>{__('Location')}</th><th>{__('Device')}</th></tr></thead>
              <tbody>{active.map((s) => (
                <tr key={s.id}>
                  <td><a href={visitorLink(s.visitor_id, s.id)} className="uf-strong">{s.label}</a> {s.logged_in ? <Badge kind="user">{__('Logged-in')}</Badge> : <Badge>{__('Anonymous')}</Badge>}</td>
                  <td><code>{s.exit}</code></td><td>{duration(s.elapsed)}</td><td>{s.location || '—'}</td><td>{[s.device, s.browser].filter(Boolean).join(' · ')}</td>
                </tr>))}</tbody>
            </table></div>
          )}
        </Card>
        <Card title={__('Activity feed')}>
          {!loaded ? <p className="uf-loading">{__('Loading…')}</p> : items.length === 0 ? <Empty>{__('No activity yet.')}</Empty> : (
            <ul className="uf-feed">{items.map((i) => (
              <li key={i.key} className={fresh[i.key] ? 'uf-feed-new' : ''}>
                <span className="uf-tl-time">{timeOnly(i.at)}</span>{' '}
                <a href={visitorLink(i.visitor_id, i.session_id)} className="uf-strong">{i.label}</a>{' '}
                {i.logged_in ? <Badge kind="user">{__('Logged-in')}</Badge> : null}{' '}
                <span>{describe(i)}</span>
              </li>))}</ul>
          )}
        </Card>
      </div>
    </div>
  );
}
