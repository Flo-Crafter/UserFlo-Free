<?php
namespace UserFlo\Rest;

use UserFlo\Commerce\Access;
use UserFlo\Commerce\Orders;
use UserFlo\Commerce\Stats;
use UserFlo\Core\Clock;
use UserFlo\Edition;
use UserFlo\Modules;

defined( 'ABSPATH' ) || exit;

/** WooCommerce sales. Registered only while the module is on and WooCommerce is present. */
final class CommerceRoutes {

	public static function register() {
		if ( ! Modules::on( 'woocommerce' ) ) {
			return;
		}
		$range = array(
			'days' => array( 'type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 366 ),
			'from' => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
			'to'   => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
		);
		$can = array( Access::class, 'can' );
		register_rest_route( Routes::NS, '/commerce/summary', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'summary' ), 'permission_callback' => $can, 'args' => $range + array( 'compare' => array( 'type' => 'boolean', 'default' => true ) ) ) );
		register_rest_route( Routes::NS, '/commerce/resync', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'resync' ), 'permission_callback' => static function () {
			return current_user_can( 'manage_options' );
		}, 'args' => array( 'page' => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 100000 ), 'days' => array( 'type' => 'integer', 'default' => 90, 'minimum' => 1, 'maximum' => 3650 ) ) ) );
	}

	private static function bounds( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		return array( Clock::mysql( $range[0] ), Clock::mysql( $range[1] ), $range );
	}

	public static function summary( \WP_REST_Request $r ) {
		$b = self::bounds( $r );
		if ( is_wp_error( $b ) ) {
			return $b;
		}
		$out = Stats::summary( $b[0], $b[1] ) + array( 'counted' => \UserFlo\Commerce\Statuses::counted() );
		if ( $r['compare'] ) {
			$len  = $b[2][1] - $b[2][0];
			$prev = Stats::summary( Clock::mysql( $b[2][0] - $len ), $b[0] );
			$by   = array();
			foreach ( $prev['currencies'] as $c ) {
				$by[ $c['currency'] ] = $c;
			}
			foreach ( $out['currencies'] as $i => $c ) {
				$out['currencies'][ $i ]['prev'] = isset( $by[ $c['currency'] ] ) ? array( 'orders' => $by[ $c['currency'] ]['orders'], 'revenue' => $by[ $c['currency'] ]['revenue'] ) : array( 'orders' => 0, 'revenue' => 0 );
			}
		}
		$out['advanced'] = false;
		return (array) apply_filters( 'userflo_commerce_summary', $out, $b[0], $b[1] ); // FloLens Pro adds the basket numbers.
	}

	public static function resync( \WP_REST_Request $r ) {
		return Orders::resync_page( (int) $r['days'], (int) $r['page'] );
	}
}
