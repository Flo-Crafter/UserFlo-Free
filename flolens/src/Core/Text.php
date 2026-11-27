<?php
namespace UserFlo\Core;

defined( 'ABSPATH' ) || exit;

/** Small string helpers that do not depend on the optional mbstring extension. */
final class Text {

	/**
	 * First $max characters of $value, never cutting a UTF-8 character in half.
	 * Uses mbstring when present (WordPress also polyfills it); otherwise falls back to PCRE in UTF-8 mode, and for input that is
	 * not valid UTF-8 to a byte cut with any incomplete trailing sequence removed.
	 */
	public static function substr( $value, $max ) {
		$value = (string) $value;
		$max   = max( 0, (int) $max );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max, 'UTF-8' );
		}
		return self::substr_fallback( $value, $max );
	}

	/** The path taken without mbstring (public so tests can exercise it where mbstring is installed). */
	public static function substr_fallback( $value, $max ) {
		$value = (string) $value;
		$max   = max( 0, (int) $max );
		if ( 0 === $max ) {
			return '';
		}
		if ( strlen( $value ) <= $max ) {
			return $value; // At most $max bytes means at most $max characters.
		}
		if ( 1 === @preg_match( '/^.{0,' . $max . '}/us', $value, $m ) ) {
			return $m[0];
		}
		return self::trim_partial( substr( $value, 0, $max ) ); // Invalid UTF-8: byte cut without an incomplete trailing sequence.
	}

	private static function trim_partial( $cut ) {
		for ( $i = 1; $i <= 4 && $i <= strlen( $cut ); $i++ ) {
			$b = ord( $cut[ strlen( $cut ) - $i ] );
			if ( $b >= 0xC0 ) {
				$need = $b >= 0xF0 ? 4 : ( $b >= 0xE0 ? 3 : 2 );
				return $i < $need ? substr( $cut, 0, strlen( $cut ) - $i ) : $cut;
			}
			if ( $b < 0x80 ) {
				break;
			}
		}
		return $cut;
	}
}
