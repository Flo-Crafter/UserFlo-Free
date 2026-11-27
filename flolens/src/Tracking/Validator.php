<?php
namespace UserFlo\Tracking;

defined( 'ABSPATH' ) || exit;

/** Strict validation and normalisation of a tracker payload. Pure; no WordPress calls. */
final class Validator {

	const MAX_BODY   = 32768;
	const MAX_EVENTS = 50;

	public static $event_types = array( 'click', 'download', 'outbound', 'scroll', 'custom', 'form', 'media', 'commerce' );

	/**
	 * @param mixed $data Decoded JSON.
	 * @return array|null Normalised payload or null if invalid.
	 */
	public static function normalize( $data ) {
		if ( ! is_array( $data ) || ( isset( $data['v'] ) && 1 !== (int) $data['v'] ) ) {
			return null;
		}
		$vid = isset( $data['vid'] ) ? self::hex( $data['vid'], 32 ) : null;
		$sid = isset( $data['sid'] ) ? self::hex( $data['sid'], 32 ) : null;
		$pid = isset( $data['pid'] ) ? self::hex( $data['pid'], 16 ) : null;
		if ( ! $vid || ! $sid || ! $pid ) {
			return null;
		}
		$url = self::path( isset( $data['u'] ) ? $data['u'] : '' );
		if ( null === $url ) {
			return null;
		}

		$out = array(
			'vid'    => $vid,
			'sid'    => $sid,
			'pid'    => $pid,
			'url'    => $url,
			'title'  => self::text( isset( $data['t'] ) ? $data['t'] : '', 255 ),
			'ref'    => self::text( isset( $data['r'] ) ? $data['r'] : '', 500 ),
			'sw'     => self::int( isset( $data['sw'] ) ? $data['sw'] : 0, 0, 20000 ),
			'sh'     => self::int( isset( $data['sh'] ) ? $data['sh'] : 0, 0, 20000 ),
			'eng'    => self::int( isset( $data['eng'] ) ? $data['eng'] : 0, 0, 600 ),
			'sc'     => self::int( isset( $data['sc'] ) ? $data['sc'] : 0, 0, 100 ),
			'dur'    => self::int( isset( $data['dur'] ) ? $data['dur'] : 0, 0, 86400 ),
			'events' => array(),
		);

		$events = isset( $data['ev'] ) && is_array( $data['ev'] ) ? array_slice( $data['ev'], 0, self::MAX_EVENTS ) : array();
		foreach ( $events as $ev ) {
			if ( ! is_array( $ev ) || ! isset( $ev['k'], $ev['i'] ) || ! in_array( $ev['k'], self::$event_types, true ) ) {
				continue;
			}
			$out['events'][] = array(
				'i'      => self::int( $ev['i'], 0, 1000000 ),
				'type'   => $ev['k'],
				'name'   => self::text( isset( $ev['n'] ) ? $ev['n'] : '', 100 ),
				'target' => self::text( isset( $ev['g'] ) ? $ev['g'] : '', 255 ),
				'value'  => self::text( isset( $ev['x'] ) ? $ev['x'] : '', 500 ),
				'comp'   => self::text( isset( $ev['c'] ) ? $ev['c'] : '', 100 ),
				'age'    => self::int( isset( $ev['a'] ) ? $ev['a'] : 0, 0, 86400000 ),
			);
		}
		return $out;
	}

	public static function hex( $v, $len ) {
		return is_string( $v ) && preg_match( '/^[a-f0-9]{' . (int) $len . '}$/', $v ) ? $v : null;
	}

	public static function int( $v, $min, $max ) {
		return is_numeric( $v ) ? max( $min, min( $max, (int) $v ) ) : $min;
	}

	/** Remove control characters, collapse whitespace and truncate (multibyte safe). */
	public static function text( $v, $max ) {
		if ( ! is_scalar( $v ) ) {
			return '';
		}
		$v = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', (string) $v );
		$v = null === $v ? '' : trim( preg_replace( '/\s+/u', ' ', $v ) );
		return \UserFlo\Core\Text::substr( $v, $max );
	}

	/** Site-relative path (with optional short query). */
	public static function path( $v ) {
		if ( ! is_string( $v ) || '' === $v || '/' !== $v[0] || '/' === ( $v[1] ?? '' ) ) {
			return null;
		}
		$v = self::text( $v, 500 );
		return '' === $v ? null : self::filter_query( $v );
	}

	/**
	 * The tracker already drops every query parameter except utm_* and a short allow-list; this applies the same rule on the server, so a
	 * hand-made request cannot store click ids, tokens or e-mail addresses in a recorded URL.
	 */
	public static function filter_query( $path ) {
		$pos = strpos( $path, '?' );
		if ( false === $pos ) {
			return $path;
		}
		$keep = array( 'p', 'page_id', 'cat', 'tag', 'post_type', 'product', 'paged' );
		if ( function_exists( 'apply_filters' ) ) {
			$keep = (array) apply_filters( 'userflo_query_params', $keep );
		}
		$out = array();
		foreach ( explode( '&', substr( $path, $pos + 1 ) ) as $pair ) {
			$k = explode( '=', $pair, 2 )[0];
			if ( '' !== $k && ( 0 === stripos( $k, 'utm_' ) || in_array( $k, $keep, true ) ) ) {
				$out[] = $pair;
			}
		}
		return substr( $path, 0, $pos ) . ( $out ? '?' . implode( '&', $out ) : '' );
	}
}
