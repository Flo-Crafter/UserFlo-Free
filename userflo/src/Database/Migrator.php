<?php
namespace UserFlo\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Versioned schema migrations. Runs only on activation or when the stored
 * version is lower than the code version, never on every request.
 */
final class Migrator {

	const OPTION = 'userflo_db_version';

	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPTION, 0 ) < USERFLO_DB_VERSION ) {
			self::run();
		}
	}

	public static function run() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$installed = (int) get_option( self::OPTION, 0 );

		// Migration 1: initial schema. Migration 2: events.component (dbDelta adds the column, keeps data).
		// Future migrations are added as `if ( $installed < N )` blocks; all are idempotent.
		// Migration 3: reporting indexes (sessions.started_ref, pageviews.viewed_url); building them can take a few seconds per million rows.
		// Migration 4: heatmap aggregate table (uf_heat). Migration 5: geolocation columns on visitors/sessions.
		// Migration 6: visitors.identified_at (when a browser was first linked to an account).
		// Migration 7: events.type_name_visitor (covering index for the visitor filters by event type and name) and events.pageview_id (most clicked elements of a page, heatmap screen).
		// Migration 8: campaign columns on sessions (utm_source/medium/campaign/term/content) and the started_campaign index. Existing rows are left
		// as they are; a small background job (Insights\UtmBackfill) reads the landing pages already recorded and fills them in, in batches.
		// Migration 9: goals and goal_hits tables (new tables only; nothing existing is touched).
		// Migration 10: the WooCommerce order mirror (uf_orders), a new table only.
		// Migration 11: the exclusion rules table (uf_exclusions), a new table only.
		// Migration 12: the website audit tables (uf_audit_pages, _elements, _locations, _findings), new tables only.
		// Migration 13: the Alerts tables (uf_alert_rules, _state, _deliveries), new tables only. Lite creates them empty and never writes to them.
		// Migration 14: the Statistics Builder table (uf_dashboards), a new table only. Lite creates it empty and never writes to it.
		// Migration 15: the bot counters table (uf_bots), a new table only.
		// Migration 16: goal options (amount per session, time frame, schedule and threshold): columns added to uf_goals, existing goals keep working unchanged.
		// Migration 17: the long-term daily summaries table (uf_summary), a new table only; the first cleanup run fills it from the data that exists.
		// Migration 18: uf_audit_pages.metrics (counts kept with each scan). Migration 19: the AI access call log (uf_ai_log), a new table only.
		// Migration 20: uf_summary.amount (goal value and sales revenue per day), added with the goal, order, event and custom rows the daily summary now keeps.
		// The column is additive; existing rows read 0 and every report that existed before ignores it.
		// Migration 20 also drops two indexes that a longer index already provides (sessions.started_at is the first column of started_ref,
		// started_country and started_campaign; pageviews.viewed_at is the first column of viewed_url). Every query that used them still has the
		// longer index; measured on 1 M page views the saving is about 30 MB and roughly a sixth less time per bulk insert, with range counts
		// within a few tens of milliseconds. Dropping an index is metadata work, no rows are rewritten. To undo: ADD INDEX started_at (started_at)
		// and ADD INDEX viewed_at (viewed_at).
		// dbDelta only adds what is missing, so every upgrade simply re-reads the schema.
		if ( $installed < USERFLO_DB_VERSION ) {
			foreach ( Schema::sql() as $sql ) {
				dbDelta( $sql );
			}
		}

		if ( self::tables_exist() ) {
			if ( $installed > 0 && $installed < 20 ) {
				self::drop_redundant_indexes();
			}
			update_option( self::OPTION, USERFLO_DB_VERSION, true );
			if ( $installed > 0 && $installed < 17 && ! wp_next_scheduled( \UserFlo\Insights\Summary::HOOK ) ) {
				wp_schedule_single_event( time() + MINUTE_IN_SECONDS, \UserFlo\Insights\Summary::HOOK ); // Summarise the days already recorded.
			}
			if ( $installed >= 17 && $installed < 20 ) {
				\UserFlo\Insights\Summary::start_upgrade(); // Days summarised before 20 lack the goal, sales and interaction rows: write them again while their detail exists.
			}
			if ( $installed > 0 && $installed < 8 ) {
				\UserFlo\Insights\UtmBackfill::start(); // An upgrade, not a fresh install: there may be history to read.
			}
			return true;
		}
		return false;
	}

	/**
	 * Drops an index only when the longer index that makes it redundant is there. A failure (no ALTER right, a locked table) is ignored:
	 * the index is only extra weight, and the next upgrade run is not needed because nothing depends on it being gone.
	 */
	private static function drop_redundant_indexes() {
		global $wpdb;
		$pairs = array(
			array( Tables::sessions(), 'started_at', 'started_ref' ),
			array( Tables::pageviews(), 'viewed_at', 'viewed_url' ),
		);
		$prev = $wpdb->suppress_errors( true );
		foreach ( $pairs as $p ) {
			$have = $wpdb->get_col( $wpdb->prepare( "SHOW INDEX FROM {$p[0]} WHERE Key_name IN (%s, %s)", $p[1], $p[2] ), 2 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
			if ( is_array( $have ) && in_array( $p[1], $have, true ) && in_array( $p[2], $have, true ) ) {
				$wpdb->query( "ALTER TABLE {$p[0]} DROP INDEX {$p[1]}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Own tables: names are internal constants, values are prepared.
			}
		}
		$wpdb->suppress_errors( $prev );
	}

	public static function tables_exist() {
		global $wpdb;
		foreach ( Tables::owned() as $table ) {
			if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
				return false;
			}
		}
		return true;
	}
}
