export class ApiError extends Error {
  status: number;
  constructor(message: string, status: number) { super(message); this.status = status; }
}

type Params = Record<string, string | number | undefined | null>;

export function qs(params: Params = {}): string {
  const p = Object.keys(params)
    .filter((k) => params[k] !== undefined && params[k] !== null && params[k] !== '')
    .map((k) => `${encodeURIComponent(k)}=${encodeURIComponent(String(params[k]))}`);
  return p.length ? `?${p.join('&')}` : '';
}

async function call<T>(method: 'GET' | 'DELETE', path: string, params: Params, signal?: AbortSignal): Promise<T> {
  try {
    // The AI screens show live state (accounts, the call log): a caching plugin or CDN must never answer them from an old copy.
    const fresh = method === 'GET' && path.indexOf('ai-admin/') === 0 ? { ...params, _ts: Date.now() } : params;
    return (await window.wp.apiFetch({ path: `/userflo/v1/${path}${qs(fresh)}`, method, signal })) as T;
  } catch (e) {
    const err = e as { message?: string; data?: { status?: number }; name?: string };
    if (err && err.name === 'AbortError') { throw e; }
    throw new ApiError(err && err.message ? err.message : 'Request failed', err && err.data && err.data.status ? err.data.status : 0);
  }
}

export const get = <T,>(path: string, params: Params = {}, signal?: AbortSignal): Promise<T> => call<T>('GET', path, params, signal);
export const del = <T,>(path: string): Promise<T> => call<T>('DELETE', path, {});

/** Writes (create, update, delete, actions). WordPress' apiFetch adds the REST nonce. */
export async function send<T>(method: 'POST' | 'PUT' | 'DELETE', path: string, data?: Record<string, unknown>): Promise<T> {
  try {
    return (await window.wp.apiFetch({ path: `/userflo/v1/${path}`, method, data })) as T;
  } catch (e) {
    const err = e as { message?: string; data?: { status?: number } };
    throw new ApiError(err && err.message ? err.message : 'Request failed', err && err.data && err.data.status ? err.data.status : 0);
  }
}
