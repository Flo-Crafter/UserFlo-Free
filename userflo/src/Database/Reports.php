<?php
namespace UserFlo\Database;

use UserFlo\Core\Clock;

defined( 'ABSPATH' ) || exit;

/** Read-only, bounded reporting queries used by the admin screens. */
final class Reports {

	const ACTIVE_WINDOW = 300;

	public static function overview( $from, $to = '9999-12-31 23:59:59' ) {
		global $wpdb;
		$s = Tables::sessions();
		$row = $wpdb->get_row( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				"SELECT COUNT(*) AS sessions, COUNT(DISTINCT visitor_id) AS visitors,
				COALESCE(AVG(TIMESTAMPDIFF(SECOND, started_at, last_activity_at)),0) AS avg_duration,
				COALESCE(SUM(engaged_seconds),0) AS engaged, COALESCE(SUM(user_id IS NOT NULL),0) AS logged_in,
				COALESCE(SUM(pageview_count <= 1),0) AS single_page
				FROM {$s} WHERE started_at >= %s AND started_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$from,
				$to
			),
			ARRAY_A
		);
		$row['pageviews']  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Tables::pageviews() . ' p WHERE viewed_at >= %s AND viewed_at < %s' . \UserFlo\Exclusions\Sql::pageviews( 'p' ), $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		$row['new']        = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Tables::visitors() . ' WHERE first_seen >= %s AND first_seen < %s', $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		$row['active_now'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$s} WHERE last_activity_at >= %s", Clock::mysql( Clock::time() - self::ACTIVE_WINDOW ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		return $row;
	}

	/** When the oldest recorded session started (UTC mysql datetime), or '' when there is no data. Index-only. */
	public static function first_data_at() {
		global $wpdb;
		return (string) $wpdb->get_var( 'SELECT MIN(started_at) FROM ' . Tables::sessions() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	public static function active_now() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Tables::sessions() . ' WHERE last_activity_at >= %s', Clock::mysql( Clock::time() - self::ACTIVE_WINDOW ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	/**
	 * Two phases keep this fast on large tables: first a cheap grouped count served by the (viewed_at, url) index,
	 * then the secondary statistics for just the winning URLs.
	 */
	public static function top_pages( $from, $limit = 10, $to = '9999-12-31 23:59:59' ) {
		global $wpdb;
		$pv   = Tables::pageviews();
		$top  = $wpdb->get_results( $wpdb->prepare( "SELECT url, COUNT(*) AS views FROM {$pv} p WHERE viewed_at >= %s AND viewed_at < %s" . \UserFlo\Exclusions\Sql::pageviews( 'p' ) . " GROUP BY url ORDER BY views DESC LIMIT %d", $from, $to, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		if ( ! $top ) {
			return array();
		}
		$urls = array_column( $top, 'url' );
		$in   = implode( ',', array_fill( 0, count( $urls ), '%s' ) );
		$more = $wpdb->get_results( $wpdb->prepare( "SELECT url, MAX(title) AS title, COUNT(DISTINCT visitor_id) AS visitors, COALESCE(AVG(engaged_seconds),0) AS avg_engaged FROM {$pv} WHERE viewed_at >= %s AND viewed_at < %s AND url IN ({$in}) GROUP BY url", array_merge( array( $from, $to ), $urls ) ), OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		foreach ( $top as &$row ) {
			$m                  = isset( $more[ $row['url'] ] ) ? $more[ $row['url'] ] : null;
			$row['title']       = $m ? $m->title : '';
			$row['visitors']    = $m ? (int) $m->visitors : 0;
			$row['avg_engaged'] = $m ? (float) $m->avg_engaged : 0;
		}
		return $top;
	}

	/** Sessions grouped by traffic source (referrer host). */
	public static function referrers( $from, $limit = 10, $to = '9999-12-31 23:59:59' ) {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare( 'SELECT referrer, COUNT(*) AS n FROM ' . Tables::sessions() . ' WHERE started_at >= %s AND started_at < %s GROUP BY referrer ORDER BY n DESC LIMIT 300', $from, $to ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
			ARRAY_A
		);
		$out = array();
		foreach ( $rows as $r ) {
			$ref = (string) $r['referrer'];
			if ( '' === $ref || '/' === $ref[0] ) {
				$host = __( 'Direct / internal', 'userflo' );
			} else {
				$host = (string) wp_parse_url( $ref, PHP_URL_HOST );
				$host = '' === $host ? __( 'Direct / internal', 'userflo' ) : preg_replace( '/^www\./', '', $host );
			}
			$out[ $host ] = ( isset( $out[ $host ] ) ? $out[ $host ] : 0 ) + (int) $r['n'];
		}
		arsort( $out );
		return array_slice( $out, 0, $limit, true );
	}

	/** Sessions, page views, distinct visitors, engaged seconds and one-page sessions per site-local day. */
	public static function daily( $from_ts, $to_ts ) {
		global $wpdb;
		$offset = (int) wp_timezone()->getOffset( new \DateTime( '@' . $to_ts ) );
		$from   = Clock::mysql( $from_ts );
		$to     = Clock::mysql( $to_ts );
		$sess   = $wpdb->get_results( $wpdb->prepare( 'SELECT DATE(DATE_ADD(started_at, INTERVAL %d SECOND)) AS d, COUNT(*) AS n, COUNT(DISTINCT visitor_id) AS v, COALESCE(SUM(engaged_seconds),0) AS e, COALESCE(SUM(pageview_count <= 1),0) AS sp FROM ' . Tables::sessions() . ' WHERE started_at >= %s AND started_at < %s GROUP BY d', $offset, $from, $to ), OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		$pvs    = $wpdb->get_results( $wpdb->prepare( 'SELECT DATE(DATE_ADD(viewed_at, INTERVAL %d SECOND)) AS d, COUNT(*) AS n FROM ' . Tables::pageviews() . ' p WHERE viewed_at >= %s AND viewed_at < %s' . \UserFlo\Exclusions\Sql::pageviews( 'p' ) . ' GROUP BY d', $offset, $from, $to ), OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		$out    = array();
		for ( $t = $from_ts; $t < $to_ts; $t += DAY_IN_SECONDS ) {
			$d = wp_date( 'Y-m-d', $t );
			if ( isset( $out[ $d ] ) ) {
				continue;
			}
			$out[ $d ] = array(
				'sessions'  => isset( $sess[ $d ] ) ? (int) $sess[ $d ]->n : 0,
				'pageviews' => isset( $pvs[ $d ] ) ? (int) $pvs[ $d ]->n : 0,
				'visitors'  => isset( $sess[ $d ] ) ? (int) $sess[ $d ]->v : 0, // Distinct visitors that day (a visitor on two days counts on both).
				'engaged'   => isset( $sess[ $d ] ) ? (int) $sess[ $d ]->e : 0,
				'single'    => isset( $sess[ $d ] ) ? (int) $sess[ $d ]->sp : 0,
			);
		}
		return $out;
	}

	/**
	 * Sessions per derived traffic channel (see Insights\Channels) and per device class from ONE scan of the period's sessions: grouping
	 * by referrer, device and the utm query string (empty when the entry URL has no utm parameter) keeps the number of groups small, so
	 * the channel rules run on a few hundred groups instead of every session. Sessions beyond the group limit stay unclassified; the
	 * caller shows the difference to the session total as "Other" and never guesses.
	 *
	 * @return array{channels: array<string,int>, devices: array<int,array{key:string,sessions:int}>}
	 */
	public static function traffic_mix( $from, $to ) {
		global $wpdb;
		$s    = Tables::sessions();
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT referrer, device, IF(LOCATE('utm_', entry_url) > 0, SUBSTRING(entry_url, LOCATE('?', entry_url)), '') AS q, COUNT(*) AS n FROM {$s} WHERE started_at >= %s AND started_at < %s GROUP BY referrer, device, q ORDER BY n DESC LIMIT 3000", $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$channels = array_fill_keys( \UserFlo\Insights\Channels::KEYS, 0 );
		$by_dev   = array();
		foreach ( (array) $rows as $r ) {
			$channels[ \UserFlo\Insights\Channels::classify( $r['referrer'], $r['q'], $host ) ] += (int) $r['n'];
			if ( '' !== $r['device'] ) {
				$by_dev[ $r['device'] ] = ( isset( $by_dev[ $r['device'] ] ) ? $by_dev[ $r['device'] ] : 0 ) + (int) $r['n'];
			}
		}
		arsort( $by_dev );
		$devices = array();
		foreach ( array_slice( $by_dev, 0, 6, true ) as $k => $n ) {
			$devices[] = array( 'key' => (string) $k, 'sessions' => $n );
		}
		return array( 'channels' => $channels, 'devices' => $devices );
	}

	/** Campaign groupings: key => the session columns that make a row. The first group is the default. UserFlo Pro adds the combined groupings. */
	public static function campaign_groups() {
		return (array) apply_filters( 'userflo_campaign_groups', array(
			'campaign' => array( 'cols' => array( 'utm_campaign' ) ),
			'source'   => array( 'cols' => array( 'utm_source' ) ),
			'medium'   => array( 'cols' => array( 'utm_medium' ) ),
			'content'  => array( 'cols' => array( 'utm_content' ) ),
			'term'     => array( 'cols' => array( 'utm_term' ) ),
		) );
	}

	/**
	 * Sessions by campaign value(s), from the columns filled at ingest (and by Insights\UtmBackfill for older sessions). A row needs at least
	 * one non-empty value in its group's columns; sessions without any campaign tag are reported as a total, never as a row.
	 *
	 * @param string $from  UTC mysql datetime, inclusive.
	 * @param string $to    UTC mysql datetime, exclusive.
	 * @param string $group A key of campaign_groups().
	 * @return array{rows:array,total:int,tagged:int}
	 */
	public static function campaigns( $from, $to, $group, $limit = 50 ) {
		global $wpdb;
		$groups = self::campaign_groups();
		$cols   = isset( $groups[ $group ] ) ? $groups[ $group ]['cols'] : $groups['campaign']['cols'];
		$s      = Tables::sessions();
		$list   = implode( ', ', $cols ); // Column names come from the fixed map above.
		$any    = implode( " <> '' OR ", $cols ) . " <> ''";
		// Sessions that completed any goal: only asked for when the Goals module is on and at least one goal exists, so a site without goals runs the same query as before.
		$goals  = \UserFlo\Modules::on( 'goals' ) && \UserFlo\Goals\Store::active();
		$conv   = $goals ? ', SUM(EXISTS(SELECT 1 FROM ' . Tables::goal_hits() . ' g WHERE g.session_id = s.id)) AS converted' : ', 0 AS converted';
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT {$list}, COUNT(*) AS sessions, COUNT(DISTINCT visitor_id) AS visitors, SUM(pageview_count) AS pageviews, SUM(pageview_count <= 1) AS single, SUM(engaged_seconds) AS engaged{$conv} FROM {$s} s WHERE started_at >= %s AND started_at < %s AND ({$any}) GROUP BY {$list} ORDER BY sessions DESC, {$list} LIMIT %d", $from, $to, (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$out    = array();
		foreach ( (array) $rows as $r ) {
			$n     = max( 1, (int) $r['sessions'] );
			$entry = array();
			foreach ( $cols as $c ) {
				$entry[ substr( $c, 4 ) ] = (string) $r[ $c ];
			}
			$out[] = $entry + array(
				'sessions'     => (int) $r['sessions'],
				'visitors'     => (int) $r['visitors'],
				'pages'        => round( (int) $r['pageviews'] / $n, 1 ),
				'single_rate'  => round( 100 * (int) $r['single'] / $n, 1 ),
				'engaged_secs' => (int) round( (int) $r['engaged'] / $n ),
				'converted'    => (int) $r['converted'],
			);
		}
		$tot    = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, SUM(utm_source <> '' OR utm_medium <> '' OR utm_campaign <> '' OR utm_term <> '' OR utm_content <> '') AS tagged FROM {$s} WHERE started_at >= %s AND started_at < %s", $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		return array( 'rows' => $out, 'total' => (int) $tot['n'], 'tagged' => (int) $tot['tagged'], 'has_goals' => (bool) $goals );
	}

	/** Sessions per country (only rows that have a country: geolocation must be on). */
	public static function countries( $from, $to, $limit = 8 ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT country AS k, COUNT(*) AS n FROM ' . Tables::sessions() . ' WHERE started_at >= %s AND started_at < %s AND country IS NOT NULL AND country <> %s GROUP BY country ORDER BY n DESC LIMIT %d', $from, $to, '', $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array( 'code' => (string) $r['k'], 'name' => \UserFlo\Geo\Countries::name( $r['k'] ), 'sessions' => (int) $r['n'] );
		}
		return $out;
	}

	public static function recent_sessions( $limit = 10 ) {
		global $wpdb;
		// Pick the newest rows first (index on last_activity_at), then join: avoids sorting the joined result set.
		return $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
				'SELECT s.*, v.visitor_uid, u.user_login, u.display_name FROM (SELECT * FROM ' . Tables::sessions() . ' ORDER BY last_activity_at DESC LIMIT %d) s
				JOIN ' . Tables::visitors() . ' v ON v.id = s.visitor_id
				LEFT JOIN ' . $wpdb->users . ' u ON u.ID = s.user_id
				ORDER BY s.last_activity_at DESC', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
				$limit
			)
		);
	}

	/**
	 * @param array $args Filters from VisitorFilter::defaults() plus page, per_page.
	 * @return array{rows:array,total:int}
	 */
	public static function visitors( array $args ) {
		global $wpdb;
		$v = Tables::visitors();
		list( $w, $vals ) = VisitorFilter::where( $args );
		$join   = "FROM {$v} v LEFT JOIN {$wpdb->users} u ON u.ID = v.user_id WHERE {$w}";
		$per    = max( 1, min( 100, (int) $args['per_page'] ) );
		$offset = max( 0, ( (int) $args['page'] - 1 ) * $per );

		$count_sql = "SELECT COUNT(*) {$join}";
		$total     = (int) ( $vals ? $wpdb->get_var( $wpdb->prepare( $count_sql, $vals ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$list_sql  = "SELECT v.*, u.user_login, u.user_email, u.display_name {$join} ORDER BY " . VisitorFilter::order( $args ) . ' LIMIT %d OFFSET %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows      = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $vals, array( $per, $offset ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		return array( 'rows' => $rows, 'total' => $total );
	}

	public static function visitor( $id ) {
		global $wpdb;
		return $wpdb->get_row( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				'SELECT v.*, u.user_login, u.user_email, u.display_name FROM ' . Tables::visitors() . ' v LEFT JOIN ' . $wpdb->users . ' u ON u.ID = v.user_id WHERE v.id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
				$id
			)
		);
	}

	/**
	 * A visitor's sessions, newest first. Under Lite only the most recent `visitor_sessions` are inspectable: `total` is then the
	 * number that can be browsed, `real_total` what is stored (nothing is deleted or hidden from Pro), and `cap` the limit applied.
	 */
	public static function visitor_sessions( $visitor_id, $page = 1, $per = 20 ) {
		global $wpdb;
		$s          = Tables::sessions();
		$real_total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$s} WHERE visitor_id = %d", $visitor_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$cap        = \UserFlo\Edition::can( 'history_extended' ) ? 0 : \UserFlo\Edition::limit( 'visitor_sessions' );
		$off        = max( 0, ( $page - 1 ) * $per );
		$total      = $cap ? min( $real_total, $cap ) : $real_total;
		if ( $cap ) {
			$per = max( 0, min( $per, $cap - $off ) );
		}
		$rows = $per > 0 ? $wpdb->get_results( $wpdb->prepare( "SELECT s.*, u.user_login FROM {$s} s LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id WHERE s.visitor_id = %d ORDER BY s.started_at DESC LIMIT %d OFFSET %d", $visitor_id, $per, $off ) ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		return array( 'rows' => $rows, 'total' => $total, 'real_total' => $real_total, 'cap' => $cap );
	}

	/** Whether a session is among its visitor's most recent `visitor_sessions` (the Lite window). Same ordering as visitor_sessions(). */
	public static function session_in_recent_window( $session ) {
		global $wpdb;
		$cap = \UserFlo\Edition::limit( 'visitor_sessions' );
		if ( ! $cap ) {
			return true;
		}
		$newer = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Tables::sessions() . ' WHERE visitor_id = %d AND (started_at > %s OR (started_at = %s AND id > %d))', (int) $session->visitor_id, $session->started_at, $session->started_at, (int) $session->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		return $newer < $cap;
	}

	public static function session( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT s.*, u.user_login FROM ' . Tables::sessions() . ' s LEFT JOIN ' . $wpdb->users . ' u ON u.ID = s.user_id WHERE s.id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	/**
	 * Chronological journey (page views merged with events), paginated in SQL.
	 *
	 * @return array{items:array,total:int}
	 */
	public static function journey( $session_id, $page = 1, $per = 100 ) {
		global $wpdb;
		$pv  = Tables::pageviews();
		$ev  = Tables::events();
		$sid = (int) $session_id;
		$per = max( 1, min( 200, (int) $per ) );
		$off = max( 0, ( (int) $page - 1 ) * $per );

		$real_total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT (SELECT COUNT(*) FROM {$pv} WHERE session_id = %d) + (SELECT COUNT(*) FROM {$ev} WHERE session_id = %d)", $sid, $sid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		// Lite shows the most recent `activity_events` of the session; Pro the whole journey. The window is applied to the
		// chronological list, so older entries are only skipped by the query, never removed.
		$cap   = \UserFlo\Edition::can( 'history_extended' ) ? 0 : \UserFlo\Edition::limit( 'activity_events' );
		$total = $cap ? min( $real_total, $cap ) : $real_total;
		if ( $cap ) {
			$start = max( 0, $real_total - $cap );
			$per   = max( 0, min( $per, $cap - $off ) );
			$off   = $start + $off;
		}
		if ( $per < 1 ) {
			return array( 'items' => array(), 'total' => $total, 'real_total' => $real_total, 'cap' => $cap );
		}
		$rows  = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
				"(SELECT 'pageview' AS kind, id, viewed_at AS at, url, title, referrer, engaged_seconds, duration_seconds, max_scroll, '' AS type, '' AS name, '' AS target, '' AS value, '' AS component, id AS pv, 0 AS ord FROM {$pv} WHERE session_id = %d)
				UNION ALL
				(SELECT 'event' AS kind, id, occurred_at AS at, '' AS url, '' AS title, '' AS referrer, 0, 0, 0, type, name, target, value, component, pageview_id AS pv, 1 AS ord FROM {$ev} WHERE session_id = %d)
				ORDER BY at ASC, ord ASC, id ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$sid,
				$sid,
				$per,
				$off
			),
			ARRAY_A
		);
		return array( 'items' => $rows, 'total' => $total, 'real_total' => $real_total, 'cap' => $cap );
	}

	/** Return frequency computed from actual session records. */
	public static function return_stats( $visitor_id ) {
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT started_at FROM ' . Tables::sessions() . ' WHERE visitor_id = %d ORDER BY started_at ASC LIMIT 5000', $visitor_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		$days = array();
		foreach ( $rows as $utc ) {
			$days[ wp_date( 'Y-m-d', strtotime( $utc . ' UTC' ) ) ] = true;
		}
		$n = count( $days );
		$out = array( 'sessions' => count( $rows ), 'active_days' => $n, 'avg_gap_days' => null );
		if ( $n > 1 ) {
			$keys               = array_keys( $days );
			$span               = ( strtotime( end( $keys ) ) - strtotime( $keys[0] ) ) / DAY_IN_SECONDS;
			$out['avg_gap_days'] = round( $span / ( $n - 1 ), 1 );
		}
		return $out;
	}

	public static function delete_visitor( $visitor_id ) {
		global $wpdb;
		$id = (int) $visitor_id;
		foreach ( array( Tables::events(), Tables::pageviews(), Tables::goal_hits(), Tables::sessions() ) as $t ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE visitor_id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		}
		return (bool) $wpdb->delete( Tables::visitors(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	/** Sessions with activity in the last ACTIVE_WINDOW seconds, newest first, with identity columns. */
	public static function active_sessions( $limit = 50 ) {
		global $wpdb;
		return $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
				'SELECT s.*, v.visitor_uid, u.user_login, u.display_name FROM (SELECT * FROM ' . Tables::sessions() . ' WHERE last_activity_at >= %s ORDER BY last_activity_at DESC LIMIT %d) s
				JOIN ' . Tables::visitors() . ' v ON v.id = s.visitor_id
				LEFT JOIN ' . $wpdb->users . ' u ON u.ID = s.user_id
				ORDER BY s.last_activity_at DESC', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
				Clock::mysql( Clock::time() - self::ACTIVE_WINDOW ),
				$limit
			)
		);
	}

	/** Page views newer than cursor id (or the latest $limit when the cursor is 0). */
	public static function live_pageviews( $after_id, $limit = 50 ) {
		global $wpdb;
		$where = $after_id > 0 ? $wpdb->prepare( 'WHERE p.id > %d', $after_id ) : '';
		return $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
				'SELECT p.id, p.viewed_at AS at, p.url, p.title, p.session_id, p.visitor_id, v.visitor_uid, s.country, s.city, s.lat, s.lon, COALESCE(s.user_id, v.user_id) AS user_id, u.user_login, u.display_name
				FROM (SELECT * FROM ' . Tables::pageviews() . ' p ' . $where . ' ORDER BY p.id DESC LIMIT %d) p
				JOIN ' . Tables::visitors() . ' v ON v.id = p.visitor_id
				JOIN ' . Tables::sessions() . ' s ON s.id = p.session_id
				LEFT JOIN ' . $wpdb->users . ' u ON u.ID = COALESCE(s.user_id, v.user_id)
				ORDER BY p.id DESC', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
				$limit
			)
		);
	}

	/** Events newer than cursor id (or the latest $limit when the cursor is 0). */
	public static function live_events( $after_id, $limit = 50 ) {
		global $wpdb;
		$where = $after_id > 0 ? $wpdb->prepare( 'WHERE e.id > %d', $after_id ) : '';
		return $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
				'SELECT e.id, e.occurred_at AS at, e.type, e.name, e.target, e.session_id, e.visitor_id, p.url, v.visitor_uid, s.country, s.city, s.lat, s.lon, COALESCE(s.user_id, v.user_id) AS user_id, u.user_login, u.display_name
				FROM (SELECT * FROM ' . Tables::events() . ' e ' . $where . ' ORDER BY e.id DESC LIMIT %d) e
				JOIN ' . Tables::visitors() . ' v ON v.id = e.visitor_id
				JOIN ' . Tables::sessions() . ' s ON s.id = e.session_id
				LEFT JOIN ' . Tables::pageviews() . ' p ON p.id = e.pageview_id
				LEFT JOIN ' . $wpdb->users . ' u ON u.ID = COALESCE(s.user_id, v.user_id)
				ORDER BY e.id DESC', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
				$limit
			)
		);
	}

	/**
	 * Bot counters for a period of site-local days: requests per kind, per bot, per page (AI first) and per day.
	 *
	 * @param string $from First day (Y-m-d). @param string $to Last day (Y-m-d, inclusive).
	 */
	public static function bots( $from, $to ) {
		global $wpdb;
		$t   = Tables::bots();
		$w   = $wpdb->prepare( 'day >= %s AND day <= %s', $from, $to );
		$out = array( 'kinds' => array( 'ai' => 0, 'search' => 0, 'other' => 0 ), 'bots' => array(), 'pages' => array( 'ai' => array(), 'other' => array() ), 'daily' => array(), 'first_day' => null );
		foreach ( (array) $wpdb->get_results( "SELECT kind, SUM(hits) AS n FROM {$t} WHERE {$w} GROUP BY kind", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			if ( isset( $out['kinds'][ $r['kind'] ] ) ) {
				$out['kinds'][ $r['kind'] ] = (int) $r['n'];
			}
		}
		foreach ( (array) $wpdb->get_results( "SELECT bot, kind, SUM(hits) AS n FROM {$t} WHERE {$w} GROUP BY bot, kind ORDER BY n DESC LIMIT 60", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$out['bots'][] = array( 'bot' => $r['bot'], 'kind' => $r['kind'], 'hits' => (int) $r['n'] );
		}
		foreach ( array( 'ai' => "kind = 'ai'", 'other' => "kind <> 'ai'" ) as $k => $cond ) {
			foreach ( (array) $wpdb->get_results( "SELECT url, SUM(hits) AS n, COUNT(DISTINCT bot) AS bots FROM {$t} WHERE {$w} AND {$cond} GROUP BY url_hash, url ORDER BY n DESC LIMIT 15", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
				$out['pages'][ $k ][] = array( 'url' => $r['url'], 'hits' => (int) $r['n'], 'bots' => (int) $r['bots'] );
			}
		}
		foreach ( (array) $wpdb->get_results( "SELECT day, kind, SUM(hits) AS n FROM {$t} WHERE {$w} GROUP BY day, kind ORDER BY day", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$out['daily'][ $r['day'] ][ $r['kind'] ] = (int) $r['n'];
		}
		$out['first_day'] = $wpdb->get_var( "SELECT MIN(day) FROM {$t}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		return $out;
	}

	/** Bot requests per page and kind for a period: url => array( ai, search, other ). */
	public static function bots_by_url( $from, $to ) {
		global $wpdb;
		$t   = Tables::bots();
		$map = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT url, kind, SUM(hits) AS n FROM {$t} WHERE day >= %s AND day <= %s AND url <> %s GROUP BY url_hash, url, kind", $from, $to, '(other pages)' ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			if ( ! isset( $map[ $r['url'] ] ) ) {
				$map[ $r['url'] ] = array( 'ai' => 0, 'search' => 0, 'other' => 0 );
			}
			$map[ $r['url'] ][ $r['kind'] ] = (int) $r['n'];
		}
		return $map;
	}

	/** Latest page views since a time, optionally of one page (history menu). Excluded pages stay out. */
	public static function history_pageviews( $since, $url, $limit ) {
		global $wpdb;
		$cond = $wpdb->prepare( 'p.viewed_at >= %s', $since ) . ( '' !== $url ? $wpdb->prepare( ' AND p.url = %s', $url ) : '' ) . \UserFlo\Exclusions\Sql::pageviews( 'p' );
		return $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
				'SELECT p.id, p.viewed_at AS at, p.url, p.title, p.session_id, p.visitor_id, v.visitor_uid, s.country, s.city, s.lat, s.lon, COALESCE(s.user_id, v.user_id) AS user_id, u.user_login, u.display_name
				FROM (SELECT * FROM ' . Tables::pageviews() . ' p WHERE ' . $cond . ' ORDER BY p.id DESC LIMIT %d) p
				JOIN ' . Tables::visitors() . ' v ON v.id = p.visitor_id
				JOIN ' . Tables::sessions() . ' s ON s.id = p.session_id
				LEFT JOIN ' . $wpdb->users . ' u ON u.ID = COALESCE(s.user_id, v.user_id)
				ORDER BY p.id DESC', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
				$limit
			)
		);
	}

	/** Latest interactions since a time, optionally on one page (history menu). Excluded events and pages stay out. */
	public static function history_events( $since, $url, $limit ) {
		global $wpdb;
		$join = '' !== $url ? ' JOIN ' . Tables::pageviews() . ' pg ON pg.id = e.pageview_id AND pg.url = ' . $wpdb->prepare( '%s', $url ) : '';
		$cond = $wpdb->prepare( 'e.occurred_at >= %s', $since ) . \UserFlo\Exclusions\Sql::events( 'e' );
		return $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
				'SELECT e.id, e.occurred_at AS at, e.type, e.name, e.target, e.session_id, e.visitor_id, p.url, v.visitor_uid, s.country, s.city, s.lat, s.lon, COALESCE(s.user_id, v.user_id) AS user_id, u.user_login, u.display_name
				FROM (SELECT e.* FROM ' . Tables::events() . ' e' . $join . ' WHERE ' . $cond . ' ORDER BY e.id DESC LIMIT %d) e
				JOIN ' . Tables::visitors() . ' v ON v.id = e.visitor_id
				JOIN ' . Tables::sessions() . ' s ON s.id = e.session_id
				LEFT JOIN ' . Tables::pageviews() . ' p ON p.id = e.pageview_id
				LEFT JOIN ' . $wpdb->users . ' u ON u.ID = COALESCE(s.user_id, v.user_id)
				ORDER BY e.id DESC', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
				$limit
			)
		);
	}

	/** Counts for the history menu header: page views, interactions and distinct visitors since a time (on one page when $url is set). */
	public static function history_totals( $since, $url ) {
		global $wpdb;
		$cond = $wpdb->prepare( 'p.viewed_at >= %s', $since ) . ( '' !== $url ? $wpdb->prepare( ' AND p.url = %s', $url ) : '' ) . \UserFlo\Exclusions\Sql::pageviews( 'p' );
		$pv   = $wpdb->get_row( 'SELECT COUNT(*) AS n, COUNT(DISTINCT p.visitor_id) AS v FROM ' . Tables::pageviews() . ' p WHERE ' . $cond, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$join = '' !== $url ? ' JOIN ' . Tables::pageviews() . ' pg ON pg.id = e.pageview_id AND pg.url = ' . $wpdb->prepare( '%s', $url ) : '';
		$ev   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Tables::events() . ' e' . $join . ' WHERE ' . $wpdb->prepare( 'e.occurred_at >= %s', $since ) . \UserFlo\Exclusions\Sql::events( 'e' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$out = array( 'pageviews' => (int) $pv['n'], 'visitors' => (int) $pv['v'], 'interactions' => $ev );
		if ( \UserFlo\Settings\Settings::get( 'bot_counter' ) ) { // Bot requests of the same days (site-local), for the page or the whole site.
			$bt   = Tables::bots();
			$cond = $wpdb->prepare( 'day >= %s', wp_date( 'Y-m-d', strtotime( $since . ' UTC' ) ) ) . ( '' !== $url ? $wpdb->prepare( ' AND url_hash = %s', md5( $url ) ) : '' );
			$bots = array( 'ai' => 0, 'search' => 0, 'other' => 0 );
			foreach ( (array) $wpdb->get_results( "SELECT kind, SUM(hits) AS n FROM {$bt} WHERE {$cond} GROUP BY kind", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
				if ( isset( $bots[ $r['kind'] ] ) ) {
					$bots[ $r['kind'] ] = (int) $r['n'];
				}
			}
			$out['bots'] = $bots;
		}
		return $out;
	}

	public static function pageviews_since( $ts ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Tables::pageviews() . ' WHERE viewed_at >= %s', Clock::mysql( $ts ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	/**
	 * Who viewed a page, newest first (anonymous and logged-in).
	 *
	 * @param string $type all|anonymous|logged_in.
	 * @return array{rows:array,total:int}
	 */
	public static function page_visits( $url, $from, $to, $type, $page, $per ) {
		global $wpdb;
		$pv   = Tables::pageviews();
		$cond = '';
		if ( 'anonymous' === $type ) {
			$cond = ' AND COALESCE(s.user_id, v.user_id) IS NULL';
		} elseif ( 'logged_in' === $type ) {
			$cond = ' AND COALESCE(s.user_id, v.user_id) IS NOT NULL';
		}
		$from_sql = "FROM {$pv} p JOIN " . Tables::visitors() . ' v ON v.id = p.visitor_id JOIN ' . Tables::sessions() . " s ON s.id = p.session_id LEFT JOIN {$wpdb->users} u ON u.ID = COALESCE(s.user_id, v.user_id) WHERE p.url = %s AND p.viewed_at >= %s AND p.viewed_at < %s{$cond}";
		$total    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$from_sql}", $url, $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own tables: names are internal constants, values are prepared.
		$rows     = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Own tables: names are internal constants, values are prepared.
				"SELECT p.id, p.viewed_at, p.engaged_seconds, p.duration_seconds, p.max_scroll, p.referrer, p.session_id, p.visitor_id, v.visitor_uid, s.device, s.browser, s.os,
				COALESCE(s.user_id, v.user_id) AS user_id, u.user_login, u.display_name, u.user_email {$from_sql} ORDER BY p.viewed_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$url,
				$from,
				$to,
				$per,
				max( 0, ( $page - 1 ) * $per )
			)
		);
		return array( 'rows' => $rows, 'total' => $total );
	}

}
