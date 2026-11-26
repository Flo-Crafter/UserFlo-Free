<?php
namespace UserFlo\Geo;

use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * IP address -> location using an MMDB database (DB-IP "lite" downloaded by UserFlo, or any MaxMind-format file).
 * The IP address is only used in memory for the lookup; only the result (country, optional city and coordinates) is stored.
 */
final class Locator {

	/** Effective location mode: off or country. City level is added by UserFlo Pro (`userflo_geo_mode`); stored city data is kept for it. */
	public static function mode() {
		$m = (string) Settings::get( 'geo_mode' );
		return (string) apply_filters( 'userflo_geo_mode', 'city' === $m ? 'country' : $m, $m );
	}

	private static $reader = null;
	private static $path   = null;

	/** @return string '' when geolocation is off or no database is available. */
	public static function database_path() {
		$mode = self::mode();
		if ( 'off' === $mode ) {
			return '';
		}
		if ( 'custom' === Settings::get( 'geo_source' ) ) {
			$p = (string) Settings::get( 'geo_custom_path' );
			return ( '' !== $p && is_readable( $p ) ) ? $p : '';
		}
		$p = Database::file();
		return is_readable( $p ) ? $p : '';
	}

	private static function reader( $path ) {
		if ( self::$reader && self::$path === $path ) {
			return self::$reader;
		}
		require_once USERFLO_DIR . 'lib/maxmind-db/MaxMind/Db/Reader/InvalidDatabaseException.php';
		require_once USERFLO_DIR . 'lib/maxmind-db/MaxMind/Db/Reader/Util.php';
		require_once USERFLO_DIR . 'lib/maxmind-db/MaxMind/Db/Reader/Metadata.php';
		require_once USERFLO_DIR . 'lib/maxmind-db/MaxMind/Db/Reader/Decoder.php';
		require_once USERFLO_DIR . 'lib/maxmind-db/MaxMind/Db/Reader.php';
		self::$reader = new \MaxMind\Db\Reader( $path );
		self::$path   = $path;
		return self::$reader;
	}

	public static function reset() {
		self::$reader = null;
		self::$path   = null;
	}

	/**
	 * @return array|null array( country (ISO2), city, lat, lon ); city/lat/lon are empty in country mode.
	 */
	public static function lookup( $ip ) {
		if ( '' === (string) $ip || ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return null;
		}
		$path = self::database_path();
		if ( '' === $path ) {
			return null;
		}
		try {
			$rec = self::reader( $path )->get( $ip );
		} catch ( \Throwable $e ) {
			return null;
		}
		return self::normalise( $rec, 'city' === self::mode() );
	}

	/** Accepts MaxMind/DB-IP city or country records and the flat "ip-location-db" layout. */
	public static function normalise( $rec, $with_city ) {
		if ( ! is_array( $rec ) ) {
			return null;
		}
		$cc = '';
		if ( isset( $rec['country']['iso_code'] ) ) {
			$cc = $rec['country']['iso_code'];
		} elseif ( isset( $rec['registered_country']['iso_code'] ) ) {
			$cc = $rec['registered_country']['iso_code'];
		} elseif ( isset( $rec['country_code'] ) ) {
			$cc = $rec['country_code'];
		}
		$cc = strtoupper( (string) $cc );
		if ( ! preg_match( '/^[A-Z]{2}$/', $cc ) ) {
			return null;
		}
		$out = array( 'country' => $cc, 'city' => '', 'lat' => null, 'lon' => null );
		/** UserFlo Pro reads the city and its coordinates from the record when the mode is city. */
		return apply_filters( 'userflo_geo_record', $out, $rec, (bool) $with_city );
	}
}
