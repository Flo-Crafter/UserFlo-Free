<?php
namespace UserFlo\Admin;

use UserFlo\Database\Migrator;
use UserFlo\Database\Reports;
use UserFlo\Database\Tables;
use UserFlo\Export\Datasets;
use UserFlo\Export\Jobs;
use UserFlo\Plugin;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/** Tools: exports (background jobs), PDF reports, data management and status. UI behaviour lives in assets/admin/tools.js. */
final class ToolsPage {

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'userflo-tools' ) ) {
			return;
		}
		if ( self::tab() === 'storage' ) {
			StoragePage::assets();
			return;
		}
		wp_enqueue_script( 'userflo-tools', USERFLO_URL . 'assets/admin/tools.js', array( 'wp-api-fetch', 'wp-i18n' ), USERFLO_VERSION . '-' . filemtime( USERFLO_DIR . 'assets/admin/tools.js' ), true );
		\UserFlo\I18n::script( 'userflo-tools' );
		wp_add_inline_script(
			'userflo-tools',
			'window.userfloTools=' . wp_json_encode( array(
				'download' => admin_url( 'admin-post.php' ),
				'nonce'    => wp_create_nonce( 'userflo_download' ),
				'canPurge' => current_user_can( 'manage_options' ),
			) ) . ';',
			'before'
		);
	}

	/** The open tab of the Tools screen: 'tools' (exports, reports, data) or 'storage' (Database & Storage). */
	public static function tab() {
		$t = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'tools'; // phpcs:ignore WordPress.Security.NonceVerification -- Only chooses which read-only tab is shown.
		return 'storage' === $t ? 'storage' : 'tools';
	}

	private static function tabs() {
		$cur = self::tab();
		echo '<nav class="nav-tab-wrapper uf-tools-tabs" aria-label="' . esc_attr__( 'Tools sections', 'userflo' ) . '">';
		foreach ( array( 'tools' => __( 'Export & data', 'userflo' ), 'storage' => __( 'Database & Storage', 'userflo' ) ) as $k => $label ) {
			printf( '<a class="nav-tab%s" href="%s"%s>%s</a>', $cur === $k ? ' nav-tab-active' : '', esc_url( Ui::url( 'userflo-tools', 'tools' === $k ? array() : array( 'tab' => $k ) ) ), $cur === $k ? ' aria-current="page"' : '', esc_html( $label ) );
		}
		echo '</nav>';
	}

	public static function render() {
		global $wpdb;
		if ( ! current_user_can( Plugin::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'userflo' ), 403 );
		}
		Ui::open( __( 'Tools', 'userflo' ) );
		self::tabs();
		if ( 'storage' === self::tab() ) {
			StoragePage::render();
			Ui::close();
			return;
		}
		$formats = array_keys( Jobs::formats() );

		echo '<div class="uf-card"><h2>' . esc_html__( 'Export data', 'userflo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Exports run as resumable background jobs in bounded batches, so large tables are safe. Files are kept privately on this server for 24 hours.', 'userflo' ) . '</p>';
		echo '<form id="uf-export-form" class="uf-form-grid">';
		echo '<label>' . esc_html__( 'Format', 'userflo' ) . ' <select name="format">';
		foreach ( $formats as $f ) {
			echo '<option value="' . esc_attr( $f ) . '">' . esc_html( strtoupper( $f ) ) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__( 'Dataset', 'userflo' ) . ' <select name="dataset"><option value="all">' . esc_html__( 'All (JSON only)', 'userflo' ) . '</option>';
		foreach ( array_keys( Datasets::all() ) as $k ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( ucfirst( $k ) ) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__( 'From', 'userflo' ) . ' <input type="date" name="from"></label>';
		echo '<label>' . esc_html__( 'To', 'userflo' ) . ' <input type="date" name="to"></label>';
		echo '<label>' . esc_html__( 'Visitor ID (number)', 'userflo' ) . ' <input type="number" name="visitor" min="0" class="small-text"></label>';
		echo '<label>' . esc_html__( 'Event types', 'userflo' ) . ' <input type="text" name="types" placeholder="click,download" class="regular-text"></label>';
		echo '<label>' . esc_html__( 'Anonymisation', 'userflo' ) . ' <select name="anonymise"><option value="strip">' . esc_html__( 'Remove user IDs and IP addresses', 'userflo' ) . '</option><option value="pseudo">' . esc_html__( 'Also replace visitor/session IDs with hashes', 'userflo' ) . '</option><option value="none">' . esc_html__( 'None (contains personal data)', 'userflo' ) . '</option></select></label>';
		echo '<p><button type="submit" class="button button-primary" id="uf-export-start">' . esc_html__( 'Start export', 'userflo' ) . '</button> <button type="button" class="button" id="uf-export-cancel" hidden>' . esc_html__( 'Cancel', 'userflo' ) . '</button></p></form>';
		echo '<div id="uf-export-status" role="status" aria-live="polite"></div></div>';

		echo '<div class="uf-card"><h2>' . esc_html__( 'Daily summaries', 'userflo' ) . '</h2><p>' . esc_html__( 'One row of counts per day (totals, top pages, sources, countries, devices), kept after the detailed data is deleted. Counts only: no visitor, session or address.', 'userflo' ) . '</p>';
		$first = \UserFlo\Insights\Summary::first_day();
		echo '<p>' . esc_html( $first ? sprintf( /* translators: %s: date */ __( 'Summaries exist from %s.', 'userflo' ), $first ) : __( 'No summaries yet. They are written once a day is over.', 'userflo' ) ) . ' <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=userflo_summary_csv' ), 'userflo_summary_csv' ) ) . '">' . esc_html__( 'Download as CSV', 'userflo' ) . '</a></p></div>';

		/** Add-ons (UserFlo Pro) add their own cards, e.g. PDF reports. */
		do_action( 'userflo_tools_cards' );

		echo '<div class="uf-card"><h2>' . esc_html__( 'Data management', 'userflo' ) . '</h2>';
		$days = (int) Settings::get( 'retention_days' );
		echo '<p>' . esc_html( $days > 0 ? sprintf( /* translators: %d: days */ __( 'Retention is set to %d days; cleanup runs daily. You can run it now:', 'userflo' ), $days ) : __( 'Retention is disabled (data is kept forever). Change it in Settings.', 'userflo' ) ) . '</p>';
		echo '<p><button class="button" id="uf-cleanup-now">' . esc_html__( 'Run retention cleanup now', 'userflo' ) . '</button> <span id="uf-cleanup-result" role="status"></span></p>';
		if ( current_user_can( 'manage_options' ) ) {
			echo '<hr><p><strong>' . esc_html__( 'Delete all analytics data', 'userflo' ) . '</strong> — ' . esc_html__( 'removes every visitor, session, page view and event. Settings are kept.', 'userflo' ) . '</p>';
			echo '<p><label>' . esc_html__( 'Type DELETE ALL DATA to confirm:', 'userflo' ) . ' <input type="text" id="uf-purge-confirm" autocomplete="off"></label> <button class="button button-link-delete" id="uf-purge" disabled>' . esc_html__( 'Delete everything', 'userflo' ) . '</button> <span id="uf-purge-result" role="status"></span></p>';
		}
		echo '<p class="description">' . esc_html__( 'Individual visitors can be deleted from their profile. WordPress’ Tools → Export/Erase Personal Data also covers visitors linked to a user account.', 'userflo' ) . '</p></div>';

		echo '<div class="uf-card"><h2>' . esc_html__( 'Status', 'userflo' ) . '</h2><table class="widefat striped"><tbody>';
		echo '<tr><th>' . esc_html__( 'Plugin version', 'userflo' ) . '</th><td>' . esc_html( USERFLO_VERSION ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Database schema', 'userflo' ) . '</th><td>' . (int) get_option( Migrator::OPTION, 0 ) . ' / ' . (int) USERFLO_DB_VERSION . ( Migrator::tables_exist() ? '' : ' — <strong>' . esc_html__( 'tables missing', 'userflo' ) . '</strong>' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		// Row counts come from the stored measurement (database estimates), not from counting the tables while this page loads.
		$snap = \UserFlo\Storage\Measure::read();
		foreach ( Tables::all() as $t ) {
			$sfx = 0 === strpos( $t, $wpdb->prefix ) ? substr( $t, strlen( $wpdb->prefix ) ) : $t;
			$n   = $snap && ! empty( $snap['tables'][ $sfx ] ) && null !== $snap['tables'][ $sfx ]['rows'] ? '~' . number_format_i18n( (int) $snap['tables'][ $sfx ]['rows'] ) . ' ' . __( 'rows (estimate)', 'userflo' ) : '—';
			echo '<tr><th><code>' . esc_html( $t ) . '</code></th><td>' . esc_html( $n ) . '</td></tr>';
		}
		$bf = \UserFlo\Insights\UtmBackfill::state();
		if ( $bf && ! empty( $bf['done'] ) ) {
			echo '<tr><th>' . esc_html__( 'Campaign data', 'userflo' ) . '</th><td>' . esc_html( sprintf( /* translators: %s: number of sessions */ __( 'Read from earlier visits: %s sessions with campaign values.', 'userflo' ), number_format_i18n( (int) $bf['filled'] ) ) ) . '</td></tr>';
		} elseif ( $bf ) {
			echo '<tr><th>' . esc_html__( 'Campaign data', 'userflo' ) . '</th><td>' . esc_html__( 'Database upgrade: reading campaign values from earlier visits', 'userflo' ) . ' (' . (int) $bf['last'] . ' / ' . (int) $bf['max'] . ')</td></tr>';
		}
		echo '<tr><th>' . esc_html__( 'Next cleanup', 'userflo' ) . '</th><td>' . esc_html( wp_next_scheduled( 'userflo_cleanup' ) ? wp_date( 'Y-m-d H:i', wp_next_scheduled( 'userflo_cleanup' ) ) : '—' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Tracker endpoint', 'userflo' ) . '</th><td><code>' . esc_html( rest_url( 'userflo/v1/collect' ) ) . '</code></td></tr>';
		echo '</tbody></table></div>';
		Ui::close();
	}

	/** Shared by add-ons: capability and nonce check for an admin-post action. */
	public static function guard( $action ) {
		if ( ! current_user_can( Plugin::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'userflo' ), 403 );
		}
		check_admin_referer( $action );
	}

	/** Streams a finished export job to its owner. */
	public static function handle_download() {
		self::guard( 'userflo_download' );
		$job = isset( $_GET['job'] ) ? sanitize_text_field( wp_unslash( $_GET['job'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- guard() has checked the nonce.
		$res = Jobs::download_path( $job, get_current_user_id() );
		if ( is_wp_error( $res ) ) {
			wp_die( esc_html( $res->get_error_message() ), (int) ( $res->get_error_data()['status'] ?? 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: ' . $res['mime'] );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $res['filename'] ) . '"' );
		header( 'Content-Length: ' . filesize( $res['path'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $res['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	public static function handle_delete_visitor() {
		$id = isset( $_POST['visitor'] ) ? absint( $_POST['visitor'] ) : 0;
		if ( ! current_user_can( Plugin::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to delete analytics.', 'userflo' ), 403 );
		}
		check_admin_referer( 'userflo_delete_visitor_' . $id );
		Reports::delete_visitor( $id );
		wp_safe_redirect( Ui::url( 'userflo-visitors' ) );
		exit;
	}
}
