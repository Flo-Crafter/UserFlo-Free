<?php
namespace UserFlo\Insights;

use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * What happens just before and just after a moment in a session: the page viewed next and the page viewed before. Used by Elements ("people who
 * used this button went on to…") and by the page report. It looks at a bounded sample, never at the whole table, and only describes what was
 * observed: it says nothing about cause.
 */
final class Around {

	const MAX_POINTS = 1500;

	/**
	 * @param array[] $points Each: session_id, at (UTC mysql datetime of the moment), url (page it happened on, or '' for any).
	 * @return array{sampled:int,next:array[],prev:array[]} next/prev: url, count; next also holds the share that ended the session (url '').
	 */
	public static function pages( array $points ) {
		global $wpdb;
		$points = array_slice( $points, 0, self::MAX_POINTS );
		$by     = array();
		foreach ( $points as $p ) {
			$by[ (int) $p['session_id'] ][] = $p;
		}
		$next = array();
		$prev = array();
		$pv   = Tables::pageviews();
		foreach ( array_chunk( array_keys( $by ), 400 ) as $chunk ) {
			$rows = $wpdb->get_results( "SELECT session_id, url, viewed_at FROM {$pv} WHERE session_id IN (" . implode( ',', array_map( 'intval', $chunk ) ) . ') ORDER BY session_id, viewed_at, id', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
			$views = array();
			foreach ( (array) $rows as $r ) {
				$views[ (int) $r['session_id'] ][] = $r;
			}
			foreach ( $chunk as $sid ) {
				$list = isset( $views[ $sid ] ) ? $views[ $sid ] : array();
				foreach ( $by[ $sid ] as $p ) {
					$idx = -1;
					foreach ( $list as $i => $v ) {
						if ( $v['viewed_at'] <= $p['at'] ) {
							$idx = $i;
						} else {
							break;
						}
					}
					$cur    = $idx >= 0 ? $list[ $idx ]['url'] : $p['url'];
					$after  = '';
					$before = '';
					for ( $j = $idx + 1; $j < count( $list ); $j++ ) {
						if ( $list[ $j ]['url'] !== $cur ) {
							$after = $list[ $j ]['url'];
							break;
						}
					}
					for ( $j = $idx - 1; $j >= 0; $j-- ) {
						if ( $list[ $j ]['url'] !== $cur ) {
							$before = $list[ $j ]['url'];
							break;
						}
					}
					$next[ $after ]   = isset( $next[ $after ] ) ? $next[ $after ] + 1 : 1;
					if ( '' !== $before ) {
						$prev[ $before ] = isset( $prev[ $before ] ) ? $prev[ $before ] + 1 : 1;
					}
				}
			}
		}
		$fmt = static function ( array $m ) {
			arsort( $m );
			$out = array();
			foreach ( array_slice( $m, 0, 6, true ) as $url => $n ) {
				$out[] = array( 'url' => (string) $url, 'count' => (int) $n );
			}
			return $out;
		};
		return array( 'sampled' => count( $points ), 'next' => $fmt( $next ), 'prev' => $fmt( $prev ) );
	}
}
