<?php
namespace UserFlo\Privacy;

defined( 'ABSPATH' ) || exit;

/** Applies the configured IP-address policy before anything is stored. */
final class IpHandler {

	/**
	 * @param string $ip   Raw IP address.
	 * @param string $mode off | truncate | hash | full.
	 * @param string $salt Secret used for hashing.
	 * @return string Value safe to store ('' when off or invalid).
	 */
	public static function process( $ip, $mode, $salt = '' ) {
		$ip = (string) $ip;
		if ( 'off' === $mode || '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		switch ( $mode ) {
			case 'full':
				return $ip;
			case 'hash':
				return substr( hash_hmac( 'sha256', $ip, $salt ), 0, 32 );
			case 'truncate':
			default:
				return self::truncate( $ip );
		}
	}

	public static function truncate( $ip ) {
		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $packed ) {
			return '';
		}
		if ( 4 === strlen( $packed ) ) {
			$packed[3] = "\0";
		} else {
			$packed = substr( $packed, 0, 6 ) . str_repeat( "\0", 10 ); // keep /48.
		}
		return (string) inet_ntop( $packed );
	}

	/**
	 * Client IP. By default only REMOTE_ADDR is used. Behind a reverse proxy/CDN the site owner can choose the header
	 * the proxy sets (Settings > Privacy); only do so if every request really passes through that proxy, otherwise
	 * visitors could spoof the value.
	 */
	public static function client_ip() {
		$map    = array( 'x_forwarded_for' => 'HTTP_X_FORWARDED_FOR', 'cf_connecting_ip' => 'HTTP_CF_CONNECTING_IP', 'x_real_ip' => 'HTTP_X_REAL_IP' );
		$choice = (string) \UserFlo\Settings\Settings::get( 'ip_header' );
		$ip     = '';
		if ( isset( $map[ $choice ] ) && ! empty( $_SERVER[ $map[ $choice ] ] ) ) {
			$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $map[ $choice ] ] ) ) );
			$ip    = trim( (string) $parts[0] );
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				$ip = '';
			}
		}
		if ( '' === $ip ) {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		}
		return (string) apply_filters( 'userflo_client_ip', $ip );
	}
}
