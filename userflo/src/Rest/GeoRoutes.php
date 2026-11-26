<?php
namespace UserFlo\Rest;

use UserFlo\Geo\Stats;
use UserFlo\Modules;

defined( 'ABSPATH' ) || exit;

/** Map data (analytics capability required). Cached briefly. */
final class GeoRoutes {

	public static function register() {
		if ( ! Modules::needed( 'map' ) ) {
			return;
		}
		$base  = array( 'permission_callback' => array( Routes::class, 'can_view' ), 'methods' => 'GET' );
		$range = array(
			'days' => array( 'type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 366 ),
			'from' => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
			'to'   => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
		);
		register_rest_route( Routes::NS, '/geo/summary', $base + array( 'callback' => array( __CLASS__, 'summary' ), 'args' => $range ) );
		register_rest_route( Routes::NS, '/geo/details', $base + array(
			'callback' => array( __CLASS__, 'details' ),
			'args'     => $range + array(
				'level' => array( 'type' => 'string', 'enum' => (array) apply_filters( 'userflo_geo_levels', array( 'continent', 'country' ) ), 'required' => true ),
				'key'   => array( 'type' => 'string', 'required' => true, 'maxLength' => 100 ),
			),
		) );
	}

	private static function cached( $key, $fn ) {
		$ttl = (int) apply_filters( 'userflo_overview_cache_ttl', 120 );
		$out = $ttl > 0 ? get_transient( $key ) : false;
		if ( false === $out ) {
			$out = call_user_func( $fn );
			if ( $ttl > 0 && ! is_wp_error( $out ) ) {
				set_transient( $key, $out, $ttl );
			}
		}
		return $out;
	}

	public static function summary( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		return self::cached( 'userflo_geo_' . md5( wp_json_encode( $range ) . \UserFlo\Geo\Locator::mode() ), static function () use ( $range ) {
			return Stats::summary( $range[0], $range[1] );
		} );
	}

	public static function details( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$level = $r['level'];
		$key   = sanitize_text_field( $r['key'] );
		return self::cached( 'userflo_geod_' . md5( wp_json_encode( array( $range, $level, $key ) ) ), static function () use ( $level, $key, $range ) {
			return Stats::details( $level, $key, $range[0], $range[1] );
		} );
	}
}
