<?php
namespace UserFlo\Database;

defined( 'ABSPATH' ) || exit;

/** Table name registry. */
final class Tables {

	public static function visitors() {
		return self::name( 'uf_visitors' );
	}
	public static function sessions() {
		return self::name( 'uf_sessions' );
	}
	public static function pageviews() {
		return self::name( 'uf_pageviews' );
	}
	public static function events() {
		return self::name( 'uf_events' );
	}

	/** Goal definitions. Configuration, not analytics data: "Delete all analytics data" keeps them. */
	public static function goals() {
		return self::name( 'uf_goals' );
	}

	/** One row per goal and session that completed it. Analytics data (purged, retained and erased with the sessions). */
	public static function goal_hits() {
		return self::name( 'uf_goal_hits' );
	}

	/** Exclusion rules (stop tracking / leave out of statistics / suppress audit findings). Configuration, not analytics data: "Delete all analytics data" keeps them. */
	public static function exclusions() {
		return self::name( 'uf_exclusions' );
	}

	/** Website audit: scanned pages, the elements found on them, where each appears, and findings. Configuration-like inventory: kept by "Delete all analytics data". */
	public static function audit_pages() {
		return self::name( 'uf_audit_pages' );
	}
	public static function audit_elements() {
		return self::name( 'uf_audit_elements' );
	}
	public static function audit_locations() {
		return self::name( 'uf_audit_locations' );
	}
	/** Alerts (Pro): rules, per-rule counters and the delivery log. Configuration and log, not analytics: kept by "Delete all analytics data". */
	/** Calls made by an AI assistant through UserFlo Pro's AI access (when, which account, what was asked for, how much was returned). */
	public static function ai_log() {
		return self::name( 'uf_ai_log' );
	}
	public static function alert_rules() {
		return self::name( 'uf_alert_rules' );
	}
	public static function alert_state() {
		return self::name( 'uf_alert_state' );
	}
	public static function alert_deliveries() {
		return self::name( 'uf_alert_deliveries' );
	}
	/** Statistics Builder (Pro): named dashboards with their layout and widget settings. Configuration, not analytics: kept by "Delete all analytics data". */
	public static function dashboards() {
		return self::name( 'uf_dashboards' );
	}
	public static function audit_findings() {
		return self::name( 'uf_audit_findings' );
	}

	/** A minimal mirror of WooCommerce orders (numbers, status, currency, time and the session that placed it; no personal data). */
	public static function orders() {
		return self::name( 'uf_orders' );
	}

	/** Daily counters of requests by AI crawlers, search engines and other bots: no address, no full user agent, no visitor. */
	public static function bots() {
		return self::name( 'uf_bots' );
	}

	/** Long-term daily summaries (counts only, no visitor identifiers): kept for years so a period can be compared with the same period last year. */
	public static function summary() {
		return self::name( 'uf_summary' );
	}

	public static function heat() {
		return self::name( 'uf_heat' );
	}

	/** Whether any heatmap counters are stored (they may outlive Pro, so their retention setting must stay reachable). */
	public static function has_heat_data() {
		global $wpdb;
		$t = self::heat();
		return (bool) $wpdb->get_var( "SELECT 1 FROM {$t} LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	/** The analytics data tables (what purge empties and Tools lists). */
	public static function all() {
		return array( self::visitors(), self::sessions(), self::pageviews(), self::events(), self::heat(), self::bots(), self::summary(), self::goal_hits(), self::orders() );
	}

	/** Every table the plugin owns, including configuration. */
	public static function owned() {
		return array_merge( self::all(), array( self::goals(), self::exclusions(), self::audit_pages(), self::audit_elements(), self::audit_locations(), self::audit_findings(), self::alert_rules(), self::alert_state(), self::alert_deliveries(), self::dashboards(), self::ai_log() ) );
	}

	private static function name( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . $suffix;
	}
}
