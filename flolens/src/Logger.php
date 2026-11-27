<?php
namespace UserFlo;

defined( 'ABSPATH' ) || exit;

/** Small ring-buffer log (Settings → Help → Logs) for background work: downloads, syncs, retention, share links. No visitor data is logged. */
final class Logger {

	const OPTION = 'userflo_log';
	const MAX    = 200;

	/** @param string $level info|warning|error */
	public static function add( $level, $area, $message ) {
		$log = self::all();
		array_unshift( $log, array(
			't' => time(),
			'l' => in_array( $level, array( 'info', 'warning', 'error' ), true ) ? $level : 'info',
			'a' => substr( sanitize_key( $area ), 0, 30 ),
			'm' => \UserFlo\Core\Text::substr( wp_strip_all_tags( (string) $message ), 300 ),
		) );
		update_option( self::OPTION, array_slice( $log, 0, self::MAX ), false );
	}

	public static function all() {
		$l = get_option( self::OPTION, array() );
		return is_array( $l ) ? $l : array();
	}

	public static function clear() {
		delete_option( self::OPTION );
	}
}
