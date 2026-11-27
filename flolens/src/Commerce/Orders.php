<?php
namespace UserFlo\Commerce;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/** The order mirror. Every write is an upsert on the order id, so processing the same order any number of times gives the same row. */
final class Orders {

	/** @var array<int,bool> Orders already synced in this request with the same state (hooks often fire several times per save). */
	private static $seen = array();

	/** Re-reads one order from WooCommerce and stores its numbers; removes the row when the order no longer exists. */
	public static function sync( $order_id, $force = false ) {
		$order_id = (int) $order_id;
		if ( $order_id <= 0 || ! Source::active() ) {
			return false;
		}
		$row = Source::read( $order_id );
		if ( null === $row ) {
			self::delete( $order_id );
			return false;
		}
		$sig = md5( wp_json_encode( $row ) );
		if ( ! $force && isset( self::$seen[ $order_id ] ) && self::$seen[ $order_id ] === $sig ) {
			return true;
		}
		self::$seen[ $order_id ] = $sig;
		self::upsert( $row );
		return true;
	}

	public static function reset_memo() {
		self::$seen = array();
	}

	public static function upsert( array $r ) {
		global $wpdb;
		$t = Tables::orders();
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$t} (order_id, created_gmt, status, currency, total, tax, shipping, discount, refunded, tax_refunded, shipping_refunded, updated_gmt) VALUES (%d,%s,%s,%s,%f,%f,%f,%f,%f,%f,%f,%s)
			ON DUPLICATE KEY UPDATE created_gmt = VALUES(created_gmt), status = VALUES(status), currency = VALUES(currency), total = VALUES(total), tax = VALUES(tax), shipping = VALUES(shipping), discount = VALUES(discount), refunded = VALUES(refunded), tax_refunded = VALUES(tax_refunded), shipping_refunded = VALUES(shipping_refunded), updated_gmt = VALUES(updated_gmt)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$r['order_id'], $r['created_gmt'], $r['status'], $r['currency'], $r['total'], $r['tax'], $r['shipping'], $r['discount'], $r['refunded'], $r['tax_refunded'], $r['shipping_refunded'], Clock::now() ) );
	}

	public static function delete( $order_id ) {
		global $wpdb;
		unset( self::$seen[ (int) $order_id ] );
		return (bool) $wpdb->delete( Tables::orders(), array( 'order_id' => (int) $order_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	/**
	 * Remembers which visit was active when the order was placed: the visitor's current FloLens session id, read from their own checkout
	 * request. Set once (an order keeps the visit it was placed in) and only if the cookie is well formed.
	 */
	public static function remember_session( $order_id, $session_uid ) {
		global $wpdb;
		if ( ! is_string( $session_uid ) || ! preg_match( '/^[a-f0-9]{32}$/', $session_uid ) ) {
			return false;
		}
		return (bool) $wpdb->query( $wpdb->prepare( 'UPDATE ' . Tables::orders() . ' SET session_uid = %s WHERE order_id = %d AND session_uid IS NULL', $session_uid, (int) $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	/** The session cookie of the current request, or '' when there is none (no consent, other storage mode, cache or CDN stripped it). */
	public static function request_session() {
		if ( \UserFlo\Privacy\Mode::cookieless() ) {
			return ''; // Session-only tracking: no session cookie, so no order is tied to a visit.
		}
		$v = isset( $_COOKIE['uf_sid'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['uf_sid'] ) ) : '';
		return preg_match( '/^[a-f0-9]{32}$/', $v ) ? $v : '';
	}

	/**
	 * Re-reads orders created in the last $days days, one page per call, for the "Re-read orders" action. Orders placed while FloLens was off
	 * get their numbers but no visit (it cannot be recovered).
	 *
	 * @return array{page:int,done:bool,count:int}
	 */
	public static function resync_page( $days, $page ) {
		$ids = Source::ids_since( Clock::time() - (int) $days * DAY_IN_SECONDS, max( 1, (int) $page ) );
		foreach ( $ids as $id ) {
			self::sync( $id, true );
		}
		return array( 'page' => max( 1, (int) $page ), 'done' => count( $ids ) < 100, 'count' => count( $ids ) );
	}
}
