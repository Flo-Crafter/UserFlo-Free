<?php
namespace UserFlo\Admin;

use UserFlo\Plugin;

defined( 'ABSPATH' ) || exit;

/** Mounts the React/TypeScript dashboard (admin-only bundle; the frontend tracker never loads it). */
final class AppPage {

	public static function overview() {
		self::mount( 'overview' );
	}

	public static function visitors() {
		self::mount( 'visitors' );
	}

	public static function live() {
		self::mount( 'live' );
	}

	public static function pages() {
		self::mount( 'pages' );
	}

	public static function map() {
		self::mount( 'map' );
	}

	public static function reports() {
		self::mount( 'reports' );
	}

	public static function stats() {
		self::mount( 'stats' );
	}

	public static function journeys() {
		self::mount( 'journeys' );
	}

	public static function calendar() {
		self::mount( 'calendar' );
	}

	public static function goals() {
		self::mount( 'goals' );
	}

	public static function elements() {
		self::mount( 'elements' );
	}

	public static function achievements() {
		self::mount( 'achievements' );
	}

	/** Exclusion rules change what is tracked and counted, so the screen needs the same right as Settings. */
	public static function exclusions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage exclusions.', 'flolens' ), 403 );
		}
		self::mount( 'exclusions' );
	}

	/** Mounts the React app for a view. Public so add-ons can register their own screens on the same shell. */
	public static function mount( $view ) {
		if ( ! current_user_can( Plugin::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to view analytics.', 'flolens' ), 403 );
		}
		$module = array( 'live' => 'live', 'pages' => 'pages', 'stats' => 'statistics', 'calendar' => 'statistics', 'journeys' => 'statistics', 'goals' => 'goals', 'elements' => 'elements', 'reports' => 'reports', 'achievements' => 'achievements', 'map' => 'map', 'audit' => 'page_intelligence' );
		if ( isset( $module[ $view ] ) && ! \UserFlo\Modules::on( $module[ $view ] ) ) {
			wp_die( esc_html__( 'This section is turned off in FloLens → Settings.', 'flolens' ), '', array( 'response' => 403, 'back_link' => true ) );
		}
		echo '<div class="wrap uf-wrap">';
		\UserFlo\Brand::bar();
		echo '<div id="userflo-app" data-view="' . esc_attr( $view ) . '"><p>' . esc_html__( 'Loading FloLens…', 'flolens' ) . '</p></div>';
		echo '<noscript><p>' . esc_html__( 'FloLens needs JavaScript enabled for its dashboard.', 'flolens' ) . '</p></noscript></div>';
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'userflo' ) ) {
			return;
		}
		$file = USERFLO_DIR . 'assets/admin/app.js';
		if ( file_exists( $file ) && ( false !== strpos( $hook, 'toplevel_page_userflo' ) || false !== strpos( $hook, 'userflo-visitors' ) || false !== strpos( $hook, 'userflo-goals' ) || false !== strpos( $hook, 'userflo-live' ) || false !== strpos( $hook, 'userflo-pages' ) || false !== strpos( $hook, 'userflo-map' ) || false !== strpos( $hook, 'userflo-reports' ) || false !== strpos( $hook, 'userflo-stats' ) || false !== strpos( $hook, 'userflo-calendar' ) || false !== strpos( $hook, 'userflo-journeys' ) || false !== strpos( $hook, 'userflo-achievements' ) || false !== strpos( $hook, 'userflo-exclusions' ) || false !== strpos( $hook, 'userflo-elements' ) || false !== strpos( $hook, 'userflo-audit' ) ) ) {
			wp_enqueue_script( 'userflo-admin-app', USERFLO_URL . 'assets/admin/app.js', (array) apply_filters( 'userflo_app_script_deps', array( 'wp-element', 'wp-api-fetch', 'wp-i18n' ) ), USERFLO_VERSION . '-' . filemtime( $file ), true );
			$tz = wp_timezone();
			wp_add_inline_script(
				'userflo-admin-app',
				'window.userfloApp=' . wp_json_encode(
					apply_filters( 'userflo_app_config', array(
						'timezone'  => wp_timezone_string(),
						'weekStart' => (int) get_option( 'start_of_week', 1 ),
						'locale'    => str_replace( '_', '-', determine_locale() ),
						'urls'      => array( 'overview' => admin_url( 'admin.php?page=userflo' ), 'visitors' => admin_url( 'admin.php?page=userflo-visitors' ), 'pages' => \UserFlo\Modules::on( 'pages' ) ? admin_url( 'admin.php?page=userflo-pages' ) : '', 'exclusions' => admin_url( 'admin.php?page=userflo-exclusions' ), 'elements' => \UserFlo\Modules::on( 'elements' ) ? admin_url( 'admin.php?page=userflo-elements' ) : '', 'tools' => admin_url( 'admin.php?page=userflo-tools' ), 'sharing' => admin_url( 'admin.php?page=userflo-sharing' ), 'settings' => admin_url( 'admin.php?page=userflo-settings' ), 'heatmaps' => \UserFlo\Modules::on( 'heatmaps' ) ? admin_url( 'admin.php?page=userflo-heatmaps' ) : '' ),
						'adminPost' => admin_url( 'admin-post.php' ),
						'canExclude' => current_user_can( 'manage_options' ),
						'buttons'   => array( 'guide' => (bool) \UserFlo\Settings\Settings::get( 'show_guide_button' ), 'ai' => (bool) \UserFlo\Settings\Settings::get( 'show_ai_button' ), 'export' => (bool) \UserFlo\Settings\Settings::get( 'show_export_button' ) ),
						'exclusionsAudit' => \UserFlo\Exclusions\Service::audit_available(),
						'edition'   => \UserFlo\Edition::export(),
						'brandLogo' => \UserFlo\Brand::company_logo(),
						'geoUrl'    => USERFLO_URL . 'assets/geo/world.json',
						'tablePrefs' => (object) \UserFlo\Admin\TablePrefs::all( get_current_user_id() ),
						'flagsUrl'  => USERFLO_URL . 'assets/flags/flags.svg',
						'animations' => (bool) \UserFlo\Settings\Settings::get( 'animations' ),
						'geoMode'   => \UserFlo\Settings\Settings::get( 'geo_mode' ),
						'modules'   => array( 'elements' => \UserFlo\Modules::on( 'elements' ), 'audit' => \UserFlo\Modules::on( 'audit' ), 'page_intelligence' => \UserFlo\Modules::on( 'page_intelligence' ), 'woocommerce' => \UserFlo\Modules::on( 'woocommerce' ), 'goals' => \UserFlo\Modules::on( 'goals' ), 'wp_reports' => \UserFlo\Modules::on( 'wp_reports' ), 'advanced_tracking' => \UserFlo\Modules::on( 'advanced_tracking' ), 'dashboards' => \UserFlo\Modules::on( 'dashboards' ) ),
						'features'  => array(
							'globe' => self::globe_enabled(),
							'live'  => \UserFlo\Modules::on( 'live' ),
						),
						'globeUrl'  => self::globe_enabled() ? self::globe_url() : '',
						'landUrl'   => self::globe_enabled() ? (string) apply_filters( 'userflo_globe_land_url', '' ) : '',
						'globeStyle' => 'vector' === \UserFlo\Settings\Settings::get( 'globe_style' ) ? 'vector' : 'hex',
						'siteType'  => \UserFlo\Settings\Settings::get( 'site_type' ),
						'reportLang' => \UserFlo\Settings\Settings::get( 'report_language' ),
						'languages' => \UserFlo\Translations\Routes::available(),
						'utcOffset' => $tz->getOffset( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) ),
					) )
				) . ';',
				'before'
			);
			\UserFlo\I18n::script( 'userflo-admin-app' );
		}
	}

	/** Address of the 3D globe script. It ships with FloLens Pro, which supplies it through this filter; Lite has none. */
	public static function globe_url() {
		return (string) apply_filters( 'userflo_globe_url', '' );
	}

	/**
	 * Whether the 3D globe view is offered on the Map screen: it needs the Pro feature and the script. The `userflo_feature_globe`
	 * filter can still switch it off. The 2D map, the data it reads and all tracking never depend on it.
	 */
	public static function globe_enabled() {
		return \UserFlo\Edition::can( 'map_globe' ) && (bool) apply_filters( 'userflo_feature_globe', true ) && '' !== self::globe_url();
	}
}
