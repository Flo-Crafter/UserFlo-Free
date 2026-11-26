import { h, __, can, useState, useEffect, useRef, useCallback, useMemo, rangePresets } from '../wp';
import { useApi } from '../hooks';
import type { GeoPlace, GeoSummary, LiveItem } from '../types';
import { num } from '../format';
import { animationsOn } from '../motion';
import { doubleClickFactor, wheelFactor } from '../zoom';
import { Async, Card } from '../components/ui';
import { MapModal } from '../components/MapModal';
import { proj, useWorld } from '../components/geo';
import type { GlobeHandle, GlobePlace } from '../types';
import { slots } from '../slots';
import { useLive } from '../components/useLive';

type Level = 'auto' | 'continent' | 'country' | 'city';
type Mode = '2d' | '3d';

const PREF_KEY = 'userflo_map_prefs_v1';
interface Prefs { feed: boolean; live: boolean; rotate: boolean; style: '' | 'hex' | 'vector' }
function loadPrefs(): Prefs {
  try { const r = window.localStorage.getItem(PREF_KEY); if (r) { const p = JSON.parse(r) as Partial<Prefs>; return { feed: !!p.feed, live: p.live !== false, rotate: p.rotate !== false, style: p.style === 'hex' || p.style === 'vector' ? p.style : '' }; } } catch (e) { /* storage unavailable */ }
  return { feed: false, live: true, rotate: true, style: '' };
}

export function MapView() {
  const world = useWorld();
  const [days, setDays] = useState(30);
  const [level, setLevel] = useState<Level>('auto');
  const [view, setView] = useState({ k: 1, x: 500, y: 197 });
  const [open, setOpen] = useState<{ level: string; key: string; title: string } | null>(null);
  const q = useApi<GeoSummary>('geo/summary', { days });
  const GlobePanel = slots().GlobePanel;
  const LiveFeedPanel = slots().LiveFeedPanel;
  // The 3D view needs the server's permission (Pro) and the Pro bundle's panels.
  const features = { ...(window.userfloApp.features || { globe: false, live: false }), globe: !!(window.userfloApp.features && window.userfloApp.features.globe && GlobePanel && LiveFeedPanel) };
  const [mode, setMode] = useState<Mode>('2d');
  const [gk, setGk] = useState(1);
  const [prefs, setPrefs] = useState(loadPrefs);
  const [failed, setFailed] = useState(false);
  const [styleFailed, setStyleFailed] = useState(false);
  const [filter, setFilter] = useState<{ level: string; key: string; title: string } | null>(null);
  const [sel, setSel] = useState<{ key: string; title: string; level: string; visitors: number; sessions: number } | null>(null);
  const prevK = useRef(1);
  const globe = useRef<GlobeHandle | null>(null);
  const savePrefs = (p: Prefs) => { setPrefs(p); try { window.localStorage.setItem(PREF_KEY, JSON.stringify(p)); } catch (e) { /* ignore */ } };
  // The look of the globe: this browser's choice, else the site's (Settings), else hexagons. Vector needs the land map the server offers.
  const canVector = !!window.userfloApp.landUrl && !styleFailed;
  const globeStyle: 'hex' | 'vector' = canVector && (prefs.style || window.userfloApp.globeStyle || 'hex') === 'vector' ? 'vector' : 'hex';
  const liveOn = mode === '3d' && features.live && prefs.live;
  // Pulses for new activity only: a few per poll, one per place.
  const onNew = useCallback((items: LiveItem[]) => {
    if (!globe.current) { return; }
    const seen: Record<string, boolean> = {};
    let n = 0;
    items.forEach((i) => {
      if (!i.geo || seen[i.geo.key] || n >= 6) { return; }
      seen[i.geo.key] = true; n++;
      globe.current!.pulse(i.geo.lat, i.geo.lon, i.geo.key);
    });
  }, []);
  const live = useLive(liveOn, onNew);
  const toGlobe = (d: GeoSummary, lv: string): GlobePlace[] => (lv === 'continent' ? d.continents : lv === 'country' ? d.countries : d.cities).map((p) => ({
    key: lv === 'city' ? `${p.country}|${p.name}` : (p.code as string), title: lv === 'city' ? `${p.name}, ${p.country_name}` : p.name, lat: p.lat, lon: p.lon, visitors: p.visitors, sessions: p.sessions,
  }));

  const svg = useRef<SVGSVGElement | null>(null);
  // The map's element comes and goes (it is drawn once both the world and the visitor data are there), so the zoom listeners follow the element itself.
  const [svgEl, setSvgEl] = useState<SVGSVGElement | null>(null);
  const svgRef = useCallback((el: SVGSVGElement | null) => { svg.current = el; setSvgEl(el); }, []);
  const drag = useRef<{ x: number; y: number } | null>(null);
  const [hotCc, setHotCc] = useState<string | null>(null); // Country under the pointer, drawn again on top and slightly larger.
  const growMap = animationsOn();
  // Lite has country totals only: stop zooming before the city level (which would show no numbers).
  const maxK = can('map_city') ? 40 : 5;
  const W = world ? world.w : 1000, H = world ? world.h : 394;
  const clamp = (v: { k: number; x: number; y: number }) => {
    const k = Math.max(1, Math.min(maxK, v.k)), hw = W / (2 * k), hh = H / (2 * k);
    return { k, x: Math.max(hw, Math.min(W - hw, v.x)), y: Math.max(hh, Math.min(H - hh, v.y)) };
  };
  const zoomAt = (f: number, cx?: number, cy?: number) => setView((v) => {
    const k = Math.max(1, Math.min(maxK, v.k * f));
    const px = cx === undefined ? v.x : v.x + (cx - v.x) * (1 - v.k / k), py = cy === undefined ? v.y : v.y + (cy - v.y) * (1 - v.k / k);
    return clamp({ k, x: px, y: py });
  });

  useEffect(() => {
    const el = svgEl;
    if (!el) { return undefined; }
    // Zoom by `f` keeping the map point under the pointer where it is.
    const zoomOn = (clientX: number, clientY: number, f: number) => {
      const r = el.getBoundingClientRect();
      setView((v) => {
        const vw = W / v.k, vh = H / v.k, mx = v.x - vw / 2 + ((clientX - r.left) / r.width) * vw, my = v.y - vh / 2 + ((clientY - r.top) / r.height) * vh;
        const k = Math.max(1, Math.min(maxK, v.k * f));
        return clamp({ k, x: mx + (v.x - mx) * (v.k / k), y: my + (v.y - my) * (v.k / k) });
      });
    };
    // A plain wheel steps the zoom; Ctrl + wheel (and a touch-pad pinch) zooms smoothly by how far it moved. Both stop the page from scrolling or zooming.
    const wheel = (e: WheelEvent) => { e.preventDefault(); zoomOn(e.clientX, e.clientY, wheelFactor(e.deltaY, e.ctrlKey, 1.25)); };
    // Double-click zooms in on that spot (Shift: out). A double-click on a number is two clicks on it, not a zoom.
    const dbl = (e: MouseEvent) => {
      // The map holds the pointer while it is pressed, so ask what is at that spot instead of what the event says.
      if (document.elementsFromPoint(e.clientX, e.clientY).some((n) => !!n.closest && !!n.closest('.uf-map-pt'))) { return; }
      e.preventDefault(); zoomOn(e.clientX, e.clientY, doubleClickFactor(e.shiftKey)); };
    el.addEventListener('dblclick', dbl);
    el.addEventListener('wheel', wheel, { passive: false });
    return () => { el.removeEventListener('wheel', wheel); el.removeEventListener('dblclick', dbl); };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [svgEl, world, maxK]);

  const zoomK = mode === '3d' ? gk : view.k;
  const effective: Exclude<Level, 'auto'> = level !== 'auto' ? level : mode === '3d' ? (gk < 1.7 ? 'continent' : gk < 3.5 ? 'country' : 'city') : view.k < 2.5 ? 'continent' : view.k < 6 ? 'country' : 'city';
  void zoomK;
  const presets = rangePresets([[7, __('7 days')], [30, __('30 days')], [90, __('90 days')], [366, __('1 year')]]);

  const places = (d: GeoSummary): GeoPlace[] => (effective === 'continent' ? d.continents : effective === 'country' ? d.countries : d.cities);
  const keyOf = (p: GeoPlace): string => (effective === 'continent' ? (p.code as string) : effective === 'country' ? (p.code as string) : `${p.country}|${p.name}`);
  const titleOf = (p: GeoPlace): string => (effective === 'city' ? `${p.name}, ${p.country_name}` : p.name);

  const gPlaces = useMemo(() => (q.data ? toGlobe(q.data, effective) : []), [q.data, effective]);
  const gByCountry = useMemo(() => { const m: Record<string, number> = {}; if (q.data) { q.data.countries.forEach((c) => { m[c.code as string] = c.visitors; }); } return m; }, [q.data]);
  /** Click on a number: turn to it and zoom in a little; the feed shows only that place. */
  const select = useCallback((key: string) => {
    const pl = gPlaces.find((x) => x.key === key);
    if (!pl || !globe.current) { return; }
    if (!sel) { prevK.current = gk; }
    setSel({ key, title: pl.title, level: effective, visitors: pl.visitors, sessions: pl.sessions });
    if (effective !== 'continent') { setFilter({ level: effective, key, title: pl.title }); } else { setFilter(null); }
    globe.current.setSelected(key);
    globe.current.setHold(true);
    // A little closer, but not so close that automatic detail switches to the next level and replaces the number just clicked.
    const target = effective === 'continent' ? Math.max(gk, 1.5) : effective === 'country' ? Math.min(3.4, Math.max(gk * 1.3, 2.2)) : Math.min(6, Math.max(gk * 1.25, 2.8));
    globe.current.flyTo(pl.lat, pl.lon, target);
  }, [gPlaces, effective, gk, sel]);
  /** Click anywhere else (or Escape): let go, zoom back out and carry on turning. */
  const deselect = useCallback(() => {
    if (!sel || !globe.current) { return; }
    setSel(null); setFilter(null);
    globe.current.setSelected(null);
    globe.current.setHold(false);
    globe.current.flyTo(null, null, prevK.current);
  }, [sel]);
  useEffect(() => { if (sel) { setSel(null); setFilter(null); if (globe.current) { globe.current.setSelected(null); globe.current.setHold(false); } } }, [days, level, mode]);
  const feedMatch = (i: LiveItem): boolean => !filter || (!!i.geo && (filter.level === 'city' ? i.geo.key === filter.key : i.geo.country === filter.key));
  const pick = (i: LiveItem) => {
    if (!i.geo || !globe.current) { return; }
    globe.current.focus(i.geo.lat, i.geo.lon);
    globe.current.pulse(i.geo.lat, i.geo.lon, i.geo.key);
    globe.current.highlight(effective === 'city' ? i.geo.key : effective === 'country' ? i.geo.country : null);
  };
  const globeOn = mode === '3d' && features.globe;
  const showFeed = globeOn && features.live && prefs.feed;

  const vw = W / view.k, vh = H / view.k;
  const vb = `${view.x - vw / 2} ${view.y - vh / 2} ${vw} ${vh}`;

  return (
    <div>
      <h1 className="uf-title">{__('Map')}</h1>
      <p className="uf-sub">{__('Where visitors come from. Zoom in for countries and cities; click a number for details. Visitors who used several locations are counted in each.')}</p>
      <div className="uf-filter" role="group" aria-label={__('Options')}>
        {presets.map(([d, l]) => <button key={d} className={'button' + (days === d ? ' button-primary' : '')} onClick={() => setDays(d)}>{l}</button>)}
        <label>{__('Detail')} <select value={level} onChange={(e) => setLevel((e.target as HTMLSelectElement).value as Level)}>
          <option value="auto">{__('Automatic (by zoom)')}</option><option value="continent">{__('Continents')}</option><option value="country">{__('Countries')}</option>{can('map_city') ? <option value="city">{__('Cities')}</option> : null}
        </select></label>
        <button className="button" disabled={mode !== '3d' && view.k >= maxK - 0.001} onClick={() => (mode === '3d' ? setGk((g) => Math.min(8, g * 1.5)) : zoomAt(1.5))} aria-label={__('Zoom in')}>+</button>
        <button className="button" onClick={() => (mode === '3d' ? setGk((g) => Math.max(1, g / 1.5)) : zoomAt(1 / 1.5))} aria-label={__('Zoom out')}>−</button>
        <button className="button" onClick={() => { if (mode === '3d') { setGk(1); if (globe.current) { globe.current.reset(); } } else { setView({ k: 1, x: W / 2, y: H / 2 }); } }}>{__('Reset')}</button>
        {features.globe ? (
          <span className="uf-seg" role="group" aria-label={__('Map view')}>
            <button type="button" className={'button' + (mode === '2d' ? ' button-primary' : '')} aria-pressed={mode === '2d'} onClick={() => setMode('2d')}>{__('2D Map')}</button>
            <button type="button" className={'button' + (mode === '3d' ? ' button-primary' : '')} aria-pressed={mode === '3d'} onClick={() => { setFailed(false); setMode('3d'); }}>{__('3D Globe')}</button>
          </span>
        ) : null}
      </div>
      {mode === '3d' ? (
        <div className="uf-filter" role="group" aria-label={__('Globe options')}>
          <button type="button" className={'button' + (prefs.rotate ? ' button-primary' : '')} aria-pressed={prefs.rotate} onClick={() => savePrefs({ ...prefs, rotate: !prefs.rotate })}>{prefs.rotate ? __('Auto-rotate: on') : __('Auto-rotate: off')}</button>
          {window.userfloApp.landUrl ? (
            <span className="uf-seg" role="group" aria-label={__('Globe style')}>
              <button type="button" className={'button' + (globeStyle === 'hex' ? ' button-primary' : '')} aria-pressed={globeStyle === 'hex'} onClick={() => { setStyleFailed(false); savePrefs({ ...prefs, style: 'hex' }); }}>{__('Hexagons')}</button>
              <button type="button" className={'button' + (globeStyle === 'vector' ? ' button-primary' : '')} aria-pressed={globeStyle === 'vector'} onClick={() => { setStyleFailed(false); savePrefs({ ...prefs, style: 'vector' }); }}>{__('Vector map')}</button>
            </span>
          ) : null}
          {styleFailed ? <small className="uf-sub" role="status">{__('The vector map could not be loaded in this browser; the hexagon style is shown.')}</small> : null}
          {features.live ? (<span className="uf-gl-livegrp">
          <button type="button" className={'button' + (prefs.live ? ' button-primary' : '')} aria-pressed={prefs.live} onClick={() => savePrefs({ ...prefs, live: !prefs.live })}>{prefs.live ? __('Live activity: on') : __('Live activity: paused')}</button>
          <button type="button" className="button" aria-pressed={prefs.feed} aria-controls="uf-gl-feed" onClick={() => savePrefs({ ...prefs, feed: !prefs.feed })}>{prefs.feed ? __('Hide live feed') : __('Show live feed')}</button>
          </span>) : null}
          <small className="uf-sub">{__('Soft rings on the globe mark new activity; the numbered circles are visitor totals for the selected period.')} {__('The globe turns slowly and stops while the pointer is over it.')}</small>
        </div>
      ) : null}
      <Async q={q}>
        {(d) => {
          const list = places(d), max = Math.max(1, ...list.map((p) => p.visitors));
          const byCountry: Record<string, number> = {};
          d.countries.forEach((c) => { byCountry[c.code as string] = c.visitors; });
          const maxC = Math.max(1, ...d.countries.map((c) => c.visitors));
          return (
            <div>
              {d.mode === 'off' || !d.database ? (
                <div className="notice notice-warning inline"><p>{d.mode === 'off' ? __('Geolocation is off.') : __('No location database is installed yet.')} <a href={window.userfloApp.urls.settings}>{__('Settings')}</a></p></div>
              ) : null}
              <Card>
                {globeOn ? (
                  <div className={'uf-gl-layout' + (showFeed ? ' has-feed' : '')}>
                    <div className="uf-map-wrap uf-gl-box">
                      {failed ? (
                        <div className="uf-gl-fail" role="alert">
                          <p>{__('The 3D globe could not be started in this browser. The 2D map still works.')}</p>
                          <button type="button" className="button button-primary" onClick={() => setMode('2d')}>{__('Back to the 2D map')}</button>
                        </div>
                      ) : world ? (
                        <GlobePanel world={world} places={gPlaces} byCountry={gByCountry} zoom={gk} pulses={liveOn} rotate={prefs.rotate} style={globeStyle} onStyleFail={() => setStyleFailed(true)}
                          onBackground={deselect} onSelect={select} onZoom={setGk} onReady={(hd: GlobeHandle) => { globe.current = hd; }} onFail={() => setFailed(true)} />
                      ) : <p className="uf-loading">{__('Loading…')}</p>}
                      {sel ? (
                        <div className="uf-gl-card" role="status">
                          <strong>{sel.title}</strong>
                          <span>{num(sel.visitors)} {__('visitors')} · {num(sel.sessions)} {__('sessions')}</span>
                          <span className="uf-gl-card-act">
                            <button type="button" className="button button-small" onClick={() => setOpen({ level: sel.level, key: sel.key, title: sel.title })}>{__('Details')}</button>
                            <button type="button" className="button button-small" onClick={deselect}>{__('Zoom out')}</button>
                          </span>
                        </div>
                      ) : null}
                    </div>
                    {showFeed ? (
                      <div id="uf-gl-feed" className="uf-gl-feed">
                        <LiveFeedPanel items={live.items} fresh={live.fresh} loaded={live.loaded} error={live.error} live={liveOn}
                          filter={filter} matches={feedMatch} onClearFilter={() => setFilter(null)} onPick={pick} />
                      </div>
                    ) : null}
                  </div>
                ) : (
                <div className="uf-map-wrap">
                    {world ? (
                      <svg ref={svgRef} className="uf-map" viewBox={vb} role="group" aria-label={__('World map of visitors')}
                        onPointerDown={(e) => { drag.current = { x: e.clientX, y: e.clientY }; (e.currentTarget as SVGSVGElement).setPointerCapture(e.pointerId); }}
                        onPointerMove={(e) => {
                          if (!drag.current || !svg.current) { return; }
                          const r = svg.current.getBoundingClientRect(), dx = ((e.clientX - drag.current.x) / r.width) * vw, dy = ((e.clientY - drag.current.y) / r.height) * vh;
                          drag.current = { x: e.clientX, y: e.clientY };
                          setView((v) => clamp({ k: v.k, x: v.x - dx, y: v.y - dy }));
                        }}
                        onPointerUp={() => { drag.current = null; }}>
                        <rect x="0" y="0" width={W} height={H} className="uf-map-sea" />
                        {Object.keys(world.countries).map((cc) => <path key={cc} d={world.countries[cc]} className="uf-map-land" style={byCountry[cc] ? { fillOpacity: 0.35 + 0.65 * (byCountry[cc] / maxC) } : undefined} strokeWidth={0.4 / view.k} data-has={byCountry[cc] ? '1' : undefined} onPointerEnter={growMap ? () => setHotCc(cc) : undefined} onPointerLeave={growMap ? () => setHotCc(null) : undefined} />)}
                        {growMap && hotCc && world.countries[hotCc] ? <path className="uf-map-hot" d={world.countries[hotCc]} strokeWidth={0.6 / view.k} aria-hidden="true" pointerEvents="none" /> : null}
                        {list.map((p) => {
                          const [x, y] = proj(world, p.lat, p.lon), r = (11 + 15 * Math.sqrt(p.visitors / max)) / view.k;
                          return (
                            <g key={keyOf(p)} className="uf-map-pt" role="button" tabIndex={0} transform={`translate(${x} ${y})`} aria-label={`${titleOf(p)}: ${p.visitors} ${__('visitors')}`}
                              onClick={() => setOpen({ level: effective, key: keyOf(p), title: titleOf(p) })}
                              onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setOpen({ level: effective, key: keyOf(p), title: titleOf(p) }); } }}>
                              <circle r={r} strokeWidth={1.5 / view.k} />
                              <text textAnchor="middle" dy={4 / view.k} fontSize={12 / view.k}>{num(p.visitors)}</text>
                            </g>
                          );
                        })}
                      </svg>
                    ) : <p className="uf-loading">{__('Loading…')}</p>}
                  </div>
                )}
                <p className="uf-sub">{effective === 'city' && d.mode !== 'city' ? __('City level needs “Country and city” in Settings → Geolocation.') : null} {globeOn && globeStyle === 'vector' ? <span>{__('Map outline:')} <a href="https://commons.wikimedia.org/wiki/File:Equirectangular_projection_world_map_without_borders.svg" target="_blank" rel="noopener noreferrer">Wikimedia Commons</a>, <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener noreferrer">CC BY-SA 4.0</a>. </span> : null}{num(d.total)} {__('visitors in total')}{d.unknown ? ` · ${num(d.unknown)} ${__('with unknown location')}` : ''}</p>
              </Card>
              <details className="uf-card">
                <summary>{__('Show as table')}</summary>
                {list.length ? (
                  <table className="widefat striped uf-table"><thead><tr><th>{effective === 'continent' ? __('Continent') : effective === 'country' ? __('Country') : __('City')}</th><th>{__('Visitors')}</th><th>{__('Sessions')}</th></tr></thead>
                    <tbody>{list.map((p) => <tr key={keyOf(p)}><td><button className="button-link uf-strong" onClick={() => setOpen({ level: effective, key: keyOf(p), title: titleOf(p) })}>{titleOf(p)}</button></td><td>{num(p.visitors)}</td><td>{num(p.sessions)}</td></tr>)}</tbody></table>
                ) : <p className="uf-empty">{__('No locations recorded in this period.')}</p>}
              </details>
            </div>
          );
        }}
      </Async>
      {open ? <MapModal level={open.level} keyId={open.key} title={open.title} days={days} onClose={() => setOpen(null)} /> : null}
    </div>
  );
}
