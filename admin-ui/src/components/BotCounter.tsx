import { h, __, sprintf, cfg, useState } from '../wp';
import { useApi } from '../hooks';
import { num } from '../format';
import { Async } from './ui';
import { Viz } from './Viz';

export interface BotData {
  enabled: boolean; first_day: string | null;
  kinds: { ai: number; search: number; other: number };
  bots: { bot: string; kind: 'ai' | 'search' | 'other'; hits: number; assistant?: string; purpose?: string }[];
  assistants: { assistant: string; hits: number }[];
  referrals: { total: number; logged_in: number; assistants: { assistant: string; sessions: number }[] };
  pages: { ai: { url: string; hits: number; bots: number }[]; other: { url: string; hits: number; bots: number }[] };
  daily: Record<string, { ai?: number; search?: number; other?: number }>;
}

const KIND: Record<string, string> = { ai: __('AI crawlers'), search: __('Search engines'), other: __('Other bots') };
const PURPOSE: Record<string, string> = { training: __('collects pages for training'), search: __('builds an answer-search index'), user: __('fetches a page because someone asked') };

function Tile(p: { k: number; label: string; value: string; hint: string }) {
  return (
    <div className="uf-bot-tile" style={{ ['--k' as string]: `var(--uf-series-${p.k})` }}>
      <div className="uf-bot-v">{p.value}</div>
      <div className="uf-bot-l">{p.label}</div>
      <div className="uf-bot-h">{p.hint}</div>
    </div>
  );
}

/** Which AI is behind the crawler visits (by assistant or by crawler, with what each does) and the pages AI crawlers asked for. */
function CrawlerDetail(p: { d: BotData }) {
  const [by, setBy] = useState<'assistant' | 'crawler'>('assistant');
  const d = p.d;
  const ai = d.bots.filter((b) => b.kind === 'ai');
  const rows = by === 'assistant'
    ? d.assistants.map((a) => ({ key: a.assistant, label: a.assistant, value: a.hits }))
    : ai.map((b) => ({ key: b.bot, label: b.purpose && PURPOSE[b.purpose] ? `${b.bot} · ${PURPOSE[b.purpose]}` : b.bot, value: b.hits }));
  return (
    <div className="uf-bot-detail">
      <div>
        <h3>{__('Which AI crawled your site')}</h3>
        <div className="uf-viz-switch" role="group" aria-label={__('Group by')}>
          <button type="button" className={'button button-small' + (by === 'assistant' ? ' button-primary' : '')} aria-pressed={by === 'assistant'} onClick={() => setBy('assistant')}>{__('By assistant')}</button>
          <button type="button" className={'button button-small' + (by === 'crawler' ? ' button-primary' : '')} aria-pressed={by === 'crawler'} onClick={() => setBy('crawler')}>{__('By crawler')}</button>
        </div>
        <Viz id={'bots-who-' + by} initial="bars" kinds={['bars', 'donut', 'pie', 'columns']} centreLabel={__('requests')} rows={rows} empty={__('No AI crawler visits in this period.')} />
      </div>
      <div>
        <h3>{__('Pages AI crawlers requested most')}</h3>
        {d.pages.ai.length ? (
          <ol className="uf-rank">{d.pages.ai.map((r) => <li key={r.url}><div className="uf-rank-top"><span className="uf-rank-label" title={r.url}>{r.url}</span><span className="uf-rank-n">{num(r.hits)}</span></div><div className="uf-rank-bar" aria-hidden="true"><span style={{ width: Math.max(2, (r.hits / d.pages.ai[0].hits) * 100) + '%' }} /></div></li>)}</ol>
        ) : <p className="uf-empty">{__('No AI crawler visits in this period.')}</p>}
      </div>
    </div>
  );
}

/**
 * Humans next to bots. Humans are visitors and page views seen by the browser tracker; bots are requests counted on the server
 * (most bots never run JavaScript). The two are different measurements, so the tiles say which is which and the bar compares page loads.
 * The fifth tile is people sent by an AI assistant (their visits, from the referrer or utm_source).
 */
export function BotCounter(p: { params: { days?: number; from?: string; to?: string }; humans: { visitors: number; pageviews: number }; full?: boolean }) {
  const q = useApi<BotData>('stats/bots', p.params);
  return (
    <Async q={q}>
      {(d) => {
        if (!d.enabled) {
          return <p className="uf-empty">{__('Counting bots is switched off.')} <a href={cfg().urls.settings}>{__('Turn it on in Settings')}</a></p>;
        }
        const total = d.kinds.ai + d.kinds.search + d.kinds.other;
        const loads = p.humans.pageviews + total;
        const pct = (n: number) => (loads ? (100 * n) / loads : 0);
        const parts: [string, number, number][] = [[__('Humans'), p.humans.pageviews, 1], [KIND.ai, d.kinds.ai, 5], [KIND.search, d.kinds.search, 3], [KIND.other, d.kinds.other, 6]];
        const ref = d.referrals || { total: 0, logged_in: 0, assistants: [] };
        return (
          <div>
            <div className="uf-bot-tiles">
              <Tile k={1} label={__('Human visitors')} value={num(p.humans.visitors)} hint={sprintf(__('%s page views'), num(p.humans.pageviews))} />
              <Tile k={5} label={KIND.ai} value={num(d.kinds.ai)} hint={__('page requests')} />
              <Tile k={3} label={KIND.search} value={num(d.kinds.search)} hint={__('page requests')} />
              <Tile k={6} label={KIND.other} value={num(d.kinds.other)} hint={__('page requests')} />
              <Tile k={2} label={__('Sent by an AI assistant')} value={num(ref.total)} hint={ref.assistants.length ? ref.assistants.slice(0, 2).map((a) => a.assistant).join(', ') : __('visits from AI chats')} />
            </div>
            {loads ? (
              <div>
                <div className="uf-bot-bar" role="img" aria-label={parts.map(([l, n]) => `${l}: ${Math.round(pct(n))}%`).join(', ')}>
                  {parts.map(([l, n, k]) => (n ? <span key={l} style={{ width: pct(n) + '%', background: `var(--uf-series-${k})` }} title={`${l}: ${num(n)} (${Math.round(pct(n))}%)`} /> : null))}
                </div>
                <p className="uf-bot-legend">{parts.map(([l, n, k]) => <span key={l}><i className="uf-bl-sw" style={{ background: `var(--uf-series-${k})` }} aria-hidden="true" /> {l} {pct(n) >= 0.1 ? Math.round(pct(n) * 10) / 10 + '%' : n ? '<0.1%' : '0%'}</span>)}</p>
              </div>
            ) : null}
            {total === 0 ? <p className="uf-sub">{d.first_day ? __('No bot visits in this period.') : __('No bot visits recorded yet. Counting started when this option was turned on, so numbers appear as bots visit.')}</p> : null}
            {p.full && total > 0 ? <CrawlerDetail d={d} /> : null}
            {p.full ? (
              <div className="uf-bot-ref">
                <h3>{__('Visitors sent by AI assistants')}</h3>
                {ref.assistants.length ? (
                  <Viz id="bots-referrals" initial="bars" kinds={['bars', 'donut', 'pie', 'columns']} centreLabel={__('visits')} rows={ref.assistants.map((a) => ({ key: a.assistant, label: a.assistant, value: a.sessions }))} />
                ) : <p className="uf-empty">{__('No visits from AI assistants in this period.')}</p>}
                <p className="uf-sub">{__('Recognised from the referrer or utm_source of each visit (for example chatgpt.com, claude.ai, perplexity.ai). Visits from Google’s AI answers look like ordinary Google traffic and are not included; apps that hide the referrer, and copied links, show up as direct visits. Treat this as a minimum. You can add more assistants in Settings.')}</p>
              </div>
            ) : null}
            <p className="uf-sub">{__('Bots are counted on the server by the name they give themselves, which can be faked. Pages served from a full-page cache may not be counted, so treat bot numbers as a minimum. Humans are counted by the browser tracker; the bar compares page loads.')}</p>
          </div>
        );
      }}
    </Async>
  );
}
