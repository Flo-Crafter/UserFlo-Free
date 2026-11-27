<?php
namespace UserFlo\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * The only place that reads WooCommerce. It uses WooCommerce's own CRUD getters (wc_get_order and the order object), never post meta or SQL
 * on WooCommerce tables, so it works with both the legacy posts storage and High-Performance Order Storage. Nothing personal is read: no
 * names, addresses, e-mail addresses, customer ids, notes or line items.
 */
final class Source {

	/** Whether WooCommerce is present. Filterable so tests (and sites with an unusual loader) can say so. */
	public static function active() {
		return (bool) apply_filters( 'userflo_woocommerce_active', class_exists( 'WooCommerce' ) );
	}

	/**
	 * @param int $order_id Order id.
	 * @return array|null Normalised numbers, or null when the order does not exist or is not a shop order (for example a refund).
	 */
	public static function read( $order_id ) {
		$pre = apply_filters( 'userflo_commerce_read_order', null, (int) $order_id ); // Lets tests and importers supply the normalised array.
		if ( null !== $pre ) {
			return is_array( $pre ) ? $pre : null;
		}
		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}
		$o = wc_get_order( (int) $order_id );
		return $o ? self::normalize( $o ) : null;
	}

	/**
	 * @param object $o A WC_Order (any object with the same getters).
	 * @return array|null
	 */
	public static function normalize( $o ) {
		if ( method_exists( $o, 'get_type' ) && 'shop_order' !== $o->get_type() ) {
			return null; // Refunds and other order-like objects are not orders.
		}
		$created = method_exists( $o, 'get_date_created' ) ? $o->get_date_created() : null;
		$ts      = $created && method_exists( $created, 'getTimestamp' ) ? (int) $created->getTimestamp() : time();
		$num     = static function ( $v ) {
			return round( (float) $v, 2 );
		};
		return array(
			'order_id'          => (int) $o->get_id(),
			'created_gmt'       => gmdate( 'Y-m-d H:i:s', $ts ),
			'status'            => substr( sanitize_key( (string) $o->get_status() ), 0, 30 ),
			'currency'          => strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $o->get_currency() ), 0, 3 ) ),
			'total'             => $num( $o->get_total() ),
			'tax'               => $num( $o->get_total_tax() ),
			'shipping'          => $num( $o->get_shipping_total() ),
			'discount'          => $num( $o->get_total_discount() ),
			'refunded'          => $num( $o->get_total_refunded() ),
			'tax_refunded'      => $num( $o->get_total_tax_refunded() ),
			'shipping_refunded' => $num( $o->get_total_shipping_refunded() ),
		);
	}

	/** @return int[] Order ids created at or after a time, oldest first, one page at a time. */
	public static function ids_since( $since_ts, $page, $per_page = 100 ) {
		$pre = apply_filters( 'userflo_commerce_order_ids', null, (int) $since_ts, (int) $page, (int) $per_page );
		if ( null !== $pre ) {
			return array_map( 'intval', (array) $pre );
		}
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		return array_map( 'intval', (array) wc_get_orders( array( 'limit' => (int) $per_page, 'paged' => (int) $page, 'orderby' => 'ID', 'order' => 'ASC', 'return' => 'ids', 'type' => 'shop_order', 'date_created' => '>=' . (int) $since_ts, 'status' => array_keys( Statuses::all() ) ) ) );
	}
}
