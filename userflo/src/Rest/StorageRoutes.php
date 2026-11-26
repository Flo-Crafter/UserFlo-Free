<?php
namespace UserFlo\Rest;

use UserFlo\Core\Clock;
use UserFlo\Insights\Summary;
use UserFlo\Privacy\Retention;
use UserFlo\Settings\Settings;
use UserFlo\Storage\Maintenance;
use UserFlo\Storage\Measure;

defined( 'ABSPATH' ) || exit;

/**
 * Tools → Database & Storage. Reading needs the analytics capability; everything that changes data or settings needs an administrator.
 * Cookie-authenticated requests carry the REST nonce, which WordPress checks before a callback runs. There is no GET that changes anything,
 * and no endpoint takes SQL: every input is a number, a date, or a name from a fixed list. Deletion goes through Privacy\Retention and
 * summaries through Insights\Summary, the same code the daily run uses.
 */
final class StorageRoutes {

	const STEP_BUDGET = 8; // Seconds of work one request may do.

	public static function register() {
		$read  = array( Routes::class, 'can_view' );
		$write = array( __CLASS__, 'can_manage' );
		$date  = array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' );

		register_rest_route( Routes::NS, '/storage', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'overview' ), 'permission_callback' => $read ) );
		register_rest_route( Routes::NS, '/storage/refresh', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'refresh' ), 'permission_callback' => $read ) );
		register_rest_route( Routes::NS, '/storage/impact', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'impact' ),
			'permission_callback' => $read,
			'args'                => array( 'days' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 3650 ), 'date' => $date ),
		) );
		register_rest_route( Routes::NS, '/storage/retention', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'save_retention' ),
			'permission_callback' => $write,
			'args'                => array(
				'days'             => array( 'type' => 'integer', 'required' => true, 'minimum' => 0, 'maximum' => 3650 ),
				'short_event_days' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 3650 ),
				'confirm'          => array( 'type' => 'boolean', 'default' => false ),
			),
		) );
		register_rest_route( Routes::NS, '/storage/run', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'run' ), 'permission_callback' => $write ) );
		register_rest_route( Routes::NS, '/storage/purge', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'purge' ),
			'permission_callback' => $write,
			'args'                => array( 'date' => $date + array( 'required' => true ), 'confirm' => array( 'type' => 'boolean', 'default' => false ) ),
		) );
		register_rest_route( Routes::NS, '/storage/rebuild', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'rebuild' ),
			'permission_callback' => $write,
			'args'                => array( 'from' => $date + array( 'required' => true ), 'to' => $date + array( 'required' => true ), 'confirm' => array( 'type' => 'boolean', 'default' => false ) ),
		) );
		register_rest_route( Routes::NS, '/storage/step', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'step' ), 'permission_callback' => $write ) );
		register_rest_route( Routes::NS, '/storage/optimize', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'optimize' ),
			'permission_callback' => $write,
			'args'                => array( 'table' => array( 'type' => 'string', 'required' => true, 'enum' => Maintenance::allowed() ), 'confirm' => array( 'type' => 'boolean', 'default' => false ) ),
		) );
	}

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/** Everything the screen shows, from stored values only: no measuring, no counting. */
	public static function data() {
		$snap = Measure::read();
		$inv  = Measure::inventory();
		$rows = array();
		foreach ( $inv as $suffix => $meta ) {
			$t        = $snap && array_key_exists( $suffix, $snap['tables'] ) ? $snap['tables'][ $suffix ] : null;
			$rows[]   = array(
				'key'        => $suffix,
				'label'      => $meta['label'],
				'purpose'    => $meta['purpose'],
				'kind'       => $meta['kind'],
				'available'  => (bool) $t,
				'rows'       => $t ? $t['rows'] : null,
				'exact_rows' => $t ? $t['exact_rows'] : false,
				'data'       => $t ? $t['data'] : null,
				'index'      => $t ? $t['index'] : null,
				'free'       => $t ? $t['free'] : null,
				'engine'     => $t ? $t['engine'] : '',
				'can_rebuild' => $t && 'other' !== $meta['kind'],
			);
		}
		$ret   = Retention::state();
		$sum   = Summary::state();
		$days  = (int) Settings::get( 'retention_days' );
		$short = (int) Settings::get( 'short_event_days' );
		$job   = ! empty( $ret['job'] ) ? $ret['job'] : null;
		return array(
			'measured_at' => $snap ? (int) $snap['at'] : null,
			'source'      => $snap ? $snap['source'] : 'none',
			'totals'      => $snap ? $snap['totals'] : null,
			'tables'      => $rows,
			'growth'      => Measure::growth(),
			'settings'    => array(
				'retention_days'   => $days,
				'profile'          => Retention::profile( $days ),
				'presets'          => Retention::PRESETS,
				'short_event_days' => $short,
				'summary_years'    => (int) Settings::get( 'summary_years' ),
			),
			'retention'   => array(
				'running'     => \UserFlo\Core\Lock::held( Retention::LOCK ),
				'job'         => $job,
				'last_run'    => isset( $ret['last_run'] ) ? (int) $ret['last_run'] : null,
				'last_ok'     => isset( $ret['last_ok'] ) ? (int) $ret['last_ok'] : null,
				'last_error'  => isset( $ret['last_error'] ) ? $ret['last_error'] : null,
				'last_counts' => isset( $ret['last_counts'] ) ? $ret['last_counts'] : null,
				'last_job'    => isset( $ret['last_job'] ) ? $ret['last_job'] : null,
				'next'        => wp_next_scheduled( Retention::HOOK ) ?: null,
				'min_days'    => Retention::MIN_MANUAL_DAYS,
			),
			'summary'     => array(
				'enabled'     => Summary::enabled(),
				'first_day'   => Summary::first_day(),
				'last_ok'     => isset( $sum['last_ok'] ) ? (int) $sum['last_ok'] : null,
				'last_ok_day' => isset( $sum['last_ok_day'] ) ? $sum['last_ok_day'] : null,
				'error'       => isset( $sum['error'] ) ? $sum['error'] : null,
				'rebuild'     => Summary::rebuild_state(),
				'floor'       => Summary::rebuild_floor(),
				'upgrade'     => Summary::upgrade_pending(),
			),
			'maintenance' => Maintenance::state(),
			'now'         => Clock::time(),
		);
	}

	public static function overview() {
		return self::data();
	}

	public static function refresh() {
		Measure::measure();
		return self::data();
	}

	/** Rows past a retention period or a date, counted up to a limit. */
	public static function impact( \WP_REST_Request $r ) {
		if ( $r['date'] ) {
			try {
				$ts = ( new \DateTimeImmutable( $r['date'] . ' 00:00:00', wp_timezone() ) )->getTimestamp();
			} catch ( \Exception $e ) {
				return new \WP_Error( 'userflo_bad_date', __( 'Choose a valid date.', 'userflo' ), array( 'status' => 400 ) );
			}
			$cut = Clock::mysql( $ts );
		} elseif ( $r['days'] ) {
			$cut = Retention::cutoff( (int) $r['days'] );
		} else {
			return new \WP_Error( 'userflo_bad_request', __( 'Give a number of days or a date.', 'userflo' ), array( 'status' => 400 ) );
		}
		return array( 'cutoff' => $cut, 'cutoff_local' => wp_date( 'Y-m-d H:i', strtotime( $cut . ' UTC' ) ), 'impact' => Retention::impact( $cut ) );
	}

	/**
	 * Saves the detailed-data period (and the optional shorter period for clicks and scrolls). Making the period shorter than it is now
	 * makes existing data eligible for deletion at the next run, so it needs `confirm` once the request would affect stored rows.
	 */
	public static function save_retention( \WP_REST_Request $r ) {
		$new  = (int) $r['days'];
		$now  = (int) Settings::get( 'retention_days' );
		$sh   = null === $r['short_event_days'] ? (int) Settings::get( 'short_event_days' ) : (int) $r['short_event_days'];
		$cur  = (int) Settings::get( 'short_event_days' );
		$tighter = ( $new > 0 && ( 0 === $now || $new < $now ) ) || ( $sh > 0 && ( 0 === $cur || $sh < $cur ) );
		if ( $tighter && ! $r['confirm'] ) {
			$cuts = array();
			if ( $new > 0 && ( 0 === $now || $new < $now ) ) {
				$cuts['main'] = Retention::impact( Retention::cutoff( $new ) );
			}
			if ( $sh > 0 && ( 0 === $cur || $sh < $cur ) ) {
				$cuts['short'] = Retention::impact( Retention::cutoff( $sh ) );
			}
			$any = false;
			foreach ( $cuts as $c ) {
				if ( $c['events'] + $c['pageviews'] + $c['sessions'] > 0 ) {
					$any = true;
				}
			}
			if ( $any ) {
				return new \WP_Error( 'userflo_confirm', __( 'This makes stored data eligible for deletion. Confirm to continue.', 'userflo' ), array( 'status' => 409, 'impact' => $cuts ) );
			}
		}
		$all = Settings::all();
		$all['retention_days']   = $new;
		$all['short_event_days'] = $sh;
		update_option( Settings::OPTION, Settings::sanitize( $all ) );
		Settings::reset_cache();
		if ( (int) Settings::get( 'retention_days' ) !== $new || (int) Settings::get( 'short_event_days' ) !== $sh ) {
			return new \WP_Error( 'userflo_not_saved', __( 'The setting could not be saved.', 'userflo' ), array( 'status' => 500 ) );
		}
		return self::data();
	}

	/** Runs the same cleanup the daily schedule runs, now. */
	public static function run() {
		$res = Retention::run( self::STEP_BUDGET );
		return self::data() + array( 'result' => $res );
	}

	/** Starts a purge up to a chosen date and does its first step. */
	public static function purge( \WP_REST_Request $r ) {
		if ( ! $r['confirm'] ) {
			return new \WP_Error( 'userflo_confirm', __( 'Confirm to delete this data. It cannot be undone.', 'userflo' ), array( 'status' => 400 ) );
		}
		$job = Retention::start_job( (string) $r['date'] );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		$res = Retention::run( self::STEP_BUDGET );
		return self::data() + array( 'result' => $res );
	}

	public static function rebuild( \WP_REST_Request $r ) {
		if ( ! $r['confirm'] ) {
			return new \WP_Error( 'userflo_confirm', __( 'Confirm to rebuild the summaries.', 'userflo' ), array( 'status' => 400 ) );
		}
		$st = Summary::start_rebuild( (string) $r['from'], (string) $r['to'] );
		if ( is_wp_error( $st ) ) {
			return $st;
		}
		Summary::rebuild_step( self::STEP_BUDGET );
		return self::data();
	}

	/** Continues whichever job is open (a purge or a rebuild); the screen calls it until nothing is left. */
	public static function step() {
		$res = array();
		if ( Summary::rebuild_running() ) {
			Summary::rebuild_step( self::STEP_BUDGET );
		}
		$st = Retention::state();
		if ( ! empty( $st['job'] ) ) {
			$res = Retention::run( self::STEP_BUDGET );
		}
		return self::data() + array( 'result' => $res );
	}

	public static function optimize( \WP_REST_Request $r ) {
		if ( ! $r['confirm'] ) {
			return new \WP_Error( 'userflo_confirm', __( 'Confirm to rebuild this table.', 'userflo' ), array( 'status' => 400 ) );
		}
		$res = Maintenance::optimize( (string) $r['table'] );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return self::data() + array( 'result' => $res );
	}
}
