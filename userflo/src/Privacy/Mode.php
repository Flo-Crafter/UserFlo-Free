<?php
namespace UserFlo\Privacy;

use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Tracking mode. "standard" keeps today's behaviour (two first-party cookies once tracking starts). "cookieless" is session-only tracking: no
 * cookie is written or read, a visit is recognised only within one browser tab (ids in sessionStorage, gone when the tab closes), nothing
 * identifies a returning visitor, no IP address is stored, and a visit is never linked to a user account. It is a privacy control, so it is
 * available in every edition. It does not replace the consent setting and does not make a site compliant by itself.
 */
final class Mode {

	const STANDARD  = 'standard';
	const COOKIELESS = 'cookieless';

	public static function cookieless() {
		return self::COOKIELESS === Settings::get( 'tracking_mode' );
	}

	/**
	 * A visitor id that cannot outlive the session: derived from the session id with the site's secret, so even a browser that still sends an
	 * old persistent id (a cached page with the previous tracker, a hand-made request) is stored as one new visitor per session.
	 */
	public static function visitor_for_session( $sid ) {
		return substr( hash_hmac( 'sha256', 'uf-cookieless:' . $sid, wp_salt( 'auth' ) ), 0, 32 );
	}

	public static function sanitize( array $input, array $stored ) {
		$v = isset( $input['tracking_mode'] ) ? $input['tracking_mode'] : ( isset( $stored['tracking_mode'] ) ? $stored['tracking_mode'] : self::STANDARD );
		$v = is_string( $v ) ? $v : self::STANDARD;
		return self::COOKIELESS === $v ? self::COOKIELESS : self::STANDARD;
	}
}
