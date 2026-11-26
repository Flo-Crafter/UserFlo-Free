import { useEffect, useState, useCallback, useRef } from './wp';
import { get, ApiError } from './api';

export interface Query<T> { data: T | null; error: ApiError | null; loading: boolean; reload: () => void }

/** Fetches a read endpoint; ignores stale responses and aborts on change/unmount. `path === null` disables. */
export function useApi<T>(path: string | null, params: Record<string, string | number | undefined | null> = {}): Query<T> {
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<ApiError | null>(null);
  const [loading, setLoading] = useState<boolean>(path !== null);
  const [tick, setTick] = useState(0);
  const key = path === null ? '' : path + JSON.stringify(params) + tick;
  const seq = useRef(0);

  useEffect(() => {
    if (path === null) { return undefined; }
    const my = ++seq.current;
    const ctl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    setLoading(true);
    setError(null);
    get<T>(path, params, ctl ? ctl.signal : undefined)
      .then((d) => { if (my === seq.current) { setData(d); setLoading(false); } })
      .catch((e: ApiError & { name?: string }) => { if (my === seq.current && e.name !== 'AbortError') { setError(e); setLoading(false); } });
    return () => { if (ctl) { ctl.abort(); } };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  const reload = useCallback(() => setTick((t) => t + 1), []);
  return { data, error, loading, reload };
}
