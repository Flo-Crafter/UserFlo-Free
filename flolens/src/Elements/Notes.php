<?php
namespace UserFlo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Names and notes the administrator gives to elements, so several buttons that carry the same label ("Downloads") can be told apart.
 * Stored in one option, keyed by the element's tracker identity (the same key that joins an element to its recorded interactions), so a note
 * survives re-scans and also works for elements the scan did not find. Display only: nothing here changes what is tracked or counted.
 */
final class Notes {

	const OPTION    = 'userflo_element_notes';
	const MAX       = 500;
	const MAX_ALIAS = 80;
	const MAX_NOTE  = 300;

	/** @return array<string,array{alias:string,note:string}> */
	public static function all() {
		$o = get_option( self::OPTION, array() );
		return is_array( $o ) ? $o : array();
	}

	/** The stored key for an element: a short hash of its tracker identity. */
	public static function key( $kind, $identifier, $value, $label ) {
		return substr( md5( Stats::key( (string) $kind, (string) $identifier, (string) $value, (string) $label ) ), 0, 16 );
	}

	public static function clean( $s, $max ) {
		$s = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $s ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, $max ) : substr( $s, 0, $max );
	}

	/** Sets (or, with both empty, removes) the name and note of one element. @return true|\WP_Error */
	public static function set( $key, $alias, $note ) {
		if ( ! is_string( $key ) || ! preg_match( '/^[a-f0-9]{16}$/', $key ) ) {
			return new \WP_Error( 'userflo_bad_key', __( 'That element could not be identified.', 'flolens' ), array( 'status' => 400 ) );
		}
		$all   = self::all();
		$alias = self::clean( $alias, self::MAX_ALIAS );
		$note  = self::clean( $note, self::MAX_NOTE );
		if ( '' === $alias && '' === $note ) {
			unset( $all[ $key ] );
		} else {
			if ( ! isset( $all[ $key ] ) && count( $all ) >= self::MAX ) {
				return new \WP_Error( 'userflo_notes_full', sprintf( /* translators: %d: maximum */ __( 'You can name or annotate up to %d elements. Clear some first.', 'flolens' ), self::MAX ), array( 'status' => 400 ) );
			}
			$all[ $key ] = array( 'alias' => $alias, 'note' => $note );
		}
		update_option( self::OPTION, $all, false );
		return true;
	}

	/** Adds `key`, `alias` and `note` to an element row. */
	public static function decorate( array $row, array $all = null ) {
		$all = null === $all ? self::all() : $all;
		$k   = self::key( $row['kind'], $row['identifier'], $row['value'], $row['label'] );
		$row['key']   = $k;
		$row['alias'] = isset( $all[ $k ] ) ? $all[ $k ]['alias'] : '';
		$row['note']  = isset( $all[ $k ] ) ? $all[ $k ]['note'] : '';
		return $row;
	}
}
