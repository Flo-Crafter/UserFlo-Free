<?php
namespace UserFlo;

use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/** Optional parts of UserFlo that can be switched off in Settings. Overview, Visitors, Settings and Tools are always available. */
final class Modules {

	/** key => label. `heatmaps` is backed by the existing heatmaps_enabled setting (it also controls collection). */
	/** Modules that belong to UserFlo Pro, and the edition feature each needs. Lite neither lists nor runs them. */
	const PRO = array(
		'reports'           => 'reports',
		'integrations'      => 'integrations',
		'heatmaps'          => 'heatmaps',
		'scheduled_reports' => 'scheduled_reports',
		'advanced_tracking' => 'advanced_tracking',
		'wp_reports'        => 'wordpress_reports',
		'page_intelligence' => 'page_audit',
		'studio_connection' => 'studio_remote',
		'alerts'            => 'alerts',
		'ai_access'         => 'ai_access',
		'dashboards'        => 'statistics_builder',
	);

	/** Modules the setup wizard does not show: they are switched only in Settings → Components, and the wizard leaves them as they are. */
	const MANAGER_ONLY = array( 'scheduled_reports', 'advanced_tracking', 'wp_reports', 'audit', 'elements', 'page_intelligence', 'studio_connection', 'alerts', 'ai_access', 'dashboards' );

	/** Hard dependencies: a module whose required module is off is not running, whatever its own switch says (the manager says so). */
	const REQUIRES = array( 'wp_reports' => array( 'statistics' ), 'elements' => array( 'audit' ), 'page_intelligence' => array( 'audit' ) );

	/**
	 * Which modules lean on another module's data routes: a module that is on keeps those routes registered even when the module that
	 * owns them is off. (Reports builds on the statistics and map data.) Use `needed()` where routes are registered.
	 */
	const USES = array( 'reports' => array( 'statistics', 'map' ) );

	/**
	 * What each module owns at runtime, declared as data so the lifecycle is readable in one place and testable without loading any
	 * module code: edition feature, REST routes (under /userflo/v1), shortcodes, cron hooks and the hooks it attaches. A module that is
	 * off registers none of these. Collection, privacy, retention, exports, translations, help and settings are core, not modules.
	 * `plugin` says which package registers it (lite|pro).
	 */
	public static function spec() {
		return array(
			'live'         => array( 'plugin' => 'lite', 'routes' => array( '/live', '/history' ) ),
			'pages'        => array( 'plugin' => 'lite', 'routes' => array( '/pages', '/pages/visits' ) ),
			'statistics'   => array( 'plugin' => 'lite', 'routes' => array( '/stats/insights', '/stats/journeys', '/stats/traffic/calendar', '/stats/peaks', '/stats/bots' ) ),
			'achievements' => array( 'plugin' => 'lite', 'routes' => array( '/achievements' ) ),
			'map'          => array( 'plugin' => 'lite', 'routes' => array( '/geo/summary', '/geo/details' ) ),
			'goals'        => array( 'plugin' => 'lite', 'routes' => array( '/goals' ) ),
			'woocommerce'  => array( 'plugin' => 'lite', 'needs' => 'WooCommerce', 'routes' => array( '/commerce' ), 'hooks' => array( 'woocommerce_new_order', 'woocommerce_checkout_order_processed' ) ),
			'audit'        => array( 'plugin' => 'lite', 'routes' => array( '/audit/summary', '/audit/pages', '/audit/scan' ), 'jobs' => array( 'userflo_audit_tick' ) ),
			'elements'     => array( 'plugin' => 'lite', 'requires' => self::REQUIRES['elements'], 'routes' => array( '/elements' ) ),
			'shortcodes'   => array( 'plugin' => 'lite', 'shortcodes' => array( 'userflo_overview', 'userflo_statistics', 'userflo_achievements', 'userflo_map' ) ),
			'sharing'      => array( 'plugin' => 'lite', 'routes' => array( '/shared/(?P<token>[a-f0-9]{32})/(?P<what>overview|insights|live)' ), 'hooks' => array( 'template_redirect', 'rest_pre_serve_request' ) ),
			'reports'      => array( 'plugin' => 'pro', 'feature' => 'reports', 'uses' => self::USES['reports'], 'hooks' => array( 'admin_post_userflo_pdf', 'userflo_tools_cards' ) ),
			'integrations' => array( 'plugin' => 'pro', 'feature' => 'integrations', 'routes' => array( '/integrations' ), 'jobs' => array( 'userflo_integrations_sync' ), 'hooks' => array( 'wp_footer' ) ),
			'dashboards'   => array( 'plugin' => 'pro', 'feature' => 'statistics_builder', 'routes' => array( '/dashboards' ), 'hooks' => array( 'userflo_share_page_scopes' ) ),
			'ai_access'    => array( 'plugin' => 'pro', 'feature' => 'ai_access', 'routes' => array( '/ai-admin/settings', '/ai-admin/summary' ), 'hooks' => array( 'userflo_settings_tabs', 'admin_post_userflo_ai_personas' ) ),
			'alerts'       => array( 'plugin' => 'pro', 'feature' => 'alerts', 'jobs' => array( 'userflo_alerts_tick', 'userflo_alerts_flush' ), 'hooks' => array( 'userflo_ingested', 'userflo_goal_completed', 'userflo_admin_menu' ) ),
			'studio_connection' => array( 'plugin' => 'pro', 'feature' => 'studio_remote', 'hooks' => array( 'rest_api_init', 'userflo_admin_menu_end', 'userflo_settings_sections' ) ),
			'heatmaps'     => array( 'plugin' => 'pro', 'feature' => 'heatmaps', 'routes' => array( '/heat', '/heatmap', '/heatmap/pages' ) ),
			'scheduled_reports' => array( 'plugin' => 'pro', 'feature' => 'scheduled_reports', 'routes' => array( '/mail' ), 'jobs' => array( 'userflo_mail_tick', 'userflo_mail_retry' ), 'hooks' => array( 'userflo_settings_tabs', 'admin_enqueue_scripts' ) ),
			'advanced_tracking' => array( 'plugin' => 'pro', 'feature' => 'advanced_tracking', 'hooks' => array( 'userflo_tracker_config', 'userflo_tracker_enqueued', 'userflo_form_shortcode_attributes' ) ),
			'wp_reports'   => array( 'plugin' => 'pro', 'feature' => 'wordpress_reports', 'requires' => self::REQUIRES['wp_reports'], 'routes' => array( '/stats/wordpress' ) ),
			'page_intelligence' => array( 'plugin' => 'pro', 'feature' => 'page_audit', 'requires' => self::REQUIRES['page_intelligence'], 'routes' => array( '/audit/findings', '/audit/page', '/audit/insights', '/audit/table' ), 'hooks' => array( 'userflo_audit_findings' ) ),
		);
	}

	/** True when the module is on, or another module that is on builds on its data (see USES). */
	public static function needed( $key ) {
		if ( self::on( $key ) ) {
			return true;
		}
		foreach ( self::USES as $user => $uses ) {
			if ( in_array( $key, $uses, true ) && self::on( $user ) ) {
				return true;
			}
		}
		return false;
	}

	public static function all() {
		$all = self::all_known();
		foreach ( self::PRO as $k => $feature ) {
			if ( ! Edition::can( $feature ) ) {
				unset( $all[ $k ] );
			}
		}
		return $all;
	}

	/** Labels of every module (also those a Pro licence would add), for the manager and its dependency notes. */
	public static function all_labels() {
		return self::all_known();
	}

	private static function all_known() {
		return array(
			'live'         => __( 'Live view', 'userflo' ),
			'pages'        => __( 'Pages', 'userflo' ),
			'statistics'   => __( 'Statistics', 'userflo' ),
			'goals'        => __( 'Goals', 'userflo' ),
			'audit'        => __( 'Website audit (page and element inventory)', 'userflo' ),
			'elements'     => __( 'Elements', 'userflo' ),
			'page_intelligence' => __( 'Page Intelligence (audit findings and insights)', 'userflo' ),
			'woocommerce'  => __( 'WooCommerce sales', 'userflo' ),
			'reports'      => __( 'Reports', 'userflo' ),
			'achievements' => __( 'Achievements', 'userflo' ),
			'map'          => __( 'Map (visitor locations)', 'userflo' ),
			'shortcodes'   => __( 'Front-end shortcodes', 'userflo' ),
			'sharing'      => __( 'Sharing and data feeds', 'userflo' ),
			'integrations' => __( 'Integrations', 'userflo' ),
			'heatmaps'     => __( 'Heatmaps (also starts/stops click collection)', 'userflo' ),
			'scheduled_reports' => __( 'Scheduled e-mail reports', 'userflo' ),
			'studio_connection' => __( 'UserFlo Studio connection', 'userflo' ),
			'alerts'       => __( 'Alerts (e-mail and webhook notifications)', 'userflo' ),
			'ai_access'    => __( 'Copy for AI', 'userflo' ),
			'dashboards'   => __( 'Statistics Builder (custom dashboards)', 'userflo' ),
			'advanced_tracking' => __( 'Advanced tracking', 'userflo' ),
			'wp_reports'   => __( 'WordPress reports', 'userflo' ),
		);
	}

	public static function on( $key ) {
		if ( isset( self::PRO[ $key ] ) && ! Edition::can( self::PRO[ $key ] ) ) {
			return false;
		}
		foreach ( isset( self::REQUIRES[ $key ] ) ? self::REQUIRES[ $key ] : array() as $need ) {
			if ( ! self::on( $need ) ) {
				return false;
			}
		}
		return self::switched_on( $key );
	}

	/** The module's own switch, ignoring what it requires. */
	public static function switched_on( $key ) {
		if ( 'woocommerce' === $key && ! \UserFlo\Commerce\Source::active() ) {
			return false; // Without WooCommerce there is nothing to switch on: no hooks, no routes, no queries.
		}
		if ( 'heatmaps' === $key ) {
			return (bool) Settings::get( 'heatmaps_enabled' );
		}
		$m = (array) Settings::get( 'modules' );
		return ! isset( $m[ $key ] ) || ! empty( $m[ $key ] );
	}

	/** Turns a module on or off (the same switch as the Components screen) and lets listeners react through `userflo_module_toggled`. */
	public static function set_switched( $key, $on ) {
		$all = Settings::all();
		if ( 'heatmaps' === $key ) {
			$all['heatmaps_enabled'] = (bool) $on;
		} else {
			$all['modules'][ $key ] = (bool) $on;
		}
		update_option( Settings::OPTION, Settings::sanitize( $all ) );
		Settings::reset_cache();
	}

	/** Detects which modules a settings save switched and announces each: `do_action( 'userflo_module_toggled', $key, $is_on )`. */
	public static function on_settings_saved( $old, $new ) {
		$old = is_array( $old ) ? $old : array();
		$new = is_array( $new ) ? $new : array();
		$was = static function ( $k, $s ) {
			if ( 'heatmaps' === $k ) {
				return ! empty( $s['heatmaps_enabled'] );
			}
			return ! isset( $s['modules'][ $k ] ) || ! empty( $s['modules'][ $k ] );
		};
		foreach ( self::keys() as $k ) {
			$a = $was( $k, $old );
			$b = $was( $k, $new );
			if ( $a !== $b ) {
				do_action( 'userflo_module_toggled', $k, $b );
			}
		}
	}

	/**
	 * Whether the module keeps its sidebar entry. Independent of `on()`: a module can be on but hidden (it keeps collecting and reporting),
	 * or off but visible (its entry then shows a turned-off notice with an enable button). A module never saved with the manager follows
	 * `on()`, which is what earlier versions did.
	 */
	public static function nav( $key ) {
		$m = (array) Settings::get( 'modules_nav' );
		return isset( $m[ $key ] ) ? ! empty( $m[ $key ] ) : self::on( $key );
	}

	/** Whether scheduled and exported reports include the module's data. Default yes; independent of `nav()` and `on()`. */
	public static function in_reports( $key ) {
		$m = (array) Settings::get( 'modules_report' );
		return ! isset( $m[ $key ] ) || ! empty( $m[ $key ] );
	}

	/** Whether the entry points a report section at a module that is currently off (so the report says so instead of showing nothing). */
	public static function reportable( $key ) {
		return self::in_reports( $key ) && self::on( $key );
	}

	/** Keys only (no translated labels): Settings::defaults() runs inside the gettext filter, so it must not call __(). */
	public static function keys() {
		return array( 'live', 'pages', 'statistics', 'goals', 'woocommerce', 'reports', 'achievements', 'map', 'shortcodes', 'sharing', 'integrations', 'heatmaps', 'scheduled_reports', 'advanced_tracking', 'wp_reports', 'audit', 'elements', 'page_intelligence', 'studio_connection', 'alerts', 'ai_access', 'dashboards' );
	}

	public static function defaults() {
		$out = array();
		foreach ( self::keys() as $k ) {
			$out[ $k ] = true;
		}
		unset( $out['heatmaps'] );
		return $out;
	}

	/**
	 * What the manager shows per module. Built only on the Components screen (it translates text). menu: has a sidebar entry; report: has data
	 * that scheduled reports can include; cost: what the module adds while it is on.
	 */
	public static function meta() {
		return array(
			'live'         => array( 'group' => 'behaviour', 'menu' => true, 'report' => false, 'cost' => 'low', 'desc' => __( 'Who is on the site now. Polls while its screen is open.', 'userflo' ) ),
			'pages'        => array( 'group' => 'pages', 'menu' => true, 'report' => true, 'cost' => 'low', 'desc' => __( 'Per-page views, visitors and engagement.', 'userflo' ) ),
			'statistics'   => array( 'group' => 'behaviour', 'menu' => true, 'report' => true, 'cost' => 'medium', 'desc' => __( 'Statistics, Journeys and the traffic calendar.', 'userflo' ) ),
			'goals'        => array( 'group' => 'behaviour', 'menu' => true, 'report' => true, 'cost' => 'low', 'desc' => __( 'Define outcomes (a page reached, an event) and measure completions and conversion rates. Checked as visits are recorded.', 'userflo' ) ),
			'woocommerce'  => array( 'group' => 'reports', 'menu' => false, 'report' => false, 'cost' => 'low', 'desc' => __( 'Orders and revenue from WooCommerce (read from your orders, not from the browser) and, in Pro, sales by campaign. Present only while WooCommerce is active.', 'userflo' ) ),
			'achievements' => array( 'group' => 'behaviour', 'menu' => false, 'report' => false, 'cost' => 'low', 'desc' => __( 'Milestones for your site. Reached from Settings.', 'userflo' ) ),
			'map'          => array( 'group' => 'behaviour', 'menu' => true, 'report' => true, 'cost' => 'medium', 'desc' => __( 'Where visitors come from (needs geolocation). Loads map assets on its screen only.', 'userflo' ) ),
			'shortcodes'   => array( 'group' => 'sharing', 'menu' => false, 'report' => false, 'cost' => 'low', 'desc' => __( 'Show statistics on public pages.', 'userflo' ) ),
			'sharing'      => array( 'group' => 'sharing', 'menu' => false, 'report' => false, 'cost' => 'low', 'desc' => __( 'Expiring share links and CSV/Excel feeds.', 'userflo' ) ),
			'reports'      => array( 'group' => 'reports', 'menu' => true, 'report' => false, 'cost' => 'medium', 'desc' => __( 'PDF and image report builder.', 'userflo' ) ),
			'scheduled_reports' => array( 'group' => 'reports', 'menu' => false, 'report' => false, 'cost' => 'low', 'desc' => __( 'E-mail reports on a schedule. Turning this off stops the schedule; the settings are kept.', 'userflo' ) ),
			'wp_reports'   => array( 'group' => 'reports', 'menu' => false, 'report' => true, 'cost' => 'medium', 'desc' => __( 'Content types, forms, shop and builder reports inside Statistics.', 'userflo' ) ),
			'integrations' => array( 'group' => 'integrations', 'menu' => false, 'report' => false, 'cost' => 'medium', 'desc' => __( 'GA4, Clarity and PostHog. Adds their tags only when you enable them, and a twice-daily import.', 'userflo' ) ),
			'advanced_tracking' => array( 'group' => 'integrations', 'menu' => false, 'report' => false, 'cost' => 'low', 'desc' => __( 'Selector rules, event properties, form detection and the Google Tag Manager data layer. Turning this off removes those from your pages; recorded events are kept.', 'userflo' ) ),
			'audit'        => array( 'group' => 'pages', 'menu' => false, 'report' => false, 'cost' => 'medium', 'desc' => __( 'Reads your own pages a few at a time (as a visitor would see them) to list the pages and elements on your site and, in Pro, to find problems. Used by Pages and Elements. Turning this off stops the scanning; what was found is kept.', 'userflo' ) ),
			'elements'     => array( 'group' => 'pages', 'menu' => true, 'report' => false, 'cost' => 'low', 'desc' => __( 'Buttons, links, forms, images and files on your pages, with how visitors use them. Needs the Website audit.', 'userflo' ) ),
			'page_intelligence' => array( 'group' => 'pages', 'menu' => true, 'report' => false, 'cost' => 'low', 'desc' => __( 'Audit findings per page, a health indicator on Pages and a prioritised list of pages that need attention. Needs the Website audit.', 'userflo' ) ),
			'dashboards'   => array( 'group' => 'reports', 'menu' => false, 'report' => false, 'cost' => 'low', 'desc' => __( 'Build several named dashboards from a widget library, switch between them in Statistics and share each one on its own. Turning this off brings back the standard Statistics page and stops shared dashboards; your dashboards are kept.', 'userflo' ) ),
			'ai_access'    => array( 'group' => 'integrations', 'menu' => false, 'report' => false, 'cost' => 'low', 'desc' => __( 'Adds a “Copy for AI” button to the screens. It builds one block of counters-only text, with an AI persona and the data that suits the screen, that you paste into any AI chat (ChatGPT, Claude, Gemini and others). Nothing is sent anywhere. The personas are in Settings → AI Personas.', 'userflo' ) ),
			'alerts'       => array( 'group' => 'integrations', 'menu' => true, 'report' => false, 'cost' => 'low', 'desc' => __( 'Notify you by e-mail or webhook when a milestone is reached, something happens (a form is sent, a file is downloaded, a goal is completed) or a day’s visitors fall below a number you set. Hiding it from the sidebar keeps the rules running; turning it off stops all alert work and keeps your rules.', 'userflo' ) ),
			'studio_connection' => array( 'group' => 'integrations', 'menu' => false, 'report' => false, 'cost' => 'low', 'desc' => __( 'Lets a central UserFlo Studio site you pair with read summary figures from this site (no visitor data, read only). Turning this off stops access at once; connections are kept.', 'userflo' ) ),
			'heatmaps'     => array( 'group' => 'behaviour', 'menu' => true, 'report' => false, 'cost' => 'high', 'desc' => __( 'Click and scroll heatmaps. Starts and stops click collection and loads an extra script.', 'userflo' ) ),
		);
	}

	public static function groups() {
		return array(
			'behaviour'    => __( 'Behaviour', 'userflo' ),
			'pages'        => __( 'Pages', 'userflo' ),
			'reports'      => __( 'Reports', 'userflo' ),
			'integrations' => __( 'Integrations and tracking', 'userflo' ),
			'sharing'      => __( 'Sharing', 'userflo' ),
		);
	}

	/** Modules that depend on `$key` and would stop running if it were off (for the manager's warning). */
	public static function dependants( $key ) {
		$out = array();
		foreach ( self::REQUIRES as $m => $needs ) {
			if ( in_array( $key, $needs, true ) ) {
				$out[] = $m;
			}
		}
		return $out;
	}

	/**
	 * Sidebar and report flags from the Components form. A key that is not posted at all (the setup wizard, Advanced tracking) keeps what is
	 * stored; the form always posts a `__form` marker so unticking every box is still a deliberate choice.
	 *
	 * @param array  $input Raw settings input.
	 * @param string $field modules_nav|modules_report.
	 * @param array  $stored Previously saved settings.
	 */
	public static function sanitize_flags( $input, $field, array $stored ) {
		$old = isset( $stored[ $field ] ) && is_array( $stored[ $field ] ) ? $stored[ $field ] : array();
		$in  = isset( $input[ $field ] ) && is_array( $input[ $field ] ) ? $input[ $field ] : null;
		if ( null === $in ) {
			return $old;
		}
		$posted = isset( $in['__form'] );
		$out    = array();
		foreach ( self::meta() as $k => $m ) {
			$applies = 'modules_nav' === $field ? $m['menu'] : $m['report'];
			if ( ! $applies ) {
				continue;
			}
			if ( ( isset( self::PRO[ $k ] ) && ! Edition::can( self::PRO[ $k ] ) ) || ! $posted ) {
				if ( isset( $old[ $k ] ) ) {
					$out[ $k ] = ! empty( $old[ $k ] ); // Not editable here: keep what was saved.
				}
				continue;
			}
			$out[ $k ] = ! empty( $in[ $k ] );
		}
		return $out;
	}

	public static function sanitize( $input ) {
		$out = array();
		$in  = isset( $input['modules'] ) && is_array( $input['modules'] ) ? $input['modules'] : array();
		$old = (array) get_option( Settings::OPTION, array() );
		$old = isset( $old['modules'] ) && is_array( $old['modules'] ) ? $old['modules'] : array();
		foreach ( array_keys( self::defaults() ) as $k ) {
			if ( 'woocommerce' === $k && ! \UserFlo\Commerce\Source::active() ) {
				$out[ $k ] = ! isset( $old[ $k ] ) || ! empty( $old[ $k ] ); // WooCommerce is not active: the choice is kept for when it is.
				continue;
			}
			if ( isset( self::PRO[ $k ] ) && ! Edition::can( self::PRO[ $k ] ) ) {
				$out[ $k ] = ! isset( $old[ $k ] ) || ! empty( $old[ $k ] ); // Not editable in Lite: keep what was saved for when Pro is added.
				continue;
			}
			$out[ $k ] = ! empty( $in[ $k ] );
		}
		return $out;
	}
}
