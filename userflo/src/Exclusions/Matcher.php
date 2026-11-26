<?php
namespace UserFlo\Exclusions;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether an exclusion rule applies to a page, a recorded interaction or an audit finding. Pure functions, no database: the same
 * rules are written once as PHP here (used at ingest, in the tracker configuration and in tests) and once as SQL in Sql (used by the
 * statistics queries); a test keeps the two in step.
 *
 * Matching is deliberately simple and cheap: exact, prefix or contains, compared case-insensitively. There are no regular expressions, so a
 * stored rule can never be slow or unsafe to evaluate. Page comparisons use the recorded path; the query string is ignored unless the pattern
 * itself contains "?", and for an exact match a trailing slash makes no difference (as for goals).
 */
final class Matcher {

	const MODES   = array( 'stop', 'stats', 'audit' );
	const TARGETS = array( 'page', 'element', 'finding' );
	const MATCHES = array( 'exact', 'prefix', 'contains' );

	/**
	 * Which recorded value an element rule compares. `target` is the stable element identifier the tracker builds (tag, id or class, parents),
	 * `name` the visible label, `value` the destination (link address, file path), `component` the section it sits in. `css` is a selector the
	 * browser applies itself; it cannot be evaluated later on the server, so it only ever stops tracking.
	 */
	const ELEMENT_FIELDS = array( 'target', 'name', 'value', 'component', 'css' );

	/** @param string $match exact|prefix|contains. */
	public static function text( $match, $pattern, $subject ) {
		$pattern = strtolower( (string) $pattern );
		$subject = strtolower( (string) $subject );
		if ( '' === $pattern ) {
			return false;
		}
		switch ( $match ) {
			case 'prefix':
				return 0 === strpos( $subject, $pattern );
			case 'contains':
				return false !== strpos( $subject, $pattern );
			default:
				return $subject === $pattern;
		}
	}

	/** Whether a path (as recorded, possibly with a short query) satisfies a page pattern. */
	public static function path( $match, $pattern, $path ) {
		$pattern = strtolower( (string) $pattern );
		$path    = strtolower( (string) $path );
		if ( '' === $pattern ) {
			return false;
		}
		if ( false === strpos( $pattern, '?' ) ) {
			$q    = strpos( $path, '?' );
			$path = false === $q ? $path : substr( $path, 0, $q );
		}
		if ( 'exact' === $match ) {
			return rtrim( $path, '/' ) === rtrim( $pattern, '/' ) || ( '/' === $path && '/' === $pattern );
		}
		return self::text( $match, $pattern, $path );
	}

	/** True when a rule limited to pages under `page_scope` applies on this path. An empty scope means everywhere. */
	public static function in_scope( array $rule, $path ) {
		return '' === (string) $rule['page_scope'] || 0 === strpos( strtolower( (string) $path ), strtolower( (string) $rule['page_scope'] ) );
	}

	/** @param array $rule A page rule (target=page). */
	public static function page( array $rule, $path ) {
		return 'page' === $rule['target'] && self::path( $rule['match_type'], $rule['pattern'], $path );
	}

	/**
	 * @param array  $rule An element rule (target=element, field not css).
	 * @param array  $ev   Recorded interaction: type, name, target, value, comp.
	 * @param string $path Path of the page it happened on.
	 */
	public static function element( array $rule, array $ev, $path ) {
		if ( 'element' !== $rule['target'] || 'css' === $rule['field'] ) {
			return false;
		}
		if ( '' !== $rule['event_type'] && $rule['event_type'] !== $ev['type'] ) {
			return false;
		}
		if ( ! self::in_scope( $rule, $path ) ) {
			return false;
		}
		$col = 'component' === $rule['field'] ? 'comp' : $rule['field'];
		return self::text( $rule['match_type'], $rule['pattern'], isset( $ev[ $col ] ) ? $ev[ $col ] : '' );
	}

	/**
	 * Audit findings. A finding is array( 'type' => code, 'url' => path, 'element' => identifier ). A rule suppresses it when the finding type
	 * agrees (an empty type on the rule means every type), the page is in scope, and, if the rule names an element, the identifier matches.
	 */
	public static function finding( array $rule, array $finding ) {
		if ( 'finding' !== $rule['target'] ) {
			return false;
		}
		if ( '' !== $rule['finding_type'] && $rule['finding_type'] !== ( isset( $finding['type'] ) ? $finding['type'] : '' ) ) {
			return false;
		}
		if ( ! self::in_scope( $rule, isset( $finding['url'] ) ? $finding['url'] : '' ) ) {
			return false;
		}
		if ( '' === $rule['pattern'] ) {
			return true; // The whole type, here or site-wide depending on the scope.
		}
		return self::text( $rule['match_type'], $rule['pattern'], isset( $finding['element'] ) ? $finding['element'] : '' );
	}
}
