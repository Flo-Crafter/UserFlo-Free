<?php
namespace UserFlo\Rest;

use UserFlo\Audit\A11yReport;
use UserFlo\Audit\Findings;
use UserFlo\Audit\Scanner;
use UserFlo\Audit\Seo;
use UserFlo\Audit\Store;
use UserFlo\Core\Clock;
use UserFlo\Database\Tables;
use UserFlo\Edition;

defined( 'ABSPATH' ) || exit;

/** Website audit: what has been scanned, a list of pages with their health, and "scan this page now". Reading follows the analytics capability. */
final class AuditRoutes {

	public static function register() {
		if ( ! \UserFlo\Modules::on( 'audit' ) ) {
			return;
		}
		$view = array( Routes::class, 'can_view' );
		register_rest_route( Routes::NS, '/audit/summary', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'summary' ), 'permission_callback' => $view ) );
		register_rest_route( Routes::NS, '/audit/pages', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'pages' ), 'permission_callback' => $view, 'args' => array(
			'search'   => array( 'type' => 'string', 'default' => '', 'maxLength' => 100 ),
			'health'   => array( 'type' => 'string', 'enum' => array( '', 'red', 'amber', 'green', 'unscanned' ), 'default' => '' ),
			'sort'     => array( 'type' => 'string', 'enum' => array( 'health', 'views', 'url', 'scanned' ), 'default' => 'health' ),
			'days'     => array( 'type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 366 ),
			'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 1000 ),
			'per_page' => array( 'type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100 ),
		) ) );
		register_rest_route( Routes::NS, '/audit/scan', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'scan' ), 'permission_callback' => array( __CLASS__, 'can_manage' ), 'args' => array(
			'url' => array( 'type' => 'string', 'required' => true, 'maxLength' => 500 ),
		) ) );
	}

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/** Pages by state and the health of the scanned ones, with exclusion rules applied. Cached for two minutes; rule changes clear it. */
	public static function summary() {
		$ttl = (int) apply_filters( 'userflo_overview_cache_ttl', 120 );
		$out = $ttl > 0 ? get_transient( 'userflo_aud_sum' ) : false;
		if ( ! is_array( $out ) ) {
			$out = self::compute_summary();
			if ( $ttl > 0 ) {
				set_transient( 'userflo_aud_sum', $out, $ttl );
			}
		}
		$out['due'] = Store::due_count();
		return $out;
	}

	private static function compute_summary() {
		global $wpdb;
		$t     = Tables::audit_pages();
		$rows  = (array) $wpdb->get_results( "SELECT id, state, http_status, scanned_at FROM {$t}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$pages = array( 'total' => count( $rows ), 'ok' => 0, 'queued' => 0, 'error' => 0, 'skipped' => 0 );
		$ids   = array();
		$last  = '';
		foreach ( $rows as $r ) {
			if ( isset( $pages[ $r['state'] ] ) ) {
				++$pages[ $r['state'] ];
			}
			if ( 'ok' === $r['state'] || ( 'error' === $r['state'] && (int) $r['http_status'] >= 400 ) ) {
				$ids[] = (int) $r['id'];
			}
			if ( $r['scanned_at'] && $r['scanned_at'] > $last ) {
				$last = $r['scanned_at'];
			}
		}
		$health = array( 'red' => 0, 'amber' => 0, 'green' => 0, 'unscanned' => $pages['total'] - count( $ids ) );
		$active = 0;
		$excl   = 0;
		$codes  = array();
		$a11y   = array();                          // Accessibility findings: code => count.
		$a11y_pages = array( 'issue' => array(), 'potential' => array() ); // Pages with at least one of each kind.
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			foreach ( Findings::for_pages( $chunk ) as $pid => $list ) {
				foreach ( $list as $f ) {
					if ( $f['excluded'] ) {
						++$excl;
					} else {
						++$active;
						$codes[ $f['code'] ] = isset( $codes[ $f['code'] ] ) ? $codes[ $f['code'] ] + 1 : 1;
						if ( ! empty( $f['wcag'] ) ) {
							$a11y[ $f['code'] ] = isset( $a11y[ $f['code'] ] ) ? $a11y[ $f['code'] ] + 1 : 1;
							if ( isset( $a11y_pages[ $f['detect'] ] ) ) {
								$a11y_pages[ $f['detect'] ][ $pid ] = true;
							}
						}
					}
				}
			}
		}
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$fs = Findings::for_pages( $chunk );
			foreach ( $chunk as $id ) {
				$lvl = Findings::health( isset( $fs[ $id ] ) ? $fs[ $id ] : array() );
				++$health[ 2 === $lvl ? 'red' : ( 1 === $lvl ? 'amber' : 'green' ) ];
			}
		}
		return array(
			'pages'    => $pages,
			'health'   => $health,
			'findings' => array( 'active' => $active, 'excluded' => $excl, 'by_code' => $codes ),
			'a11y'     => array(
				'pages_issue'     => count( $a11y_pages['issue'] ),
				'pages_potential' => count( $a11y_pages['potential'] ),
				'overview'        => A11yReport::overview( $a11y ),
				'manual'          => A11yReport::manual(),
			),
			'last_scan' => $last,
			'cap'      => (int) Edition::limit( 'audit_pages' ),
			'daily_cap' => Scanner::daily_cap(),
			'full'     => Findings::full(),
			'seo'      => Seo::status(),
		);
	}

	public static function pages( \WP_REST_Request $r ) {
		global $wpdb;
		$t    = Tables::audit_pages();
		$days = (int) $r['days'];
		$where = '';
		$args  = array();
		if ( '' !== (string) $r['search'] ) {
			$like  = '%' . $wpdb->esc_like( (string) $r['search'] ) . '%';
			$where = ' WHERE (url LIKE %s OR title LIKE %s)';
			$args  = array( $like, $like );
		}
		$sql  = "SELECT id, url, title, state, http_status, scanned_at, next_scan_at, error, element_count FROM {$t}" . $where . ' ORDER BY id LIMIT 5000';
		$rows = (array) ( $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$urls = array_column( $rows, 'url' );
		$h    = Findings::for_urls( $urls );
		$views = array();
		if ( $urls ) {
			$from = Clock::mysql( Clock::time() - $days * DAY_IN_SECONDS );
			foreach ( array_chunk( $urls, 400 ) as $chunk ) {
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT url, COUNT(*) AS n FROM ' . Tables::pageviews() . ' p WHERE viewed_at >= %s AND url IN (' . implode( ',', array_fill( 0, count( $chunk ), '%s' ) ) . ')' . \UserFlo\Exclusions\Sql::pageviews( 'p' ) . ' GROUP BY url', array_merge( array( $from ), $chunk ) ), ARRAY_A ) as $v ) { // phpcs:ignore WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
					$views[ $v['url'] ] = (int) $v['n'];
				}
			}
		}
		$items = array();
		foreach ( $rows as $p ) {
			$hh  = isset( $h[ $p['url'] ] ) ? $h[ $p['url'] ] : array( 'state' => $p['state'], 'level' => -1, 'red' => 0, 'amber' => 0, 'excluded' => 0 );
			$lvl = (int) $hh['level'];
			$bucket = $lvl < 0 ? 'unscanned' : ( 2 === $lvl ? 'red' : ( 1 === $lvl ? 'amber' : 'green' ) );
			if ( '' !== (string) $r['health'] && $bucket !== $r['health'] ) {
				continue;
			}
			$items[] = array(
				'url' => $p['url'], 'title' => $p['title'], 'state' => $hh['state'], 'level' => $lvl, 'bucket' => $bucket, 'red' => $hh['red'], 'amber' => $hh['amber'], 'excluded' => $hh['excluded'],
				'views' => isset( $views[ $p['url'] ] ) ? $views[ $p['url'] ] : 0, 'scanned_at' => $p['scanned_at'], 'elements' => (int) $p['element_count'], 'error' => $p['error'], 'http_status' => (int) $p['http_status'], 'findings' => isset( $h[ $p['url'] ]['findings'] ) ? $h[ $p['url'] ]['findings'] : array(),
			);
		}
		$sort = (string) $r['sort'];
		usort( $items, static function ( $a, $b ) use ( $sort ) {
			switch ( $sort ) {
				case 'views':
					return $b['views'] <=> $a['views'];
				case 'url':
					return strcmp( $a['url'], $b['url'] );
				case 'scanned':
					return strcmp( (string) $b['scanned_at'], (string) $a['scanned_at'] );
				default:
					return ( $b['level'] <=> $a['level'] ) ?: ( $b['views'] <=> $a['views'] );
			}
		} );
		$per = (int) $r['per_page'];
		return array( 'items' => array_slice( $items, ( (int) $r['page'] - 1 ) * $per, $per ), 'total' => count( $items ), 'page' => (int) $r['page'], 'per_page' => $per, 'full' => Findings::full() );
	}

	public static function scan( \WP_REST_Request $r ) {
		$res = Scanner::scan_now( (string) $r['url'] );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		delete_transient( 'userflo_aud_sum' );
		return array( 'result' => $res['result'], 'scanned_at' => $res['page']['scanned_at'], 'state' => $res['page']['state'] );
	}
}
