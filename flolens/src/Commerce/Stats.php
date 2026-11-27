<?php
namespace UserFlo\Commerce;

use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Sales numbers from the order mirror. Definitions, in one place:
 *  - A sale is an order whose status is in Statuses::counted() (default processing + completed), placed in the period (order creation time).
 *  - Revenue = order total minus the amount refunded so far, in the order's own currency. Orders are never converted or summed across
 *    currencies: every figure is per currency. A refund lowers the revenue of the period the order was placed in (not the refund date).
 *  - Net sales = revenue minus the tax and shipping that were not refunded.
 *  - Orders in other statuses (pending, failed, cancelled, refunded, ...) are not sales and are listed separately.
 */
final class Stats {

	/** @return string SQL list of quoted counted statuses. */
	public static function in_list() {
		global $wpdb;
		$s = Statuses::counted();
		return implode( ',', array_map( static function ( $v ) use ( $wpdb ) {
			return $wpdb->prepare( '%s', $v );
		}, $s ) );
	}

	/**
	 * @return array{currencies:array[],excluded:array<string,int>,sessions:int,orders:int}
	 */
	public static function summary( $from, $to ) {
		global $wpdb;
		$t    = Tables::orders();
		$in   = self::in_list();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT currency, COUNT(*) AS n, SUM(total) AS total, SUM(refunded) AS refunded, SUM(tax - tax_refunded) AS tax, SUM(shipping - shipping_refunded) AS ship, SUM(discount) AS disc FROM {$t} WHERE created_gmt >= %s AND created_gmt < %s AND status IN ({$in}) GROUP BY currency ORDER BY n DESC", $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
		$cur  = array();
		$all  = 0;
		foreach ( (array) $rows as $r ) {
			$n       = (int) $r['n'];
			$revenue = round( (float) $r['total'] - (float) $r['refunded'], 2 );
			$cur[]   = array(
				'currency' => (string) $r['currency'],
				'orders'   => $n,
				'revenue'  => $revenue,
				'net'      => round( $revenue - (float) $r['tax'] - (float) $r['ship'], 2 ),
				'aov'      => $n ? round( $revenue / $n, 2 ) : 0.0,
				'refunded' => round( (float) $r['refunded'], 2 ),
				'discount' => round( (float) $r['disc'], 2 ),
				'tax'      => round( (float) $r['tax'], 2 ),
				'shipping' => round( (float) $r['ship'], 2 ),
			);
			$all += $n;
		}
		$ex = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM {$t} WHERE created_gmt >= %s AND created_gmt < %s AND status NOT IN ({$in}) GROUP BY status ORDER BY n DESC", $from, $to ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
			$ex[ $r['status'] ] = (int) $r['n'];
		}
		return array( 'currencies' => $cur, 'excluded' => $ex, 'sessions' => \UserFlo\Goals\Stats::sessions( $from, $to ), 'orders' => $all );
	}
}
