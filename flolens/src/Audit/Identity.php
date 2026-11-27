<?php
namespace UserFlo\Audit;

use UserFlo\Core\Text;

defined( 'ABSPATH' ) || exit;

/**
 * Gives an element in a fetched page the same identifier the tracker gives it in the browser, so an inventoried element and the clicks recorded
 * for it meet on one value (`uf_events.target`). This is a port of `selector()` / `part()` / `label()` in `assets/js/tracker.js`; keep them in
 * step (a browser test compares the two on a real page).
 *
 * The identifier is not unique on a page (`a.btn` can be many buttons). An element's *identity* therefore also carries what tells instances apart
 * (the address of a link, the text of a button, the file of an image): see key().
 *
 * Limit: the tracker reads the live DOM, this reads the HTML the server sent. Classes or ids added by JavaScript after load are not seen, so such
 * an element shows in analytics without an inventory row (and the other way round).
 */
final class Identity {

	/** Same test as the tracker's stableClass(). */
	private static function stable_class( $c ) {
		return 1 === preg_match( '/^[a-z][a-z_-]{2,40}$/i', $c ) && 1 !== preg_match( '/^(active|open|show|hidden|current|selected|js-)/i', $c );
	}

	private static function part( \DOMElement $e ) {
		$t  = strtolower( $e->tagName );
		$id = $e->getAttribute( 'id' );
		if ( '' !== $id && 1 !== preg_match( '/\d{3,}|[0-9a-f]{8,}/i', $id ) ) {
			return $t . '#' . $id;
		}
		foreach ( array( 'data-userflo', 'data-testid', 'name', 'aria-controls' ) as $attr ) {
			$a = $e->getAttribute( $attr );
			if ( '' !== $a ) {
				return $t . '[' . Text::substr( $a, 40 ) . ']';
			}
		}
		foreach ( preg_split( '/\s+/', $e->getAttribute( 'class' ) ) as $c ) {
			if ( '' !== $c && self::stable_class( $c ) ) {
				return $t . '.' . $c;
			}
		}
		return $t;
	}

	/** The tracker's element identifier: up to four ancestors, stopping at the first id. */
	public static function identifier( \DOMElement $el ) {
		$parts = array();
		$depth = 0;
		$node  = $el;
		while ( $node instanceof \DOMElement && $depth < 4 && 'body' !== strtolower( $node->tagName ) ) {
			$p = self::part( $node );
			array_unshift( $parts, $p );
			if ( false !== strpos( $p, '#' ) ) {
				break;
			}
			$node = $node->parentNode;
			++$depth;
		}
		return Text::substr( implode( ' > ', $parts ), 255 );
	}

	/** The tracker's label(): aria-label, title, the value of a button input, the text, or the alt text of an image inside. */
	public static function label( \DOMElement $el ) {
		$s = $el->getAttribute( 'aria-label' );
		if ( '' === $s ) {
			$s = $el->getAttribute( 'title' );
		}
		if ( '' === $s ) {
			$s = 'input' === strtolower( $el->tagName ) ? $el->getAttribute( 'value' ) : $el->textContent;
			if ( '' === trim( (string) $s ) ) {
				foreach ( $el->getElementsByTagName( 'img' ) as $img ) {
					if ( $img->hasAttribute( 'alt' ) && '' !== $img->getAttribute( 'alt' ) ) {
						$s = $img->getAttribute( 'alt' );
						break;
					}
				}
			}
		}
		$s = preg_replace( '/\s+/u', ' ', (string) $s );
		return Text::substr( trim( null === $s ? '' : $s ), 80 );
	}

	/** Stable key of one element across pages: kind + identifier + what tells instances apart. */
	public static function key( $kind, $identifier, $sig ) {
		return md5( $kind . '|' . $identifier . '|' . strtolower( (string) $sig ) );
	}
}
