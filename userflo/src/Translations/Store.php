<?php
namespace UserFlo\Translations;

use UserFlo\I18n;

defined( 'ABSPATH' ) || exit;

/**
 * Translations edited in Settings → Translations. They are stored in the database (option userflo_translations), apply on top of the
 * shipped translations, survive plugin updates, and also let administrators reword the English text or add whole new languages.
 * English remains the source of truth; anything untranslated falls back to it.
 */
final class Store {

	const OPTION = 'userflo_translations';

	private static $cache = null;
	private static $locale = null;

	public static function register() {
		add_filter( 'gettext', array( __CLASS__, 'filter' ), 20, 3 );
		add_filter( 'pre_load_script_translations', array( __CLASS__, 'script_json' ), 20, 4 );
	}

	public static function all() {
		if ( null === self::$cache ) {
			$o           = get_option( self::OPTION, array() );
			self::$cache = is_array( $o ) ? $o : array();
		}
		return self::$cache;
	}

	public static function reset() {
		self::$cache  = null;
		self::$locale = null;
	}

	public static function strings( $code ) {
		$a = self::all();
		return isset( $a[ $code ]['strings'] ) && is_array( $a[ $code ]['strings'] ) ? $a[ $code ]['strings'] : array();
	}

	public static function meta( $code ) {
		$a = self::all();
		return array( 'name' => isset( $a[ $code ]['name'] ) ? (string) $a[ $code ]['name'] : '', 'notes' => isset( $a[ $code ]['notes'] ) ? (string) $a[ $code ]['notes'] : '' );
	}

	public static function is_custom_language( $code ) {
		$a = self::all();
		return isset( $a[ $code ] ) && ! Catalog::has_shipped( $code ) && 'en_US' !== $code;
	}

	/** Custom (database-only) languages: code => name. */
	public static function custom_languages() {
		$out = array();
		foreach ( self::all() as $code => $d ) {
			if ( ! Catalog::has_shipped( $code ) && 'en_US' !== $code ) {
				$out[ $code ] = isset( $d['name'] ) && '' !== $d['name'] ? (string) $d['name'] : $code;
			}
		}
		return $out;
	}

	public static function valid_code( $code ) {
		return is_string( $code ) && (bool) preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $code );
	}

	private static function save_all( array $a ) {
		update_option( self::OPTION, $a, false );
		self::reset();
	}

	public static function create( $code, $name ) {
		if ( ! self::valid_code( $code ) ) {
			return new \WP_Error( 'userflo_bad_code', __( 'Use a locale code such as de_DE, it_IT or pt.', 'userflo' ), array( 'status' => 400 ) );
		}
		$a = self::all();
		if ( isset( $a[ $code ] ) || Catalog::has_shipped( $code ) || 'en_US' === $code ) {
			return new \WP_Error( 'userflo_exists', __( 'That language already exists.', 'userflo' ), array( 'status' => 409 ) );
		}
		$a[ $code ] = array( 'name' => \UserFlo\Core\Text::substr( sanitize_text_field( $name ), 60 ), 'notes' => '', 'strings' => array() );
		self::save_all( $a );
		return true;
	}

	private static function kses( $v ) {
		return wp_kses( $v, array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'strong' => array(), 'em' => array(), 'code' => array(), 'br' => array() ) );
	}

	/**
	 * Saves edits. $strings maps English text => translation; an empty value (or one equal to the shipped text) removes the override.
	 *
	 * @return array|\WP_Error array( saved, removed, rejected[] )
	 */
	public static function save( $code, $name, $notes, array $strings ) {
		if ( 'en_US' !== $code && ! self::valid_code( $code ) ) {
			return new \WP_Error( 'userflo_bad_code', __( 'Unknown language.', 'userflo' ), array( 'status' => 404 ) );
		}
		if ( 'en_US' !== $code && ! Catalog::has_shipped( $code ) && ! isset( self::all()[ $code ] ) ) {
			return new \WP_Error( 'userflo_bad_code', __( 'Unknown language.', 'userflo' ), array( 'status' => 404 ) );
		}
		$entries = Catalog::entries();
		$shipped = 'en_US' === $code ? array() : Catalog::shipped( $code );
		$a       = self::all();
		$cur     = isset( $a[ $code ]['strings'] ) ? $a[ $code ]['strings'] : array();
		$saved   = 0;
		$removed = 0;
		$bad     = array();
		foreach ( $strings as $k => $v ) {
			if ( ! is_string( $k ) || ! isset( $entries[ $k ] ) || ! is_string( $v ) ) {
				continue;
			}
			$v = trim( self::kses( \UserFlo\Core\Text::substr( str_replace( "\r", '', $v ), 2000 ) ) );
			$base = 'en_US' === $code ? $k : ( isset( $shipped[ $k ] ) ? $shipped[ $k ] : '' );
			if ( '' === $v || $v === $base ) {
				if ( isset( $cur[ $k ] ) ) {
					unset( $cur[ $k ] );
					++$removed;
				}
				continue;
			}
			if ( Catalog::placeholders( $k ) !== Catalog::placeholders( $v ) ) {
				$bad[] = $k; // A wrong number of %s/%d would crash sprintf() on PHP 8.
				continue;
			}
			$cur[ $k ] = $v;
			++$saved;
		}
		$a[ $code ] = array(
			'name'    => '' !== trim( (string) $name ) ? \UserFlo\Core\Text::substr( sanitize_text_field( $name ), 60 ) : ( isset( $a[ $code ]['name'] ) ? $a[ $code ]['name'] : '' ),
			'notes'   => \UserFlo\Core\Text::substr( sanitize_textarea_field( (string) $notes ), 1000 ),
			'strings' => $cur,
		);
		if ( ! $cur && Catalog::has_shipped( $code ) && '' === $a[ $code ]['notes'] ) {
			unset( $a[ $code ] ); // Back to "as shipped".
		} elseif ( ! $cur && 'en_US' === $code && '' === $a[ $code ]['notes'] ) {
			unset( $a[ $code ] );
		}
		self::save_all( $a );
		return array( 'saved' => $saved, 'removed' => $removed, 'rejected' => $bad );
	}

	/** Removes all edits of a shipped language, or deletes a custom language. */
	public static function delete( $code ) {
		$a = self::all();
		if ( ! isset( $a[ $code ] ) ) {
			return false;
		}
		unset( $a[ $code ] );
		self::save_all( $a );
		return true;
	}

	/** Language whose overrides apply right now. */
	public static function active_locale() {
		if ( null === self::$locale ) {
			$l = I18n::locale();
			if ( '' === $l ) {
				$l = (string) determine_locale();
				if ( 0 === strpos( $l, 'en' ) ) {
					$l = 'en_US';
				}
			}
			self::$locale = $l;
		}
		return self::$locale;
	}

	public static function filter( $translation, $text, $domain ) {
		static $busy = false;
		if ( 'userflo' !== $domain || $busy ) {
			return $translation;
		}
		$busy = true; // Resolving the locale reads settings, which may translate strings: never recurse.
		try {
			$a = self::all();
			$l = $a ? self::active_locale() : '';
		} finally {
			$busy = false;
		}
		if ( ! $a ) {
			return $translation;
		}
		return isset( $a[ $l ]['strings'][ $text ] ) && '' !== $a[ $l ]['strings'][ $text ] ? $a[ $l ]['strings'][ $text ] : $translation;
	}

	/** Merges database translations into the admin script JSON (also builds it when no shipped file exists). */
	public static function script_json( $pre, $file, $handle, $domain ) {
		if ( 'userflo' !== $domain || ! in_array( $handle, I18n::HANDLES, true ) ) {
			return $pre;
		}
		$l  = self::active_locale();
		$ov = self::strings( $l );
		if ( ! $ov ) {
			return $pre;
		}
		$f    = I18n::script_file( $file, $handle, $domain ); // The shipped file for the language chosen in settings.
		$json = null !== $pre ? $pre : ( $f && is_readable( $f ) ? file_get_contents( $f ) : '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = $json ? json_decode( $json, true ) : null;
		if ( ! is_array( $data ) || ! isset( $data['locale_data']['messages'] ) ) {
			$data = array( 'domain' => 'messages', 'locale_data' => array( 'messages' => array( '' => array( 'domain' => 'messages', 'lang' => $l, 'plural-forms' => 'nplurals=2; plural=(n != 1);' ) ) ) );
		}
		foreach ( $ov as $k => $v ) {
			$data['locale_data']['messages'][ $k ] = array( $v );
		}
		return wp_json_encode( $data );
	}

	/** Effective translation count for a language. */
	public static function progress( $code ) {
		$total = Catalog::total();
		if ( 'en_US' === $code ) {
			return array( $total, $total );
		}
		$have = Catalog::shipped( $code );
		$ov   = self::strings( $code );
		$done = 0;
		foreach ( Catalog::entries() as $k => $_r ) {
			if ( isset( $have[ $k ] ) || ( isset( $ov[ $k ] ) && '' !== $ov[ $k ] ) ) {
				++$done;
			}
		}
		return array( $done, $total );
	}

	/** Builds a .po file of the effective translations for download. */
	public static function export_po( $code ) {
		$shipped = 'en_US' === $code ? array() : Catalog::shipped( $code );
		$ov      = self::strings( $code );
		$meta    = self::meta( $code );
		$esc     = static function ( $s ) { return str_replace( array( '\\', '"', "\n" ), array( '\\\\', '\\"', '\\n' ), $s ); };
		$out     = "# UserFlo translation ({$code}), exported " . gmdate( 'Y-m-d' ) . "\nmsgid \"\"\nmsgstr \"\"\n\"Project-Id-Version: UserFlo\\n\"\n\"Language: {$code}\\n\"\n\"MIME-Version: 1.0\\n\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Content-Transfer-Encoding: 8bit\\n\"\n\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n\"X-Domain: userflo\\n\"\n\n";
		if ( '' !== $meta['notes'] ) {
			$out = '# ' . str_replace( "\n", "\n# ", $meta['notes'] ) . "\n" . $out;
		}
		foreach ( Catalog::entries() as $k => $refs ) {
			$t = isset( $ov[ $k ] ) && '' !== $ov[ $k ] ? $ov[ $k ] : ( 'en_US' === $code ? '' : ( isset( $shipped[ $k ] ) ? $shipped[ $k ] : '' ) );
			$out .= '#: ' . $refs . "\nmsgid \"" . $esc( $k ) . "\"\nmsgstr \"" . $esc( $t ) . "\"\n\n";
		}
		return $out;
	}
}
