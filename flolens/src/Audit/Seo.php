<?php
namespace UserFlo\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Which SEO plugins are active. Read-only: FloLens never changes another plugin's settings. The audit judges what the page *renders*, so it works
 * with no SEO plugin at all; this only lets the screens give plugin-specific advice and flag a configuration worth reviewing.
 *
 * Detection uses each plugin's own version constant (WPSEO_VERSION, SEOPRESS_VERSION). Reading their stored meta keys is deliberately not done:
 * those names could not be verified against the plugins here.
 */
final class Seo {

	/** @return array{yoast:bool,seopress:bool,both:bool,names:string[]} */
	public static function status() {
		$y = defined( 'WPSEO_VERSION' );
		$s = defined( 'SEOPRESS_VERSION' );
		$n = array();
		if ( $y ) {
			$n[] = 'Yoast SEO';
		}
		if ( $s ) {
			$n[] = 'SEOPress';
		}
		return array( 'yoast' => $y, 'seopress' => $s, 'both' => $y && $s, 'names' => $n );
	}

	/** Where to edit a page's title and description. The plugin's own editor panel is on the post screen, so the post link is the right place. */
	public static function edit_link( $post_id ) {
		return $post_id ? (string) get_edit_post_link( (int) $post_id, 'raw' ) : '';
	}
}
