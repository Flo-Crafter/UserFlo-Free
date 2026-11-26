<?php
namespace UserFlo\Insights;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Achievement badges built from the site's own tracking data. Computed on request (cached briefly), nothing is stored,
 * so badges reflect data still within retention. Tiers: copper < silver < gold < diamond < olympian.
 */
final class Achievements {

	const TIERS = array( 'copper', 'silver', 'gold', 'diamond', 'olympian' );

	/** Marks a literal for translation (extracted by bin/make-pot.py) and translates it at runtime. */
	private static function t( $text ) {
		return __( $text, 'userflo' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
	}

	/** Metric keys are produced by metrics(). Families expand to one badge per tier. */
	private static function families() {
		return array(
			// category, metric, [names per tier], description (%s = target), targets
			array( 'traffic', 'pageviews', array( self::t( 'First Hundred' ), self::t( 'Thousand Club' ), self::t( 'Ten Thousand Strong' ), self::t( 'Hundred Thousand Hall' ), self::t( 'Million Views' ) ), self::t( 'Record %s page views.' ), array( 100, 1000, 10000, 100000, 1000000 ) ),
			array( 'traffic', 'visitors', array( self::t( 'Open Doors' ), self::t( 'Growing Crowd' ), self::t( 'Busy Lobby' ), self::t( 'Packed House' ), self::t( 'City Square' ) ), self::t( 'Welcome %s different visitors.' ), array( 25, 250, 2500, 25000, 250000 ) ),
			array( 'traffic', 'sessions', array( self::t( 'Sessions Begin' ), self::t( 'Regular Rhythm' ), self::t( 'Session Machine' ), self::t( 'Session Tide' ), self::t( 'Session Ocean' ) ), self::t( 'Record %s sessions.' ), array( 50, 500, 5000, 50000, 500000 ) ),
			array( 'traffic', 'unique_pages', array( self::t( 'Page Explorer' ), self::t( 'Site Cartographer' ), self::t( 'Library Builder' ), self::t( 'Page Atlas' ), self::t( 'Endless Archive' ) ), self::t( 'Get views on %s different pages.' ), array( 5, 25, 100, 500, 2500 ) ),
			array( 'traffic', 'peak_day', array( self::t( 'Busy Day' ), self::t( 'Rush Hour Day' ), self::t( 'Spike Surfer' ), self::t( 'Traffic Tsunami' ), self::t( 'Viral Day' ) ), self::t( 'Reach %s sessions in a single day.' ), array( 10, 50, 250, 1000, 5000 ) ),
			array( 'engagement', 'engaged_hours', array( self::t( 'Attention Earned' ), self::t( 'Time Well Spent' ), self::t( 'Reading Room' ), self::t( 'Marathon Readers' ), self::t( 'Time Dilation' ) ), self::t( 'Accumulate %s hours of active engagement.' ), array( 1, 10, 100, 1000, 10000 ) ),
			array( 'engagement', 'deep_reads', array( self::t( 'Scroll Starter' ), self::t( 'Scroll Believers' ), self::t( 'Bottom Feeders' ), self::t( 'Reach The Footer' ), self::t( 'Footer Legends' ) ), self::t( 'Get %s page views scrolled to the very bottom.' ), array( 10, 100, 1000, 10000, 100000 ) ),
			array( 'engagement', 'long_session_min', array( self::t( 'Sticking Around' ), self::t( 'Settled In' ), self::t( 'Deep Dive' ), self::t( 'Camping Out' ), self::t( 'Moved In' ) ), self::t( 'Have a session last %s minutes.' ), array( 5, 15, 30, 60, 180 ) ),
			array( 'journeys', 'max_pages_session', array( self::t( 'Curious Click' ), self::t( 'Page Hopper' ), self::t( 'Journey Planner' ), self::t( 'Rabbit Hole' ), self::t( 'Odyssey' ) ), self::t( 'A single session visits %s pages.' ), array( 5, 10, 25, 50, 100 ) ),
			array( 'journeys', 'multi_page_sessions', array( self::t( 'Beyond The Landing' ), self::t( 'Browsing Habit' ), self::t( 'Funnel Walkers' ), self::t( 'Site Wanderers' ), self::t( 'Nomads' ) ), self::t( 'Have %s sessions that view 2+ pages.' ), array( 25, 250, 2500, 25000, 250000 ) ),
			array( 'identity', 'logged_in_visitors', array( self::t( 'First Member' ), self::t( 'Member Circle' ), self::t( 'Community' ), self::t( 'Membership Hall' ), self::t( 'Citizens' ) ), self::t( 'See %s logged-in visitors.' ), array( 1, 10, 100, 1000, 10000 ) ),
			array( 'identity', 'returning_visitors', array( self::t( 'Welcome Back' ), self::t( 'Familiar Faces' ), self::t( 'Loyal Following' ), self::t( 'Regulars Bar' ), self::t( 'Hall Of Regulars' ) ), self::t( 'Have %s visitors come back for another session.' ), array( 5, 50, 500, 5000, 50000 ) ),
			array( 'identity', 'loyal_visitors', array( self::t( 'Fifth Visit' ), self::t( 'Devoted' ), self::t( 'Superfans' ), self::t( 'Inner Circle' ), self::t( 'Legends' ) ), self::t( 'Have %s visitors with 5+ sessions.' ), array( 1, 10, 100, 1000, 10000 ) ),
			array( 'interactions', 'clicks', array( self::t( 'First Clicks' ), self::t( 'Click Rhythm' ), self::t( 'Click Storm' ), self::t( 'Click Avalanche' ), self::t( 'Click Eternity' ) ), self::t( 'Record %s tracked clicks.' ), array( 100, 1000, 10000, 100000, 1000000 ) ),
			array( 'interactions', 'outbound', array( self::t( 'Sending Visitors Out' ), self::t( 'Link Ambassador' ), self::t( 'Outbound Highway' ), self::t( 'Referral Engine' ), self::t( 'Link Empire' ) ), self::t( 'Record %s outbound link clicks.' ), array( 5, 50, 500, 5000, 50000 ) ),
			array( 'interactions', 'downloads', array( self::t( 'First Download' ), self::t( 'File Sharer' ), self::t( 'Download Depot' ), self::t( 'Download Hub' ), self::t( 'Download Titan' ) ), self::t( 'Record %s file downloads.' ), array( 1, 25, 250, 2500, 25000 ) ),
			array( 'interactions', 'forms', array( self::t( 'First Form' ), self::t( 'Form Filler' ), self::t( 'Form Factory' ), self::t( 'Lead Magnet' ), self::t( 'Form Legend' ) ), self::t( 'Record %s form submissions.' ), array( 1, 25, 250, 2500, 25000 ) ),
			array( 'interactions', 'media', array( self::t( 'Press Play' ), self::t( 'Showtime' ), self::t( 'Media Marathon' ), self::t( 'Screening Room' ), self::t( 'Cinema Palace' ) ), self::t( 'Record %s video or audio plays.' ), array( 1, 25, 250, 2500, 25000 ) ),
			array( 'interactions', 'custom', array( self::t( 'Custom Spark' ), self::t( 'Event Engineer' ), self::t( 'Signal Smith' ), self::t( 'Event Orchestra' ), self::t( 'Event Cosmos' ) ), self::t( 'Record %s custom events (UserFlo.track).' ), array( 1, 50, 500, 5000, 50000 ) ),
			array( 'interactions', 'commerce', array( self::t( 'First Basket' ), self::t( 'Shopping Spree' ), self::t( 'Cart Whisperer' ), self::t( 'Retail Therapy' ), self::t( 'Mall Of The World' ) ), self::t( 'Record %s shop events.' ), array( 1, 25, 250, 2500, 25000 ) ),
			array( 'devices', 'mobile_visitors', array( self::t( 'Pocket Visitors' ), self::t( 'Thumb Traffic' ), self::t( 'Mobile Majority' ), self::t( 'Pocket Crowd' ), self::t( 'Mobile Nation' ) ), self::t( 'Welcome %s visitors on phones.' ), array( 5, 50, 500, 5000, 50000 ) ),
			array( 'devices', 'desktop_visitors', array( self::t( 'Desk Duty' ), self::t( 'Workstation Crowd' ), self::t( 'Big Screen Club' ), self::t( 'Desktop Congregation' ), self::t( 'Desktop Empire' ) ), self::t( 'Welcome %s visitors on desktops.' ), array( 5, 50, 500, 5000, 50000 ) ),
			array( 'devices', 'tablet_visitors', array( self::t( 'Tablet Tapper' ), self::t( 'Couch Browsers' ), self::t( 'Tablet Tribe' ), self::t( 'Slate Society' ), self::t( 'Tablet Titans' ) ), self::t( 'Welcome %s visitors on tablets.' ), array( 1, 10, 100, 1000, 10000 ) ),
			array( 'devices', 'browsers', array( self::t( 'Two Browsers' ), self::t( 'Browser Zoo' ), self::t( 'Engine Collector' ), self::t( 'Browser Bazaar' ), self::t( 'Browser Cosmos' ) ), self::t( 'See %s different browsers.' ), array( 2, 4, 6, 8, 12 ) ),
			array( 'rhythm', 'streak', array( self::t( 'Three In A Row' ), self::t( 'Week Streak' ), self::t( 'Fortnight Flow' ), self::t( 'Month Streak' ), self::t( 'Quarter Streak' ) ), self::t( 'Get visitors on %s days in a row.' ), array( 3, 7, 14, 30, 90 ) ),
			array( 'rhythm', 'weekend_sessions', array( self::t( 'Weekend Visitors' ), self::t( 'Weekend Warriors' ), self::t( 'Saturday Crowd' ), self::t( 'Weekend Wave' ), self::t( 'Weekend Empire' ) ), self::t( 'Record %s weekend sessions.' ), array( 10, 100, 1000, 10000, 100000 ) ),
			array( 'rhythm', 'night_sessions', array( self::t( 'Night Owl Visit' ), self::t( 'Night Shift' ), self::t( 'After Hours' ), self::t( 'Insomniac Index' ), self::t( 'Never Sleeps' ) ), self::t( 'Record %s sessions between midnight and 5 am (site time).' ), array( 5, 50, 500, 5000, 50000 ) ),
		);
	}

	/** One-off badges: category, id, name, description, metric, target, secret. */
	private static function singles() {
		return array(
			array( 'setup', 'tracking_on', self::t( 'Eyes Open' ), self::t( 'Tracking is enabled and has recorded data.' ), 'tracking_ok', 1, 0 ),
			array( 'setup', 'consent_set', self::t( 'Consent Keeper' ), self::t( 'A consent mode is configured so tracking waits for permission.' ), 'consent_on', 1, 0 ),
			array( 'setup', 'ip_private', self::t( 'Privacy Guardian' ), self::t( 'Visitor IPs are anonymised, hashed or not stored.' ), 'ip_private', 1, 0 ),
			array( 'setup', 'retention', self::t( 'Tidy Archivist' ), self::t( 'A data retention period is set so old data is removed automatically.' ), 'retention_on', 1, 0 ),
			array( 'setup', 'heatmaps', self::t( 'Heat Seeker' ), self::t( 'Heatmaps are enabled and have recorded clicks.' ), 'heat_clicks', 1, 0 ),
			array( 'setup', 'exported', self::t( 'Data Courier' ), self::t( 'Export your data at least once.' ), 'exports', 1, 0 ),
			array( 'setup', 'integrated', self::t( 'Connected' ), self::t( 'Connect an analytics integration.' ), 'integrations', 1, 0 ),
			array( 'secret', 's_midnight', self::t( 'Witching Hour' ), self::t( 'Secret: someone visited at exactly 3 am (site time).' ), 'witching', 1, 1 ),
			array( 'secret', 's_marathon', self::t( 'Marathon Visitor' ), self::t( 'Secret: a single session lasted over 3 hours.' ), 'long_session_min', 180, 1 ),
			array( 'secret', 's_hundred', self::t( 'Perfect Hundred' ), self::t( 'Secret: one visitor viewed 100+ pages in total.' ), 'max_visitor_pages', 100, 1 ),
			array( 'secret', 's_everything', self::t( 'Full Spectrum' ), self::t( 'Secret: every event type has been recorded at least once.' ), 'event_types', 8, 1 ),
		);
	}

	public static function metrics() {
		global $wpdb;
		$v   = Tables::visitors();
		$s   = Tables::sessions();
		$p   = Tables::pageviews();
		$e   = Tables::events();
		$off = (int) wp_timezone()->getOffset( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) );
		$m   = array();

		$row = $wpdb->get_row( "SELECT COUNT(*) AS visitors, COALESCE(SUM(session_count>1),0) AS returning_visitors, COALESCE(SUM(session_count>=5),0) AS loyal_visitors, COALESCE(SUM(user_id IS NOT NULL),0) AS logged_in_visitors, COALESCE(SUM(engaged_seconds),0) AS eng, COALESCE(MAX(pageview_count),0) AS max_visitor_pages FROM {$v}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$m['visitors']           = (int) $row['visitors'];
		$m['returning_visitors'] = (int) $row['returning_visitors'];
		$m['loyal_visitors']     = (int) $row['loyal_visitors'];
		$m['logged_in_visitors'] = (int) $row['logged_in_visitors'];
		$m['engaged_hours']      = (int) floor( $row['eng'] / 3600 );
		$m['max_visitor_pages']  = (int) $row['max_visitor_pages'];

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS sessions, COALESCE(SUM(pageview_count>=2),0) AS multi, COALESCE(MAX(pageview_count),0) AS maxp, COALESCE(MAX(TIMESTAMPDIFF(MINUTE,started_at,last_activity_at)),0) AS longest,
			COALESCE(SUM(DAYOFWEEK(DATE_ADD(started_at, INTERVAL %d SECOND)) IN (1,7)),0) AS weekend, COALESCE(SUM(HOUR(DATE_ADD(started_at, INTERVAL %d SECOND)) < 5),0) AS night, COALESCE(SUM(HOUR(DATE_ADD(started_at, INTERVAL %d SECOND)) = 3),0) AS witching FROM {$s}", $off, $off, $off ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$m['sessions']            = (int) $row['sessions'];
		$m['multi_page_sessions'] = (int) $row['multi'];
		$m['max_pages_session']   = (int) $row['maxp'];
		$m['long_session_min']    = (int) $row['longest'];
		$m['weekend_sessions']    = (int) $row['weekend'];
		$m['night_sessions']      = (int) $row['night'];
		$m['witching']            = (int) $row['witching'];

		$row = $wpdb->get_row( "SELECT COUNT(*) AS pv, COUNT(DISTINCT url) AS urls, COALESCE(SUM(max_scroll>=100),0) AS deep FROM {$p}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$m['pageviews']    = (int) $row['pv'];
		$m['unique_pages'] = (int) $row['urls'];
		$m['deep_reads']   = (int) $row['deep'];

		$types = array( 'click' => 'clicks', 'outbound' => 'outbound', 'download' => 'downloads', 'form' => 'forms', 'media' => 'media', 'custom' => 'custom', 'commerce' => 'commerce' );
		foreach ( $types as $k ) {
			$m[ $k ] = 0;
		}
		$m['event_types'] = 0;
		foreach ( (array) $wpdb->get_results( "SELECT type, COUNT(*) AS n FROM {$e} GROUP BY type" ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			if ( isset( $types[ $r->type ] ) ) {
				$m[ $types[ $r->type ] ] = (int) $r->n;
			}
			if ( (int) $r->n > 0 ) {
				++$m['event_types'];
			}
		}

		$dev = array( 'mobile' => 'mobile_visitors', 'desktop' => 'desktop_visitors', 'tablet' => 'tablet_visitors' );
		foreach ( $dev as $k ) {
			$m[ $k ] = 0;
		}
		foreach ( (array) $wpdb->get_results( "SELECT device, COUNT(DISTINCT visitor_id) AS n FROM {$s} GROUP BY device" ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			if ( isset( $dev[ $r->device ] ) ) {
				$m[ $dev[ $r->device ] ] = (int) $r->n;
			}
		}
		$m['browsers'] = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT browser) FROM {$s} WHERE browser <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.

		// Days with sessions (site time): peak day and longest streak.
		$days = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(DATE_ADD(started_at, INTERVAL %d SECOND)) AS d, COUNT(*) AS n FROM {$s} GROUP BY d ORDER BY d", $off ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$peak = 0;
		$run  = 0;
		$best = 0;
		$prev = null;
		foreach ( (array) $days as $d ) {
			$peak = max( $peak, (int) $d['n'] );
			$ts   = strtotime( $d['d'] . ' UTC' );
			$run  = ( null !== $prev && 86400 === $ts - $prev ) ? $run + 1 : 1;
			$best = max( $best, $run );
			$prev = $ts;
		}
		$m['peak_day'] = $peak;
		$m['streak']   = $best;

		$m['heat_clicks']   = (int) $wpdb->get_var( 'SELECT COALESCE(SUM(clicks),0) FROM ' . Tables::heat() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		$m['tracking_ok']   = ( Settings::get( 'enabled' ) && $m['pageviews'] > 0 ) ? 1 : 0;
		$m['consent_on']    = 'none' !== Settings::get( 'consent_mode' ) ? 1 : 0;
		$m['ip_private']    = 'full' !== Settings::get( 'ip_mode' ) ? 1 : 0;
		$m['retention_on']  = (int) Settings::get( 'retention_days' ) > 0 ? 1 : 0;
		$m['exports']       = get_option( 'userflo_export_jobs' ) ? 1 : 0;
		$ints               = get_option( 'userflo_integrations' );
		$m['integrations']  = is_array( $ints ) && $ints ? 1 : 0;
		return $m;
	}

	/** @return array{summary:array,items:array} One box per achievement family (its tier label climbs copper to olympian) plus single badges. */
	public static function evaluate( array $m ) {
		$items    = array();
		$unlocked = 0;
		$total    = 0;
		$disc     = 0;
		$secret   = 0;
		$high     = -1;
		foreach ( self::families() as $f ) {
			$value   = isset( $m[ $f[1] ] ) ? (int) $m[ $f[1] ] : 0;
			$tiers   = array();
			$reached = -1;
			foreach ( self::TIERS as $i => $tier ) {
				$done    = $value >= $f[4][ $i ];
				$tiers[] = array( 'tier' => $tier, 'name' => self::t_dyn( $f[2][ $i ] ), 'target' => $f[4][ $i ], 'reached' => $done );
				if ( $done ) {
					$reached = $i;
				}
				++$total;
			}
			$unlocked += $reached + 1;
			$high      = max( $high, $reached );
			$shown     = $reached >= 0 ? $reached : 0; // The box shows the highest earned tier, or the first while nothing is earned.
			$next      = $reached < 4 ? $reached + 1 : 4;
			$prev      = $reached >= 0 ? $f[4][ $reached ] : 0;
			$span      = max( 1, $f[4][ $next ] - ( $reached < 4 ? $prev : $f[4][3] ) );
			$progress  = 4 === $reached ? 1 : min( 1, max( 0, ( $value - $prev ) / $span ) );
			if ( $reached < 4 && $value > 0 && $reached < 0 ) {
				++$disc;
			}
			$items[] = array(
				'id'       => $f[1],
				'kind'     => 'family',
				'name'     => $tiers[ $shown ]['name'],
				'category' => $f[0],
				'desc'     => sprintf( self::t_dyn( $f[3] ), number_format_i18n( $f[4][ $next ] ) ),
				'tier'     => self::TIERS[ $reached >= 0 ? $reached : 0 ],
				'next'     => $reached < 4 ? self::TIERS[ $next ] : '',
				'status'   => $reached >= 0 ? 'unlocked' : ( $value > 0 ? 'discovered' : 'locked' ),
				'value'    => $value,
				'target'   => $f[4][ $next ],
				'progress' => round( $progress, 3 ),
				'tiers'    => $tiers,
			);
		}
		foreach ( self::singles() as $s ) {
			$value = isset( $m[ $s[4] ] ) ? (int) $m[ $s[4] ] : 0;
			$done  = $value >= $s[5];
			++$total;
			if ( $s[6] && ! $done && $value <= 0 ) {
				++$secret;
				$items[] = array( 'id' => $s[1], 'kind' => 'single', 'name' => '???', 'category' => $s[0], 'desc' => __( 'Secret achievement: hidden until your data shows the first matching signal.', 'userflo' ), 'tier' => 'gold', 'next' => '', 'status' => 'secret', 'value' => 0, 'target' => 0, 'progress' => 0, 'tiers' => array() );
				continue;
			}
			if ( $done ) {
				++$unlocked;
			} elseif ( $value > 0 ) {
				++$disc;
			}
			$items[] = array( 'id' => $s[1], 'kind' => 'single', 'name' => self::t_dyn( $s[2] ), 'category' => $s[0], 'desc' => self::t_dyn( $s[3] ), 'tier' => $s[6] ? 'gold' : 'copper', 'next' => '', 'status' => $done ? 'unlocked' : ( $value > 0 ? 'discovered' : 'locked' ), 'value' => $value, 'target' => $s[5], 'progress' => round( $s[5] > 0 ? min( 1, $value / $s[5] ) : 0, 3 ), 'tiers' => array(), 'metric' => $s[4] );
		}
		return array(
			'summary' => array( 'total' => $total, 'unlocked' => $unlocked, 'boxes' => count( $items ), 'discovered' => $disc, 'secret' => $secret, 'highest_tier' => $high >= 0 ? self::TIERS[ $high ] : '' ),
			'items'   => $items,
		);
	}

	/** Translates a string that was already marked by t() (the runtime lookup is the same). */
	private static function t_dyn( $text ) {
		return $text; // families()/singles() already return translated strings.
	}

	/** Metric key for an item id (single badges map to their metric). */
	public static function metric_for( $id ) {
		foreach ( self::families() as $f ) {
			if ( $f[1] === $id ) {
				return $id;
			}
		}
		foreach ( self::singles() as $s ) {
			if ( $s[1] === $id ) {
				return $s[4];
			}
		}
		return null;
	}

	/** @return array|null Tier definitions (tier, name, target) for a metric family. */
	public static function tiers_for( $metric ) {
		foreach ( self::families() as $f ) {
			if ( $f[1] === $metric ) {
				$out = array();
				foreach ( self::TIERS as $i => $tier ) {
					$out[] = array( 'tier' => $tier, 'name' => $f[2][ $i ], 'target' => $f[4][ $i ] );
				}
				return $out;
			}
		}
		return null;
	}
}
