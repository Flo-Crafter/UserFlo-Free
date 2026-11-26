<?php
namespace UserFlo\Exclusions;

use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * The SQL form of the "leave out of statistics" rules. A query that reads interactions or page views appends one of these fragments to its
 * WHERE clause, so excluded data never reaches a total, ranking, rate, trend, export or journey calculation. Nothing is filtered in the browser
 * and nothing is deleted: removing or pausing a rule brings the data back at once.
 *
 * A site without such rules gets an empty string, which costs nothing. Fragments are built once per request.
 */
final class Sql {

	/** @var array<string,string> */
	private static $memo = array();

	public static function reset() {
		self::$memo = array();
	}

	/**
	 * Fragment for a query over the events table: ` AND NOT (…)`, or '' when no rule applies. Page rules reach events through the page view
	 * they belong to (an indexed primary-key lookup, evaluated only for rows a rule could match).
	 *
	 * @param string $alias Alias of the events table in the query.
	 */
	public static function events( $alias = 'e' ) {
		$key = 'e:' . $alias;
		if ( ! isset( self::$memo[ $key ] ) ) {
			self::$memo[ $key ] = self::build( $alias, true );
		}
		return self::$memo[ $key ];
	}

	/** Fragment for a query over the page views table. Only page rules apply to page views. */
	public static function pageviews( $alias = 'p' ) {
		$key = 'p:' . $alias;
		if ( ! isset( self::$memo[ $key ] ) ) {
			self::$memo[ $key ] = self::build( $alias, false );
		}
		return self::$memo[ $key ];
	}

	/**
	 * Fragment for a query over a table that stores a page address in some column (for example a session's entry and exit pages), so that
	 * the entry, exit and landing-page rankings leave excluded pages out too.
	 *
	 * @param string $alias  Table alias, or '' when the query has none.
	 * @param string $column Column holding the recorded page path.
	 */
	public static function urls( $alias, $column ) {
		$key = 'u:' . $alias . ':' . $column;
		if ( ! isset( self::$memo[ $key ] ) ) {
			global $wpdb;
			$parts = array();
			foreach ( Store::active()['stats'] as $r ) {
				if ( 'page' === $r['target'] ) {
					$cond = \UserFlo\Goals\Store::condition( array( 'type' => 'page', 'match_type' => $r['match_type'], 'pattern' => $r['pattern'] ), $alias, $column );
					if ( null !== $cond ) {
						$parts[] = $wpdb->prepare( $cond[0], $cond[1] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					}
				}
			}
			self::$memo[ $key ] = $parts ? ' AND NOT (' . implode( ' OR ', $parts ) . ')' : '';
		}
		return self::$memo[ $key ];
	}

	/** True when at least one active rule leaves data out of statistics (lets a caller skip work, e.g. a cached shortcut). */
	public static function any() {
		return (bool) Store::active()['stats'];
	}

	private static function build( $a, $events ) {
		$parts = array();
		foreach ( Store::active()['stats'] as $r ) {
			$c = self::rule( $r, $a, $events );
			if ( null !== $c ) {
				$parts[] = $c;
			}
		}
		return $parts ? ' AND NOT (' . implode( ' OR ', $parts ) . ')' : '';
	}

	/**
	 * The condition one rule describes, already prepared, or null when the rule has no SQL form here (a CSS selector, an audit finding, an
	 * element rule when the query reads page views).
	 *
	 * @param array  $r      Rule.
	 * @param string $a      Alias of the table the query reads.
	 * @param bool   $events True for the events table, false for page views.
	 */
	public static function rule( array $r, $a, $events ) {
		global $wpdb;
		if ( 'page' === $r['target'] ) {
			$cond = self::page_cond( $r, $events ? 'x' : $a );
			if ( null === $cond ) {
				return null;
			}
			return $events
				? $wpdb->prepare( 'EXISTS (SELECT 1 FROM ' . Tables::pageviews() . ' x WHERE x.id = ' . $a . '.pageview_id AND ' . $cond[0] . ')', $cond[1] ) // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own tables: names are internal constants, values are prepared.
				: $wpdb->prepare( $cond[0], $cond[1] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if ( ! $events || 'element' !== $r['target'] || 'css' === $r['field'] ) {
			return null;
		}
		$sql  = array();
		$vals = array();
		if ( '' !== $r['event_type'] ) {
			$sql[]  = "{$a}.type = %s";
			$vals[] = $r['event_type'];
		}
		$col = in_array( $r['field'], array( 'target', 'name', 'value', 'component' ), true ) ? $r['field'] : 'target';
		list( $op, $val ) = self::op( $r['match_type'], $r['pattern'] );
		$sql[]  = "LOWER({$a}.{$col}) {$op} %s";
		$vals[] = $val;
		if ( '' !== $r['page_scope'] ) {
			$sql[]  = 'EXISTS (SELECT 1 FROM ' . Tables::pageviews() . " x WHERE x.id = {$a}.pageview_id AND LOWER(x.url) LIKE %s)";
			$vals[] = $wpdb->esc_like( strtolower( $r['page_scope'] ) ) . '%';
		}
		return '(' . $wpdb->prepare( implode( ' AND ', $sql ), $vals ) . ')'; // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own tables: names are internal constants, values are prepared.
	}

	/** @return array{0:string,1:string} Operator and the value to compare with. */
	private static function op( $match, $pattern ) {
		global $wpdb;
		$p = strtolower( (string) $pattern );
		if ( 'prefix' === $match ) {
			return array( 'LIKE', $wpdb->esc_like( $p ) . '%' );
		}
		if ( 'contains' === $match ) {
			return array( 'LIKE', '%' . $wpdb->esc_like( $p ) . '%' );
		}
		return array( '=', $p );
	}

	/** A page rule expressed with the goals matcher's SQL, so "this page" means the same thing for goals and for exclusions. */
	private static function page_cond( array $r, $alias ) {
		return \UserFlo\Goals\Store::condition( array( 'type' => 'page', 'match_type' => $r['match_type'], 'pattern' => $r['pattern'] ), $alias );
	}
}
