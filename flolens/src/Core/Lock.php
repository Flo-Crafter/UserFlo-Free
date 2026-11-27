<?php
namespace UserFlo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * A small cross-request mutex kept in one non-autoloaded option row, so two background runs (cleanup, summary roll, a manual purge from
 * the admin screen) never work on the same data at once. Acquiring is a single INSERT IGNORE (atomic); a lock that was not renewed before
 * its expiry is taken over, so a run that died never blocks the next one for longer than its time-to-live.
 */
final class Lock {

	private static function key( $name ) {
		return 'userflo_lock_' . sanitize_key( $name );
	}

	private static function forget( $key ) {
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}

	/** @return bool Whether the caller now holds the lock. */
	public static function acquire( $name, $ttl = 600 ) {
		global $wpdb;
		$key = self::key( $name );
		$now = time();
		$new = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, (string) ( $now + (int) $ttl ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::forget( $key );
		if ( 1 === (int) $new ) {
			return true;
		}
		$old = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( '' !== $old && (int) $old >= $now ) {
			return false; // Held by a run that is still alive.
		}
		// Expired: take it over only if nobody else did in the meantime (compare-and-swap on the old value).
		$won = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", (string) ( $now + (int) $ttl ), $key, $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::forget( $key );
		return 1 === (int) $won;
	}

	/** Pushes the expiry forward while a long run is still working. */
	public static function renew( $name, $ttl = 600 ) {
		global $wpdb;
		$key = self::key( $name );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", (string) ( time() + (int) $ttl ), $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::forget( $key );
	}

	public static function release( $name ) {
		global $wpdb;
		$key = self::key( $name );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::forget( $key );
	}

	/** Whether a live run holds the lock (for status displays). */
	public static function held( $name ) {
		global $wpdb;
		$v = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::key( $name ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return null !== $v && (int) $v >= time();
	}
}
