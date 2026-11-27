<?php
namespace UserFlo\Admin;

use UserFlo\Help\Guides;

defined( 'ABSPATH' ) || exit;

/** Static parts of Settings → Help and Settings → About (dynamic parts are loaded by assets/admin/settings.js). */
final class HelpTab {

	public static function render() {
		echo '<p class="uf-sub">' . esc_html__( 'Guides open in a window; the change log and logs are here too.', 'flolens' ) . '</p>';

		echo '<div class="uf-help-quick"><div class="uf-card"><h3>' . esc_html__( 'Set up geolocation', 'flolens' ) . '</h3><p>' . esc_html__( 'Locations come from a local database file in MMDB format. The easiest way is the free DB-IP Lite file that FloLens downloads for you (Geolocation tab, source “DB-IP Lite”). If you prefer MaxMind GeoLite2 or another provider, download the City or Country file in MMDB format, upload it to your server and enter its path under “My own .mmdb file”.', 'flolens' ) . '</p><ul class="uf-int-limits"><li><a href="https://db-ip.com/db/download/ip-to-city-lite" target="_blank" rel="noopener noreferrer">DB-IP Lite (CC BY 4.0)</a></li><li><a href="https://www.maxmind.com/en/geolite2/signup" target="_blank" rel="noopener noreferrer">MaxMind GeoLite2 (free account)</a></li></ul><p><button type="button" class="button" data-guide="geolocation-setup">' . esc_html__( 'Read the step-by-step guide', 'flolens' ) . '</button></p></div>';
		if ( \UserFlo\Edition::can( 'integrations' ) ) { // The integrations guide ships with Pro.
			echo '<div class="uf-card"><h3>' . esc_html__( 'Connect integrations', 'flolens' ) . '</h3><p>' . esc_html__( 'Google Analytics 4, Microsoft Clarity and PostHog are optional. If Google Site Kit, the Clarity plugin or the PostHog plugin is already installed, open Integrations and press “Use these settings” instead of typing keys again. Otherwise the guide lists exactly which IDs, tokens and service accounts you need and where to find them.', 'flolens' ) . '</p><p><button type="button" class="button" data-guide="connecting-integrations">' . esc_html__( 'Read the step-by-step guide', 'flolens' ) . '</button></p></div>';
		}
		echo '</div>';

		echo '<h3>' . esc_html__( 'Guides', 'flolens' ) . '</h3><ul class="uf-guides">';
		foreach ( Guides::list_items() as $g ) {
			echo '<li><button type="button" class="uf-guide" data-guide="' . esc_attr( $g['slug'] ) . '"><strong>' . esc_html( $g['title'] ) . '</strong><span>' . esc_html( $g['summary'] ) . '</span></button></li>';
		}
		echo '</ul>';

		echo '<h3>' . esc_html__( 'Logs', 'flolens' ) . '</h3><p class="description">' . esc_html__( 'Background work such as database downloads, integration syncs, retention cleanups and share links (no visitor data). The newest 200 entries are kept.', 'flolens' ) . '</p>';
		echo '<p><button type="button" class="button" id="uf-log-refresh">' . esc_html__( 'Refresh', 'flolens' ) . '</button> <button type="button" class="button button-link-delete" id="uf-log-clear">' . esc_html__( 'Clear log', 'flolens' ) . '</button></p><div id="uf-log" aria-live="polite"></div>';
	}

	public static function about() {
		echo '<div class="uf-about-head"><img src="' . esc_url( \UserFlo\Brand::icon_url() ) . '" width="56" height="56" alt=""><div><h3>FloLens <small>' . esc_html( USERFLO_VERSION ) . '</small></h3><p>' . esc_html__( 'Self-hosted visitor intelligence for WordPress: sessions, journeys, logged-in and anonymous visitors, heatmaps, maps and reports, stored on your own server.', 'flolens' ) . '</p></div></div>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Licence', 'flolens' ) . '</th><td>GPL-2.0-or-later <button type="button" class="button" data-guide="license">' . esc_html__( 'Read the licence', 'flolens' ) . '</button></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Source code and issues', 'flolens' ) . '</th><td><a href="https://github.com/Flo-Crafter/UserFlo-Free" target="_blank" rel="noopener noreferrer">github.com/Flo-Crafter/UserFlo-Free</a></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Change log', 'flolens' ) . '</th><td><button type="button" class="button" data-guide="changelog">' . esc_html__( 'Open the change log', 'flolens' ) . '</button></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Credits', 'flolens' ) . '</th><td><ul class="uf-int-limits">';
		echo '<li>' . wp_kses_post( __( 'IP geolocation data by <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer">DB-IP.com</a> (CC BY 4.0) when the free database is used.', 'flolens' ) ) . '</li>';
		if ( \UserFlo\Edition::can( 'map_globe' ) ) {
			echo '<li>' . wp_kses_post( __( 'Vector globe map: <a href="https://commons.wikimedia.org/wiki/File:Equirectangular_projection_world_map_without_borders.svg" target="_blank" rel="noopener noreferrer">Equirectangular projection world map without borders</a> (Wikimedia Commons), <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener noreferrer">CC BY-SA 4.0</a>; compressed, otherwise unchanged.', 'flolens' ) ) . '</li>';
		}
		echo '<li>MaxMind DB Reader (maxmind-db/reader, Apache-2.0)</li>';
		echo '<li>' . esc_html__( 'Country names, continents, map points and outlines from Natural Earth (public domain).', 'flolens' ) . '</li>';
		echo '<li>React (bundled with WordPress), esbuild, terser (build tools)</li></ul></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'System information', 'flolens' ) . '</th><td><button type="button" class="button" id="uf-sys-load">' . esc_html__( 'Show system information', 'flolens' ) . '</button> <button type="button" class="button" id="uf-sys-copy" hidden>' . esc_html__( 'Copy to clipboard', 'flolens' ) . '</button><pre id="uf-sysinfo" class="uf-sysinfo" hidden></pre><p class="description">' . esc_html__( 'Useful when asking for support. Contains no secrets and no visitor data.', 'flolens' ) . '</p></td></tr>';
		echo '</tbody></table>';
	}
}
