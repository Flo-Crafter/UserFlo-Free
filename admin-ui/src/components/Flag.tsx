import { h, __, useState, useEffect, cfg } from '../wp';
import { flagCode } from '../columnOrder';

/**
 * The national flag beside a country, drawn from one local SVG file that ships with FloLens (no image requests to other sites, no emoji
 * fonts, so it looks the same in every browser and operating system). The file is fetched once, only when a screen shows a flag, and
 * placed in the page; each flag then refers to it. When the file cannot be loaded, or the country has no flag in it, nothing is drawn and
 * the country name next to it still tells the story.
 */
const HOLDER = 'uf-flag-sprite';
let loading: Promise<boolean> | null = null;

function loadSprite(): Promise<boolean> {
  if (document.getElementById(HOLDER)) { return Promise.resolve(true); }
  const url = cfg().flagsUrl;
  if (!url || typeof window.fetch !== 'function') { return Promise.resolve(false); }
  if (!loading) {
    loading = window.fetch(url, { credentials: 'same-origin' })
      .then((r) => (r.ok ? r.text() : Promise.reject(new Error('flags'))))
      .then((svg) => {
        const box = document.createElement('div');
        box.id = HOLDER; box.hidden = true; box.setAttribute('aria-hidden', 'true');
        box.innerHTML = svg;
        document.body.appendChild(box);
        return true;
      })
      .catch(() => { loading = null; return false; });
  }
  return loading;
}

/** Whether the sprite has a flag for this code (in the same page, so it can be checked without a request). */
export function hasFlag(code: string): boolean {
  return !!code && !!document.getElementById('uf-flag-' + code);
}

/** A flag for a two-letter country code with a text alternative ("Flag of Hungary"); renders nothing for an unknown or malformed code. */
export function Flag(p: { code: unknown; name?: string }) {
  const code = flagCode(p.code);
  // 'wait' while the file is on its way (the flag's space is kept so the column does not jump), then 'yes' or 'no'.
  const [state, setState] = useState<'wait' | 'yes' | 'no'>(() => (document.getElementById(HOLDER) ? 'yes' : 'wait'));
  useEffect(() => {
    if (!code || state !== 'wait') { return undefined; }
    let on = true;
    loadSprite().then((ok) => { if (on) { setState(ok ? 'yes' : 'no'); } });
    return () => { on = false; };
  }, [code, state]);
  if (!code || state === 'no' || (state === 'yes' && !hasFlag(code))) { return null; }
  if (state === 'wait') { return <span className="uf-flag uf-flag-wait" aria-hidden="true" />; }
  return (
    <svg className="uf-flag" viewBox="0 0 3 2" width="21" height="14" role="img" aria-label={p.name ? __('Flag of') + ' ' + p.name : __('Flag')} focusable="false">
      <use href={'#uf-flag-' + code} width="3" height="2" />
    </svg>
  );
}
