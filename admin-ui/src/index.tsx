import type * as React from 'react';
import { animationsOn, canSweep, playWhenVisible } from './motion';
import { h, Fragment, useState, useEffect, render, createRoot, createPortal } from './wp';
import { Overview } from './views/Overview';
import { VisitorList } from './views/VisitorList';
import { Live } from './views/Live';
import { Pages } from './views/Pages';
import { MapView } from './views/Map';
import { slots } from './slots';
import { Stats } from './views/Stats';
import { Journeys } from './views/Journeys';
import { TrafficCalendar } from './views/TrafficCalendar';
import { Achievements } from './views/Achievements';
import { Goals } from './views/Goals';
import { Exclusions } from './views/Exclusions';
import { Elements } from './views/Elements';
import { VisitorProfile, ProfileState } from './views/VisitorProfile';
import { ExportButton } from './components/ExportCsv';
import { exportSetsFor } from './exportSets';
import { GuideButton, guideFor } from './components/GuideButton';

interface Route { visitor: number | null; state: ProfileState }

/** #/visitor/12/session/5?month=2026-10&day=2026-10-14 */
export function parseHash(hash: string): Route {
  const [path, query = ''] = hash.replace(/^#\/?/, '').split('?');
  const m = /^visitor\/(\d+)(?:\/session\/(\d+))?$/.exec(path);
  if (!m) { return { visitor: null, state: {} }; }
  const q = new URLSearchParams(query);
  const month = q.get('month') || undefined, day = q.get('day') || undefined;
  return {
    visitor: Number(m[1]),
    state: {
      session: m[2] ? Number(m[2]) : undefined,
      month: month && /^\d{4}-(0[1-9]|1[0-2])$/.test(month) ? month : undefined,
      day: day && /^\d{4}-\d{2}-\d{2}$/.test(day) ? day : undefined,
    },
  };
}

export function buildHash(id: number | null, s: ProfileState = {}): string {
  if (id === null) { return '#/'; }
  const q = new URLSearchParams();
  if (s.month) { q.set('month', s.month); }
  if (s.day) { q.set('day', s.day); }
  const qs = q.toString();
  return `#/visitor/${id}${s.session ? '/session/' + s.session : ''}${qs ? '?' + qs : ''}`;
}

function VisitorsApp() {
  const [route, setRoute] = useState<Route>(parseHash(window.location.hash));
  useEffect(() => {
    const on = () => setRoute(parseHash(window.location.hash));
    window.addEventListener('hashchange', on);
    return () => window.removeEventListener('hashchange', on);
  }, []);
  const go = (id: number | null, s: ProfileState = {}) => { window.location.hash = buildHash(id, s); };
  if (route.visitor === null) { return <VisitorList onOpen={(id) => go(id)} />; }
  return <VisitorProfile key={route.visitor} id={route.visitor} state={route.state} onState={(s) => go(route.visitor, s)} onBack={() => go(null)} />;
}

/** Puts the "Export CSV" button above a screen. A visitor's own page (the visitors screen with a profile open) has nothing to export. */
function WithExport(p: { view: string; children: React.ReactNode }) {
  const [hash, setHash] = useState(window.location.hash);
  useEffect(() => {
    const on = () => setHash(window.location.hash);
    window.addEventListener('hashchange', on);
    return () => window.removeEventListener('hashchange', on);
  }, []);
  const sets = exportSetsFor(p.view);
  // The button sits in the UserFlo bar at the top, next to the distraction-free button (an empty placeholder is printed there).
  const slot = document.getElementById('uf-brand-export');
  const profile = p.view === 'visitors' && parseHash(hash).visitor !== null;
  const detail = p.view === 'pages' && /^#\/p\//.test(hash);
  // Pro adds "Copy for AI" next to Guide (only when the AI feature is on). It is left out where a screen shows one visitor or one page.
  const Ai = slots().CopyForAi;
  const btn = (window as unknown as { userfloApp?: { buttons?: { guide: boolean; ai: boolean; export: boolean } } }).userfloApp?.buttons;
  const showGuide = !btn || btn.guide, showExport = !btn || btn.export;
  const aiOn = !!Ai && !!(window as unknown as { userfloApp?: { ai?: boolean } }).userfloApp?.ai && (!btn || btn.ai) && !profile && !detail;
  const tools = (
    <>
      {showGuide ? <GuideButton view={p.view} /> : null}
      {aiOn && Ai ? <Ai view={p.view} /> : null}
      {showExport && sets.length && !profile && !detail ? <ExportButton sets={sets} /> : null}
    </>
  );
  const any = (showGuide && !!guideFor(p.view)) || aiOn || (showExport && sets.length > 0 && !profile && !detail);
  return (
    <div>
      {any ? (slot && createPortal ? createPortal(tools, slot) : <div className="uf-export-bar">{tools}</div>) : null}
      {p.children}
    </div>
  );
}

function mount() {
  const root = document.getElementById('userflo-app');
  if (!root) { return; }
  // One switch for every animation: the owner's setting and the device's "reduce motion" choice (see motion.ts). Without the class nothing moves.
  if (animationsOn()) { root.classList.add('uf-anim'); if (canSweep()) { root.classList.add('uf-anim-sweep'); } playWhenVisible(root); }
  const view = root.getAttribute('data-view');
  const app = view === 'visitors' ? <VisitorsApp /> : view === 'live' ? <Live /> : view === 'pages' ? <Pages /> : view === 'map' ? <MapView /> : view === 'reports' ? (slots().ReportsView ? (() => { const R = slots().ReportsView!; return <R />; })() : <Overview />) : view === 'stats' ? <Stats /> : view === 'calendar' ? <TrafficCalendar /> : view === 'journeys' ? <Journeys /> : view === 'goals' ? <Goals /> : view === 'exclusions' ? <Exclusions /> : view === 'elements' ? <Elements /> : view === 'achievements' ? <Achievements /> : view === 'audit' ? (slots().AuditView ? (() => { const A = slots().AuditView!; return <A />; })() : <Overview />) : <Overview />;
  const wrapped = <WithExport view={view || ''}>{app}</WithExport>;
  if (createRoot) { createRoot(root).render(wrapped); } else { render(wrapped, root); }
}
mount();
