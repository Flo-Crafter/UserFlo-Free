<?php
namespace UserFlo\Insights;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;
use UserFlo\Exclusions\Sql;

defined( 'ABSPATH' ) || exit;

/**
 * Peak times and peak days: how many sessions began in each hour of each weekday (in the site's time zone), split into logged-in and
 * anonymous sessions. Counts only; a session counts once, in the hour it started. The offset used is the site's current one, so a range
 * that crosses a daylight-saving change can place a session an hour off.
 */
final class Peaks {

	/**
	 * @param int $from Range start (unix time, inclusive).
	 * @param int $to   Range end (unix time, exclusive).
	 * @return array{grid:array,days:array,hours:array,totals:array,sessions:int}
	 */
	public static function build( $from, $to ) {
		global $wpdb;
		$s   = Tables::sessions();
		$v   = Tables::visitors();
		$off = (int) wp_timezone()->getOffset( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) );
		$loc = $wpdb->prepare( 'DATE_ADD(s.started_at, INTERVAL %d SECOND)', $off );
		$sql = $wpdb->prepare( "SELECT WEEKDAY({$loc}) AS dow, HOUR({$loc}) AS hr, (s.user_id IS NOT NULL OR v.user_id IS NOT NULL) AS li, COUNT(*) AS n FROM {$s} s LEFT JOIN {$v} v ON v.id = s.visitor_id WHERE s.started_at >= %s AND s.started_at < %s" . Sql::urls( 's', 'entry_url' ) . ' GROUP BY dow, hr, li', Clock::mysql( $from ), Clock::mysql( $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		// grid[weekday 0 = Monday .. 6][hour 0..23] = [anonymous, logged-in]
		$grid = array_fill( 0, 7, array_fill( 0, 24, array( 0, 0 ) ) );
		foreach ( (array) $wpdb->get_results( $sql, ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$d = (int) $r['dow'];
			$h = (int) $r['hr'];
			if ( $d >= 0 && $d <= 6 && $h >= 0 && $h <= 23 ) {
				$grid[ $d ][ $h ][ (int) $r['li'] ? 1 : 0 ] += (int) $r['n'];
			}
		}
		$days   = array_fill( 0, 7, array( 0, 0 ) );
		$hours  = array_fill( 0, 24, array( 0, 0 ) );
		$totals = array( 0, 0 );
		foreach ( $grid as $d => $row ) {
			foreach ( $row as $h => $c ) {
				$days[ $d ][0]  += $c[0];
				$days[ $d ][1]  += $c[1];
				$hours[ $h ][0] += $c[0];
				$hours[ $h ][1] += $c[1];
				$totals[0]      += $c[0];
				$totals[1]      += $c[1];
			}
		}
		return array( 'grid' => $grid, 'days' => $days, 'hours' => $hours, 'totals' => $totals, 'sessions' => $totals[0] + $totals[1], 'utc_offset' => $off );
	}
}
