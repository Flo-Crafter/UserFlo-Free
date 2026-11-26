<?php
namespace UserFlo\Geo;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;
use UserFlo\Rest\Presenter;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/** Visitor geography aggregates for the map (visitors = distinct visitors with a session in the period). */
final class Stats {

	public static function summary( $from_ts, $to_ts ) {
		global $wpdb;
		$s    = Tables::sessions();
		$from = Clock::mysql( $from_ts );
		$to   = Clock::mysql( $to_ts );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT country, COUNT(DISTINCT visitor_id) AS v, COUNT(*) AS s FROM {$s} WHERE started_at >= %s AND started_at < %s AND country IS NOT NULL GROUP BY country ORDER BY v DESC, country ASC", $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$countries = array();
		$conts     = array();
		foreach ( (array) $rows as $r ) {
			$d = Countries::get( $r->country );
			if ( ! $d ) {
				continue;
			}
			$countries[] = array( 'code' => $r->country, 'name' => Countries::name( $r->country ), 'continent' => $d[1], 'visitors' => (int) $r->v, 'sessions' => (int) $r->s, 'lat' => $d[2], 'lon' => $d[3] );
			if ( ! isset( $conts[ $d[1] ] ) ) {
				$conts[ $d[1] ] = array( 'visitors' => 0, 'sessions' => 0, 'wlat' => 0, 'wlon' => 0 );
			}
			$conts[ $d[1] ]['visitors'] += (int) $r->v;
			$conts[ $d[1] ]['sessions'] += (int) $r->s;
			$conts[ $d[1] ]['wlat']     += $d[2] * (int) $r->v;
			$conts[ $d[1] ]['wlon']     += $d[3] * (int) $r->v;
		}
		$names      = Countries::continents();
		$continents = array();
		foreach ( $conts as $code => $c ) {
			$continents[] = array( 'code' => $code, 'name' => $names[ $code ], 'visitors' => $c['visitors'], 'sessions' => $c['sessions'], 'lat' => round( $c['wlat'] / max( 1, $c['visitors'] ), 2 ), 'lon' => round( $c['wlon'] / max( 1, $c['visitors'] ), 2 ) );
		}
		usort( $continents, static function ( $a, $b ) { return $b['visitors'] - $a['visitors']; } );
		$cities = (array) apply_filters( 'userflo_geo_cities', array(), $from, $to ); // City level is added by UserFlo Pro.
		$unknown = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT visitor_id) FROM {$s} WHERE started_at >= %s AND started_at < %s AND country IS NULL", $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$total   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT visitor_id) FROM {$s} WHERE started_at >= %s AND started_at < %s", $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		return array(
			'mode'       => \UserFlo\Geo\Locator::mode(),
			'database'   => '' !== Locator::database_path(),
			'total'      => $total,
			'unknown'    => $unknown,
			'continents' => $continents,
			'countries'  => $countries,
			'cities'     => $cities,
		);
	}

	/** @return array|\WP_Error Top pages, logged-in users and time patterns for one place. */
	public static function details( $level, $key, $from_ts, $to_ts ) {
		global $wpdb;
		$s = Tables::sessions();
		$p = Tables::pageviews();
		if ( 'continent' === $level ) {
			$codes = Countries::in_continent( $key );
			if ( ! $codes ) {
				return new \WP_Error( 'userflo_geo_unknown', __( 'Unknown place.', 'userflo' ), array( 'status' => 404 ) );
			}
			$cond = 's.country IN (' . implode( ',', array_fill( 0, count( $codes ), '%s' ) ) . ')';
			$args = $codes;
			$name = Countries::continents()[ $key ];
		} elseif ( 'country' === $level ) {
			if ( ! Countries::get( $key ) ) {
				return new \WP_Error( 'userflo_geo_unknown', __( 'Unknown place.', 'userflo' ), array( 'status' => 404 ) );
			}
			$cond = 's.country = %s';
			$args = array( strtoupper( $key ) );
			$name = Countries::name( $key );
		} else { // A level an add-on provides (UserFlo Pro: a city, "GB|London").
			$place = apply_filters( 'userflo_geo_place', null, $level, $key );
			if ( ! is_array( $place ) ) {
				return is_wp_error( $place ) ? $place : new \WP_Error( 'userflo_geo_unknown', __( 'Unknown place.', 'userflo' ), array( 'status' => 404 ) );
			}
			list( $cond, $args, $name ) = $place;
		}
		$from = Clock::mysql( $from_ts );
		$to   = Clock::mysql( $to_ts );
		$off  = (int) wp_timezone()->getOffset( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) );
		$rng  = array( $from, $to );

		$base   = "FROM {$s} s WHERE s.started_at >= %s AND s.started_at < %s AND {$cond}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$vis    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT s.visitor_id) {$base}", array_merge( $rng, $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own tables: names are internal constants, values are prepared.
		$sess   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$base}", array_merge( $rng, $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own tables: names are internal constants, values are prepared.
		$pages  = $wpdb->get_results( $wpdb->prepare( "SELECT pv.url, COUNT(*) AS n FROM {$p} pv JOIN {$s} s ON s.id = pv.session_id WHERE pv.viewed_at >= %s AND pv.viewed_at < %s AND {$cond}" . \UserFlo\Exclusions\Sql::pageviews( 'pv' ) . " GROUP BY pv.url ORDER BY n DESC LIMIT 6", array_merge( $rng, $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Own tables: names are internal constants, values are prepared.
		$users  = $wpdb->get_results( $wpdb->prepare( "SELECT s.user_id, MIN(s.visitor_id) AS vid, COUNT(*) AS n, u.user_login, u.display_name FROM {$s} s LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id WHERE s.started_at >= %s AND s.started_at < %s AND s.user_id IS NOT NULL AND {$cond} GROUP BY s.user_id ORDER BY n DESC LIMIT 3", array_merge( $rng, $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Own tables: names are internal constants, values are prepared.
		$hours  = array_fill( 0, 24, 0 );
		$wd     = array_fill( 0, 7, 0 );
		$hr     = $wpdb->get_results( $wpdb->prepare( "SELECT HOUR(DATE_ADD(s.started_at, INTERVAL {$off} SECOND)) AS h, COUNT(*) AS n {$base} GROUP BY h", array_merge( $rng, $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own tables: names are internal constants, values are prepared.
		foreach ( (array) $hr as $r ) {
			$hours[ (int) $r->h ] = (int) $r->n;
		}
		$wr = $wpdb->get_results( $wpdb->prepare( "SELECT WEEKDAY(DATE_ADD(s.started_at, INTERVAL {$off} SECOND)) AS d, COUNT(*) AS n {$base} GROUP BY d", array_merge( $rng, $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own tables: names are internal constants, values are prepared.
		foreach ( (array) $wr as $r ) {
			$wd[ (int) $r->d ] = (int) $r->n; // 0 = Monday.
		}
		$days  = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(DATE_ADD(s.started_at, INTERVAL {$off} SECOND)) AS d, COUNT(*) AS n {$base} GROUP BY d ORDER BY n DESC, d DESC LIMIT 5", array_merge( $rng, $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own tables: names are internal constants, values are prepared.
		$weeks = $wpdb->get_results( $wpdb->prepare( "SELECT YEARWEEK(DATE_ADD(s.started_at, INTERVAL {$off} SECOND), 3) AS w, MIN(DATE(DATE_ADD(s.started_at, INTERVAL {$off} SECOND))) AS first, COUNT(*) AS n {$base} GROUP BY w ORDER BY n DESC, w DESC LIMIT 5", array_merge( $rng, $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own tables: names are internal constants, values are prepared.

		$top_hours = $hours;
		arsort( $top_hours );
		$top_hours = array_slice( array_keys( array_filter( $top_hours ) ), 0, 3 );
		$show      = Settings::get( 'show_identity' );
		return array(
			'level'    => $level,
			'key'      => $key,
			'name'     => $name,
			'visitors' => $vis,
			'sessions' => $sess,
			'pages'    => array_map( static function ( $r ) { return array( 'url' => $r->url, 'views' => (int) $r->n ); }, (array) $pages ),
			'users'    => array_map(
				static function ( $r ) use ( $show ) {
					return array( 'visitor_id' => (int) $r->vid, 'label' => ( $show && $r->user_login ) ? ( $r->display_name ? $r->display_name : $r->user_login ) : sprintf( /* translators: %d: user id */ __( 'Registered user #%d', 'userflo' ), (int) $r->user_id ), 'sessions' => (int) $r->n );
				},
				(array) $users
			),
			'hours'     => $hours,
			'weekdays'  => $wd,
			'top_hours' => $top_hours,
			'top_days'  => array_map( static function ( $r ) { return array( 'date' => $r->d, 'sessions' => (int) $r->n ); }, (array) $days ),
			'top_weeks' => array_map( static function ( $r ) { return array( 'week' => substr( (string) $r->w, 0, 4 ) . '-W' . substr( (string) $r->w, 4 ), 'starts' => $r->first, 'sessions' => (int) $r->n ); }, (array) $weeks ),
		);
	}
}
