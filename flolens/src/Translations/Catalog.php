<?php
namespace UserFlo\Translations;

defined( 'ABSPATH' ) || exit;

/** Reads the shipped template (userflo.pot) and shipped translations (userflo-<locale>.po) and groups strings into editor sections. */
final class Catalog {

	private static $entries = null;
	private static $shipped = array();

	public static function dir() {
		return USERFLO_DIR . 'languages/';
	}

	private static function unq( $s ) {
		return str_replace( array( '\\n', '\\"', '\\\\' ), array( "\n", '"', '\\' ), $s );
	}

	/** Parses a simple PO/POT file: returns list of array( msgid, msgstr, refs ). */
	private static function parse( $path ) {
		if ( ! is_readable( $path ) ) {
			return array();
		}
		$out = array();
		foreach ( preg_split( '/\R\R/', (string) file_get_contents( $path ) ) as $block ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( ! preg_match( '/^msgid "(.*)"$/m', $block, $a ) || '' === $a[1] ) {
				continue;
			}
			preg_match( '/^msgstr "(.*)"$/m', $block, $b );
			preg_match( '/^#: (.*)$/m', $block, $r );
			$out[] = array( self::unq( $a[1] ), isset( $b[1] ) ? self::unq( $b[1] ) : '', isset( $r[1] ) ? $r[1] : '' );
		}
		return $out;
	}

	/** @return array msgid => refs */
	public static function entries() {
		if ( null === self::$entries ) {
			self::$entries = array();
			foreach ( self::parse( self::dir() . 'flolens.pot' ) as $e ) {
				self::$entries[ $e[0] ] = $e[2];
			}
		}
		return self::$entries;
	}

	/** @return array msgid => shipped translation (non-empty only) */
	public static function shipped( $code ) {
		if ( ! preg_match( '/^[A-Za-z_]{2,10}$/', $code ) ) {
			return array();
		}
		if ( ! isset( self::$shipped[ $code ] ) ) {
			self::$shipped[ $code ] = array();
			foreach ( self::parse( self::dir() . 'flolens-' . $code . '.po' ) as $e ) {
				if ( '' !== $e[1] ) {
					self::$shipped[ $code ][ $e[0] ] = $e[1];
				}
			}
		}
		return self::$shipped[ $code ];
	}

	public static function has_shipped( $code ) {
		return preg_match( '/^[A-Za-z_]{2,10}$/', $code ) && is_readable( self::dir() . 'flolens-' . $code . '.po' );
	}

	/** Ordered section definitions: id => array( label, path regex ). */
	public static function sections() {
		return array(
			'settings'     => array( __( 'Settings and general', 'flolens' ), '#Admin/SettingsPage|Settings/|Modules|Brand|I18n#' ),
			'dashboard'    => array( __( 'Overview and navigation', 'flolens' ), '#views/Overview|views/External|components/(Chart|ui|Calendar)|Admin/(Menu|Ui|AppPage)|index\.tsx#' ),
			'visitors'     => array( __( 'Visitors and journeys', 'flolens' ), '#Visitor|Journey|SessionsTable|Presenter#' ),
			'live'         => array( __( 'Live view', 'flolens' ), '#views/Live|LiveRoutes#' ),
			'pages'        => array( __( 'Pages and statistics', 'flolens' ), '#views/(Pages|Stats)|Insights/Stats|InsightRoutes#' ),
			'reports'      => array( __( 'Reports', 'flolens' ), '#Reports|reportRender|Export/Pdf#' ),
			'achievements' => array( __( 'Achievements', 'flolens' ), '#Achievement#' ),
			'geo'          => array( __( 'Map and geolocation', 'flolens' ), '#Geo|views/Map|MapModal#' ),
			'heatmaps'     => array( __( 'Heatmaps', 'flolens' ), '#Heatmap|heatmap#' ),
			'sharing'      => array( __( 'Sharing, export and tools', 'flolens' ), '#Sharing|Tools|tools\.js|Export|Jobs#' ),
			'integrations' => array( __( 'Integrations', 'flolens' ), '#Integrations|integrations\.js#' ),
			'frontend'     => array( __( 'Shortcodes and shared pages', 'flolens' ), '#Frontend/|share#' ),
			'privacy'      => array( __( 'Privacy and data', 'flolens' ), '#Privacy/|PersonalData#' ),
			'other'        => array( __( 'Everything else', 'flolens' ), '#.#' ),
		);
	}

	public static function section_of( $refs ) {
		foreach ( self::sections() as $id => $s ) {
			if ( 'other' !== $id && preg_match( $s[1], $refs ) ) {
				return $id;
			}
		}
		return 'other';
	}

	/**
	 * @param string $code      Language code.
	 * @param array  $overrides Custom strings (msgid => text).
	 * @return array sections list for the editor.
	 */
	public static function view( $code, array $overrides ) {
		$shipped = 'en_US' === $code ? array() : self::shipped( $code );
		$groups  = array();
		foreach ( self::entries() as $msgid => $refs ) {
			$sid = self::section_of( $refs );
			$cus = isset( $overrides[ $msgid ] ) && '' !== $overrides[ $msgid ];
			$groups[ $sid ][] = array(
				'k'       => $msgid,
				's'       => isset( $shipped[ $msgid ] ) ? $shipped[ $msgid ] : ( 'en_US' === $code ? $msgid : '' ),
				'c'       => $cus ? $overrides[ $msgid ] : '',
			);
		}
		$out = array();
		foreach ( self::sections() as $id => $s ) {
			if ( empty( $groups[ $id ] ) ) {
				continue;
			}
			$done = 0;
			foreach ( $groups[ $id ] as $it ) {
				if ( '' !== $it['c'] || '' !== $it['s'] ) {
					++$done;
				}
			}
			$out[] = array( 'id' => $id, 'label' => $s[0], 'total' => count( $groups[ $id ] ), 'done' => $done, 'items' => $groups[ $id ] );
		}
		return $out;
	}

	public static function total() {
		return count( self::entries() );
	}

	public static function placeholders( $s ) {
		preg_match_all( '/%(?:\d+\$)?[sdf]/', (string) $s, $m );
		sort( $m[0] );
		return $m[0];
	}
}
