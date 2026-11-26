<?php
namespace UserFlo\Rest;

use UserFlo\Core\Clock;
use UserFlo\Database\Reports;
use UserFlo\Modules;
use UserFlo\Tracking\Validator;

defined( 'ABSPATH' ) || exit;

/** Live view and per-page visit history (analytics capability required). */
final class LiveRoutes {

	public static function register() {
		$base = array( 'permission_callback' => array( Routes::class, 'can_view' ), 'methods' => 'GET' );
		if ( Modules::on( 'live' ) ) {
		register_rest_route( Routes::NS, '/live', $base + array(
			'callback' => array( __CLASS__, 'live' ),
			'args'     => array(
				'pv' => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0 ),
				'ev' => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0 ),
			),
		) );
		}
		if ( Modules::on( 'live' ) ) {
			register_rest_route( Routes::NS, '/history', $base + array(
				'callback' => array( __CLASS__, 'history' ),
				'args'     => array(
					'scope' => array( 'type' => 'string', 'enum' => array( 'all', 'page' ), 'default' => 'all' ),
					'url'   => array( 'type' => 'string', 'default' => '', 'maxLength' => 500 ),
					'limit' => array( 'type' => 'integer', 'default' => 30, 'minimum' => 5, 'maximum' => 60 ),
				),
			) );
		}
		if ( ! Modules::on( 'pages' ) ) {
			return;
		}
		$range = array(
			'days' => array( 'type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 366 ),
			'from' => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
			'to'   => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
		);
		register_rest_route( Routes::NS, '/pages', $base + array(
			'callback' => array( __CLASS__, 'pages' ),
			'args'     => $range + array( 'search' => array( 'type' => 'string', 'default' => '', 'maxLength' => 100 ), 'bots' => array( 'type' => 'boolean', 'default' => false ) ),
		) );
		register_rest_route( Routes::NS, '/pages/visits', $base + array(
			'callback' => array( __CLASS__, 'visits' ),
			'args'     => $range + array(
				'url'      => array( 'type' => 'string', 'required' => true, 'maxLength' => 500 ),
				'type'     => array( 'type' => 'string', 'enum' => array( 'all', 'anonymous', 'logged_in' ), 'default' => 'all' ),
				'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 100000 ),
				'per_page' => array( 'type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100 ),
			),
		) );
	}

	private static function who( $row ) {
		return array(
			'geo'        => Presenter::geo( isset( $row->country ) ? $row->country : '', isset( $row->city ) ? $row->city : '', isset( $row->lat ) ? $row->lat : null, isset( $row->lon ) ? $row->lon : null ),
			'visitor_id' => (int) $row->visitor_id,
			'session_id' => (int) $row->session_id,
			'label'      => Presenter::visitor_label( $row ),
			'logged_in'  => ! empty( $row->user_id ),
		);
	}

	public static function live( \WP_REST_Request $r ) {
		$sessions = array();
		foreach ( Reports::active_sessions( 50 ) as $s ) {
			$sessions[] = Presenter::session( $s ) + array( 'label' => Presenter::visitor_label( $s ), 'logged_in' => ! empty( $s->user_id ) );
		}
		$items  = array();
		$pv_cur = (int) $r['pv'];
		$ev_cur = (int) $r['ev'];
		$max_pv = $pv_cur;
		$max_ev = $ev_cur;
		$first  = 0 === $pv_cur && 0 === $ev_cur;
		foreach ( Reports::live_pageviews( $pv_cur, $first ? 20 : 50 ) as $p ) {
			$max_pv  = max( $max_pv, (int) $p->id );
			$items[] = array( 'key' => 'p' . $p->id, 'kind' => 'pageview', 'at' => Presenter::iso( $p->at ), 'url' => $p->url, 'title' => $p->title, 'type' => '', 'name' => '', 'target' => '' ) + self::who( $p );
		}
		foreach ( Reports::live_events( $ev_cur, $first ? 20 : 50 ) as $e ) {
			$max_ev  = max( $max_ev, (int) $e->id );
			$items[] = array( 'key' => 'e' . $e->id, 'kind' => 'event', 'at' => Presenter::iso( $e->at ), 'url' => (string) $e->url, 'title' => '', 'type' => $e->type, 'name' => $e->name, 'target' => $e->target ) + self::who( $e );
		}
		usort( $items, static function ( $a, $b ) {
			return strcmp( $b['at'], $a['at'] ) ?: strcmp( $b['key'], $a['key'] );
		} );
		$resp = rest_ensure_response(
			array(
				'active'    => $sessions,
				'items'     => array_slice( $items, 0, 60 ),
				'cursor'    => array( 'pv' => $max_pv, 'ev' => $max_ev ),
				'last_hour' => Reports::pageviews_since( Clock::time() - HOUR_IN_SECONDS ),
				'server'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
			)
		);
		$resp->header( 'Cache-Control', 'no-store' );
		return $resp;
	}

	/**
	 * The latest visits and interactions of the last 7 days, for the toolbar History menu: across the site, or on one page. Names follow the
	 * same "show usernames" setting as the rest of the dashboard; no address, e-mail or form content is ever returned.
	 */
	/** The latest visits and interactions of the last 7 days (site-wide, or for one page path) and their totals. Used by the History route and the toolbar menu. */
	public static function history_data( $url, $limit ) {
		$since = Clock::mysql( Clock::time() - 7 * DAY_IN_SECONDS );
		$items = array();
		foreach ( Reports::history_pageviews( $since, $url, $limit ) as $p ) {
			$items[] = array( 'key' => 'p' . $p->id, 'kind' => 'pageview', 'at' => Presenter::iso( $p->at ), 'url' => $p->url, 'title' => $p->title, 'type' => '', 'name' => '', 'target' => '' ) + self::who( $p );
		}
		foreach ( Reports::history_events( $since, $url, $limit ) as $e ) {
			$items[] = array( 'key' => 'e' . $e->id, 'kind' => 'event', 'at' => Presenter::iso( $e->at ), 'url' => (string) $e->url, 'title' => '', 'type' => $e->type, 'name' => $e->name, 'target' => $e->target ) + self::who( $e );
		}
		usort( $items, static function ( $a, $b ) {
			return strcmp( $b['at'], $a['at'] ) ?: strcmp( $b['key'], $a['key'] );
		} );
		return array( 'items' => array_slice( $items, 0, $limit ), 'totals' => Reports::history_totals( $since, $url ), 'server' => gmdate( 'Y-m-d\TH:i:s\Z' ) );
	}

	public static function history( \WP_REST_Request $r ) {
		$url = '';
		if ( 'page' === $r['scope'] ) {
			$url = (string) Validator::path( (string) $r['url'] );
			if ( '' === $url ) {
				return new \WP_Error( 'userflo_bad_url', __( 'Page must be a site-relative path starting with /.', 'userflo' ), array( 'status' => 400 ) );
			}
		}
		$resp = rest_ensure_response( array( 'scope' => $r['scope'], 'url' => $url ) + self::history_data( $url, (int) $r['limit'] ) );
		$resp->header( 'Cache-Control', 'no-store' );
		return $resp;
	}

	public static function pages( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$out    = array();
		$search = strtolower( (string) $r['search'] );
		foreach ( Reports::top_pages( Clock::mysql( $range[0] ), 200, Clock::mysql( $range[1] ) ) as $p ) {
			if ( '' !== $search && false === strpos( strtolower( $p['url'] . ' ' . $p['title'] ), $search ) ) {
				continue;
			}
			$out[] = array( 'url' => $p['url'], 'title' => $p['title'], 'views' => (int) $p['views'], 'visitors' => (int) $p['visitors'], 'avg_engaged' => (int) round( $p['avg_engaged'] ) );
		}
		if ( $out && \UserFlo\Modules::on( 'audit' ) ) {
			$h = \UserFlo\Audit\Findings::for_urls( array_column( $out, 'url' ) ); // The health dot: one indexed lookup for the rows shown.
			foreach ( $out as $i => $row ) {
				$x = isset( $h[ $row['url'] ] ) ? $h[ $row['url'] ] : null;
				$out[ $i ]['health'] = $x ? array( 'state' => $x['state'], 'level' => $x['level'], 'red' => $x['red'], 'amber' => $x['amber'], 'excluded' => $x['excluded'], 'findings' => $x['findings'] ) : array( 'state' => 'unscanned', 'level' => -1, 'red' => 0, 'amber' => 0, 'excluded' => 0, 'findings' => array() );
			}
		}
		if ( $r['bots'] && \UserFlo\Settings\Settings::get( 'bot_counter' ) ) { // Highlight AI and bot visits: counts per page, and pages only bots have requested.
			$map  = \UserFlo\Database\Reports::bots_by_url( wp_date( 'Y-m-d', $range[0] ), wp_date( 'Y-m-d', $range[1] - 1 ) );
			$seen = array();
			foreach ( $out as $i => $row ) {
				$seen[ $row['url'] ]  = true;
				$out[ $i ]['bots']    = isset( $map[ $row['url'] ] ) ? $map[ $row['url'] ] : array( 'ai' => 0, 'search' => 0, 'other' => 0 );
			}
			foreach ( $map as $url => $c ) {
				if ( isset( $seen[ $url ] ) || ( '' !== $search && false === strpos( strtolower( $url ), $search ) ) ) {
					continue;
				}
				$out[] = array( 'url' => $url, 'title' => '', 'views' => 0, 'visitors' => 0, 'avg_engaged' => 0, 'bots' => $c, 'bots_only' => true );
			}
		}
		return array( 'items' => $out );
	}

	public static function visits( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$url = Validator::path( $r['url'] );
		if ( null === $url ) {
			return new \WP_Error( 'userflo_bad_url', __( 'Page must be a site-relative path starting with /.', 'userflo' ), array( 'status' => 400 ) );
		}
		$res   = Reports::page_visits( $url, Clock::mysql( $range[0] ), Clock::mysql( $range[1] ), $r['type'], (int) $r['page'], (int) $r['per_page'] );
		$items = array();
		$show  = \UserFlo\Settings\Settings::get( 'show_identity' );
		foreach ( $res['rows'] as $v ) {
			$items[] = self::who( $v ) + array(
				'id'       => (int) $v->id,
				'at'       => Presenter::iso( $v->viewed_at ),
				'engaged'  => (int) $v->engaged_seconds,
				'duration' => (int) $v->duration_seconds,
				'scroll'   => (int) $v->max_scroll,
				'referrer' => $v->referrer,
				'device'   => trim( $v->device . ' · ' . $v->browser . ' · ' . $v->os, ' ·' ),
				'email'    => ( $show && ! empty( $v->user_id ) && ! empty( $v->user_email ) ) ? $v->user_email : null,
			);
		}
		return array( 'url' => $url, 'total' => $res['total'], 'items' => $items );
	}
}
