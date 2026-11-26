<?php
namespace UserFlo\Insights;

use UserFlo\Core\Clock;
use UserFlo\Database\Reports;
use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Traffic calendar: compact aggregates per site-local day and per hour of one day.
 *
 * Metric definitions (identical to the Overview, so the numbers agree):
 *  - pageviews: rows in uf_pageviews by viewed_at.
 *  - sessions:  rows in uf_sessions by started_at (sessions are attributed to the day/hour they began).
 *  - visitors:  distinct visitor_id of the sessions that began in the bucket (the Overview "visitors" rule).
 *  - events:    rows in uf_events by occurred_at (clicks, forms, downloads, custom events).
 *
 * Daylight saving: days are grouped with an offset that is chosen per *instant* (the timezone's transitions become CASE
 * branches), so a transition inside the range, even at midnight, cannot shift rows into the wrong date. Hours are buckets
 * of 3600 s counted from the real start of the local day, so a DST day has 23 or 25 cells.
 */
final class Traffic {

	const METRICS      = array( 'pageviews', 'sessions', 'visitors', 'events' );
	const MAX_DAYS     = 42;
	const TOP_PAGES    = 10;

	/** @return \DateTimeZone */
	public static function tz() {
		return wp_timezone();
	}

	/**
	 * SQL expression giving the site-local DATE of a UTC datetime column, exact across DST transitions.
	 *
	 * @param string $col     Column name (trusted constant).
	 * @param int    $from_ts Range start (UTC).
	 * @param int    $to_ts   Range end (UTC).
	 */
	public static function local_date_sql( $col, $from_ts, $to_ts ) {
		$tz   = self::tz();
		$off0 = (int) $tz->getOffset( new \DateTime( '@' . (int) $from_ts ) );
		$case = '';
		$prev = $off0;
		$trs  = $tz->getTransitions( (int) $from_ts, (int) $to_ts );
		if ( is_array( $trs ) ) {
			array_shift( $trs ); // The first entry is the state at $from_ts itself.
			$branches = array();
			foreach ( $trs as $t ) {
				if ( (int) $t['offset'] === $prev ) {
					continue;
				}
				$branches[] = sprintf( "WHEN {$col} < '%s' THEN %d", gmdate( 'Y-m-d H:i:s', (int) $t['ts'] ), $prev );
				$prev       = (int) $t['offset'];
			}
			if ( $branches ) {
				$case = 'CASE ' . implode( ' ', $branches ) . ' ELSE ' . $prev . ' END';
			}
		}
		if ( '' === $case ) {
			$case = (string) $off0;
		}
		return "DATE(DATE_ADD({$col}, INTERVAL ({$case}) SECOND))";
	}

	/** @return array{0:int,1:int}|null UTC [start, end) of a site-local day. */
	public static function day_bounds( $ymd ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $ymd, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return null;
		}
		$start = new \DateTimeImmutable( $ymd . ' 00:00:00', self::tz() );
		$end   = $start->modify( '+1 day' )->setTime( 0, 0, 0 );
		return array( $start->getTimestamp(), $end->getTimestamp() );
	}

	/**
	 * Check for a calendar request: page views only, a whole calendar month, and only the current month plus the previous
	 * `calendar_months - 1` (the stored data stays; only what can be browsed is limited). UserFlo Pro answers the other metrics and windows.
	 *
	 * @return true|\WP_Error
	 */
	public static function lite_window( $from, $to, $metric ) {
		if ( 'pageviews' !== $metric ) {
			return \UserFlo\Edition::denied( 'calendar_metrics' ); // The other metrics are in UserFlo Pro.
		}
		$first = gmdate( 'Y-m-01', strtotime( $from . ' 12:00:00 UTC' ) );
		$last  = gmdate( 'Y-m-t', strtotime( $from . ' 12:00:00 UTC' ) );
		if ( $from !== $first || $to !== $last ) { // A week or any partial range is the Pro week view.
			return \UserFlo\Edition::denied( 'calendar_week' );
		}
		$months = \UserFlo\Edition::limit( 'calendar_months' );
		if ( $months ) {
			$now      = ( new \DateTimeImmutable( '@' . Clock::time() ) )->setTimezone( self::tz() );
			$earliest = $now->modify( 'first day of this month' )->modify( '-' . ( $months - 1 ) . ' months' )->format( 'Y-m-d' );
			if ( $from < $earliest || $from > $now->format( 'Y-m-t' ) ) {
				return \UserFlo\Edition::denied( 'calendar_history' );
			}
		}
		return true;
	}

	/** Coverage of one day: 'future', 'nodata' (before the first recorded visit), or 'data'; plus whether it is partial. */
	public static function coverage( $start, $end, $first_ts, $now ) {
		if ( $start > $now ) {
			return array( 'future', false );
		}
		if ( null === $first_ts || $end <= $first_ts ) {
			return array( 'nodata', false );
		}
		$partial = ( $first_ts > $start ) || ( $now < $end );
		return array( 'data', $partial );
	}

	public static function first_ts() {
		$first = Reports::first_data_at();
		return '' === $first ? null : strtotime( $first . ' UTC' );
	}

	/**
	 * Per-day page views for a whole calendar month. UserFlo Pro answers the other metrics and windows (see `userflo_traffic_calendar_response`).
	 *
	 * @return array|\WP_Error
	 */
	public static function calendar( $from, $to ) {
		global $wpdb;
		$a = self::day_bounds( $from );
		$b = self::day_bounds( $to );
		if ( ! $a || ! $b || $b[0] < $a[0] ) {
			return new \WP_Error( 'userflo_bad_range', __( 'Invalid date range.', 'userflo' ), array( 'status' => 400 ) );
		}
		$days_n = (int) round( ( $b[1] - $a[0] ) / DAY_IN_SECONDS );
		if ( $days_n > self::MAX_DAYS + 1 ) {
			return new \WP_Error( 'userflo_bad_range', __( 'The date range must not exceed 42 days.', 'userflo' ), array( 'status' => 400 ) );
		}
		$lo = Clock::mysql( $a[0] );
		$hi = Clock::mysql( $b[1] );

		$values = array();
		$d      = self::local_date_sql( 'viewed_at', $a[0], $b[1] );
		$skip   = \UserFlo\Exclusions\Sql::pageviews( 't' ); // Excluded pages are left out.
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT ' . $d . ' AS d, COUNT(*) AS n FROM ' . Tables::pageviews() . " t WHERE viewed_at >= %s AND viewed_at < %s{$skip} GROUP BY d", $lo, $hi ), OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		foreach ( $rows as $date => $r ) {
			$values[ $date ] = (int) $r->n;
		}

		$out   = array();
		$first = self::first_ts();
		$now   = Clock::time();
		$day   = new \DateTimeImmutable( $from . ' 00:00:00', self::tz() );
		for ( $i = 0; $i < $days_n; $i++ ) {
			$key                  = $day->format( 'Y-m-d' );
			$next                 = $day->modify( '+1 day' )->setTime( 0, 0, 0 );
			list( $state, $part ) = self::coverage( $day->getTimestamp(), $next->getTimestamp(), $first, $now );
			$out[ $key ]          = array( 'state' => $state, 'partial' => $part, 'pageviews' => 'data' === $state ? ( isset( $values[ $key ] ) ? $values[ $key ] : 0 ) : null );
			$day                  = $next;
		}
		return array(
			'metrics'  => array( 'pageviews' ),
			'from'     => $from,
			'to'       => $to,
			'timezone' => wp_timezone_string(),
			'today'    => wp_date( 'Y-m-d', $now ),
			'days'     => $out,
		);
	}
}
