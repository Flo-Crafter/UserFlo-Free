import { h, __, screen } from '../wp';
import { useApi } from '../hooks';
import type { GoalStats } from '../types';
import { num } from '../format';
import { Async, Card, DeltaChip, Empty } from './ui';

/** Goal completions and conversion rates for the Overview's period. Nothing is shown when there are no goals and a link explains how to add one. */
export function GoalsCard(p: { params: Record<string, string | number | undefined> }) {
  const q = useApi<GoalStats>('goals/stats', p.params);
  return (
    <Card title={__('Goals')} more={{ href: screen('goals'), label: __('All goals') }}>
      <Async q={q}>
        {(s) => {
          const live = s.goals.filter((g) => g.status === 'active');
          return live.length === 0 ? (
            <Empty>{__('No goals yet.')} <a href="admin.php?page=userflo-goals">{__('Add a goal')}</a></Empty>
          ) : (
            <div>
              <table className="widefat striped uf-compact">
                <thead><tr><th>{__('Goal')}</th><th>{__('Completions')}</th><th>{__('Conversion rate')}</th></tr></thead>
                <tbody>{live.map((g) => (
                  <tr key={g.id}><td>{g.name}</td>
                    <td>{num(g.completions)} {g.prev !== undefined ? <DeltaChip now={g.completions} before={g.prev} vs={__('previous period')} /> : null}</td>
                    <td>{g.rate === null ? '—' : g.rate + '%'}</td></tr>))}</tbody>
              </table>
              <p className="uf-ov-note">{__('Conversion rate: sessions that completed the goal divided by all sessions that started in the period. A session counts once per goal.')} <a href="admin.php?page=userflo-goals">{__('Manage goals')}</a></p>
            </div>
          );
        }}
      </Async>
    </Card>
  );
}
