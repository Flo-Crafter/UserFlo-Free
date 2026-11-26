<?php
namespace UserFlo\Identity;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;
use UserFlo\Settings\Settings;
use UserFlo\Tracking\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Captures successful logins and explicit logouts from WordPress authentication hooks.
 * A browser session (tracker cookie) is distinct from a WordPress auth session; both are recorded separately.
 */
final class AuthHooks {

	const DEDUPE_SECONDS = 5;

	public static function register() {
		add_action( 'wp_login', array( __CLASS__, 'on_login' ), 10, 2 );
		add_action( 'wp_logout', array( __CLASS__, 'on_logout' ), 10, 1 );
		add_action( 'clear_auth_cookie', array( __CLASS__, 'on_clear_cookie' ) ); // Inactivity-logout plugins often clear the cookie without calling wp_logout().
		// Fires for every way of signing in (WooCommerce checkout, registration auto-login, SSO plugins), not only wp-login.php.
		add_action( 'set_logged_in_cookie', array( Linker::class, 'on_cookie_set' ), 10, 4 );
	}

	/** @param string $user_login Login. @param \WP_User $user User. */
	public static function on_login( $user_login, $user ) {
		global $wpdb;
		if ( ! Settings::get( 'enabled' ) || empty( $user->ID ) ) {
			return;
		}
		$session = self::session_from_cookie();
		if ( ! $session ) {
			return; // No tracked browser session: the first authenticated pageview (or the sign-in cookie hook) links the account.
		}
		$st  = Tables::sessions();
		$now = Clock::now();
		// Duplicate protection: ignore a repeat login for the same session within a few seconds.
		$changed = $wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				"UPDATE {$st} SET login_at = %s WHERE id = %d AND (login_at IS NULL OR login_at <= %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now,
				$session->id,
				Clock::mysql( Clock::time() - self::DEDUPE_SECONDS )
			)
		);
		if ( ! $changed ) {
			return;
		}
		if ( Settings::get( 'identity_association' ) ) {
			Linker::link( $session->visitor_id, $session->id, $user->ID );
		}
		self::add_event( $session, 'login' );
	}

	public static function on_clear_cookie() {
		$id = get_current_user_id();
		if ( $id > 0 ) {
			self::on_logout( $id ); // Repeated calls for one logout are ignored (DEDUPE_SECONDS).
		}
	}

	/** @param int $user_id User who logged out (WP 5.5+). */
	public static function on_logout( $user_id = 0 ) {
		global $wpdb;
		if ( ! Settings::get( 'enabled' ) ) {
			return;
		}
		$st      = Tables::sessions();
		$now     = Clock::now();
		$session = self::session_from_cookie();
		if ( ! $session && $user_id ) {
			// No cookie: fall back to the user's most recently active session within the last day.
			$session = $wpdb->get_row( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
				$wpdb->prepare(
					"SELECT id, visitor_id FROM {$st} WHERE user_id = %d AND last_activity_at >= %s ORDER BY last_activity_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					Clock::mysql( Clock::time() - DAY_IN_SECONDS )
				)
			);
		}
		if ( ! $session ) {
			return;
		}
		$changed = $wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
				"UPDATE {$st} SET logout_at = %s, last_activity_at = GREATEST(last_activity_at, %s)
				WHERE id = %d AND (logout_at IS NULL OR logout_at < COALESCE(login_at, '1970-01-01 00:00:00') OR logout_at <= %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now,
				$now,
				$session->id,
				Clock::mysql( Clock::time() - self::DEDUPE_SECONDS )
			)
		);
		if ( $changed ) {
			self::add_event( $session, 'logout' );
		}
	}

	private static function session_from_cookie() {
		global $wpdb;
		if ( \UserFlo\Privacy\Mode::cookieless() ) {
			return null;
		}
		$vid = isset( $_COOKIE['uf_vid'] ) ? Validator::hex( sanitize_text_field( wp_unslash( $_COOKIE['uf_vid'] ) ), 32 ) : null;
		$sid = isset( $_COOKIE['uf_sid'] ) ? Validator::hex( sanitize_text_field( wp_unslash( $_COOKIE['uf_sid'] ) ), 32 ) : null;
		if ( ! $vid || ! $sid ) {
			return null;
		}
		$st = Tables::sessions();
		$vt = Tables::visitors();
		return $wpdb->get_row( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				"SELECT s.id, s.visitor_id FROM {$st} s INNER JOIN {$vt} v ON v.id = s.visitor_id WHERE s.session_uid = %s AND v.visitor_uid = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$sid,
				$vid
			)
		);
	}

	private static function add_event( $session, $type ) {
		global $wpdb;
		$wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . Tables::events() . ' (dedupe_key, pageview_id, session_id, visitor_id, occurred_at, type, name) VALUES (%s,0,%d,%d,%s,%s,%s)', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
				md5( 'auth' . $type . $session->id . Clock::time() . wp_rand() ),
				$session->id,
				$session->visitor_id,
				Clock::now(),
				$type,
				$type
			)
		);
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Tables::sessions() . ' SET event_count = event_count + 1 WHERE id = %d', $session->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Tables::visitors() . ' SET event_count = event_count + 1 WHERE id = %d', $session->visitor_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}
}
