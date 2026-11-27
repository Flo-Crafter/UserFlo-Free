<?php
namespace UserFlo\Storage;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * How much space FloLens's tables use. Everything here comes from the database's own table statistics (one metadata query, never a scan of
 * the rows), is stored as a small snapshot and only refreshed once a day by the cleanup run or when an administrator asks. Opening the
 * screen reads the stored snapshot and does no measuring.
 *
 * What the numbers are, and are not:
 *  - Data and index sizes are what the storage engine reports it has allocated. They are measurements, but of a moment, and some
 *    databases (MySQL 8) cache them for up to a day.
 *  - Row counts are estimates for InnoDB (the engine WordPress uses); only MyISAM reports exact counts.
 *  - "Free space" is allocated space inside the table files that deleted rows left behind. It is reused by new rows; the files only shrink
 *    when the table is rebuilt (Maintenance::optimize).
 */
final class Measure {

	const OPTION  = 'userflo_storage_stats';
	const HISTORY = 'userflo_storage_history';
	const KEEP    = 400; // History points kept (about a year of daily measurements).

	/** Tables of the detailed analytics data, which grow with traffic. */
	const DETAIL = array( 'uf_visitors', 'uf_sessions', 'uf_pageviews', 'uf_events' );

	/**
	 * Every table the plugin owns, in the order they are shown: suffix => kind (detail | summary | other) and a plain-language purpose.
	 *
	 * @return array<string,array{kind:string,label:string,purpose:string}>
	 */
	public static function inventory() {
		return array(
			'uf_events'          => array( 'kind' => 'detail', 'label' => __( 'Interactions', 'flolens' ), 'purpose' => __( 'Every click, scroll, download, form and custom event. The fastest-growing table.', 'flolens' ) ),
			'uf_pageviews'       => array( 'kind' => 'detail', 'label' => __( 'Page views', 'flolens' ), 'purpose' => __( 'One row per page a visitor opened.', 'flolens' ) ),
			'uf_sessions'        => array( 'kind' => 'detail', 'label' => __( 'Visits', 'flolens' ), 'purpose' => __( 'One row per visit: where it came from, device, country, campaign.', 'flolens' ) ),
			'uf_visitors'        => array( 'kind' => 'detail', 'label' => __( 'Visitors', 'flolens' ), 'purpose' => __( 'One row per browser that visited.', 'flolens' ) ),
			'uf_goal_hits'       => array( 'kind' => 'detail', 'label' => __( 'Goal completions', 'flolens' ), 'purpose' => __( 'Which visit completed which goal.', 'flolens' ) ),
			'uf_orders'          => array( 'kind' => 'detail', 'label' => __( 'Sales mirror', 'flolens' ), 'purpose' => __( 'WooCommerce orders as numbers only (no names or addresses).', 'flolens' ) ),
			'uf_summary'         => array( 'kind' => 'summary', 'label' => __( 'Daily summaries', 'flolens' ), 'purpose' => __( 'Counts per day that stay after the detailed data expires.', 'flolens' ) ),
			'uf_heat'            => array( 'kind' => 'summary', 'label' => __( 'Heatmap counters', 'flolens' ), 'purpose' => __( 'Anonymous click counts per page position and day.', 'flolens' ) ),
			'uf_bots'            => array( 'kind' => 'summary', 'label' => __( 'Bot counters', 'flolens' ), 'purpose' => __( 'Daily requests by search engines and AI crawlers.', 'flolens' ) ),
			'uf_goals'           => array( 'kind' => 'other', 'label' => __( 'Goal definitions', 'flolens' ), 'purpose' => __( 'The goals you created. Settings, not analytics.', 'flolens' ) ),
			'uf_exclusions'      => array( 'kind' => 'other', 'label' => __( 'Exclusion rules', 'flolens' ), 'purpose' => __( 'What you chose to leave out of statistics.', 'flolens' ) ),
			'uf_audit_pages'     => array( 'kind' => 'other', 'label' => __( 'Website audit: pages', 'flolens' ), 'purpose' => __( 'The pages the audit has scanned.', 'flolens' ) ),
			'uf_audit_elements'  => array( 'kind' => 'other', 'label' => __( 'Website audit: elements', 'flolens' ), 'purpose' => __( 'Buttons, links and forms found on those pages.', 'flolens' ) ),
			'uf_audit_locations' => array( 'kind' => 'other', 'label' => __( 'Website audit: locations', 'flolens' ), 'purpose' => __( 'Which element is on which page.', 'flolens' ) ),
			'uf_audit_findings'  => array( 'kind' => 'other', 'label' => __( 'Website audit: findings', 'flolens' ), 'purpose' => __( 'Issues the audit found.', 'flolens' ) ),
			'uf_alert_rules'     => array( 'kind' => 'other', 'label' => __( 'Alert rules', 'flolens' ), 'purpose' => __( 'Your alert rules.', 'flolens' ) ),
			'uf_alert_state'     => array( 'kind' => 'other', 'label' => __( 'Alert state', 'flolens' ), 'purpose' => __( 'Counters the alerts use.', 'flolens' ) ),
			'uf_alert_deliveries' => array( 'kind' => 'other', 'label' => __( 'Alert deliveries', 'flolens' ), 'purpose' => __( 'A short log of sent alerts.', 'flolens' ) ),
			'uf_dashboards'      => array( 'kind' => 'other', 'label' => __( 'Custom dashboards', 'flolens' ), 'purpose' => __( 'Dashboards you built.', 'flolens' ) ),
			'uf_ai_log'          => array( 'kind' => 'other', 'label' => __( 'AI access log', 'flolens' ), 'purpose' => __( 'A short log of AI assistant requests.', 'flolens' ) ),
		);
	}

	private static function suffix( $table ) {
		global $wpdb;
		return 0 === strpos( $table, $wpdb->prefix ) ? substr( $table, strlen( $wpdb->prefix ) ) : $table;
	}

	/** The stored snapshot, or null when nothing has been measured yet. */
	public static function read() {
		$s = get_option( self::OPTION, null );
		return is_array( $s ) && isset( $s['tables'] ) ? $s : null;
	}

	/**
	 * Asks the database for the table statistics and stores the snapshot.
	 *
	 * @return array Snapshot: at (time), source (information_schema | show_table_status | none), tables (suffix => rows, data, index, free, engine, exact_rows), totals.
	 */
	public static function measure() {
		global $wpdb;
		$names = Tables::owned();
		$rows  = array();
		$src   = 'none';
		$prev  = $wpdb->suppress_errors( true );

		$ph  = implode( ',', array_fill( 0, count( $names ), '%s' ) );
		$res = $wpdb->get_results( $wpdb->prepare( "SELECT TABLE_NAME AS n, ENGINE AS e, TABLE_ROWS AS r, DATA_LENGTH AS d, INDEX_LENGTH AS i, DATA_FREE AS f FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$ph})", $names ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders
		if ( is_array( $res ) && $res ) {
			$src = 'information_schema';
			foreach ( $res as $r ) {
				$rows[ $r['n'] ] = $r;
			}
		} else {
			// Some hosts hide information_schema from the plugin's database user; SHOW TABLE STATUS is the same data by another door.
			$like = $wpdb->esc_like( $wpdb->prefix . 'uf_' ) . '%';
			$res  = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $like ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( is_array( $res ) && $res ) {
				$src = 'show_table_status';
				foreach ( $res as $r ) {
					$rows[ $r['Name'] ] = array( 'n' => $r['Name'], 'e' => $r['Engine'], 'r' => $r['Rows'], 'd' => $r['Data_length'], 'i' => $r['Index_length'], 'f' => $r['Data_free'] );
				}
			}
		}
		$wpdb->suppress_errors( $prev );

		$tables = array();
		$tot    = array( 'data' => 0, 'index' => 0, 'free' => 0, 'rows' => 0, 'detail' => 0, 'summary' => 0, 'other' => 0 );
		$inv    = self::inventory();
		foreach ( $names as $name ) {
			$suffix = self::suffix( $name );
			if ( ! isset( $rows[ $name ] ) ) {
				$tables[ $suffix ] = null; // Not reported by the database: shown as unavailable, never as zero.
				continue;
			}
			$r      = $rows[ $name ];
			$engine = (string) $r['e'];
			$t      = array(
				'rows'       => null === $r['r'] ? null : (int) $r['r'],
				'data'       => (int) $r['d'],
				'index'      => (int) $r['i'],
				'free'       => (int) $r['f'],
				'engine'     => $engine,
				'exact_rows' => in_array( strtolower( $engine ), array( 'myisam', 'aria', 'memory' ), true ),
			);
			$tables[ $suffix ] = $t;
			$tot['data']  += $t['data'];
			$tot['index'] += $t['index'];
			$tot['free']  += $t['free'];
			$tot['rows']  += (int) $t['rows'];
			$kind = isset( $inv[ $suffix ] ) ? $inv[ $suffix ]['kind'] : 'other';
			$tot[ $kind ] += $t['data'] + $t['index'];
		}
		$snap = array( 'at' => Clock::time(), 'source' => $src, 'tables' => $tables, 'totals' => $tot );
		update_option( self::OPTION, $snap, false );
		return $snap;
	}

	/** Daily: measures and adds a point to the history. Never throws and never blocks a cleanup run. */
	public static function record() {
		try {
			$snap = self::measure();
		} catch ( \Throwable $e ) {
			return;
		}
		if ( 'none' === $snap['source'] ) {
			return;
		}
		$h    = self::history();
		$last = $h ? end( $h ) : null;
		if ( $last && Clock::time() - (int) $last['t'] < 20 * HOUR_IN_SECONDS ) {
			return; // One point a day is enough to see a trend; more would only grow the option.
		}
		$bytes = 0;
		$rows  = 0;
		foreach ( self::DETAIL as $sfx ) {
			if ( ! empty( $snap['tables'][ $sfx ] ) ) {
				$bytes += $snap['tables'][ $sfx ]['data'] + $snap['tables'][ $sfx ]['index'];
				$rows  += (int) $snap['tables'][ $sfx ]['rows'];
			}
		}
		$h[] = array( 't' => (int) $snap['at'], 'b' => $bytes, 'r' => $rows, 'a' => $snap['totals']['data'] + $snap['totals']['index'] );
		update_option( self::HISTORY, array_slice( $h, -self::KEEP ), false );
	}

	public static function history() {
		$h = get_option( self::HISTORY, array() );
		return is_array( $h ) ? array_values( $h ) : array();
	}

	/**
	 * Average growth of the detailed tables, from the stored points. Needs at least 5 points over at least 7 days; with less there is
	 * nothing honest to say, and the screen says so instead of guessing.
	 *
	 * @return array{enough:bool,points:int,days:float,per_day:int,per_week:int,per_month:int}
	 */
	public static function growth() {
		$h   = array_values( array_filter( self::history(), static function ( $p ) {
			return (int) $p['t'] >= Clock::time() - 60 * DAY_IN_SECONDS; // The recent trend, not the whole year.
		} ) );
		$out = array( 'enough' => false, 'points' => count( $h ), 'days' => 0.0, 'per_day' => 0, 'per_week' => 0, 'per_month' => 0 );
		if ( count( $h ) < 5 ) {
			return $out;
		}
		$a    = $h[0];
		$b    = end( $h );
		$days = ( (int) $b['t'] - (int) $a['t'] ) / DAY_IN_SECONDS;
		$out['days'] = round( $days, 1 );
		if ( $days < 7 ) {
			return $out;
		}
		$per = ( (int) $b['b'] - (int) $a['b'] ) / $days;
		$out['enough']    = true;
		$out['per_day']   = (int) round( $per );
		$out['per_week']  = (int) round( $per * 7 );
		$out['per_month'] = (int) round( $per * 30 );
		return $out;
	}
}
