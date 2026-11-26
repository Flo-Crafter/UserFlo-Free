<?php
namespace UserFlo\Tracking;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Counts requests by AI crawlers, search engines and other bots. Most of them never run JavaScript, so they are invisible to the browser
 * tracker; this reads the request on the server instead. Only a daily counter per bot and page is kept (the bot's name, the page path
 * without its query, a number): no IP address, no full user agent, no cookie, nothing about a person. Pages answered from a full-page cache
 * never reach PHP and are not counted.
 */
final class BotCounter {

	const MAX_PAGES_PER_BOT_DAY = 500; // Beyond this a bot's further new pages are counted under one shared line, so a crawler cannot grow the table.
	const OTHER_PAGES           = '(other pages)';

	public static function register() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_count' ), 0 );
	}

	public static function maybe_count() {
		if ( ! Settings::get( 'bot_counter' ) || ( defined( 'USERFLO_DISABLED' ) && USERFLO_DISABLED ) || is_admin() || is_user_logged_in() ) {
			return;
		}
		if ( 'GET' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || is_404() || is_feed() || is_robots() || ( function_exists( 'is_favicon' ) && is_favicon() ) ) {
			return;
		}
		$ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 300 ) : '';
		$path = Validator::path( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Validated here.
		if ( null === $path ) {
			return;
		}
		self::record( $ua, $path );
	}

	/** Counts one request. @return bool Whether it was counted. */
	public static function record( $ua, $path, $time = null ) {
		global $wpdb;
		$hit = Bots::classify( $ua );
		if ( null === $hit || Collector::path_excluded( $path ) ) {
			return false;
		}
		$day = wp_date( 'Y-m-d', null === $time ? Clock::time() : $time );
		$t   = Tables::bots();
		$bot = substr( $hit['bot'], 0, 40 );
		$q   = strpos( $path, '?' );
		$url = substr( false === $q ? $path : substr( $path, 0, $q ), 0, 500 ); // The page, not its query.
		$key = 'uf_botn_' . md5( $day . $bot );
		$n   = get_transient( $key );
		if ( false === $n ) {
			$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE day = %s AND bot = %s", $day, $bot ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own tables: names are internal constants, values are prepared.
			set_transient( $key, $n, MINUTE_IN_SECONDS );
		}
		if ( $n >= self::MAX_PAGES_PER_BOT_DAY ) {
			$url = self::OTHER_PAGES;
		}
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$t} (day, bot, kind, url_hash, url, hits) VALUES (%s, %s, %s, %s, %s, 1) ON DUPLICATE KEY UPDATE hits = hits + 1", $day, $bot, $hit['kind'], md5( $url ), $url ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own tables: names are internal constants, values are prepared.
		return true;
	}
}
