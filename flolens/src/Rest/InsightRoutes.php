<?php
namespace UserFlo\Rest;

use UserFlo\Insights\Achievements;
use UserFlo\Insights\AchievementDetail;
use UserFlo\Insights\Stats;
use UserFlo\Modules;

defined( 'ABSPATH' ) || exit;

/** Statistics and achievements (analytics capability required). Results are cached briefly. */
final class InsightRoutes {

	public static function register() {
		$base  = array( 'permission_callback' => array( Routes::class, 'can_view' ), 'methods' => 'GET' );
		$range = array(
			'days' => array( 'type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 366 ),
			'from' => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
			'to'   => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
		);
		register_rest_route( Routes::NS, '/stats/focus', $base + array( 'callback' => array( __CLASS__, 'focus' ), 'args' => $range + array( 'type' => array( 'type' => 'string', 'enum' => array_keys( \UserFlo\Focus\Profiles::types() ) ), 'lang' => array( 'type' => 'string', 'pattern' => '^[A-Za-z_]{2,10}$' ) ) ) );
		if ( Modules::needed( 'statistics' ) ) {
			register_rest_route( Routes::NS, '/stats/insights', $base + array( 'callback' => array( __CLASS__, 'insights' ), 'args' => $range ) );
		}
		if ( Modules::on( 'statistics' ) ) {
			register_rest_route( Routes::NS, '/stats/bots', $base + array( 'callback' => array( __CLASS__, 'bots' ), 'args' => $range ) );
			register_rest_route( Routes::NS, '/stats/year', $base + array( 'callback' => array( __CLASS__, 'year' ), 'args' => $range ) );
			register_rest_route( Routes::NS, '/stats/peaks', $base + array( 'callback' => array( __CLASS__, 'peaks' ), 'args' => $range ) );
			register_rest_route( Routes::NS, '/stats/campaigns', $base + array( 'callback' => array( __CLASS__, 'campaigns' ), 'args' => $range + (array) apply_filters( 'userflo_campaigns_rest_args', array(
				'group' => array( 'type' => 'string', 'enum' => array_keys( \UserFlo\Database\Reports::campaign_groups() ), 'default' => 'campaign' ),
			) ) ) );
			register_rest_route( Routes::NS, '/stats/journeys', $base + array( 'callback' => array( __CLASS__, 'journeys' ), 'args' => (array) apply_filters( 'userflo_journeys_rest_args', $range ) ) );
			$date = array( 'type' => 'string', 'required' => true, 'pattern' => '^\d{4}-\d{2}-\d{2}$' );
			register_rest_route( Routes::NS, '/stats/traffic/calendar', $base + array( 'callback' => array( __CLASS__, 'traffic_calendar' ), 'args' => array( 'from' => $date, 'to' => $date, 'metric' => array( 'type' => 'string', 'default' => 'pageviews', 'enum' => \UserFlo\Insights\Traffic::METRICS ) ) ) );
		}
		if ( Modules::on( 'achievements' ) ) {
			register_rest_route( Routes::NS, '/achievements', $base + array( 'callback' => array( __CLASS__, 'achievements' ) ) );
			register_rest_route( Routes::NS, '/achievements/(?P<id>[a-z0-9_]{2,40})', $base + array( 'callback' => array( __CLASS__, 'achievement' ), 'args' => array( 'id' => array( 'type' => 'string', 'pattern' => '^[a-z0-9_]{2,40}$' ) ) ) );
		}
	}

	/** The journey summary (entrances, common paths, exits). FloLens Pro answers with the full analysis through `userflo_journeys_response`. */
	public static function journeys( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$other = apply_filters( 'userflo_journeys_response', null, $r, $range );
		if ( null !== $other ) {
			return $other;
		}
		$ttl = self::ttl();
		$key = 'userflo_jny_basic_' . md5( wp_json_encode( $range ) );
		$out = $ttl > 0 ? get_transient( $key ) : false;
		if ( ! is_array( $out ) ) {
			$out = \UserFlo\Insights\Journeys::build( $range[0], $range[1] );
			if ( $ttl > 0 ) {
				set_transient( $key, $out, $ttl );
			}
		}
		return $out;
	}

	/** Traffic calendar endpoints are live (not cached): today's numbers must be current and each call is a few indexed aggregates. */
	public static function traffic_calendar( \WP_REST_Request $r ) {
		$other = apply_filters( 'userflo_traffic_calendar_response', null, $r['from'], $r['to'], $r['metric'] ); // FloLens Pro: other metrics and windows.
		if ( null !== $other ) {
			return $other;
		}
		$ok = \UserFlo\Insights\Traffic::lite_window( $r['from'], $r['to'], $r['metric'] );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return \UserFlo\Insights\Traffic::calendar( $r['from'], $r['to'] );
	}

	private static function ttl() {
		return (int) apply_filters( 'userflo_overview_cache_ttl', 120 );
	}

	public static function insights( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$ttl = self::ttl();
		$key = 'userflo_ins_' . md5( wp_json_encode( $range ) );
		$out = $ttl > 0 ? get_transient( $key ) : false;
		if ( ! is_array( $out ) ) {
			$out = Stats::build( $range[0], $range[1] );
			if ( $ttl > 0 ) {
				set_transient( $key, $out, $ttl );
			}
		}
		return $out;
	}

	/** Humans next to bots: counters of requests by AI crawlers, search engines and other bots for the period. */
	public static function bots( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$ttl = self::ttl();
		$key = 'userflo_bots_' . md5( wp_json_encode( $range ) );
		$out = $ttl > 0 ? get_transient( $key ) : false;
		if ( ! is_array( $out ) ) {
			$out    = \UserFlo\Database\Reports::bots( wp_date( 'Y-m-d', $range[0] ), wp_date( 'Y-m-d', $range[1] - 1 ) );
			$groups = array();
			foreach ( $out['bots'] as $i => $b ) { // Which AI is behind each crawler, and what it does there.
				if ( 'ai' === $b['kind'] ) {
					$d                         = \UserFlo\Tracking\Bots::detail( $b['bot'] );
					$out['bots'][ $i ]         = $b + $d;
					$groups[ $d['assistant'] ] = ( isset( $groups[ $d['assistant'] ] ) ? $groups[ $d['assistant'] ] : 0 ) + $b['hits'];
				}
			}
			arsort( $groups );
			$out['assistants'] = array();
			foreach ( $groups as $name => $n ) {
				$out['assistants'][] = array( 'assistant' => $name, 'hits' => $n );
			}
			$out['referrals'] = \UserFlo\Insights\AiReferrals::build( $range[0], $range[1] );
			if ( $ttl > 0 ) {
				set_transient( $key, $out, $ttl );
			}
		}
		$out['enabled'] = (bool) \UserFlo\Settings\Settings::get( 'bot_counter' );
		return $out;
	}

	/** The period next to the same dates one year earlier, and the monthly totals of the summarised years, from the long-term daily summaries. */
	public static function year( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$from = wp_date( 'Y-m-d', $range[0] );
		$to   = wp_date( 'Y-m-d', $range[1] - 1 );
		$out  = \UserFlo\Insights\Summary::compare( $from, $to );
		$out['months'] = $out['enabled'] ? \UserFlo\Insights\Summary::months( wp_date( 'Y-m-d', \UserFlo\Core\Clock::time() - \UserFlo\Insights\Summary::years() * 366 * DAY_IN_SECONDS ), $to ) : array();
		return $out;
	}

	/** Peak times and days: sessions by weekday and hour, logged-in and anonymous. */
	public static function peaks( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$ttl = self::ttl();
		$key = 'userflo_peak_' . md5( wp_json_encode( $range ) );
		$out = $ttl > 0 ? get_transient( $key ) : false;
		if ( ! is_array( $out ) ) {
			$out = \UserFlo\Insights\Peaks::build( $range[0], $range[1] );
			if ( $ttl > 0 ) {
				set_transient( $key, $out, $ttl );
			}
		}
		return $out;
	}

	public static function focus( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$type = $r->get_param( 'type' ) ? (string) $r->get_param( 'type' ) : (string) \UserFlo\Settings\Settings::get( 'site_type' );
		$lang = (string) $r->get_param( 'lang' );
		if ( '' !== $lang && ! isset( \UserFlo\Translations\Routes::available()[ $lang ] ) ) {
			$lang = '';
		}
		$ttl  = self::ttl();
		$key  = 'userflo_foc_' . md5( wp_json_encode( array( $range, $type, $lang ) ) );
		$out  = $ttl > 0 ? get_transient( $key ) : false;
		if ( ! is_array( $out ) ) {
			$out = '' !== $lang ? \UserFlo\I18n::with_locale( $lang, static function () use ( $type, $range ) { return \UserFlo\Focus\Stats::build( $type, $range[0], $range[1] ); } ) : \UserFlo\Focus\Stats::build( $type, $range[0], $range[1] );
			if ( $ttl > 0 ) {
				set_transient( $key, $out, $ttl );
			}
		}
		return $out;
	}

	/** Sessions by campaign. Single-value groupings are Lite; combined groupings and the previous-period comparison are campaigns_detail. */
	public static function campaigns( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$group  = (string) $r['group'];
		$groups = \UserFlo\Database\Reports::campaign_groups();
		if ( ! isset( $groups[ $group ] ) ) {
			$group = 'campaign';
		}
		$compare = (bool) $r->get_param( 'compare' ); // Only FloLens Pro offers the comparison (it adds the parameter).
		$ttl     = self::ttl();
		$key     = 'userflo_cmp_' . md5( wp_json_encode( array( $range, $group, $compare ) ) );
		$out     = $ttl > 0 ? get_transient( $key ) : false;
		if ( ! is_array( $out ) ) {
			$from = \UserFlo\Core\Clock::mysql( $range[0] );
			$to   = \UserFlo\Core\Clock::mysql( $range[1] );
			$out  = \UserFlo\Database\Reports::campaigns( $from, $to, $group );
			$out  = (array) apply_filters( 'userflo_campaigns_report', $out, $range, $group, $compare );
			$out['group'] = $group;
			if ( $ttl > 0 ) {
				set_transient( $key, $out, $ttl );
			}
		}
		$out['backfilling'] = \UserFlo\Insights\UtmBackfill::pending(); // Not cached: it changes when the background job finishes.
		return $out;
	}

	public static function achievements() {
		$ttl = self::ttl();
		$out = $ttl > 0 ? get_transient( 'userflo_ach' ) : false;
		if ( ! is_array( $out ) ) {
			$out = Achievements::evaluate( Achievements::metrics() );
			if ( $ttl > 0 ) {
				set_transient( 'userflo_ach', $out, 5 * $ttl );
			}
		}
		return $out;
	}

	public static function achievement( \WP_REST_Request $r ) {
		$id     = (string) $r['id'];
		$metric = Achievements::metric_for( $id );
		if ( null === $metric ) {
			return new \WP_Error( 'userflo_unknown_achievement', __( 'Unknown achievement.', 'flolens' ), array( 'status' => 404 ) );
		}
		$ttl = self::ttl();
		$key = 'userflo_achd_' . $metric;
		$out = $ttl > 0 ? get_transient( $key ) : false;
		if ( ! is_array( $out ) ) {
			$out = AchievementDetail::build( $metric, $id );
			if ( $ttl > 0 ) {
				set_transient( $key, $out, 5 * $ttl );
			}
		}
		return $out;
	}
}
