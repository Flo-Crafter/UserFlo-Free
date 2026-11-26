<?php
namespace UserFlo\Export;

defined( 'ABSPATH' ) || exit;

/** Keyset-paginated reads (never OFFSET, never the whole table in memory). */
final class Query {

	const MAX_BATCH = 1000;

	private static function where( array $d, Filters $f, $after_id ) {
		$where = array( 'id > %d' );
		$vals  = array( (int) $after_id );
		if ( $f->from ) {
			$where[] = "{$d['time']} >= %s";
			$vals[]  = $f->from;
		}
		if ( $f->to ) {
			$where[] = "{$d['time']} < %s";
			$vals[]  = $f->to;
		}
		if ( $f->visitor_id ) {
			$where[] = "{$d['visitor_col']} = %d";
			$vals[]  = $f->visitor_id;
		}
		if ( $f->event_types && isset( $d['type_col'] ) ) {
			$where[] = $d['type_col'] . ' IN (' . implode( ',', array_fill( 0, count( $f->event_types ), '%s' ) ) . ')';
			$vals    = array_merge( $vals, $f->event_types );
		}
		return array( implode( ' AND ', $where ), $vals );
	}

	/** Interactions and page views a rule leaves out of statistics are left out of dataset exports too (the personal-data export is separate and complete). */
	private static function skip( $key ) {
		return 'events' === $key ? \UserFlo\Exclusions\Sql::events( 't' ) : ( 'pageviews' === $key ? \UserFlo\Exclusions\Sql::pageviews( 't' ) : '' );
	}

	/** @return array[] Rows keyed by column (already anonymised). */
	public static function batch( $key, Filters $f, $after_id, $limit = self::MAX_BATCH ) {
		global $wpdb;
		$d = Datasets::get( $key );
		if ( ! $d ) {
			return array();
		}
		$cols            = Datasets::columns( $key, $f->anonymise );
		list( $w, $vals ) = self::where( $d, $f, $after_id );
		$vals[]          = max( 1, min( self::MAX_BATCH, (int) $limit ) );
		$rows            = $wpdb->get_results( $wpdb->prepare( 'SELECT ' . implode( ',', $cols ) . " FROM {$d['table']} t WHERE {$w}" . self::skip( $key ) . ' ORDER BY id ASC LIMIT %d', $vals ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
		if ( Filters::ANON_PSEUDO === $f->anonymise && $d['pseudo'] ) {
			foreach ( $rows as &$row ) {
				foreach ( $d['pseudo'] as $c ) {
					if ( isset( $row[ $c ] ) ) {
						$row[ $c ] = substr( hash_hmac( 'sha256', $row[ $c ], wp_salt( 'auth' ) ), 0, 16 );
					}
				}
			}
			unset( $row );
		}
		return $rows;
	}

	public static function count( $key, Filters $f ) {
		global $wpdb;
		$d = Datasets::get( $key );
		if ( ! $d ) {
			return 0;
		}
		list( $w, $vals ) = self::where( $d, $f, 0 );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$d['table']} t WHERE {$w}" . self::skip( $key ), $vals ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own tables: names are internal constants, values are prepared.
	}
}
