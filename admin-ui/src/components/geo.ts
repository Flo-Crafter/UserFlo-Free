import { useEffect, useState } from '../wp';
import type { WorldData } from '../types';

let cache: WorldData | null = null;

/** Loads the bundled world map (SVG paths per country, equirectangular projection) once. */
export function useWorld(): WorldData | null {
  const [w, setW] = useState<WorldData | null>(cache);
  useEffect(() => {
    if (cache || !window.userfloApp.geoUrl) { return; }
    let stop = false;
    fetch(window.userfloApp.geoUrl).then((r) => r.json()).then((d: WorldData) => { cache = d; if (!stop) { setW(d); } }).catch(() => undefined);
    return () => { stop = true; };
  }, []);
  return w;
}

export const proj = (w: WorldData, lat: number, lon: number): [number, number] => [
  ((lon + 180) / 360) * w.w,
  ((w.latMax - Math.max(w.latMin, Math.min(w.latMax, lat))) / (w.latMax - w.latMin)) * w.h,
];
