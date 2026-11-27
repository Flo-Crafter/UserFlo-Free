<?php
namespace UserFlo\Rest;

use UserFlo\Exclusions\Impact;
use UserFlo\Exclusions\Service;
use UserFlo\Exclusions\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Exclusion rules. Rules decide what is tracked and what is counted, so reading and changing them needs administrator rights
 * (`manage_options`), like the settings they sit beside. The REST nonce is checked by WordPress for cookie-authenticated requests.
 */
final class ExclusionRoutes {

	public static function register() {
		$admin = array( __CLASS__, 'can_manage' );
		$id    = array( 'id' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ) );
		$body  = array(
			'mode'         => array( 'type' => 'string', 'maxLength' => 8 ),
			'target'       => array( 'type' => 'string', 'maxLength' => 8 ),
			'field'        => array( 'type' => 'string', 'maxLength' => 10 ),
			'match_type'   => array( 'type' => 'string', 'maxLength' => 10 ),
			'pattern'      => array( 'type' => 'string', 'maxLength' => 400 ),
			'event_type'   => array( 'type' => 'string', 'maxLength' => 20 ),
			'page_scope'   => array( 'type' => 'string', 'maxLength' => 400 ),
			'finding_type' => array( 'type' => 'string', 'maxLength' => 40 ),
			'label'        => array( 'type' => 'string', 'maxLength' => 400 ),
			'note'         => array( 'type' => 'string', 'maxLength' => 400 ),
			'status'       => array( 'type' => 'string', 'enum' => array( 'active', 'paused' ) ),
		);
		register_rest_route( Routes::NS, '/exclusions', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'index' ), 'permission_callback' => $admin ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'create' ), 'permission_callback' => $admin, 'args' => $body ),
		) );
		register_rest_route( Routes::NS, '/exclusions/(?P<id>\d+)', array(
			array( 'methods' => 'PUT', 'callback' => array( __CLASS__, 'update' ), 'permission_callback' => $admin, 'args' => $id + $body ),
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete' ), 'permission_callback' => $admin, 'args' => $id ),
		) );
		register_rest_route( Routes::NS, '/exclusions/preview', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'preview' ), 'permission_callback' => $admin, 'args' => $body ) );
	}

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	private static function params( \WP_REST_Request $r ) {
		$p = $r->get_json_params();
		return is_array( $p ) && $p ? $p : $r->get_params();
	}

	public static function index() {
		$limit = Store::limit();
		return array(
			'rules'           => Store::all(),
			'builtin'         => Service::builtin(),
			'limit'           => $limit,
			'active'          => Store::active_count(),
			'audit_available' => Service::audit_available(),
			'event_types'     => \UserFlo\Tracking\Validator::$event_types,
			'settings_url'    => admin_url( 'admin.php?page=userflo-settings' ),
		);
	}

	public static function create( \WP_REST_Request $r ) {
		$rule = Store::save( self::params( $r ) );
		return is_wp_error( $rule ) ? $rule : new \WP_REST_Response( $rule, 201 );
	}

	public static function update( \WP_REST_Request $r ) {
		return Store::save( self::params( $r ), (int) $r['id'] );
	}

	public static function delete( \WP_REST_Request $r ) {
		return Store::delete( (int) $r['id'] ) ? array( 'deleted' => true ) : new \WP_Error( 'userflo_exclusion_missing', __( 'That rule no longer exists.', 'flolens' ), array( 'status' => 404 ) );
	}

	/** Checks a rule and reports what it would cover in the last 30 days, without saving anything. */
	public static function preview( \WP_REST_Request $r ) {
		$row = Store::clean( self::params( $r ) );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		return array( 'valid' => true ) + Impact::of( $row );
	}
}
