import { h, __, useState, useEffect, useRef } from '../wp';
import { get } from '../api';

/** The guide that explains each screen (the same guides as Settings → Help). A screen with no entry has no button. */
const GUIDES: Record<string, string> = {
  overview: 'install', visitors: 'privacy', journeys: 'journey-analytics', stats: 'campaigns', map: 'geolocation', live: 'map-globe', pages: 'audit',
  calendar: 'traffic-calendar', goals: 'goals', elements: 'elements', exclusions: 'exclusions', reports: 'email-reports', audit: 'audit',
};
export const guideFor = (view: string): string => GUIDES[view] || '';

interface Guide { title: string; html: string }

function GuideDialog(p: { slug: string; onClose: () => void }) {
  const [slug, setSlug] = useState(p.slug);
  const [g, setG] = useState<Guide | null>(null);
  const [err, setErr] = useState('');
  const box = useRef<HTMLDivElement | null>(null);
  useEffect(() => {
    setG(null); setErr('');
    get<Guide>('help/guides/' + slug).then(setG).catch((e: Error) => setErr(e.message || __('Guide not found.')));
  }, [slug]);
  useEffect(() => {
    const prev = document.activeElement as HTMLElement | null;
    const key = (e: KeyboardEvent) => { if (e.key === 'Escape') { p.onClose(); } };
    document.addEventListener('keydown', key);
    document.body.classList.add('uf-modal-open');
    if (box.current) { box.current.focus(); }
    return () => { document.removeEventListener('keydown', key); document.body.classList.remove('uf-modal-open'); if (prev) { prev.focus(); } };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  return (
    <div className="uf-modal-bg" onClick={(e) => { if (e.target === e.currentTarget) { p.onClose(); } }}>
      <div className="uf-modal uf-tier-silver uf-guide-modal" role="dialog" aria-modal="true" aria-labelledby="uf-guide-title" tabIndex={-1} ref={box}>
        <div className="uf-modal-top"><h2 id="uf-guide-title">{g ? g.title : err ? __('Guide not found.') : __('Loading…')}</h2><button type="button" className="button" aria-label={__('Close')} onClick={p.onClose}>×</button></div>
        {err ? <p>{err}</p> : null}
        {g ? <div className="uf-guide-body" onClick={(e) => { const a = (e.target as HTMLElement).closest('a[data-guide]'); if (a) { e.preventDefault(); setSlug(a.getAttribute('data-guide') || slug); } }} dangerouslySetInnerHTML={{ __html: g.html }} /> : null}
      </div>
    </div>
  );
}

/** "Guide" button: opens this screen's guide in a dialog without leaving the page. The guide text is FloLens's own bundled documentation. */
export function GuideButton(p: { view: string }) {
  const slug = guideFor(p.view);
  const [open, setOpen] = useState(false);
  if (!slug) { return null; }
  return (
    <span className="uf-guide-btn-wrap">
      <button type="button" className="button uf-guide-btn" onClick={() => setOpen(true)} title={__('Open the guide for this page')}><span aria-hidden="true">?</span> {__('Guide')}</button>
      {open ? <GuideDialog slug={slug} onClose={() => setOpen(false)} /> : null}
    </span>
  );
}
