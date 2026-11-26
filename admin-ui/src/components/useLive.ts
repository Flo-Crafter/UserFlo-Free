import { __, useEffect, useRef, useState } from '../wp';
import { get } from '../api';
import type { LiveData, LiveItem, Session } from '../types';

export const POLL_MS = 5000;
const KEEP = 100;

export function visitorLink(id: number, sid: number): string {
  return `${window.userfloApp.urls.visitors}#/visitor/${id}/session/${sid}`;
}

export function describe(i: LiveItem): string {
  if (i.kind === 'pageview') { return `${__('viewed')} ${i.title || i.url}`; }
  const what = i.name || i.target || '';
  return `${i.type}${what ? ': ' + what : ''}${i.url ? ' — ' + i.url : ''}`;
}

export interface LiveState {
  active: Session[]; items: LiveItem[]; fresh: Record<string, boolean>; hour: number; error: string; loaded: boolean; updated: string;
}

/**
 * The one live-activity poll (every 5 seconds, skipped while the tab is hidden) shared by the Live screen and the Map screen.
 * `onNew` receives the items that arrived after the first load, so historical activity never triggers a pulse.
 */
export function useLive(enabled: boolean, onNew?: (items: LiveItem[]) => void): LiveState {
  const [active, setActive] = useState<Session[]>([]);
  const [items, setItems] = useState<LiveItem[]>([]);
  const [fresh, setFresh] = useState<Record<string, boolean>>({});
  const [hour, setHour] = useState(0);
  const [error, setError] = useState('');
  const [loaded, setLoaded] = useState(false);
  const [updated, setUpdated] = useState('');
  const cursor = useRef({ pv: 0, ev: 0 });
  const cb = useRef(onNew);
  cb.current = onNew;

  useEffect(() => {
    if (!enabled) { return undefined; }
    let stop = false;
    let timer = 0;
    const tick = async () => {
      if (stop) { return; }
      if (document.visibilityState === 'hidden') { timer = window.setTimeout(tick, POLL_MS); return; }
      try {
        const d = (await get<LiveData>('live', { pv: cursor.current.pv, ev: cursor.current.ev })) as LiveData;
        if (stop) { return; }
        const first = cursor.current.pv === 0 && cursor.current.ev === 0;
        cursor.current = d.cursor;
        setActive(d.active); setHour(d.last_hour); setError(''); setLoaded(true); setUpdated(d.server);
        if (d.items.length) {
          setItems((old) => {
            const seen: Record<string, boolean> = {};
            old.forEach((o) => { seen[o.key] = true; });
            const add = d.items.filter((i) => !seen[i.key]);
            return add.concat(old).slice(0, KEEP);
          });
          if (!first) {
            const f: Record<string, boolean> = {};
            d.items.forEach((i) => { f[i.key] = true; });
            setFresh(f);
            if (cb.current) { cb.current(d.items); }
          }
        }
      } catch (e) {
        if (!stop) { setError((e as Error).message || __('Could not load data:')); }
      }
      if (!stop) { timer = window.setTimeout(tick, POLL_MS); }
    };
    tick();
    return () => { stop = true; window.clearTimeout(timer); };
  }, [enabled]);

  return { active, items, fresh, hour, error, loaded, updated };
}
