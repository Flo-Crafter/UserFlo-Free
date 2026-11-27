<?php
namespace UserFlo\Audit;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;
use UserFlo\Edition;
use UserFlo\Settings\Settings;
use UserFlo\Tracking\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * The one website scanner. A WP-Cron tick takes a few due pages, fetches each as a visitor would and stores what it found. It is deliberately
 * slow: a handful of pages per tick, one tick at a time (a lock), a daily cap, growing delays after failures, and no re-reading of a page whose
 * content has not changed. Pages come from the pages visitors actually viewed (the analytics), so the inventory and the statistics agree.
 *
 * Not running at all: the Website audit module switched off, `USERFLO_AUDIT_OFF`, or no cron. Data already collected stays.
 */
final class Scanner {

	const TICK   = 'userflo_audit_tick';
	const STATE  = 'userflo_audit_state';
	const LOCK   = 'userflo_audit_lock';
	const BATCH  = 3;     // Pages per tick.
	const GAP    = 300;   // Seconds between ticks while work remains.
	const TTL    = 1209600; // Re-read a page after 14 days.
	const HARD_PAGE_CAP = 2000; // Safety limit however the edition is configured.

	public static function register() {
		add_action( self::TICK, array( __CLASS__, 'tick' ) ); // The toggle listener is attached by Plugin::boot, whether or not the module is on.
	}

	public static function module_toggled( $key, $on ) {
		if ( 'audit' === $key ) {
			$on ? self::sync() : self::clear();
		}
	}

	/** Starts the safety-net schedule (the hourly event picks up whatever a missed single event left). */
	public static function sync() {
		wp_clear_scheduled_hook( self::TICK );
		if ( \UserFlo\Modules::on( 'audit' ) ) {
			wp_schedule_event( time() + 2 * MINUTE_IN_SECONDS, 'hourly', self::TICK );
		}
	}

	public static function clear() {
		wp_unschedule_hook( self::TICK );
		delete_option( self::LOCK );
	}

	/** Edition cap on pages in the inventory (0 = none, still bounded by HARD_PAGE_CAP). */
	public static function page_cap() {
		$c = (int) Edition::limit( 'audit_pages' );
		return $c ? $c : self::HARD_PAGE_CAP;
	}

	/** Most pages scanned in one day. */
	public static function daily_cap() {
		return (int) apply_filters( 'userflo_audit_daily_scans', 500 ); // A safety bound on one day's scanning, the same for every edition.
	}

	public static function ttl() {
		return max( HOUR_IN_SECONDS, (int) apply_filters( 'userflo_audit_rescan_seconds', self::TTL ) );
	}

	private static function state() {
		$s = get_option( self::STATE, array() );
		return array_merge( array( 'seeded' => 0, 'day' => '', 'n' => 0, 'last_tick' => 0 ), is_array( $s ) ? $s : array() );
	}

	private static function save_state( array $s ) {
		update_option( self::STATE, $s, false );
	}

	private static function lock() {
		if ( add_option( self::LOCK, time(), '', false ) ) {
			return true;
		}
		$taken = (int) get_option( self::LOCK, 0 );
		if ( $taken && time() - $taken < 10 * MINUTE_IN_SECONDS ) {
			return false;
		}
		global $wpdb;
		return 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", (string) time(), self::LOCK, (string) $taken ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	/** One round of work. Cheap when there is nothing to do. */
	public static function tick() {
		if ( ! \UserFlo\Modules::on( 'audit' ) || ( defined( 'USERFLO_AUDIT_OFF' ) && USERFLO_AUDIT_OFF ) || ! self::lock() ) {
			return array( 'scanned' => 0 );
		}
		$done = 0;
		try {
			$s = self::state();
			if ( time() - (int) $s['seeded'] > DAY_IN_SECONDS ) {
				self::seed();
				$s = self::state();
			}
			$today = gmdate( 'Y-m-d' );
			if ( $s['day'] !== $today ) {
				$s['day'] = $today;
				$s['n']   = 0;
			}
			$room = max( 0, self::daily_cap() - (int) $s['n'] );
			$take = min( (int) apply_filters( 'userflo_audit_batch', self::BATCH ), $room );
			if ( $take > 0 ) {
				foreach ( Store::due( $take ) as $page ) {
					self::scan_page( $page );
					++$done;
				}
			}
			$s['n']        += $done;
			$s['last_tick'] = time();
			self::save_state( $s );
		} finally {
			delete_option( self::LOCK );
		}
		if ( $done && Store::due_count() ) {
			wp_schedule_single_event( time() + self::GAP, self::TICK ); // More waiting: come back soon instead of after the hour.
		}
		return array( 'scanned' => $done );
	}

	/**
	 * Adds the pages visitors actually viewed (most viewed first, up to the cap) and the home page. Reads the analytics directly: a page that is
	 * left out of statistics by an exclusion rule is still part of the website and still inventoried.
	 */
	public static function seed() {
		global $wpdb;
		$cap  = self::page_cap();
		$from = Clock::mysql( Clock::time() - 30 * DAY_IN_SECONDS );
		$pv   = Tables::pageviews();
		Store::enqueue( '/', $cap );
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT url FROM {$pv} WHERE viewed_at >= %s GROUP BY url ORDER BY COUNT(*) DESC LIMIT %d", $from, $cap ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		foreach ( (array) $rows as $url ) {
			$path = Validator::path( $url );
			if ( null !== $path && ! \UserFlo\Exclusions\Service::page_stopped( $path ) ) { // A page the owner told FloLens not to track is not scanned either.
				Store::enqueue( $path, $cap );
			}
		}
		$s           = self::state();
		$s['seeded'] = time();
		self::save_state( $s );
	}

	/** Reads one page and stores the result. @param array $page A row of the pages table. */
	public static function scan_page( array $page ) {
		$id  = (int) $page['id'];
		$res = Fetcher::get( $page['url'] );
		if ( 'network' === $res['error'] || 'redirect_offsite' === $res['error'] || 'too_many_redirects' === $res['error'] || 'disabled' === $res['error'] || 'bad_path' === $res['error'] ) {
			Store::fail( $id, $res['status'], $res['error'] );
			return 'error';
		}
		if ( Fetcher::is_wall( $res ) || 'not_html' === $res['error'] || 'too_large' === $res['error'] ) {
			Store::fail( $id, $res['status'], '' === $res['error'] ? 'protected' : $res['error'], 'skipped' ); // Says nothing about the site: no findings.
			return 'skipped';
		}
		$ctx = array( 'url' => $page['url'], 'http_status' => $res['status'], 'headers' => $res['headers'], 'final_url' => $res['final_url'] );
		if ( $res['status'] >= 400 ) {
			Store::fail( $id, $res['status'], 'HTTP ' . $res['status'] );
			Store::replace_findings( $id, Checks::basic( $ctx, null ), array() );
			return 'error';
		}
		$hash = md5( $res['body'] );
		if ( $hash === $page['content_hash'] && 'ok' === $page['state'] && ! empty( $page['metrics'] ) && A11yReport::current( $page['metrics'], Findings::full() ) ) {
			Store::touch( $id, self::ttl() ); // Unchanged: nothing to parse or store.
			return 'unchanged';
		}
		$host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$parsed = Parser::parse( $res['body'], '' !== $res['final_url'] ? $res['final_url'] : home_url( $page['url'] ), array(
			'host' => $host,
			'dl'   => array_values( array_filter( explode( ',', strtolower( (string) Settings::get( 'download_extensions' ) ) ) ) ),
			'keep' => (array) apply_filters( 'userflo_query_params', array( 'p', 'page_id', 'cat', 'tag', 'post_type', 'product', 'paged' ) ),
		) );
		if ( null === $parsed ) {
			Store::fail( $id, $res['status'], 'unreadable', 'skipped' );
			return 'skipped';
		}
		$elements = array();
		foreach ( $parsed['elements'] as $e ) {
			$e['ekey'] = Identity::key( $e['kind'], $e['identifier'], $e['sig'] );
			$elements[] = $e;
		}
		$parsed['elements'] = $elements;
		/**
		 * Findings for this page. Lite supplies the basic ones; FloLens Pro adds the full set. Each finding: code (see Catalog), ekey (the element's
		 * key from `$parsed['elements']`, or null for the page) and evidence (short text).
		 */
		$findings = (array) apply_filters( 'userflo_audit_findings', Checks::basic( $ctx, $parsed ), $parsed, $ctx );
		// What was tested for accessibility, kept with the scan so "passed" and "not tested" can be shown honestly. Pro adds its checks here.
		$stats          = $parsed['stats'];
		$stats['a11y'] = array( 'v' => A11yBasic::VERSION, 'full' => Findings::full() ? 1 : 0, 'checks' => (array) apply_filters( 'userflo_audit_a11y_checks', A11yBasic::report( $parsed ), $parsed ) );
		Store::save_scan( $id, array(
			'http_status'  => $res['status'],
			'final_url'    => $res['final_url'],
			'content_hash' => $hash,
			'title'        => $parsed['title'],
			'description'  => $parsed['description'],
			'canonical'    => $parsed['canonical'],
			'robots'       => $parsed['robots'],
			'metrics'      => wp_json_encode( $stats ),
		), $elements, $findings, self::ttl() );
		$post = url_to_postid( home_url( $page['url'] ) );
		if ( $post ) {
			global $wpdb;
			$wpdb->update( Tables::audit_pages(), array( 'post_id' => (int) $post ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		}
		return 'ok';
	}

	/**
	 * Scan one page now (the "Scan again" action). At most once per ten minutes per page, and only pages inside the edition cap.
	 *
	 * @return array|\WP_Error
	 */
	public static function scan_now( $path ) {
		$path = Validator::path( $path );
		if ( null === $path ) {
			return new \WP_Error( 'userflo_audit_path', __( 'That is not a page address.', 'flolens' ), array( 'status' => 400 ) );
		}
		$id = Store::enqueue( $path, self::page_cap() );
		if ( ! $id ) {
			return new \WP_Error( 'userflo_audit_cap', __( 'This edition scans a limited number of pages.', 'flolens' ), array( 'status' => 403 ) );
		}
		$page = Store::page( $id );
		if ( $page['scanned_at'] && Clock::time() - strtotime( $page['scanned_at'] . ' UTC' ) < 10 * MINUTE_IN_SECONDS ) {
			return new \WP_Error( 'userflo_audit_rate', __( 'This page was scanned a moment ago. Try again in a few minutes.', 'flolens' ), array( 'status' => 429 ) );
		}
		if ( ! self::lock() ) {
			return new \WP_Error( 'userflo_audit_busy', __( 'A scan is already running. Try again in a minute.', 'flolens' ), array( 'status' => 429 ) );
		}
		try {
			$page['content_hash'] = ''; // A manual scan always reads the page.
			$r = self::scan_page( $page );
		} finally {
			delete_option( self::LOCK );
		}
		return array( 'result' => $r, 'page' => Store::page( $id ) );
	}
}
