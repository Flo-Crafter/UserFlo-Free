<?php
namespace UserFlo\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the WHERE clause of the visitor list from the filters the UI offers. Every value goes through $wpdb->prepare();
 * related tables are reached with IN (SELECT visitor_id …) semi-joins so MySQL can use the existing indexes
 * (sessions: visitor_started / started_at, pageviews: url_viewed / visitor_viewed, events: type_time / visitor_time).
 */
final class VisitorFilter {

	/**
	 * Sortable columns of the visitor list. The value is the list of expressions to order by; they are fixed strings (never user input).
	 * `location`, `visitor` and `type` are built in order() because they depend on the data (country names, display names).
	 */
	const SORTS = array(
		'last_seen'  => 'v.last_seen',
		'first_seen' => 'v.first_seen',
		'sessions'   => 'v.session_count',
		'pageviews'  => 'v.pageview_count',
		'engaged'    => 'v.engaged_seconds',
		'visitor'    => '',
		'type'       => '',
		'location'   => '',
		'device'     => '',
	);

	/** Defaults for every supported filter. UserFlo Pro adds the advanced ones (`userflo_visitor_filter_defaults`). */
	public static function defaults() {
		return (array) apply_filters( 'userflo_visitor_filter_defaults', array(
			'type'    => 'all',   // all | anonymous | logged_in.
			'search'  => '',      // Visitor ID prefix, username, e-mail, display name.
			'orderby' => 'last_seen',
			'order'   => 'desc',
		) );
	}

	/** @return array{0:string,1:array} WHERE sql (without the keyword) and its values. */
	public static function where( array $f ) {
		global $wpdb;
		$f     = array_merge( self::defaults(), $f );
		$where = array( '1=1' );
		$vals  = array();

		if ( 'anonymous' === $f['type'] ) {
			$where[] = 'v.user_id IS NULL';
		} elseif ( 'logged_in' === $f['type'] ) {
			$where[] = 'v.user_id IS NOT NULL';
		}
		if ( '' !== $f['search'] ) {
			$term = $f['search'];
			$like = '%' . $wpdb->esc_like( $term ) . '%';
			// Visitor IDs are matched by prefix (unique index); accounts are matched on the small users table first.
			$uids  = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login LIKE %s OR user_email LIKE %s OR display_name LIKE %s LIMIT 500", $like, $like, $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$conds = array();
			if ( preg_match( '/^[a-f0-9]{4,32}$/i', $term ) ) {
				$conds[] = 'v.visitor_uid LIKE %s';
				$vals[]  = $wpdb->esc_like( strtolower( $term ) ) . '%';
			}
			if ( $uids ) {
				$conds[] = 'v.user_id IN (' . implode( ',', array_map( 'intval', $uids ) ) . ')';
			}
			$where[] = $conds ? '(' . implode( ' OR ', $conds ) . ')' : '1=0';
		}

		/**
		 * Lets an add-on add conditions. Receives and returns array( list of SQL conditions (aliases: v = visitors), values ).
		 *
		 * @param array $pair array( $where, $vals ).
		 * @param array $f    The filters (with every default).
		 */
		list( $where, $vals ) = apply_filters( 'userflo_visitor_filter_where', array( $where, $vals ), $f );
		return array( implode( ' AND ', $where ), $vals );
	}

	/** ORDER BY expression from whitelisted values only. Ties are always broken by newest visitor first, so pages never repeat or skip rows. */
	public static function order( array $f ) {
		$f   = array_merge( self::defaults(), $f );
		$key = isset( self::SORTS[ $f['orderby'] ] ) ? $f['orderby'] : 'last_seen';
		$dir = 'asc' === strtolower( (string) $f['order'] ) ? 'ASC' : 'DESC';
		$out = array();
		switch ( $key ) {
			case 'visitor':
				// Registered visitors by name, then anonymous ones by their id (the label shown in the list).
				$out = array( 'v.user_id IS NULL ' . $dir, "COALESCE(NULLIF(u.display_name, ''), u.user_login, v.visitor_uid) " . $dir );
				break;
			case 'type':
				$out = array( 'v.user_id IS NULL ' . $dir, 'v.session_count > 1 ' . $dir );
				break;
			case 'device':
				$out = array( 'v.device ' . $dir, 'v.browser ' . $dir, 'v.os ' . $dir );
				break;
			case 'location':
				// Sorted by the text the list shows ("City, Country", or just the country): the city when it is shown, else the country's name.
				// Visitors without a location always come last.
				$names = array();
				foreach ( \UserFlo\Geo\Countries::all() as $code => $row ) {
					if ( preg_match( '/^[A-Z]{2}$/', (string) $code ) ) {
						$names[ $code ] = \UserFlo\Geo\Countries::name( $code );
					}
				}
				$out = array( 'v.country IS NULL ASC' );
				if ( $names ) {
					$list  = "'" . implode( "','", array_map( 'esc_sql', array_keys( $names ) ) ) . "'";
					$label = "ELT(FIELD(v.country, {$list}), '" . implode( "','", array_map( 'esc_sql', array_values( $names ) ) ) . "')";
					$out[] = apply_filters( 'userflo_visitor_location_sort', $label, $label ) . ' ' . $dir; // UserFlo Pro sorts by the city when it shows one.
				}
				$out[] = 'v.country ' . $dir;
				break;
			default:
				$out = array( self::SORTS[ $key ] . ' ' . $dir );
		}
		return implode( ', ', $out ) . ', v.id DESC';
	}
}
