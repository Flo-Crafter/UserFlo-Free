<?php
namespace UserFlo\Rest;

use UserFlo\Database\Tables;
use UserFlo\Export\Filters;
use UserFlo\Export\Jobs;
use UserFlo\Privacy\Retention;

defined( 'ABSPATH' ) || exit;

/** Export jobs and data-management actions. Same capability as the read API; cookie-authenticated requests also need the REST nonce. */
final class ExportRoutes {

	public static function register() {
		$perm = array( Routes::class, 'can_view' );
		$id   = array( 'id' => array( 'type' => 'string', 'required' => true, 'pattern' => '^[a-f0-9]{32}$' ) );

		register_rest_route( Routes::NS, '/export', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'create' ),
			'permission_callback' => $perm,
			'args'                => array(
				'format'    => array( 'type' => 'string', 'required' => true, 'maxLength' => 20 ),
				'dataset'   => array( 'type' => 'string', 'default' => 'all', 'maxLength' => 20 ),
				'from'      => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
				'to'        => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
				'visitor'   => array( 'type' => 'integer', 'minimum' => 0 ),
				'types'     => array( 'type' => 'string', 'maxLength' => 200 ),
				'anonymise' => array( 'type' => 'string', 'enum' => array( 'none', 'strip', 'pseudo' ), 'default' => 'strip' ),
			),
		) );
		register_rest_route( Routes::NS, '/export/(?P<id>[a-f0-9]{32})', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'status' ), 'permission_callback' => $perm, 'args' => $id ),
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'cancel' ), 'permission_callback' => $perm, 'args' => $id ),
		) );
		register_rest_route( Routes::NS, '/export/(?P<id>[a-f0-9]{32})/step', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'step' ), 'permission_callback' => $perm, 'args' => $id ) );

		register_rest_route( Routes::NS, '/data/cleanup', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'cleanup' ), 'permission_callback' => $perm ) );
		register_rest_route( Routes::NS, '/data/purge', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'purge' ),
			'permission_callback' => array( __CLASS__, 'can_purge' ),
			'args'                => array( 'confirm' => array( 'type' => 'string', 'required' => true ) ),
		) );
	}

	/** Deleting everything is limited to administrators even if the view capability was delegated. */
	public static function can_purge() {
		return current_user_can( 'manage_options' );
	}

	public static function create( \WP_REST_Request $r ) {
		$f = Filters::from_array( array( 'from' => $r['from'], 'to' => $r['to'], 'visitor' => $r['visitor'], 'types' => $r['types'], 'anonymise' => $r['anonymise'] ) );
		return Jobs::create( (string) $r['format'], (string) $r['dataset'], $f, get_current_user_id() );
	}

	public static function step( \WP_REST_Request $r ) {
		return Jobs::step( $r['id'], get_current_user_id() );
	}

	public static function status( \WP_REST_Request $r ) {
		return Jobs::status( $r['id'], get_current_user_id() );
	}

	public static function cancel( \WP_REST_Request $r ) {
		return Jobs::cancel( $r['id'], get_current_user_id() );
	}

	public static function cleanup() {
		return Retention::run( 10 );
	}

	public static function purge( \WP_REST_Request $r ) {
		if ( 'DELETE ALL DATA' !== $r['confirm'] ) {
			return new \WP_Error( 'userflo_confirm', __( 'Type DELETE ALL DATA to confirm.', 'userflo' ), array( 'status' => 400 ) );
		}
		global $wpdb;
		foreach ( Tables::all() as $t ) {
			$wpdb->query( "TRUNCATE TABLE {$t}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own tables: names are internal constants, values are prepared.
		}
		// The notes about what was summarised, verified and deleted described data that no longer exists.
		delete_option( \UserFlo\Insights\Summary::STATE );
		delete_option( Retention::STATE );
		delete_option( \UserFlo\Storage\Measure::OPTION );
		return array( 'purged' => true );
	}
}
