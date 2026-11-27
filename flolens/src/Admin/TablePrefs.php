<?php
namespace UserFlo\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * How each administrator arranges the columns of a dashboard table (order and hidden columns), kept per WordPress user in user meta so
 * different administrators can have their own layout. Only column ids from the table itself are stored (never data), and every value is
 * checked on the way in and out. Without a saved layout the table shows its default columns in their default order.
 */
final class TablePrefs {

	const META = 'userflo_table_prefs';

	/** Tables whose layout can be saved. */
	const TABLES = array( 'visitors' );

	const MAX_COLUMNS = 40;

	public static function is_table( $table ) {
		return is_string( $table ) && in_array( $table, self::TABLES, true );
	}

	/** A list of column ids: only lowercase letters, digits and underscores, no repeats, no more than MAX_COLUMNS. */
	public static function clean_ids( $ids ) {
		$out = array();
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			if ( is_string( $id ) && preg_match( '/^[a-z0-9_]{1,40}$/', $id ) && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
			if ( count( $out ) >= self::MAX_COLUMNS ) {
				break;
			}
		}
		return $out;
	}

	/** @return array{order:string[],hidden:string[]} */
	public static function clean( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'order'  => self::clean_ids( isset( $raw['order'] ) ? $raw['order'] : array() ),
			'hidden' => self::clean_ids( isset( $raw['hidden'] ) ? $raw['hidden'] : array() ),
		);
	}

	/** All saved layouts of a user: table => array( order, hidden ). Unknown tables are dropped. */
	public static function all( $user_id ) {
		$meta = get_user_meta( (int) $user_id, self::META, true );
		$out  = array();
		if ( is_array( $meta ) ) {
			foreach ( $meta as $table => $raw ) {
				if ( self::is_table( $table ) ) {
					$out[ $table ] = self::clean( $raw );
				}
			}
		}
		return $out;
	}

	/** @return array|null The saved layout of one table, or null when the user never changed it. */
	public static function get( $user_id, $table ) {
		$all = self::all( $user_id );
		return isset( $all[ $table ] ) ? $all[ $table ] : null;
	}

	/** @return array|false The layout as stored, or false for a table that cannot be saved. */
	public static function save( $user_id, $table, $raw ) {
		if ( ! self::is_table( $table ) || (int) $user_id < 1 ) {
			return false;
		}
		$all           = self::all( $user_id );
		$all[ $table ] = self::clean( $raw );
		update_user_meta( (int) $user_id, self::META, $all );
		return $all[ $table ];
	}

	/** Back to the default layout (nothing saved) for one table. */
	public static function reset( $user_id, $table ) {
		if ( ! self::is_table( $table ) || (int) $user_id < 1 ) {
			return false;
		}
		$all = self::all( $user_id );
		unset( $all[ $table ] );
		if ( $all ) {
			update_user_meta( (int) $user_id, self::META, $all );
		} else {
			delete_user_meta( (int) $user_id, self::META );
		}
		return true;
	}
}
