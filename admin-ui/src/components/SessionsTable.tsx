import { h, __ } from '../wp';
import type { Session } from '../types';
import { dateTime, duration } from '../format';
import { StateBadge } from './ui';

export function SessionsTable(p: { items: Session[]; onOpen: (s: Session) => void; selected?: number | null }) {
  return (
    <div className="uf-scroll">
      <table className="widefat striped">
        <thead><tr>
          <th>{__('Started')}</th><th>{__('Elapsed')}</th><th>{__('Engaged')}</th><th>{__('Pages')}</th><th>{__('Events')}</th>
          <th>{__('Entry → exit')}</th><th>{__('Login / logout')}</th><th>{__('Status')}</th>
        </tr></thead>
        <tbody>
          {p.items.map((s) => (
            <tr key={s.id} className={p.selected === s.id ? 'uf-row-sel' : ''}>
              <td><button className="button-link" onClick={() => p.onOpen(s)} aria-label={`${__('Open journey')}: ${dateTime(s.started_at)}`}>{dateTime(s.started_at)}</button></td>
              <td>{duration(s.elapsed)}</td>
              <td>{duration(s.engaged)}</td>
              <td>{s.pageviews}</td>
              <td>{s.events}</td>
              <td><code>{s.entry}</code> → <code>{s.exit}</code></td>
              <td>{s.login_at ? dateTime(s.login_at) : '—'} / {s.logout_at ? dateTime(s.logout_at) : '—'}</td>
              <td><StateBadge state={s.state} /></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
