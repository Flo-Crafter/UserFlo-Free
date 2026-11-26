export type SessionState = 'active' | 'ended_estimated' | 'logged_out_observed';

/** `limit`, `real_total` and `limited` describe an edition display limit (Lite); the stored data is not affected. */
export interface Paged<T> { items: T[]; total: number; page: number; per_page: number; limit?: number; real_total?: number; limited?: boolean }

export interface Kpis { visitors: number; new_visitors: number; sessions: number; logged_in: number; pageviews: number; avg_session: number; engaged: number; active_now: number; returning: number; single_page: number }
export interface PrevKpis { from: string; to?: string; mode?: string; daily?: DailyPoint[]; visitors: number; new_visitors: number; returning: number; sessions: number; pageviews: number; avg_session: number; engaged: number; single_page: number }
export interface DailyPoint { date: string; sessions: number; pageviews: number; visitors: number; engaged: number; single: number }
export interface PageRow { url: string; title: string; views: number; visitors: number; avg_engaged: number }
export interface PageBots { ai: number; search: number; other: number }
export interface Referrer { source: string; sessions: number }

export interface Session {
  id: number; visitor_id: number; started_at: string; last_activity: string; elapsed: number; engaged: number;
  pageviews: number; events: number; entry: string; exit: string; referrer: string; device: string; browser: string; os: string;
  screen: string; user_id: number | null; login_at: string | null; logout_at: string | null; state: SessionState;
  label?: string; logged_in?: boolean; location?: string;
}

export type CompareMode = 'previous' | 'week' | 'month' | 'year' | 'none';
export interface ChannelRow { key: string; sessions: number }
export interface DeviceRow { key: string; sessions: number }
export interface CountryRow { code: string; name: string; sessions: number }
export interface Overview {
  range: { from: string; to: string }; kpis: Kpis; previous: PrevKpis | null; compare: CompareMode; daily: DailyPoint[]; pages: PageRow[]; referrers: Referrer[];
  channels: ChannelRow[]; devices: DeviceRow[]; geo: boolean; countries: CountryRow[]; recent: Session[];
}

export interface Visitor {
  id: number; uid: string; label: string; logged_in: boolean; returning: boolean; email: string | null;
  first_seen: string; last_seen: string; sessions: number; pageviews: number; events: number; engaged: number;
  device: string; browser: string; os: string; location: string;
  /** Two-letter code of a country UserFlo knows ('' when unknown) and its name, for the flag. */
  country?: string | null; country_name?: string;
}
export interface VisitorDetail extends Visitor {
  profile_url?: string; profile_target?: 'same' | 'new';
  username?: string; identified_at?: string | null; roles: string[]; first_landing: string; first_referrer: string; ip: string; country: string | null;
  return: { sessions: number; active_days: number; avg_gap_days: number | null };
}

export interface CalendarData { month: string; days: Record<string, number>; months: { month: string; sessions: number }[] }

export type JourneyItem =
  | { kind: 'pageview'; id: number; at: string; url: string; title: string; referrer: string; engaged: number; duration: number; scroll: number }
  | { kind: 'event'; id: number; at: string; type: string; name: string; target: string; value: string; component: string; pv?: number };
export interface JourneyIdentity {
  state: 'anonymous' | 'known' | 'transition' | 'attributed_earlier' | 'signed_in_unlinked';
  label: string | null; transition: 'login' | 'link' | null; transition_at: string | null; linked_at: string | null;
  registered: boolean; already_linked: boolean; retroactive: boolean;
}
export interface JourneyNav { prev: number | null; next: number | null; position: number; count: number }
export interface Journey { session: Session; identity?: JourneyIdentity; nav?: JourneyNav; items: JourneyItem[]; total: number; page: number; per_page: number; limit?: number; real_total?: number; limited?: boolean }

export interface ExternalAggregate {
  id: string; label: string; fetched_at: string; dashboard_url: string; note: string;
  data: {
    period: { label: string };
    metrics: Record<string, number>;
    daily?: { date: string; sessions: number; pageviews: number }[];
    pages?: { path: string; views: number }[];
    sources?: { source: string; sessions: number }[];
    extra?: Record<string, number>;
  };
}

export interface LiveItem {
  key: string; kind: 'pageview' | 'event'; at: string; url: string; title: string; type: string; name: string; target: string;
  visitor_id: number; session_id: number; label: string; logged_in: boolean;
  /** Approximate place (country centroid, or city rounded to 0.1 degree); null when geolocation is off or unknown. */
  geo: LiveGeo | null;
}
export interface LiveGeo { country: string; city: string; name: string; key: string; lat: number; lon: number }
export interface LiveData { active: Session[]; items: LiveItem[]; cursor: { pv: number; ev: number }; last_hour: number; server: string }
export interface PageListRow { url: string; title: string; views: number; visitors: number; avg_engaged: number; health?: PageHealth; bots?: { ai: number; search: number; other: number }; bots_only?: boolean }
export interface PageVisit {
  id: number; at: string; visitor_id: number; session_id: number; label: string; logged_in: boolean; email: string | null;
  engaged: number; duration: number; scroll: number; referrer: string; device: string;
}
export interface PageVisits { url: string; total: number; items: PageVisit[] }

export interface InsightRow { key: string; name: string; component: string; count: number }
export interface Insights {
  pages: PageListRow[]; buttons: InsightRow[]; links: InsightRow[]; outbound: InsightRow[]; downloads: InsightRow[]; forms: InsightRow[]; custom: InsightRow[];
  entry: { key: string; count: number }[]; exit: { key: string; count: number }[]; devices: { key: string; count: number }[]; browsers: { key: string; count: number }[];
  journeys: { sessions: number; single_page: number; top: { steps: string[]; count: number }[] };
}
export type Tier = 'copper' | 'silver' | 'gold' | 'diamond' | 'olympian';
export interface TierInfo { tier: Tier; name: string; target: number; reached: boolean }
export interface Achievement {
  id: string; kind: 'family' | 'single'; name: string; category: string; desc: string; tier: Tier; next: Tier | ''; status: 'unlocked' | 'discovered' | 'locked' | 'secret';
  value: number; target: number; progress: number; tiers: TierInfo[];
}
export interface Achievements { summary: { total: number; unlocked: number; boxes: number; discovered: number; secret: number; highest_tier: Tier | '' }; items: Achievement[] }
export interface Who { visitor_id: number; session_id: number; label: string; logged_in: boolean }
export interface Milestone { tier: Tier; name: string; target: number; reached_at: string | null; who: Who | null; what: string }
export interface AchievementDetail {
  id: string; history: boolean; milestones: Milestone[]; series: { d: string; total: number }[]; total?: number; first_at: string | null; daily?: boolean;
  record: { at: string; value: number; who: Who | null } | null;
}

export interface GeoPlace { code?: string; name: string; continent?: string; country?: string; country_name?: string; visitors: number; sessions: number; lat: number; lon: number }
export interface GeoSummary { mode: 'off' | 'country' | 'city'; database: boolean; total: number; unknown: number; continents: GeoPlace[]; countries: GeoPlace[]; cities: GeoPlace[] }
export interface GeoDetails {
  level: string; key: string; name: string; visitors: number; sessions: number; pages: { url: string; views: number }[]; users: { visitor_id: number; label: string; sessions: number }[];
  hours: number[]; weekdays: number[]; top_hours: number[]; top_days: { date: string; sessions: number }[]; top_weeks: { week: string; starts: string; sessions: number }[];
}
export interface WorldData { w: number; h: number; latMax: number; latMin: number; countries: Record<string, string> }

export interface FocusGroup { id: string; label: string; views: number; sessions: number; visitors: number; avg_engaged: number; top: { url: string; views: number }[] }
export interface FocusStep { id: string; label: string; sessions: number; pct_prev: number | null; pct_first: number | null }
export interface Focus {
  type: string; label: string; groups: FocusGroup[]; funnel: FocusStep[]; highlights: { id: string; label: string; items: { label: string; count: number }[] }[];
}

export interface VisitorFacets {
  event_types: string[];
  event_names: { type: string; name: string }[];
  devices: string[];
  countries: { code: string; name: string }[];
}

export interface Highlights {
  comparable: boolean;
  movers: { up: Mover[]; down: Mover[] };
  engaged: { url: string; title: string; views: number; avg_engaged: number; avg_scroll: number }[];
  exits: { url: string; exits: number; views: number; rate: number | null }[];
}
export interface Mover { url: string; views: number; previous: number; change: number }

export interface WpContent {
  content: {
    types: { type: string; label: string; pages: number; views: number; avg_engaged: number; top: { url: string; title: string; views: number }[] }[];
    terms: { taxonomy: string; term: string; pages: number; views: number }[];
    pageviews: number; examined: number; matched: number; pages_looked: number;
  };
  available: { shop: boolean; builders: string[] };
}
export interface WpForms { forms: { name: string; url: string; builder: string; builder_label: string; submits: number; sent: number | null; visitors: number; views: number; per_100: number | null }[] }
export interface WpShop {
  shop: null | {
    active: boolean; unmatched: number;
    products: { id: number; title: string; url: string; views: number; add_to_cart: number; per_100: number | null }[];
    funnel: { id: string; label: string; sessions: number; pct_prev: number | null; pct_first: number | null }[];
  };
}
export interface WpBuilders { builders: { builder: string; label: string; interactions: number; modules: { module: string; clicks: number; forms: number; downloads: number; outbound: number; media: number; total: number; visitors: number }[] }[] }

/* Traffic calendar */
export type TrafficMetric = 'pageviews' | 'sessions' | 'visitors' | 'events';
export type TrafficState = 'data' | 'nodata' | 'future';
/** Per-day values for the requested metric only; the others are fetched when selected. */
export type TrafficDay = { state: TrafficState; partial: boolean } & Partial<Record<TrafficMetric, number | null>>;
export interface TrafficCalendarData { metrics: TrafficMetric[]; from: string; to: string; timezone: string; today: string; days: Record<string, TrafficDay> }
export interface TrafficHour { start: number; end: number; label: string; end_label: string; state: TrafficState; pageviews: number | null; sessions: number | null; visitors: number | null; events: number | null }
export interface TrafficBusy { index: number; start: number; label: string; value: number }
export interface TrafficDayDetail {
  date: string; timezone: string; state: TrafficState; partial: boolean;
  kpis: { pageviews: number; sessions: number; visitors: number; events: number };
  hours: TrafficHour[];
  busiest: Record<TrafficMetric, TrafficBusy[]>;
  pages: { url: string; title: string; views: number; visitors: number }[];
  sources: { source: string; sessions: number }[];
  devices: { device: string; sessions: number }[];
}
export interface TrafficHourDetail {
  start: number; end: number; label: string; timezone: string;
  kpis: { pageviews: number; sessions: number; visitors: number; events: number };
  pages: { url: string; title: string; views: number; visitors: number }[];
  events: { type: string; name: string; count: number }[];
}

export interface JyPageRef { page: string }
export interface JyEntrance { page: string; count: number; share: number; next: { page: string | null; count: number; share: number }[] }
export interface JyExit { page: string; count: number; share: number; views: number; rate: number | null; lead: { pages: string[]; count: number }[] }
export interface JyPath { steps: string[]; count: number; share: number; complete: boolean }
export interface JyTransition { step: number; from: string; to: string; count: number; of_source: number }
export interface Journeys {
  state: 'data' | 'nodata'; partial: boolean;
  period: { from: string; to: string };
  summary: { sessions: number; sessions_in_period: number; analysed: number; sampled: boolean; excluded_retention: number; selected: number; single_page: number };
  entrances: { total: number; distinct?: number; items: JyEntrance[] };
  exits: { total: number; distinct?: number; items: JyExit[] };
  flow: { steps?: number; columns: { id: string; col: number; page: string; kind: 'page' | 'other' | 'end' | 'more'; count: number; selectable: boolean; pages?: number }[][]; links: { source: string; target: string; step: number; from: string; to: string; count: number }[]; sessions?: number };
  transitions: JyTransition[];
  paths: { items: JyPath[]; distinct?: number; total?: number } | never[];
  titles?: Record<string, string>;
  facets?: { devices: { key: string; count: number }[]; countries: { key: string; name: string; count: number }[] };
  previous: null | { available: boolean; reason: string; from: string; to: string; sessions?: number; entrance_points?: Record<string, number>; exit_points?: Record<string, number> };
}

export interface CampaignRow {
  source?: string; medium?: string; campaign?: string; content?: string; term?: string;
  sessions: number; visitors: number; pages: number; single_rate: number; engaged_secs: number; prev?: number; converted?: number;
}
export interface Campaigns { group: string; rows: CampaignRow[]; total: number; tagged: number; prev_total?: number; backfilling: boolean; has_goals?: boolean }

export interface Goal {
  id: number; name: string; type: 'page' | 'event' | 'funnel'; match_type: string; pattern: string; event_type: string; value: number; status: 'active' | 'paused'; steps?: number[];
  min_count: number; measure: 'session' | 'target'; within_min: number; starts_on: string; ends_on: string;
  /** ISO weekdays, 1 = Monday .. 7 = Sunday; empty = every day. */
  days: number[]; time_from: string; time_to: string; compare: '' | 'more' | 'atleast' | 'less' | 'atmost'; threshold: number; count_what: 'hits' | 'sessions' | 'visitors';
}
export interface TargetWindow { date: string; from: string; to: string; count: number | null; met: boolean | null; state: 'met' | 'missed' | 'open' | 'nodata' }
export interface TargetResult {
  id: number; name: string; status: string; compare: Goal['compare']; threshold: number; count_what: Goal['count_what']; days: number[]; time_from: string; time_to: string; starts_on: string; ends_on: string;
  windows: TargetWindow[]; measured: number; met: number; open: number; nodata: number; average: number | null; truncated: boolean;
}
export interface TargetList { targets: TargetResult[] }
export interface GoalList { goals: Goal[]; limit: number; active: number; event_types: string[]; advanced: boolean }
export interface GoalStat { id: number; name: string; type: string; status: string; completions: number; rate: number | null; value: number; prev?: number }
export interface GoalStats { sessions: number; goals: GoalStat[]; prev_sessions?: number; converting: number }
export interface GoalBreakdown { goal: string; by: string; rows: { key: string; sessions: number; completions: number; rate: number | null }[] }
export interface FunnelResult { name: string; truncated: boolean; steps: { goal_id: number; name: string; sessions: number; pct_prev: number | null; pct_first: number | null }[] }

export interface CommerceCurrency { currency: string; orders: number; revenue: number; net: number; aov: number; refunded: number; discount: number; tax: number; shipping: number; prev?: { orders: number; revenue: number } }
export interface CommerceSummary { currencies: CommerceCurrency[]; excluded: Record<string, number>; sessions: number; orders: number; counted: string[]; advanced: boolean; cart_sessions?: number }
export interface CommerceAttribution { by: string; rows: { currency: string; key: string; orders: number; revenue: number }[]; linked: number; unlinked: number }

/** Exclusions: rules that stop tracking, leave data out of statistics, or suppress audit findings. */
export interface ExclusionRule {
  id: number; mode: 'stop' | 'stats' | 'audit'; target: 'page' | 'element' | 'finding'; field: string; match_type: string; pattern: string;
  event_type: string; page_scope: string; finding_type: string; label: string; note: string; status: 'active' | 'paused';
  created_by: number; created_at: string; updated_at: string;
}
export interface ExclusionBuiltin { kind: 'path' | 'role' | 'selector'; mode: 'stop'; label: string; where: string }
export interface ExclusionList {
  rules: ExclusionRule[]; builtin: ExclusionBuiltin[]; limit: number; active: number; audit_available: boolean; event_types: string[]; settings_url: string;
}
export interface ExclusionImpact { valid: boolean; known: boolean; pageviews: number; events: number; findings?: number; samples: string[]; days: number }

/** Website audit (page and element inventory, findings) and Elements. */
export type FindingClass = 'verified' | 'warning' | 'recommendation';
export interface Wcag { sc: string; level: string }
/** How sure an accessibility check is: a specific issue, a potential issue, or something only a person can judge. */
export type Detect = 'issue' | 'potential' | 'manual';
export interface AuditFinding {
  id: number; code: string; class: FindingClass; severity: number; title: string; why: string; fix: string; evidence: string; page_id: number; url: string;
  element: { id: number; label: string; identifier: string; kind: string } | null; first_seen: string; last_seen?: string; excluded: boolean;
  /** Accessibility findings carry the WCAG 2.2 success criteria they relate to, how sure the check is, and when it can be wrong. */
  wcag?: Wcag[]; detect?: Detect | ''; limits?: string;
}
export interface A11yOverviewRow { code: string; title: string; wcag: Wcag[]; detect: Detect; count: number }
export interface ManualTopic { sc: string; level: string; title: string; what: string }
export type A11yStatus = Detect | 'pass' | 'not_tested' | 'none' | 'excluded';
export interface A11yCheckRow { code: string; title: string; wcag: Wcag[]; detect: Detect; status: A11yStatus; tested: number; found: number }
export interface PageHealth {
  state: string; level: number; red: number; amber: number; excluded: number;
  findings: { code: string; title: string; class: FindingClass; excluded: boolean; evidence: string }[];
}
export interface AuditSummary {
  pages: { total: number; ok: number; queued: number; error: number; skipped: number };
  health: { red: number; amber: number; green: number; unscanned: number };
  findings: { active: number; excluded: number; by_code: Record<string, number> };
  last_scan: string; cap: number; daily_cap: number; full: boolean; due: number;
  seo: { yoast: boolean; seopress: boolean; both: boolean; names: string[] };
  a11y?: { pages_issue: number; pages_potential: number; overview: A11yOverviewRow[]; manual: ManualTopic[] };
}
export interface AuditPageRow {
  url: string; title: string; state: string; level: number; bucket: 'red' | 'amber' | 'green' | 'unscanned'; red: number; amber: number; excluded: number; views: number;
  scanned_at: string | null; elements: number; error: string; http_status: number; findings: PageHealth['findings'];
}
export interface AuditPages { items: AuditPageRow[]; total: number; page: number; per_page: number; full: boolean }
export interface PageReportData {
  page: { url: string; title: string; description: string; canonical: string; robots: string; state: string; http_status: number; scanned_at: string | null; post_id: number; edit_url: string; elements: number };
  health: number; findings: { active: AuditFinding[]; excluded: AuditFinding[] };
  analytics: { days: number; views: number; visitors: number; avg_engaged: number; avg_scroll: number; entrances: number; single_page_entrances: number; exits: number; exit_share: number | null };
  around: { sampled: number; next: { url: string; count: number }[]; prev: { url: string; count: number }[] };
  elements: { id: number; kind: string; label: string; identifier: string; instances: number }[];
  seo: AuditSummary['seo'];
  a11y?: { checklist: A11yCheckRow[]; manual: ManualTopic[] };
}
export interface InsightItem {
  rule: 'busy_with_finding' | 'landing_leave' | 'a11y_priority'; url: string; title: string; priority: number;
  numbers: { views?: number; entrances?: number; single_page_share?: number; site_median?: number; days: number; key_page?: number }; findings: { code: string; title: string }[];
}
export interface InsightsData { items: InsightItem[]; days: number; note: string }
export interface FindingsOverview {
  codes: { code: string; title: string; class: FindingClass; severity: number; active: number; excluded: number; pages: number }[];
  items: AuditFinding[]; total: number; titles: Record<string, string>;
}
export interface ElementRow {
  id: number; inventoried: boolean; kind: string; label: string; identifier: string; value: string; href: string; pages: number; instances: number;
  interactions: number; visitors: number; logged_in: number; anonymous: number;
  /** Tracker identity used to attach a name and note, the administrator's own name for it and the note. */
  key: string; alias: string; note: string;
}
export interface ElementsList { items: ElementRow[]; total: number; page: number; per_page: number; kinds: Record<string, number>; advanced: boolean; audit: { pages: number } }
export interface ElementDetailData {
  element: { id: number; kind: string; label: string; identifier: string; value: string; href: string; pages: number; instances: number; first_seen: string; last_seen: string; key: string; alias: string; note: string };
  interactions: number; visitors: number; sessions: number; logged_in: number; anonymous: number; logged_in_visitors: number;
  trend: Record<string, number>; pages_used: { url: string; interactions: number }[]; locations: { url: string; title: string; instances: number }[];
  advanced: boolean; around?: { sampled: number; next: { url: string; count: number }[]; prev: { url: string; count: number }[] };
  goals?: { state: string; sessions: number; goals: { goal: string; with: number; without: number; completions_with: number }[] }; findings?: AuditFinding[];
}

/** The 3D globe (Pro) is drawn by a component UserFlo Pro registers; these are the shapes the Map screen hands to it. */
export interface GlobePlace { key: string; title: string; lat: number; lon: number; visitors: number; sessions: number }
export interface GlobeHandle {
  focus(lat: number, lon: number): void;
  flyTo(lat: number | null, lon: number | null, k: number): void;
  pulse(lat: number, lon: number, key?: string): void;
  highlight(key: string | null): void;
  setSelected(key: string | null): void;
  setHold(hold: boolean): void;
  reset(): void;
}
