<?php
namespace UserFlo\Goals;

use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Re-reads the stored page views or events for ONE goal and records its completions. Editing a goal is not retroactive; this is the explicit
 * action that makes the stored history agree with the current definition. It works in id windows so a single request stays short, and the
 * caller repeats it until `done`.
 */
final class History {

	const WINDOW = 50000;

	/**
	 * @param int  $goal_id Goal id (a page or event goal).
	 * @param int  $after   Last row id handled by the previous call (0 to start).
	 * @param bool $reset   Remove the goal's existing completions first (only with $after = 0).
	 * @return array|\WP_Error next (last id handled), max (the current maximum), done, added.
	 */
	public static function rebuild( $goal_id, $after = 0, $reset = false ) {
		global $wpdb;
		$g = Store::get( $goal_id );
		if ( ! $g || ! Store::standard( $g ) ) {
			return new \WP_Error( 'userflo_goal_missing', __( 'Only page and event goals can be re-evaluated.', 'flolens' ), array( 'status' => 404 ) );
		}
		/** An add-on can run the re-read itself for goals with extra conditions (return null to leave it here). */
		$other = apply_filters( 'userflo_goal_rebuild', null, $g, (int) $after, (bool) $reset );
		if ( null !== $other ) {
			return $other;
		}
		$after = max( 0, (int) $after );
		if ( $reset && 0 === $after ) {
			$wpdb->delete( Tables::goal_hits(), array( 'goal_id' => (int) $g['id'] ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		}
		$tbl  = 'page' === $g['type'] ? Tables::pageviews() : Tables::events();
		$time = 'page' === $g['type'] ? 'x.viewed_at' : 'x.occurred_at';
		$max  = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$tbl}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$end  = min( $max, $after + self::WINDOW );
		$cond = Store::condition( $g, 'x' );
		$add  = 0;
		if ( $end > $after && (int) $g['min_count'] > 1 ) {
			// Sessions with a match in this window, then everything they matched (a session can straddle two windows), kept when it reaches the amount.
			$cond2 = Store::condition( $g, 'y' );
			$time2 = str_replace( 'x.', 'y.', $time );
			$sql   = 'INSERT IGNORE INTO ' . Tables::goal_hits() . " (goal_id, session_id, visitor_id, hit_at) SELECT %d, y.session_id, MIN(y.visitor_id), MIN({$time2}) FROM (SELECT DISTINCT x.session_id FROM {$tbl} x WHERE x.id > %d AND x.id <= %d AND {$cond[0]}) w JOIN {$tbl} y ON y.session_id = w.session_id WHERE {$cond2[0]} GROUP BY y.session_id HAVING COUNT(*) >= %d";
			$add   = (int) $wpdb->query( $wpdb->prepare( $sql, array_merge( array( (int) $g['id'], $after, $end ), $cond[1], $cond2[1], array( max( 1, (int) $g['min_count'] ) ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		} elseif ( $end > $after ) {
			$sql = 'INSERT IGNORE INTO ' . Tables::goal_hits() . " (goal_id, session_id, visitor_id, hit_at) SELECT %d, x.session_id, MIN(x.visitor_id), MIN({$time}) FROM {$tbl} x WHERE x.id > %d AND x.id <= %d AND {$cond[0]} GROUP BY x.session_id";
			$add = (int) $wpdb->query( $wpdb->prepare( $sql, array_merge( array( (int) $g['id'], $after, $end ), $cond[1] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		}
		return array( 'next' => $end, 'max' => $max, 'done' => $end >= $max, 'added' => $add );
	}

	/** Matches in the last N days, for the "does this goal match anything?" check while editing. Read-only. */
	public static function preview( array $g, $days = 30 ) {
		global $wpdb;
		$cond = Store::condition( $g, 'x' );
		if ( null === $cond ) {
			return array( 'matches' => 0, 'sessions' => 0 );
		}
		$tbl  = 'page' === $g['type'] ? Tables::pageviews() : Tables::events();
		$time = 'page' === $g['type'] ? 'x.viewed_at' : 'x.occurred_at';
		$from = \UserFlo\Core\Clock::mysql( \UserFlo\Core\Clock::time() - (int) $days * DAY_IN_SECONDS );
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS m, COUNT(DISTINCT x.session_id) AS s FROM {$tbl} x WHERE {$time} >= %s AND {$cond[0]}", array_merge( array( $from ), $cond[1] ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
		return array( 'matches' => (int) $row['m'], 'sessions' => (int) $row['s'], 'days' => (int) $days );
	}
}
