<?php
namespace UserFlo\Insights;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The journey summary: where sessions begin, the ordered paths they follow and where they end.
 *
 * Definitions (documented for administrators in docs/JOURNEY-ANALYTICS.md):
 *  - Population: sessions that STARTED in the period (the convention used everywhere in UserFlo) and have at least one recorded page view.
 *  - A session's path is its page views in time order, with consecutive repeats of the same page collapsed (a reload is not a step).
 *  - Entrance = first recorded page, exit = last recorded page; a one-page session is both.
 *  - Counts are SESSIONS. Sessions that began before the retention cutoff are left out: their first page views may already be deleted.
 *
 * Everything is calculated here, in bounded chunks, from the existing tables; the browser receives aggregates only. UserFlo Pro replaces
 * this with the full analysis (filters, exploring by selection, the flow diagram, comparison with the previous period) through the
 * `userflo_journeys_response` filter.
 */
final class Journeys {

	const STEPS        = 5;      // Pages kept at the start of a path.
	const MAX_SESSIONS = 50000;  // Sessions analysed per request; a larger period uses the most recent ones and says so.
	const CHUNK        = 1000;
	const END          = '~end';  // Pseudo page: the session's last recorded page was the one before.
	const MORE         = '~more'; // Pseudo page: the session continued beyond the last step kept.

	/** The options of the summary. Every key is present so a screen can read them without checks. */
	public static function options( array $in = array() ) {
		return array(
			'device'  => '', 'country' => '', 'user' => '', 'group' => 'page', 'entry' => '', 'exit' => '', 'step' => 0, 'at' => '', 'then' => '',
			'paths'   => isset( $in['paths'] ) ? max( 10, min( 100, (int) $in['paths'] ) ) : 25,
			'compare' => false,
		);
	}

	/**
	 * @return array The summary for the period, or an array with 'state' => 'nodata' when nothing was recorded.
	 */
	public static function build( $from_ts, $to_ts, array $opts = array() ) {
		$cap  = \UserFlo\Edition::limit( 'journey_rows' );
		$rows = $cap > 0 ? $cap : 100; // The same most-common-paths list for everyone; 100 is the most it ever lists.
		$o    = array_merge( self::options( $opts ), array( 'paths' => $rows ) );
		$now  = Clock::time();
		$a    = self::collect( $from_ts, $to_ts );

		$first = Traffic::first_ts();
		list( $state, $partial ) = Traffic::coverage( $from_ts, $to_ts, $first, $now );
		$out = array(
			'state'   => 'nodata' === $state || 0 === $a['base'] ? 'nodata' : 'data',
			'partial' => $partial,
			'period'  => array( 'from' => Clock::mysql( $from_ts ), 'to' => Clock::mysql( $to_ts ) ),
			'options' => $o,
			'basic'   => true, // The summary: top rows only, no flow, no exploring.
			'rows'    => $rows,
			'summary' => array(
				'sessions'            => $a['base'],
				'sessions_in_period'  => $a['in_period'],
				'analysed'            => $a['analysed'],
				'sampled'             => $a['sampled'],
				'excluded_retention'  => $a['excluded'],
				'selected'            => $a['base'],
				'single_page'         => $a['single'],
			),
		);
		$flow = array( 'steps' => self::STEPS, 'columns' => array(), 'links' => array(), 'sessions' => $a['base'] );
		if ( 'nodata' === $out['state'] ) {
			return $out + array( 'entrances' => array( 'total' => 0, 'items' => array() ), 'exits' => array( 'total' => 0, 'items' => array() ), 'flow' => array( 'columns' => array(), 'links' => array() ), 'paths' => array(), 'transitions' => array(), 'previous' => null );
		}
		$titles_for = array();
		$out       += array(
			'entrances'   => self::entrances( $a, $titles_for, $rows ),
			'exits'       => self::exits( $a, $titles_for, $rows ),
			'flow'        => $flow,
			'transitions' => array(),
			'paths'       => self::paths( $a, $rows ),
			'facets'      => null,
			'previous'    => null,
		);
		$out['titles'] = self::titles( array_keys( $titles_for ), $from_ts, $to_ts );
		return $out;
	}

	/** One pass over the period's sessions (newest first, in bounded chunks) that fills the counters. */
	private static function collect( $from_ts, $to_ts ) {
		global $wpdb;
		$st   = Tables::sessions();
		$pv   = Tables::pageviews();
		$from = Clock::mysql( $from_ts );
		$to   = Clock::mysql( $to_ts );
		$cap  = max( 1, (int) apply_filters( 'userflo_journey_max_sessions', self::MAX_SESSIONS ) );

		$where = 'started_at >= %s AND started_at < %s';
		$args  = array( $from, $to );
		$days  = (int) Settings::get( 'retention_days' );
		$cut   = $days > 0 ? Clock::mysql( Clock::time() - $days * DAY_IN_SECONDS ) : '';
		$excluded = 0;
		if ( $cut && $from < $cut ) {
			$excluded = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$st} WHERE started_at >= %s AND started_at < %s AND started_at < %s", $from, $to, $cut ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$where   .= ' AND started_at >= %s';
			$args[]   = $cut;
		}
		$in_period = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$st} WHERE {$where}", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own tables: names are internal constants, values are prepared.

		$a = array(
			'in_period' => $in_period, 'analysed' => 0, 'sampled' => false, 'excluded' => $excluded, 'base' => 0, 'single' => 0,
			'entr' => array(), 'entr_next' => array(), 'exit' => array(), 'exit_views' => array(), 'paths' => array(),
		);
		$cursor_at = null;
		$cursor_id = 0;
		while ( $a['analysed'] < $cap ) {
			$w = $where;
			$q = $args;
			if ( null !== $cursor_at ) {
				$w .= ' AND (started_at < %s OR (started_at = %s AND id < %d))';
				array_push( $q, $cursor_at, $cursor_at, $cursor_id );
			}
			$q[]  = min( self::CHUNK, $cap - $a['analysed'] );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, started_at FROM {$st} WHERE {$w} ORDER BY started_at DESC, id DESC LIMIT %d", $q ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			if ( ! $rows ) {
				break;
			}
			$last      = end( $rows );
			$cursor_at = $last->started_at;
			$cursor_id = (int) $last->id;
			$ids       = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
			$a['analysed'] += count( $ids );
			$views = $wpdb->get_results( 'SELECT session_id, url FROM ' . $pv . ' p WHERE session_id IN (' . implode( ',', $ids ) . ')' . \UserFlo\Exclusions\Sql::pageviews( 'p' ) . ' ORDER BY session_id, viewed_at, id' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
			$seq   = array();
			$raw   = array();
			foreach ( (array) $views as $r ) {
				$sid = (int) $r->session_id;
				$k   = (string) $r->url;
				if ( ! isset( $seq[ $sid ] ) ) {
					$seq[ $sid ] = array();
					$raw[ $sid ] = array();
				}
				$raw[ $sid ][] = $k;
				if ( end( $seq[ $sid ] ) !== $k ) {
					$seq[ $sid ][] = $k;
				}
			}
			unset( $views );
			foreach ( $seq as $sid => $path ) {
				self::consume( $a, $path, $raw[ $sid ] );
			}
			if ( count( $rows ) < self::CHUNK ) {
				break;
			}
		}
		$a['sampled'] = $in_period > $a['analysed'];
		return $a;
	}

	/** Adds one session's path to the counters. */
	private static function consume( array &$a, array $path, array $raw ) {
		$n    = count( $path );
		$last = $path[ $n - 1 ];
		++$a['base'];
		$a['entr'][ $path[0] ] = ( isset( $a['entr'][ $path[0] ] ) ? $a['entr'][ $path[0] ] : 0 ) + 1;
		$next = $n > 1 ? $path[1] : self::END;
		$a['entr_next'][ $path[0] ][ $next ] = ( isset( $a['entr_next'][ $path[0] ][ $next ] ) ? $a['entr_next'][ $path[0] ][ $next ] : 0 ) + 1;
		$a['exit'][ $last ] = ( isset( $a['exit'][ $last ] ) ? $a['exit'][ $last ] : 0 ) + 1;
		foreach ( $raw as $p ) {
			$a['exit_views'][ $p ] = ( isset( $a['exit_views'][ $p ] ) ? $a['exit_views'][ $p ] : 0 ) + 1;
		}
		if ( 1 === $n ) {
			++$a['single'];
		}
		$key = implode( "\n", array_slice( $path, 0, self::STEPS ) ) . ( $n > self::STEPS ? "\n" . self::MORE : '' );
		$a['paths'][ $key ] = ( isset( $a['paths'][ $key ] ) ? $a['paths'][ $key ] : 0 ) + 1;
	}

	private static function entrances( array $a, array &$titles, $rows ) {
		arsort( $a['entr'] );
		$items = array();
		foreach ( array_slice( $a['entr'], 0, $rows, true ) as $page => $n ) {
			$titles[ $page ] = true;
			$next = $a['entr_next'][ $page ];
			arsort( $next );
			$steps = array();
			foreach ( array_slice( $next, 0, 3, true ) as $to => $c ) {
				$steps[] = array( 'page' => self::END === $to ? null : (string) $to, 'count' => (int) $c, 'share' => round( $c / $n, 4 ) );
				if ( self::END !== $to ) {
					$titles[ $to ] = true;
				}
			}
			$items[] = array( 'page' => (string) $page, 'count' => (int) $n, 'share' => $a['base'] ? round( $n / $a['base'], 4 ) : 0, 'next' => $steps );
		}
		return array( 'total' => $a['base'], 'distinct' => count( $a['entr'] ), 'items' => $items );
	}

	private static function exits( array $a, array &$titles, $rows ) {
		arsort( $a['exit'] );
		$items = array();
		foreach ( array_slice( $a['exit'], 0, $rows, true ) as $page => $n ) {
			$titles[ $page ] = true;
			$views   = isset( $a['exit_views'][ $page ] ) ? (int) $a['exit_views'][ $page ] : 0;
			$items[] = array( 'page' => (string) $page, 'count' => (int) $n, 'share' => $a['base'] ? round( $n / $a['base'], 4 ) : 0, 'views' => $views, 'rate' => $views ? round( $n / $views, 4 ) : null, 'lead' => array() );
		}
		return array( 'total' => $a['base'], 'distinct' => count( $a['exit'] ), 'items' => $items );
	}

	private static function paths( array $a, $limit ) {
		arsort( $a['paths'] );
		$out = array();
		foreach ( array_slice( $a['paths'], 0, $limit, true ) as $key => $n ) {
			$steps    = explode( "\n", (string) $key );
			$complete = true;
			if ( self::MORE === end( $steps ) ) {
				array_pop( $steps );
				$complete = false;
			}
			$out[] = array( 'steps' => $steps, 'count' => (int) $n, 'share' => $a['base'] ? round( $n / $a['base'], 4 ) : 0, 'complete' => $complete );
		}
		return array( 'items' => $out, 'distinct' => count( $a['paths'] ), 'total' => $a['base'] );
	}

	/** Most common page title per address for the pages that are shown (one bounded query). */
	private static function titles( array $urls, $from_ts, $to_ts ) {
		global $wpdb;
		$urls = array_slice( array_values( array_filter( $urls, static function ( $u ) { return '' !== $u && '~' !== $u[0]; } ) ), 0, 60 );
		if ( ! $urls ) {
			return array();
		}
		$ph   = implode( ',', array_fill( 0, count( $urls ), '%s' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT url, title, COUNT(*) AS n FROM ' . Tables::pageviews() . " WHERE url IN ({$ph}) AND viewed_at >= %s AND viewed_at < %s AND title <> '' GROUP BY url, title ORDER BY n DESC LIMIT 400", array_merge( $urls, array( Clock::mysql( $from_ts ), Clock::mysql( $to_ts + DAY_IN_SECONDS ) ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Own tables: names are internal constants, values are prepared.
		$out  = array();
		foreach ( (array) $rows as $r ) {
			if ( ! isset( $out[ $r->url ] ) ) {
				$out[ $r->url ] = \UserFlo\Core\Text::substr( $r->title, 120 );
			}
		}
		return $out;
	}
}
