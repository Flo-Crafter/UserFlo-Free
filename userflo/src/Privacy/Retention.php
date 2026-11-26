<?php
namespace UserFlo\Privacy;

use UserFlo\Core\Clock;
use UserFlo\Core\Lock;
use UserFlo\Database\Tables;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Scheduled, batched deletion of expired analytics data.
 *
 * One run at a time (a lock), bounded batches inside a time budget, and a stored state that survives the request: what was deleted, when
 * the last run finished, what went wrong, and (for a purge started from Tools → Database & Storage) the cutoff the job is working to, so a
 * run that is cut short is simply continued by the next one. Nothing newer than the cutoff is ever deleted, and nothing is deleted from a
 * day whose daily summary is missing or does not match what is stored (see Summary::safe_cutoff).
 */
final class Retention {

	const HOOK   = 'userflo_cleanup';
	const BATCH  = 1000; // Small batches keep each DELETE short on heavily indexed tables and let the time budget be respected.
	const STATE  = 'userflo_retention_state';
	const LOCK   = 'retention';
	const MIN_MANUAL_DAYS = 7; // A purge from the screen cannot reach into the last week.
	const SHORT_TYPES     = array( 'click', 'scroll' ); // The interaction types the optional shorter period applies to.

	/** Retention profiles offered on the Database & Storage screen. Custom is any other value. */
	const PRESETS = array(
		'minimal'  => 30,
		'standard' => 90,
		'long'     => 365,
	);

	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( self::HOOK . '_more', array( __CLASS__, 'run' ) );
		if ( ! wp_next_scheduled( self::HOOK ) && wp_doing_cron() ) {
			self::schedule();
		}
		add_action( 'admin_init', array( __CLASS__, 'self_heal' ) );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * WP-Cron only runs when the site gets visits. If the daily event has been lost, or the last run is more than 36 hours old on a site
	 * where someone is using the admin, a one-off run is queued. Costs one option read per admin request and nothing else.
	 */
	public static function self_heal() {
		if ( wp_doing_ajax() || ! current_user_can( \UserFlo\Plugin::cap() ) ) {
			return;
		}
		self::schedule();
		$s   = self::state();
		$due = ! empty( $s['last_run'] ) ? ( Clock::time() - (int) $s['last_run'] > 36 * HOUR_IN_SECONDS ) : false;
		if ( $due && ! wp_next_scheduled( self::HOOK . '_more' ) && ! Lock::held( self::LOCK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK . '_more' );
		}
	}

	// ---- settings and cutoffs ---------------------------------------------------------------------------------------------------------

	/** Which profile a number of days is: minimal | standard | long | custom | forever. */
	public static function profile( $days ) {
		$days = (int) $days;
		if ( $days <= 0 ) {
			return 'forever';
		}
		$k = array_search( $days, self::PRESETS, true );
		return false === $k ? 'custom' : $k;
	}

	/** UTC mysql datetime before which data is past its retention period, or '' when retention is off. */
	public static function cutoff( $days = null ) {
		$days = null === $days ? (int) Settings::get( 'retention_days' ) : (int) $days;
		return $days > 0 ? Clock::mysql( Clock::time() - $days * DAY_IN_SECONDS ) : '';
	}

	/** UTC mysql datetime before which clicks and scrolls are past their own, shorter period; '' when they follow the main period. */
	public static function short_cutoff() {
		$short = (int) Settings::get( 'short_event_days' );
		$main  = (int) Settings::get( 'retention_days' );
		if ( $short <= 0 || ( $main > 0 && $short >= $main ) ) {
			return '';
		}
		return Clock::mysql( Clock::time() - $short * DAY_IN_SECONDS );
	}

	/**
	 * How much data is past a cutoff, counted up to a limit so asking never means reading a whole large table.
	 *
	 * @param string $cutoff UTC mysql datetime.
	 * @return array{events:int,pageviews:int,sessions:int,goal_hits:int,orders:int,capped:bool} Each at most $cap (and `capped` says some were cut).
	 */
	public static function impact( $cutoff, $cap = 100000 ) {
		global $wpdb;
		$map = array(
			'events'    => array( Tables::events(), 'occurred_at' ),
			'pageviews' => array( Tables::pageviews(), 'viewed_at' ),
			'sessions'  => array( Tables::sessions(), 'last_activity_at' ),
			'goal_hits' => array( Tables::goal_hits(), 'hit_at' ),
			'orders'    => array( Tables::orders(), 'created_gmt' ),
		);
		$out = array( 'capped' => false );
		foreach ( $map as $k => $m ) {
			$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM (SELECT 1 FROM {$m[0]} WHERE {$m[1]} < %s LIMIT %d) x", $cutoff, (int) $cap ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$out[ $k ] = $n;
			if ( $n >= $cap ) {
				$out['capped'] = true;
			}
		}
		return $out;
	}

	// ---- state ------------------------------------------------------------------------------------------------------------------------

	public static function state() {
		$s = get_option( self::STATE, array() );
		return is_array( $s ) ? $s : array();
	}

	private static function save( array $s ) {
		update_option( self::STATE, $s, false );
	}

	/** Whether a run is in progress or queued to continue (for the screen and for refusing a second start). */
	public static function busy() {
		$s = self::state();
		return Lock::held( self::LOCK ) || ! empty( $s['job'] );
	}

	/**
	 * Starts a purge of everything past a cutoff chosen on the Database & Storage screen. The cutoff is stored with the job, so the
	 * runs that continue it work to the same moment, whatever the retention setting becomes meanwhile.
	 *
	 * @param string $date Local date Y-m-d: data from before the start of that day is deleted.
	 * @return array|\WP_Error The new state.
	 */
	public static function start_job( $date ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
			return new \WP_Error( 'userflo_bad_date', __( 'Choose a valid date.', 'userflo' ), array( 'status' => 400 ) );
		}
		try {
			$ts = ( new \DateTimeImmutable( $date . ' 00:00:00', wp_timezone() ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return new \WP_Error( 'userflo_bad_date', __( 'Choose a valid date.', 'userflo' ), array( 'status' => 400 ) );
		}
		if ( $ts > Clock::time() - self::MIN_MANUAL_DAYS * DAY_IN_SECONDS ) {
			/* translators: %d: number of days */
			return new \WP_Error( 'userflo_too_recent', sprintf( __( 'Choose a date at least %d days ago. The most recent data is never deleted from here.', 'userflo' ), self::MIN_MANUAL_DAYS ), array( 'status' => 400 ) );
		}
		if ( self::busy() ) {
			return new \WP_Error( 'userflo_busy', __( 'A cleanup is already running. Wait for it to finish.', 'userflo' ), array( 'status' => 409 ) );
		}
		$s        = self::state();
		$s['job'] = array( 'cutoff' => Clock::mysql( $ts ), 'date' => $date, 'started' => Clock::time(), 'deleted' => array(), 'by' => get_current_user_id() );
		self::save( $s );
		return $s;
	}

	// ---- single-table purges (kept, and used by the main run) -------------------------------------------------------------------------

	/**
	 * Heatmap counters older than $days. Lives here (not in the Pro heatmap code) so retention keeps applying to stored aggregates
	 * even when Pro is not installed: data protection does not depend on the edition.
	 */
	public static function purge_heat( $days, $budget = 10 ) {
		global $wpdb;
		$t = Tables::heat();
		return self::drain( $wpdb->prepare( "DELETE FROM {$t} WHERE day < %s LIMIT %d", gmdate( 'Y-m-d', Clock::time() - (int) $days * DAY_IN_SECONDS ), self::BATCH ), $budget ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Bot counters older than $days (0 keeps them). */
	public static function purge_bots( $days, $budget = 10 ) {
		global $wpdb;
		if ( (int) $days <= 0 ) {
			return 0;
		}
		$t = Tables::bots();
		return self::drain( $wpdb->prepare( "DELETE FROM {$t} WHERE day < %s LIMIT %d", gmdate( 'Y-m-d', Clock::time() - (int) $days * DAY_IN_SECONDS ), self::BATCH ), $budget ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Repeats one DELETE ... LIMIT until it removes less than a full batch or the budget is used. @return int Rows deleted. */
	private static function drain( $sql, $budget ) {
		global $wpdb;
		$end   = microtime( true ) + (float) $budget;
		$total = 0;
		do {
			$n = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			if ( false === $n ) {
				break;
			}
			$total += (int) $n;
		} while ( self::BATCH === (int) $n && microtime( true ) < $end );
		return $total;
	}

	// ---- the run ----------------------------------------------------------------------------------------------------------------------

	/**
	 * Does one bounded pass of everything that is due: heatmap and bot counters, the daily summaries (before anything is deleted), then
	 * the detailed rows older than the cutoff. Safe to call again at any time: a second caller while a run is working returns at once.
	 *
	 * @param int $time_budget Seconds this pass may use.
	 * @return array Rows deleted per table, plus `locked` (another run is working), `more` (rows are left, a follow-up is queued), `errors`.
	 */
	public static function run( $time_budget = 20 ) {
		global $wpdb;
		$out = array( 'events' => 0, 'pageviews' => 0, 'goal_hits' => 0, 'orders' => 0, 'sessions' => 0, 'visitors' => 0, 'locked' => false, 'more' => false, 'errors' => array() );
		$time_budget = max( 0, (int) $time_budget ); // 0 = one batch per table, then stop.
		if ( ! Lock::acquire( self::LOCK, max( 120, $time_budget * 4 ) ) ) {
			$out['locked'] = true;
			return $out;
		}
		$end = microtime( true ) + $time_budget;
		$st  = self::state();
		$st['running']  = true;
		$st['last_run'] = Clock::time();
		self::save( $st );

		try {
			$out = self::pass( $out, $end, $time_budget );
		} catch ( \Throwable $e ) {
			$out['errors'][] = 'exception';
			\UserFlo\Logger::add( 'error', 'retention', 'Cleanup stopped by an error; it will continue at the next run.' );
		}

		$st = self::state();
		$st['running'] = false;
		$st['last_run'] = Clock::time();
		if ( $out['errors'] ) {
			$st['last_error'] = array( 'at' => Clock::time(), 'what' => implode( ', ', array_unique( $out['errors'] ) ) );
		} else {
			$st['last_ok'] = Clock::time();
			unset( $st['last_error'] );
		}
		$st['last_counts'] = array_intersect_key( $out, array_flip( array( 'events', 'pageviews', 'goal_hits', 'orders', 'sessions', 'visitors' ) ) );
		if ( ! empty( $st['job'] ) ) {
			foreach ( $st['last_counts'] as $k => $n ) {
				$st['job']['deleted'][ $k ] = ( isset( $st['job']['deleted'][ $k ] ) ? (int) $st['job']['deleted'][ $k ] : 0 ) + (int) $n;
			}
			if ( ! $out['more'] && ! $out['errors'] ) {
				$st['last_job'] = $st['job'] + array( 'finished' => Clock::time() );
				unset( $st['job'] );
			}
		}
		self::save( $st );
		Lock::release( self::LOCK );
		// The daily numbers behind the Database & Storage screen are refreshed here, once per run (one metadata query).
		\UserFlo\Storage\Measure::record();
		// Keep draining in short follow-up runs instead of waiting a whole day.
		if ( ( $out['more'] || ! empty( $st['job'] ) ) && ! $out['errors'] && ! wp_next_scheduled( self::HOOK . '_more' ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK . '_more' );
		}
		return $out;
	}

	private static function pass( array $out, $end, $time_budget ) {
		global $wpdb;
		// Heatmap aggregates have their own (shorter) retention.
		self::purge_heat( (int) Settings::get( 'heatmap_retention_days' ), min( 10, $time_budget ) );
		// Finished days are summarised first (Insights\Summary), and raw rows of a day that is not summarised yet are never deleted.
		\UserFlo\Insights\Summary::roll_pending( min( 10, $time_budget ) );
		\UserFlo\Insights\Summary::purge();
		$days = (int) Settings::get( 'retention_days' );
		self::purge_bots( $days, min( 10, $time_budget ) );

		$st     = self::state();
		$cutoff = '';
		if ( ! empty( $st['job']['cutoff'] ) ) {
			$cutoff = (string) $st['job']['cutoff'];
			$limit  = Clock::mysql( Clock::time() - self::MIN_MANUAL_DAYS * DAY_IN_SECONDS );
			if ( $cutoff > $limit ) {
				$cutoff = $limit; // Never newer than a week, whatever was stored.
			}
		}
		if ( $days > 0 ) {
			$cfg = self::cutoff( $days );
			$cutoff = '' === $cutoff ? $cfg : max( $cutoff, $cfg ); // A purge may go further back in time than the setting says, never less far.
		}
		$short = self::short_cutoff();
		if ( '' === $cutoff && '' === $short ) {
			return $out; // Retention off and no job.
		}

		$e = Tables::events();
		$p = Tables::pageviews();
		$s = Tables::sessions();
		$v = Tables::visitors();
		$vend = microtime( true ) + max( 3, $time_budget ); // Checking that days are summarised has its own allowance, so a zero budget still deletes what is verified.

		$steps = array();
		if ( '' !== $short ) {
			$safe = \UserFlo\Insights\Summary::safe_cutoff( $short, $vend );
			$ph   = implode( ',', array_fill( 0, count( self::SHORT_TYPES ), '%s' ) );
			$steps['events'] = array( $wpdb->prepare( "DELETE FROM {$e} WHERE type IN ({$ph}) AND occurred_at < %s LIMIT %d", array_merge( self::SHORT_TYPES, array( $safe, self::BATCH ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders
		}
		if ( '' !== $cutoff ) {
			$cutoff = \UserFlo\Insights\Summary::safe_cutoff( $cutoff, $vend ); // Days not safely in a summary hold the deletion back.
			$steps['events_all'] = array( $wpdb->prepare( "DELETE FROM {$e} WHERE occurred_at < %s LIMIT %d", $cutoff, self::BATCH ), 'events' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$steps['pageviews']  = array( $wpdb->prepare( "DELETE FROM {$p} WHERE viewed_at < %s LIMIT %d", $cutoff, self::BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$steps['goal_hits']  = array( $wpdb->prepare( 'DELETE FROM ' . Tables::goal_hits() . ' WHERE hit_at < %s LIMIT %d', $cutoff, self::BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$steps['orders']     = array( $wpdb->prepare( 'DELETE FROM ' . Tables::orders() . ' WHERE created_gmt < %s LIMIT %d', $cutoff, self::BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$steps['sessions']   = array( $wpdb->prepare( "DELETE FROM {$s} WHERE last_activity_at < %s LIMIT %d", $cutoff, self::BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$steps['visitors']   = array( $wpdb->prepare( "DELETE FROM {$v} WHERE last_seen < %s AND NOT EXISTS (SELECT 1 FROM {$s} WHERE {$s}.visitor_id = {$v}.id) LIMIT %d", $cutoff, self::BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		foreach ( $steps as $name => $step ) {
			$into = isset( $step[1] ) ? $step[1] : $name;
			do {
				$n = $wpdb->query( $step[0] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
				if ( false === $n ) {
					$out['errors'][] = $name;
					\UserFlo\Logger::add( 'error', 'retention', sprintf( 'Cleanup step "%s" failed; the data of that step is kept and the next run tries again.', $name ) );
					break;
				}
				$out[ $into ] += (int) $n;
				Lock::renew( self::LOCK, max( 120, $time_budget * 4 ) );
			} while ( $n >= self::BATCH && microtime( true ) < $end );
			if ( false !== $n && $n >= self::BATCH ) {
				$out['more'] = true; // Time budget ran out with rows still expired.
			}
		}
		if ( $out['events'] + $out['pageviews'] + $out['goal_hits'] + $out['orders'] + $out['sessions'] + $out['visitors'] > 0 ) {
			\UserFlo\Logger::add( 'info', 'retention', sprintf( 'Deleted %d events, %d page views, %d sessions, %d visitors.', $out['events'], $out['pageviews'], $out['sessions'], $out['visitors'] ) );
		}
		return $out;
	}
}
