<?php
namespace UserFlo\Insights;

use UserFlo\Database\Tables;
use UserFlo\Rest\Presenter;

defined( 'ABSPATH' ) || exit;

/** History behind an achievement: when each tier was reached (and by whom), a cumulative chart, or the record holder. */
final class AchievementDetail {

	/** metric => [table, where, what column, who columns]. */
	private static function specs() {
		$e = array( 'table' => 'events', 'time' => 'occurred_at', 'what' => "COALESCE(NULLIF(name,''), target)", 'visitor' => 'visitor_id', 'session' => 'session_id' );
		return array(
			'pageviews'          => array( 'table' => 'pageviews', 'time' => 'viewed_at', 'what' => 'url', 'visitor' => 'visitor_id', 'session' => 'session_id' ),
			'deep_reads'         => array( 'table' => 'pageviews', 'time' => 'viewed_at', 'where' => 'max_scroll >= 100', 'what' => 'url', 'visitor' => 'visitor_id', 'session' => 'session_id' ),
			'visitors'           => array( 'table' => 'visitors', 'time' => 'first_seen', 'what' => 'first_landing', 'visitor' => 'id', 'session' => '0' ),
			'logged_in_visitors' => array( 'table' => 'visitors', 'time' => 'first_seen', 'where' => 'user_id IS NOT NULL', 'what' => 'first_landing', 'visitor' => 'id', 'session' => '0' ),
			'sessions'           => array( 'table' => 'sessions', 'time' => 'started_at', 'what' => 'entry_url', 'visitor' => 'visitor_id', 'session' => 'id' ),
			'clicks'             => $e + array( 'where' => "type = 'click'" ),
			'outbound'           => $e + array( 'where' => "type = 'outbound'" ),
			'downloads'          => $e + array( 'where' => "type = 'download'" ),
			'forms'              => $e + array( 'where' => "type = 'form'" ),
			'media'              => $e + array( 'where' => "type = 'media'" ),
			'custom'             => $e + array( 'where' => "type = 'custom'" ),
			'commerce'           => $e + array( 'where' => "type = 'commerce'" ),
		);
	}

	private static function who( $visitor_id, $session_id ) {
		global $wpdb;
		if ( ! $visitor_id ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT v.id, v.visitor_uid, v.user_id, u.user_login, u.display_name FROM ' . Tables::visitors() . ' v LEFT JOIN ' . $wpdb->users . ' u ON u.ID = v.user_id WHERE v.id = %d', $visitor_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		if ( ! $row ) {
			return null; // Visitor was deleted (retention or erasure).
		}
		return array( 'visitor_id' => (int) $row->id, 'session_id' => (int) $session_id, 'label' => Presenter::visitor_label( $row ), 'logged_in' => ! empty( $row->user_id ) );
	}

	/** @return array|null */
	public static function build( $metric, $title ) {
		global $wpdb;
		$specs = self::specs();
		$out   = array( 'id' => $metric, 'title' => $title, 'history' => false, 'milestones' => array(), 'series' => array(), 'record' => null, 'first_at' => null );
		$tiers = Achievements::tiers_for( $metric );

		if ( isset( $specs[ $metric ] ) ) {
			$sp    = $specs[ $metric ];
			$tbl   = Tables::{$sp['table']}();
			$where = isset( $sp['where'] ) ? $sp['where'] : '1=1';
			$out['history'] = true;
			$rows = $wpdb->get_results( "SELECT DATE({$sp['time']}) AS d, COUNT(*) AS n FROM {$tbl} WHERE {$where} GROUP BY d ORDER BY d" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$sum  = 0;
			foreach ( (array) $rows as $r ) {
				$sum              += (int) $r->n;
				$out['series'][]   = array( 'd' => $r->d, 'total' => $sum );
			}
			$out['total'] = $sum;
			if ( $rows ) {
				$out['first_at'] = $rows[0]->d;
			}
			foreach ( (array) $tiers as $t ) {
				$ms = array( 'tier' => $t['tier'], 'name' => $t['name'], 'target' => $t['target'], 'reached_at' => null, 'who' => null, 'what' => '' );
				if ( $sum >= $t['target'] ) {
					$r = $wpdb->get_row( $wpdb->prepare( "SELECT {$sp['time']} AS at, {$sp['visitor']} AS vid, {$sp['session']} AS sid, {$sp['what']} AS what FROM {$tbl} WHERE {$where} ORDER BY id LIMIT 1 OFFSET %d", $t['target'] - 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
					if ( $r ) {
						$ms['reached_at'] = Presenter::iso( $r->at );
						$ms['who']        = self::who( (int) $r->vid, (int) $r->sid );
						$ms['what']       = (string) $r->what;
					}
				}
				$out['milestones'][] = $ms;
			}
			return $out;
		}

		if ( 'unique_pages' === $metric ) {
			$pv   = Tables::pageviews();
			$rows = $wpdb->get_results( "SELECT url, MIN(viewed_at) AS m FROM {$pv} GROUP BY url ORDER BY m LIMIT 5000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$out['history'] = true;
			$days = array();
			foreach ( (array) $rows as $i => $r ) {
				$days[ substr( $r->m, 0, 10 ) ] = $i + 1;
			}
			foreach ( $days as $d => $n ) {
				$out['series'][] = array( 'd' => $d, 'total' => $n );
			}
			$out['total'] = count( (array) $rows );
			foreach ( (array) $tiers as $t ) {
				$r                   = isset( $rows[ $t['target'] - 1 ] ) ? $rows[ $t['target'] - 1 ] : null;
				$out['milestones'][] = array( 'tier' => $t['tier'], 'name' => $t['name'], 'target' => $t['target'], 'reached_at' => $r ? Presenter::iso( $r->m ) : null, 'who' => null, 'what' => $r ? $r->url : '' );
			}
			return $out;
		}

		$s = Tables::sessions();
		$v = Tables::visitors();
		if ( 'max_pages_session' === $metric || 'long_session_min' === $metric ) {
			$order = 'max_pages_session' === $metric ? 'pageview_count DESC' : 'TIMESTAMPDIFF(MINUTE, started_at, last_activity_at) DESC';
			$r     = $wpdb->get_row( "SELECT id, visitor_id, started_at, pageview_count, TIMESTAMPDIFF(MINUTE, started_at, last_activity_at) AS mins FROM {$s} ORDER BY {$order} LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			if ( $r ) {
				$out['record'] = array( 'at' => Presenter::iso( $r->started_at ), 'value' => 'max_pages_session' === $metric ? (int) $r->pageview_count : (int) $r->mins, 'who' => self::who( (int) $r->visitor_id, (int) $r->id ) );
			}
			return $out;
		}
		if ( 'max_visitor_pages' === $metric ) {
			$r = $wpdb->get_row( "SELECT id, last_seen, pageview_count FROM {$v} ORDER BY pageview_count DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			if ( $r ) {
				$out['record'] = array( 'at' => Presenter::iso( $r->last_seen ), 'value' => (int) $r->pageview_count, 'who' => self::who( (int) $r->id, 0 ) );
			}
			return $out;
		}
		if ( 'peak_day' === $metric || 'streak' === $metric ) {
			$rows = $wpdb->get_results( "SELECT DATE(started_at) AS d, COUNT(*) AS n FROM {$s} GROUP BY d ORDER BY d" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			$out['history'] = true;
			$best = null;
			foreach ( (array) $rows as $r ) {
				$out['series'][] = array( 'd' => $r->d, 'total' => (int) $r->n ); // Per-day sessions (not cumulative) for these records.
				if ( null === $best || (int) $r->n > $best['value'] ) {
					$best = array( 'at' => Presenter::iso( $r->d . ' 00:00:00' ), 'value' => (int) $r->n, 'who' => null );
				}
			}
			$out['daily'] = true;
			if ( 'peak_day' === $metric ) {
				$out['record'] = $best;
			}
			return $out;
		}
		return $out; // No per-event history for this metric.
	}
}
