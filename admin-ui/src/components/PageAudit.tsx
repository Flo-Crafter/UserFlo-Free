import { h, __, useState, can, modOn, sprintf, cfg } from '../wp';
import { useApi } from '../hooks';
import { send } from '../api';
import type { AuditPages } from '../types';
import { dateTime } from '../format';
import { Async, Card, Empty, LimitNote } from './ui';
import { HealthDot, classLabel } from './Health';
import { slots } from '../slots';

/** "Scan this page again" (administrators). The server allows one scan per page every ten minutes. */
export function ScanButton(p: { url: string; onDone: () => void }) {
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState('');
  if (!cfg().canExclude) { return null; }
  const run = async () => {
    setBusy(true); setMsg('');
    try { await send('POST', 'audit/scan', { url: p.url }); setMsg(__('Scanned.')); p.onDone(); } catch (e) { setMsg((e as Error).message); }
    setBusy(false);
  };
  return <span><button type="button" className="button" disabled={busy} onClick={run}>{busy ? __('Scanning…') : __('Scan again')}</button>{msg ? <span role="status" className="uf-scan-msg"> {msg}</span> : null}</span>;
}

/** The audit panel on a page's detail screen: the full report in Pro, the basic checks in Lite. */
export function PageAudit(p: { url: string; days: number }) {
  const full = can('page_audit') && modOn('page_intelligence') && !!slots().PageReport;
  const q = useApi<AuditPages>(full ? null : 'audit/pages', { search: p.url, per_page: 5 });
  if (!modOn('audit')) { return null; }
  if (full) { const R = slots().PageReport!; return <R url={p.url} days={p.days} />; }
  return (
    <Card title={__('Page audit')}>
      <Async q={q}>
        {(d) => {
          const row = d.items.find((r) => r.url === p.url);
          if (!row) { return <div><Empty>{__('This page has not been scanned yet. UserFlo reads a few pages at a time in the background.')}</Empty><ScanButton url={p.url} onDone={q.reload} /></div>; }
          const active = row.findings.filter((f) => !f.excluded);
          return (
            <div>
              <p><HealthDot health={{ state: row.state, level: row.level, red: row.red, amber: row.amber, excluded: row.excluded, findings: row.findings }} /> <small>{row.scanned_at ? sprintf(__('Scanned %s'), dateTime(row.scanned_at + ' UTC')) : ''}</small></p>
              {active.length ? <ul className="uf-findings">{active.map((f, i) => <li key={i}><strong>{f.title}</strong> <span className="uf-badge">{classLabel(f.class)}</span>{f.evidence ? <small> {f.evidence}</small> : null}</li>)}</ul> : row.level >= 0 ? <p>{__('The basic checks found nothing to fix on this page.')}</p> : null}
              <p><ScanButton url={p.url} onDone={q.reload} /></p>
              <LimitNote feature="page_audit">{__('The free version checks that a page loads, has a title and is open to search engines. Pro adds descriptions, headings, image alt text, links, forms and a report per page.')}</LimitNote>
            </div>
          );
        }}
      </Async>
    </Card>
  );
}
