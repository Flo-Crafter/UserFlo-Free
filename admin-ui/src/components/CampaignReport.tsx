import { h, __, useState, can } from '../wp';
import { useApi } from '../hooks';
import type { Campaigns, CampaignRow } from '../types';
import { duration, num } from '../format';
import { Async, Card, DeltaChip, LimitNote } from './ui';

const GROUPS: [string, string, boolean][] = [
  ['campaign', __('Campaign'), false], ['source', __('Source'), false], ['medium', __('Medium'), false], ['content', __('Content'), false], ['term', __('Term'), false],
  ['source_medium', __('Source / medium'), true], ['source_medium_campaign', __('Source / medium / campaign'), true],
];
const COLS: Record<string, (keyof CampaignRow)[]> = {
  campaign: ['campaign'], source: ['source'], medium: ['medium'], content: ['content'], term: ['term'],
  source_medium: ['source', 'medium'], source_medium_campaign: ['source', 'medium', 'campaign'],
};
const label = (r: CampaignRow, cols: (keyof CampaignRow)[]) => cols.map((c) => String(r[c] || '(none)')).join(' / ');

/** Sessions by campaign (UTM) value. The campaign is the one on the page that started each session; see docs for how this differs from GA4. */
export function CampaignReport(p: { days: number }) {
  const [group, setGroup] = useState('campaign');
  const detail = can('campaigns_detail');
  const q = useApi<Campaigns>('stats/campaigns', { days: p.days, group, compare: detail ? 1 : 0 });
  return (
    <Card title={__('Campaigns')}>
      <p className="uf-sub">{__('Sessions grouped by the utm_ values on the page that started them. A visit without utm values is not listed here. Each session belongs to one campaign: the one it arrived with.')}</p>
      <div className="uf-filter" role="group" aria-label={__('Group by')}>
        {GROUPS.filter((g) => !g[2] || detail).map(([k, l]) => <button key={k} className={'button' + (group === k ? ' button-primary' : '')} aria-pressed={group === k} onClick={() => setGroup(k)}>{l}</button>)}
      </div>
      <LimitNote feature="campaigns_detail">{__('Combined source / medium / campaign views and the comparison with the previous period are part of Pro.')}</LimitNote>
      <Async q={q} empty={(d) => d.rows.length === 0} emptyText={__('No campaign-tagged visits in this period. Add utm_source, utm_medium and utm_campaign to the links you share.')}>
        {(d) => {
          const cols = COLS[d.group] || COLS.campaign;
          const max = Math.max(1, ...d.rows.map((r) => r.sessions));
          return (
            <div>
              {d.backfilling ? <p className="uf-sub" role="status">{__('Older visits are still being read in the background; counts for earlier periods may grow for a while.')}</p> : null}
              <p className="uf-sub">{num(d.tagged)} {__('of')} {num(d.total)} {__('sessions in this period carry a campaign tag.')}</p>
              <table className="widefat striped uf-compact">
                <thead><tr><th>{GROUPS.find((g) => g[0] === d.group)?.[1]}</th><th>{__('Sessions')}</th><th>{__('Visitors')}</th><th>{__('Pages / session')}</th><th>{__('Single-page')}</th><th>{__('Avg. engaged')}</th>{d.has_goals ? <th>{__('Completed a goal')}</th> : null}</tr></thead>
                <tbody>{d.rows.map((r, i) => (
                  <tr key={i}>
                    <td><strong>{label(r, cols)}</strong><div className="uf-camp-bar" aria-hidden="true"><span style={{ width: Math.max(2, (r.sessions / max) * 100) + '%' }} /></div></td>
                    <td>{num(r.sessions)} {r.prev !== undefined ? <DeltaChip now={r.sessions} before={r.prev} vs={__('previous period')} /> : null}</td>
                    <td>{num(r.visitors)}</td><td>{r.pages}</td><td>{r.single_rate}%</td><td>{duration(r.engaged_secs)}</td>
                    {d.has_goals ? <td>{num(r.converted || 0)} <small>({r.sessions ? Math.round((1000 * (r.converted || 0)) / r.sessions) / 10 : 0}%)</small></td> : null}
                  </tr>
                ))}</tbody>
              </table>
            </div>
          );
        }}
      </Async>
    </Card>
  );
}
