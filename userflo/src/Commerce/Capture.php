<?php
namespace UserFlo\Commerce;

use UserFlo\Modules;

defined( 'ABSPATH' ) || exit;

/** Hooks. Registered only while the WooCommerce module is on (and WooCommerce is present), so other sites run none of this. */
final class Capture {

	public static function register() {
		if ( ! Modules::on( 'woocommerce' ) ) {
			return;
		}
		add_action( 'woocommerce_new_order', array( __CLASS__, 'changed' ), 20, 1 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'changed' ), 20, 1 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'changed' ), 20, 1 );
		add_action( 'woocommerce_refund_deleted', array( __CLASS__, 'refund_deleted' ), 20, 2 );
		add_action( 'woocommerce_delete_order', array( Orders::class, 'delete' ), 20, 1 );
		// Customer checkouts only (classic and block checkout): the request carries the visitor's own UserFlo cookie.
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'classic_checkout' ), 30, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'block_checkout' ), 30, 1 );
	}

	public static function changed( $order_id ) {
		Orders::sync( $order_id );
	}

	public static function refund_deleted( $refund_id, $order_id ) {
		Orders::sync( $order_id, true );
	}

	public static function classic_checkout( $order_id ) {
		Orders::sync( $order_id );
		Orders::remember_session( $order_id, Orders::request_session() );
	}

	/** @param object $order WC_Order. */
	public static function block_checkout( $order ) {
		if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			self::classic_checkout( (int) $order->get_id() );
		}
	}
}
