<?php
namespace UserFlo\Insights;

use UserFlo\Core\Clock;
use UserFlo\Database\Reports;
use UserFlo\Database\Tables;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Long-term daily summaries. The raw rows (visitors, sessions, page views, events) are deleted after the retention period, so a
 * comparison with last year needs something that outlives them. Once a local day is over, its counts are written here: one row of totals
 * and the top pages, sources, countries, devices and channels of that day. Counts only. No visitor, session, IP address or user agent
 * is stored, so keeping them for years does not keep personal data.
 *
 * Rows are written once per finished day (never for today) and the raw data of a day is not deleted until that day is summarised
 * (Retention asks `protect_from()`). The summary has its own period in years (Settings → Privacy); 0 switches it off and deletes it.
 */
final class Summary {

	const HOOK       = 'userflo_summary_roll';
	const PAGES      = 50;
	const SOURCES    = 20;
	const COUNTRIES  = 20;
	const DAYS_RUN   = 60;
	const DIMS       = array( 'page', 'source', 'country', 'device', 'channel' );
	const STATE      = 'userflo_summary_state';
	const LOCK       = 'summary';
	const CUSTOM     = 50; // Custom event names kept per day.

	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'roll_pending' ) );
		add_action( 'admin_post_userflo_summary_csv', array( __CLASS__, 'download' ) );
	}

	/** The daily summaries as a CSV file (counts only), for people who want a copy outside the database. */
	public static function download() {
		global $wpdb;
		if ( ! current_user_can( \UserFlo\Plugin::cap() ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'userflo_summary_csv' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'flolens' ), 403 );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="userflo-daily-summaries-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, \UserFlo\Export\CsvExporter::line( array( 'day', 'type', 'name', 'sessions', 'page_views', 'visitors_per_day', 'new_visitors', 'logged_in_sessions', 'engaged_seconds', 'one_page_sessions', 'amount' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- A stream or file FloLens creates itself.
		$t    = Tables::summary();
		$last = '';
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT day, dim, k, sessions, pageviews, visitors, new_visitors, logged_in, engaged, single, amount FROM {$t} WHERE CONCAT(day, dim, k_hash) > %s ORDER BY CONCAT(day, dim, k_hash) LIMIT 2000", $last ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			foreach ( (array) $rows as $r ) {
				fwrite( $out, \UserFlo\Export\CsvExporter::line( array_values( $r ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- A stream or file FloLens creates itself.
			}
			if ( $rows ) {
				$e    = end( $rows );
				$last = $e['day'] . $e['dim'] . md5( substr( (string) $e['k'], 0, 300 ) );
			}
		} while ( count( (array) $rows ) === 2000 );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	public static function years() {
		return max( 0, (int) Settings::get( 'summary_years' ) );
	}

	public static function enabled() {
		return self::years() > 0;
	}

	/** Start and end (UTC timestamps) of a site-local day given as Y-m-d, DST-correct. @return int[]|null */
	public static function day_bounds( $ymd ) {
		try {
			$start = new \DateTimeImmutable( $ymd . ' 00:00:00', wp_timezone() );
		} catch ( \Exception $e ) {
			return null;
		}
		return array( $start->getTimestamp(), $start->modify( '+1 day' )->getTimestamp() );
	}

	private static function today() {
		return wp_date( 'Y-m-d', Clock::time() );
	}

	/**
	 * Writes (or rewrites) the summary of one finished local day from the raw rows. Returns false for today, the future, a bad date, a day
	 * that another run is writing right now, or a failed write (which leaves the previous summary of that day untouched).
	 *
	 * The write is all-or-nothing: the day's old rows are replaced inside one transaction, with the `total` row last. `total` is what marks
	 * a day as summarised, so even on a database engine without transactions a run that dies half-way leaves no marker and the day is
	 * simply done again. Running it twice gives the same rows (it replaces, never adds).
	 *
	 * @param string $ymd  Local day, Y-m-d.
	 * @param array  $opts keep: dims to leave as they are (raw data for them is gone); skip: dims not to write at all.
	 */
	public static function roll_day( $ymd, array $opts = array() ) {
		global $wpdb;
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $ymd ) || $ymd >= self::today() ) {
			return false;
		}
		$b = self::day_bounds( $ymd );
		if ( ! $b ) {
			return false;
		}
		if ( ! \UserFlo\Core\Lock::acquire( self::LOCK, 120 ) ) {
			return false;
		}
		$ok = false;
		try {
			$ok = self::write_day( $ymd, $b, $opts );
		} catch ( \Throwable $e ) {
			self::fail( $ymd, 'exception' );
		}
		\UserFlo\Core\Lock::release( self::LOCK );
		return $ok;
	}

	private static function write_day( $ymd, array $b, array $opts ) {
		global $wpdb;
		$from = Clock::mysql( $b[0] );
		$to   = Clock::mysql( $b[1] );
		$t    = Tables::summary();
		$keep = isset( $opts['keep'] ) ? (array) $opts['keep'] : array();
		$skip = isset( $opts['skip'] ) ? (array) $opts['skip'] : array();
		$rows = array(); // "dim|hash" => row; a key that repeats (two long addresses cut to the same 300 characters) adds up.
		$add  = static function ( $dim, $k, $sessions, $views, $visitors, $new, $li, $eng, $single, $amount = 0.0, $hash = null ) use ( &$rows, $keep, $skip ) {
			if ( in_array( $dim, $keep, true ) || in_array( $dim, $skip, true ) ) {
				return;
			}
			$k = \UserFlo\Core\Text::substr( (string) $k, 300 );
			$h = null === $hash ? md5( $k ) : $hash;
			$i = $dim . '|' . $h;
			if ( isset( $rows[ $i ] ) ) {
				foreach ( array( 2 => $sessions, 3 => $views, 4 => $visitors, 5 => $new, 6 => $li, 7 => $eng, 8 => $single, 9 => $amount ) as $c => $v ) {
					$rows[ $i ][ $c ] += $v;
				}
				return;
			}
			$rows[ $i ] = array( $dim, $k, (int) $sessions, (int) $views, (int) $visitors, (int) $new, (int) $li, (int) $eng, (int) $single, (float) $amount, $h );
		};
		$o = Reports::overview( $from, $to );
		foreach ( Reports::top_pages( $from, self::PAGES, $to ) as $p ) {
			$add( 'page', (string) $p['url'], 0, (int) $p['views'], (int) $p['visitors'], 0, 0, 0, 0 );
		}
		$direct = __( 'Direct / internal', 'flolens' );
		foreach ( Reports::referrers( $from, self::SOURCES, $to ) as $host => $n ) {
			$host = $host === $direct ? '' : $host; // Stored as an empty name, so changing the site language never splits a source in two.
			$add( 'source', (string) $host, (int) $n, 0, 0, 0, 0, 0, 0 );
		}
		foreach ( Reports::countries( $from, $to, self::COUNTRIES ) as $c ) {
			$add( 'country', (string) $c['code'], (int) $c['sessions'], 0, 0, 0, 0, 0, 0 );
		}
		$mix = Reports::traffic_mix( $from, $to );
		foreach ( $mix['devices'] as $d ) {
			$add( 'device', (string) $d['key'], (int) $d['sessions'], 0, 0, 0, 0, 0, 0 );
		}
		foreach ( $mix['channels'] as $key => $n ) {
			if ( $n > 0 ) {
				$add( 'channel', (string) $key, (int) $n, 0, 0, 0, 0, 0, 0 );
			}
		}
		self::conversions( $from, $to, $add );
		self::events( $from, $to, $add );
		if ( $wpdb->last_error ) {
			return self::fail( $ymd, 'read' ); // A read that failed would write a summary with holes in it.
		}
		// The marker goes in last.
		$rows[ 'total|' . md5( '' ) ] = array( 'total', '', (int) $o['sessions'], (int) $o['pageviews'], (int) $o['visitors'], (int) $o['new'], (int) $o['logged_in'], (int) $o['engaged'], (int) $o['single_page'], 0.0, md5( '' ) );
		$total = array_pop( $rows );
		$rows[] = $total;

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$del = 'DELETE FROM ' . $t . ' WHERE day = %s'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$args = array( $ymd );
		if ( $keep ) {
			$del  .= ' AND dim NOT IN (' . implode( ',', array_fill( 0, count( $keep ), '%s' ) ) . ')';
			$args  = array_merge( $args, array_values( $keep ) );
		}
		$fine = false !== $wpdb->query( $wpdb->prepare( $del, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		foreach ( array_chunk( array_values( $rows ), 100 ) as $chunk ) {
			if ( ! $fine ) {
				break;
			}
			$vals = array();
			foreach ( $chunk as $r ) {
				$vals[] = $wpdb->prepare( '(%s,%s,%s,%s,%d,%d,%d,%d,%d,%d,%d,%f)', $ymd, $r[0], $r[10], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7], $r[8], $r[9] );
			}
			$fine = false !== $wpdb->query( "INSERT INTO {$t} (day, dim, k_hash, k, sessions, pageviews, visitors, new_visitors, logged_in, engaged, single, amount) VALUES " . implode( ',', $vals ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		}
		if ( ! $fine ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return self::fail( $ymd, 'write' );
		}
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::note_rolled( $ymd );
		return true;
	}

	/** Goal completions and sales of the day, counted the way the Goals and Sales screens count them. */
	private static function conversions( $from, $to, callable $add ) {
		global $wpdb;
		$names = array();
		$vals  = array();
		foreach ( \UserFlo\Goals\Store::all() as $g ) {
			$names[ (int) $g['id'] ] = (string) $g['name'];
			$vals[ (int) $g['id'] ]  = isset( $g['value'] ) ? (float) $g['value'] : 0.0;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT h.goal_id, COUNT(*) AS n FROM ' . Tables::goal_hits() . ' h JOIN ' . Tables::sessions() . ' s ON s.id = h.session_id WHERE s.started_at >= %s AND s.started_at < %s GROUP BY h.goal_id', $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		foreach ( (array) $rows as $r ) {
			$id = (int) $r['goal_id'];
			// The key is the goal's id, so renaming a goal never splits its history; the name is the one it had that day.
			$add( 'goal', isset( $names[ $id ] ) ? $names[ $id ] : 'Goal #' . $id, (int) $r['n'], 0, 0, 0, 0, 0, 0, (int) $r['n'] * ( isset( $vals[ $id ] ) ? $vals[ $id ] : 0.0 ), md5( 'goal:' . $id ) );
		}
		if ( $wpdb->get_var( 'SELECT 1 FROM ' . Tables::orders() . ' LIMIT 1' ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
			$st = \UserFlo\Commerce\Stats::summary( $from, $to );
			foreach ( $st['currencies'] as $c ) {
				$add( 'order', (string) $c['currency'], (int) $c['orders'], 0, 0, 0, 0, 0, 0, (float) $c['revenue'] );
			}
		}
	}

	/** Interactions of the day by type, and custom events by name: counts only, so the totals outlive the individual rows. */
	private static function events( $from, $to, callable $add ) {
		global $wpdb;
		$e    = Tables::events();
		$skip = \UserFlo\Exclusions\Sql::events( 'e' );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT e.type, COUNT(*) AS n FROM {$e} e WHERE e.occurred_at >= %s AND e.occurred_at < %s{$skip} GROUP BY e.type", $from, $to ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$add( 'event', (string) $r['type'], (int) $r['n'], 0, 0, 0, 0, 0, 0 );
		}
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT e.name, COUNT(*) AS n FROM {$e} e WHERE e.type = 'custom' AND e.name <> '' AND e.occurred_at >= %s AND e.occurred_at < %s{$skip} GROUP BY e.name ORDER BY n DESC LIMIT %d", $from, $to, self::CUSTOM ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$add( 'custom', (string) $r['name'], (int) $r['n'], 0, 0, 0, 0, 0, 0 );
		}
	}

	// ---- state: what was written when, and what went wrong --------------------------------------------------------------------------

	public static function state() {
		$s = get_option( self::STATE, array() );
		return is_array( $s ) ? $s : array();
	}

	private static function save_state( array $s ) {
		update_option( self::STATE, $s, false );
	}

	private static function note_rolled( $ymd ) {
		$s                 = self::state();
		$s['rolled']       = isset( $s['rolled'] ) && is_array( $s['rolled'] ) ? $s['rolled'] : array();
		$s['rolled'][ $ymd ] = Clock::time();
		krsort( $s['rolled'] );
		$s['rolled']       = array_slice( $s['rolled'], 0, 6, true );
		$s['last_ok']      = Clock::time();
		$s['last_ok_day']  = $ymd;
		unset( $s['error'] );
		self::save_state( $s );
	}

	/** Records a failed write, once, in the plugin log and in the state shown on the Database & Storage screen. */
	private static function fail( $ymd, $why ) {
		$s          = self::state();
		$s['error'] = array( 'day' => (string) $ymd, 'why' => (string) $why, 'at' => Clock::time() );
		self::save_state( $s );
		\UserFlo\Logger::add( 'error', 'summary', sprintf( 'Could not summarise %s (%s). The detailed data of that day is kept and the next run tries again.', $ymd, $why ) );
		return false;
	}

	/** Seconds after midnight before a day is considered complete: a visit that began before midnight can still be reporting. */
	public static function late_grace() {
		return max( 30, (int) Settings::get( 'inactivity_minutes' ) ) * 60 + 600;
	}

	/** Local days that have raw data but no summary yet, oldest first (finished days only). @return string[] */
	public static function missing_days( $limit = 0 ) {
		global $wpdb;
		$first = Reports::first_data_at();
		if ( '' === $first ) {
			return array();
		}
		$have = array_flip( (array) $wpdb->get_col( 'SELECT day FROM ' . Tables::summary() . " WHERE dim = 'total'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		try {
			$d = new \DateTimeImmutable( '@' . strtotime( $first . ' UTC' ) );
			$d = $d->setTimezone( wp_timezone() )->setTime( 0, 0 );
		} catch ( \Exception $e ) {
			return array();
		}
		$today = self::today();
		$out   = array();
		for ( $i = 0; $i < 4000; $i++, $d = $d->modify( '+1 day' ) ) {
			$ymd = $d->format( 'Y-m-d' );
			if ( $ymd >= $today ) {
				break;
			}
			if ( ! isset( $have[ $ymd ] ) ) {
				$out[] = $ymd;
				if ( $limit && count( $out ) >= $limit ) {
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Summarises the finished days that are not summarised yet, oldest first, within a time budget, and writes the two latest days once
	 * more after the late-arrival grace has passed (a day done just after midnight may have missed the last visits and their final
	 * beacons). Schedules a follow-up run when days are left (a first run on an old site has up to a retention period of days to read).
	 *
	 * @return int Days written.
	 */
	public static function roll_pending( $budget = 15 ) {
		if ( ! self::enabled() ) {
			return 0;
		}
		$end   = microtime( true ) + (float) $budget;
		$done  = 0;
		$days  = self::missing_days( self::DAYS_RUN + 1 );
		$more  = count( $days ) > self::DAYS_RUN;
		$clean = true; // Every missing day was written.
		foreach ( array_slice( $days, 0, self::DAYS_RUN ) as $ymd ) {
			if ( microtime( true ) >= $end ) {
				$more  = true;
				$clean = false;
				break;
			}
			if ( self::roll_day( $ymd ) ) {
				$done++;
			} else {
				$more  = false; // A day that cannot be written is not retried in a tight loop; the next daily run tries it again.
				$clean = false;
				break;
			}
		}
		if ( $clean && ! $more ) {
			$done += self::upgrade_step( $end );
			$more  = $more || (bool) self::upgrade_pending();
			$done += self::refresh_recent( $end );
		}
		if ( $more && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK );
		}
		return $done;
	}

	/** Writes yesterday and the day before again when they were first written before their grace period ended. @return int Days written. */
	private static function refresh_recent( $end ) {
		$s    = self::state();
		$have = isset( $s['rolled'] ) && is_array( $s['rolled'] ) ? $s['rolled'] : array();
		$n    = 0;
		for ( $i = 1; $i <= 2; $i++ ) {
			$ymd = wp_date( 'Y-m-d', Clock::time() - $i * DAY_IN_SECONDS );
			$b   = self::day_bounds( $ymd );
			if ( ! $b || ! isset( $have[ $ymd ] ) || microtime( true ) >= $end ) {
				continue;
			}
			$late = $b[1] + self::late_grace();
			if ( (int) $have[ $ymd ] < $late && Clock::time() >= $late && self::roll_day( $ymd ) ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * The earliest day whose raw rows must not be deleted yet because it is not summarised (UTC mysql datetime), or '' when nothing is
	 * waiting. Retention never deletes at or after this moment.
	 */
	public static function protect_from() {
		if ( ! self::enabled() ) {
			return '';
		}
		$hold = '';
		$days = self::missing_days( 1 );
		if ( $days ) {
			$b = self::day_bounds( $days[0] );
			$hold = $b ? Clock::mysql( $b[0] ) : '';
		}
		$up = self::upgrade_pending();
		if ( $up ) { // Summaries written before the goal, sales and interaction rows existed are written again first.
			$b = self::day_bounds( $up['next'] );
			if ( $b && ( '' === $hold || Clock::mysql( $b[0] ) < $hold ) ) {
				$hold = Clock::mysql( $b[0] );
			}
		}
		return $hold;
	}

	/**
	 * After an upgrade from a version that wrote fewer rows per day: the range of days that still have their detailed rows and are
	 * written again once, oldest first, before any of those rows can be deleted. @return array{next:string,to:string}|null
	 */
	public static function upgrade_pending() {
		$s = self::state();
		return isset( $s['upgrade']['next'], $s['upgrade']['to'] ) ? $s['upgrade'] : null;
	}

	public static function start_upgrade() {
		$s            = self::state();
		$s['upgrade'] = array( 'next' => self::rebuild_floor(), 'to' => wp_date( 'Y-m-d', Clock::time() - DAY_IN_SECONDS ) );
		self::save_state( $s );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK );
		}
	}

	private static function upgrade_step( $end ) {
		$up = self::upgrade_pending();
		if ( ! $up ) {
			return 0;
		}
		if ( $up['next'] > $up['to'] ) {
			$s = self::state();
			unset( $s['upgrade'] );
			self::save_state( $s );
			return 0;
		}
		$r = self::rebuild( $up['next'], $up['to'], max( 1, $end - microtime( true ) ) );
		$s = self::state();
		if ( $r['failed'] > 0 ) {
			return $r['done']; // Keep the position; the next run tries the same days again.
		}
		if ( $r['finished'] ) {
			unset( $s['upgrade'] );
		} else {
			$s['upgrade']['next'] = $r['next'];
		}
		self::save_state( $s );
		return $r['done'];
	}

	/**
	 * The moment up to which raw rows may be deleted: the requested cutoff, pulled back to the start of the first day whose summary is
	 * missing or does not match the raw rows still there. Nothing that is not safely in a summary is ever released for deletion. With
	 * summaries switched off the request is returned unchanged (the person chose not to keep them).
	 *
	 * A day is verified by comparing its summary's session count with the sessions still stored for that day; a day that differs is
	 * written again once and compared again. Fewer raw rows than the summary is fine (part of the day was removed earlier).
	 *
	 * @param string $cutoff UTC mysql datetime.
	 * @param float  $until  microtime() deadline for the checking.
	 * @return string UTC mysql datetime, never later than $cutoff.
	 */
	public static function safe_cutoff( $cutoff, $until = 0.0 ) {
		global $wpdb;
		if ( ! self::enabled() ) {
			return $cutoff;
		}
		$hold = self::protect_from();
		if ( '' !== $hold && $hold < $cutoff ) {
			$cutoff = $hold;
		}
		$first = Reports::first_data_at();
		if ( '' === $first || $first >= $cutoff ) {
			return $cutoff;
		}
		$s     = self::state();
		$mark  = isset( $s['verified_to'] ) ? (string) $s['verified_to'] : '';
		$day   = self::local_day( strtotime( $first . ' UTC' ) );
		if ( '' !== $mark && $mark >= $day ) {
			$day = self::next_day( $mark ); // Resume after the last verified day.
		}
		$sess = Tables::sessions();
		$sum  = Tables::summary();
		for ( $i = 0; $i < 800; $i++ ) {
			$b = self::day_bounds( $day );
			if ( ! $b || $b[1] > strtotime( $cutoff . ' UTC' ) || $day >= self::today() ) {
				break; // Only days that end before the cutoff matter.
			}
			if ( $until && microtime( true ) >= $until ) {
				$cutoff = min( $cutoff, Clock::mysql( $b[0] ) ); // Out of time: release only what is verified so far.
				break;
			}
			$raw  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sess} WHERE started_at >= %s AND started_at < %s", Clock::mysql( $b[0] ), Clock::mysql( $b[1] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$have = $wpdb->get_var( $wpdb->prepare( "SELECT sessions FROM {$sum} WHERE day = %s AND dim = 'total'", $day ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			if ( null === $have || $raw > (int) $have ) {
				if ( ( null === $have && 0 === $raw ) ) {
					$have = 0; // Nothing recorded and nothing summarised: a quiet day with no marker needs none of its own checking.
				} else {
					self::roll_day( $day );
					$have = $wpdb->get_var( $wpdb->prepare( "SELECT sessions FROM {$sum} WHERE day = %s AND dim = 'total'", $day ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
					$raw  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sess} WHERE started_at >= %s AND started_at < %s", Clock::mysql( $b[0] ), Clock::mysql( $b[1] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
					if ( null === $have || $raw > (int) $have ) {
						self::fail( $day, 'verify' );
						return min( $cutoff, Clock::mysql( $b[0] ) );
					}
				}
			}
			$s['verified_to'] = $day;
			$day              = self::next_day( $day );
		}
		if ( isset( $s['verified_to'] ) ) {
			$cur                = self::state(); // The rolls above wrote their own notes; add the watermark to those, not over them.
			$cur['verified_to'] = $s['verified_to'];
			self::save_state( $cur );
		}
		return $cutoff;
	}

	private static function local_day( $ts ) {
		return wp_date( 'Y-m-d', $ts );
	}

	/** The next local calendar day (DST-safe). */
	public static function next_day( $ymd ) {
		try {
			return ( new \DateTimeImmutable( $ymd . ' 00:00:00', wp_timezone() ) )->modify( '+1 day' )->format( 'Y-m-d' );
		} catch ( \Exception $e ) {
			return $ymd;
		}
	}

	/**
	 * The first local day that can be written again from the data that is still stored: every day after the oldest stored session's day.
	 * That day itself may have been cut by a deletion (or by tracking starting part-way through it), so its existing summary stays.
	 */
	public static function rebuild_floor() {
		$first = Reports::first_data_at();
		if ( '' === $first ) {
			return wp_date( 'Y-m-d', Clock::time() ); // No raw rows: nothing can be rebuilt.
		}
		return self::next_day( self::local_day( strtotime( $first . ' UTC' ) ) );
	}

	/**
	 * Rewrites the summaries of the local days $from..$to that still have all their detailed rows. Days whose detailed rows are partly or
	 * wholly gone are skipped and their summaries are never touched. Interaction counts of days older than the shorter retention for
	 * clicks and scrolls are left as they were. Safe to repeat: each day is replaced as a whole, or not at all.
	 *
	 * @return array{done:int,skipped:int,failed:int,next:string,finished:bool}
	 */
	public static function rebuild( $from, $to, $budget = 8 ) {
		$floor = self::rebuild_floor();
		$end   = microtime( true ) + (float) $budget;
		$out   = array( 'done' => 0, 'skipped' => 0, 'failed' => 0, 'next' => $from, 'finished' => true );
		$ymd   = $from;
		$limit = min( $to, wp_date( 'Y-m-d', Clock::time() - DAY_IN_SECONDS ) );
		$short = (int) Settings::get( 'short_event_days' );
		for ( $i = 0; $i < 4000 && $ymd <= $limit; $i++ ) {
			if ( microtime( true ) >= $end ) {
				$out['next']     = $ymd;
				$out['finished'] = false;
				return $out;
			}
			if ( $ymd < $floor ) {
				$out['skipped']++;
			} else {
				$opts = array();
				if ( $short > 0 && $ymd < wp_date( 'Y-m-d', Clock::time() - $short * DAY_IN_SECONDS ) ) {
					$opts['keep'] = array( 'event', 'custom' ); // Their rows were partly removed on purpose; the day's old counts are the complete ones.
				}
				if ( self::roll_day( $ymd, $opts ) ) {
					$out['done']++;
				} else {
					$out['failed']++;
				}
			}
			$ymd = self::next_day( $ymd );
		}
		$out['next'] = $ymd;
		return $out;
	}

	// ---- a rebuild started from Tools → Database & Storage ---------------------------------------------------------------------------

	/** @return array|null The running or last rebuild: from, to, next, done, skipped, failed, started, finished. */
	public static function rebuild_state() {
		$s = self::state();
		return isset( $s['rebuild'] ) && is_array( $s['rebuild'] ) ? $s['rebuild'] : null;
	}

	public static function rebuild_running() {
		$r = self::rebuild_state();
		return $r && empty( $r['finished'] );
	}

	/**
	 * Queues a rebuild of the summaries for local days $from..$to (Y-m-d). It runs in steps (rebuild_step), so a large range never has to
	 * fit in one request, and can be left and picked up again.
	 *
	 * @return array|\WP_Error
	 */
	public static function start_rebuild( $from, $to ) {
		$re = '/^\d{4}-\d{2}-\d{2}$/';
		if ( ! preg_match( $re, (string) $from ) || ! preg_match( $re, (string) $to ) || $from > $to ) {
			return new \WP_Error( 'userflo_bad_range', __( 'Choose a valid range of dates.', 'flolens' ), array( 'status' => 400 ) );
		}
		if ( ! self::enabled() ) {
			return new \WP_Error( 'userflo_summaries_off', __( 'Daily summaries are switched off (Settings → Privacy).', 'flolens' ), array( 'status' => 400 ) );
		}
		if ( self::rebuild_running() || \UserFlo\Privacy\Retention::busy() ) {
			return new \WP_Error( 'userflo_busy', __( 'A cleanup or a rebuild is already running. Wait for it to finish.', 'flolens' ), array( 'status' => 409 ) );
		}
		$yesterday = wp_date( 'Y-m-d', Clock::time() - DAY_IN_SECONDS );
		$to        = min( $to, $yesterday );
		if ( $from > $to ) {
			return new \WP_Error( 'userflo_bad_range', __( 'Only finished days can be rebuilt.', 'flolens' ), array( 'status' => 400 ) );
		}
		$s            = self::state();
		$s['rebuild'] = array( 'from' => $from, 'to' => $to, 'next' => $from, 'done' => 0, 'skipped' => 0, 'failed' => 0, 'started' => Clock::time() );
		self::save_state( $s );
		return $s['rebuild'];
	}

	/** Continues the rebuild within a time budget. @return array|null The state. */
	public static function rebuild_step( $budget = 8 ) {
		$r = self::rebuild_state();
		if ( ! $r || ! empty( $r['finished'] ) ) {
			return $r;
		}
		$res = self::rebuild( $r['next'], $r['to'], $budget );
		$s   = self::state();
		$r   = $s['rebuild'];
		$r['done']    += $res['done'];
		$r['skipped'] += $res['skipped'];
		$r['failed']  += $res['failed'];
		$r['next']     = $res['next'];
		if ( $res['finished'] ) {
			$r['finished'] = Clock::time();
			\UserFlo\Logger::add( 'info', 'summary', sprintf( 'Summaries rebuilt: %d days written, %d skipped (their detailed data is gone), %d failed.', $r['done'], $r['skipped'], $r['failed'] ) );
		}
		$s['rebuild'] = $r;
		self::save_state( $s );
		return $r;
	}

	/** Deletes summaries older than the summary period (all of them when the period is 0). Returns rows deleted. */
	public static function purge() {
		global $wpdb;
		$t = Tables::summary();
		if ( ! self::enabled() ) {
			return (int) $wpdb->query( "DELETE FROM {$t}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		}
		$cut = wp_date( 'Y-m-d', Clock::time() - self::years() * 366 * DAY_IN_SECONDS );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE day < %s", $cut ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	/** The oldest summarised day, or ''. */
	public static function first_day() {
		global $wpdb;
		return (string) $wpdb->get_var( 'SELECT MIN(day) FROM ' . Tables::summary() . " WHERE dim = 'total'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	// ---- reading -------------------------------------------------------------------------------------------------------------------

	/** Same local dates one year earlier (29 February becomes 28 February). */
	public static function last_year( $ymd ) {
		try {
			$d = new \DateTimeImmutable( $ymd, wp_timezone() );
		} catch ( \Exception $e ) {
			return $ymd;
		}
		$y = $d->modify( '-1 year' );
		if ( '02-29' === $d->format( 'm-d' ) ) {
			$y = $d->modify( '-1 year -1 day' );
		}
		return $y->format( 'Y-m-d' );
	}

	/**
	 * Totals and the top rows of each dimension for the local days $from..$to (inclusive) from the summaries.
	 *
	 * @return array{days:int,totals:array,top:array}
	 */
	public static function period( $from, $to, $limit = 8 ) {
		global $wpdb;
		$t   = Tables::summary();
		$sum = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS days, COALESCE(SUM(sessions),0) AS sessions, COALESCE(SUM(pageviews),0) AS pageviews, COALESCE(SUM(visitors),0) AS visitors, COALESCE(SUM(new_visitors),0) AS new_visitors, COALESCE(SUM(logged_in),0) AS logged_in, COALESCE(SUM(engaged),0) AS engaged, COALESCE(SUM(single),0) AS single FROM {$t} WHERE dim = 'total' AND day >= %s AND day <= %s", $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$out = array(
			'days'   => (int) $sum['days'],
			'totals' => array(
				'sessions'     => (int) $sum['sessions'],
				'pageviews'    => (int) $sum['pageviews'],
				'visitors'     => (int) $sum['visitors'], // Distinct visitors per day, added up: a person who came on two days counts twice.
				'new_visitors' => (int) $sum['new_visitors'],
				'logged_in'    => (int) $sum['logged_in'],
				'engaged'      => (int) $sum['engaged'],
				'single'       => (int) $sum['single'],
			),
			'top'    => array(),
		);
		foreach ( array( 'page' => 'pageviews', 'source' => 'sessions', 'country' => 'sessions', 'device' => 'sessions', 'channel' => 'sessions' ) as $dim => $col ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT k, SUM({$col}) AS n FROM {$t} WHERE dim = %s AND day >= %s AND day <= %s GROUP BY k_hash, k ORDER BY n DESC LIMIT %d", $dim, $from, $to, (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$out['top'][ $dim ] = array();
			foreach ( (array) $rows as $r ) {
				$out['top'][ $dim ][ (string) $r['k'] ] = (int) $r['n'];
			}
		}
		return $out;
	}

	/** Value per key of one dimension for a period (the keys given, so a row can be compared even when it was not in the other period's top). @return array<string,int> */
	public static function values( $dim, array $keys, $from, $to ) {
		global $wpdb;
		if ( ! $keys ) {
			return array();
		}
		$col = 'page' === $dim ? 'pageviews' : 'sessions';
		$in  = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT k, SUM(' . $col . ') AS n FROM ' . Tables::summary() . " WHERE dim = %s AND day >= %s AND day <= %s AND k_hash IN ({$in}) GROUP BY k_hash, k", array_merge( array( $dim, $from, $to ), array_map( 'md5', array_map( static function ( $k ) { return substr( (string) $k, 0, 300 ); }, $keys ) ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ (string) $r['k'] ] = (int) $r['n'];
		}
		return $out;
	}

	/** Per calendar month totals, for a long chart. @return array<int,array{month:string,sessions:int,pageviews:int,visitors:int,days:int}> */
	public static function months( $from, $to ) {
		global $wpdb;
		$t    = Tables::summary();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(day, '%%Y-%%m') AS m, COUNT(*) AS days, SUM(sessions) AS sessions, SUM(pageviews) AS pageviews, SUM(visitors) AS visitors FROM {$t} WHERE dim = 'total' AND day >= %s AND day <= %s GROUP BY m ORDER BY m ASC", $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array( 'month' => (string) $r['m'], 'days' => (int) $r['days'], 'sessions' => (int) $r['sessions'], 'pageviews' => (int) $r['pageviews'], 'visitors' => (int) $r['visitors'] );
		}
		return $out;
	}

	/**
	 * A period next to the same dates a year earlier. The current period is cut at yesterday (today is not summarised). `complete` says
	 * whether the earlier year has a summary for every day; if not, the comparison is shown as partial rather than guessed.
	 */
	public static function compare( $from, $to ) {
		$yesterday = wp_date( 'Y-m-d', Clock::time() - DAY_IN_SECONDS );
		$to        = min( $to, $yesterday );
		$out       = array( 'enabled' => self::enabled(), 'years' => self::years(), 'first_day' => self::first_day(), 'from' => $from, 'to' => $to );
		if ( $from > $to ) {
			return $out + array( 'current' => null, 'previous' => null, 'expected_days' => 0, 'complete' => false );
		}
		$pf  = self::last_year( $from );
		$pt  = self::last_year( $to );
		$exp = (int) ( ( strtotime( $to . ' UTC' ) - strtotime( $from . ' UTC' ) ) / DAY_IN_SECONDS ) + 1;
		$cur = self::period( $from, $to );
		$prv = self::period( $pf, $pt );
		$rows = array();
		foreach ( $cur['top'] as $dim => $list ) {
			$before       = self::values( $dim, array_keys( $list ), $pf, $pt );
			$rows[ $dim ] = array();
			foreach ( $list as $k => $n ) {
				$rows[ $dim ][] = array( 'k' => (string) $k, 'now' => $n, 'before' => isset( $before[ $k ] ) ? $before[ $k ] : 0 );
			}
		}
		return $out + array(
			'rows'          => $rows,
			'previous_from' => $pf,
			'previous_to'   => $pt,
			'expected_days' => $exp,
			'current'       => $cur,
			'previous'      => $prv,
			'complete'      => $prv['days'] >= $exp && $cur['days'] >= $exp,
		);
	}
}
