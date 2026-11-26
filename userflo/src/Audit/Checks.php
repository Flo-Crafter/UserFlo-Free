<?php
namespace UserFlo\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * The findings UserFlo Lite decides: only those that need nothing but the response itself (status, title, robots). Pro adds the full set through
 * the `userflo_audit_findings` filter. Pure functions.
 */
final class Checks {

	/**
	 * @param array $page   http_status, headers (lower-case names).
	 * @param array $parsed Parser::parse() result, or null when the body could not be read.
	 * @return array[] Each: code, ekey (null for the page), evidence.
	 */
	public static function basic( array $page, $parsed ) {
		$out = array();
		if ( (int) $page['http_status'] >= 400 ) {
			$out[] = array( 'code' => 'http_error', 'ekey' => null, 'evidence' => 'HTTP ' . (int) $page['http_status'] );
			return $out; // Nothing else can be said about an error page.
		}
		if ( null === $parsed ) {
			return $out;
		}
		if ( '' === trim( $parsed['title'] ) ) {
			$out[] = array( 'code' => 'title_missing', 'ekey' => null, 'evidence' => '' );
		}
		$out = array_merge( $out, A11yBasic::run( $parsed ) ); // The basic accessibility findings (document language, names and labels).
		$hdr = isset( $page['headers']['x-robots-tag'] ) ? strtolower( (string) $page['headers']['x-robots-tag'] ) : '';
		if ( false !== strpos( $parsed['robots'], 'noindex' ) || false !== strpos( $hdr, 'noindex' ) ) {
			$out[] = array( 'code' => 'noindex', 'ekey' => null, 'evidence' => false !== strpos( $hdr, 'noindex' ) ? 'X-Robots-Tag: ' . $hdr : 'meta robots: ' . $parsed['robots'] );
		}
		return $out;
	}
}
