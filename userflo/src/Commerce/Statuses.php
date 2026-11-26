<?php
namespace UserFlo\Commerce;

defined( 'ABSPATH' ) || exit;

/** Which WooCommerce order statuses count as sales. UserFlo counts the default pair; choosing others is part of UserFlo Pro. */
final class Statuses {

	const DEFAULT_COUNTED = array( 'processing', 'completed' );

	/** @return array<string,string> Status key (without "wc-") => label, for the settings screen. */
	public static function all() {
		$out = array( 'pending' => 'Pending payment', 'processing' => 'Processing', 'on-hold' => 'On hold', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded', 'failed' => 'Failed' );
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			$out = array();
			foreach ( (array) wc_get_order_statuses() as $k => $label ) {
				$out[ 0 === strpos( $k, 'wc-' ) ? substr( $k, 3 ) : $k ] = (string) $label;
			}
		}
		return (array) apply_filters( 'userflo_commerce_statuses', $out );
	}

	/** @return string[] Statuses that count as sales (UserFlo Pro lets the owner choose them through `userflo_commerce_counted`). */
	public static function counted() {
		$list = (array) apply_filters( 'userflo_commerce_counted', self::DEFAULT_COUNTED );
		return $list ? array_values( array_map( 'strval', $list ) ) : self::DEFAULT_COUNTED;
	}

	/**
	 * Settings sanitiser. The saved choice is kept as it was; UserFlo Pro changes it through `userflo_woo_statuses_sanitize`. A form that
	 * does not post the field (another tab, the wizard) leaves it unchanged.
	 */
	public static function sanitize( array $input, array $stored ) {
		$keep = isset( $stored['woo_statuses'] ) && is_array( $stored['woo_statuses'] ) ? array_values( $stored['woo_statuses'] ) : self::DEFAULT_COUNTED;
		return (array) apply_filters( 'userflo_woo_statuses_sanitize', $keep, $input, $stored );
	}
}
