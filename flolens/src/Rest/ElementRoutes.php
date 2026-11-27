<?php
namespace UserFlo\Rest;

use UserFlo\Audit\Findings;
use UserFlo\Edition;
use UserFlo\Elements\Stats;
use UserFlo\Tracking\Validator;

defined( 'ABSPATH' ) || exit;

/** Elements: the audit inventory with interaction analytics. Reading follows the analytics capability. */
final class ElementRoutes {

	public static function register() {
		if ( ! \UserFlo\Modules::on( 'elements' ) ) {
			return;
		}
		$view  = array( Routes::class, 'can_view' );
		$range = array(
			'days'     => array( 'type' => 'integer', 'default' => 30, 'minimum' => 1, 'maximum' => 366 ),
			'from'     => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
			'to'       => array( 'type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$' ),
			'audience' => array( 'type' => 'string', 'enum' => array( 'all', 'anonymous', 'logged_in' ), 'default' => 'all' ),
		);
		register_rest_route( Routes::NS, '/elements', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'index' ), 'permission_callback' => $view, 'args' => $range + array(
			'kind'     => array( 'type' => 'string', 'enum' => array( '', 'link', 'external', 'file', 'contact', 'button', 'form', 'image', 'media', 'anchor', 'control' ), 'default' => '' ),
			'page'     => array( 'type' => 'string', 'default' => '', 'maxLength' => 500 ),
			'search'   => array( 'type' => 'string', 'default' => '', 'maxLength' => 100 ),
			'sort'     => array( 'type' => 'string', 'enum' => array( 'interactions', 'visitors', 'label', 'pages' ), 'default' => 'interactions' ),
			'dir'      => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'default' => 'desc' ),
			'paged'    => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 1000 ),
			'per_page' => array( 'type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100 ),
		) ) );
		register_rest_route( Routes::NS, '/elements/note', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'note' ), 'permission_callback' => array( __CLASS__, 'can_annotate' ), 'args' => array(
			'key'   => array( 'type' => 'string', 'required' => true, 'maxLength' => 16 ),
			'alias' => array( 'type' => 'string', 'default' => '', 'maxLength' => 400 ),
			'note'  => array( 'type' => 'string', 'default' => '', 'maxLength' => 2000 ),
		) ) );
		register_rest_route( Routes::NS, '/elements/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'detail' ), 'permission_callback' => $view, 'args' => $range + array(
			'id' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ),
		) ) );
	}

	public static function can_annotate() {
		return current_user_can( 'manage_options' );
	}

	/** Gives an element a name and/or a note (both empty removes them). Display only. */
	public static function note( \WP_REST_Request $r ) {
		$res = \UserFlo\Elements\Notes::set( (string) $r['key'], (string) $r['alias'], (string) $r['note'] );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array( 'ok' => true );
	}

	private static function bounds( \WP_REST_Request $r ) {
		$range = Routes::range( $r );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		return array( \UserFlo\Core\Clock::mysql( $range[0] ), \UserFlo\Core\Clock::mysql( $range[1] ) );
	}

	public static function index( \WP_REST_Request $r ) {
		$b = self::bounds( $r );
		if ( is_wp_error( $b ) ) {
			return $b;
		}
		$page = (string) $r['page'];
		if ( '' !== $page && null === Validator::path( $page ) ) {
			return new \WP_Error( 'userflo_bad_page', __( 'That is not a page address.', 'flolens' ), array( 'status' => 400 ) );
		}
		$ttl = (int) apply_filters( 'userflo_overview_cache_ttl', 120 );
		$key = 'userflo_elm_' . md5( wp_json_encode( array( $b, $r->get_params() ) ) );
		$out = $ttl > 0 ? get_transient( $key ) : false;
		if ( ! is_array( $out ) ) {
			$out = Stats::listing( array(
				'from' => $b[0], 'to' => $b[1], 'audience' => (string) $r['audience'], 'kind' => (string) $r['kind'], 'page' => $page, 'search' => (string) $r['search'],
				'sort' => (string) $r['sort'], 'dir' => (string) $r['dir'], 'page_no' => (int) $r['paged'], 'per_page' => (int) $r['per_page'], 'unlisted' => true,
			) );
			$out['kinds'] = Stats::kinds();
			if ( $ttl > 0 ) {
				set_transient( $key, $out, $ttl );
			}
		}
		$notes = \UserFlo\Elements\Notes::all();
		foreach ( $out['items'] as $i => $it ) {
			$out['items'][ $i ] = \UserFlo\Elements\Notes::decorate( $it, $notes );
		}
		$out['advanced'] = Edition::can( 'elements_advanced' );
		$out['audit']    = array( 'pages' => \UserFlo\Audit\Store::page_count() );
		return $out;
	}

	public static function detail( \WP_REST_Request $r ) {
		$el = Stats::element( (int) $r['id'] );
		if ( ! $el ) {
			return new \WP_Error( 'userflo_element_missing', __( 'That element is no longer in the inventory.', 'flolens' ), array( 'status' => 404 ) );
		}
		$b = self::bounds( $r );
		if ( is_wp_error( $b ) ) {
			return $b;
		}
		$off = (int) wp_timezone()->getOffset( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) );
		$out = Stats::detail( $el, $b[0], $b[1], (string) $r['audience'], $off );
		$out['element']  = array( 'id' => (int) $el['id'], 'kind' => $el['kind'], 'label' => $el['label'], 'identifier' => $el['identifier'], 'value' => $el['value_sig'], 'href' => $el['href'], 'pages' => (int) $el['pages'], 'instances' => (int) $el['instances'], 'first_seen' => $el['first_seen'], 'last_seen' => $el['last_seen'] );
		$out['element'] = \UserFlo\Elements\Notes::decorate( $out['element'] );
		$out['advanced'] = Edition::can( 'elements_advanced' ) && class_exists( '\\UserFlo\\Elements\\Journey' );
		if ( $out['advanced'] ) {
			$out['around'] = \UserFlo\Elements\Journey::around( $el, $b[0], $b[1], (string) $r['audience'] );
			$out['goals']  = \UserFlo\Elements\Journey::goals( $el, $b[0], $b[1] );
			$out['findings'] = Findings::for_element( (int) $el['id'] );
		}
		return $out;
	}
}
