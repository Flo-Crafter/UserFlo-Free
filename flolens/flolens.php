<?php
/**
 * Plugin Name:       FloLens – Visitor Journeys & Analytics
 * Plugin URI:        https://flocrafter.com/
 * Description:       Visitor tracking and analytics that stay on your own site: visitor profiles, session history, page views and journeys. Privacy-first, no external service.
 * Version:           0.47.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            FloCrafter
 * Author URI:        https://flocrafter.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flolens
 *
 * @package FloLens
 */

defined( 'ABSPATH' ) || exit;

define( 'USERFLO_VERSION', '0.47.0' );
define( 'USERFLO_DB_VERSION', 20 );
define( 'USERFLO_FILE', __FILE__ );
define( 'USERFLO_DIR', plugin_dir_path( __FILE__ ) );
define( 'USERFLO_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'UserFlo\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$file = USERFLO_DIR . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'UserFlo\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'UserFlo\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'UserFlo\\Plugin', 'boot' ) );

// WooCommerce asks every plugin whether it works with High-Performance Order Storage and the block checkout. FloLens only reads orders through
// WooCommerce's own order objects and never touches order tables or post meta, so it declares both.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', USERFLO_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', USERFLO_FILE, true );
		}
	}
);
