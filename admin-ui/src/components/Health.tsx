import { h, __, sprintf } from '../wp';
import type { FindingClass, PageHealth } from '../types';

/** Words for a health level, so the dot is never the only signal. */
export const healthText = (level: number, state: string): string => {
  if (level < 0) { return state === 'queued' ? __('Waiting to be scanned') : state === 'skipped' ? __('Could not be read') : state === 'error' ? __('Could not be scanned') : __('Not scanned'); }
  return level === 2 ? __('Issues') : level === 1 ? __('Warnings') : __('No issues');
};

/** Green / amber / red dot with a text label (and counts in the tooltip): colour is never the only signal. */
export function HealthDot(p: { health: PageHealth | undefined; compact?: boolean; url?: string }) {
  const hp = p.health;
  if (!hp) { return <span className="uf-health uf-health-none"><span className="uf-hdot" aria-hidden="true" />{p.compact ? null : <span>{__('Not scanned')}</span>}</span>; }
  const cls = hp.level < 0 ? 'none' : hp.level === 2 ? 'red' : hp.level === 1 ? 'amber' : 'green';
  const txt = healthText(hp.level, hp.state);
  const detail = hp.level < 0 ? txt : `${txt} — ${sprintf(__('%1$d issues, %2$d warnings'), hp.red, hp.amber)}${hp.excluded ? ', ' + sprintf(__('%d excluded'), hp.excluded) : ''}`;
  return <span className={'uf-health uf-health-' + cls} title={detail}><span className="uf-hdot" aria-hidden="true" />{p.url && hp.level >= 0 ? <a className={p.compact ? 'screen-reader-text' : ''} href={'#/p/' + encodeURIComponent(p.url)} title={__('Open the page audit')}>{txt}</a> : <span className={p.compact ? 'screen-reader-text' : ''}>{txt}</span>}</span>;
}

export const classLabel = (c: FindingClass): string => (c === 'verified' ? __('Verified') : c === 'warning' ? __('Warning') : __('Recommendation'));
