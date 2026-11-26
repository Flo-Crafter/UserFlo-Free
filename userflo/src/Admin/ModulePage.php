<?php
namespace UserFlo\Admin;

use UserFlo\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * What a sidebar entry shows while its module is turned off: a short notice and, for administrators, an enable button. It loads no script
 * or style of its own and runs none of the module's queries. Stored data is untouched.
 */
final class ModulePage {

	const ACTION = 'userflo_module_enable';

	public static function register() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_enable' ) );
	}

	public static function render( $module, $title ) {
		$can = current_user_can( 'manage_options' );
		echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1><div class="notice notice-info inline"><p><strong>' . esc_html__( 'This section is turned off.', 'userflo' ) . '</strong> ';
		esc_html_e( 'Nothing is collected or processed for it while it is off. Data recorded earlier is kept and comes back when you turn it on.', 'userflo' );
		echo '</p></div>';
		if ( $can ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::ACTION . '_' . $module );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="module" value="' . esc_attr( $module ) . '">';
			echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Turn on', 'userflo' ) . '</button> ';
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=userflo-settings&tab=components' ) ) . '">' . esc_html__( 'Manage sections', 'userflo' ) . '</a></p></form>';
		} else {
			echo '<p>' . esc_html__( 'Ask a site administrator to turn this section on.', 'userflo' ) . '</p>';
		}
		echo '</div>';
	}

	public static function handle_enable() {
		$module = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked on the next line.
		check_admin_referer( self::ACTION . '_' . $module );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'userflo' ), 403 );
		}
		if ( ! isset( Modules::all()[ $module ] ) ) { // Unknown, or a Pro module this site is not licensed for: refused server-side.
			wp_die( esc_html__( 'That section is not available.', 'userflo' ), 400 );
		}
		Modules::set_switched( $module, true );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=userflo' ) );
		exit;
	}
}
