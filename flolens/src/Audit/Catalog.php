<?php
namespace UserFlo\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Every finding the audit can report: how sure it is (class), how much it matters, what it means and what to do. One list, so the Lite checks,
 * the Pro checks, the screens and the exclusion rules all name findings the same way.
 *
 * Classes: **verified** (a fact read from the page, shown with its evidence), **warning** (usually a problem, not always), **recommendation**
 * (advice that depends on judgement; never counted as an issue). Nothing here claims an effect on rankings or conversions.
 */
final class Catalog {

	/** Findings the Lite scanner can decide from data it already has. Everything else needs FloLens Pro. */
	const BASIC = array( 'http_error', 'title_missing', 'noindex', 'html_lang_missing', 'img_alt_missing', 'link_empty', 'button_no_name', 'form_field_no_label' );

	public static function all() {
		return self::seo() + A11yCatalog::entries();
	}

	private static function seo() {
		return array(
			'http_error'            => array( 'verified', 3, 'page', __( 'The page did not load', 'flolens' ), __( 'The server answered with an error status when FloLens fetched this page as a visitor would.', 'flolens' ), __( 'Open the page in a private window. If it should exist, check redirects, permissions and plugins; if it should not, remove links to it.', 'flolens' ) ),
			'title_missing'         => array( 'verified', 2, 'page', __( 'No page title', 'flolens' ), __( 'The page has no title tag. Browsers, bookmarks and search results fall back to the address.', 'flolens' ), __( 'Set a title in the page editor or in your SEO plugin.', 'flolens' ) ),
			'noindex'               => array( 'verified', 2, 'page', __( 'Asks search engines not to index it', 'flolens' ), __( 'A robots meta tag or header tells crawlers to skip this page. That is deliberate for some pages (thank-you pages, members areas) and a mistake for others.', 'flolens' ), __( 'If the page should appear in search results, switch off “discourage search engines” or the page’s noindex setting.', 'flolens' ) ),
			'title_long'            => array( 'warning', 1, 'page', __( 'Title may be too long', 'flolens' ), __( 'Titles over about 60 characters are often cut off in search results (how much is shown varies).', 'flolens' ), __( 'Shorten the title so the main words come first.', 'flolens' ) ),
			'description_missing'   => array( 'verified', 2, 'page', __( 'No meta description', 'flolens' ), __( 'The page has no description tag, so search engines choose their own snippet.', 'flolens' ), __( 'Write a one or two sentence description in the page editor or your SEO plugin.', 'flolens' ) ),
			'description_long'      => array( 'warning', 1, 'page', __( 'Description may be too long', 'flolens' ), __( 'Descriptions over about 160 characters are often shortened in search results.', 'flolens' ), __( 'Tighten the description.', 'flolens' ) ),
			'canonical_missing'     => array( 'warning', 1, 'page', __( 'No canonical address', 'flolens' ), __( 'The page does not say which address is the preferred one. Duplicates (with tracking parameters, for example) may compete.', 'flolens' ), __( 'Most SEO plugins add this; check that one is active and configured.', 'flolens' ) ),
			'canonical_other_host'  => array( 'warning', 2, 'page', __( 'Canonical points to another site', 'flolens' ), __( 'The canonical address is on a different host. That is right for syndicated content and wrong otherwise.', 'flolens' ), __( 'Check the canonical setting of the page.', 'flolens' ) ),
			'h1_missing'            => array( 'verified', 2, 'page', __( 'No main heading', 'flolens' ), __( 'The page has no h1 heading. Visitors using assistive technology and search engines use it to understand the page.', 'flolens' ), __( 'Give the page one clear main heading.', 'flolens' ) ),
			'h1_multiple'           => array( 'warning', 1, 'page', __( 'More than one main heading', 'flolens' ), __( 'The page has several h1 headings. Theme templates and page builders often add one by accident.', 'flolens' ), __( 'Keep one h1 heading and make the others h2.', 'flolens' ) ),
			'heading_skip'          => array( 'warning', 1, 'page', __( 'Heading levels skip', 'flolens' ), __( 'A heading jumps down more than one level (for example h2 to h4), which makes the outline harder to follow.', 'flolens' ), __( 'Use consecutive heading levels.', 'flolens' ) ),
			'og_missing'            => array( 'recommendation', 1, 'page', __( 'No social sharing title', 'flolens' ), __( 'Without Open Graph tags, shared links may show a poor preview.', 'flolens' ), __( 'Most SEO plugins can add social sharing tags.', 'flolens' ) ),
			'img_alt_missing'       => array( 'verified', 2, 'element', __( 'Image without alt text', 'flolens' ), __( 'The image has no alt attribute, so screen readers announce the file name. Decorative images should have an empty alt (alt=""), which is fine.', 'flolens' ), __( 'Describe the image in the media library, or mark it decorative with an empty alt.', 'flolens' ) ),
			'link_empty'            => array( 'verified', 2, 'element', __( 'Link without a name', 'flolens' ), __( 'The link has no text, label or image text, so it is announced only as “link”.', 'flolens' ), __( 'Add visible text or an aria-label.', 'flolens' ) ),
			'link_generic'          => array( 'warning', 1, 'element', __( 'Link text says little', 'flolens' ), __( 'Text such as “click here” or “read more” does not tell people where the link goes when read out of context.', 'flolens' ), __( 'Say what the link leads to.', 'flolens' ) ),
			'button_no_name'        => array( 'verified', 2, 'element', __( 'Button without a name', 'flolens' ), __( 'The button has no text, label or image text, so it is announced only as “button”.', 'flolens' ), __( 'Add visible text or an aria-label.', 'flolens' ) ),
			'form_field_no_label'   => array( 'verified', 2, 'element', __( 'Form fields without labels', 'flolens' ), __( 'Some fields of this form have no label, so people using assistive technology cannot tell what to enter. A placeholder alone is not a label.', 'flolens' ), __( 'Add a label for each field.', 'flolens' ) ),
		);
	}

	public static function get( $code ) {
		$all = self::all();
		if ( ! isset( $all[ $code ] ) ) {
			return null;
		}
		$r = $all[ $code ];
		$m = A11yCatalog::meta();
		return array( 'code' => $code, 'class' => $r[0], 'severity' => $r[1], 'scope' => $r[2], 'title' => $r[3], 'why' => $r[4], 'fix' => $r[5] ) + ( isset( $m[ $code ] ) ? array( 'wcag' => $m[ $code ][0], 'detect' => $m[ $code ][1], 'limits' => $m[ $code ][2] ) : array() );
	}

	/** Codes of the findings that belong to the accessibility audit (they carry a WCAG reference). */
	public static function a11y_codes() {
		return array_keys( A11yCatalog::meta() );
	}

	/** Code => title, for filters and rule dialogs. */
	public static function titles() {
		$out = array();
		foreach ( self::all() as $code => $r ) {
			$out[ $code ] = $r[3];
		}
		return $out;
	}

	/** Health of a page from its *active* findings: 2 red (a verified issue), 1 amber (a warning), 0 green. Recommendations never colour it. */
	public static function health( array $classes ) {
		if ( in_array( 'verified', $classes, true ) ) {
			return 2;
		}
		return in_array( 'warning', $classes, true ) ? 1 : 0;
	}
}
