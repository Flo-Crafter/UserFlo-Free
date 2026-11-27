<?php
namespace UserFlo\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * The accessibility findings FloLens (free) decides: facts read straight from the page's own HTML that need no judgement and rarely give a
 * false alarm. Pure functions on the Parser's result. FloLens Pro adds the broader checks through the `userflo_audit_findings` and
 * `userflo_audit_a11y_checks` filters.
 *
 * "Tested" counts say how many things of that kind the page had, so a clean result can be shown as "passed this automated check" (and only
 * when something was actually tested). A passed check never means the page conforms to WCAG.
 */
final class A11yBasic {

	/** Bump when what is stored per scan changes, so pages are read again once after an upgrade. */
	const VERSION = 1;

	/** @return array[] Findings: code, ekey (null for the page), evidence. */
	public static function run( array $parsed ) {
		$out = array();
		if ( isset( $parsed['a11y'] ) && empty( $parsed['a11y']['lang_present'] ) ) {
			$out[] = array( 'code' => 'html_lang_missing', 'ekey' => null, 'evidence' => '' );
		}
		foreach ( isset( $parsed['elements'] ) ? $parsed['elements'] : array() as $e ) {
			$k = isset( $e['ekey'] ) ? $e['ekey'] : null;
			$f = isset( $e['flags'] ) ? $e['flags'] : array();
			switch ( $e['kind'] ) {
				case 'image':
					if ( isset( $f['alt'] ) && 'missing' === $f['alt'] && empty( $f['decorative'] ) ) {
						$out[] = array( 'code' => 'img_alt_missing', 'ekey' => $k, 'evidence' => $e['sig'] );
					}
					break;
				case 'link':
				case 'external':
				case 'file':
				case 'anchor':
				case 'contact':
					if ( isset( $f['name'] ) && ! $f['name'] ) {
						$out[] = array( 'code' => 'link_empty', 'ekey' => $k, 'evidence' => trim( $e['href'] . ( ! empty( $f['img_only'] ) ? ' (image only)' : '' ) ) );
					}
					break;
				case 'button':
					if ( isset( $f['name'] ) && ! $f['name'] ) {
						$out[] = array( 'code' => 'button_no_name', 'ekey' => $k, 'evidence' => ! empty( $f['img_only'] ) ? 'image only' : '' );
					}
					break;
				case 'form':
					if ( ! empty( $f['fields_without_label'] ) ) {
						$out[] = array( 'code' => 'form_field_no_label', 'ekey' => $k, 'evidence' => (int) $f['fields_without_label'] . ' field(s)' );
					}
					break;
			}
		}
		return $out;
	}

	/**
	 * What was tested on this page, per check: code => array( 't' => things tested, 'f' => things found, optional 'nt' => 1 when the check
	 * could not be made at all).
	 *
	 * @return array<string,array>
	 */
	public static function report( array $parsed ) {
		$r = array(
			'html_lang_missing'   => array( 't' => isset( $parsed['a11y'] ) ? 1 : 0, 'f' => isset( $parsed['a11y'] ) && empty( $parsed['a11y']['lang_present'] ) ? 1 : 0 ),
			'img_alt_missing'     => array( 't' => 0, 'f' => 0 ),
			'link_empty'          => array( 't' => 0, 'f' => 0 ),
			'button_no_name'      => array( 't' => 0, 'f' => 0 ),
			'form_field_no_label' => array( 't' => 0, 'f' => 0 ),
		);
		foreach ( isset( $parsed['elements'] ) ? $parsed['elements'] : array() as $e ) {
			$f = isset( $e['flags'] ) ? $e['flags'] : array();
			switch ( $e['kind'] ) {
				case 'image':
					if ( isset( $f['alt'] ) && empty( $f['decorative'] ) ) {
						++$r['img_alt_missing']['t'];
						$r['img_alt_missing']['f'] += 'missing' === $f['alt'] ? 1 : 0;
					}
					break;
				case 'link':
				case 'external':
				case 'file':
				case 'anchor':
				case 'contact':
					if ( isset( $f['name'] ) ) {
						++$r['link_empty']['t'];
						$r['link_empty']['f'] += $f['name'] ? 0 : 1;
					}
					break;
				case 'button':
					if ( isset( $f['name'] ) ) {
						++$r['button_no_name']['t'];
						$r['button_no_name']['f'] += $f['name'] ? 0 : 1;
					}
					break;
				case 'form':
					$r['form_field_no_label']['t'] += isset( $f['fields'] ) ? (int) $f['fields'] : 0;
					$r['form_field_no_label']['f'] += isset( $f['fields_without_label'] ) ? (int) $f['fields_without_label'] : 0;
					break;
			}
		}
		return $r;
	}
}
