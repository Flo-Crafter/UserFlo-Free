<?php
namespace UserFlo\Rest;

use UserFlo\Core\Clock;
use UserFlo\Database\Reports;
use UserFlo\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Read API for the admin dashboard (namespace userflo/v1). Every route requires the analytics capability;
 * responses are bounded (per_page <= 100) and never return full event histories.
 */
final class Routes {

	const NS = 'userflo/v1';

	/** What the Overview compares with: the period right before, the same dates a week / month / year earlier, or nothing. */
	const COMPARE = array( 'previous', 'week', 'month', 'year', 'none' );

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function can_view() {
		return current_user_can( Plugin::cap() );
	}

	public static function routes() {
		ExportRoutes::register();
		PrefsRoutes::register();
		StorageRoutes::register();
		LiveRoutes::register();
		InsightRoutes::register();
		GeoRoutes::register();
		GoalRoutes::register();
		ExclusionRoutes::register();
		AuditRoutes::register();
		ElementRoutes::register();
		CommerceRoutes::register();
		$base = array( 'permission_callback' => array( __CLASS__, 'can_view' ) );
		$id   = array( 'id' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ) );
		$page = array(
			'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 100000 ),
			'per_page' => array( 'type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100 ),
		);
		$range = array(
			'days' => array( 'type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 366 ),
			'from' => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
			'to'   => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
		);

		register_rest_route( self::NS, '/stats/overview', $base + array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'overview' ), 'args' => $range + array( 'compare' => array( 'type' => 'string', 'enum' => self::COMPARE, 'default' => 'previous' ) ) ) );
		$ymd = array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' );
		$txt = static function ( $max ) { return array( 'type' => 'string', 'default' => '', 'maxLength' => $max ); };
		register_rest_route( self::NS, '/visitors', $base + array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'visitors' ),
			'args'     => (array) apply_filters( 'userflo_visitors_rest_args', $page + array(
				'type'    => array( 'type' => 'string', 'enum' => array( 'all', 'anonymous', 'logged_in' ), 'default' => 'all' ),
				'search'  => $txt( 100 ),
				'orderby' => array( 'type' => 'string', 'default' => 'last_seen', 'enum' => array_keys( \UserFlo\Database\VisitorFilter::SORTS ) ),
				'order'   => array( 'type' => 'string', 'default' => 'desc', 'enum' => array( 'asc', 'desc' ) ),
			) ),
		) );
		register_rest_route( self::NS, '/visitors/(?P<id>\d+)', $base + array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'visitor' ), 'args' => $id ) );
		register_rest_route( self::NS, '/visitors/(?P<id>\d+)', $base + array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete_visitor' ), 'args' => $id ) );
		register_rest_route( self::NS, '/visitors/(?P<id>\d+)/sessions', $base + array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'visitor_sessions' ), 'args' => $id + $page ) );
		register_rest_route( self::NS, '/sessions/(?P<id>\d+)', $base + array(
			'methods'  => 'GET',
			'callback' => array( __CLASS__, 'session' ),
			'args'     => $id + array(
				'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 100000 ),
				'per_page' => array( 'type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 200 ),
			),
		) );
	}

	/**
	 * Resolve the requested range to UTC [from, to) bounds. UserFlo reports cover the last 7 or 30 days (the `overview_days` limit);
	 * UserFlo Pro adds custom date ranges and longer periods (`userflo_custom_range`).
	 */
	public static function range( \WP_REST_Request $r ) {
		if ( $r->get_param( 'from' ) ) {
			$custom = apply_filters( 'userflo_custom_range', null, $r );
			return null !== $custom ? $custom : \UserFlo\Edition::denied( 'range_extended' );
		}
		$max = \UserFlo\Edition::limit( 'overview_days' );
		if ( $max && (int) $r['days'] > $max ) {
			return \UserFlo\Edition::denied( 'range_extended' );
		}
		$now = Clock::time();
		return array( $now - (int) $r['days'] * DAY_IN_SECONDS, $now + 1 );
	}

	public static function overview( \WP_REST_Request $r ) {
		$range = self::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		list( $from_ts, $to_ts ) = $range;
		// The heavy aggregates are cached briefly; "active now" and recent sessions are always live.
		$ttl      = (int) apply_filters( 'userflo_overview_cache_ttl', 120 );
		$custom   = $r->get_param( 'from' ) ? true : false;
		$mode     = in_array( (string) $r['compare'], self::COMPARE, true ) ? (string) $r['compare'] : 'previous';
		$ckey     = 'userflo_ov_' . md5( ( $custom ? $r['from'] . $r['to'] : 'd' . (int) $r['days'] ) . '|' . $mode . '|' . get_locale() );
		$cached   = $ttl > 0 ? get_transient( $ckey ) : false;
		if ( is_array( $cached ) ) {
			$cached['kpis']['active_now'] = Reports::active_now();
			$cached['recent']             = self::recent();
			$cached['cached']             = true;
			return $cached;
		}
		$from = Clock::mysql( $from_ts );
		$to   = Clock::mysql( $to_ts );
		$o    = Reports::overview( $from, $to );

		$referrers = array();
		foreach ( Reports::referrers( $from, 10, $to ) as $source => $n ) {
			$referrers[] = array( 'source' => $source, 'sessions' => (int) $n );
		}
		$daily = array();
		foreach ( Reports::daily( $from_ts, $to_ts ) as $date => $d ) {
			$daily[] = array( 'date' => $date, 'sessions' => $d['sessions'], 'pageviews' => $d['pageviews'], 'visitors' => $d['visitors'], 'engaged' => $d['engaged'], 'single' => $d['single'] );
		}
		$channels = array();
		$mix      = Reports::traffic_mix( $from, $to );
		foreach ( $mix['channels'] as $key => $n ) {
			if ( $n > 0 ) {
				$channels[] = array( 'key' => $key, 'sessions' => (int) $n );
			}
		}
		usort( $channels, static function ( $a, $b ) { return $b['sessions'] - $a['sessions']; } );
		$geo_on = 'off' !== \UserFlo\Geo\Locator::mode();
		$pages = array();
		foreach ( Reports::top_pages( $from, 10, $to ) as $p ) {
			$pages[] = array( 'url' => $p['url'], 'title' => $p['title'], 'views' => (int) $p['views'], 'visitors' => (int) $p['visitors'], 'avg_engaged' => (int) round( $p['avg_engaged'] ) );
		}
		$recent = self::recent();
		$out    = array(
			'range'     => array( 'from' => Presenter::iso( $from ), 'to' => Presenter::iso( $to ) ),
			'kpis'      => array(
				'visitors'     => (int) $o['visitors'],
				'new_visitors' => (int) $o['new'],
				'sessions'     => (int) $o['sessions'],
				'logged_in'    => (int) $o['logged_in'],
				'pageviews'    => (int) $o['pageviews'],
				'avg_session'  => (int) round( $o['avg_duration'] ),
				'engaged'      => (int) $o['engaged'],
				'active_now'   => (int) $o['active_now'],
				'returning'    => max( 0, (int) $o['visitors'] - (int) $o['new'] ),
				'single_page'  => (int) $o['single_page'],
			),
			'previous'  => 'none' === $mode ? null : self::previous( $from_ts, $to_ts, $mode, true ),
			'compare'   => $mode,
			'daily'     => $daily,
			'pages'     => $pages,
			'referrers' => $referrers,
			'channels'  => $channels,
			'devices'   => $mix['devices'],
			'geo'       => $geo_on,
			'countries' => $geo_on ? Reports::countries( $from, $to ) : array(),
			'recent'    => $recent,
			'cached'    => false,
		);
		if ( $ttl > 0 ) {
			set_transient( $ckey, $out, $ttl );
		}
		return $out;
	}

	/**
	 * Start of the comparison period for a mode. The comparison period always has the same length as the selected one, so totals and
	 * the day-by-day overlay line up: "previous" is the period right before; "week", "month" and "year" are the same dates shifted back
	 * (in the site timezone, so a month is a calendar month).
	 */
	public static function compare_from( $from_ts, $to_ts, $mode ) {
		$len = $to_ts - $from_ts;
		if ( 'week' === $mode ) {
			return $from_ts - 7 * DAY_IN_SECONDS;
		}
		if ( 'month' === $mode || 'year' === $mode ) {
			$d = ( new \DateTime( '@' . $from_ts ) )->setTimezone( wp_timezone() );
			$d->modify( 'month' === $mode ? '-1 month' : '-1 year' );
			return $d->getTimestamp();
		}
		return $from_ts - $len;
	}

	/**
	 * The same KPIs for the comparison period, or null when it cannot be compared honestly: it is not fully covered by recorded data
	 * (tracking started later, or retention already removed it), or it overlaps the selected period by more than three days.
	 *
	 * @param bool $with_daily Also return the comparison period's per-day series (the Overview chart overlay).
	 */
	public static function previous( $from_ts, $to_ts, $mode = 'previous', $with_daily = false ) {
		$len       = $to_ts - $from_ts;
		$prev_from = self::compare_from( $from_ts, $to_ts, $mode );
		$prev_to   = $prev_from + $len;
		$first     = Reports::first_data_at();
		if ( '' === $first || strtotime( $first . ' UTC' ) > $prev_from ) {
			return null;
		}
		if ( $prev_to > $from_ts + 3 * DAY_IN_SECONDS ) {
			return null; // Shifting by a week, month or year when the period is longer than the shift would count the same days twice.
		}
		$prev_to = min( $prev_to, $from_ts );
		$o       = Reports::overview( Clock::mysql( $prev_from ), Clock::mysql( $prev_to ) );
		$out     = array(
			'from'         => Presenter::iso( Clock::mysql( $prev_from ) ),
			'to'           => Presenter::iso( Clock::mysql( $prev_to ) ),
			'mode'         => $mode,
			'visitors'     => (int) $o['visitors'],
			'new_visitors' => (int) $o['new'],
			'returning'    => max( 0, (int) $o['visitors'] - (int) $o['new'] ),
			'sessions'     => (int) $o['sessions'],
			'pageviews'    => (int) $o['pageviews'],
			'avg_session'  => (int) round( $o['avg_duration'] ),
			'engaged'      => (int) $o['engaged'],
			'single_page'  => (int) $o['single_page'],
		);
		if ( $with_daily ) {
			$out['daily'] = array();
			foreach ( Reports::daily( $prev_from, $prev_to ) as $date => $d ) {
				$out['daily'][] = array( 'date' => $date, 'sessions' => $d['sessions'], 'pageviews' => $d['pageviews'], 'visitors' => $d['visitors'], 'engaged' => $d['engaged'], 'single' => $d['single'] );
			}
		}
		return $out;
	}

	private static function recent() {
		$recent = array();
		foreach ( Reports::recent_sessions( 10 ) as $s ) {
			$s->user_email = null;
			$recent[]      = array_merge( Presenter::session( $s ), array( 'label' => Presenter::visitor_label( $s ), 'logged_in' => ! empty( $s->user_id ) ) );
		}
		return $recent;
	}

	public static function visitors( \WP_REST_Request $r ) {
		$f = array();
		foreach ( array_keys( \UserFlo\Database\VisitorFilter::defaults() ) as $k ) {
			if ( null !== $r->get_param( $k ) ) {
				$f[ $k ] = is_string( $r[ $k ] ) ? trim( $r[ $k ] ) : $r[ $k ];
			}
		}
		$f = apply_filters( 'userflo_visitors_filters', $f, $r ); // UserFlo Pro turns its advanced filters (dates, pages, events ...) into query values.
		if ( is_wp_error( $f ) ) {
			return $f;
		}
		$res = Reports::visitors( $f + array( 'page' => $r['page'], 'per_page' => $r['per_page'] ) );
		return self::paged( array_map( array( Presenter::class, 'visitor' ), $res['rows'] ), $res['total'], $r );
	}

	public static function visitor( \WP_REST_Request $r ) {
		$v = Reports::visitor( (int) $r['id'] );
		if ( ! $v ) {
			return self::not_found();
		}
		$out           = Presenter::visitor( $v, true );
		$out['return'] = Reports::return_stats( (int) $v->id );
		return $out;
	}

	public static function delete_visitor( \WP_REST_Request $r ) {
		if ( ! Reports::visitor( (int) $r['id'] ) ) {
			return self::not_found();
		}
		Reports::delete_visitor( (int) $r['id'] );
		return array( 'deleted' => true );
	}

	public static function visitor_sessions( \WP_REST_Request $r ) {
		if ( ! Reports::visitor( (int) $r['id'] ) ) {
			return self::not_found();
		}
		$res = Reports::visitor_sessions( (int) $r['id'], (int) $r['page'], (int) $r['per_page'] );
		return self::paged( array_map( array( Presenter::class, 'session' ), $res['rows'] ), $res['total'], $r, $res );
	}

	public static function session( \WP_REST_Request $r ) {
		$s = Reports::session( (int) $r['id'] );
		if ( ! $s ) {
			return self::not_found();
		}
		if ( ! \UserFlo\Edition::can( 'history_extended' ) && ! Reports::session_in_recent_window( $s ) ) {
			return \UserFlo\Edition::denied( 'history_extended' ); // Older sessions cannot be opened by id either.
		}
		$j     = Reports::journey( (int) $s->id, (int) $r['page'], (int) $r['per_page'] );
		$items = array();
		foreach ( $j['items'] as $it ) {
			$base = array( 'kind' => $it['kind'], 'id' => (int) $it['id'], 'at' => Presenter::iso( $it['at'] ) );
			if ( 'pageview' === $it['kind'] ) {
				$items[] = $base + array( 'url' => $it['url'], 'title' => $it['title'], 'referrer' => $it['referrer'], 'engaged' => (int) $it['engaged_seconds'], 'duration' => (int) $it['duration_seconds'], 'scroll' => (int) $it['max_scroll'] );
			} else {
				$items[] = $base + array( 'type' => $it['type'], 'name' => $it['name'], 'target' => $it['target'], 'value' => $it['value'], 'component' => $it['component'], 'pv' => (int) $it['pv'] );
			}
		}
		$extra = (array) apply_filters( 'userflo_session_extra', array(), $s ); // The visual journey's extras; the timeline needs neither.
		return array( 'session' => Presenter::session( $s ) ) + $extra + array( 'items' => $items, 'total' => $j['total'], 'page' => (int) $r['page'], 'per_page' => (int) $r['per_page'] ) + self::limit_info( $j );
	}

	/** Display-limit facts for the UI: the real count, the cap that applied (0 = none) and whether the list was cut. */
	private static function limit_info( array $res ) {
		return array(
			'limit'      => isset( $res['cap'] ) ? (int) $res['cap'] : 0,
			'real_total' => isset( $res['real_total'] ) ? (int) $res['real_total'] : (int) $res['total'],
			'limited'    => ! empty( $res['cap'] ) && isset( $res['real_total'] ) && $res['real_total'] > $res['cap'],
		);
	}

	private static function paged( array $items, $total, \WP_REST_Request $r, array $info = array() ) {
		$res = new \WP_REST_Response( array( 'items' => $items, 'total' => (int) $total, 'page' => (int) $r['page'], 'per_page' => (int) $r['per_page'] ) + ( $info ? self::limit_info( $info ) : array() ) );
		$res->header( 'X-WP-Total', (string) $total );
		return $res;
	}

	private static function not_found() {
		return new \WP_Error( 'userflo_not_found', __( 'Not found.', 'userflo' ), array( 'status' => 404 ) );
	}
}
