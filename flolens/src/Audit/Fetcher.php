<?php
namespace UserFlo\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Fetches one page of THIS site the way an anonymous visitor (and a crawler) sees it: no cookies, no login. The address is always built from the
 * site's own home URL and a stored path, never from user input, and redirects are followed by hand and only while they stay on the same host, so
 * the audit cannot be pointed at another server.
 *
 * `wp_remote_get` is used rather than `wp_safe_remote_get` because many hosts resolve their own name to a private address, which the "safe"
 * variant refuses; the same-host rule is what protects here. Sites behind HTTP authentication or a firewall can adjust the request with the
 * `userflo_audit_http_args` filter.
 */
final class Fetcher {

	const MAX_HOPS = 3;
	const MAX_BODY = 2097152;

	/** @return array status, final_url, body, headers, content_type, error ('' when the body can be audited). */
	public static function get( $path ) {
		$out = array( 'status' => 0, 'final_url' => '', 'body' => '', 'headers' => array(), 'content_type' => '', 'error' => '' );
		if ( defined( 'USERFLO_AUDIT_OFF' ) && USERFLO_AUDIT_OFF ) {
			$out['error'] = 'disabled';
			return $out;
		}
		$path = (string) $path;
		if ( '' === $path || '/' !== $path[0] || 0 === strpos( $path, '//' ) ) {
			$out['error'] = 'bad_path';
			return $out;
		}
		$home = home_url();
		$host = strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) );
		$url  = home_url( $path );
		$args = (array) apply_filters( 'userflo_audit_http_args', array(
			'timeout'             => 8,
			'redirection'         => 0, // Followed below, on this host only.
			'user-agent'          => 'FloLens-Audit/' . USERFLO_VERSION,
			'headers'             => array( 'Accept' => 'text/html,application/xhtml+xml', 'Cache-Control' => 'no-cache' ),
			'cookies'             => array(),
			'sslverify'           => (bool) apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core constant or hook, not ours.
			'limit_response_size' => self::MAX_BODY + 1,
		) );
		for ( $hop = 0; $hop <= self::MAX_HOPS; $hop++ ) {
			$res = wp_remote_get( $url, $args );
			if ( is_wp_error( $res ) ) {
				$out['error'] = 'network';
				return $out;
			}
			$code = (int) wp_remote_retrieve_response_code( $res );
			$loc  = (string) wp_remote_retrieve_header( $res, 'location' );
			if ( $code >= 300 && $code < 400 && '' !== $loc ) {
				$next = wp_http_validate_url( $loc ) ? $loc : ( 0 === strpos( $loc, '/' ) && 0 !== strpos( $loc, '//' ) ? home_url( $loc ) : '' );
				if ( '' === $next || strtolower( (string) wp_parse_url( $next, PHP_URL_HOST ) ) !== $host ) {
					$out['status'] = $code;
					$out['error']  = 'redirect_offsite';
					return $out;
				}
				$url = $next;
				continue;
			}
			$h = array();
			foreach ( array( 'x-robots-tag', 'content-type', 'link' ) as $k ) {
				$v = wp_remote_retrieve_header( $res, $k );
				$h[ $k ] = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
			}
			$out['status']       = $code;
			$out['final_url']    = $url;
			$out['headers']      = $h;
			$out['content_type'] = strtolower( $h['content-type'] );
			$body                = (string) wp_remote_retrieve_body( $res );
			if ( $code < 400 && false === strpos( $out['content_type'], 'html' ) ) {
				$out['error'] = 'not_html';
				return $out;
			}
			if ( strlen( $body ) > self::MAX_BODY ) {
				$out['error'] = 'too_large';
				return $out;
			}
			$out['body'] = $body;
			return $out;
		}
		$out['error'] = 'too_many_redirects';
		return $out;
	}

	/** Pages that answer like a maintenance, coming-soon or login wall say nothing about the site, so no findings are made from them. */
	public static function is_wall( array $res ) {
		if ( in_array( (int) $res['status'], array( 401, 403, 503 ), true ) ) {
			return true;
		}
		return false;
	}
}
