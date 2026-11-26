<?php
namespace UserFlo\Audit;

use UserFlo\Database\Tables;
use UserFlo\Edition;
use UserFlo\Exclusions\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Reads findings the way every screen must: with the exclusion rules applied. An excluded finding is not an issue (it does not colour the page,
 * count in a score or appear in a priority list) but it is kept and listed separately, so nothing is hidden for good.
 */
final class Findings {

	/** The Exclusions screen offers the "hide audit findings" choice only while there are findings to hide (Pro findings). */
	public static function available() {
		return \UserFlo\Modules::on( 'audit' ) && Edition::can( 'page_audit' );
	}

	/** Whether the full set of findings (not just the three basic ones) is in play. */
	public static function full() {
		return Edition::can( 'page_audit' ) && \UserFlo\Modules::on( 'page_intelligence' );
	}

	/** One stored row to the shape the screens use, plus whether a rule excludes it. */
	private static function shape( array $r ) {
		$c = Catalog::get( $r['code'] );
		if ( ! $c ) {
			return null;
		}
		$el = (int) $r['element_id'] ? array( 'id' => (int) $r['element_id'], 'label' => (string) $r['e_label'], 'identifier' => (string) $r['e_identifier'], 'kind' => (string) $r['e_kind'] ) : null;
		$excluded = Service::suppresses( array( 'type' => $r['code'], 'url' => $r['p_url'], 'element' => $el ? $el['identifier'] : '' ) );
		return array(
			'id'         => (int) $r['id'],
			'code'       => $r['code'],
			'class'      => $c['class'],
			'severity'   => $c['severity'],
			'title'      => $c['title'],
			'why'        => $c['why'],
			'fix'        => $c['fix'],
			'evidence'   => (string) $r['evidence'],
			'page_id'    => (int) $r['page_id'],
			'url'        => (string) $r['p_url'],
			'element'    => $el,
			'first_seen' => (string) $r['first_seen'],
			'last_seen'  => isset( $r['last_seen'] ) ? (string) $r['last_seen'] : '',
			'excluded'   => $excluded,
			'wcag'       => isset( $c['wcag'] ) ? A11yReport::wcag( $c['wcag'] ) : array(),
			'detect'     => isset( $c['detect'] ) ? $c['detect'] : '',
			'limits'     => isset( $c['limits'] ) ? $c['limits'] : '',
		);
	}

	private static function base_sql() {
		return 'SELECT f.*, p.url AS p_url, e.label AS e_label, e.identifier AS e_identifier, e.kind AS e_kind FROM ' . Tables::audit_findings() . ' f JOIN ' . Tables::audit_pages() . ' p ON p.id = f.page_id LEFT JOIN ' . Tables::audit_elements() . ' e ON e.id = f.element_id';
	}

	/**
	 * Findings of the given pages, with the rules applied. Findings the current edition does not show (everything beyond the basic ones in Lite)
	 * are left out here, so a downgraded site does not show them.
	 *
	 * @param int[] $page_ids Page ids.
	 * @return array<int,array[]> Page id => findings.
	 */
	public static function for_pages( array $page_ids ) {
		global $wpdb;
		$out = array();
		$ids = array_values( array_filter( array_map( 'intval', $page_ids ) ) );
		if ( ! $ids ) {
			return $out;
		}
		$rows = $wpdb->get_results( self::base_sql() . ' WHERE f.page_id IN (' . implode( ',', $ids ) . ') ORDER BY f.severity DESC, f.code, f.id', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$full = self::full();
		foreach ( (array) $rows as $r ) {
			if ( ! $full && ! in_array( $r['code'], Catalog::BASIC, true ) ) {
				continue;
			}
			$f = self::shape( $r );
			if ( $f ) {
				$out[ $f['page_id'] ][] = $f;
			}
		}
		return $out;
	}

	/** Findings about one element across the site (shown only where the edition shows the full set). */
	public static function for_element( $element_id ) {
		global $wpdb;
		if ( ! self::full() ) {
			return array();
		}
		$out = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( self::base_sql() . ' WHERE f.element_id = %d ORDER BY f.severity DESC, f.code LIMIT 100', (int) $element_id ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$f = self::shape( $r );
			if ( $f ) {
				$out[] = $f;
			}
		}
		return $out;
	}

	/** Health of one page from its active findings: level 0 green, 1 amber, 2 red. */
	public static function health( array $findings ) {
		$classes = array();
		foreach ( $findings as $f ) {
			if ( ! $f['excluded'] ) {
				$classes[] = $f['class'];
			}
		}
		return Catalog::health( $classes );
	}

	/**
	 * The health indicator for a list of page addresses (the Pages screen).
	 *
	 * @param string[] $urls Page paths.
	 * @return array<string,array> Path => state (unscanned|ok|error|skipped|queued), level, red, amber, excluded.
	 */
	public static function for_urls( array $urls ) {
		global $wpdb;
		$out = array();
		$map = array();
		foreach ( $urls as $u ) {
			$map[ Store::url_hash( $u ) ] = $u;
		}
		if ( ! $map ) {
			return $out;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, url_hash, state, http_status, scanned_at FROM ' . Tables::audit_pages() . ' WHERE url_hash IN (' . implode( ',', array_fill( 0, count( $map ), '%s' ) ) . ')', array_keys( $map ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$ids  = array();
		foreach ( (array) $rows as $r ) {
			$ids[] = (int) $r['id'];
		}
		$fs = self::for_pages( $ids );
		foreach ( (array) $rows as $r ) {
			$f    = isset( $fs[ (int) $r['id'] ] ) ? $fs[ (int) $r['id'] ] : array();
			$red  = 0;
			$amb  = 0;
			$exc  = 0;
			foreach ( $f as $x ) {
				if ( $x['excluded'] ) {
					++$exc;
				} elseif ( 'verified' === $x['class'] ) {
					++$red;
				} elseif ( 'warning' === $x['class'] ) {
					++$amb;
				}
			}
			$scanned = 'ok' === $r['state'] || ( 'error' === $r['state'] && (int) $r['http_status'] >= 400 );
			$out[ $map[ $r['url_hash'] ] ] = array(
				'id'      => (int) $r['id'],
				'state'   => $scanned ? ( 'error' === $r['state'] ? 'error' : 'ok' ) : $r['state'],
				'level'   => $scanned ? self::health( $f ) : -1,
				'red'     => $red,
				'amber'   => $amb,
				'excluded' => $exc,
				'scanned_at' => $r['scanned_at'],
				'findings' => array_map( static function ( $x ) { return array( 'code' => $x['code'], 'title' => $x['title'], 'class' => $x['class'], 'excluded' => $x['excluded'], 'evidence' => $x['evidence'] ); }, $f ),
			);
		}
		return $out;
	}
}
