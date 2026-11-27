<?php
namespace UserFlo;

use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/** FloLens's own mark (always shown) and the optional company logo set in Settings. */
final class Brand {

	public static function icon_url() {
		return USERFLO_URL . 'assets/img/userflo-icon.svg';
	}

	/** Data URI for the admin menu icon (a monochrome glyph; the coloured mark is used inside FloLens's own screens). */
	public static function menu_icon() {
		// A single-colour glyph: WordPress paints menu icons to match the admin colour scheme, so the full-colour mark (with its gradient
		// background) would come out as a plain white block.
		$svg = file_get_contents( USERFLO_DIR . 'assets/img/userflo-menu-icon.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return $svg ? 'data:image/svg+xml;base64,' . base64_encode( $svg ) : 'dashicons-chart-area'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/** Company logo URL or ''. FloLens Pro supplies it (`userflo_company_logo`); a logo saved earlier stays stored and returns with Pro. */
	public static function company_logo() {
		return (string) apply_filters( 'userflo_company_logo', '' );
	}

	/** FloLens's own menu entries (label, url, current) for the horizontal menu of distraction-free mode. */
	public static function nav_items() {
		global $submenu, $plugin_page;
		$out = array();
		foreach ( isset( $submenu['userflo'] ) ? (array) $submenu['userflo'] : array() as $item ) {
			if ( ! current_user_can( $item[1] ) ) {
				continue;
			}
			$out[] = array( 'label' => wp_strip_all_tags( $item[0] ), 'url' => admin_url( 'admin.php?page=' . $item[2] ), 'current' => $item[2] === $plugin_page || ( 'userflo-settings' === $item[2] && \UserFlo\Admin\Menu::in_settings( (string) $plugin_page ) ) );
		}
		return $out;
	}

	/** Header strip for FloLens admin screens: FloLens mark on the left, company logo on the right, and the distraction-free switch. */
	public static function bar() {
		$logo = self::company_logo();
		echo '<div class="uf-brandbar"><span class="uf-brand"><img src="' . esc_url( self::icon_url() ) . '" width="28" height="28" alt=""> <strong>FloLens</strong></span>';
		echo '<nav class="uf-topnav" aria-label="' . esc_attr__( 'FloLens menu', 'flolens' ) . '">';
		foreach ( self::nav_items() as $i ) {
			echo '<a href="' . esc_url( $i['url'] ) . '"' . ( $i['current'] ? ' aria-current="page"' : '' ) . '>' . esc_html( $i['label'] ) . '</a>';
		}
		echo '</nav><span class="uf-brandend">';
		if ( ! \UserFlo\Edition::is_pro() ) {
			// Lite: one quiet link, no licence field (Pro is a separate plugin with its own licence screen).
			echo '<a class="uf-upgrade-link" href="' . esc_url( \UserFlo\Edition::upgrade_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Upgrade to Pro', 'flolens' ) . '</a>';
		}
		if ( $logo ) {
			echo '<img class="uf-company-logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
		}
		echo '<span id="uf-brand-export" class="uf-brand-export"></span>';
		echo '<button type="button" class="uf-focus-btn" id="uf-focus-btn" aria-pressed="false" data-on="' . esc_attr__( 'Back to the normal view', 'flolens' ) . '" data-off="' . esc_attr__( 'Distraction-free view', 'flolens' ) . '" title="' . esc_attr__( 'Distraction-free view', 'flolens' ) . '"><span class="dashicons dashicons-editor-expand" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Distraction-free view', 'flolens' ) . '</span></button></span></div>';
		self::section_strip();
	}

	/**
	 * The row of sections that live under Settings (Settings itself, Achievements, Sharing, Integrations, Tools, Licence). Shown on Settings
	 * and on each of those screens, so they read as parts of Settings although they are not separate entries in the sidebar.
	 */
	public static function section_strip() {
		global $plugin_page;
		if ( ! \UserFlo\Admin\Menu::in_settings( (string) $plugin_page ) ) {
			return;
		}
		echo '<nav class="uf-sections" aria-label="' . esc_attr__( 'Settings sections', 'flolens' ) . '"><a href="' . esc_url( admin_url( 'admin.php?page=userflo-settings' ) ) . '"' . ( 'userflo-settings' === $plugin_page ? ' aria-current="page"' : '' ) . '><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> ' . esc_html__( 'Settings', 'flolens' ) . '</a>';
		foreach ( \UserFlo\Admin\Menu::settings_sections() as $slug => $s ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"' . ( $slug === $plugin_page ? ' aria-current="page"' : '' ) . '><span class="dashicons ' . esc_attr( $s[1] ) . '" aria-hidden="true"></span> ' . esc_html( $s[0] ) . '</a>';
		}
		echo '</nav>';
	}
}
