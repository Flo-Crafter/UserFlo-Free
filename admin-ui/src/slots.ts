import type * as React from 'react';

/**
 * Extension points filled by FloLens Pro's admin bundle (pro.js), which WordPress loads before this app when Pro is active and
 * entitled. In Lite every slot is empty, the matching views render nothing, and none of the Pro UI code is part of this bundle.
 */
export interface Slots {
  HighlightsPanel?: (p: { params: Record<string, string | number | undefined> }) => React.ReactElement | null;
  WordPressReport?: (p: { params: Record<string, string | number | undefined> }) => React.ReactElement | null;
  ContactClicks?: (p: { params: Record<string, string | number | undefined> }) => React.ReactElement | null;
  ReportsView?: () => React.ReactElement | null;
  TrafficCalendarFull?: () => React.ReactElement | null;
  JourneysFull?: () => React.ReactElement | null;
  /** Goals (Pro): the complete Goals screen with funnels, targets, a value per goal, time frames and more ways to split. */
  GoalsFull?: () => React.ReactElement | null;
  /** Page Intelligence (Pro): the prioritised list and findings by kind, and the report for one page. */
  PageIntelligence?: (p: { days: number; onOpen: (url: string) => void }) => React.ReactElement | null;
  PageReport?: (p: { url: string; days: number }) => React.ReactElement | null;
  /** Elements (Pro): journeys around an element, goal correlation and findings. */
  ElementAdvanced?: (p: { data: import('./types').ElementDetailData; days: number; audience: string; onChange: () => void }) => React.ReactElement | null;
  /** Statistics Builder (Pro): the dashboard switcher, viewer, manager and builder. `classic` is the standard Statistics page, shown when no dashboard is chosen. */
  StatsDashboards?: (p: { classic: React.ReactElement }) => React.ReactElement | null;
  /** Audit (Pro): the table of every scanned page with its counts and findings. */
  AuditView?: () => React.ReactElement | null;
  /** Copy for AI (Pro): the button next to Guide that opens a window to copy a screen's data as text for any AI chat. */
  CopyForAi?: (p: { view: string }) => React.ReactElement | null;
  /** Visitors (Pro): advanced filters. Rendered as the toggle button, the panel and the chips (`part`). */
  VisitorFilters?: (p: VisitorFilterProps) => React.ReactElement | null;
  /** Visitor profile (Pro): the visit calendar and the sessions of a chosen day. */
  VisitorCalendar?: (p: VisitorCalendarProps) => React.ReactElement | null;
  /** WooCommerce (Pro): sales by campaign, source and medium. */
  CommerceAttribution?: (p: { params: Record<string, string | number | undefined> }) => React.ReactElement | null;
  /** Overview (Pro): custom start and end dates. */
  CustomRange?: (p: { active: boolean; onApply: (from: string, to: string) => void }) => React.ReactElement | null;
  JourneyVisual?: (p: { sessionId: number; onSession: (id: number) => void }) => React.ReactElement | null;
  /* eslint-disable @typescript-eslint/no-explicit-any */
  GlobePanel?: any;
  LiveFeedPanel?: any;
}

export interface VisitorFilterProps {
  part: 'button' | 'panel' | 'chips';
  open: boolean; setOpen: (o: boolean) => void;
  values: Record<string, string>;
  onChange: (patch: Record<string, string>) => void;
  sort: { by: string; dir: 'asc' | 'desc' };
  onSort: (by: string, dir: 'asc' | 'desc') => void;
}

export interface VisitorCalendarProps {
  id: number; month: string; day: string; session?: number;
  onMonth: (m: string) => void; onDay: (d: string) => void;
  onOpen: (s: import('./types').Session) => void;
}

declare global { interface Window { userfloSlots?: Slots } }

export const slots = (): Slots => window.userfloSlots || {};
