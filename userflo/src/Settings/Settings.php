<?php
namespace UserFlo\Settings;

defined( 'ABSPATH' ) || exit;

/** Typed settings storage with defaults and sanitisation. */
final class Settings {

	const OPTION = 'userflo_settings';

	/** @var array|null */
	private static $cache = null;

	public static function defaults() {
		return array(
			'enabled'              => true,
			'exclude_roles'        => array(),
			'exclude_paths'        => "/wp-admin\n/wp-login.php",
			'filter_bots'          => true,
			'ai_extra_bots'        => '', // Extra AI crawlers: one "user-agent token = assistant name" per line.
			'ai_extra_sources'     => '', // Extra AI assistant sites sending visitors: one "domain = assistant name" per line.
			'bot_counter'          => true, // Count requests by AI crawlers and other bots (counters only; see Tracking\BotCounter).
			'respect_gpc'          => true,
			'consent_mode'         => 'none', // none | cookie | js | complianz | cookieyes | cookiebot | borlabs.
			'consent_cookie'       => '',
			'ip_mode'              => 'truncate', // off | truncate | hash | full.
			'ip_header'            => 'remote_addr', // remote_addr | x_forwarded_for | cf_connecting_ip | x_real_ip.
			'identity_association' => true,
			'identity_retroactive' => true,
			'site_type'            => 'general',
			'show_identity'        => true,
			'user_link'            => 'new',
			'share_credit'         => false, // "Powered by UserFlo" on shared pages: off unless the owner switches it on.
			'show_guide_button'    => true, // The Guide, Copy for AI and Export CSV buttons at the top of each screen.
			'show_ai_button'       => true,
			'show_export_button'   => true,
			'animations'           => true, // Charts draw in, table rows fade in and map countries grow under the pointer (off: data appears at once). The browser's "reduce motion" choice is always respected.
			'bar_links'            => true, // Quick links in the toolbar menu (and the Pro audit dot).
			'bar_activity'         => true, // The latest visits and interactions in the toolbar menu.
			'history_bar'          => true, // The History menu in the WordPress toolbar for administrators (needs the Live module).
			'inactivity_minutes'   => 30,
			'scroll_thresholds'    => '25,50,75,100',
			'track_clicks'         => true,
			'include_selectors'    => '',
			'exclude_selectors'    => '',
			'download_extensions'  => 'pdf,zip,doc,docx,xls,xlsx,ppt,pptx,csv,mp3,mp4,dmg,exe',
			'retention_days'       => 180,
			'summary_years'        => 3,
			'short_event_days'     => 0, // Days to keep clicks and scroll events; 0 = as long as everything else (the main period). Their daily counts are kept in the summaries either way.
			'heatmaps_enabled'     => false,
			'brand_logo_id'        => 0,
			'geo_mode'             => 'off', // off | country | city.
			'geo_source'           => 'dbip', // dbip (downloaded) | custom (path to an .mmdb file).
			'geo_custom_path'      => '',
			'geo_auto_update'      => true,
			'modules'              => \UserFlo\Modules::defaults(),
			'tracking_mode'        => 'standard', // standard (two first-party cookies) | cookieless (session-only, no cookies, no returning-visitor recognition).
			'woo_statuses'         => array( 'processing', 'completed' ), // WooCommerce order statuses that count as sales (changing them is Pro).
			'modules_nav'          => array(), // Sidebar entry per module; a module that is not listed follows whether it is on.
			'modules_report'       => array(), // Whether reports include a module's data; a module that is not listed is included.
			'language'             => 'auto', // auto | en_US | es_ES | fr_FR | hu_HU.
			'report_language'      => '', // '' = same as the plugin language, otherwise any available language code.
			'ui_style'             => 'default', // default | dark | glass | elegant | fancy | mono | noir.
			'globe_style'          => 'hex', // hex | vector: how land looks on the 3D globe (Pro).
			'heatmap_retention_days' => 90,
			'delete_on_uninstall'  => false,
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
			// A module added after this site saved its settings is on by default; without this a re-save of the whole array would switch it off.
			self::$cache['modules'] = array_merge( \UserFlo\Modules::defaults(), is_array( self::$cache['modules'] ) ? self::$cache['modules'] : array() );
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function reset_cache() {
		self::$cache = null;
	}

	public static function ensure_defaults() {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults() );
		}
		self::reset_cache();
	}

	/** Sanitise a settings array (used as the register_setting callback). */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$d     = self::defaults();
		$out   = array();

		foreach ( array( 'enabled', 'filter_bots', 'bot_counter', 'respect_gpc', 'identity_association', 'identity_retroactive', 'show_identity', 'share_credit', 'history_bar', 'bar_links', 'bar_activity', 'show_guide_button', 'show_ai_button', 'show_export_button', 'animations', 'track_clicks', 'delete_on_uninstall', 'heatmaps_enabled', 'geo_auto_update' ) as $k ) {
			$out[ $k ] = ! empty( $input[ $k ] );
		}

		foreach ( array( 'ai_extra_bots', 'ai_extra_sources' ) as $k ) { // "token = name" lines, trimmed and bounded.
			$lines = array();
			foreach ( preg_split( '/\r\n|\r|\n/', isset( $input[ $k ] ) ? (string) $input[ $k ] : '' ) as $line ) {
				$line = trim( sanitize_text_field( $line ) );
				if ( '' !== $line && preg_match( '/^[A-Za-z0-9._\/\-]{2,60}\s*(=\s*[^=]{1,40})?$/', $line ) ) {
					$lines[] = $line;
				}
			}
			$out[ $k ] = implode( "\n", array_slice( array_unique( $lines ), 0, 40 ) );
		}

		$roles             = isset( $input['exclude_roles'] ) && is_array( $input['exclude_roles'] ) ? $input['exclude_roles'] : array();
		$out['exclude_roles'] = array_values( array_intersect( array_map( 'sanitize_key', $roles ), array_keys( wp_roles()->get_names() ) ) );

		$paths = isset( $input['exclude_paths'] ) ? (string) $input['exclude_paths'] : '';
		$lines = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $paths ) as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			if ( '' !== $line && '/' === $line[0] ) {
				$lines[] = substr( $line, 0, 200 );
			}
		}
		$out['exclude_paths'] = implode( "\n", array_slice( $lines, 0, 50 ) );

		$stored = (array) get_option( self::OPTION, array() );
		foreach ( array( 'include_selectors', 'exclude_selectors' ) as $k ) {
			$out[ $k ] = self::selectors( isset( $input[ $k ] ) ? (string) $input[ $k ] : '' );
		}
		if ( ! \UserFlo\Edition::can( 'advanced_tracking' ) ) {
			// Include selectors are edited in Pro. Without it the saved value is kept exactly as it was (never lost by saving Lite's form).
			$out['include_selectors'] = isset( $stored['include_selectors'] ) ? self::selectors( (string) $stored['include_selectors'] ) : '';
		}

		$out['consent_mode']   = self::choice( $input, 'consent_mode', array( 'none', 'cookie', 'js', 'complianz', 'cookieyes', 'cookiebot', 'borlabs' ), $d['consent_mode'] );
		$out['consent_cookie'] = isset( $input['consent_cookie'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $input['consent_cookie'] ) : '';
		$out['ip_mode']        = self::choice( $input, 'ip_mode', array( 'off', 'truncate', 'hash', 'full' ), $d['ip_mode'] );
		$out['modules']        = \UserFlo\Modules::sanitize( $input );
		$out['tracking_mode']  = \UserFlo\Privacy\Mode::sanitize( $input, $stored );
		$out['woo_statuses']   = \UserFlo\Commerce\Statuses::sanitize( $input, $stored );
		$out['modules_nav']    = \UserFlo\Modules::sanitize_flags( $input, 'modules_nav', $stored );
		$out['modules_report'] = \UserFlo\Modules::sanitize_flags( $input, 'modules_report', $stored );
		$logo_id               = isset( $input['brand_logo_id'] ) ? absint( $input['brand_logo_id'] ) : 0;
		$out['brand_logo_id']  = ( $logo_id && wp_attachment_is_image( $logo_id ) ) ? $logo_id : 0;
		if ( ! \UserFlo\Edition::can( 'branding' ) ) {
			$out['brand_logo_id'] = isset( $stored['brand_logo_id'] ) ? absint( $stored['brand_logo_id'] ) : 0; // Branding is Pro: a saved logo is kept as it was.
		}
		$out['geo_mode']        = self::choice( $input, 'geo_mode', array( 'off', 'country', 'city' ), $d['geo_mode'] );
		if ( 'city' === $out['geo_mode'] && ! \UserFlo\Edition::can( 'map_city' ) ) {
			// City level is Pro: an existing city setting is kept (its data is reused after an upgrade); nobody can newly choose it in Lite.
			$out['geo_mode'] = ( isset( $stored['geo_mode'] ) && 'city' === $stored['geo_mode'] ) ? 'city' : 'country';
		}
		$out['geo_source']      = self::choice( $input, 'geo_source', array( 'dbip', 'custom' ), $d['geo_source'] );
		$out['geo_custom_path'] = isset( $input['geo_custom_path'] ) ? sanitize_text_field( (string) $input['geo_custom_path'] ) : '';
		$out['site_type']       = self::choice( $input, 'site_type', array_keys( \UserFlo\Focus\Profiles::types() ), $d['site_type'] );
		$out['language']       = self::choice( $input, 'language', array_merge( array( 'auto', 'en_US', 'es_ES', 'fr_FR', 'hu_HU' ), array_keys( \UserFlo\Translations\Store::custom_languages() ) ), $d['language'] );
		$rl                    = isset( $input['report_language'] ) ? (string) $input['report_language'] : '';
		$out['report_language'] = ( '' !== $rl && isset( \UserFlo\Translations\Routes::available()[ $rl ] ) ) ? $rl : '';
		if ( ! \UserFlo\Edition::can( 'reports' ) ) {
			$out['report_language'] = isset( $stored['report_language'] ) ? (string) $stored['report_language'] : ''; // Reports are Pro: the saved choice is kept.
		}
		$out['globe_style']    = self::choice( $input, 'globe_style', array( 'hex', 'vector' ), $d['globe_style'] );
		if ( ! \UserFlo\Edition::can( 'map_globe' ) ) {
			$out['globe_style'] = isset( $stored['globe_style'] ) && 'vector' === $stored['globe_style'] ? 'vector' : 'hex'; // The globe is Pro: the saved look is kept.
		}
		$out['user_link']      = self::choice( $input, 'user_link', array( 'off', 'same', 'new' ), $d['user_link'] );
		$out['ui_style']       = self::choice( $input, 'ui_style', array( 'default', 'dark', 'glass', 'elegant', 'fancy', 'mono', 'noir' ), $d['ui_style'] );
		$out['ip_header']      = self::choice( $input, 'ip_header', array( 'remote_addr', 'x_forwarded_for', 'cf_connecting_ip', 'x_real_ip' ), $d['ip_header'] );

		$out['inactivity_minutes'] = self::int_range( $input, 'inactivity_minutes', 5, 240, $d['inactivity_minutes'] );
		$out['retention_days']     = self::int_range( $input, 'retention_days', 0, 3650, $d['retention_days'] );
		$out['short_event_days']   = self::int_range( $input, 'short_event_days', 0, 3650, isset( $stored['short_event_days'] ) ? (int) $stored['short_event_days'] : $d['short_event_days'] );
		$out['summary_years']      = self::int_range( $input, 'summary_years', 0, 10, isset( $stored['summary_years'] ) ? (int) $stored['summary_years'] : $d['summary_years'] );
		$out['heatmap_retention_days'] = self::int_range( $input, 'heatmap_retention_days', 1, 3650, isset( $stored['heatmap_retention_days'] ) ? (int) $stored['heatmap_retention_days'] : $d['heatmap_retention_days'] );
		if ( ! \UserFlo\Edition::can( 'heatmaps' ) ) {
			// Heatmaps are a Pro feature. Their saved switch is kept untouched (and is inert without Pro) so an upgrade restores it.
			$out['heatmaps_enabled'] = ! empty( $stored['heatmaps_enabled'] );
		}

		$th = array();
		foreach ( explode( ',', isset( $input['scroll_thresholds'] ) ? (string) $input['scroll_thresholds'] : '' ) as $n ) {
			$n = (int) trim( $n );
			if ( $n >= 1 && $n <= 100 ) {
				$th[ $n ] = $n;
			}
		}
		ksort( $th );
		$out['scroll_thresholds'] = $th ? implode( ',', array_slice( $th, 0, 10 ) ) : $d['scroll_thresholds'];
		if ( ! \UserFlo\Edition::can( 'advanced_tracking' ) ) {
			// Choosing the scroll-depth steps is Pro. Lite records the saved steps (25, 50, 75, 100 unless set earlier) and never changes them.
			$out['scroll_thresholds'] = isset( $stored['scroll_thresholds'] ) ? (string) $stored['scroll_thresholds'] : $d['scroll_thresholds'];
		}

		$ext = array();
		foreach ( explode( ',', isset( $input['download_extensions'] ) ? strtolower( (string) $input['download_extensions'] ) : '' ) as $e ) {
			$e = preg_replace( '/[^a-z0-9]/', '', $e );
			if ( '' !== $e ) {
				$ext[ $e ] = $e;
			}
		}
		$out['download_extensions'] = implode( ',', array_slice( $ext, 0, 40 ) );

		self::reset_cache();
		return $out;
	}

	/** Comma/newline separated CSS selectors; only simple characters are kept (no quotes, braces or angle brackets). */
	public static function selectors( $raw ) {
		$out = array();
		foreach ( preg_split( '/[\r\n,]+/', $raw ) as $sel ) {
			$sel = trim( $sel );
			if ( '' !== $sel && strlen( $sel ) <= 100 && preg_match( '/^[A-Za-z0-9_\-.#\[\]=*^$~|>+ :()]+$/', $sel ) ) {
				$out[ $sel ] = $sel;
			}
		}
		return implode( ', ', array_slice( $out, 0, 30 ) );
	}

	private static function choice( $input, $key, $allowed, $default ) {
		return isset( $input[ $key ] ) && in_array( $input[ $key ], $allowed, true ) ? $input[ $key ] : $default;
	}

	private static function int_range( $input, $key, $min, $max, $default ) {
		if ( ! isset( $input[ $key ] ) || ! is_numeric( $input[ $key ] ) ) {
			return $default;
		}
		return max( $min, min( $max, (int) $input[ $key ] ) );
	}
}
