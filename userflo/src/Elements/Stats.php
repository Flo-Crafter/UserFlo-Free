<?php
namespace UserFlo\Elements;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;
use UserFlo\Exclusions\Sql;

defined( 'ABSPATH' ) || exit;

/**
 * Elements: the audit's inventory joined to the interactions UserFlo already records. No new tracker and no new events: an interaction belongs
 * to an element when its recorded identifier (`uf_events.target`) and, for links, files and media, its destination (`value`) match the
 * element's. Two elements the tracker cannot tell apart (same identifier and address) share their numbers; the screens say so.
 *
 * Every query goes through the Exclusions filter, so interactions left out of statistics are left out here too.
 */
final class Stats {

	const MAX_ROWS = 5000;

	/** Event type per inventory kind, for the "unlisted" rows and the filters. */
	const KIND_TYPES = array( 'link' => 'click', 'button' => 'click', 'anchor' => 'click', 'control' => 'click', 'contact' => 'click', 'external' => 'outbound', 'file' => 'download', 'form' => 'form', 'media' => 'media' );

	/** The key an element and its events meet on. */
	public static function key( $kind, $identifier, $value, $label ) {
		$by_label = in_array( $kind, array( 'button', 'control', 'anchor' ), true );
		return strtolower( $identifier . '|' . ( 'media' === $kind || in_array( $kind, array( 'link', 'external', 'file', 'contact' ), true ) ? $value : '' ) . '|' . ( $by_label ? $label : '' ) );
	}

	/** Kind of an interaction the inventory does not know (a button added by script, for instance). */
	private static function unlisted_kind( $type, $value ) {
		switch ( $type ) {
			case 'outbound':
				return 'external';
			case 'download':
				return 'file';
			case 'form':
				return 'form';
			case 'media':
				return 'media';
			default:
				return in_array( $value, array( 'mailto:', 'tel:' ), true ) ? 'contact' : ( '' === $value ? 'button' : 'link' );
		}
	}

	/** SQL for "who": all, anonymous or logged-in visitors (an account on the session or on the visitor). */
	private static function audience_sql( $audience ) {
		if ( 'logged_in' === $audience ) {
			return ' AND (s.user_id IS NOT NULL OR vis.user_id IS NOT NULL)';
		}
		if ( 'anonymous' === $audience ) {
			return ' AND s.user_id IS NULL AND vis.user_id IS NULL';
		}
		return '';
	}

	/**
	 * Interactions grouped the way elements are keyed.
	 *
	 * @param string $from     UTC mysql datetime.
	 * @param string $to       UTC mysql datetime, exclusive.
	 * @param string $audience all|anonymous|logged_in.
	 * @param string $page     Page path to restrict to ('' for the whole site).
	 * @return array<string,array> key => n, visitors, logged_n, logged_v, type, value, name, target.
	 */
	public static function interactions( $from, $to, $audience = 'all', $page = '' ) {
		global $wpdb;
		$e   = Tables::events();
		$s   = Tables::sessions();
		$v   = Tables::visitors();
		$pv  = Tables::pageviews();
		$join = '' === $page ? '' : " JOIN {$pv} pg ON pg.id = e.pageview_id";
		$page_sql = '' === $page ? '' : $wpdb->prepare( ' AND pg.url = %s', $page );
		$logged = '(s.user_id IS NOT NULL OR vis.user_id IS NOT NULL)';
		$sql = "SELECT e.target, e.type, e.value, e.name, COUNT(*) AS n, COUNT(DISTINCT e.visitor_id) AS vis_n, SUM({$logged}) AS li_n, COUNT(DISTINCT CASE WHEN {$logged} THEN e.visitor_id END) AS li_v
			FROM {$e} e JOIN {$s} s ON s.id = e.session_id LEFT JOIN {$v} vis ON vis.id = e.visitor_id{$join}
			WHERE e.type IN ('click','download','outbound','form','media') AND e.target <> '' AND e.occurred_at >= %s AND e.occurred_at < %s" . self::audience_sql( $audience ) . $page_sql . Sql::events( 'e' ) . '
			GROUP BY e.target, e.type, e.value, e.name ORDER BY n DESC LIMIT ' . self::MAX_ROWS;
		$out = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $from, $to ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$kind = self::unlisted_kind( $r['type'], $r['value'] );
			$key  = self::key( $kind, $r['target'], $r['value'], $r['name'] );
			if ( isset( $out[ $key ] ) ) {
				$out[ $key ]['n']   += (int) $r['n'];
				$out[ $key ]['vis_n'] = max( $out[ $key ]['vis_n'], (int) $r['vis_n'] ); // Distinct counts cannot be summed across groups; the larger is a safe lower bound.
				$out[ $key ]['li_n'] += (int) $r['li_n'];
				$out[ $key ]['li_v'] = max( $out[ $key ]['li_v'], (int) $r['li_v'] );
				continue;
			}
			$out[ $key ] = array( 'n' => (int) $r['n'], 'vis_n' => (int) $r['vis_n'], 'li_n' => (int) $r['li_n'], 'li_v' => (int) $r['li_v'], 'type' => $r['type'], 'value' => $r['value'], 'name' => $r['name'], 'target' => $r['target'], 'kind' => $kind );
		}
		return $out;
	}

	/**
	 * The elements list.
	 *
	 * @param array $a from, to (UTC mysql), audience, kind, page, search, sort, dir, page_no, per_page, unlisted.
	 */
	public static function listing( array $a ) {
		global $wpdb;
		$el   = Tables::audit_elements();
		$loc  = Tables::audit_locations();
		$ap   = Tables::audit_pages();
		$where = array();
		$args  = array();
		if ( '' !== $a['kind'] ) {
			$where[] = 'e.kind = %s';
			$args[]  = $a['kind'];
		}
		$join = '';
		if ( '' !== $a['page'] ) {
			$join    = " JOIN {$loc} l ON l.element_id = e.id JOIN {$ap} p ON p.id = l.page_id";
			$where[] = 'p.url = %s';
			$args[]  = $a['page'];
		}
		$sql  = "SELECT e.id, e.kind, e.label, e.identifier, e.value_sig, e.href, e.pages, e.instances FROM {$el} e{$join}" . ( $where ? ' WHERE ' . implode( ' AND ', $where ) : '' ) . ' ORDER BY e.id LIMIT ' . self::MAX_ROWS;
		$rows = (array) ( $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$act  = self::interactions( $a['from'], $a['to'], $a['audience'], $a['page'] );
		$items = array();
		$used  = array();
		$notes  = Notes::all();
		$needle = strtolower( (string) $a['search'] );
		$found  = static function ( array $parts ) use ( $needle ) {
			foreach ( $parts as $p ) {
				if ( '' !== (string) $p && false !== strpos( strtolower( (string) $p ), $needle ) ) {
					return true;
				}
			}
			return false;
		};
		foreach ( $rows as $r ) {
			$k = self::key( $r['kind'], $r['identifier'], $r['value_sig'], $r['label'] );
			$n = $notes[ Notes::key( $r['kind'], $r['identifier'], $r['value_sig'], $r['label'] ) ] ?? array( 'alias' => '', 'note' => '' );
			if ( '' !== $needle && ! $found( array( $r['label'], $r['identifier'], $r['href'], $n['alias'], $n['note'] ) ) ) {
				continue;
			}
			$x = isset( $act[ $k ] ) ? $act[ $k ] : null;
			$used[ $k ] = true;
			$items[] = array(
				'id' => (int) $r['id'], 'inventoried' => true, 'kind' => $r['kind'], 'label' => $r['label'], 'identifier' => $r['identifier'], 'value' => $r['value_sig'], 'href' => $r['href'],
				'pages' => (int) $r['pages'], 'instances' => (int) $r['instances'],
				'interactions' => $x ? $x['n'] : 0, 'visitors' => $x ? $x['vis_n'] : 0, 'logged_in' => $x ? $x['li_n'] : 0, 'anonymous' => $x ? $x['n'] - $x['li_n'] : 0,
			);
		}
		// Interactions on elements the scan did not find (added by script, or on pages not scanned yet).
		if ( ! empty( $a['unlisted'] ) ) {
			foreach ( $act as $k => $x ) {
				if ( isset( $used[ $k ] ) || ( '' !== $a['kind'] && $a['kind'] !== $x['kind'] ) ) {
					continue;
				}
				if ( '' !== $needle ) {
					$n = $notes[ Notes::key( $x['kind'], $x['target'], $x['value'], $x['name'] ) ] ?? array( 'alias' => '', 'note' => '' );
					if ( ! $found( array( $x['name'], $x['target'], $x['value'], $n['alias'], $n['note'] ) ) ) {
						continue;
					}
				}
				$items[] = array(
					'id' => 0, 'inventoried' => false, 'kind' => $x['kind'], 'label' => $x['name'], 'identifier' => $x['target'], 'value' => $x['value'], 'href' => '', 'pages' => 0, 'instances' => 0,
					'interactions' => $x['n'], 'visitors' => $x['vis_n'], 'logged_in' => $x['li_n'], 'anonymous' => $x['n'] - $x['li_n'],
				);
			}
		}
		$sort = $a['sort'];
		$m    = 'asc' === $a['dir'] ? 1 : -1;
		usort( $items, static function ( $p, $q ) use ( $sort, $m ) {
			switch ( $sort ) {
				case 'label':
					return $m * strcmp( strtolower( $p['label'] ), strtolower( $q['label'] ) );
				case 'pages':
					return ( $m * ( $p['pages'] <=> $q['pages'] ) ) ?: ( $q['interactions'] <=> $p['interactions'] );
				case 'visitors':
					return ( $m * ( $p['visitors'] <=> $q['visitors'] ) ) ?: ( $q['interactions'] <=> $p['interactions'] );
				default:
					return ( $m * ( $p['interactions'] <=> $q['interactions'] ) ) ?: strcmp( $p['label'], $q['label'] );
			}
		} );
		$total = count( $items );
		$per   = max( 1, (int) $a['per_page'] );
		return array( 'items' => array_slice( $items, ( max( 1, (int) $a['page_no'] ) - 1 ) * $per, $per ), 'total' => $total, 'page' => (int) $a['page_no'], 'per_page' => $per );
	}

	/** Totals per kind for the filter chips: elements in the inventory and interactions in the period. */
	public static function kinds() {
		global $wpdb;
		$out = array();
		foreach ( (array) $wpdb->get_results( 'SELECT kind, COUNT(*) AS n FROM ' . Tables::audit_elements() . ' GROUP BY kind', ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$out[ $r['kind'] ] = (int) $r['n'];
		}
		return $out;
	}

	public static function element( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Tables::audit_elements() . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	/**
	 * Everything about one inventoried element for a period: the totals, the audience split, a daily trend and the pages it is on with the
	 * interactions on each.
	 */
	public static function detail( array $el, $from, $to, $audience, $off_seconds ) {
		global $wpdb;
		$e   = Tables::events();
		$s   = Tables::sessions();
		$v   = Tables::visitors();
		$pv  = Tables::pageviews();
		$cond = self::match_sql( $el );
		$base = "FROM {$e} e JOIN {$s} s ON s.id = e.session_id LEFT JOIN {$v} vis ON vis.id = e.visitor_id WHERE e.occurred_at >= %s AND e.occurred_at < %s AND {$cond}" . self::audience_sql( $audience ) . Sql::events( 'e' );
		$logged = '(s.user_id IS NOT NULL OR vis.user_id IS NOT NULL)';
		$tot = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, COUNT(DISTINCT e.visitor_id) AS v, COALESCE(SUM({$logged}),0) AS li_n, COUNT(DISTINCT CASE WHEN {$logged} THEN e.visitor_id END) AS li_v, COUNT(DISTINCT e.session_id) AS sess {$base}", $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own tables: names are internal constants, values are prepared.
		$trend = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT DATE(DATE_ADD(e.occurred_at, INTERVAL %d SECOND)) AS d, COUNT(*) AS n {$base} GROUP BY d ORDER BY d", (int) $off_seconds, $from, $to ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Own tables: names are internal constants, values are prepared.
			$trend[ $r['d'] ] = (int) $r['n'];
		}
		$pages = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT pg.url, COUNT(*) AS n FROM {$e} e JOIN {$s} s ON s.id = e.session_id LEFT JOIN {$v} vis ON vis.id = e.visitor_id JOIN {$pv} pg ON pg.id = e.pageview_id WHERE e.occurred_at >= %s AND e.occurred_at < %s AND {$cond}" . self::audience_sql( $audience ) . Sql::events( 'e' ) . ' GROUP BY pg.url ORDER BY n DESC LIMIT 10', $from, $to ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
			$pages[] = array( 'url' => $r['url'], 'interactions' => (int) $r['n'] );
		}
		$locs = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT p.url, p.title, l.instances FROM ' . Tables::audit_locations() . ' l JOIN ' . Tables::audit_pages() . ' p ON p.id = l.page_id WHERE l.element_id = %d ORDER BY p.url LIMIT 200', (int) $el['id'] ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$locs[] = array( 'url' => $r['url'], 'title' => $r['title'], 'instances' => (int) $r['instances'] );
		}
		return array(
			'interactions' => (int) $tot['n'], 'visitors' => (int) $tot['v'], 'sessions' => (int) $tot['sess'], 'logged_in' => (int) $tot['li_n'], 'anonymous' => (int) $tot['n'] - (int) $tot['li_n'],
			'logged_in_visitors' => (int) $tot['li_v'], 'trend' => $trend, 'pages_used' => $pages, 'locations' => $locs,
		);
	}

	/** SQL (prepared) selecting the events that belong to an inventoried element. */
	public static function match_sql( array $el ) {
		global $wpdb;
		$kind = $el['kind'];
		$sql  = $wpdb->prepare( 'e.target = %s', $el['identifier'] );
		if ( in_array( $kind, array( 'link', 'external', 'file', 'contact', 'media' ), true ) ) {
			$sql .= $wpdb->prepare( ' AND e.value = %s', $el['value_sig'] );
		} elseif ( in_array( $kind, array( 'button', 'control', 'anchor' ), true ) ) {
			$sql .= $wpdb->prepare( ' AND e.name = %s', $el['label'] );
		}
		return '(' . $sql . ')';
	}

	/** UTC "now" bounds helper for callers. */
	public static function now_bounds( $days ) {
		return array( Clock::mysql( Clock::time() - (int) $days * DAY_IN_SECONDS ), Clock::mysql( Clock::time() + 1 ) );
	}
}
