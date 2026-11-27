<?php
namespace UserFlo\Exclusions;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/** What a rule would cover in the recent past. Shown before a rule is applied, so the effect is explained with the site's own numbers. */
final class Impact {

	/**
	 * @param array $row  A clean rule (see Store::clean()).
	 * @param int   $days How far back to look.
	 * @return array{known:bool,pageviews:int,events:int,samples:string[],days:int}
	 */
	public static function of( array $row, $days = 30 ) {
		global $wpdb;
		$out  = array( 'known' => false, 'pageviews' => 0, 'events' => 0, 'samples' => array(), 'days' => (int) $days );
		$from = Clock::mysql( Clock::time() - (int) $days * DAY_IN_SECONDS );
		if ( 'page' === $row['target'] ) {
			$cond = Sql::rule( $row, 'p', false );
			if ( null === $cond ) {
				return $out;
			}
			$t    = Tables::pageviews();
			$out['known']     = true;
			$out['pageviews'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} p WHERE p.viewed_at >= %s AND {$cond}", $from ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$out['samples']   = array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.url FROM {$t} p WHERE p.viewed_at >= %s AND {$cond} GROUP BY p.url ORDER BY COUNT(*) DESC LIMIT 5", $from ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			return $out;
		}
		if ( 'finding' === $row['target'] ) {
			// Findings are current facts, not a period: how many of the findings on file this rule would hide right now.
			$t     = \UserFlo\Database\Tables::audit_findings();
			$where = '' !== $row['finding_type'] ? $wpdb->prepare( ' WHERE f.code = %s', $row['finding_type'] ) : '';
			$rows  = (array) $wpdb->get_results( 'SELECT f.code, p.url, e.identifier FROM ' . $t . ' f JOIN ' . \UserFlo\Database\Tables::audit_pages() . ' p ON p.id = f.page_id LEFT JOIN ' . \UserFlo\Database\Tables::audit_elements() . ' e ON e.id = f.element_id' . $where . ' LIMIT 20000', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$n     = 0;
			$pages = array();
			foreach ( $rows as $r ) {
				if ( Matcher::finding( $row, array( 'type' => $r['code'], 'url' => $r['url'], 'element' => (string) $r['identifier'] ) ) ) {
					++$n;
					$pages[ $r['url'] ] = true;
				}
			}
			$out['known']    = true;
			$out['findings'] = $n;
			$out['samples']  = array_slice( array_keys( $pages ), 0, 5 );
			return $out;
		}
		if ( 'element' === $row['target'] && 'css' !== $row['field'] ) {
			$cond = Sql::rule( $row, 'e', true );
			if ( null === $cond ) {
				return $out;
			}
			$t    = Tables::events();
			$col  = in_array( $row['field'], array( 'target', 'name', 'value', 'component' ), true ) ? $row['field'] : 'target';
			$out['known']  = true;
			$out['events'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} e WHERE e.occurred_at >= %s AND {$cond}", $from ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$out['samples'] = array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT e.{$col} FROM {$t} e WHERE e.occurred_at >= %s AND {$cond} GROUP BY e.{$col} ORDER BY COUNT(*) DESC LIMIT 5", $from ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		}
		return $out;
	}
}
