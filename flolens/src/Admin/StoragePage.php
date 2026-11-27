<?php
namespace UserFlo\Admin;

use UserFlo\Rest\StorageRoutes;

defined( 'ABSPATH' ) || exit;

/**
 * Tools → Database & Storage. The page itself only prints an empty region and the stored figures; assets/admin/storage.js draws it and talks
 * to the REST routes in Rest\StorageRoutes. Opening it measures nothing (the figures are the last stored measurement).
 */
final class StoragePage {

	public static function assets() {
		wp_enqueue_script( 'userflo-storage', USERFLO_URL . 'assets/admin/storage.js', array( 'wp-api-fetch', 'wp-i18n' ), USERFLO_VERSION . '-' . filemtime( USERFLO_DIR . 'assets/admin/storage.js' ), true );
		\UserFlo\I18n::script( 'userflo-storage' );
		wp_add_inline_script(
			'userflo-storage',
			'window.userfloStorage=' . wp_json_encode( array(
				'data'      => StorageRoutes::data(),
				'canManage' => current_user_can( 'manage_options' ),
				'settings'  => Ui::url( 'userflo-settings' ),
				'locale'    => str_replace( '_', '-', determine_locale() ),
				'today'     => wp_date( 'Y-m-d' ),
			) ) . ';',
			'before'
		);
	}

	public static function render() {
		echo '<div id="uf-storage" class="uf-storage" aria-live="off">';
		echo '<noscript><p>' . esc_html__( 'This screen needs JavaScript.', 'flolens' ) . '</p></noscript>';
		echo '</div>';
	}
}
