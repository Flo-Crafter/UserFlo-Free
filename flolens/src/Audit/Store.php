<?php
namespace UserFlo\Audit;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/** Reads and writes the audit tables. All SQL for the audit inventory is here. */
final class Store {

	/** Pages known to the audit (or 0 for "any"), used by the edition cap. */
	public static function page_count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Tables::audit_pages() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	public static function url_hash( $path ) {
		return md5( strtolower( (string) $path ) );
	}

	/** Adds a page to the scan queue (or returns the existing one). @return int Page id, 0 when it was not added. */
	public static function enqueue( $path, $cap = 0, $when = null ) {
		global $wpdb;
		$t    = Tables::audit_pages();
		$hash = self::url_hash( $path );
		$id   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE url_hash = %s", $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		if ( $id ) {
			return $id;
		}
		if ( $cap && self::page_count() >= $cap ) {
			return 0;
		}
		$now = Clock::now();
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t} (url_hash, url, state, first_seen, next_scan_at) VALUES (%s,%s,'queued',%s,%s)", $hash, $path, $now, null === $when ? $now : $when ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE url_hash = %s", $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	public static function page( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Tables::audit_pages() . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	public static function page_by_url( $path ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Tables::audit_pages() . ' WHERE url_hash = %s', self::url_hash( $path ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	/** Pages whose scan is due, most important (never scanned, then oldest) first. @return array[] */
	public static function due( $limit ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Tables::audit_pages() . ' WHERE next_scan_at <= %s ORDER BY (scanned_at IS NULL) DESC, next_scan_at ASC LIMIT %d', Clock::now(), (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	public static function due_count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Tables::audit_pages() . ' WHERE next_scan_at <= %s', Clock::now() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	/** Records a failed or skipped fetch; the page is tried again later with a growing delay. */
	public static function fail( $page_id, $status, $error, $state = 'error' ) {
		global $wpdb;
		$t  = Tables::audit_pages();
		$p  = self::page( $page_id );
		$n  = $p ? min( 8, (int) $p['failures'] + 1 ) : 1;
		$dt = min( DAY_IN_SECONDS, 15 * MINUTE_IN_SECONDS * ( 2 ** ( $n - 1 ) ) );
		$wpdb->update( $t, array( 'http_status' => (int) $status, 'state' => $state, 'error' => \UserFlo\Core\Text::substr( $error, 120 ), 'failures' => $n, 'scanned_at' => Clock::now(), 'next_scan_at' => Clock::mysql( Clock::time() + $dt ) ), array( 'id' => (int) $page_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	/** Pushes the next scan out without reading the page again (its content has not changed). */
	public static function touch( $page_id, $ttl ) {
		global $wpdb;
		$wpdb->update( Tables::audit_pages(), array( 'scanned_at' => Clock::now(), 'next_scan_at' => Clock::mysql( Clock::time() + (int) $ttl ), 'failures' => 0, 'state' => 'ok', 'error' => '' ), array( 'id' => (int) $page_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	/**
	 * Stores one successful scan: the page facts, its elements and where they are, and the findings, replacing what the previous scan stored.
	 *
	 * @param int   $page_id  Page id.
	 * @param array $facts    http_status, final_url, content_hash, title, description, canonical, robots.
	 * @param array $elements Parser elements, each with ekey.
	 * @param array $findings Checks results: code, ekey|null, evidence.
	 * @param int   $ttl      Seconds until the next scan.
	 */
	public static function save_scan( $page_id, array $facts, array $elements, array $findings, $ttl ) {
		global $wpdb;
		$now = Clock::now();
		$page_id = (int) $page_id;
		$el  = Tables::audit_elements();
		$loc = Tables::audit_locations();

		// Elements: one row per reusable element; the same element several times on the page is one location with a count.
		$by = array();
		foreach ( $elements as $e ) {
			if ( isset( $by[ $e['ekey'] ] ) ) {
				++$by[ $e['ekey'] ]['n'];
			} else {
				$by[ $e['ekey'] ] = $e + array( 'n' => 1 );
			}
		}
		$old = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT element_id FROM {$loc} WHERE page_id = %d", $page_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		foreach ( array_chunk( $by, 200, true ) as $chunk ) {
			$vals = array();
			foreach ( $chunk as $ekey => $e ) {
				$vals[] = $wpdb->prepare( '(%s,%s,%s,%s,%s,%s,%s,%s)', $ekey, $e['kind'], $e['label'], $e['identifier'], $e['value_sig'], $e['href'], $now, $now );
			}
			$wpdb->query( "INSERT INTO {$el} (ekey, kind, label, identifier, value_sig, href, first_seen, last_seen) VALUES " . implode( ',', $vals ) . ' ON DUPLICATE KEY UPDATE label = VALUES(label), href = VALUES(href), last_seen = VALUES(last_seen)' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		}
		$ids = array();
		foreach ( array_chunk( array_keys( $by ), 300 ) as $keys ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, ekey FROM ' . $el . ' WHERE ekey IN (' . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ')', $keys ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			foreach ( (array) $rows as $r ) {
				$ids[ $r['ekey'] ] = (int) $r['id'];
			}
		}
		$wpdb->delete( $loc, array( 'page_id' => $page_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		foreach ( array_chunk( $by, 300, true ) as $chunk ) {
			$vals = array();
			foreach ( $chunk as $ekey => $e ) {
				if ( isset( $ids[ $ekey ] ) ) {
					$vals[] = $wpdb->prepare( '(%d,%d,%d)', $ids[ $ekey ], $page_id, min( 65535, $e['n'] ) );
				}
			}
			if ( $vals ) {
				$wpdb->query( "INSERT INTO {$loc} (element_id, page_id, instances) VALUES " . implode( ',', $vals ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
			}
		}
		self::recount( array_unique( array_merge( $old, array_values( $ids ) ) ) );
		self::replace_findings( $page_id, $findings, $ids );

		$wpdb->update( Tables::audit_pages(), array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			'http_status'   => (int) $facts['http_status'],
			'final_url'     => \UserFlo\Core\Text::substr( $facts['final_url'], 500 ),
			'title'         => \UserFlo\Core\Text::substr( $facts['title'], 255 ),
			'description'   => \UserFlo\Core\Text::substr( $facts['description'], 500 ),
			'canonical'     => \UserFlo\Core\Text::substr( $facts['canonical'], 500 ),
			'robots'        => \UserFlo\Core\Text::substr( $facts['robots'], 100 ),
			'content_hash'  => $facts['content_hash'],
			'element_count' => count( $elements ),
			'metrics'       => isset( $facts['metrics'] ) ? $facts['metrics'] : null,
			'state'         => 'ok',
			'error'         => '',
			'failures'      => 0,
			'scanned_at'    => $now,
			'next_scan_at'  => Clock::mysql( Clock::time() + (int) $ttl ),
		), array( 'id' => $page_id ) );
	}

	/** Recomputes how many pages and instances each of these elements has, and drops elements that are on no page any more. */
	public static function recount( array $element_ids ) {
		global $wpdb;
		$el  = Tables::audit_elements();
		$loc = Tables::audit_locations();
		foreach ( array_chunk( array_map( 'intval', $element_ids ), 300 ) as $chunk ) {
			if ( ! $chunk ) {
				continue;
			}
			$in = implode( ',', $chunk );
			$wpdb->query( "UPDATE {$el} e SET e.pages = (SELECT COUNT(*) FROM {$loc} l WHERE l.element_id = e.id), e.instances = (SELECT COALESCE(SUM(l.instances),0) FROM {$loc} l WHERE l.element_id = e.id) WHERE e.id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->query( "DELETE FROM {$el} WHERE id IN ({$in}) AND pages = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->query( 'DELETE FROM ' . Tables::audit_findings() . " WHERE element_id IN ({$in}) AND element_id NOT IN (SELECT id FROM {$el})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		}
	}

	/** Replaces a page's findings, keeping the first-seen date of any finding that is still there. @param array $ids ekey => element id. */
	public static function replace_findings( $page_id, array $findings, array $ids ) {
		global $wpdb;
		$t    = Tables::audit_findings();
		$now  = Clock::now();
		$old  = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT element_id, code, first_seen FROM {$t} WHERE page_id = %d", (int) $page_id ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$old[ $r['element_id'] . '|' . $r['code'] ] = $r['first_seen'];
		}
		$wpdb->delete( $t, array( 'page_id' => (int) $page_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$vals = array();
		foreach ( $findings as $f ) {
			$c = Catalog::get( $f['code'] );
			if ( ! $c ) {
				continue;
			}
			$eid = null === $f['ekey'] ? 0 : ( isset( $ids[ $f['ekey'] ] ) ? $ids[ $f['ekey'] ] : -1 );
			if ( $eid < 0 ) {
				continue;
			}
			$key    = $eid . '|' . $f['code'];
			$vals[ $key ] = $wpdb->prepare( '(%d,%d,%s,%s,%d,%s,%s,%s)', (int) $page_id, $eid, $f['code'], $c['class'], $c['severity'], \UserFlo\Core\Text::substr( (string) $f['evidence'], 300 ), isset( $old[ $key ] ) ? $old[ $key ] : $now, $now );
		}
		foreach ( array_chunk( array_values( $vals ), 200 ) as $chunk ) {
			$wpdb->query( "INSERT IGNORE INTO {$t} (page_id, element_id, code, class, severity, evidence, first_seen, last_seen) VALUES " . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		}
	}

	/** Forgets every scan result (pages, elements, findings). Used by the "scan again from scratch" tool; analytics are never touched. */
	public static function reset() {
		global $wpdb;
		foreach ( array( Tables::audit_findings(), Tables::audit_locations(), Tables::audit_elements(), Tables::audit_pages() ) as $t ) {
			$wpdb->query( "TRUNCATE TABLE {$t}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own tables: names are internal constants, values are prepared.
		}
	}
}
