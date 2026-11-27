<?php
namespace UserFlo\Audit;

use UserFlo\Core\Text;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the HTML a page returned and lists what is on it: page-level facts (title, description, canonical, robots, headings) and the interactive
 * and media elements, each with the identifier the tracker would give it. No network, no WordPress calls: pure functions on a string, so it is
 * tested with fixtures. The checks that turn these facts into findings live elsewhere (Lite: three basic ones, Pro: the full set).
 */
final class Parser {

	const MAX_BYTES    = 2097152; // 2 MB: larger documents are not parsed.
	const MAX_ELEMENTS = 1500;

	/**
	 * @param string $html      Response body.
	 * @param string $base      Absolute URL the page was fetched from (to resolve relative addresses).
	 * @param array  $opts      host (the site's host), dl (array of download extensions), keep (query parameters the tracker keeps).
	 * @return array|null Null when the body is not parseable HTML.
	 */
	public static function parse( $html, $base, array $opts = array() ) {
		$html = (string) $html;
		if ( '' === trim( $html ) || strlen( $html ) > self::MAX_BYTES || false === stripos( $html, '<' ) ) {
			return null;
		}
		$opts = array_merge( array( 'host' => '', 'dl' => array( 'pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'mp3', 'mp4', 'dmg', 'exe' ), 'keep' => array( 'p', 'page_id', 'cat', 'tag', 'post_type', 'product', 'paged' ) ), $opts );
		$dom  = new \DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$ok   = $dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $ok || ! $dom->documentElement ) {
			return null;
		}
		$xp  = new \DOMXPath( $dom );
		$out = array(
			'title'       => self::text( $xp, '//head/title' ),
			'description' => self::meta( $xp, 'description' ),
			'robots'      => strtolower( self::meta( $xp, 'robots' ) ),
			'canonical'   => '',
			'og'          => '' !== self::property( $xp, 'og:title' ),
			'lang'        => $dom->documentElement->getAttribute( 'lang' ),
			'headings'    => array(),
			'elements'    => array(),
			'truncated'   => false,
		);
		foreach ( $xp->query( '//head/link[@rel]' ) as $l ) {
			if ( in_array( 'canonical', preg_split( '/\s+/', strtolower( $l->getAttribute( 'rel' ) ) ), true ) ) {
				$out['canonical'] = trim( $l->getAttribute( 'href' ) );
				break;
			}
		}
		foreach ( $xp->query( '//body//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]' ) as $h ) {
			if ( self::inert( $h ) ) {
				continue;
			}
			$out['headings'][] = array( 'level' => (int) substr( $h->tagName, 1 ), 'text' => Text::substr( trim( (string) preg_replace( '/\s+/u', ' ', $h->textContent ) ), 80 ) );
		}
		$q = "//body//*[self::a[@href] or self::button or self::input[@type='button' or @type='submit' or @type='image'] or @role='button' or @role='link' or @role='tab' or @role='menuitem' or @role='switch' or @aria-expanded or @aria-controls or self::summary or @data-userflo or self::form or self::img or self::video or self::audio]";
		$seen = array();
		foreach ( $xp->query( $q ) as $el ) {
			if ( ! $el instanceof \DOMElement || self::inert( $el ) ) {
				continue;
			}
			$oid = spl_object_id( $el );
			if ( isset( $seen[ $oid ] ) ) {
				continue;
			}
			$seen[ $oid ] = true;
			if ( count( $out['elements'] ) >= self::MAX_ELEMENTS ) {
				$out['truncated'] = true;
				break;
			}
			$row = self::element( $el, $base, $opts );
			if ( $row ) {
				$out['elements'][] = $row;
			}
		}
		$out['a11y']  = A11yFacts::read( $dom, $xp );
		$out['stats'] = self::stats( $out );
		return $out;
	}

	/**
	 * Counts of what is on the page, kept with the scan so the audit table can show them without reading the page again. Every number is a
	 * plain count of what the parser found (the first MAX_ELEMENTS interactive elements and images; `truncated` says when that cap was hit).
	 *
	 * @param array $page Parsed page.
	 * @return array
	 */
	private static function stats( array $page ) {
		$st = array( 'h1' => 0, 'h2' => 0, 'h3' => 0, 'h4' => 0, 'h5' => 0, 'h6' => 0, 'links_internal' => 0, 'links_external' => 0, 'links_other' => 0, 'files' => 0, 'images' => 0, 'img_no_alt' => 0, 'img_empty_alt' => 0, 'img_no_size' => 0, 'buttons' => 0, 'forms' => 0, 'media' => 0, 'truncated' => ! empty( $page['truncated'] ) );
		foreach ( $page['headings'] as $hd ) {
			$k = 'h' . (int) $hd['level'];
			if ( isset( $st[ $k ] ) ) {
				++$st[ $k ];
			}
		}
		foreach ( $page['elements'] as $e ) {
			switch ( $e['kind'] ) {
				case 'link':
					++$st['links_internal'];
					break;
				case 'external':
					++$st['links_external'];
					break;
				case 'file':
					++$st['files'];
					break;
				case 'contact':
				case 'anchor':
					++$st['links_other'];
					break;
				case 'button':
					++$st['buttons'];
					break;
				case 'form':
					++$st['forms'];
					break;
				case 'media':
					++$st['media'];
					break;
				case 'image':
					++$st['images'];
					$f = isset( $e['flags'] ) ? $e['flags'] : array();
					if ( empty( $f['decorative'] ) && isset( $f['alt'] ) && 'missing' === $f['alt'] ) {
						++$st['img_no_alt'];
					} elseif ( isset( $f['alt'] ) && 'empty' === $f['alt'] && empty( $f['decorative'] ) ) {
						++$st['img_empty_alt'];
					}
					if ( isset( $f['sized'] ) && ! $f['sized'] ) {
						++$st['img_no_size'];
					}
					break;
			}
		}
		$st['title_len'] = self::len( (string) $page['title'] );
		$st['desc_len']  = self::len( (string) $page['description'] );
		$st['og']        = ! empty( $page['og'] );
		$st['lang']      = (string) $page['lang'];
		return $st;
	}

	private static function len( $v ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $v, 'UTF-8' ) : strlen( $v );
	}

	/** Inside a script, template, noscript, svg or head: not part of the interactive page. */
	private static function inert( \DOMElement $el ) {
		for ( $n = $el; $n instanceof \DOMElement; $n = $n->parentNode ) {
			if ( in_array( strtolower( $n->tagName ), array( 'template', 'noscript', 'script', 'svg', 'head' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	private static function text( \DOMXPath $xp, $path ) {
		$n = $xp->query( $path );
		return ( $n && $n->length ) ? Text::substr( trim( (string) preg_replace( '/\s+/u', ' ', $n->item( 0 )->textContent ) ), 255 ) : '';
	}

	private static function meta( \DOMXPath $xp, $name ) {
		foreach ( $xp->query( '//head/meta[@name]' ) as $m ) {
			if ( strtolower( $m->getAttribute( 'name' ) ) === $name ) {
				return trim( $m->getAttribute( 'content' ) );
			}
		}
		return '';
	}

	private static function property( \DOMXPath $xp, $name ) {
		foreach ( $xp->query( '//head/meta[@property]' ) as $m ) {
			if ( strtolower( $m->getAttribute( 'property' ) ) === $name ) {
				return trim( $m->getAttribute( 'content' ) );
			}
		}
		return '';
	}

	/** Accessible-name test for the cases that can be decided from markup. */
	private static function has_name( \DOMElement $el ) {
		foreach ( array( 'aria-label', 'aria-labelledby', 'title' ) as $a ) {
			if ( '' !== trim( $el->getAttribute( $a ) ) ) {
				return true;
			}
		}
		if ( 'input' === strtolower( $el->tagName ) ) {
			$type = strtolower( $el->getAttribute( 'type' ) );
			if ( in_array( $type, array( 'submit', 'reset' ), true ) ) {
				return true; // Browsers give these buttons a default name ("Submit", "Reset") when there is no value.
			}
			return '' !== trim( 'image' === $type ? $el->getAttribute( 'alt' ) : $el->getAttribute( 'value' ) );
		}
		if ( '' !== trim( $el->textContent ) ) {
			return true;
		}
		foreach ( $el->getElementsByTagName( 'img' ) as $img ) {
			if ( '' !== trim( $img->getAttribute( 'alt' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** Makes an address absolute against the page it was found on. @return array|null scheme, host, path, query. */
	public static function resolve( $href, $base ) {
		$href = trim( $href );
		$b    = wp_parse_url( $base );
		if ( ! $b || empty( $b['host'] ) ) {
			return null;
		}
		$scheme = isset( $b['scheme'] ) ? $b['scheme'] : 'http';
		if ( 0 === strpos( $href, '//' ) ) {
			$href = $scheme . ':' . $href;
		}
		if ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $href ) ) {
			$u = wp_parse_url( $href );
			if ( ! $u || empty( $u['scheme'] ) ) {
				return null;
			}
			return array( 'scheme' => strtolower( $u['scheme'] ), 'host' => isset( $u['host'] ) ? strtolower( $u['host'] ) : '', 'path' => isset( $u['path'] ) ? $u['path'] : '', 'query' => isset( $u['query'] ) ? $u['query'] : '' );
		}
		$q = '';
		if ( false !== ( $i = strpos( $href, '#' ) ) ) {
			$href = substr( $href, 0, $i );
		}
		if ( false !== ( $i = strpos( $href, '?' ) ) ) {
			$q    = substr( $href, $i + 1 );
			$href = substr( $href, 0, $i );
		}
		if ( '' === $href ) {
			$path = isset( $b['path'] ) ? $b['path'] : '/';
		} elseif ( '/' === $href[0] ) {
			$path = $href;
		} else {
			$dir  = isset( $b['path'] ) ? preg_replace( '#[^/]*$#', '', $b['path'] ) : '/';
			$path = $dir . $href;
		}
		$stack = array();
		foreach ( explode( '/', $path ) as $seg ) {
			if ( '..' === $seg ) {
				array_pop( $stack );
			} elseif ( '.' !== $seg ) {
				$stack[] = $seg;
			}
		}
		$path = implode( '/', $stack );
		return array( 'scheme' => $scheme, 'host' => strtolower( $b['host'] ), 'path' => '' === $path ? '/' : ( '/' === $path[0] ? $path : '/' . $path ), 'query' => $q );
	}

	/** The tracker's cleanPath(): path plus only the query parameters it keeps. */
	public static function clean_path( $path, $query, array $keep ) {
		$q2 = array();
		foreach ( '' === $query ? array() : explode( '&', $query ) as $p ) {
			$k = explode( '=', $p )[0];
			if ( 1 === preg_match( '/^utm_/i', $k ) || in_array( $k, $keep, true ) ) {
				$q2[] = $p;
			}
		}
		return Text::substr( $path . ( $q2 ? '?' . implode( '&', $q2 ) : '' ), 500 );
	}

	private static function element( \DOMElement $el, $base, array $opts ) {
		$tag = strtolower( $el->tagName );
		$row = array( 'kind' => 'control', 'label' => '', 'identifier' => Identity::identifier( $el ), 'sig' => '', 'value_sig' => '', 'href' => '', 'flags' => array() );
		if ( 'img' === $tag ) {
			$src = trim( $el->getAttribute( 'src' ) );
			if ( '' === $src ) {
				$src = trim( $el->getAttribute( 'data-src' ) );
			}
			$name = '' === $src ? '' : basename( (string) wp_parse_url( $src, PHP_URL_PATH ) );
			$alt  = $el->hasAttribute( 'alt' ) ? $el->getAttribute( 'alt' ) : null;
			$row  = array_merge( $row, array(
				'kind'  => 'image',
				'label' => Text::substr( null !== $alt && '' !== trim( $alt ) ? trim( $alt ) : $name, 80 ),
				'sig'   => $name,
				'flags' => array(
					'alt'        => null === $alt ? 'missing' : ( '' === trim( $alt ) ? 'empty' : 'text' ),
					'decorative' => in_array( strtolower( $el->getAttribute( 'role' ) ), array( 'presentation', 'none' ), true ) || 'true' === strtolower( $el->getAttribute( 'aria-hidden' ) ),
					'linked'     => self::in_link( $el ),
					'sized'      => $el->hasAttribute( 'width' ) && $el->hasAttribute( 'height' ),
				),
			) );
			return $row;
		}
		if ( 'video' === $tag || 'audio' === $tag ) {
			$src = trim( $el->getAttribute( 'src' ) );
			if ( '' === $src ) {
				foreach ( $el->getElementsByTagName( 'source' ) as $s ) {
					$src = trim( $s->getAttribute( 'src' ) );
					if ( '' !== $src ) {
						break;
					}
				}
			}
			$path = '' === $src ? '' : (string) wp_parse_url( $src, PHP_URL_PATH );
			return array_merge( $row, array( 'kind' => 'media', 'label' => Text::substr( $el->getAttribute( 'title' ) ? $el->getAttribute( 'title' ) : $tag, 80 ), 'sig' => basename( $path ), 'value_sig' => $path ) );
		}
		if ( 'form' === $tag ) {
			$wrap = null;
			for ( $n = $el->parentNode; $n instanceof \DOMElement; $n = $n->parentNode ) {
				if ( $n->hasAttribute( 'data-userflo-form' ) ) {
					$wrap = $n;
					break;
				}
			}
			$name = $wrap ? $wrap->getAttribute( 'data-userflo-form' ) : '';
			foreach ( array( 'aria-label', 'name', 'id' ) as $a ) {
				if ( '' === $name ) {
					$name = $el->getAttribute( $a );
				}
			}
			return array_merge( $row, array( 'kind' => 'form', 'label' => Text::substr( '' === $name ? 'form' : $name, 80 ), 'sig' => $el->getAttribute( 'id' ) . '|' . $el->getAttribute( 'name' ), 'flags' => array( 'fields' => self::count_fields( $el ), 'fields_without_label' => self::unlabelled_fields( $el ) ) ) );
		}
		$row['label'] = Identity::label( $el );
		if ( 'a' === $tag ) {
			$href = trim( $el->getAttribute( 'href' ) );
			$row['href'] = Text::substr( $href, 500 );
			if ( '' === $href || 1 === preg_match( '/^(javascript:|#)/i', $href ) ) {
				$row['kind']  = 'anchor';
				$row['sig']   = $href;
				$row['flags'] = array( 'name' => self::has_name( $el ), 'img_only' => self::img_only( $el ), 'empty_href' => '' === $href || '#' === $href );
				return $row;
			}
			$u = self::resolve( $href, $base );
			$row['flags'] = array( 'name' => self::has_name( $el ), 'img_only' => self::img_only( $el ), 'empty_href' => false, 'generic' => 1 === preg_match( '/^(click here|here|read more|more|learn more|link|this)$/i', $row['label'] ) );
			if ( ! $u ) {
				$row['kind'] = 'anchor';
				$row['sig']  = $href;
				return $row;
			}
			if ( in_array( $u['scheme'], array( 'mailto', 'tel' ), true ) ) {
				$row['kind']      = 'contact';
				$row['value_sig'] = $u['scheme'] . ':';
				$row['sig']       = $u['scheme'] . ':'; // Never the address or number itself.
				$row['href']      = $u['scheme'] . ':';
				return $row;
			}
			$ext = strtolower( pathinfo( $u['path'], PATHINFO_EXTENSION ) );
			if ( $el->hasAttribute( 'download' ) || in_array( $ext, $opts['dl'], true ) ) {
				$row['kind']      = 'file';
				$row['value_sig'] = $u['path'];
			} elseif ( '' !== $opts['host'] && $u['host'] !== strtolower( $opts['host'] ) ) {
				$row['kind']      = 'external';
				$row['value_sig'] = Text::substr( $u['scheme'] . '://' . $u['host'] . $u['path'], 500 );
			} else {
				$row['kind']      = 'link';
				$row['value_sig'] = self::clean_path( $u['path'], $u['query'], $opts['keep'] );
			}
			$row['sig'] = $row['value_sig'];
			return $row;
		}
		$row['kind']  = in_array( $tag, array( 'button', 'input', 'summary' ), true ) || in_array( strtolower( $el->getAttribute( 'role' ) ), array( 'button', 'link', 'tab', 'menuitem', 'switch' ), true ) ? 'button' : 'control';
		$row['sig']   = strtolower( Text::substr( $row['label'], 40 ) );
		$row['flags'] = array( 'name' => self::has_name( $el ), 'img_only' => self::img_only( $el ) );
		return $row;
	}

	/** The element holds an image and no text: when it has no name, the picture is what is missing its text alternative. */
	private static function img_only( \DOMElement $el ) {
		return '' === trim( (string) $el->textContent ) && $el->getElementsByTagName( 'img' )->length > 0;
	}

	private static function in_link( \DOMElement $el ) {
		for ( $n = $el->parentNode; $n instanceof \DOMElement; $n = $n->parentNode ) {
			if ( 'a' === strtolower( $n->tagName ) && $n->hasAttribute( 'href' ) ) {
				return true;
			}
		}
		return false;
	}

	/** Visible fields of a form (everything but hidden inputs and buttons). */
	private static function count_fields( \DOMElement $form ) {
		$n = 0;
		foreach ( array( 'input', 'select', 'textarea' ) as $t ) {
			foreach ( $form->getElementsByTagName( $t ) as $f ) {
				if ( ! in_array( strtolower( $f->getAttribute( 'type' ) ), array( 'hidden', 'submit', 'button', 'image', 'reset' ), true ) ) {
					++$n;
				}
			}
		}
		return $n;
	}

	/** Visible form fields with no label, aria-label, title or placeholder-only naming. Counted, not listed. */
	private static function unlabelled_fields( \DOMElement $form ) {
		$labels = array();
		$doc    = $form->ownerDocument;
		foreach ( $doc->getElementsByTagName( 'label' ) as $l ) {
			if ( '' !== $l->getAttribute( 'for' ) ) {
				$labels[ $l->getAttribute( 'for' ) ] = true;
			}
		}
		$n = 0;
		foreach ( array( 'input', 'select', 'textarea' ) as $t ) {
			foreach ( $form->getElementsByTagName( $t ) as $f ) {
				$type = strtolower( $f->getAttribute( 'type' ) );
				if ( in_array( $type, array( 'hidden', 'submit', 'button', 'image', 'reset' ), true ) ) {
					continue;
				}
				$id = $f->getAttribute( 'id' );
				$wrapped = false;
				for ( $p = $f->parentNode; $p instanceof \DOMElement; $p = $p->parentNode ) {
					if ( 'label' === strtolower( $p->tagName ) ) {
						$wrapped = true;
						break;
					}
				}
				if ( ! $wrapped && ( '' === $id || ! isset( $labels[ $id ] ) ) && '' === trim( $f->getAttribute( 'aria-label' ) ) && '' === trim( $f->getAttribute( 'aria-labelledby' ) ) && '' === trim( $f->getAttribute( 'title' ) ) ) {
					++$n;
				}
			}
		}
		return $n;
	}
}
