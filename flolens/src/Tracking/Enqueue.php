<?php
namespace UserFlo\Tracking;

use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/** Delivers the frontend tracker (async/deferred, no dependencies). */
final class Enqueue {

	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		// The heatmap screen embeds the page; the admin bar would shift the layout away from what visitors see.
		if ( isset( $_GET['userflo_hm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			add_filter( 'show_admin_bar', '__return_false' );
		}
	}

	/** True when the current request should not load the tracker. */
	public static function should_skip() {
		if ( defined( 'USERFLO_DISABLED' ) && USERFLO_DISABLED ) {
			return true;
		}
		if ( ! Settings::get( 'enabled' ) || is_admin() || is_preview() || is_customize_preview() ) {
			return true;
		}
		// Page-builder editors / previews.
		foreach ( array( 'et_fb', 'et_pb_preview', 'elementor-preview', 'bricks', 'brickspreview', 'fl_builder', 'ct_builder', 'userflo_hm' ) as $arg ) {
			if ( isset( $_GET[ $arg ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				return true;
			}
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( Collector::path_excluded( (string) $path ) ) {
			return true;
		}
		if ( is_user_logged_in() && Collector::role_excluded( get_current_user_id() ) ) {
			return true;
		}
		return false;
	}

	public static function enqueue() {
		if ( self::should_skip() ) {
			return;
		}
		$min = file_exists( USERFLO_DIR . 'assets/js/tracker.min.js' ) ? 'tracker.min.js' : 'tracker.js';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		wp_enqueue_script( 'userflo-tracker', USERFLO_URL . 'assets/js/' . $min, array(), USERFLO_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );

		$config = array(
			'e'   => esc_url_raw( rest_url( 'userflo/v1/collect' ) ),
			'ttl' => (int) Settings::get( 'inactivity_minutes' ) * 60,
			'cm'  => Settings::get( 'consent_mode' ),
			'cc'  => Settings::get( 'consent_cookie' ),
			'gpc' => (bool) Settings::get( 'respect_gpc' ),
			'sc'  => array_map( 'intval', array_filter( explode( ',', (string) Settings::get( 'scroll_thresholds' ) ) ) ),
			'dl'  => (string) Settings::get( 'download_extensions' ),
			'ck'  => (bool) Settings::get( 'track_clicks' ),
			'is'  => (string) Settings::get( 'include_selectors' ),
			'xs'  => self::exclude_selectors( $path ),
			// Query parameters kept in recorded URLs (everything else is dropped; utm_* is always kept).
			'qp'  => array_values( (array) apply_filters( 'userflo_query_params', array( 'p', 'page_id', 'cat', 'tag', 'post_type', 'product', 'paged' ) ) ),
		);
		$ex = \UserFlo\Exclusions\Service::tracker( $path );
		if ( $ex['ids'] ) {
			$config['xt'] = $ex['ids']; // Exact element identifiers the browser should not report (Exclusions screen).
		}
		if ( \UserFlo\Privacy\Mode::cookieless() ) {
			$config['sm'] = 1; // Session-only: the tracker writes and reads no cookie.
		}
		if ( self::adapters_needed() ) {
			$a = file_exists( USERFLO_DIR . 'assets/js/adapters.min.js' ) ? 'adapters.min.js' : 'adapters.js';
			wp_enqueue_script( 'userflo-adapters', USERFLO_URL . 'assets/js/' . $a, array( 'userflo-tracker' ), USERFLO_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
		}
		/** Add-ons enqueue their own front-end scripts (for example heatmaps) and extend the config through `userflo_tracker_config`. */
		do_action( 'userflo_tracker_enqueued' );
		wp_add_inline_script( 'userflo-tracker', 'window.userfloConfig=' . wp_json_encode( apply_filters( 'userflo_tracker_config', $config ) ) . ';', 'before' );
	}

	/** The selectors the browser ignores on this page: those from Settings → Privacy plus the "stop tracking" rules for CSS selectors. */
	private static function exclude_selectors( $path ) {
		$css = \UserFlo\Exclusions\Service::tracker( $path )['css'];
		return trim( (string) Settings::get( 'exclude_selectors' ) . ( '' !== $css ? ',' . $css : '' ), ', ' );
	}

	/**
	 * Builder/commerce adapters are tiny and feature-detect in the browser; load them only when a
	 * supported builder or WooCommerce is present (filterable).
	 */
	public static function adapters_needed() {
		$theme = wp_get_theme();
		$names = strtolower( $theme->get( 'Name' ) . ' ' . $theme->get_template() );
		$needed = defined( 'ELEMENTOR_VERSION' ) || defined( 'ET_BUILDER_VERSION' ) || defined( 'ET_CORE_VERSION' ) || class_exists( 'WooCommerce' )
			|| false !== strpos( $names, 'divi' ) || false !== strpos( $names, 'bricks' ) || defined( 'BRICKS_VERSION' );
		return (bool) apply_filters( 'userflo_load_adapters', $needed );
	}
}
