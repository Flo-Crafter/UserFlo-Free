<?php
namespace UserFlo\Help;

use UserFlo\Logger;
use UserFlo\Rest\Routes as Api;

defined( 'ABSPATH' ) || exit;

/** Help data for the Settings screen: guides, logs, system info (administrators only). */
final class Routes {

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function can() {
		return current_user_can( 'manage_options' );
	}

	public static function routes() {
		$base = array( 'permission_callback' => array( __CLASS__, 'can' ) );
		register_rest_route( Api::NS, '/help/guides', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'guides' ) ) + $base );
		register_rest_route( Api::NS, '/help/guides/(?P<slug>[a-z0-9-]{2,40})', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'guide' ), 'args' => array( 'slug' => array( 'type' => 'string', 'pattern' => '^[a-z0-9-]{2,40}$' ) ) ) + $base );
		register_rest_route( Api::NS, '/help/log', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'log' ) ) + $base,
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'clear_log' ) ) + $base,
		) );
		register_rest_route( Api::NS, '/help/system', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'system' ) ) + $base );
	}

	public static function guides() {
		return array( 'items' => Guides::list_items() );
	}

	public static function guide( \WP_REST_Request $r ) {
		$g = Guides::render( $r['slug'] );
		return $g ? array( 'slug' => $r['slug'], 'title' => $g[0], 'html' => $g[1] ) : new \WP_Error( 'userflo_no_guide', __( 'Guide not found.', 'userflo' ), array( 'status' => 404 ) );
	}

	public static function log() {
		$items = array();
		foreach ( Logger::all() as $e ) {
			$items[] = array( 'at' => gmdate( 'Y-m-d\TH:i:s\Z', (int) $e['t'] ), 'level' => $e['l'], 'area' => $e['a'], 'message' => $e['m'] );
		}
		return array( 'items' => $items );
	}

	public static function clear_log() {
		Logger::clear();
		return array( 'cleared' => true );
	}

	/** Plain-text system information for support requests (no secrets, no visitor data). */
	public static function system() {
		global $wpdb;
		$geo  = \UserFlo\Geo\Database::status();
		$rows = array(
			'UserFlo'          => USERFLO_VERSION . ' (database schema ' . get_option( 'userflo_db_version' ) . ')',
			'WordPress'        => get_bloginfo( 'version' ) . ( is_multisite() ? ' (multisite)' : '' ),
			'PHP'              => PHP_VERSION,
			'Database'         => $wpdb->db_server_info(),
			'Server'           => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
			'Theme'            => wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ),
			'Site language'    => get_locale(),
			'Memory limit'     => WP_MEMORY_LIMIT,
			'WP-Cron'          => ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 'disabled (external cron needed)' : 'enabled',
			'zip extension'    => class_exists( '\ZipArchive' ) ? 'yes' : 'no',
			'sodium (secrets)' => function_exists( 'sodium_crypto_secretbox' ) ? 'yes' : 'no',
			'Geolocation'      => \UserFlo\Settings\Settings::get( 'geo_mode' ) . ' / ' . ( \UserFlo\Geo\Locator::database_path() ? 'database ready' : 'no database' ) . ( $geo['error'] ? ' / last error: ' . $geo['error'] : '' ),
			'Active plugins'   => count( (array) get_option( 'active_plugins', array() ) ),
		);
		return array( 'rows' => $rows );
	}
}
