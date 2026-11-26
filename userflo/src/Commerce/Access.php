<?php
namespace UserFlo\Commerce;

defined( 'ABSPATH' ) || exit;

/** Sales figures are more sensitive than traffic: besides UserFlo's own capability the user needs WooCommerce's (or be an administrator). */
final class Access {

	public static function can() {
		return \UserFlo\Rest\Routes::can_view() && ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) || current_user_can( 'view_woocommerce_reports' ) );
	}
}
