<?php
namespace UserFlo\Storage;

use UserFlo\Core\Clock;
use UserFlo\Core\Lock;
use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * The one physical maintenance action offered: rebuilding a single UserFlo table so space left behind by deleted rows is given back to
 * the server. It is never run automatically (a rebuild copies the whole table and can take minutes on a large one), only on request,
 * one table at a time, and only on UserFlo's own data tables.
 */
final class Maintenance {

	const STATE = 'userflo_maintenance_state';
	const LOCK  = 'maintenance';

	public static function state() {
		$s = get_option( self::STATE, array() );
		return is_array( $s ) ? $s : array();
	}

	/** Suffixes that may be rebuilt: the detailed and summary tables only. @return string[] */
	public static function allowed() {
		$out = array();
		foreach ( Measure::inventory() as $suffix => $row ) {
			if ( 'other' !== $row['kind'] ) {
				$out[] = $suffix;
			}
		}
		return $out;
	}

	/**
	 * @param string $suffix A table name without the prefix, from allowed().
	 * @return array|\WP_Error before / after (bytes), engine.
	 */
	public static function optimize( $suffix ) {
		global $wpdb;
		$suffix = (string) $suffix;
		if ( ! in_array( $suffix, self::allowed(), true ) ) {
			return new \WP_Error( 'userflo_bad_table', __( 'That table cannot be rebuilt from here.', 'userflo' ), array( 'status' => 400 ) );
		}
		if ( \UserFlo\Privacy\Retention::busy() ) {
			return new \WP_Error( 'userflo_busy', __( 'A cleanup is running. Try again when it has finished.', 'userflo' ), array( 'status' => 409 ) );
		}
		if ( ! Lock::acquire( self::LOCK, 900 ) ) {
			return new \WP_Error( 'userflo_busy', __( 'Another maintenance action is running.', 'userflo' ), array( 'status' => 409 ) );
		}
		$table = $wpdb->prefix . $suffix;
		$meta  = self::meta( $table );
		if ( ! $meta || ! in_array( strtolower( (string) $meta['engine'] ), array( 'innodb', 'myisam', 'aria' ), true ) ) {
			Lock::release( self::LOCK );
			return new \WP_Error( 'userflo_unsupported', __( 'This database does not report a table engine that can be rebuilt here.', 'userflo' ), array( 'status' => 400 ) );
		}
		$prev = $wpdb->suppress_errors( true );
		$res  = $wpdb->query( "OPTIMIZE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$err  = $wpdb->last_error;
		$wpdb->suppress_errors( $prev );
		$after = self::meta( $table );
		Lock::release( self::LOCK );
		$s = array( 'table' => $suffix, 'at' => Clock::time(), 'ok' => false !== $res && '' === $err, 'before' => (int) $meta['bytes'], 'after' => $after ? (int) $after['bytes'] : null );
		update_option( self::STATE, $s, false );
		if ( ! $s['ok'] ) {
			\UserFlo\Logger::add( 'error', 'storage', 'Rebuilding a table failed: the database refused it (its permissions may not allow it).' );
			return new \WP_Error( 'userflo_optimize_failed', __( 'The database did not allow the rebuild. Nothing was changed.', 'userflo' ), array( 'status' => 500 ) );
		}
		\UserFlo\Logger::add( 'info', 'storage', 'A table was rebuilt on request.' );
		Measure::measure();
		return $s + array( 'engine' => $meta['engine'] );
	}

	/** @return array{engine:string,bytes:int}|null */
	private static function meta( $table ) {
		global $wpdb;
		$prev = $wpdb->suppress_errors( true );
		$r    = $wpdb->get_row( $wpdb->prepare( 'SELECT ENGINE AS engine, DATA_LENGTH + INDEX_LENGTH AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->suppress_errors( $prev );
		return $r ? array( 'engine' => (string) $r['engine'], 'bytes' => (int) $r['bytes'] ) : null;
	}
}
