<?php
namespace UserFlo\Admin;

use UserFlo\Modules;
use UserFlo\Plugin;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( SettingsPage::class, 'register' ) );
		SetupWizard::register();
		ModulePage::register();
		CommunityTabs::register();
		add_action( 'admin_post_userflo_download', array( ToolsPage::class, 'handle_download' ) );
		add_action( 'admin_enqueue_scripts', array( ToolsPage::class, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( SettingsPage::class, 'assets' ) );
		add_action( 'admin_post_userflo_delete_visitor', array( ToolsPage::class, 'handle_delete_visitor' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'parent_file', array( __CLASS__, 'highlight_parent' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'highlight_sub' ) );
		add_action( 'admin_head', array( __CLASS__, 'focus_early' ) );
		add_action( 'admin_enqueue_scripts', array( AppPage::class, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( USERFLO_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/** Parent for screens that are reached from Settings and have no entry of their own in the sidebar (same trick as the setup wizard). */
	const HIDDEN = 'options.php';

	/** Slugs of screens that are on but hidden from the sidebar (they stay reachable by link and from the Components screen). */
	private static $hidden = array();

	/**
	 * Adds a module's screen according to its two independent switches. On and shown: a normal sidebar entry. On and hidden: the same screen,
	 * reachable by its address and by links, but not in the sidebar. Off but shown: the entry stays and opens a short "turned off" notice with
	 * an enable button, so nothing in the sidebar silently disappears. Off and hidden: nothing.
	 *
	 * @param string   $module   Module key.
	 * @param string   $title    Menu title.
	 * @param string   $cap      Capability.
	 * @param string   $slug     Page slug.
	 * @param callable $callback Renders the screen.
	 */
	public static function entry( $module, $title, $cap, $slug, $callback ) {
		if ( isset( Modules::PRO[ $module ] ) && ! \UserFlo\Edition::can( Modules::PRO[ $module ] ) ) {
			return; // Not licensed: no entry, as before.
		}
		$on  = Modules::on( $module );
		$nav = Modules::nav( $module );
		if ( $on && $nav ) {
			add_submenu_page( 'userflo', $title, $title, $cap, $slug, $callback );
		} elseif ( $on ) {
			self::$hidden[ $slug ] = true;
			add_submenu_page( self::HIDDEN, $title, $title, $cap, $slug, $callback );
		} elseif ( $nav && Modules::switched_on( $module ) === false ) {
			add_submenu_page( 'userflo', $title, $title . ' <span class="uf-menu-off" aria-label="' . esc_attr__( 'turned off', 'userflo' ) . '">·</span>', $cap, $slug, function () use ( $module, $title ) {
				ModulePage::render( $module, $title );
			} );
		}
	}

	public static function menu() {
		$cap = Plugin::cap();
		add_menu_page( 'UserFlo', 'UserFlo', $cap, 'userflo', array( AppPage::class, 'overview' ), \UserFlo\Brand::menu_icon(), 58 );
		// Sidebar order: Overview, Visitors, Journeys, Statistics, Map, Live, Pages, Calendar, then the Pro add-ons (Heatmaps, Reports), then Settings.
		add_submenu_page( 'userflo', __( 'Overview', 'userflo' ), __( 'Overview', 'userflo' ), $cap, 'userflo', array( AppPage::class, 'overview' ) );
		add_submenu_page( 'userflo', __( 'Visitors', 'userflo' ), __( 'Visitors', 'userflo' ), $cap, 'userflo-visitors', array( AppPage::class, 'visitors' ) );
		if ( Modules::on( 'statistics' ) ) { // Journeys and the Calendar belong to Statistics and follow its switches.
			self::entry( 'statistics', __( 'Journeys', 'userflo' ), $cap, 'userflo-journeys', array( AppPage::class, 'journeys' ) );
		}
		self::entry( 'statistics', __( 'Statistics', 'userflo' ), $cap, 'userflo-stats', array( AppPage::class, 'stats' ) );
		self::entry( 'map', __( 'Map', 'userflo' ), $cap, 'userflo-map', array( AppPage::class, 'map' ) );
		self::entry( 'live', __( 'Live', 'userflo' ), $cap, 'userflo-live', array( AppPage::class, 'live' ) );
		self::entry( 'pages', __( 'Pages', 'userflo' ), $cap, 'userflo-pages', array( AppPage::class, 'pages' ) );
		if ( Modules::on( 'statistics' ) ) {
			self::entry( 'statistics', __( 'Calendar', 'userflo' ), $cap, 'userflo-calendar', array( AppPage::class, 'calendar' ) );
		}
		self::entry( 'goals', __( 'Goals', 'userflo' ), $cap, 'userflo-goals', array( AppPage::class, 'goals' ) );
		self::entry( 'elements', __( 'Elements', 'userflo' ), $cap, 'userflo-elements', array( AppPage::class, 'elements' ) );
		/** Add-ons add their analytics screens (Heatmaps, Reports) here. */
		do_action( 'userflo_admin_menu', $cap );
		add_submenu_page( 'userflo', __( 'Settings', 'userflo' ), __( 'Settings', 'userflo' ), 'manage_options', 'userflo-settings', array( SettingsPage::class, 'render' ) );
		// Reached from Settings (its section list and the strip at the top of each of these screens), not from the sidebar.
		if ( Modules::on( 'achievements' ) ) {
			add_submenu_page( self::HIDDEN, __( 'Achievements', 'userflo' ), __( 'Achievements', 'userflo' ), $cap, 'userflo-achievements', array( AppPage::class, 'achievements' ) );
		}
		if ( Modules::on( 'sharing' ) ) {
			add_submenu_page( self::HIDDEN, __( 'Sharing', 'userflo' ), __( 'Sharing', 'userflo' ), 'manage_options', 'userflo-sharing', array( SharingPage::class, 'render' ) );
		}
		add_submenu_page( self::HIDDEN, __( 'Exclusions', 'userflo' ), __( 'Exclusions', 'userflo' ), 'manage_options', 'userflo-exclusions', array( AppPage::class, 'exclusions' ) );
		do_action( 'userflo_admin_menu_end', $cap );
		add_submenu_page( self::HIDDEN, __( 'Tools', 'userflo' ), __( 'Tools', 'userflo' ), $cap, 'userflo-tools', array( ToolsPage::class, 'render' ) );
	}

	/**
	 * Screens that live under Settings: slug => array( label, dashicon, capability ), in display order. Add-ons add theirs with the
	 * `userflo_settings_sections` filter (UserFlo Pro adds Integrations and Licence).
	 */
	public static function settings_sections() {
		$cap = Plugin::cap();
		$all = array();
		if ( Modules::on( 'achievements' ) ) {
			$all['userflo-achievements'] = array( __( 'Achievements', 'userflo' ), 'dashicons-awards', $cap );
		}
		if ( Modules::on( 'sharing' ) ) {
			$all['userflo-sharing'] = array( __( 'Sharing', 'userflo' ), 'dashicons-share', 'manage_options' );
		}
		$all['userflo-exclusions'] = array( __( 'Exclusions', 'userflo' ), 'dashicons-hidden', 'manage_options' );
		$all['userflo-tools'] = array( __( 'Tools', 'userflo' ), 'dashicons-admin-tools', $cap );
		$all = (array) apply_filters( 'userflo_settings_sections', $all );
		$out = array();
		foreach ( $all as $slug => $s ) {
			if ( current_user_can( $s[2] ) ) {
				$out[ $slug ] = $s;
			}
		}
		return $out;
	}

	/** True on Settings itself and on the screens listed under it. */
	public static function in_settings( $page ) {
		return 'userflo-settings' === $page || isset( self::settings_sections()[ $page ] );
	}

	/** Keeps "UserFlo → Settings" highlighted in the sidebar while one of its sections is open. */
	public static function highlight_parent( $parent ) {
		global $plugin_page;
		return ( $plugin_page && ( isset( self::$hidden[ $plugin_page ] ) || ( 'userflo-settings' !== $plugin_page && self::in_settings( $plugin_page ) ) ) ) ? 'userflo' : $parent;
	}

	public static function highlight_sub( $sub ) {
		global $plugin_page;
		if ( $plugin_page && isset( self::$hidden[ $plugin_page ] ) ) {
			return 'userflo'; // A screen that is on but not in the sidebar highlights Overview.
		}
		return ( $plugin_page && 'userflo-settings' !== $plugin_page && self::in_settings( $plugin_page ) ) ? 'userflo-settings' : $sub;
	}

	public static function assets( $hook ) {
		if ( false !== strpos( (string) $hook, 'userflo' ) ) {
			wp_enqueue_style( 'userflo-admin', USERFLO_URL . 'assets/css/admin.css', array(), USERFLO_VERSION );
			wp_enqueue_style( 'userflo-themes', USERFLO_URL . 'assets/css/themes.css', array( 'userflo-admin' ), USERFLO_VERSION );
			wp_enqueue_script( 'userflo-focus', USERFLO_URL . 'assets/admin/focus.js', array(), USERFLO_VERSION, true );
			wp_enqueue_script( 'userflo-toast', USERFLO_URL . 'assets/admin/toast.js', array(), USERFLO_VERSION, true );
		}
	}

	/** Sets the distraction-free class before the page paints (no flash of the WordPress menu). */
	public static function focus_early() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, 'userflo' ) ) {
			echo "<script>try{if(localStorage.getItem('userflo_focus')==='1'){document.documentElement.classList.add('uf-focus');}}catch(e){}</script>\n";
		}
	}

	/** Adds the chosen UserFlo style to the body class on UserFlo screens only. */
	public static function body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( (string) $screen->id, 'userflo' ) ) {
			$classes .= ' uf-screen';
			$style    = (string) Settings::get( 'ui_style' );
			if ( in_array( $style, array( 'dark', 'glass', 'elegant', 'fancy', 'mono', 'noir' ), true ) ) {
				$classes .= ' uf-style-' . $style;
			}
		}
		return $classes;
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=userflo-settings' ) ) . '">' . esc_html__( 'Settings', 'userflo' ) . '</a>' );
		return $links;
	}
}
