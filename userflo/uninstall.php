<?php
/**
 * Uninstall handler.
 *
 * - Analytics data (tables) is removed only if the administrator ticked "Delete all UserFlo data when the plugin is uninstalled".
 * - Credentials, cached third-party aggregates, export jobs and export files are ALWAYS removed: they are secrets or
 *   temporary files, not analytics data.
 * - On multisite every site is processed.
 *
 * @package UserFlo
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! function_exists( 'userflo_uninstall_site' ) ) {
	/**
	 * Clean up one site (the current blog).
	 */
	function userflo_uninstall_site() {
		global $wpdb;
		$settings = get_option( 'userflo_settings', array() );

		if ( ! empty( $settings['delete_on_uninstall'] ) ) {
			foreach ( array( 'uf_events', 'uf_pageviews', 'uf_sessions', 'uf_visitors', 'uf_heat', 'uf_bots', 'uf_goals', 'uf_exclusions', 'uf_audit_pages', 'uf_audit_elements', 'uf_audit_locations', 'uf_audit_findings', 'uf_alert_rules', 'uf_alert_state', 'uf_alert_deliveries', 'uf_dashboards', 'uf_ai_log', 'uf_goal_hits', 'uf_orders', 'uf_summary' ) as $suffix ) {
				$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}{$suffix}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own tables: names are internal constants, values are prepared.
			}
			delete_option( 'userflo_settings' );
			delete_option( 'userflo_db_version' );
			delete_option( 'userflo_goals_active' );
			delete_option( 'userflo_exclusions_active' );
			delete_option( 'userflo_audit_state' );
			delete_option( 'userflo_audit_lock' );
			delete_option( 'userflo_element_notes' );
		}

		// Secrets and temporary files go regardless of the data choice.
		delete_option( 'userflo_alert_channels' ); // Webhook addresses and signing secrets.
		delete_option( 'userflo_integrations' );
		delete_option( 'userflo_split_notice_dismissed' );
		delete_option( 'userflo_geo_db' );
		delete_option( 'userflo_setup' ); // The setup wizard asks again after a fresh install.
		delete_transient( 'userflo_setup_redirect' );
		$uf_up = wp_upload_dir();
		$uf_geo = trailingslashit( $uf_up['basedir'] ) . 'userflo-geo/';
		if ( is_dir( $uf_geo ) ) {
			foreach ( (array) glob( $uf_geo . '{,.}*', GLOB_BRACE ) as $uf_f ) {
				if ( is_file( $uf_f ) ) {
					wp_delete_file( $uf_f );
				}
			}
			rmdir( $uf_geo ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
		delete_option( 'userflo_shares' ); // Share links always die with the plugin: they would otherwise outlive the data they expose.
		delete_option( 'userflo_integration_cache' );
		foreach ( array( 'userflo_mail', 'userflo_mail_state', 'userflo_mail_log', 'userflo_mail_lock' ) as $uf_opt ) { // SMTP credentials and delivery history are not analytics data: always removed.
			delete_option( $uf_opt );
		}
		delete_option( 'userflo_export_jobs' );
		delete_transient( 'userflo_tok_ga4' );
		$uploads = wp_upload_dir( null, false );
		if ( empty( $uploads['error'] ) && is_dir( $uploads['basedir'] . '/userflo-exports' ) ) {
			foreach ( (array) glob( $uploads['basedir'] . '/userflo-exports/{,.}*', GLOB_BRACE ) as $file ) {
				if ( is_file( $file ) ) {
					@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
				}
			}
			@rmdir( $uploads['basedir'] . '/userflo-exports' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		}
		foreach ( array( 'userflo_cleanup', 'userflo_integrations_sync', 'userflo_export_cleanup', 'userflo_geo_update', 'userflo_mail_tick', 'userflo_mail_retry' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $userflo_blog_id ) {
		switch_to_blog( $userflo_blog_id );
		userflo_uninstall_site();
		restore_current_blog();
	}
} else {
	userflo_uninstall_site();
}
