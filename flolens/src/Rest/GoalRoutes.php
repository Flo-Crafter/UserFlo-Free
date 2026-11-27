<?php
namespace UserFlo\Rest;

use UserFlo\Edition;
use UserFlo\Goals\History;
use UserFlo\Goals\Stats;
use UserFlo\Goals\Store;

defined( 'ABSPATH' ) || exit;

/** Goals: reading follows the analytics capability; creating, changing and deleting need manage_options. */
final class GoalRoutes {

	public static function register() {
		if ( ! \UserFlo\Modules::on( 'goals' ) ) {
			return; // A module that is off registers nothing.
		}
		$view  = array( Routes::class, 'can_view' );
		$admin = array( __CLASS__, 'can_manage' );
		$id    = array( 'id' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ) );
		$body  = array(
			'name'       => array( 'type' => 'string', 'maxLength' => 200 ),
			'type'       => array( 'type' => 'string', 'enum' => Store::types() ),
			'match_type' => array( 'type' => 'string', 'maxLength' => 10 ),
			'pattern'    => array( 'type' => 'string', 'maxLength' => 400 ),
			'event_type' => array( 'type' => 'string', 'maxLength' => 20 ),
			'status'     => array( 'type' => 'string', 'enum' => array( 'active', 'paused' ) ),
			'min_count'  => array( 'type' => array( 'integer', 'string' ) ),
		);
		$body = (array) apply_filters( 'userflo_goal_rest_args', $body ); // An add-on lists the extra fields its goals accept.
		register_rest_route( Routes::NS, '/goals', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'index' ), 'permission_callback' => $view ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'create' ), 'permission_callback' => $admin, 'args' => $body ),
		) );
		register_rest_route( Routes::NS, '/goals/(?P<id>\d+)', array(
			array( 'methods' => 'PUT', 'callback' => array( __CLASS__, 'update' ), 'permission_callback' => $admin, 'args' => $id + $body ),
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'delete' ), 'permission_callback' => $admin, 'args' => $id ),
		) );
		register_rest_route( Routes::NS, '/goals/(?P<id>\d+)/rebuild', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rebuild' ), 'permission_callback' => $admin, 'args' => $id + array(
			'after' => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0 ),
		) ) );
		register_rest_route( Routes::NS, '/goals/preview', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'preview' ), 'permission_callback' => $admin, 'args' => $body ) );
		$range = array(
			'days' => array( 'type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 366 ),
			'from' => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
			'to'   => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
		);
		register_rest_route( Routes::NS, '/goals/stats', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'stats' ), 'permission_callback' => $view, 'args' => $range + array(
			'compare' => array( 'type' => 'boolean', 'default' => true ),
		) ) );
		register_rest_route( Routes::NS, '/goals/(?P<id>\d+)/breakdown', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'breakdown' ), 'permission_callback' => $view, 'args' => $id + $range + array(
			'by' => array( 'type' => 'string', 'enum' => array_keys( Stats::dimensions() ), 'default' => 'campaign' ),
		) ) );
	}

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	private static function present( array $g ) {
		return (array) apply_filters( 'userflo_goal_present', $g );
	}

	public static function index() {
		$limit = Store::limit();
		return array(
			'goals'       => array_values( array_map( array( __CLASS__, 'present' ), array_filter( Store::all(), array( Store::class, 'standard' ) ) ) ),
			'limit'       => $limit,
			'active'      => Store::active_count(),
			'event_types' => Store::event_types(),
			'advanced'    => Edition::can( 'goals_advanced' ), // Whether the add-on that extends goals is active: only used to show or hide hints.
		);
	}

	public static function create( \WP_REST_Request $r ) {
		$g = Store::save( $r->get_json_params() ? $r->get_json_params() : $r->get_params() );
		return is_wp_error( $g ) ? $g : new \WP_REST_Response( self::present( $g ), 201 );
	}

	public static function update( \WP_REST_Request $r ) {
		$g = Store::save( $r->get_json_params() ? $r->get_json_params() : $r->get_params(), (int) $r['id'] );
		return is_wp_error( $g ) ? $g : self::present( $g );
	}

	public static function delete( \WP_REST_Request $r ) {
		return Store::delete( (int) $r['id'] ) ? array( 'deleted' => true ) : new \WP_Error( 'userflo_goal_missing', __( 'That goal no longer exists.', 'flolens' ), array( 'status' => 404 ) );
	}

	public static function rebuild( \WP_REST_Request $r ) {
		return History::rebuild( (int) $r['id'], (int) $r['after'], 0 === (int) $r['after'] );
	}

	/** Checks a goal definition and reports how many page views or events of the last 30 days it would have matched. */
	public static function preview( \WP_REST_Request $r ) {
		$params = $r->get_json_params() ? $r->get_json_params() : $r->get_params();
		$row    = Store::clean( $params );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( ! Store::standard( $row ) ) {
			return array( 'valid' => true, 'matches' => null, 'sessions' => null, 'duplicate_name' => Store::name_taken( $row['name'] ) );
		}
		return array( 'valid' => true, 'duplicate_name' => Store::name_taken( $row['name'] ) ) + History::preview( $row );
	}

	public static function stats( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		$from = \UserFlo\Core\Clock::mysql( $range[0] );
		$to   = \UserFlo\Core\Clock::mysql( $range[1] );
		$out  = Stats::summary( $from, $to );
		if ( $r['compare'] ) {
			$len = $range[1] - $range[0];
			$old = Stats::summary( \UserFlo\Core\Clock::mysql( $range[0] - $len ), $from );
			$by  = array();
			foreach ( $old['goals'] as $g ) {
				$by[ $g['id'] ] = $g;
			}
			foreach ( $out['goals'] as $i => $g ) {
				$out['goals'][ $i ]['prev'] = isset( $by[ $g['id'] ] ) ? $by[ $g['id'] ]['completions'] : 0;
			}
			$out['prev_sessions'] = $old['sessions'];
		}
		$out['converting'] = Stats::converting_sessions( $from, $to );
		return $out;
	}

	public static function breakdown( \WP_REST_Request $r ) {
		$g = Store::get( (int) $r['id'] );
		if ( ! $g || ! Store::standard( $g ) ) {
			return new \WP_Error( 'userflo_goal_missing', __( 'That goal no longer exists.', 'flolens' ), array( 'status' => 404 ) );
		}
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		return array( 'goal' => $g['name'], 'by' => $r['by'], 'rows' => Stats::breakdown( $g['id'], \UserFlo\Core\Clock::mysql( $range[0] ), \UserFlo\Core\Clock::mysql( $range[1] ), $r['by'] ) );
	}
}
