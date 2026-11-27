<?php
namespace UserFlo\Tracking;

use UserFlo\Privacy\IpHandler;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/** Public REST ingestion endpoint: POST /wp-json/userflo/v1/collect */
final class Collector {

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'userflo/v1',
			'/collect',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				// Intentionally public: pages may be cached, so no nonce is possible. Protected by validation and rate limits.
				'permission_callback' => '__return_true',
			)
		);
	}

	/** @param \WP_REST_Request $request Request. */
	public static function handle( $request ) {
		$empty = static function ( $status ) {
			$r = new \WP_REST_Response( null, $status );
			$r->header( 'Cache-Control', 'no-store' );
			return $r;
		};

		if ( ! Settings::get( 'enabled' ) ) {
			return $empty( 204 );
		}
		// Browsers label cross-site requests; the legitimate tracker only ever posts same-origin/same-site.
		$sfs = $request->get_header( 'sec_fetch_site' );
		if ( 'cross-site' === $sfs ) {
			return $empty( 403 );
		}
		$body = (string) $request->get_body();
		if ( strlen( $body ) > Validator::MAX_BODY ) {
			return $empty( 413 );
		}
		$payload = Validator::normalize( json_decode( $body, true ) );
		if ( null === $payload ) {
			return $empty( 400 );
		}

		$cookieless = \UserFlo\Privacy\Mode::cookieless();
		if ( $cookieless ) {
			$payload['vid'] = \UserFlo\Privacy\Mode::visitor_for_session( $payload['sid'] ); // No visitor id may outlive its session.
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$ua = UaParser::parse( $ua );
		if ( Settings::get( 'filter_bots' ) && $ua['bot'] ) {
			return $empty( 204 );
		}
		if ( self::path_excluded( $payload['url'] ) ) {
			return $empty( 204 );
		}

		$raw_ip = IpHandler::client_ip();
		if ( ! RateLimiter::allow( 'v' . $payload['vid'], (int) apply_filters( 'userflo_rate_limit_visitor', 120 ) ) ) {
			return $empty( 429 );
		}
		if ( '' !== $raw_ip ) {
			if ( ! RateLimiter::allow( 'i' . $raw_ip, (int) apply_filters( 'userflo_rate_limit_ip', 600 ) ) ) {
				return $empty( 429 );
			}
			// Flood protection: once an IP has created many new visitors in an hour, only already-known visitors are accepted.
			$nv_limit = (int) apply_filters( 'userflo_new_visitors_per_ip_hour', 300 );
			if ( RateLimiter::count( 'nv' . $raw_ip ) >= $nv_limit && ! Ingest::visitor_exists( $payload['vid'] ) ) {
				return $empty( 429 );
			}
		}

		$user_id = self::current_user_id();
		if ( $user_id && self::role_excluded( $user_id ) ) {
			return $empty( 204 );
		}

		$ctx = array(
			'device'    => $ua['device'],
			'browser'   => $ua['browser'],
			'os'        => $ua['os'],
			'ip'        => $cookieless ? '' : IpHandler::process( $raw_ip, (string) Settings::get( 'ip_mode' ), wp_salt( 'auth' ) ),
			'user_id'   => $cookieless ? 0 : $user_id, // The account is used above to apply role exclusions; session-only tracking does not store it.
			'link_user' => $cookieless ? false : (bool) Settings::get( 'identity_association' ),
			'geo'       => ( 'off' !== \UserFlo\Geo\Locator::mode() && '' !== $raw_ip ) ? static function () use ( $raw_ip, $cookieless ) {
				$g = \UserFlo\Geo\Locator::lookup( $raw_ip );
				if ( $g && $cookieless ) { // Country only: no city and no coordinates.
					$g['city'] = '';
					$g['lat']  = null;
					$g['lon']  = null;
				}
				return $g;
			} : null,
		);
		$result = Ingest::process( $payload, $ctx );
		if ( $result && $result['new_visitor'] && '' !== $raw_ip ) {
			RateLimiter::bump( 'nv' . $raw_ip, HOUR_IN_SECONDS );
		}
		return $empty( false === $result ? 400 : 204 );
	}

	/**
	 * Logged-in user from the auth cookie. REST cookie auth would discard the user without a nonce,
	 * and pages may be cached, so the cookie is validated directly (identity only; no privileged action).
	 */
	public static function current_user_id() {
		$id = wp_validate_auth_cookie( '', 'logged_in' );
		return $id ? (int) $id : 0;
	}

	public static function path_excluded( $path ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) Settings::get( 'exclude_paths' ) );
		foreach ( $lines as $prefix ) {
			if ( '' !== $prefix && 0 === strpos( $path, $prefix ) ) {
				return true;
			}
		}
		return \UserFlo\Exclusions\Service::page_stopped( $path ); // Rules from the Exclusions screen ("stop tracking" for a page).
	}

	public static function role_excluded( $user_id ) {
		$roles = (array) Settings::get( 'exclude_roles' );
		if ( ! $roles ) {
			return false;
		}
		$user = get_userdata( $user_id );
		return $user && array_intersect( $roles, (array) $user->roles );
	}
}
