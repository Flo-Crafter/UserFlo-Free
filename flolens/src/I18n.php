<?php
namespace UserFlo;

use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Translations. PHP strings use the `flolens` text domain (languages/flolens-<locale>.mo); admin JavaScript strings
 * use per-script JSON files (languages/flolens-<locale>-<handle>.json). Bundled: Spanish (es_ES), French (fr_FR) and Hungarian (hu_HU).
 * A "Plugin language" setting can force one of them; "Automatic" follows the WordPress / user language
 * (any es_* locale falls back to es_ES, fr_* to fr_FR, hu_* to hu_HU).
 */
final class I18n {

	const BUNDLED = array( 'es_ES' => 'Español', 'fr_FR' => 'Français', 'hu_HU' => 'Magyar' );
	const HANDLES = array( 'userflo-admin-app', 'userflo-tools', 'userflo-heatmap-admin', 'userflo-integrations', 'userflo-settings', 'userflo-translations', 'userflo-mail', 'userflo-setup' );

	/** Locale forced for the duration of with_locale() (used to render reports in another language). */
	private static $override = null;

	public static function register() {
		add_action( 'init', array( __CLASS__, 'load' ) );
		add_filter( 'plugin_locale', array( __CLASS__, 'locale_filter' ), 10, 2 );
		add_filter( 'load_script_translation_file', array( __CLASS__, 'script_file' ), 10, 3 );
	}

	public static function load() {
		// Translations from translate.wordpress.org (wp-content/languages/plugins) win; otherwise use the bundled files.
		// The bundled path is built from USERFLO_DIR because plugin_basename() is unreliable for symlinked plugin folders.
		unload_textdomain( 'flolens' ); // Drops a placeholder registered by an early __() call (older WordPress versions would otherwise ignore the file).
		$locale = (string) apply_filters( 'plugin_locale', determine_locale(), 'flolens' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core constant or hook, not ours.
		if ( ! load_textdomain( 'flolens', WP_LANG_DIR . '/plugins/flolens-' . $locale . '.mo' ) ) {
			load_textdomain( 'flolens', USERFLO_DIR . 'languages/flolens-' . $locale . '.mo' );
		}
	}

	/** Locale to use for FloLens, or '' to leave WordPress's choice alone. */
	public static function locale( $wp_locale = '' ) {
		if ( null !== self::$override ) {
			return self::$override;
		}
		$choice = (string) Settings::get( 'language' );
		if ( isset( self::BUNDLED[ $choice ] ) ) {
			return $choice;
		}
		if ( 'en_US' === $choice ) {
			return 'en_US';
		}
		$custom = \UserFlo\Translations\Store::custom_languages();
		if ( isset( $custom[ $choice ] ) ) {
			return $choice;
		}
		$wp_locale = '' !== $wp_locale ? $wp_locale : determine_locale();
		if ( isset( self::BUNDLED[ $wp_locale ] ) ) {
			return $wp_locale;
		}
		if ( isset( $custom[ $wp_locale ] ) ) {
			return $wp_locale;
		}
		foreach ( array_merge( array_keys( self::BUNDLED ), array_keys( $custom ) ) as $l ) {
			if ( substr( $l, 0, 2 ) === substr( $wp_locale, 0, 2 ) ) {
				return $l;
			}
		}
		return '';
	}

	/** Runs $fn with FloLens's PHP strings in another language, then restores the normal one. */
	public static function with_locale( $code, $fn ) {
		$prev           = self::$override;
		self::$override = (string) $code;
		\UserFlo\Translations\Store::reset();
		self::load();
		try {
			return call_user_func( $fn );
		} finally {
			self::$override = $prev;
			\UserFlo\Translations\Store::reset();
			self::load();
		}
	}

	public static function locale_filter( $locale, $domain ) {
		if ( 'flolens' !== $domain ) {
			return $locale;
		}
		$mine = self::locale( $locale );
		return '' !== $mine ? $mine : $locale;
	}

	public static function script_file( $file, $handle, $domain ) {
		if ( 'flolens' !== $domain ) {
			return $file;
		}
		$mine = self::locale();
		if ( '' === $mine || 'en_US' === $mine ) {
			return 'en_US' === $mine ? false : $file;
		}
		$path = USERFLO_DIR . 'languages/flolens-' . $mine . '-' . $handle . '.json';
		return file_exists( $path ) ? $path : $file;
	}

	/** Registers JSON translations for an admin script handle. */
	public static function script( $handle ) {
		wp_set_script_translations( $handle, 'flolens', USERFLO_DIR . 'languages' );
	}
}
