<?php
namespace UserFlo\Goals;

use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Goal reports. One definition everywhere: a goal's completions in a period are the sessions that STARTED in the period and completed the
 * goal (in that session, whenever in it); the conversion rate is those sessions divided by all sessions that started in the period. A session
 * that spans midnight is therefore counted on the day it started, and the rate can never exceed 100%.
 */
final class Stats {

	/** Sessions that started in [from, to). */
	public static function sessions( $from, $to ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Tables::sessions() . ' WHERE started_at >= %s AND started_at < %s', $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	/**
	 * Completions per page/event goal for a period.
	 *
	 * @return array{sessions:int,goals:array[]}
	 */
	public static function summary( $from, $to ) {
		global $wpdb;
		$n    = self::sessions( $from, $to );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT h.goal_id, COUNT(*) AS n FROM ' . Tables::goal_hits() . ' h JOIN ' . Tables::sessions() . ' s ON s.id = h.session_id WHERE s.started_at >= %s AND s.started_at < %s GROUP BY h.goal_id', $from, $to ), OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		$out  = array();
		foreach ( Store::all() as $g ) {
			if ( ! Store::standard( $g ) ) {
				continue; // Other kinds of goal (an add-on's) have their own reports.
			}
			$c   = isset( $rows[ $g['id'] ] ) ? (int) $rows[ $g['id'] ]->n : 0;
			$row = array(
				'id'          => $g['id'],
				'name'        => $g['name'],
				'type'        => $g['type'],
				'status'      => $g['status'],
				'completions' => $c,
				'rate'        => $n ? round( 100 * $c / $n, 2 ) : null,
			);
			$out[] = apply_filters( 'userflo_goal_summary_row', $row, $g, $c );
		}
		return array( 'sessions' => $n, 'goals' => $out );
	}

	/** Sessions that completed at least one goal, for a period (the "converted" figure used beside campaigns). */
	public static function converting_sessions( $from, $to ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT h.session_id) FROM ' . Tables::goal_hits() . ' h JOIN ' . Tables::sessions() . ' s ON s.id = h.session_id WHERE s.started_at >= %s AND s.started_at < %s', $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	/** Breakdown dimensions: key => array( sql, join ). The SQL is a fixed expression (never user input); `join` adds the visitors table as `v`. */
	public static function dimensions() {
		return (array) apply_filters( 'userflo_goal_dimensions', array(
			'campaign' => array( 'sql' => 's.utm_campaign' ),
			'source'   => array( 'sql' => 's.utm_source' ),
		) );
	}

	/**
	 * One goal split by a dimension. Rows are the dimension values with the most sessions; every row has its own denominator.
	 *
	 * @return array[] key, sessions, completions, rate.
	 */
	public static function breakdown( $goal_id, $from, $to, $by, $limit = 10 ) {
		global $wpdb;
		$dims = self::dimensions();
		$expr = isset( $dims[ $by ] ) ? $dims[ $by ]['sql'] : $dims['campaign']['sql']; // A fixed map: never user input.
		$join = ! empty( $dims[ $by ]['join'] ) ? ' JOIN ' . Tables::visitors() . ' v ON v.id = s.visitor_id' : '';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$expr} AS k, COUNT(*) AS sessions, COUNT(h.session_id) AS completions FROM " . Tables::sessions() . " s{$join} LEFT JOIN " . Tables::goal_hits() . ' h ON h.session_id = s.id AND h.goal_id = %d WHERE s.started_at >= %s AND s.started_at < %s GROUP BY k ORDER BY sessions DESC, k LIMIT %d', (int) $goal_id, $from, $to, (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array( 'key' => (string) $r['k'], 'sessions' => (int) $r['sessions'], 'completions' => (int) $r['completions'], 'rate' => $r['sessions'] ? round( 100 * $r['completions'] / $r['sessions'], 2 ) : null );
		}
		return $out;
	}
}
