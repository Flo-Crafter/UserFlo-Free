<?php
namespace UserFlo\Export;

defined( 'ABSPATH' ) || exit;

/** Validated export filters. Built only through from_array() so every value is sanitised. */
final class Filters {

	const ANON_NONE   = 'none';
	const ANON_STRIP  = 'strip';  // Omit user ids and IP addresses.
	const ANON_PSEUDO = 'pseudo'; // Strip, and replace visitor/session identifiers with keyed hashes.

	/** @var string|null UTC datetime, inclusive. */
	public $from = null;
	/** @var string|null UTC datetime, exclusive. */
	public $to = null;
	/** @var int */
	public $visitor_id = 0;
	/** @var string[] */
	public $event_types = array();
	/** @var string */
	public $anonymise = self::ANON_STRIP;

	/** @param array $in Raw input (dates are site-local YYYY-MM-DD). */
	public static function from_array( array $in ) {
		$f = new self();
		$f->from       = self::day( isset( $in['from'] ) ? $in['from'] : '', false );
		$f->to         = self::day( isset( $in['to'] ) ? $in['to'] : '', true );
		$f->visitor_id = isset( $in['visitor'] ) ? max( 0, (int) $in['visitor'] ) : 0;
		$types         = isset( $in['types'] ) ? ( is_array( $in['types'] ) ? $in['types'] : explode( ',', (string) $in['types'] ) ) : array();
		foreach ( $types as $t ) {
			$t = preg_replace( '/[^a-z_]/', '', strtolower( (string) $t ) );
			if ( '' !== $t && strlen( $t ) <= 20 ) {
				$f->event_types[ $t ] = $t;
			}
		}
		$f->event_types = array_slice( array_values( $f->event_types ), 0, 20 );
		$a              = isset( $in['anonymise'] ) ? $in['anonymise'] : self::ANON_STRIP;
		$f->anonymise   = true === $a ? self::ANON_STRIP : ( in_array( $a, array( self::ANON_NONE, self::ANON_STRIP, self::ANON_PSEUDO ), true ) ? $a : self::ANON_STRIP );
		return $f;
	}

	public function to_array() {
		return array( 'from' => $this->from, 'to' => $this->to, 'visitor' => $this->visitor_id, 'types' => $this->event_types, 'anonymise' => $this->anonymise );
	}

	public static function from_stored( array $a ) {
		$f             = new self();
		$f->from       = isset( $a['from'] ) ? $a['from'] : null;
		$f->to         = isset( $a['to'] ) ? $a['to'] : null;
		$f->visitor_id = isset( $a['visitor'] ) ? (int) $a['visitor'] : 0;
		$f->event_types = isset( $a['types'] ) ? (array) $a['types'] : array();
		$f->anonymise  = isset( $a['anonymise'] ) ? $a['anonymise'] : self::ANON_STRIP;
		return $f;
	}

	private static function day( $ymd, $end ) {
		if ( ! is_string( $ymd ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
			return null;
		}
		$dt = date_create_immutable( $ymd . ' 00:00:00', wp_timezone() );
		if ( ! $dt ) {
			return null;
		}
		if ( $end ) {
			$dt = $dt->modify( '+1 day' );
		}
		return gmdate( 'Y-m-d H:i:s', $dt->getTimestamp() );
	}
}
