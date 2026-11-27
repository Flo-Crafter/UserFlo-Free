<?php
namespace UserFlo\Insights;

use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Fills the campaign columns of sessions recorded before they existed, from the landing page already stored in `entry_url`. It runs as a
 * chain of small single cron events (BATCH rows each), walks the primary key once up to the newest session that existed at the upgrade, and
 * removes itself when it reaches the end. Sessions recorded after the upgrade get their values at ingest and are never touched.
 */
final class UtmBackfill {

	const HOOK   = 'userflo_utm_backfill';
	const OPTION = 'userflo_utm_backfill';
	const BATCH  = 500;

	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	/** Begins a run: remembers the newest session id (anything newer was recorded with the columns) and schedules the first batch. */
	public static function start() {
		global $wpdb;
		$max = (int) $wpdb->get_var( 'SELECT MAX(id) FROM ' . Tables::sessions() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		update_option( self::OPTION, array( 'last' => 0, 'max' => $max, 'done' => 0 === $max, 'filled' => 0, 'started' => time() ), false );
		if ( $max > 0 ) {
			self::schedule( 5 );
		}
	}

	private static function schedule( $delay ) {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + $delay, self::HOOK );
		}
	}

	/** @return array{last:int,max:int,done:bool,filled:int,started:int}|null */
	public static function state() {
		$s = get_option( self::OPTION, null );
		return is_array( $s ) ? $s : null;
	}

	public static function pending() {
		$s = self::state();
		return $s && empty( $s['done'] );
	}

	/**
	 * Processes one batch. Safe to call from cron or from the Tools screen; returns the state afterwards.
	 *
	 * @return array|null
	 */
	public static function run() {
		global $wpdb;
		$s = self::state();
		if ( ! $s || ! empty( $s['done'] ) ) {
			return $s;
		}
		$t    = Tables::sessions();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, entry_url FROM {$t} WHERE id > %d AND id <= %d AND entry_url LIKE %s ORDER BY id LIMIT %d", (int) $s['last'], (int) $s['max'], '%' . $wpdb->esc_like( 'utm_' ) . '%', self::BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		foreach ( (array) $rows as $r ) {
			$u = Utm::parse( $r->entry_url );
			if ( Utm::any( $u ) ) {
				$wpdb->update( $t, $u, array( 'id' => (int) $r->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
				$s['filled']++;
			}
			$s['last'] = (int) $r->id;
		}
		if ( count( (array) $rows ) < self::BATCH ) {
			$s['last'] = (int) $s['max'];
			$s['done'] = true;
			delete_transient( 'userflo_campaigns' );
		}
		update_option( self::OPTION, $s, false );
		if ( empty( $s['done'] ) ) {
			self::schedule( 30 );
		}
		return $s;
	}
}
