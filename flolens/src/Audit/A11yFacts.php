<?php
namespace UserFlo\Audit;

use UserFlo\Core\Text;

defined( 'ABSPATH' ) || exit;

/**
 * Accessibility facts read from the HTML a page returned: what the page declares, never what a browser would render. Pure functions on a parsed
 * document (no network, no WordPress state), so each fact is tested with fixtures. The checks that turn the facts into findings are
 * `A11yBasic` (Lite) and FloLens Pro's `A11yChecks`.
 *
 * What this cannot see: styles from external stylesheets, anything JavaScript adds after load, the real background behind text, keyboard
 * behaviour. Facts that depend on those are not produced here (the checks report them as "not tested" or "manual review").
 */
final class A11yFacts {

	const VERSION = 1;
	const SAMPLE  = 3;   // Identifiers kept as evidence per fact.
	const MAX_SCAN = 4000; // Most elements any one fact looks at, so a huge page stays cheap.

	/** WAI-ARIA 1.2 roles plus the digital publishing and graphics modules: a role outside this list is not defined. */
	const ROLES = array(
		'alert', 'alertdialog', 'application', 'article', 'banner', 'blockquote', 'button', 'caption', 'cell', 'checkbox', 'code', 'columnheader', 'combobox', 'complementary', 'contentinfo', 'definition', 'deletion', 'dialog', 'directory', 'document', 'emphasis', 'feed', 'figure', 'form', 'generic', 'grid', 'gridcell', 'group', 'heading', 'img', 'insertion', 'link', 'list', 'listbox', 'listitem', 'log', 'main', 'marquee', 'math', 'menu', 'menubar', 'menuitem', 'menuitemcheckbox', 'menuitemradio', 'meter', 'navigation', 'none', 'note', 'option', 'paragraph', 'presentation', 'progressbar', 'radio', 'radiogroup', 'region', 'row', 'rowgroup', 'rowheader', 'scrollbar', 'search', 'searchbox', 'separator', 'slider', 'spinbutton', 'status', 'strong', 'subscript', 'superscript', 'switch', 'tab', 'table', 'tablist', 'tabpanel', 'term', 'textbox', 'time', 'timer', 'toolbar', 'tooltip', 'tree', 'treegrid', 'treeitem',
		'graphics-document', 'graphics-object', 'graphics-symbol',
	);

	/** Facts for one document. @return array */
	public static function read( \DOMDocument $dom, \DOMXPath $xp ) {
		$root  = $dom->documentElement;
		$ids   = array();
		$dups  = array();
		$nodes = array();
		$n     = 0;
		foreach ( $xp->query( '//body//*' ) as $el ) {
			if ( ! $el instanceof \DOMElement || self::inert( $el ) ) {
				continue;
			}
			if ( ++$n > self::MAX_SCAN ) {
				break;
			}
			$nodes[] = $el;
			$id      = $el->getAttribute( 'id' );
			if ( '' !== $id ) {
				$dups[ $id ] = isset( $ids[ $id ] ) ? true : ( $dups[ $id ] ?? false );
				$ids[ $id ]  = true;
			}
		}
		$f = array(
			'v'                => self::VERSION,
			'capped'           => $n > self::MAX_SCAN,
			'lang_present'     => $root->hasAttribute( 'lang' ) && '' !== trim( $root->getAttribute( 'lang' ) ),
			'lang'             => trim( $root->getAttribute( 'lang' ) ),
			'main'             => false,
			'viewport'         => '',
			'empty_headings'   => array(),
			'ref_missing'      => array(),
			'id_dup_referenced' => array(),
			'role_invalid'     => array(),
			'aria_hidden_focusable' => array(),
			'tabindex_positive' => array(),
			'iframe_no_title'  => array(),
			'custom_click'     => array(),
			'link_no_action'   => array(),
			'required_unmarked' => array(),
			'autocomplete_missing' => array(),
			'ext_css'          => 0,
			'videos'           => 0,
			'video_no_track'   => array(),
			'embeds'           => array(),
			'autoplay'         => array(),
			'outline'          => array(),
			'contrast'         => array( 'tested' => 0, 'low' => array() ),
			'alt'              => array( 'filename' => array(), 'empty_title' => array() ),
		);
		foreach ( $xp->query( '//head/meta[@name]' ) as $m ) {
			if ( 'viewport' === strtolower( $m->getAttribute( 'name' ) ) ) {
				$f['viewport'] = strtolower( $m->getAttribute( 'content' ) );
			}
		}
		$f['ext_css'] = $xp->query( '//link[@rel][contains(concat(" ", translate(normalize-space(@rel), "STYLEHEET", "stylehet"), " "), " stylesheet ")]' )->length;
		$labels_for = array();
		$ref_ids    = array();
		foreach ( $nodes as $el ) {
			$tag  = strtolower( $el->tagName );
			$role = strtolower( trim( $el->getAttribute( 'role' ) ) );
			if ( 'main' === $tag || 'main' === $role ) {
				$f['main'] = true;
			}
			if ( 'label' === $tag && '' !== $el->getAttribute( 'for' ) ) {
				$labels_for[ $el->getAttribute( 'for' ) ] = $el;
			}
			// ARIA references.
			foreach ( array( 'aria-labelledby', 'aria-describedby' ) as $a ) {
				if ( $el->hasAttribute( $a ) ) {
					foreach ( preg_split( '/\s+/', trim( $el->getAttribute( $a ) ), -1, PREG_SPLIT_NO_EMPTY ) as $ref ) {
						$ref_ids[ $ref ] = true;
						if ( ! isset( $ids[ $ref ] ) ) {
							self::add( $f['ref_missing'], array( 'attr' => $a, 'target' => Text::substr( $ref, 40 ), 'el' => Identity::identifier( $el ) ) );
						}
					}
				}
			}
			if ( '' !== $role && ! self::valid_role( $role ) ) {
				self::add( $f['role_invalid'], array( 'role' => Text::substr( $role, 40 ), 'el' => Identity::identifier( $el ) ) );
			}
			if ( $el->hasAttribute( 'tabindex' ) && is_numeric( trim( $el->getAttribute( 'tabindex' ) ) ) && (int) $el->getAttribute( 'tabindex' ) > 0 ) {
				self::add( $f['tabindex_positive'], array( 'tabindex' => (int) $el->getAttribute( 'tabindex' ), 'el' => Identity::identifier( $el ) ) );
			}
			if ( 'iframe' === $tag ) {
				self::iframe( $f, $el );
			}
			if ( 'video' === $tag || 'audio' === $tag ) {
				self::media( $f, $el, $tag );
			}
			if ( 'true' === strtolower( $el->getAttribute( 'aria-hidden' ) ) && self::focusable_inside( $el ) ) {
				self::add( $f['aria_hidden_focusable'], array( 'el' => Identity::identifier( $el ) ) );
			}
			if ( in_array( $tag, array( 'div', 'span', 'li', 'p', 'td', 'img' ), true ) && $el->hasAttribute( 'onclick' ) && '' === $role && ! $el->hasAttribute( 'tabindex' ) && ! self::inside( $el, array( 'a', 'button' ) ) ) {
				self::add( $f['custom_click'], array( 'el' => Identity::identifier( $el ) ) );
			}
			if ( 'a' === $tag && '' === $role && $el->hasAttribute( 'href' ) ) { // A "link" that behaves as a button: no real address, but wired to an action.
				$href = trim( $el->getAttribute( 'href' ) );
				if ( ( '#' === $href || 0 === stripos( $href, 'javascript:' ) ) && ( $el->hasAttribute( 'onclick' ) || $el->hasAttribute( 'aria-expanded' ) || $el->hasAttribute( 'aria-controls' ) || $el->hasAttribute( 'aria-haspopup' ) || $el->hasAttribute( 'data-toggle' ) || $el->hasAttribute( 'data-bs-toggle' ) ) ) {
					self::add( $f['link_no_action'], array( 'el' => Identity::identifier( $el ) ) );
				}
			}
			if ( in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) && ! self::has_content( $el ) ) {
				self::add( $f['empty_headings'], array( 'level' => (int) substr( $tag, 1 ), 'el' => Identity::identifier( $el ) ) );
			}
			if ( 'img' === $tag ) {
				self::image( $f, $el );
			}
			if ( 'input' === $tag ) {
				$type = strtolower( $el->getAttribute( 'type' ) );
				if ( in_array( $type, array( 'email', 'tel' ), true ) && '' === trim( $el->getAttribute( 'autocomplete' ) ) ) {
					self::add( $f['autocomplete_missing'], array( 'type' => $type, 'el' => Identity::identifier( $el ) ) );
				}
			}
			$style = self::style( $el );
			if ( $style && in_array( $tag, array( 'a', 'button', 'input', 'select', 'textarea', 'summary' ), true ) && self::outline_off( $style ) && ! self::outline_replaced( $style ) ) {
				self::add( $f['outline'], array( 'src' => 'style attribute', 'el' => Identity::identifier( $el ) ) );
			}
		}
		foreach ( array_keys( array_filter( $dups ) ) as $id ) {
			if ( isset( $ref_ids[ $id ] ) || isset( $labels_for[ $id ] ) ) {
				self::add( $f['id_dup_referenced'], array( 'id' => Text::substr( $id, 40 ) ) );
			}
		}
		foreach ( $labels_for as $for => $label ) {
			if ( ! isset( $ids[ $for ] ) ) {
				self::add( $f['ref_missing'], array( 'attr' => 'label for', 'target' => Text::substr( $for, 40 ), 'el' => Identity::identifier( $label ) ) );
			}
		}
		self::required_marks( $f, $nodes, $ids );
		self::style_blocks( $f, $xp );
		self::contrast( $f, $nodes );
		return $f;
	}

	/** Adds one sample (with a running count) to a fact list: [ 'n' => total, 'sample' => [...] ]. */
	private static function add( array &$list, array $item ) {
		if ( ! isset( $list['n'] ) ) {
			$list = array( 'n' => 0, 'sample' => array() );
		}
		++$list['n'];
		if ( count( $list['sample'] ) < self::SAMPLE ) {
			$list['sample'][] = $item;
		}
	}

	private static function inert( \DOMElement $el ) {
		for ( $n = $el; $n instanceof \DOMElement; $n = $n->parentNode ) {
			if ( in_array( strtolower( $n->tagName ), array( 'template', 'noscript', 'script', 'svg', 'head' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	private static function inside( \DOMElement $el, array $tags ) {
		for ( $n = $el->parentNode; $n instanceof \DOMElement; $n = $n->parentNode ) {
			if ( in_array( strtolower( $n->tagName ), $tags, true ) ) {
				return true;
			}
		}
		return false;
	}

	/** A role attribute is fine when at least one of its space-separated tokens is a defined role (the rest are fallbacks). */
	public static function valid_role( $role ) {
		foreach ( preg_split( '/\s+/', strtolower( trim( $role ) ), -1, PREG_SPLIT_NO_EMPTY ) as $tok ) {
			if ( in_array( $tok, self::ROLES, true ) || 0 === strpos( $tok, 'doc-' ) ) {
				return true;
			}
		}
		return false;
	}

	/** Text, an image with a name, or an aria-label inside the element. */
	private static function has_content( \DOMElement $el ) {
		if ( '' !== trim( (string) $el->textContent ) || '' !== trim( $el->getAttribute( 'aria-label' ) ) || '' !== trim( $el->getAttribute( 'aria-labelledby' ) ) ) {
			return true;
		}
		foreach ( $el->getElementsByTagName( 'img' ) as $img ) {
			if ( '' !== trim( $img->getAttribute( 'alt' ) ) ) {
				return true;
			}
		}
		foreach ( $el->getElementsByTagName( '*' ) as $c ) {
			if ( '' !== trim( $c->getAttribute( 'aria-label' ) ) ) {
				return true;
			}
		}
		return false;
	}

	private static function focusable_inside( \DOMElement $el ) {
		$cands = array_merge( array( $el ), iterator_to_array( $el->getElementsByTagName( '*' ), false ) );
		foreach ( $cands as $c ) {
			$tag = strtolower( $c->tagName );
			if ( $c->hasAttribute( 'disabled' ) || $c->hasAttribute( 'hidden' ) || '-1' === trim( $c->getAttribute( 'tabindex' ) ) || 1 === preg_match( '/display\s*:\s*none|visibility\s*:\s*hidden/i', $c->getAttribute( 'style' ) ) ) {
				continue;
			}
			$natural = ( 'a' === $tag && $c->hasAttribute( 'href' ) ) || in_array( $tag, array( 'button', 'select', 'textarea' ), true ) || ( 'input' === $tag && 'hidden' !== strtolower( $c->getAttribute( 'type' ) ) );
			if ( $natural || ( $c->hasAttribute( 'tabindex' ) && is_numeric( trim( $c->getAttribute( 'tabindex' ) ) ) && (int) $c->getAttribute( 'tabindex' ) >= 0 ) ) {
				return true;
			}
		}
		return false;
	}

	private static function iframe( array &$f, \DOMElement $el ) {
		$hidden = 'true' === strtolower( $el->getAttribute( 'aria-hidden' ) ) || '-1' === trim( $el->getAttribute( 'tabindex' ) ) || $el->hasAttribute( 'hidden' )
			|| 1 === preg_match( '/display\s*:\s*none|visibility\s*:\s*hidden/i', $el->getAttribute( 'style' ) ) || in_array( trim( $el->getAttribute( 'width' ) ), array( '0', '1' ), true ) || in_array( trim( $el->getAttribute( 'height' ) ), array( '0', '1' ), true );
		$src = strtolower( $el->getAttribute( 'src' ) . ' ' . $el->getAttribute( 'data-src' ) );
		if ( 1 === preg_match( '#(youtube(-nocookie)?\.com/embed|youtu\.be|player\.vimeo\.com|dailymotion\.com/embed|wistia|soundcloud\.com/player|spotify\.com/embed)#', $src ) ) {
			self::add( $f['embeds'], array( 'host' => Text::substr( (string) wp_parse_url( $el->getAttribute( 'src' ) ? $el->getAttribute( 'src' ) : $el->getAttribute( 'data-src' ), PHP_URL_HOST ), 60 ) ) );
		}
		if ( $hidden ) {
			return;
		}
		if ( '' === trim( $el->getAttribute( 'title' ) ) && '' === trim( $el->getAttribute( 'aria-label' ) ) && '' === trim( $el->getAttribute( 'aria-labelledby' ) ) ) {
			self::add( $f['iframe_no_title'], array( 'el' => Identity::identifier( $el ) ) );
		}
	}

	private static function media( array &$f, \DOMElement $el, $tag ) {
		$tracks = false;
		foreach ( $el->getElementsByTagName( 'track' ) as $t ) {
			if ( in_array( strtolower( $t->getAttribute( 'kind' ) ), array( 'captions', 'subtitles' ), true ) ) {
				$tracks = true;
			}
		}
		if ( 'video' === $tag ) {
			++$f['videos'];
			if ( ! $tracks && ! $el->hasAttribute( 'muted' ) ) {
				self::add( $f['video_no_track'], array( 'el' => Identity::identifier( $el ) ) );
			}
		} elseif ( ! $tracks ) {
			self::add( $f['video_no_track'], array( 'el' => Identity::identifier( $el ), 'audio' => true ) );
		}
		if ( $el->hasAttribute( 'autoplay' ) && ! $el->hasAttribute( 'muted' ) && ! $el->hasAttribute( 'controls' ) ) {
			self::add( $f['autoplay'], array( 'el' => Identity::identifier( $el ) ) );
		}
	}

	private static function image( array &$f, \DOMElement $el ) {
		if ( ! $el->hasAttribute( 'alt' ) ) {
			return;
		}
		$alt = trim( $el->getAttribute( 'alt' ) );
		if ( '' === $alt ) {
			$decor = in_array( strtolower( $el->getAttribute( 'role' ) ), array( 'presentation', 'none' ), true ) || 'true' === strtolower( $el->getAttribute( 'aria-hidden' ) );
			if ( ! $decor && '' !== trim( $el->getAttribute( 'title' ) ) ) {
				self::add( $f['alt']['empty_title'], array( 'el' => Identity::identifier( $el ), 'title' => Text::substr( trim( $el->getAttribute( 'title' ) ), 60 ) ) );
			}
			return;
		}
		if ( 1 === preg_match( '/\.(jpe?g|png|gif|webp|svg|avif|bmp)$/i', $alt ) || 1 === preg_match( '/^(img|image|photo|picture|pic|dsc|screenshot|banner|graphic|icon)[-_ ]?\d*$/i', $alt ) ) {
			self::add( $f['alt']['filename'], array( 'el' => Identity::identifier( $el ), 'alt' => Text::substr( $alt, 60 ) ) );
		}
	}

	/** A label marked with an asterisk whose control is not marked required for assistive technology. */
	private static function required_marks( array &$f, array $nodes, array $ids ) {
		foreach ( $nodes as $el ) {
			if ( 'label' !== strtolower( $el->tagName ) || false === strpos( (string) $el->textContent, '*' ) ) {
				continue;
			}
			$target = null;
			if ( '' !== $el->getAttribute( 'for' ) && isset( $ids[ $el->getAttribute( 'for' ) ] ) ) {
				$target = $el->ownerDocument->getElementById( $el->getAttribute( 'for' ) );
			}
			if ( ! $target ) {
				foreach ( array( 'input', 'select', 'textarea' ) as $t ) {
					$c = $el->getElementsByTagName( $t );
					if ( $c->length ) {
						$target = $c->item( 0 );
						break;
					}
				}
			}
			if ( $target instanceof \DOMElement && ! $target->hasAttribute( 'required' ) && 'true' !== strtolower( $target->getAttribute( 'aria-required' ) ) ) {
				self::add( $f['required_unmarked'], array( 'el' => Identity::identifier( $target ) ) );
			}
		}
	}

	/** Focus outlines turned off in the page's own <style> blocks (external stylesheets are not read). */
	private static function style_blocks( array &$f, \DOMXPath $xp ) {
		$css = '';
		foreach ( $xp->query( '//style' ) as $s ) {
			$css .= ' ' . $s->textContent;
			if ( strlen( $css ) > 200000 ) {
				break;
			}
		}
		if ( '' === trim( $css ) ) {
			return;
		}
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );
		$vis = false;
		$off = array();
		if ( preg_match_all( '/([^{}]+)\{([^{}]*)\}/', (string) $css, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $rule ) {
				$sel  = strtolower( trim( $rule[1] ) );
				$body = strtolower( $rule[2] );
				if ( false !== strpos( $sel, ':focus-visible' ) && self::draws_focus( self::decl( $body ) ) ) {
					$vis = true;
				}
				if ( false !== strpos( $sel, ':focus' ) && false === strpos( $sel, ':focus-visible' ) && false === strpos( $sel, ':focus-within' ) && self::outline_off( self::decl( $body ) ) && ! self::outline_replaced( self::decl( $body ) ) ) {
					foreach ( explode( ',', $sel ) as $one ) {
						if ( self::targets_controls( $one ) ) {
							$off[] = Text::substr( preg_replace( '/\s+/', ' ', trim( $one ) ), 60 );
						}
					}
				}
			}
		}
		if ( ! $vis ) { // A :focus-visible rule that draws something means the author handled keyboard focus; do not guess about the rest.
			foreach ( array_slice( array_unique( $off ), 0, self::SAMPLE ) as $sel ) {
				self::add( $f['outline'], array( 'src' => 'style block', 'el' => $sel ) );
			}
		}
	}

	/**
	 * Whether a `:focus` selector is about things people tab to: links, buttons and form fields (or every element). A class or id alone could be
	 * a container that only receives focus from a script (as WordPress's navigation does), so it is not guessed at.
	 */
	private static function targets_controls( $selector ) {
		$parts = preg_split( '/[\s>+~]+/', trim( $selector ), -1, PREG_SPLIT_NO_EMPTY );
		$last  = $parts ? end( $parts ) : '';
		$base  = preg_replace( '/:focus.*$/', '', $last );
		if ( '' === $base || '*' === $base ) {
			return true;
		}
		return 1 === preg_match( '/^(a|button|input|select|textarea|summary)($|[.#\[:])/', $base );
	}

	/** Declarations of one style attribute or rule body as property => value (lower case, !important removed). */
	public static function decl( $css ) {
		$out = array();
		foreach ( explode( ';', (string) $css ) as $d ) {
			if ( false === strpos( $d, ':' ) ) {
				continue;
			}
			list( $k, $v ) = array_map( 'trim', explode( ':', $d, 2 ) );
			$out[ strtolower( $k ) ] = trim( preg_replace( '/!important/i', '', strtolower( $v ) ) );
		}
		return $out;
	}

	private static function style( \DOMElement $el ) {
		return $el->hasAttribute( 'style' ) ? self::decl( $el->getAttribute( 'style' ) ) : array();
	}

	private static function outline_off( array $d ) {
		foreach ( array( 'outline', 'outline-style', 'outline-width' ) as $k ) {
			if ( isset( $d[ $k ] ) && 1 === preg_match( '/^(none|0|0px|0em|hidden)(\s|$)/', $d[ $k ] ) ) {
				return true;
			}
		}
		return false;
	}

	/** A focus style is drawn: an outline that is not switched off, or something else that shows focus. */
	private static function draws_focus( array $d ) {
		return ( isset( $d['outline'] ) && ! self::outline_off( $d ) ) || self::outline_replaced( $d );
	}

	/** Something else that can show focus is set in the same declaration block. */
	private static function outline_replaced( array $d ) {
		foreach ( array( 'box-shadow', 'border', 'border-color', 'border-bottom', 'text-decoration', 'background', 'background-color' ) as $k ) {
			if ( isset( $d[ $k ] ) && ! in_array( $d[ $k ], array( 'none', '0', 'transparent', 'inherit', 'initial', 'unset' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Contrast of text whose colour AND background are both written as solid colours in inline style attributes on the element or its
	 * ancestors. Anything else (style sheets, images, gradients, transparency, opacity, unknown units) is not tested. The result is a
	 * calculation from the declared colours, never a measurement of what a visitor sees.
	 */
	private static function contrast( array &$f, array $nodes ) {
		foreach ( $nodes as $el ) {
			$own = '';
			foreach ( $el->childNodes as $c ) {
				if ( XML_TEXT_NODE === $c->nodeType ) {
					$own .= $c->nodeValue;
				}
			}
			if ( '' === trim( $own ) ) {
				continue;
			}
			$fg = null;
			$bg = null;
			$unknown = false;
			$size = null;
			$bold = false;
			for ( $n = $el; $n instanceof \DOMElement; $n = $n->parentNode ) {
				$d = self::style( $n );
				if ( isset( $d['opacity'] ) && (float) $d['opacity'] < 1 ) {
					$unknown = true;
				}
				if ( null === $fg && isset( $d['color'] ) ) {
					$fg = self::color( $d['color'] );
					if ( null === $fg ) {
						$unknown = true;
					}
				}
				if ( null === $size && isset( $d['font-size'] ) ) {
					$size = self::px( $d['font-size'] );
				}
				if ( ! $bold && isset( $d['font-weight'] ) && ( 'bold' === $d['font-weight'] || (int) $d['font-weight'] >= 700 ) ) {
					$bold = true;
				}
				if ( null === $bg ) {
					$img = ( isset( $d['background-image'] ) && 'none' !== $d['background-image'] ) || ( isset( $d['background'] ) && 1 === preg_match( '/url\(|gradient/', $d['background'] ) );
					$col = isset( $d['background-color'] ) ? $d['background-color'] : ( isset( $d['background'] ) ? preg_replace( '/\s.*$/', '', $d['background'] ) : null );
					if ( $img ) {
						$unknown = true;
						break;
					}
					if ( null !== $col ) {
						$bg = self::color( $col );
						if ( null === $bg ) {
							$unknown = true;
							break;
						}
					}
				}
				if ( null !== $bg && null !== $fg ) {
					break;
				}
			}
			if ( $unknown || null === $fg || null === $bg ) {
				continue; // Not tested: some part of the picture is unknown.
			}
			++$f['contrast']['tested'];
			$ratio = self::ratio( $fg, $bg );
			$large = null !== $size && ( $size >= 24 || ( $bold && $size >= 18.66 ) );
			if ( null === $size ) {
				$need = 3.0; // Unknown size (em, rem, %, keywords or none): only flag what fails even the large-text threshold.
			} else {
				$need = $large ? 3.0 : 4.5;
			}
			if ( $ratio < $need ) {
				self::add( $f['contrast']['low'], array( 'el' => Identity::identifier( $el ), 'ratio' => round( $ratio, 2 ), 'need' => $need, 'size' => null === $size ? 'unknown' : ( $large ? 'large' : 'normal' ) ) );
			}
		}
	}

	private static function px( $v ) {
		if ( 1 === preg_match( '/^([\d.]+)px$/', $v, $m ) ) {
			return (float) $m[1];
		}
		if ( 1 === preg_match( '/^([\d.]+)pt$/', $v, $m ) ) {
			return (float) $m[1] * 4 / 3;
		}
		return null;
	}

	/** @return int[]|null r, g, b of a solid colour, or null (transparent, translucent, unsupported). */
	public static function color( $v ) {
		$v = strtolower( trim( $v ) );
		static $named = array( 'black' => array( 0, 0, 0 ), 'white' => array( 255, 255, 255 ), 'red' => array( 255, 0, 0 ), 'green' => array( 0, 128, 0 ), 'blue' => array( 0, 0, 255 ), 'yellow' => array( 255, 255, 0 ), 'gray' => array( 128, 128, 128 ), 'grey' => array( 128, 128, 128 ), 'silver' => array( 192, 192, 192 ), 'navy' => array( 0, 0, 128 ), 'maroon' => array( 128, 0, 0 ), 'orange' => array( 255, 165, 0 ), 'purple' => array( 128, 0, 128 ), 'teal' => array( 0, 128, 128 ), 'lime' => array( 0, 255, 0 ), 'aqua' => array( 0, 255, 255 ), 'fuchsia' => array( 255, 0, 255 ), 'olive' => array( 128, 128, 0 ) );
		if ( isset( $named[ $v ] ) ) {
			return $named[ $v ];
		}
		if ( 1 === preg_match( '/^#([0-9a-f]{3})$/', $v, $m ) ) {
			return array( hexdec( $m[1][0] . $m[1][0] ), hexdec( $m[1][1] . $m[1][1] ), hexdec( $m[1][2] . $m[1][2] ) );
		}
		if ( 1 === preg_match( '/^#([0-9a-f]{6})$/', $v, $m ) ) {
			return array( hexdec( substr( $m[1], 0, 2 ) ), hexdec( substr( $m[1], 2, 2 ) ), hexdec( substr( $m[1], 4, 2 ) ) );
		}
		if ( 1 === preg_match( '/^rgba?\(\s*(\d{1,3})\s*[, ]\s*(\d{1,3})\s*[, ]\s*(\d{1,3})\s*(?:[,\/]\s*([\d.]+%?)\s*)?\)$/', $v, $m ) ) {
			if ( isset( $m[4] ) && '' !== $m[4] && ( false !== strpos( $m[4], '%' ) ? (float) $m[4] < 100 : (float) $m[4] < 1 ) ) {
				return null;
			}
			return array( min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
		}
		return null;
	}

	/** WCAG contrast ratio of two sRGB colours (1 to 21). */
	public static function ratio( array $a, array $b ) {
		$l1 = self::luminance( $a );
		$l2 = self::luminance( $b );
		return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
	}

	private static function luminance( array $c ) {
		$v = array();
		foreach ( $c as $i => $x ) {
			$x       = $x / 255;
			$v[ $i ] = $x <= 0.03928 ? $x / 12.92 : pow( ( $x + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $v[0] + 0.7152 * $v[1] + 0.0722 * $v[2];
	}
}
