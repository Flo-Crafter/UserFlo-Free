<?php
namespace UserFlo\Goals;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Called by ingestion after a payload was stored. It compares the page view and events of that payload with the active goals (a small
 * array kept in one autoloaded option, so no query is needed to know there is nothing to do) and records "this session completed this
 * goal" with INSERT IGNORE on the primary key (goal, session). That makes it safe for retried or duplicated beacons: a session completes a
 * goal at most once, whatever the number of matching pages or events.
 */
final class Evaluator {

	/**
	 * @param int         $session_id Session row id.
	 * @param int         $visitor_id Visitor row id.
	 * @param string|null $path       Path of the page view stored by this payload, null when the page view was already known.
	 * @param array       $events     Events of the payload (type, name, value).
	 * @return int Number of new completions.
	 */
	public static function run( $session_id, $visitor_id, $path, array $events ) {
		if ( ! \UserFlo\Modules::on( 'goals' ) ) {
			return 0;
		}
		$goals = Store::active();
		if ( ! $goals ) {
			return 0;
		}
		global $wpdb;
		$t     = Tables::goal_hits();
		$new   = 0;
		foreach ( $goals as $g ) {
			/** An add-on can decide that a goal does not apply right now (for example outside a time frame). */
			if ( ! apply_filters( 'userflo_goal_applies', true, $g ) ) {
				continue;
			}
			$hit = false;
			if ( 'page' === $g['type'] ) {
				$hit = null !== $path && Matcher::page( $g, $path );
			} else {
				foreach ( $events as $e ) {
					if ( Matcher::event( $g, $e ) ) {
						$hit = true;
						break;
					}
				}
			}
			if ( ! $hit ) {
				continue;
			}
			if ( apply_filters( 'userflo_goal_check_count', (int) $g['min_count'] > 1, $g ) && ! self::reached( $g, (int) $session_id ) ) {
				continue; // Matched, but not often enough in this session yet.
			}
			$done = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t} (goal_id, session_id, visitor_id, hit_at) VALUES (%d,%d,%d,%s)", (int) $g['id'], (int) $session_id, (int) $visitor_id, Clock::now() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			if ( 1 === $done ) {
				$new++;
				/**
				 * A session completed a goal for the first time. Identifiers are internal row ids; no personal data is passed.
				 *
				 * @param array $goal    id, name, type, value.
				 * @param array $session session_id, visitor_id, at (mysql UTC).
				 */
				do_action( 'userflo_goal_completed', array( 'id' => (int) $g['id'], 'name' => $g['name'], 'type' => $g['type'], 'value' => isset( $g['value'] ) ? (float) $g['value'] : 0.0 ), array( 'session_id' => (int) $session_id, 'visitor_id' => (int) $visitor_id, 'at' => Clock::now() ) );
			}
		}
		return $new;
	}

	/**
	 * Whether the session has matched the goal at least `min_count` times. Counted from the stored rows, so a retried beacon cannot count
	 * twice. An add-on can answer for goals with extra conditions (`userflo_goal_reached`, null leaves the decision here).
	 */
	public static function reached( array $g, $session_id ) {
		global $wpdb;
		$other = apply_filters( 'userflo_goal_reached', null, $g, (int) $session_id );
		if ( null !== $other ) {
			return (bool) $other;
		}
		$cond = Store::condition( $g, 'x' );
		if ( null === $cond ) {
			return false;
		}
		$tbl = 'page' === $g['type'] ? Tables::pageviews() : Tables::events();
		$n   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} x WHERE x.session_id = %d AND {$cond[0]}", array_merge( array( (int) $session_id ), $cond[1] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		return $n >= max( 1, (int) $g['min_count'] );
	}
}
