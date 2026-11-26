<?php
namespace UserFlo\Rest;

use UserFlo\Admin\TablePrefs;

defined( 'ABSPATH' ) || exit;

/**
 * The signed-in user's own table layouts (column order and hidden columns). Everything is scoped to the current user: there is no user id
 * in the request, so one administrator cannot read or change another one's layout.
 */
final class PrefsRoutes {

	public static function register() {
		$perm  = array( Routes::class, 'can_view' );
		$list  = static function ( $v ) { return is_array( $v ); }; // A real list; WordPress would otherwise turn a lone value into a one-item list.
		$table = array( 'table' => array( 'type' => 'string', 'required' => true, 'enum' => TablePrefs::TABLES ) );
		register_rest_route( Routes::NS, '/table-prefs/(?P<table>[a-z_]+)', array(
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'save' ), 'permission_callback' => $perm, 'args' => $table + array(
				'order'  => array( 'type' => 'array', 'default' => array(), 'maxItems' => TablePrefs::MAX_COLUMNS, 'items' => array( 'type' => 'string', 'maxLength' => 40 ), 'validate_callback' => $list ),
				'hidden' => array( 'type' => 'array', 'default' => array(), 'maxItems' => TablePrefs::MAX_COLUMNS, 'items' => array( 'type' => 'string', 'maxLength' => 40 ), 'validate_callback' => $list ),
			) ),
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'reset' ), 'permission_callback' => $perm, 'args' => $table ),
		) );
	}

	public static function save( \WP_REST_Request $r ) {
		$saved = TablePrefs::save( get_current_user_id(), (string) $r['table'], array( 'order' => $r['order'], 'hidden' => $r['hidden'] ) );
		return false === $saved ? new \WP_Error( 'userflo_bad_table', __( 'This table cannot be customised.', 'userflo' ), array( 'status' => 400 ) ) : $saved;
	}

	public static function reset( \WP_REST_Request $r ) {
		return array( 'reset' => TablePrefs::reset( get_current_user_id(), (string) $r['table'] ) );
	}
}
