<?php
namespace UserFlo\Translations;

use UserFlo\Rest\Routes as Api;

defined( 'ABSPATH' ) || exit;

/** Translation editor API (administrators only). */
final class Routes {

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_post_userflo_translations_export', array( __CLASS__, 'export' ) );
	}

	/** Languages that can be chosen (code => name): English, the bundled ones and custom ones. */
	public static function available() {
		$out = array();
		foreach ( self::languages() as $c => $l ) {
			$out[ $c ] = $l['name'];
		}
		return $out;
	}

	/** Every translated string of a language (shipped script translations plus database overrides), msgid => text. */
	public static function dictionary( $code ) {
		$out = array();
		foreach ( \UserFlo\I18n::HANDLES as $h ) {
			$f = USERFLO_DIR . 'languages/flolens-' . $code . '-' . $h . '.json';
			if ( is_readable( $f ) ) {
				$j = json_decode( (string) file_get_contents( $f ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( isset( $j['locale_data']['messages'] ) ) {
					foreach ( $j['locale_data']['messages'] as $k => $v ) {
						if ( '' !== $k && isset( $v[0] ) && '' !== $v[0] ) {
							$out[ $k ] = $v[0];
						}
					}
					break; // The per-handle files carry the same strings.
				}
			}
		}
		foreach ( Store::strings( $code ) as $k => $v ) {
			if ( '' !== $v ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	public static function dictionary_route( \WP_REST_Request $r ) {
		$code = (string) $r['locale'];
		if ( ! isset( self::available()[ $code ] ) ) {
			return new \WP_Error( 'userflo_unknown_language', __( 'Unknown language.', 'flolens' ), array( 'status' => 404 ) );
		}
		return array( 'locale' => $code, 'strings' => (object) self::dictionary( $code ) );
	}

	public static function can() {
		return current_user_can( 'manage_options' );
	}

	public static function routes() {
		$base = array( 'permission_callback' => array( __CLASS__, 'can' ) );
		$code = array( 'code' => array( 'type' => 'string', 'pattern' => '^[A-Za-z_]{2,10}$', 'required' => true ) );
		register_rest_route( Api::NS, '/i18n/(?P<locale>[A-Za-z_]{2,10})', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'dictionary_route' ), 'permission_callback' => static function () { return current_user_can( \UserFlo\Plugin::cap() ); } ) );
		register_rest_route( Api::NS, '/translations', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'index' ) ) + $base,
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'create' ), 'args' => array( 'code' => array( 'type' => 'string', 'required' => true ), 'name' => array( 'type' => 'string', 'default' => '' ) ) ) + $base,
		) );
		register_rest_route( Api::NS, '/translations/(?P<code>[A-Za-z_]{2,10})', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'view' ), 'args' => $code ) + $base,
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'update' ), 'args' => $code ) + $base,
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'remove' ), 'args' => $code ) + $base,
		) );
	}

	private static function languages() {
		$list = array( 'en_US' => array( 'name' => 'English', 'shipped' => true ) );
		foreach ( array( 'es_ES' => 'Español', 'fr_FR' => 'Français', 'hu_HU' => 'Magyar' ) as $c => $n ) {
			$list[ $c ] = array( 'name' => $n, 'shipped' => true );
		}
		foreach ( glob( Catalog::dir() . 'flolens-*.po' ) ?: array() as $f ) { // Any other bundled language added later.
			$c = substr( basename( $f ), 8, -3 );
			if ( ! isset( $list[ $c ] ) ) {
				$list[ $c ] = array( 'name' => $c, 'shipped' => true );
			}
		}
		foreach ( Store::all() as $c => $d ) {
			if ( ! isset( $list[ $c ] ) ) {
				$list[ $c ] = array( 'name' => isset( $d['name'] ) && '' !== $d['name'] ? $d['name'] : $c, 'shipped' => false );
			} elseif ( ! empty( $d['name'] ) ) {
				$list[ $c ]['name'] = $d['name'];
			}
		}
		return $list;
	}

	public static function index() {
		$out = array();
		foreach ( self::languages() as $c => $l ) {
			list( $done, $total ) = Store::progress( $c );
			$out[]                = array(
				'code'    => $c,
				'name'    => $l['name'],
				'source'  => 'en_US' === $c,
				'shipped' => $l['shipped'],
				'done'    => $done,
				'total'   => $total,
				'percent' => $total ? (int) floor( 100 * $done / $total ) : 0,
				'changed' => count( Store::strings( $c ) ),
			);
		}
		return array( 'languages' => $out );
	}

	private static function known( $code ) {
		return isset( self::languages()[ $code ] );
	}

	public static function view( \WP_REST_Request $r ) {
		$code = $r['code'];
		if ( ! self::known( $code ) ) {
			return new \WP_Error( 'userflo_unknown_language', __( 'Unknown language.', 'flolens' ), array( 'status' => 404 ) );
		}
		$meta = Store::meta( $code );
		$l    = self::languages()[ $code ];
		list( $done, $total ) = Store::progress( $code );
		return array(
			'code'     => $code,
			'name'     => $meta['name'] ? $meta['name'] : $l['name'],
			'notes'    => $meta['notes'],
			'done'     => $done,
			'total'    => $total,
			'sections' => Catalog::view( $code, Store::strings( $code ) ),
		);
	}

	public static function create( \WP_REST_Request $r ) {
		$res = Store::create( (string) $r['code'], (string) $r['name'] );
		return is_wp_error( $res ) ? $res : array( 'created' => $r['code'] );
	}

	public static function update( \WP_REST_Request $r ) {
		$code = $r['code'];
		if ( ! self::known( $code ) ) {
			return new \WP_Error( 'userflo_unknown_language', __( 'Unknown language.', 'flolens' ), array( 'status' => 404 ) );
		}
		$p = $r->get_json_params();
		$p = is_array( $p ) ? $p : array();
		$s = isset( $p['strings'] ) && is_array( $p['strings'] ) ? array_slice( $p['strings'], 0, 3000, true ) : array();
		return Store::save( $code, isset( $p['name'] ) ? (string) $p['name'] : '', isset( $p['notes'] ) ? (string) $p['notes'] : '', $s );
	}

	public static function remove( \WP_REST_Request $r ) {
		return array( 'deleted' => Store::delete( $r['code'] ) );
	}

	public static function export() {
		check_admin_referer( 'userflo_translations_export' );
		if ( ! self::can() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'flolens' ), 403 );
		}
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( ! self::known( $code ) ) {
			wp_die( esc_html__( 'Unknown language.', 'flolens' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: text/x-gettext-translation; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="flolens-' . $code . '.po"' );
		echo Store::export_po( $code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text .po file.
		exit;
	}
}
