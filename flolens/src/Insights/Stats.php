<?php
namespace UserFlo\Insights;

use UserFlo\Core\Clock;
use UserFlo\Database\Reports;
use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/** Aggregated "what do visitors do" statistics for a period. All queries are bounded (LIMIT, time range) and the result is cached by the route. */
final class Stats {

	const JOURNEY_SESSIONS = 3000;

	public static function build( $from_ts, $to_ts ) {
		$from = Clock::mysql( $from_ts );
		$to   = Clock::mysql( $to_ts );
		$pages = array();
		foreach ( Reports::top_pages( $from, 15, $to ) as $p ) {
			$pages[] = array( 'url' => $p['url'], 'title' => $p['title'], 'views' => (int) $p['views'], 'visitors' => (int) $p['visitors'], 'avg_engaged' => (int) round( $p['avg_engaged'] ) );
		}
		return array(
			'pages'      => $pages,
			'buttons'    => self::events( "e.type = 'click' AND e.value = ''", 'e.target', 'target', $from, $to ),
			'links'      => self::events( "e.type = 'click' AND e.value <> '' AND e.value NOT IN ('mailto:','tel:')", 'e.value', 'value', $from, $to ),
			'outbound'   => self::events( "e.type = 'outbound'", 'e.value', 'value', $from, $to ),
			'downloads'  => self::events( "e.type = 'download'", 'e.value', 'value', $from, $to ),
			'forms'      => self::events( "e.type = 'form'", 'e.target', 'target', $from, $to ),
			'custom'     => self::events( "e.type = 'custom'", 'e.name', 'name', $from, $to ),
			'entry'      => self::sessions_by( 'entry_url', $from, $to ),
			'exit'       => self::sessions_by( 'exit_url', $from, $to ),
			'devices'    => self::sessions_by( 'device', $from, $to, 5 ),
			'browsers'   => self::sessions_by( 'browser', $from, $to, 8 ),
			'journeys'   => self::journeys( $from, $to ),
		);
	}

	/** Top rows for an event slice. Keys: label (name), key (grouped column), count. */
	private static function events( $cond, $group, $key, $from, $to ) {
		global $wpdb;
		$e    = Tables::events();
		$rows = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				"SELECT {$group} AS k, MAX(e.name) AS name, MAX(e.component) AS component, COUNT(*) AS n FROM {$e} e WHERE {$cond} AND e.occurred_at >= %s AND e.occurred_at < %s AND {$group} <> ''" . \UserFlo\Exclusions\Sql::events( 'e' ) . " GROUP BY {$group} ORDER BY n DESC LIMIT 15", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
				$from,
				$to
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array( 'key' => $r->k, 'name' => $r->name, 'component' => $r->component, 'count' => (int) $r->n );
		}
		return $out;
	}

	public static function sessions_by( $col, $from, $to, $limit = 10 ) {
		global $wpdb;
		$s    = Tables::sessions();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$col} AS k, COUNT(*) AS n FROM {$s} WHERE started_at >= %s AND started_at < %s AND {$col} <> ''" . ( in_array( $col, array( 'entry_url', 'exit_url' ), true ) ? \UserFlo\Exclusions\Sql::urls( '', $col ) : '' ) . " GROUP BY {$col} ORDER BY n DESC LIMIT %d", $from, $to, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array( 'key' => $r->k, 'count' => (int) $r->n );
		}
		return $out;
	}

	/** Most common page sequences (first 4 distinct consecutive pages) over the newest sessions in the period. */
	private static function journeys( $from, $to ) {
		global $wpdb;
		$s   = Tables::sessions();
		$pv  = Tables::pageviews();
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$s} WHERE started_at >= %s AND started_at < %s ORDER BY started_at DESC LIMIT %d", $from, $to, self::JOURNEY_SESSIONS ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$seqs = array();
		foreach ( array_chunk( array_map( 'intval', $ids ), 500 ) as $chunk ) {
			$rows = $wpdb->get_results( 'SELECT session_id, url FROM ' . $pv . ' p WHERE session_id IN (' . implode( ',', $chunk ) . ')' . \UserFlo\Exclusions\Sql::pageviews( 'p' ) . ' ORDER BY session_id, viewed_at, id' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
			foreach ( (array) $rows as $r ) {
				$sid = (int) $r->session_id;
				if ( ! isset( $seqs[ $sid ] ) ) {
					$seqs[ $sid ] = array();
				}
				$n = count( $seqs[ $sid ] );
				if ( $n < 4 && ( 0 === $n || $seqs[ $sid ][ $n - 1 ] !== $r->url ) ) {
					$seqs[ $sid ][] = $r->url;
				}
			}
		}
		$counts = array();
		$single = 0;
		foreach ( $seqs as $path ) {
			if ( count( $path ) < 2 ) {
				++$single;
				continue;
			}
			$k            = implode( "\n", $path );
			$counts[ $k ] = isset( $counts[ $k ] ) ? $counts[ $k ] + 1 : 1;
		}
		arsort( $counts );
		$top = array();
		foreach ( array_slice( $counts, 0, 10, true ) as $k => $n ) {
			$top[] = array( 'steps' => explode( "\n", $k ), 'count' => $n );
		}
		return array( 'sessions' => count( $seqs ), 'single_page' => $single, 'top' => $top );
	}
}
