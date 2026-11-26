<?php
namespace UserFlo\Insights;

defined( 'ABSPATH' ) || exit;

/**
 * Campaign (UTM) values of a landing page. One model on purpose: the campaign that started the session, read from the first page the
 * session recorded. Only the five standard keys are read; every value is cleaned and bounded, anything else in the query string is ignored.
 * Click ids (gclid, fbclid, …) and other parameters are never stored by this class.
 */
final class Utm {

	/** Column => max length. */
	const KEYS = array(
		'utm_source'   => 100,
		'utm_medium'   => 100,
		'utm_campaign' => 150,
		'utm_term'     => 150,
		'utm_content'  => 150,
	);

	/**
	 * @param string $url Recorded page path with its (already filtered) query string.
	 * @return array<string,string> All five keys; '' when absent.
	 */
	public static function parse( $url ) {
		$out = array_fill_keys( array_keys( self::KEYS ), '' );
		$url = (string) $url;
		$pos = strpos( $url, '?' );
		if ( false === $pos || false === stripos( $url, 'utm_' ) ) {
			return $out;
		}
		$query = substr( $url, $pos + 1 );
		$hash  = strpos( $query, '#' );
		if ( false !== $hash ) {
			$query = substr( $query, 0, $hash );
		}
		foreach ( explode( '&', $query ) as $pair ) {
			$kv = explode( '=', $pair, 2 );
			$k  = strtolower( rawurldecode( $kv[0] ) );
			if ( ! isset( self::KEYS[ $k ] ) || '' !== $out[ $k ] ) {
				continue; // Unknown key, or a repeated key: the first one wins.
			}
			$out[ $k ] = self::clean( isset( $kv[1] ) ? $kv[1] : '', self::KEYS[ $k ] );
		}
		return $out;
	}

	/** URL-decode, lower-case, keep letters, digits and a few separators, collapse whitespace, bound the length. */
	public static function clean( $raw, $max ) {
		$v = strtolower( rawurldecode( str_replace( '+', ' ', (string) $raw ) ) );
		$v = preg_replace( '/[^\p{L}\p{N} _.\-:\/|,]+/u', '', $v );
		$v = null === $v ? '' : trim( preg_replace( '/\s+/u', ' ', $v ) );
		return \UserFlo\Core\Text::substr( $v, $max );
	}

	/** Whether any of the five values is set. */
	public static function any( array $utm ) {
		return '' !== implode( '', $utm );
	}
}
