import { h, __, useState } from '../wp';
import { animationsOn } from '../motion';
import { num } from '../format';
import { useWorld } from './geo';

export interface MapRow { code: string; label: string; value: number }

const spots: Record<string, [number, number]> = {};

/** Where to print a country's number: the middle of the bounding box of its largest outline (so an island group is not labelled in the sea). */
function spot(code: string, path: string): [number, number] {
  if (spots[code]) { return spots[code]; }
  let best: [number, number] = [0, 0], area = -1;
  path.split('M').forEach((part) => {
    const n = part.replace(/[A-Za-z]/g, ' ').trim().split(/[\s,]+/).map(Number);
    if (n.length < 4) { return; }
    let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    for (let i = 0; i + 1 < n.length; i += 2) { x0 = Math.min(x0, n[i]); x1 = Math.max(x1, n[i]); y0 = Math.min(y0, n[i + 1]); y1 = Math.max(y1, n[i + 1]); }
    const a = (x1 - x0) * (y1 - y0);
    if (a > area) { area = a; best = [(x0 + x1) / 2, (y0 + y1) / 2]; }
  });
  spots[code] = best;
  return best;
}

/** The flat world map of the Map screen, with each listed country shaded by its share and its number printed on it. */
export function CountryMap(p: { rows: MapRow[]; unit: string }) {
  const world = useWorld();
  const [hot, setHot] = useState<string | null>(null); // The country under the pointer: drawn again on top, slightly larger, so neighbours cannot cover it.
  const grow = animationsOn();
  if (!world) { return <p className="uf-loading">{__('Loading…')}</p>; }
  const max = Math.max(1, ...p.rows.map((r) => r.value));
  const by: Record<string, MapRow> = {};
  p.rows.forEach((r) => { by[r.code] = r; });
  return (
    <div className="uf-cmap-wrap">
      <svg className="uf-cmap" viewBox={`0 0 ${world.w} ${world.h}`} role="img" aria-label={p.rows.map((r) => `${r.label}: ${num(r.value)} ${p.unit}`).join(', ')}>
        <rect x="0" y="0" width={world.w} height={world.h} className="uf-map-sea" />
        {Object.keys(world.countries).map((cc) => (
          <path key={cc} d={world.countries[cc]} className="uf-map-land" strokeWidth={0.4} data-has={by[cc] ? '1' : undefined} style={by[cc] ? { fillOpacity: 0.35 + 0.65 * (by[cc].value / max) } : undefined}
            onPointerEnter={grow ? () => setHot(cc) : undefined} onPointerLeave={grow ? () => setHot(null) : undefined}>
            {by[cc] ? <title>{`${by[cc].label}: ${num(by[cc].value)} ${p.unit}`}</title> : null}
          </path>
        ))}
        {grow && hot && world.countries[hot] ? <path className="uf-map-hot" d={world.countries[hot]} aria-hidden="true" pointerEvents="none" /> : null}
        {p.rows.filter((r) => world.countries[r.code]).map((r) => {
          const [x, y] = spot(r.code, world.countries[r.code]);
          return <text key={r.code} className="uf-cmap-n" x={x} y={y} textAnchor="middle" dominantBaseline="central">{num(r.value)}</text>;
        })}
      </svg>
    </div>
  );
}
