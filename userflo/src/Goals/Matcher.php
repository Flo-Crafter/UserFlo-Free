<?php
namespace UserFlo\Goals;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a page view or an event satisfies a goal. Pure functions, no database: the same rules are written once as PHP here (used
 * at ingest and in tests) and once as SQL in Store::condition() (used to preview and to re-evaluate history); a test keeps them in step.
 *
 * Page goals compare the recorded path. The query string is ignored unless the pattern itself contains "?". Comparison is case-insensitive,
 * and for an exact match a trailing slash makes no difference. Event goals compare the event type, and the event name (or, for downloads
 * and outbound links, the file path or destination); an empty pattern means "any event of that type".
 */
final class Matcher {

	const PAGE_MATCHES = array( 'exact', 'prefix', 'contains' );

	/** Event types whose identifying text is the value (file path, destination address); every other type uses the name. */
	const VALUE_TYPES = array( 'download', 'outbound' );

	/** @param array $goal type, match_type, pattern. */
	public static function page( array $goal, $path ) {
		if ( 'page' !== $goal['type'] ) {
			return false;
		}
		$pattern = strtolower( (string) $goal['pattern'] );
		$path    = strtolower( (string) $path );
		if ( '' === $pattern ) {
			return false;
		}
		if ( false === strpos( $pattern, '?' ) ) {
			$q    = strpos( $path, '?' );
			$path = false === $q ? $path : substr( $path, 0, $q );
		}
		switch ( $goal['match_type'] ) {
			case 'prefix':
				return 0 === strpos( $path, $pattern );
			case 'contains':
				return false !== strpos( $path, $pattern );
			default:
				return rtrim( $path, '/' ) === rtrim( $pattern, '/' ) || ( '' === rtrim( $pattern, '/' ) && '' === rtrim( $path, '/' ) );
		}
	}

	/** @param array $event type, name, value. */
	public static function event( array $goal, array $event ) {
		if ( 'event' !== $goal['type'] || $goal['event_type'] !== $event['type'] ) {
			return false;
		}
		$pattern = strtolower( (string) $goal['pattern'] );
		if ( '' === $pattern ) {
			return true;
		}
		$text = strtolower( (string) ( in_array( $event['type'], self::VALUE_TYPES, true ) ? $event['value'] : $event['name'] ) );
		return 'contains' === $goal['match_type'] ? false !== strpos( $text, $pattern ) : $text === $pattern;
	}
}
