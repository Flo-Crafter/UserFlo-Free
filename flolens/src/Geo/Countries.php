<?php
namespace UserFlo\Geo;

defined( 'ABSPATH' ) || exit;

/** Country / continent reference data (generated file country-data.php; names localised via PHP intl when available). */
final class Countries {

	private static $data = null;

	public static function all() {
		if ( null === self::$data ) {
			self::$data = require __DIR__ . '/country-data.php';
		}
		return self::$data;
	}

	public static function continents() {
		return array(
			'AF' => __( 'Africa', 'flolens' ),
			'AN' => __( 'Antarctica', 'flolens' ),
			'AS' => __( 'Asia', 'flolens' ),
			'EU' => __( 'Europe', 'flolens' ),
			'NA' => __( 'North America', 'flolens' ),
			'OC' => __( 'Oceania', 'flolens' ),
			'SA' => __( 'South America', 'flolens' ),
		);
	}

	/** @return array|null array( name, continent, lat, lon ) */
	public static function get( $code ) {
		$a = self::all();
		$c = strtoupper( (string) $code );
		return isset( $a[ $c ] ) ? $a[ $c ] : null;
	}

	public static function name( $code ) {
		$c = strtoupper( (string) $code );
		if ( class_exists( '\Locale' ) ) {
			$n = \Locale::getDisplayRegion( '-' . $c, str_replace( '_', '-', determine_locale() ) );
			if ( $n && $n !== $c && false === strpos( $n, 'Unknown' ) ) {
				return $n;
			}
		}
		$d = self::get( $c );
		return $d ? $d[0] : $c;
	}

	public static function continent( $code ) {
		$d = self::get( $code );
		return $d ? $d[1] : '';
	}

	/** Country codes belonging to a continent. */
	public static function in_continent( $cont ) {
		$out = array();
		foreach ( self::all() as $cc => $d ) {
			if ( $d[1] === $cont ) {
				$out[] = $cc;
			}
		}
		return $out;
	}
}
