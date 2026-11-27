<?php
namespace UserFlo\Admin;

use UserFlo\Edition;

defined( 'ABSPATH' ) || exit;

/**
 * One-time, dismissible notice for sites that used features before they became part of FloLens Pro (0.1.0-beta.19 split): tells them what
 * stopped, that nothing was deleted and where the settings are kept. It is only shown when such a feature was actually in use.
 */
final class SplitNotice {

	const DISMISS = 'userflo_split_notice_dismissed';

	public static function register() {
		add_action( 'admin_notices', array( __CLASS__, 'show' ) );
		add_action( 'admin_post_userflo_dismiss_split', array( __CLASS__, 'dismiss' ) );
	}

	/** @return string[] Labels of Pro features this site had configured. */
	public static function used_features() {
		$out = array();
		$s   = (array) get_option( 'userflo_settings', array() );
		if ( ! empty( $s['heatmaps_enabled'] ) && ! Edition::can( 'heatmaps' ) ) {
			$out[] = __( 'heatmaps', 'flolens' );
		}
		$m = (array) get_option( 'userflo_mail', array() );
		if ( ! empty( $m['enabled'] ) && ! Edition::can( 'scheduled_reports' ) ) {
			$out[] = __( 'scheduled e-mail reports', 'flolens' );
		}
		$i = get_option( 'userflo_integrations', array() );
		if ( ! empty( $i ) && ! Edition::can( 'integrations' ) ) {
			$out[] = __( 'GA4, Clarity or PostHog integrations', 'flolens' );
		}
		if ( ! empty( $s['geo_mode'] ) && 'city' === $s['geo_mode'] && ! Edition::can( 'map_city' ) ) {
			$out[] = __( 'city-level locations', 'flolens' );
		}
		if ( ! empty( $s['include_selectors'] ) && ! Edition::can( 'advanced_tracking' ) ) {
			$out[] = __( 'custom click selectors (still applied, now read-only)', 'flolens' );
		}
		return $out;
	}

	public static function show() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'userflo' ) || ! current_user_can( 'manage_options' ) || get_option( self::DISMISS ) ) {
			return;
		}
		$used = self::used_features();
		if ( ! $used ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Some FloLens features moved to FloLens Pro.', 'flolens' ) . '</strong> ';
		echo esc_html(
			sprintf(
				/* translators: %s: list of features */
				__( 'This site used %s. They are now part of FloLens Pro, so they are paused here. Nothing was deleted: your analytics data and these settings are kept and come back when FloLens Pro is installed and activated.', 'flolens' ),
				implode( ', ', $used )
			)
		);
		echo ' <a href="' . esc_url( Edition::upgrade_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'About FloLens Pro', 'flolens' ) . '</a> · ';
		echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=userflo_dismiss_split' ), 'userflo_dismiss_split' ) ) . '">' . esc_html__( 'Dismiss', 'flolens' ) . '</a></p></div>';
	}

	public static function dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'flolens' ), 403 );
		}
		check_admin_referer( 'userflo_dismiss_split' );
		update_option( self::DISMISS, 1, false );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=userflo' ) );
		exit;
	}
}
