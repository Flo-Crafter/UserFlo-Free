<?php
namespace UserFlo\Admin;

use UserFlo\Plugin;
use UserFlo\Sharing\Feed;
use UserFlo\Sharing\Shares;
use UserFlo\Sharing\Xlsx;

defined( 'ABSPATH' ) || exit;

/** Share links with expiry, data feeds for spreadsheets / other systems, and the Excel workbook download. */
final class SharingPage {

	public static function register() {
		add_action( 'admin_post_userflo_share_create', array( __CLASS__, 'create' ) );
		add_action( 'admin_post_userflo_share_revoke', array( __CLASS__, 'revoke' ) );
		add_action( 'admin_post_userflo_xlsx', array( __CLASS__, 'xlsx' ) );
	}

	private static function back( array $args = array() ) {
		wp_safe_redirect( add_query_arg( $args, Ui::url( 'userflo-sharing' ) ) );
		exit;
	}

	public static function create() {
		check_admin_referer( 'userflo_share_create' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'userflo' ), 403 );
		}
		$preset = isset( $_POST['expiry'] ) ? sanitize_key( wp_unslash( $_POST['expiry'] ) ) : '7d';
		$map    = array( '1d' => DAY_IN_SECONDS, '7d' => 7 * DAY_IN_SECONDS, '30d' => 30 * DAY_IN_SECONDS, '90d' => 90 * DAY_IN_SECONDS );
		if ( 'custom' === $preset && ! empty( $_POST['expires_at'] ) ) {
			$dt      = date_create_immutable( sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ), wp_timezone() );
			$expires = $dt ? $dt->getTimestamp() : 0;
		} else {
			$expires = time() + ( isset( $map[ $preset ] ) ? $map[ $preset ] : $map['7d'] );
		}
		$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : '';
		$dash  = isset( $_POST['dashboard'] ) ? preg_replace( '/[^a-f0-9]/', '', strtolower( (string) wp_unslash( $_POST['dashboard'] ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$res   = Shares::create( isset( $_POST['label'] ) ? wp_unslash( $_POST['label'] ) : '', $scope, $expires, isset( $_POST['days'] ) ? (int) $_POST['days'] : 30, get_current_user_id(), $dash ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_wp_error( $res ) ) {
			self::back( array( 'uf_err' => rawurlencode( $res->get_error_message() ) ) );
		}
		set_transient( 'userflo_new_share_' . get_current_user_id(), array( 'token' => $res['token'], 'scope' => $scope ), 10 * MINUTE_IN_SECONDS );
		self::back( array( 'uf_new' => 1 ) );
	}

	public static function revoke() {
		check_admin_referer( 'userflo_share_revoke' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'userflo' ), 403 );
		}
		Shares::revoke( isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '' );
		self::back( array( 'uf_rev' => 1 ) );
	}

	public static function xlsx() {
		check_admin_referer( 'userflo_xlsx' );
		if ( ! current_user_can( Plugin::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'userflo' ), 403 );
		}
		if ( ! Xlsx::available() ) {
			wp_die( esc_html__( 'The PHP zip extension is required for Excel files. CSV feeds still work.', 'userflo' ) );
		}
		$days   = isset( $_POST['days'] ) ? max( 1, min( 366, (int) $_POST['days'] ) ) : 30;
		$sheets = array();
		foreach ( Feed::sets() as $key => $label ) {
			$sheets[ $label ] = Feed::rows( $key, $days );
		}
		$tmp = wp_tempnam( 'userflo-xlsx' );
		if ( ! Xlsx::write( $sheets, $tmp ) ) {
			wp_delete_file( $tmp );
			wp_die( esc_html__( 'Could not create the Excel file.', 'userflo' ) );
		}
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="userflo-statistics-' . gmdate( 'Y-m-d' ) . '.xlsx"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $tmp );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'userflo' ), 403 );
		}
		Ui::open( __( 'Sharing and data export', 'userflo' ) );
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['uf_err'] ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['uf_err'] ) ) ) ) . '</p></div>';
		}
		if ( isset( $_GET['uf_rev'] ) ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Link revoked.', 'userflo' ) . '</p></div>';
		}
		// phpcs:enable
		$new = get_transient( 'userflo_new_share_' . get_current_user_id() );
		if ( is_array( $new ) && isset( $_GET['uf_new'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			delete_transient( 'userflo_new_share_' . get_current_user_id() );
			echo '<div class="notice notice-success inline uf-newlink"><p><strong>' . esc_html__( 'Link created. Copy it now: for security only a hash is stored, so it cannot be shown again.', 'userflo' ) . '</strong></p>';
			if ( 'data' === $new['scope'] ) {
				echo '<p>' . esc_html__( 'Google Sheets formula:', 'userflo' ) . '</p>';
				foreach ( Feed::sets() as $k => $l ) {
					$u = Shares::feed_url( $new['token'], $k, 'csv' );
					echo '<p><label>' . esc_html( $l ) . ' <input type="text" readonly class="large-text code" value="' . esc_attr( '=IMPORTDATA("' . $u . '")' ) . '" onclick="this.select()"></label></p>';
				}
				echo '<p class="description">' . esc_html__( 'Replace .csv with .json for other systems. Append ?days=7 (1–366) to change the period.', 'userflo' ) . '</p>';
			} else {
				echo '<p><input type="text" readonly class="large-text code" value="' . esc_attr( Shares::page_url( $new['token'] ) ) . '" onclick="this.select()"></p>';
			}
			echo '</div>';
		}

		$dashboards = (array) apply_filters( 'userflo_share_dashboards', array() );
		$pick_uid   = isset( $_GET['dashboard'] ) ? preg_replace( '/[^a-f0-9]/', '', strtolower( sanitize_text_field( wp_unslash( $_GET['dashboard'] ) ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$pick       = isset( $dashboards[ $pick_uid ] );
		echo '<div class="uf-card"><h2>' . esc_html__( 'Create a link', 'userflo' ) . '</h2>';
		echo '<p class="uf-sub">' . esc_html__( 'Anyone with the link can see only the shared content, without a WordPress login, until it expires. Shared pages never show names, emails, IP addresses or visitor ids. Data feeds contain aggregates only.', 'userflo' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="uf-form-grid">';
		echo '<input type="hidden" name="action" value="userflo_share_create">';
		wp_nonce_field( 'userflo_share_create' );
		echo '<label>' . esc_html__( 'Label', 'userflo' ) . ' <input type="text" name="label" maxlength="80" placeholder="' . esc_attr__( 'e.g. Monthly report for management', 'userflo' ) . '"></label>';
		echo '<label>' . esc_html__( 'What to share', 'userflo' ) . ' <select name="scope"><option value="stats">' . esc_html__( 'Statistics page', 'userflo' ) . '</option>' . ( $dashboards ? '<option value="dashboard"' . selected( $pick, true, false ) . '>' . esc_html__( 'One dashboard (Statistics Builder)', 'userflo' ) . '</option>' : '' ) . '<option value="live">' . esc_html__( 'Live view', 'userflo' ) . '</option><option value="data">' . esc_html__( 'Data feed (Google Sheets / other systems)', 'userflo' ) . '</option></select></label>';
		if ( $dashboards ) {
			echo '<label>' . esc_html__( 'Dashboard (when sharing one dashboard)', 'userflo' ) . ' <select name="dashboard">';
			foreach ( $dashboards as $uid => $name ) {
				echo '<option value="' . esc_attr( $uid ) . '"' . selected( $pick_uid, $uid, false ) . '>' . esc_html( $name ) . '</option>';
			}
			echo '</select></label>';
		}
		echo '<label>' . esc_html__( 'Period (statistics and feeds)', 'userflo' ) . ' <select name="days"><option value="7">7</option><option value="30" selected>30</option><option value="90">90</option></select></label>';
		echo '<label>' . esc_html__( 'Expires', 'userflo' ) . ' <select name="expiry"><option value="1d">' . esc_html__( 'in 1 day', 'userflo' ) . '</option><option value="7d" selected>' . esc_html__( 'in 7 days', 'userflo' ) . '</option><option value="30d">' . esc_html__( 'in 30 days', 'userflo' ) . '</option><option value="90d">' . esc_html__( 'in 90 days', 'userflo' ) . '</option><option value="custom">' . esc_html__( 'on a date…', 'userflo' ) . '</option></select></label>';
		echo '<label>' . esc_html__( 'Custom expiry (site time)', 'userflo' ) . ' <input type="datetime-local" name="expires_at"></label>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Create link', 'userflo' ) . '</button></p></form></div>';

		echo '<div class="uf-card"><h2>' . esc_html__( 'Active and recent links', 'userflo' ) . '</h2>';
		$all = array_reverse( Shares::all() );
		if ( ! $all ) {
			echo '<p class="uf-empty">' . esc_html__( 'No links yet.', 'userflo' ) . '</p>';
		} else {
			$scopes = array( 'stats' => __( 'Statistics', 'userflo' ), 'live' => __( 'Live view', 'userflo' ), 'data' => __( 'Data feed', 'userflo' ), 'dashboard' => __( 'Dashboard', 'userflo' ) );
			echo '<div class="uf-scroll"><table class="widefat striped uf-table"><thead><tr><th>' . esc_html__( 'Label', 'userflo' ) . '</th><th>' . esc_html__( 'Type', 'userflo' ) . '</th><th>' . esc_html__( 'Created', 'userflo' ) . '</th><th>' . esc_html__( 'Expires', 'userflo' ) . '</th><th>' . esc_html__( 'Status', 'userflo' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $all as $s ) {
				$active = $s['expires'] > time();
				echo '<tr><td>' . esc_html( $s['label'] ? $s['label'] : '—' ) . '</td><td>' . esc_html( $scopes[ $s['scope'] ] . ( 'dashboard' === $s['scope'] ? ': ' . ( isset( $s['dashboard'], $dashboards[ $s['dashboard'] ] ) ? $dashboards[ $s['dashboard'] ] : __( '(unavailable)', 'userflo' ) ) : '' ) ) . '</td><td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $s['created'] ) ) . '</td><td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $s['expires'] ) ) . '</td><td>' . ( $active ? '<span class="uf-badge uf-badge-live">' . esc_html__( 'Active', 'userflo' ) . '</span>' : '<span class="uf-badge uf-badge-est">' . esc_html__( 'Expired', 'userflo' ) . '</span>' ) . '</td><td>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="userflo_share_revoke"><input type="hidden" name="id" value="' . esc_attr( $s['id'] ) . '">';
				wp_nonce_field( 'userflo_share_revoke' );
				echo '<button class="button button-link-delete">' . esc_html__( 'Revoke', 'userflo' ) . '</button></form></td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';

		echo '<div class="uf-card"><h2>' . esc_html__( 'Excel / Google Sheets', 'userflo' ) . '</h2>';
		echo '<p class="uf-sub">' . esc_html__( 'Download all statistics as an Excel workbook (opens in Excel, Google Sheets and LibreOffice), or create a Data feed link above and paste the IMPORTDATA formula into Google Sheets for a live, refreshing table. Raw visitor, session and event exports (CSV/JSON) are on the Tools page.', 'userflo' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="uf-filter"><input type="hidden" name="action" value="userflo_xlsx">';
		wp_nonce_field( 'userflo_xlsx' );
		echo '<label>' . esc_html__( 'Period', 'userflo' ) . ' <select name="days"><option value="7">7</option><option value="30" selected>30</option><option value="90">90</option><option value="366">366</option></select> ' . esc_html__( 'days', 'userflo' ) . '</label> <button class="button button-primary"' . ( Xlsx::available() ? '' : ' disabled' ) . '>' . esc_html__( 'Download .xlsx', 'userflo' ) . '</button></form></div>';
		if ( \UserFlo\Modules::on( 'shortcodes' ) ) {
			echo '<div class="uf-card"><h2>' . esc_html__( 'Shortcodes (show analytics on your site)', 'userflo' ) . '</h2>';
			echo '<p class="uf-sub">' . esc_html__( 'Paste a shortcode into any page or post. By default only people who may view analytics see the output; add access="members" or access="public" to widen it (aggregates only, never names or IP addresses).', 'userflo' ) . '</p>';
			echo '<ul class="uf-int-limits"><li><code>[userflo_overview days="30"]</code></li><li><code>[userflo_statistics show="pages,buttons,links,journeys" access="members"]</code></li><li><code>[userflo_achievements status="unlocked" access="public"]</code></li><li><code>[userflo_map level="countries" region="EU" access="public"]</code></li></ul>';
			echo '<p class="description">' . esc_html__( 'Optional: theme="dark". Details in docs/SHORTCODES.md.', 'userflo' ) . '</p></div>';
		}
		Ui::close();
	}
}
