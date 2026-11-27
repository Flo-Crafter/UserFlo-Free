<?php
namespace UserFlo\Tracking;

defined( 'ABSPATH' ) || exit;

/** Fixed-window request counter (object cache when available, otherwise transients). */
final class RateLimiter {

	private static function k( $key ) {
		return 'uf_rl_' . md5( $key );
	}

	/** Current count in the window without incrementing. */
	public static function count( $key ) {
		$k = self::k( $key );
		return wp_using_ext_object_cache() ? (int) wp_cache_get( $k, 'userflo' ) : (int) get_transient( $k );
	}

	/** Increment a counter (window starts at the first hit). */
	public static function bump( $key, $window = 60 ) {
		$k = self::k( $key );
		if ( wp_using_ext_object_cache() ) {
			wp_cache_add( $k, 0, 'userflo', $window );
			wp_cache_incr( $k, 1, 'userflo' );
			return;
		}
		$n = (int) get_transient( $k );
		set_transient( $k, $n + 1, $window );
	}

	/** @return bool True when the request is allowed. */
	public static function allow( $key, $limit, $window = 60 ) {
		$key = 'uf_rl_' . md5( $key );
		if ( wp_using_ext_object_cache() ) {
			wp_cache_add( $key, 0, 'userflo', $window );
			$n = wp_cache_incr( $key, 1, 'userflo' );
			return false === $n || $n <= $limit;
		}
		$n = (int) get_transient( $key );
		if ( $n >= $limit ) {
			return false;
		}
		set_transient( $key, $n + 1, $window );
		return true;
	}
}
