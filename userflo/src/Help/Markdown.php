<?php
namespace UserFlo\Help;

defined( 'ABSPATH' ) || exit;

/** Tiny, safe Markdown-to-HTML converter for the bundled guides (headings, lists, tables, code, links, emphasis). Input is trusted plugin files; output is still run through wp_kses_post. */
final class Markdown {

	private static function inline( $s ) {
		$s = esc_html( $s );
		$s = preg_replace_callback( '/`([^`]+)`/', static function ( $m ) { return '<code>' . $m[1] . '</code>'; }, $s );
		$s = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s );
		$s = preg_replace( '/(?<![*\w])\*([^*\s][^*]*)\*(?!\*)/', '<em>$1</em>', $s );
		$s = preg_replace_callback( '/\[([^\]]+)\]\(([^)\s]+)\)/', static function ( $m ) {
			$href = html_entity_decode( $m[2], ENT_QUOTES );
			if ( preg_match( '#(?:^|/)([A-Za-z0-9_-]+)\.md(?:\#.*)?$#', $href, $g ) ) { // Link to another guide.
				return '<a href="#" data-guide="' . esc_attr( strtolower( $g[1] ) ) . '">' . $m[1] . '</a>';
			}
			if ( ! preg_match( '#^(https?://|mailto:)#i', $href ) ) {
				return $m[1];
			}
			return '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
		}, $s );
		// Bare URLs (not already inside a tag).
		$s = preg_replace_callback( '#(?<![">=/])(https?://[^\s<)]+)#', static function ( $m ) {
			return '<a href="' . esc_url( $m[1] ) . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
		}, $s );
		return $s;
	}

	public static function render( $md ) {
		$lines = preg_split( '/\R/', str_replace( "\t", '    ', (string) $md ) );
		$out   = '';
		$i     = 0;
		$n     = count( $lines );
		while ( $i < $n ) {
			$l = $lines[ $i ];
			if ( preg_match( '/^```/', $l ) ) {
				$code = array();
				for ( ++$i; $i < $n && ! preg_match( '/^```/', $lines[ $i ] ); $i++ ) {
					$code[] = $lines[ $i ];
				}
				++$i;
				$out .= '<pre><code>' . esc_html( implode( "\n", $code ) ) . '</code></pre>';
				continue;
			}
			if ( preg_match( '/^(#{1,4})\s+(.*)$/', $l, $m ) ) {
				$lv   = min( 5, strlen( $m[1] ) + 1 );
				$out .= "<h{$lv}>" . self::inline( $m[2] ) . "</h{$lv}>";
				++$i;
				continue;
			}
			if ( preg_match( '/^\s*[-*]\s+/', $l ) || preg_match( '/^\s*\d+\.\s+/', $l ) ) {
				$ordered = (bool) preg_match( '/^\s*\d+\.\s+/', $l );
				$out    .= $ordered ? '<ol>' : '<ul>';
				while ( $i < $n && ( preg_match( '/^\s*[-*]\s+(.*)$/', $lines[ $i ], $m ) || preg_match( '/^\s*\d+\.\s+(.*)$/', $lines[ $i ], $m ) ) ) {
					$item = $m[1];
					for ( ++$i; $i < $n && preg_match( '/^\s{2,}\S/', $lines[ $i ] ) && ! preg_match( '/^\s*([-*]|\d+\.)\s+/', $lines[ $i ] ); $i++ ) {
						$item .= ' ' . trim( $lines[ $i ] );
					}
					$out .= '<li>' . self::inline( $item ) . '</li>';
				}
				$out .= $ordered ? '</ol>' : '</ul>';
				continue;
			}
			if ( preg_match( '/^\|.*\|\s*$/', $l ) && $i + 1 < $n && preg_match( '/^\|[\s:|-]+\|\s*$/', $lines[ $i + 1 ] ) ) {
				$cells = static function ( $row ) { return array_map( 'trim', explode( '|', trim( trim( $row ), '|' ) ) ); };
				$out  .= '<table class="widefat striped"><thead><tr>';
				foreach ( $cells( $l ) as $c ) {
					$out .= '<th>' . self::inline( $c ) . '</th>';
				}
				$out .= '</tr></thead><tbody>';
				for ( $i += 2; $i < $n && preg_match( '/^\|.*\|\s*$/', $lines[ $i ] ); $i++ ) {
					$out .= '<tr>';
					foreach ( $cells( $lines[ $i ] ) as $c ) {
						$out .= '<td>' . self::inline( $c ) . '</td>';
					}
					$out .= '</tr>';
				}
				$out .= '</tbody></table>';
				continue;
			}
			if ( preg_match( '/^>\s?(.*)$/', $l, $m ) ) {
				$out .= '<blockquote>' . self::inline( $m[1] ) . '</blockquote>';
				++$i;
				continue;
			}
			if ( '' === trim( $l ) ) {
				++$i;
				continue;
			}
			$para = array();
			while ( $i < $n && '' !== trim( $lines[ $i ] ) && ! preg_match( '/^(```|#{1,4}\s|\s*[-*]\s|\s*\d+\.\s|\||>)/', $lines[ $i ] ) ) {
				$para[] = trim( $lines[ $i ] );
				++$i;
			}
			if ( ! $para ) { // Safety: never loop forever on odd input.
				$para[] = trim( $lines[ $i++ ] );
			}
			$out .= '<p>' . self::inline( implode( ' ', $para ) ) . '</p>';
		}
		return wp_kses_post( $out );
	}
}
