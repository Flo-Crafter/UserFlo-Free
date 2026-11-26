<?php
namespace UserFlo\Tracking;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Writes one validated tracker payload to the database.
 * No external calls, no reporting queries; a handful of indexed single-row statements.
 */
final class Ingest {

	/** Cheap indexed existence check used by flood protection. */
	public static function visitor_exists( $uid ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . Tables::visitors() . ' WHERE visitor_uid = %s', $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	/**
	 * @param array $p   Output of Validator::normalize().
	 * @param array $ctx device, browser, os, ip (already policy-processed), user_id (int), link_user (bool).
	 * @return array|false Summary ids/counters, or false when the payload was rejected.
	 */
	public static function process( array $p, array $ctx ) {
		global $wpdb;
		$now = Clock::now();
		// The page started `dur` seconds before this message; used for page view / session start times.
		$started = Clock::mysql( Clock::time() - (int) $p['dur'] );
		$vt  = Tables::visitors();
		$st  = Tables::sessions();
		$pt  = Tables::pageviews();
		$et  = Tables::events();

		// 1. Visitor (upsert).
		$ref = (string) $p['ref'];
		$res = $wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
				"INSERT INTO {$vt} (visitor_uid, first_seen, last_seen, first_referrer, first_landing, device, browser, os, ip)
				VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)
				ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), last_seen = GREATEST(last_seen, VALUES(last_seen))", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$p['vid'],
				$started,
				$now,
				$ref,
				$p['url'],
				$ctx['device'],
				$ctx['browser'],
				$ctx['os'],
				$ctx['ip']
			)
		);
		if ( false === $res ) {
			return false;
		}
		$visitor_id = (int) $wpdb->insert_id;
		$new_visitor = ( 1 === (int) $res );

		// 1b. A tab that stayed open longer than the inactivity time and then sends again (the browser keeps its session id in memory) is a
		// new visit, not the old session stretched over hours or days. The follow-on session and page view ids are derived from the old ones,
		// so every later message of that page lands in the same new session, and a retry never makes a second one.
		$ttl = max( 5, (int) \UserFlo\Settings\Settings::get( 'inactivity_minutes' ) ) * 60;
		$uid = $p['sid'];
		for ( $hop = 0; $hop < 25; $hop++ ) {
			$old = $wpdb->get_row( $wpdb->prepare( "SELECT id, visitor_id, last_activity_at FROM {$st} WHERE session_uid = %s", $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			if ( ! $old || (int) $old->visitor_id !== $visitor_id || Clock::time() - strtotime( $old->last_activity_at . ' UTC' ) <= $ttl ) {
				break;
			}
			$uid = md5( $p['sid'] . '|' . $old->id );
		}
		if ( $hop > 0 ) {
			$p['pid'] = substr( md5( $p['pid'] . '|' . $uid ), 0, 16 );
			$p['sid'] = $uid;
			$started  = $now; // The new visit starts now, not when the page was first opened.
		}

		// 2. Session (must belong to this visitor).
		$session = $wpdb->get_row( $wpdb->prepare( "SELECT id, visitor_id FROM {$st} WHERE session_uid = %s", $p['sid'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$new_session = false;
		if ( $session ) {
			if ( (int) $session->visitor_id !== $visitor_id ) {
				return false; // Session id presented by a different visitor: ignore.
			}
			$session_id = (int) $session->id;
		} else {
			// Geolocation runs only when a session is created (one lookup per session, never per beacon); the IP is not kept.
			$geo = isset( $ctx['geo'] ) && is_callable( $ctx['geo'] ) ? call_user_func( $ctx['geo'] ) : null;
			// The campaign is read from the page that starts the session; later pages of the session never change it.
			$utm = \UserFlo\Insights\Utm::parse( $p['url'] );
			$ok = $wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
					"INSERT IGNORE INTO {$st} (session_uid, visitor_id, started_at, last_activity_at, entry_url, exit_url, referrer, device, browser, os, screen_w, screen_h, ip, country, city, lat, lon, utm_source, utm_medium, utm_campaign, utm_term, utm_content)
					VALUES (%s,%d,%s,%s,%s,%s,%s,%s,%s,%s,%d,%d,%s,NULLIF(%s,''),%s,NULLIF(%s,''),NULLIF(%s,''),%s,%s,%s,%s,%s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$p['sid'],
					$visitor_id,
					$started,
					$now,
					$p['url'],
					$p['url'],
					$ref,
					$ctx['device'],
					$ctx['browser'],
					$ctx['os'],
					$p['sw'],
					$p['sh'],
					$ctx['ip'],
					$geo ? $geo['country'] : null,
					$geo ? $geo['city'] : '',
					$geo && null !== $geo['lat'] ? $geo['lat'] : null,
					$geo && null !== $geo['lon'] ? $geo['lon'] : null,
					$utm['utm_source'],
					$utm['utm_medium'],
					$utm['utm_campaign'],
					$utm['utm_term'],
					$utm['utm_content']
				)
			);
			if ( false === $ok ) {
				return false;
			}
			if ( 1 === (int) $ok ) {
				$session_id  = (int) $wpdb->insert_id;
				$new_session = true;
				if ( $geo ) {
					$wpdb->query( $wpdb->prepare( "UPDATE {$vt} SET country = %s, city = %s WHERE id = %d", $geo['country'], $geo['city'], $visitor_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
				}
			} else { // Lost a race with a concurrent request.
				$session_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$st} WHERE session_uid = %s AND visitor_id = %d", $p['sid'], $visitor_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
				if ( ! $session_id ) {
					return false;
				}
			}
		}

		// 3. Page view (idempotent on the client-generated id).
		$new_pv = $wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
				"INSERT IGNORE INTO {$pt} (pv_uid, session_id, visitor_id, viewed_at, url, title, referrer, engaged_seconds, duration_seconds, max_scroll)
				VALUES (%s,%d,%d,%s,%s,%s,%s,%d,%d,%d)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$p['pid'],
				$session_id,
				$visitor_id,
				$started,
				$p['url'],
				$p['title'],
				$ref,
				$p['eng'],
				$p['dur'],
				$p['sc']
			)
		);
		if ( 1 === (int) $new_pv ) {
			$pageview_id = (int) $wpdb->insert_id;
			$new_pv      = 1;
		} else {
			$new_pv      = 0;
			$pageview_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pt} WHERE pv_uid = %s AND session_id = %d", $p['pid'], $session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			if ( ! $pageview_id ) {
				return false;
			}
			if ( $p['eng'] || $p['dur'] || $p['sc'] ) {
				$wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
					$wpdb->prepare(
						"UPDATE {$pt} SET engaged_seconds = engaged_seconds + %d, duration_seconds = GREATEST(duration_seconds, %d), max_scroll = GREATEST(max_scroll, %d) WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$p['eng'],
						$p['dur'],
						$p['sc'],
						$pageview_id
					)
				);
			}
		}

		// 4. Events (multi-row insert, de-duplicated by unique key). Interactions an exclusion rule says not to track are dropped here,
		// before they are stored or counted, so they also never reach goals or the session's event counter.
		$p['events'] = \UserFlo\Exclusions\Service::filter_events( $p['events'], $p['url'] );
		$new_events = 0;
		if ( $p['events'] ) {
			$values = array();
			$now_ts = Clock::time();
			foreach ( $p['events'] as $ev ) {
				if ( 'custom' === $ev['type'] ) {
					// Custom events carry a name. Properties (the value) are stored only when an add-on (UserFlo Pro) validates them against
					// its allow-list, whatever the browser sent, so a hand-made request cannot store anything the allow-list does not permit.
					$ev['value'] = (string) apply_filters( 'userflo_custom_event_properties', '', $ev['value'], $ev['name'] );
				}
				$at       = Clock::mysql( max( $now_ts - 86400, $now_ts - (int) floor( $ev['age'] / 1000 ) ) );
				$values[] = $wpdb->prepare(
					'(%s,%d,%d,%d,%s,%s,%s,%s,%s,%s)',
					md5( $p['pid'] . ':' . $ev['i'] ),
					$pageview_id,
					$session_id,
					$visitor_id,
					$at,
					$ev['type'],
					$ev['name'],
					$ev['target'],
					$ev['value'],
					$ev['comp']
				);
			}
			$n = $wpdb->query( "INSERT IGNORE INTO {$et} (dedupe_key, pageview_id, session_id, visitor_id, occurred_at, type, name, target, value, component) VALUES " . implode( ',', $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
			$new_events = (int) $n;
		}

		// 4b. Goals: compare what this payload stored with the active goals. Idempotent per (goal, session), so a retried beacon counts once.
		if ( $new_pv || $new_events ) {
			\UserFlo\Goals\Evaluator::run( $session_id, $visitor_id, $new_pv ? $p['url'] : null, $new_events ? $p['events'] : array() );
		}

		// 5. Counters and activity timestamps (never move backwards).
		$wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
				"UPDATE {$st} SET last_activity_at = GREATEST(last_activity_at, %s), exit_url = %s,
				pageview_count = pageview_count + %d, event_count = event_count + %d, engaged_seconds = engaged_seconds + %d WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now,
				$p['url'],
				$new_pv,
				$new_events,
				$p['eng'],
				$session_id
			)
		);
		// A visit that began with a login and now arrives without a signed-in user has ended its login: the site's own inactivity logout, an
		// expired cookie or a logout in another tab. Recorded once, so the session shows as logged out instead of staying active.
		if ( ! $new_session && empty( $ctx['user_id'] ) ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$st} SET logout_at = %s WHERE id = %d AND login_at IS NOT NULL AND user_id IS NOT NULL AND (logout_at IS NULL OR logout_at < login_at)", $now, $session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		}
		$wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$wpdb->prepare(
				"UPDATE {$vt} SET session_count = session_count + %d, pageview_count = pageview_count + %d, event_count = event_count + %d, engaged_seconds = engaged_seconds + %d WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$new_session ? 1 : 0,
				$new_pv,
				$new_events,
				$p['eng'],
				$visitor_id
			)
		);

		// 6. Identity association (only when enabled; never merges people).
		if ( $ctx['user_id'] > 0 && ! empty( $ctx['link_user'] ) ) {
			\UserFlo\Identity\Linker::link( $visitor_id, $session_id, $ctx['user_id'] );
		} elseif ( $new_session && ! empty( $ctx['link_user'] ) ) {
			\UserFlo\Identity\Linker::inherit( $session_id ); // Anonymous visit from a browser already tied to an account.
		}

		$summary = array(
			'visitor_id'  => $visitor_id,
			'session_id'  => $session_id,
			'pageview_id' => $pageview_id,
			'new_visitor' => $new_visitor,
			'new_session' => $new_session,
			'new_pv'      => (bool) $new_pv,
			'events'      => $new_events,
		);
		/**
		 * A payload was stored. Add-ons (UserFlo Pro Alerts) listen here; with none attached this is one empty call. Listeners must be cheap and
		 * must never send anything from this request (queue it): the collector has to answer at once.
		 *
		 * @param array  $summary Ids and counters of what this payload created.
		 * @param string $path    Page path of the payload.
		 * @param array  $events  The payload's events after exclusions (type, name, target, value, comp, i) and the page-view uid under 'pid'.
		 * @param string $pid     The page view uid (with an event's index it identifies the event for de-duplication).
		 */
		do_action( 'userflo_ingested', $summary, $p['url'], $p['events'], $p['pid'] );
		return $summary;
	}
}
