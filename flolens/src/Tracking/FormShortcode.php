<?php
namespace UserFlo\Tracking;

defined( 'ABSPATH' ) || exit;

/**
 * [userflo_form name="Quote request"] … any form or form shortcode … [/userflo_form]
 *
 * Names a form manually, for any form plugin or hand-written form. The wrapper adds `data-userflo-form` to a `display: contents`
 * element, so it does not change the layout; the tracker then records a submit of the form inside it under that name. It collects
 * nothing itself and never reads field values. FloLens Pro adds automatic detection of the common form builders on top of this.
 */
final class FormShortcode {

	const TAG = 'userflo_form';

	public static function register() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
	}

	public static function render( $atts, $content = '' ) {
		$a    = shortcode_atts( array( 'name' => '' ), (array) $atts, self::TAG );
		$name = \UserFlo\Core\Text::substr( trim( sanitize_text_field( (string) $a['name'] ) ), 80 );
		$inner = do_shortcode( (string) $content );
		if ( '' === $name ) {
			return $inner; // Nothing to name: render the form untouched.
		}
		/** Extra data attributes for the wrapper (Pro adds the success selector). Keys must be `data-userflo-…`. */
		$extra = (array) apply_filters( 'userflo_form_shortcode_attributes', array(), (array) $atts );
		$attrs = ' data-userflo-form="' . esc_attr( $name ) . '"';
		foreach ( $extra as $k => $v ) {
			if ( 0 === strpos( (string) $k, 'data-userflo-' ) && preg_match( '/^[a-z-]+$/', $k ) ) {
				$attrs .= ' ' . $k . '="' . esc_attr( (string) $v ) . '"';
			}
		}
		return '<div class="userflo-form"' . $attrs . ' style="display:contents">' . $inner . '</div>';
	}
}
