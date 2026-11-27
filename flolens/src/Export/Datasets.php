<?php
namespace UserFlo\Export;

use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/** Whitelisted exportable datasets: table, columns, time column and personal columns. */
final class Datasets {

	public static function all() {
		return array(
			'visitors'  => array( 'table' => Tables::visitors(), 'time' => 'last_seen', 'visitor_col' => 'id', 'personal' => array( 'user_id', 'ip' ), 'pseudo' => array( 'visitor_uid' ),
				'cols' => array( 'id', 'visitor_uid', 'user_id', 'first_seen', 'last_seen', 'session_count', 'pageview_count', 'event_count', 'engaged_seconds', 'first_referrer', 'first_landing', 'device', 'browser', 'os', 'country', 'ip' ) ),
			'sessions'  => array( 'table' => Tables::sessions(), 'time' => 'started_at', 'visitor_col' => 'visitor_id', 'personal' => array( 'user_id', 'ip' ), 'pseudo' => array( 'session_uid' ),
				'cols' => array( 'id', 'session_uid', 'visitor_id', 'user_id', 'started_at', 'last_activity_at', 'login_at', 'logout_at', 'pageview_count', 'event_count', 'engaged_seconds', 'entry_url', 'exit_url', 'referrer', 'device', 'browser', 'os', 'screen_w', 'screen_h', 'ip', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) ),
			'pageviews' => array( 'table' => Tables::pageviews(), 'time' => 'viewed_at', 'visitor_col' => 'visitor_id', 'personal' => array(), 'pseudo' => array(),
				'cols' => array( 'id', 'session_id', 'visitor_id', 'viewed_at', 'url', 'title', 'referrer', 'engaged_seconds', 'duration_seconds', 'max_scroll' ) ),
			'events'    => array( 'table' => Tables::events(), 'time' => 'occurred_at', 'visitor_col' => 'visitor_id', 'personal' => array(), 'pseudo' => array(), 'type_col' => 'type',
				'cols' => array( 'id', 'pageview_id', 'session_id', 'visitor_id', 'occurred_at', 'type', 'name', 'target', 'value', 'component' ) ),
		);
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/** Columns exported for a dataset under an anonymisation mode. */
	public static function columns( $key, $anonymise ) {
		$d = self::get( $key );
		if ( ! $d ) {
			return array();
		}
		return Filters::ANON_NONE === $anonymise ? $d['cols'] : array_values( array_diff( $d['cols'], $d['personal'] ) );
	}
}
