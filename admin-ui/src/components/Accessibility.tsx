import type * as React from 'react';
import { h, __, sprintf } from '../wp';
import { Card, Empty } from './ui';
import type { A11yCheckRow, A11yOverviewRow, A11yStatus, AuditFinding, Detect, ManualTopic, Wcag } from '../types';

/** Words for how sure an accessibility check is. Never "compliant": automated checks cannot establish WCAG conformance. */
export const detectLabel = (d: Detect | '' | undefined): string => (d === 'issue' ? __('Issue detected') : d === 'potential' ? __('Potential issue') : d === 'manual' ? __('Manual review required') : '');
export const statusLabel = (s: A11yStatus): string => (s === 'pass' ? __('Passed automated check') : s === 'not_tested' ? __('Not tested') : s === 'none' ? __('Nothing to test') : s === 'excluded' ? __('Excluded by a rule') : detectLabel(s));

const wcagUrl = (sc: string): string => 'https://www.w3.org/WAI/WCAG22/Understanding/' + ({ '1.1.1': 'non-text-content', '1.2.1': 'audio-only-and-video-only-prerecorded', '1.2.2': 'captions-prerecorded', '1.2.3': 'audio-description-or-media-alternative-prerecorded', '1.3.1': 'info-and-relationships', '1.3.5': 'identify-input-purpose', '1.4.2': 'audio-control', '1.4.3': 'contrast-minimum', '1.4.4': 'resize-text', '1.4.10': 'reflow', '1.4.12': 'text-spacing', '1.4.11': 'non-text-contrast', '2.1.1': 'keyboard', '2.1.2': 'no-keyboard-trap', '2.4.1': 'bypass-blocks', '2.4.2': 'page-titled', '2.4.3': 'focus-order', '2.4.4': 'link-purpose-in-context', '2.4.6': 'headings-and-labels', '2.4.7': 'focus-visible', '2.4.11': 'focus-not-obscured-minimum', '2.5.8': 'target-size-minimum', '3.1.1': 'language-of-page', '3.3.1': 'error-identification', '3.3.2': 'labels-or-instructions', '4.1.2': 'name-role-value' } as Record<string, string>)[sc] + '.html';

/** WCAG 2.2 success criteria a finding relates to, each linking to W3C's explanation. */
export function WcagChips(p: { wcag: Wcag[] | undefined }) {
  if (!p.wcag || !p.wcag.length) { return null; }
  return <span className="uf-wcag">{p.wcag.map((w) => <a key={w.sc} className="uf-wcag-chip" href={wcagUrl(w.sc)} target="_blank" rel="noopener noreferrer" title={sprintf(__('WCAG 2.2 success criterion %1$s (level %2$s)'), w.sc, w.level)}>{w.sc} <small>{w.level}</small></a>)} </span>;
}

/** The standing honesty note: shown wherever accessibility results appear. */
export function A11yNote() {
  return <p className="uf-sub">{__('Automated checks find some accessibility problems and flag others for review, but they cannot establish WCAG conformance: many criteria need a person using the page with a keyboard and a screen reader. “Passed” means only that the condition tested was met. UserFlo reads the HTML your server sends, so anything added by JavaScript or set in style sheets it does not read is not seen.')}</p>;
}

export function ManualList(p: { topics: ManualTopic[] }) {
  if (!p.topics.length) { return null; }
  return (
    <details className="uf-finding-more">
      <summary>{sprintf(__('Needs a person: %d things automated checks cannot decide'), p.topics.length)}</summary>
      <ul className="uf-findings">{p.topics.map((t) => <li key={t.sc} className="uf-finding uf-finding-recommendation"><div className="uf-finding-top"><strong>{t.title}</strong> <span className="uf-badge">{__('Manual review required')}</span> <WcagChips wcag={[{ sc: t.sc, level: t.level }]} /></div><div><small>{t.what}</small></div></li>)}</ul>
    </details>
  );
}

/** Accessibility on the Page health screen: how many findings of each check the scanned pages have. Lite and Pro. */
export function A11yOverview(p: { a11y: { pages_issue: number; pages_potential: number; overview: A11yOverviewRow[]; manual: ManualTopic[] } }) {
  const a = p.a11y;
  return (
    <Card title={__('Accessibility')}>
      <A11yNote />
      {a.overview.length ? (
        <div>
          <p>{sprintf(__('Scanned pages with at least one detected issue: %1$d. With a potential issue: %2$d.'), a.pages_issue, a.pages_potential)}</p>
          <div className="uf-scroll"><table className="widefat striped uf-table">
            <thead><tr><th scope="col">{__('Check')}</th><th scope="col">{__('Result')}</th><th scope="col">{__('WCAG 2.2')}</th><th scope="col">{__('Found')}</th></tr></thead>
            <tbody>{a.overview.map((r) => <tr key={r.code}><td>{r.title}</td><td><span className={'uf-badge uf-detect-' + r.detect}>{detectLabel(r.detect)}</span></td><td><WcagChips wcag={r.wcag} /></td><td>{r.count}</td></tr>)}</tbody>
          </table></div>
        </div>
      ) : <Empty>{__('No accessibility findings among the pages scanned so far. That does not mean the site is accessible: see what automated checks cannot decide.')}</Empty>}
      <ManualList topics={a.manual} />
    </Card>
  );
}

/** One page's accessibility: findings by how sure the check is, then the checks that passed or could not be made, then the manual list. */
export function A11yPage(p: { findings: AuditFinding[]; checklist: A11yCheckRow[]; manual: ManualTopic[]; row: (f: AuditFinding) => React.ReactElement }) {
  const groups: Detect[] = ['issue', 'potential', 'manual'];
  const passed = p.checklist.filter((c) => c.status === 'pass');
  const untested = p.checklist.filter((c) => c.status === 'not_tested');
  return (
    <Card title={__('Accessibility')}>
      <A11yNote />
      {groups.map((g) => {
        const list = p.findings.filter((f) => f.detect === g);
        return list.length ? <div key={g}><h4>{detectLabel(g) + ' (' + list.length + ')'}</h4><ul className="uf-findings">{list.map((f) => p.row(f))}</ul></div> : null;
      })}
      {!p.findings.length ? <p>{__('The automated accessibility checks found nothing on this page. This is not a statement that the page is accessible.')}</p> : null}
      {passed.length ? <details className="uf-finding-more"><summary>{sprintf(__('Passed automated checks (%d)'), passed.length)}</summary><ul>{passed.map((c) => <li key={c.code}>{c.title} <WcagChips wcag={c.wcag} /> <small>{sprintf(__('%d tested'), c.tested)}</small></li>)}</ul></details> : null}
      {untested.length ? <details className="uf-finding-more"><summary>{sprintf(__('Not tested on this page (%d)'), untested.length)}</summary><ul>{untested.map((c) => <li key={c.code}>{c.title} <WcagChips wcag={c.wcag} /></li>)}</ul><p className="uf-sub">{__('UserFlo could not make these checks from the page HTML (for example, no text colours or backgrounds were set directly on the page, or the page uses style sheets UserFlo does not read). They are not passed.')}</p></details> : null}
      <ManualList topics={p.manual} />
    </Card>
  );
}
