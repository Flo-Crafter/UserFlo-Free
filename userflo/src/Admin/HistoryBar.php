<?php
namespace UserFlo\Admin;

use UserFlo\Audit\Findings;
use UserFlo\Edition;
use UserFlo\Modules;
use UserFlo\Plugin;
use UserFlo\Settings\Settings;
use UserFlo\Tracking\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * A "History" menu in the WordPress toolbar on the site's own pages, for people who may see the analytics: the latest visits and
 * interactions of the last 7 days across the site or on the page being looked at, with links to the Pages, Elements and Visitors screens.
 * Nothing is loaded until the menu is opened; it reads through the same REST route and capability as the Live view.
 */
final class HistoryBar {

	const HANDLE = 'userflo-history';

	public static function register() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'node' ), 90 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/** True when the menu belongs on this request. */
	public static function active() {
		return ! is_admin() && is_admin_bar_showing() && Settings::get( 'history_bar' ) && ( Settings::get( 'bar_links' ) || Settings::get( 'bar_activity' ) ) && Modules::on( 'live' ) && current_user_can( Plugin::cap() ) && ! isset( $_GET['userflo_hm'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** The tracked path of the page being looked at ('' when it has none, such as a search or 404 page). */
	private static function path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Validated by Validator::path().
		$path = Validator::path( (string) $uri );
		return null === $path ? '' : $path;
	}

	/** The audit health of this page for the Pro dot: array( class, text ) or null when the page audit is not available or the page has no result. */
	private static function health( $path ) {
		if ( '' === $path || ! Edition::can( 'page_audit' ) || ! Modules::on( 'audit' ) ) {
			return null;
		}
		$all = Findings::for_urls( array( $path ) );
		$h   = isset( $all[ $path ] ) ? $all[ $path ] : null;
		if ( ! $h || $h['level'] < 0 ) {
			return array( 'none', __( 'Not scanned', 'userflo' ) );
		}
		return 2 === (int) $h['level'] ? array( 'red', __( 'Issues', 'userflo' ) ) : ( 1 === (int) $h['level'] ? array( 'amber', __( 'Warnings', 'userflo' ) ) : array( 'green', __( 'No issues', 'userflo' ) ) );
	}

	private static function dot( $health ) {
		return $health ? '<span class="uf-hb-dot uf-hb-dot-' . esc_attr( $health[0] ) . '" title="' . esc_attr( $health[1] ) . '" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $health[1] ) . '</span>' : '';
	}

	/** The sentence for one history item, the same words the menu's script uses. */
	private static function what( array $it ) {
		if ( 'pageview' === $it['kind'] ) {
			/* translators: %s: page address */
			return sprintf( __( 'Viewed %s', 'userflo' ), '' !== $it['title'] ? $it['title'] : $it['url'] );
		}
		$n = '' !== $it['name'] ? $it['name'] : ( '' !== $it['target'] ? $it['target'] : $it['type'] );
		$q = '“' . mb_substr( (string) $n, 0, 60 ) . '”';
		switch ( $it['type'] ) {
			case 'click':
				/* translators: %s: what was clicked */
				return sprintf( __( 'Clicked %s', 'userflo' ), $q );
			case 'outbound':
				/* translators: %s: link address */
				return sprintf( __( 'Followed outbound link %s', 'userflo' ), $q );
			case 'download':
				/* translators: %s: file name */
				return sprintf( __( 'Downloaded %s', 'userflo' ), $q );
			case 'form':
				/* translators: %s: form name */
				return sprintf( __( 'Sent form %s', 'userflo' ), $q );
			case 'custom':
				/* translators: %s: event name */
				return sprintf( __( 'Event %s', 'userflo' ), $q );
			default:
				/* translators: %s: kind of interaction */
				return sprintf( __( 'Interaction: %s', 'userflo' ), (string) $it['type'] );
		}
	}

	/**
	 * The latest ten visits and interactions for a scope (the whole site, or one page path), written into the page by the server. It is what
	 * the menu shows when its script cannot run (blocked, delayed or combined by an optimisation plugin); when the script does run it
	 * replaces this with the full, live panel. Only for people who may see the analytics, and only on pages where the menu is shown.
	 */
	private static function pane_html( $url ) {
		$d    = \UserFlo\Rest\LiveRoutes::history_data( $url, 10 );
		$html = '<p class="uf-hb-intro">' . esc_html__( 'Events from the last 7 days', 'userflo' ) . '</p>';
		$t    = $d['totals'];
		$html .= '<p class="uf-hb-totals">' . esc_html( sprintf( /* translators: 1: page views, 2: interactions, 3: visitors */ __( '%1$s views · %2$s interactions · %3$s visitors', 'userflo' ), number_format_i18n( (int) $t['pageviews'] ), number_format_i18n( (int) $t['interactions'] ), number_format_i18n( (int) $t['visitors'] ) ) ) . '</p>';
		if ( ! $d['items'] ) {
			return $html . '<p class="uf-hb-msg">' . esc_html__( 'Nothing recorded yet.', 'userflo' ) . '</p>';
		}
		$html .= '<ul class="uf-hb-list">';
		foreach ( $d['items'] as $it ) {
			$ts    = strtotime( $it['at'] );
			$ago   = $ts ? sprintf( /* translators: %s: time span such as "5 minutes" */ __( '%s ago', 'userflo' ), human_time_diff( $ts ) ) : '';
			$who   = '<a class="uf-hb-name" href="' . esc_url( admin_url( 'admin.php?page=userflo-visitors#/visitor/' . (int) $it['visitor_id'] . '/session/' . (int) $it['session_id'] ) ) . '">' . esc_html( $it['label'] ) . '</a><span class="uf-hb-time"> · ' . esc_html( $ago ) . '</span>';
			$what  = esc_html( self::what( $it ) );
			$what .= '' !== $it['url'] && '' === $url ? '<span class="uf-hb-url"> ' . esc_html( mb_substr( $it['url'], 0, 40 ) ) . '</span>' : '';
			$html .= '<li class="uf-hb-item"><div class="uf-hb-who">' . $who . '</div><div class="uf-hb-what">' . $what . '</div></li>';
		}
		return $html . '</ul>';
	}

	/** The tabs and both lists, switched by radio buttons so they work without a script. The "This page" tab exists only for a page that can be tracked. */
	private static function body_html( $path ) {
		$tabs  = '<input type="radio" name="uf-hb-t" id="uf-hb-t-all" class="uf-hb-radio" checked>';
		$tabs .= '' !== $path ? '<input type="radio" name="uf-hb-t" id="uf-hb-t-page" class="uf-hb-radio">' : '';
		$bar   = '<div class="uf-hb-tabs"><label class="uf-hb-tab" for="uf-hb-t-all">' . esc_html__( 'All history', 'userflo' ) . '</label>' . ( '' !== $path ? '<label class="uf-hb-tab" for="uf-hb-t-page">' . esc_html__( 'This page', 'userflo' ) . '</label>' : '' ) . '</div>';
		$panes = '<div class="uf-hb-pane uf-hb-pane-all">' . self::pane_html( '' ) . '</div>' . ( '' !== $path ? '<div class="uf-hb-pane uf-hb-pane-page">' . self::pane_html( $path ) . '</div>' : '' );
		return '<div class="uf-hb-body">' . $tabs . $bar . $panes . '</div>';
	}

	/** The quick links, short words in one row: list of array( id, label, url, dot class or '' ). */
	public static function quick_links( $path, $health ) {
		$pages = admin_url( 'admin.php?page=userflo-pages' );
		$rows  = array(
			array( 'live', __( 'Live', 'userflo' ), admin_url( 'admin.php?page=userflo-live' ), true ),
			array( 'map', __( 'Map', 'userflo' ), admin_url( 'admin.php?page=userflo-map' ), Modules::on( 'map' ) ),
			array( 'visitors', __( 'Visitors', 'userflo' ), admin_url( 'admin.php?page=userflo-visitors' ), true ),
			array( 'page', __( 'Page info', 'userflo' ), $pages . '#/p/' . rawurlencode( $path ), '' !== $path && Modules::on( 'pages' ) ),
			array( 'elements', __( 'Elements', 'userflo' ), add_query_arg( 'for', rawurlencode( $path ), admin_url( 'admin.php?page=userflo-elements' ) ), '' !== $path && Modules::on( 'elements' ) ),
			array( 'audit', __( 'Audit', 'userflo' ), $pages . '#/p/' . rawurlencode( $path ), null !== $health && Modules::on( 'pages' ) ),
		);
		$out   = array();
		foreach ( $rows as $r ) {
			if ( $r[3] ) {
				$out[] = array( 'id' => $r[0], 'label' => $r[1], 'url' => $r[2], 'dot' => 'audit' === $r[0] && $health ? $health[0] : '', 'dot_text' => 'audit' === $r[0] && $health ? $health[1] : '' );
			}
		}
		return $out;
	}

	public static function node( $bar ) {
		if ( ! self::active() ) {
			return;
		}
		$path   = self::path();
		$links_on = (bool) Settings::get( 'bar_links' );
		$health   = $links_on ? self::health( $path ) : null;
		$bar->add_node(
			array(
				'id'     => 'userflo-history',
				'parent' => 'top-secondary',
				'title'  => '<span class="ab-icon dashicons dashicons-chart-area" aria-hidden="true"></span><span class="ab-label">UserFlo' . self::dot( $health ) . '</span>',
				'href'   => admin_url( 'admin.php?page=userflo' ),
				'meta'   => array( 'class' => 'userflo-history', 'title' => __( 'UserFlo: quick links and the latest visits', 'userflo' ) ),
			)
		);
		// One compact panel: tabs with the latest activity (scrolls when long) and one row of quick links. Plain HTML written by the server.
		$html = '';
		if ( Settings::get( 'bar_activity' ) ) {
			$html .= self::body_html( $path );
		}
		if ( $links_on ) {
			$left = '';
			foreach ( self::quick_links( $path, $health ) as $l ) {
				$left .= '<a class="uf-hb-link" href="' . esc_url( $l['url'] ) . '">' . esc_html( $l['label'] ) . ( '' !== $l['dot'] ? ' ' . self::dot( array( $l['dot'], $l['dot_text'] ) ) : '' ) . '</a>';
			}
			$html .= '<div class="uf-hb-foot"><span class="uf-hb-links">' . $left . '</span></div>';
		}
		$bar->add_node(
			array(
				'id'     => 'userflo-history-panel',
				'parent' => 'userflo-history',
				'title'  => '<div id="uf-hb-panel" class="uf-hb-panel">' . $html . '</div>',
				'meta'   => array( 'class' => 'userflo-history-panel' ),
			)
		);
	}

	public static function assets() {
		if ( ! self::active() ) {
			return;
		}
		$dir = USERFLO_DIR . 'assets/admin/history.js';
		$css = USERFLO_DIR . 'assets/css/history.css';
		if ( ! file_exists( $dir ) || ! file_exists( $css ) ) {
			return;
		}
		wp_enqueue_style( self::HANDLE, USERFLO_URL . 'assets/css/history.css', array(), USERFLO_VERSION . '-' . filemtime( $css ) );
		if ( ! Settings::get( 'bar_activity' ) ) {
			return; // Only the quick links are shown: no script is needed.
		}
		wp_enqueue_script( self::HANDLE, USERFLO_URL . 'assets/admin/history.js', array(), USERFLO_VERSION . '-' . filemtime( $dir ), true );
		$path = self::path();
		wp_localize_script(
			self::HANDLE,
			'userfloHistory',
			array(
				'rest'  => esc_url_raw( rest_url( 'userflo/v1/history' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'page'  => $path,
				'links' => (bool) Settings::get( 'bar_links' ) ? self::quick_links( $path, self::health( $path ) ) : array(),
				'urls'  => array(
					'visitors' => admin_url( 'admin.php?page=userflo-visitors' ),
					'pages'    => Modules::on( 'pages' ) ? admin_url( 'admin.php?page=userflo-pages' ) : '',
					'elements' => Modules::on( 'elements' ) ? admin_url( 'admin.php?page=userflo-elements' ) : '',
					'live'     => admin_url( 'admin.php?page=userflo-live' ),
					'settings' => admin_url( 'admin.php?page=userflo-settings' ),
				),
				'lang'  => str_replace( '_', '-', get_user_locale() ),
				'i18n'  => array(
					'all'        => __( 'All history', 'userflo' ),
					'thisPage'   => __( 'This page', 'userflo' ),
					'intro'      => __( 'Events from the last 7 days', 'userflo' ),
					'loading'    => __( 'Loading…', 'userflo' ),
					'empty'      => __( 'Nothing recorded yet.', 'userflo' ),
					'failed'     => __( 'The history could not be loaded.', 'userflo' ),
					'noPage'     => __( 'This address cannot be tracked, so it has no history.', 'userflo' ),
					/* translators: %s: page address */
					'viewed'     => __( 'Viewed %s', 'userflo' ),
					/* translators: %s: what was clicked */
					'clicked'    => __( 'Clicked %s', 'userflo' ),
					/* translators: %s: link address */
					'outbound'   => __( 'Followed outbound link %s', 'userflo' ),
					/* translators: %s: file name */
					'download'   => __( 'Downloaded %s', 'userflo' ),
					/* translators: %s: form name */
					'form'       => __( 'Sent form %s', 'userflo' ),
					/* translators: %s: event name */
					'custom'     => __( 'Event %s', 'userflo' ),
					/* translators: %s: kind of interaction */
					'other'      => __( 'Interaction: %s', 'userflo' ),
					/* translators: 1: page views, 2: interactions, 3: visitors */
					'totals'     => __( '%1$s views · %2$s interactions · %3$s visitors', 'userflo' ),
					/* translators: 1: AI crawler requests, 2: search engine requests, 3: other bot requests */
					'bots'       => __( 'Bots: %1$s AI crawler · %2$s search engine · %3$s other', 'userflo' ),
					'reload'     => __( 'Reload', 'userflo' ),
					'justNow'    => __( 'just now', 'userflo' ),
				),
			)
		);
	}
}
