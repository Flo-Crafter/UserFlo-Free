import { __ } from './wp';
import { get } from './api';
import type { Cell, ExportRange, ExportSet } from './components/ExportCsv';
import type { ElementsList, GoalStats, Insights, InsightRow, Journeys, Overview, Paged, Visitor, PageListRow } from './types';

type Row = Record<string, Cell>;

/** The REST parameters for a period: a number of days back, or exact dates. */
const rp = (r: ExportRange): Record<string, string | number> => (r.from ? { from: r.from, to: r.to || r.from } : { days: r.days });

const pct = (x: number | null | undefined): string => (x === null || x === undefined ? '' : String(Math.round(x * 1000) / 10) + '%');

/** The first and last day (YYYY-MM-DD, inclusive) of a period, for tables the server cannot cut to dates itself. */
function span(r: ExportRange): [string, string] {
  if (r.from) { return [r.from, r.to || r.from]; }
  const end = new Date(); const start = new Date(end.getTime() - r.days * 86400000);
  return [start.toISOString().slice(0, 10), end.toISOString().slice(0, 10)];
}

/** Reads every page of a list (100 rows a time, at most 5,000 rows) and hands each row to `keep`. */
async function allPages<T>(path: string, params: Record<string, string | number>, keep: (r: T) => boolean): Promise<T[]> {
  const out: T[] = [];
  for (let page = 1; page <= 50; page++) {
    const d = await get<Paged<T>>(path, { ...params, page, per_page: 100 });
    d.items.forEach((x) => { if (keep(x)) { out.push(x); } });
    if (page * 100 >= d.total || !d.items.length) { break; }
  }
  return out;
}

const visitors: ExportSet = {
  id: 'visitors', label: __('Visitors'), custom: true,
  note: __('Visitors last seen in this period.'),
  columns: [
    { id: 'visitor', label: __('Visitor') }, { id: 'type', label: __('Type') }, { id: 'email', label: __('Email') }, { id: 'first_seen', label: __('First seen') }, { id: 'last_seen', label: __('Last seen') },
    { id: 'returning', label: __('Returning') }, { id: 'sessions', label: __('Sessions') }, { id: 'pageviews', label: __('Page views') }, { id: 'events', label: __('Events') }, { id: 'engaged', label: __('Engaged (seconds)') },
    { id: 'location', label: __('Location') }, { id: 'device', label: __('Device') }, { id: 'browser', label: __('Browser') }, { id: 'os', label: __('Operating system') },
  ],
  load: async (r) => {
    const [a, b] = span(r);
    const list = await allPages<Visitor>('visitors', { type: 'all', orderby: 'last_seen', order: 'desc' }, (v) => v.last_seen.slice(0, 10) >= a && v.last_seen.slice(0, 10) <= b);
    return list.map((v): Row => ({ visitor: v.label, type: v.logged_in ? __('Logged-in') : __('Anonymous'), email: v.email, first_seen: v.first_seen, last_seen: v.last_seen, returning: v.returning, sessions: v.sessions, pageviews: v.pageviews, events: v.events, engaged: v.engaged, location: v.location, device: v.device, browser: v.browser, os: v.os }));
  },
};

const journeys = (id: string, label: string, first: string, pick: (j: Journeys) => Row[]): ExportSet => ({
  id: 'journeys-' + id, label, custom: true,
  note: __('Counts are sessions with a recorded page view.'),
  columns: [{ id: 'name', label: first }, { id: 'sessions', label: __('Sessions') }, { id: 'share', label: __('Share of sessions') }, ...(id === 'exits' ? [{ id: 'views', label: __('Page views') }, { id: 'rate', label: __('Exit rate') }] : [])],
  load: async (r) => { const j = await get<Journeys>('stats/journeys', rp(r)); return j.state === 'nodata' ? [] : pick(j); },
});
const journeySets: ExportSet[] = [
  journeys('entrances', __('Entrance pages'), __('Entrance page'), (j) => j.entrances.items.map((e) => ({ name: e.page, sessions: e.count, share: pct(e.share) }))),
  journeys('exits', __('Exit pages'), __('Exit page'), (j) => j.exits.items.map((e) => ({ name: e.page, sessions: e.count, share: pct(e.share), views: e.views, rate: pct(e.rate) }))),
  journeys('paths', __('Common paths'), __('Path'), (j) => (Array.isArray(j.paths) ? [] : j.paths.items.map((p) => ({ name: p.steps.join(' > '), sessions: p.count, share: pct(p.share) })))),
];

const insightSet = (id: string, label: string, first: string, pick: (d: Insights) => { name: string; detail?: string; count: number }[]): ExportSet => ({
  id: 'stats-' + id, label, custom: true,
  columns: [{ id: 'name', label: first }, { id: 'detail', label: __('Detail') }, { id: 'count', label: __('Count') }],
  load: async (r) => pick(await get<Insights>('stats/insights', rp(r))).map((x): Row => ({ name: x.name, detail: x.detail, count: x.count })),
});
const ev = (rows: InsightRow[]) => rows.map((x) => ({ name: x.key, detail: [x.name, x.component].filter(Boolean).join(' · '), count: x.count }));
const statsSets: ExportSet[] = [
  insightSet('pages', __('Most viewed pages'), __('Page'), (d) => d.pages.map((p) => ({ name: p.url, detail: p.title, count: p.views }))),
  insightSet('buttons', __('Most clicked buttons and elements'), __('Element'), (d) => ev(d.buttons)),
  insightSet('links', __('Most clicked internal links'), __('Link'), (d) => ev(d.links)),
  insightSet('outbound', __('Most visited outbound links'), __('Link'), (d) => ev(d.outbound)),
  insightSet('downloads', __('Most downloaded files'), __('File'), (d) => ev(d.downloads)),
  insightSet('forms', __('Forms submitted'), __('Form'), (d) => ev(d.forms)),
  insightSet('custom', __('Custom events'), __('Event'), (d) => ev(d.custom)),
  insightSet('entry', __('Top entry pages'), __('Page'), (d) => d.entry.map((x) => ({ name: x.key, count: x.count }))),
  insightSet('exit', __('Top exit pages'), __('Page'), (d) => d.exit.map((x) => ({ name: x.key, count: x.count }))),
  insightSet('devices', __('Devices'), __('Device'), (d) => d.devices.map((x) => ({ name: x.key, count: x.count }))),
  insightSet('browsers', __('Browsers'), __('Browser'), (d) => d.browsers.map((x) => ({ name: x.key, count: x.count }))),
];

const pages: ExportSet = {
  id: 'pages', label: __('Pages'), custom: true,
  columns: [{ id: 'url', label: __('Page') }, { id: 'title', label: __('Title') }, { id: 'views', label: __('Views') }, { id: 'visitors', label: __('Visitors') }, { id: 'avg_engaged', label: __('Avg. engaged (seconds)') }, { id: 'health', label: __('Health') }],
  load: async (r) => (await get<{ items: PageListRow[] }>('pages', { ...rp(r), bots: 0 })).items.map((p): Row => ({ url: p.url, title: p.title, views: p.views, visitors: p.visitors, avg_engaged: p.avg_engaged, health: p.health ? (p.health.level < 0 ? __('Not scanned') : p.health.level === 2 ? __('Issues') : p.health.level === 1 ? __('Warnings') : __('No issues')) : '' })),
};

const calendar: ExportSet = {
  id: 'calendar', label: __('Traffic by day'), custom: true,
  note: __('One row per day, in the site time zone.'),
  columns: [{ id: 'date', label: __('Date') }, { id: 'visitors', label: __('Visitors') }, { id: 'sessions', label: __('Sessions') }, { id: 'pageviews', label: __('Page views') }, { id: 'engaged', label: __('Engaged (seconds)') }, { id: 'single', label: __('One-page sessions') }],
  load: async (r) => (await get<Overview>('stats/overview', { ...rp(r), compare: 'none' })).daily.map((d): Row => ({ ...d })),
};

const goals: ExportSet = {
  id: 'goals', label: __('Goals'), custom: true,
  columns: [{ id: 'name', label: __('Goal') }, { id: 'type', label: __('Type') }, { id: 'status', label: __('Status') }, { id: 'completions', label: __('Completions') }, { id: 'rate', label: __('Conversion rate (%)') }, { id: 'value', label: __('Value') }, { id: 'prev', label: __('Previous period completions') }],
  load: async (r) => (await get<GoalStats>('goals/stats', rp(r))).goals.map((g): Row => ({ name: g.name, type: g.type, status: g.status, completions: g.completions, rate: g.rate, value: g.value, prev: g.prev })),
};

const elements: ExportSet = {
  id: 'elements', label: __('Elements'),
  columns: [{ id: 'name', label: __('Name') }, { id: 'kind', label: __('Kind') }, { id: 'identifier', label: __('Identifier') }, { id: 'value', label: __('Value') }, { id: 'href', label: __('Link address') }, { id: 'pages', label: __('Pages') }, { id: 'instances', label: __('Instances') }, { id: 'interactions', label: __('Interactions') }, { id: 'visitors', label: __('Visitors') }, { id: 'logged_in', label: __('Logged-in') }, { id: 'anonymous', label: __('Anonymous') }],
  load: async (r) => {
    const out: Row[] = [];
    for (let page = 1; page <= 50; page++) {
      const d = await get<ElementsList>('elements', { days: r.days, audience: 'all', kind: '', sort: 'interactions', dir: 'desc', paged: page, per_page: 100 });
      d.items.forEach((e) => out.push({ name: e.alias || e.label, kind: e.kind, identifier: e.identifier, value: e.value, href: e.href, pages: e.pages, instances: e.instances, interactions: e.interactions, visitors: e.visitors, logged_in: e.logged_in, anonymous: e.anonymous }));
      if (page * 100 >= d.total || !d.items.length) { break; }
    }
    return out;
  },
};

interface AuditRow { [k: string]: Cell | string[] }
const audit: ExportSet = {
  id: 'audit', label: __('Audit'), noRange: true,
  columns: [
    { id: 'url', label: __('Page') }, { id: 'title', label: __('Title') }, { id: 'title_len', label: __('Title length') }, { id: 'description', label: __('Meta description') }, { id: 'desc_len', label: __('Description length') },
    { id: 'focus_keyword', label: __('Focus keyword') }, { id: 'indexable', label: __('Indexable') }, { id: 'canonical', label: __('Canonical') },
    { id: 'h1', label: 'H1' }, { id: 'h2', label: 'H2' }, { id: 'h3', label: 'H3' }, { id: 'h4', label: 'H4' }, { id: 'h5', label: 'H5' }, { id: 'h6', label: 'H6' },
    { id: 'links_internal', label: __('Internal links') }, { id: 'links_external', label: __('External links') }, { id: 'files', label: __('File links') }, { id: 'images', label: __('Images') }, { id: 'img_no_alt', label: __('Images without alt text') }, { id: 'img_no_size', label: __('Images without width/height') },
    { id: 'buttons', label: __('Buttons') }, { id: 'forms', label: __('Forms') }, { id: 'og', label: __('Social sharing tags') }, { id: 'issues', label: __('Issues') }, { id: 'warnings', label: __('Warnings') }, { id: 'a11y_issues', label: __('Accessibility issues detected') }, { id: 'a11y_potential', label: __('Potential accessibility issues') }, { id: 'findings', label: __('Findings') }, { id: 'scanned_at', label: __('Scanned') },
  ],
  load: async () => {
    const d = await get<{ items: AuditRow[]; titles: Record<string, string> }>('audit/table', {});
    return d.items.map((r): Row => ({ ...(r as Row), findings: ((r.codes as string[]) || []).map((c) => d.titles[c] || c).join('; ') }));
  },
};

/** The tables each screen can export, by the view name the page was mounted with. */
export function exportSetsFor(view: string): ExportSet[] {
  switch (view) {
    case 'visitors': return [visitors];
    case 'journeys': return journeySets;
    case 'stats': return statsSets;
    case 'pages': return [pages];
    case 'calendar': return [calendar];
    case 'goals': return [goals];
    case 'elements': return [elements];
    case 'audit': return [audit];
    default: return [];
  }
}
