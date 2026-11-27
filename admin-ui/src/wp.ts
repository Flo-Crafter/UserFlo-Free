import type * as React from 'react';

declare global {
  interface Window {
    wp: { element: typeof React; apiFetch: (o: Record<string, unknown>) => Promise<unknown>; i18n: { __: (s: string, d?: string) => string; _n: (a: string, b: string, n: number, d?: string) => string; sprintf: (f: string, ...a: unknown[]) => string } };
    userfloApp: AppConfig;
  }
}

export interface AppConfig {
  timezone: string;
  weekStart: number;
  locale: string;
  utcOffset: number;
  urls: { overview: string; visitors: string; tools: string; sharing?: string; settings: string; heatmaps: string; pages?: string; exclusions?: string; elements?: string };
  /** True for people who may create exclusion rules (administrators). The server checks again. */
  canExclude?: boolean;
  /** Which of the top-bar buttons the owner has left on (Settings → Branding and appearance). */
  buttons?: { guide: boolean; ai: boolean; export: boolean };
  /** True when a website audit exists, so the audit mode is offered. */
  exclusionsAudit?: boolean;
  adminPost: string;
  pdfNonce: string;
  brandLogo?: string;
  geoUrl?: string;
  geoMode?: string;
  siteType?: string;
  reportLang?: string;
  languages?: Record<string, string>;
  /** 3D globe: script address (absent when the feature is switched off with the userflo_feature_globe filter) and whether live data exists. */
  features?: { globe: boolean; live: boolean };
  /** Pro sections the site owner can switch off (Settings → Components). Absent means on. */
  modules?: Record<string, boolean>;
  /** Edition (Lite / Pro) as decided by the server. Controls are hidden from it; the server enforces the same rules. */
  edition?: { pro: boolean; features: Record<string, boolean>; limits: Record<string, number>; upgradeUrl: string };
  globeUrl?: string;
  /** Detailed land map for the globe's vector look (a gzip-compressed SVG shipped with Pro) and the site's chosen look. */
  landUrl?: string;
  globeStyle?: 'hex' | 'vector';
  /** The signed-in user's own table layouts (column order and hidden columns), per table. */
  tablePrefs?: Record<string, { order: string[]; hidden: string[] }>;
  /** Owner's choice (Settings -> Branding and appearance): animate charts, tables and the map. Absent means on. */
  animations?: boolean;
  /** The flag sprite (one SVG file shipped with FloLens). */
  flagsUrl?: string;
}

const el = window.wp.element;
export const h = el.createElement;
export const Fragment = el.Fragment;
export const { useState, useEffect, useLayoutEffect, useRef, useCallback, useMemo } = el;
export const createPortal = (el as unknown as { createPortal: (n: React.ReactNode, c: Element) => React.ReactElement }).createPortal;
export const render = (el as unknown as { render: (n: React.ReactNode, c: Element) => void }).render;
export const createRoot = (el as unknown as { createRoot?: (c: Element) => { render: (n: React.ReactNode) => void } }).createRoot;

const i18n = window.wp.i18n;
export const __ = (s: string): string => i18n.__(s, 'flolens');
export const _n = (a: string, b: string, n: number): string => i18n._n(a, b, n, 'flolens');
export const sprintf = (f: string, ...a: unknown[]): string => i18n.sprintf(f, ...a);
export const cfg = (): AppConfig => window.userfloApp;

/** Address of another FloLens screen (slug without the userflo- prefix). */
export const screen = (slug: string): string => 'admin.php?page=userflo' + (slug ? '-' + slug : '');
/** Whether a switchable section is on. Defaults to on when the server did not say. */
export const modOn = (key: string): boolean => !cfg().modules || cfg().modules![key] !== false;
export const can = (feature: string): boolean => !!(cfg().edition && cfg().edition!.features[feature]);
/** Edition limit; 0 means no limit. */
export const limit = (name: string): number => (cfg().edition && cfg().edition!.limits[name]) || 0;
export const upgradeUrl = (): string => (cfg().edition && cfg().edition!.upgradeUrl) || '';
/** Range presets the edition allows (Lite: up to its day limit). */
export const rangePresets = (all: [number, string][]): [number, string][] => (can('range_extended') || !limit('overview_days') ? all : all.filter(([d]) => d <= limit('overview_days')));
