<?php
namespace UserFlo\Identity;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Connects a browser (visitor cookie) to a WordPress account the moment it signs in, registers or checks out.
 * - The visitor is linked to the first account seen on that browser and never re-assigned to another (shared computers).
 * - With "retroactive linking" on, every earlier anonymous session of that browser is attributed to the account, so the user's
 *   history includes what they did before logging in; later anonymous sessions of an identified browser are attributed too.
 * Nothing is ever merged by IP address or by guessing: only the tracker's own cookie identifies a browser.
 */
final class Linker {

	/**
	 * @return int Number of earlier sessions attributed retroactively.
	 */
	public static function link( $visitor_id, $session_id, $user_id ) {
		global $wpdb;
		$visitor_id = (int) $visitor_id;
		$user_id    = (int) $user_id;
		if ( $visitor_id <= 0 || $user_id <= 0 ) {
			return 0;
		}
		$st = Tables::sessions();
		$vt = Tables::visitors();
		if ( $session_id ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$st} SET user_id = %d WHERE id = %d AND user_id IS NULL", $user_id, (int) $session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$vt} SET user_id = %d, identified_at = %s WHERE id = %d AND user_id IS NULL", $user_id, Clock::now(), $visitor_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		if ( ! Settings::get( 'identity_retroactive' ) ) {
			return 0;
		}
		// Only when this browser belongs to this very account (never attribute to a second user on a shared browser).
		$owner = (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$vt} WHERE id = %d", $visitor_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		if ( $owner !== $user_id ) {
			return 0;
		}
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$st} SET user_id = %d WHERE visitor_id = %d AND user_id IS NULL", $user_id, $visitor_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	/** Gives a freshly created session of an identified browser the account of that browser (when retroactive linking is on). */
	public static function inherit( $session_id ) {
		global $wpdb;
		if ( ! Settings::get( 'identity_retroactive' ) ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Tables::sessions() . ' s JOIN ' . Tables::visitors() . ' v ON v.id = s.visitor_id SET s.user_id = v.user_id WHERE s.id = %d AND s.user_id IS NULL AND v.user_id IS NOT NULL', (int) $session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	/** Visitor and (when its cookie is still valid) session of the browser making the current request. */
	public static function from_cookies() {
		global $wpdb;
		if ( \UserFlo\Privacy\Mode::cookieless() ) {
			return null; // Session-only tracking never links a browser to an account.
		}
		$vid = isset( $_COOKIE['uf_vid'] ) ? \UserFlo\Tracking\Validator::hex( sanitize_text_field( wp_unslash( $_COOKIE['uf_vid'] ) ), 32 ) : null;
		if ( ! $vid ) {
			return null;
		}
		$visitor = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Tables::visitors() . ' WHERE visitor_uid = %s', $vid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		if ( ! $visitor ) {
			return null;
		}
		$sid     = isset( $_COOKIE['uf_sid'] ) ? \UserFlo\Tracking\Validator::hex( sanitize_text_field( wp_unslash( $_COOKIE['uf_sid'] ) ), 32 ) : null;
		$session = $sid ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Tables::sessions() . ' WHERE session_uid = %s AND visitor_id = %d', $sid, $visitor ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		return array( 'visitor_id' => $visitor, 'session_id' => $session );
	}

	/** Hook for every WordPress sign-in (core login, WooCommerce checkout/registration auto-login, social login plugins…). */
	public static function on_cookie_set( $logged_in_cookie, $expire, $expiration, $user_id ) {
		if ( ! Settings::get( 'enabled' ) || ! Settings::get( 'identity_association' ) || ! $user_id ) {
			return;
		}
		$b = self::from_cookies();
		if ( $b ) {
			self::link( $b['visitor_id'], $b['session_id'], $user_id );
		}
	}
}
