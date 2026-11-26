<?php
namespace UserFlo;

defined( 'ABSPATH' ) || exit;

/**
 * The owner's public addresses in one place: the developer's website, the donation pages, the plugin-directory page and the mailbox that
 * receives the bug and request form. The website and plugin-directory addresses are real. The donation pages and the mailbox are still
 * placeholders on the reserved `.invalid` TLD (RFC 2606) until the owner replaces them here or with the matching filter (see docs/EDITIONS.md and docs/DECISIONS.md).
 */
final class Links {

	const WEBSITE   = 'https://flocrafter.com/userflo/';
	const DIRECTORY = 'https://wordpress.org/plugins/userflo/';
	const KOFI      = 'https://kofi.example.invalid/placeholder';
	const COFFEE    = 'https://coffee.example.invalid/placeholder';
	const PAYPAL    = 'https://paypal.example.invalid/placeholder';
	const FEEDBACK  = 'feedback@example.invalid';

	public static function website() {
		return (string) apply_filters( 'userflo_website_url', self::WEBSITE );
	}

	public static function directory() {
		return (string) apply_filters( 'userflo_directory_url', self::DIRECTORY );
	}

	/** @return array<string,array{0:string,1:string}> id => array( label, url ). Return an empty URL from the filter to drop one; placeholders are left out. */
	public static function donate() {
		$all = (array) apply_filters(
			'userflo_donate_links',
			array(
				'kofi'   => array( 'Ko-fi', self::KOFI ),
				'coffee' => array( 'Buy Me a Coffee', self::COFFEE ),
				'paypal' => array( 'PayPal', self::PAYPAL ),
			)
		);
		// A link that is still a placeholder is never shown: a button that goes nowhere is worse than no button.
		return array_filter( $all, static function ( $l ) { return is_array( $l ) && ! empty( $l[1] ) && self::is_real( $l[1] ); } );
	}

	public static function feedback_email() {
		$e = sanitize_email( (string) apply_filters( 'userflo_feedback_email', self::FEEDBACK ) );
		return is_email( $e ) ? $e : '';
	}

	/** True when the developer's mailbox has been configured (the feedback form is offered only then). */
	public static function feedback_ready() {
		$e = self::feedback_email();
		return '' !== $e && self::is_real( $e );
	}

	/** True once the placeholder has been replaced. */
	public static function is_real( $value ) {
		return '' !== (string) $value && false === strpos( (string) $value, '.invalid' );
	}
}
