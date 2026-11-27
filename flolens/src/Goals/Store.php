<?php
namespace UserFlo\Goals;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;
use UserFlo\Edition;

defined( 'ABSPATH' ) || exit;

/** Goal definitions: validation, storage, the compiled list used at ingest, and the SQL form of the matching rules. */
final class Store {

	const CACHE      = 'userflo_goals_active';
	const MAX_NAME   = 100;
	const MAX_AMOUNT = 1000;

	/** Goal types. Page and event goals are part of FloLens; an add-on can register more through `userflo_goal_types`. */
	public static function types() {
		return array_values( array_unique( array_map( 'strval', (array) apply_filters( 'userflo_goal_types', array( 'page', 'event' ) ) ) ) );
	}

	/** Whether the goal is an ordinary page or event goal (completed once per session, counted at ingest and in the reports). */
	public static function standard( array $g ) {
		return (bool) apply_filters( 'userflo_goal_standard', in_array( $g['type'], array( 'page', 'event' ), true ), $g );
	}

	/** Event types a goal can watch (the tracker's own types; scroll is excluded: it is a per-page maximum, not a point event). */
	public static function event_types() {
		return array_values( array_diff( \UserFlo\Tracking\Validator::$event_types, array( 'scroll' ) ) );
	}

	/** @return array[] All goals, newest last. */
	public static function all() {
		global $wpdb;
		$t = Tables::goals();
		return array_map( array( __CLASS__, 'cast' ), (array) $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id", ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	public static function get( $id ) {
		global $wpdb;
		$t   = Tables::goals();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		return $row ? self::cast( $row ) : null;
	}

	private static function cast( array $r ) {
		$r['id']        = (int) $r['id'];
		$r['min_count'] = max( 1, isset( $r['min_count'] ) ? (int) $r['min_count'] : 1 );
		/**
		 * Lets an add-on read the columns it owns (they are stored with every goal) into proper types.
		 *
		 * @param array $r The goal row.
		 */
		return (array) apply_filters( 'userflo_goal_cast', $r );
	}

	/**
	 * The goals ingest has to check, as one small autoloaded option so a site without goals pays nothing and a site with goals pays no query.
	 *
	 * @return array[] Active page and event goals only.
	 */
	public static function active() {
		$c = get_option( self::CACHE, null );
		if ( is_array( $c ) ) {
			return $c;
		}
		$list = array();
		$keep = array_flip( (array) apply_filters( 'userflo_goal_active_fields', array( 'id', 'type', 'match_type', 'pattern', 'event_type', 'name', 'min_count' ) ) );
		foreach ( self::all() as $g ) {
			if ( 'active' === $g['status'] && self::standard( $g ) ) {
				$list[] = array_intersect_key( $g, $keep );
			}
		}
		update_option( self::CACHE, $list, true );
		return $list;
	}

	public static function flush() {
		delete_option( self::CACHE );
	}

	/** How many page and event goals may be active at once in this edition (0 = no limit). */
	public static function limit() {
		return (int) Edition::limit( 'goals' );
	}

	/**
	 * Validates and cleans a goal from user input.
	 *
	 * @param array $in   name, type, match_type, pattern, event_type, min_count, status.
	 * @param int   $self Id being edited (excluded from the duplicate-name warning), 0 for a new goal.
	 * @return array|\WP_Error Clean row (without id/timestamps).
	 */
	public static function clean( array $in, $self = 0 ) {
		$bad  = static function ( $msg ) {
			return new \WP_Error( 'userflo_goal_invalid', $msg, array( 'status' => 400 ) );
		};
		$name = \UserFlo\Tracking\Validator::text( isset( $in['name'] ) ? $in['name'] : '', self::MAX_NAME );
		if ( '' === $name ) {
			return $bad( __( 'Give the goal a name.', 'flolens' ) );
		}
		$type = isset( $in['type'] ) ? (string) $in['type'] : '';
		if ( ! in_array( $type, self::types(), true ) ) {
			return $bad( __( 'Choose what the goal measures.', 'flolens' ) );
		}
		$out = array(
			'name'       => $name,
			'type'       => $type,
			'match_type' => 'exact',
			'pattern'    => '',
			'event_type' => '',
			'min_count'  => 1,
			'status'     => ( isset( $in['status'] ) && 'paused' === $in['status'] ) ? 'paused' : 'active',
		);
		if ( 'page' === $type || 'event' === $type ) {
			if ( isset( $in['min_count'] ) && '' !== $in['min_count'] ) {
				if ( ! is_numeric( $in['min_count'] ) || (int) $in['min_count'] < 1 || (int) $in['min_count'] > self::MAX_AMOUNT ) {
					return $bad( sprintf( /* translators: %d: largest amount */ __( 'The amount must be a whole number from 1 to %d.', 'flolens' ), self::MAX_AMOUNT ) );
				}
				$out['min_count'] = (int) $in['min_count'];
			}
		}
		if ( 'page' === $type ) {
			$m = isset( $in['match_type'] ) ? (string) $in['match_type'] : 'exact';
			if ( ! in_array( $m, Matcher::PAGE_MATCHES, true ) ) {
				return $bad( __( 'Choose how the page address is compared.', 'flolens' ) );
			}
			$pat = trim( \UserFlo\Tracking\Validator::text( isset( $in['pattern'] ) ? $in['pattern'] : '', 300 ) );
			if ( '' === $pat || ( 'contains' !== $m && '/' !== $pat[0] ) ) {
				return $bad( 'contains' === $m ? __( 'Enter the text the page address must contain.', 'flolens' ) : __( 'Enter a page path that starts with a slash, for example /thank-you/.', 'flolens' ) );
			}
			if ( preg_match( '#^https?://#i', $pat ) ) {
				return $bad( __( 'Use the path only (starting with a slash), not the full address.', 'flolens' ) );
			}
			$out['match_type'] = $m;
			$out['pattern']    = $pat;
		} elseif ( 'event' === $type ) {
			$et = isset( $in['event_type'] ) ? (string) $in['event_type'] : '';
			if ( ! in_array( $et, self::event_types(), true ) ) {
				return $bad( __( 'Choose the kind of event.', 'flolens' ) );
			}
			$m                 = isset( $in['match_type'] ) && 'contains' === $in['match_type'] ? 'contains' : 'exact';
			$out['match_type'] = $m;
			$out['event_type'] = $et;
			$out['pattern']    = trim( \UserFlo\Tracking\Validator::text( isset( $in['pattern'] ) ? $in['pattern'] : '', 300 ) );
		}
		/**
		 * Lets an add-on validate its own goal types and options and add the columns it owns. Return the row or a WP_Error.
		 *
		 * @param array $out  The row built so far.
		 * @param array $in   The raw input.
		 * @param string $type The goal type.
		 * @param int   $self Id being edited, 0 for a new goal.
		 */
		return apply_filters( 'userflo_goal_clean', $out, $in, $type, (int) $self );
	}

	/** Whether another goal already uses this name (a warning, not an error). */
	public static function name_taken( $name, $self = 0 ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . Tables::goals() . ' WHERE name = %s AND id <> %d LIMIT 1', $name, (int) $self ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	/** Number of active page and event goals. */
	public static function active_count( $except = 0 ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Tables::goals() . " WHERE status = 'active' AND type IN ('page','event') AND id <> %d", (int) $except ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	/** @return array|\WP_Error The saved goal. */
	public static function save( array $in, $id = 0 ) {
		global $wpdb;
		$row = self::clean( $in, $id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$limit = self::limit();
		if ( $limit && 'active' === $row['status'] && self::standard( $row ) && self::active_count( $id ) >= $limit ) {
			return new \WP_Error( 'userflo_goal_limit', sprintf( /* translators: %d: number of goals */ __( 'This edition allows %d active goals. Pause or delete one first.', 'flolens' ), $limit ), array( 'status' => 402 ) );
		}
		$now = Clock::now();
		if ( $id ) {
			if ( ! self::get( $id ) ) {
				return new \WP_Error( 'userflo_goal_missing', __( 'That goal no longer exists.', 'flolens' ), array( 'status' => 404 ) );
			}
			$wpdb->update( Tables::goals(), $row + array( 'updated_at' => $now ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		} else {
			$wpdb->insert( Tables::goals(), $row + array( 'created_at' => $now, 'updated_at' => $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own tables: names are internal constants, values are prepared.
			$id = (int) $wpdb->insert_id;
		}
		self::flush();
		return self::get( $id );
	}

	/** Deletes a goal and every completion recorded for it. */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( ! self::get( $id ) ) {
			return false;
		}
		$wpdb->delete( Tables::goal_hits(), array( 'goal_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		$wpdb->delete( Tables::goals(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		/**
		 * A goal was deleted (an add-on can remove what depended on it).
		 *
		 * @param int $id Goal id.
		 */
		do_action( 'userflo_goal_deleted', $id );
		self::flush();
		return true;
	}

	public static function set_status( $id, $status ) {
		$g = self::get( $id );
		if ( ! $g ) {
			return new \WP_Error( 'userflo_goal_missing', __( 'That goal no longer exists.', 'flolens' ), array( 'status' => 404 ) );
		}
		return self::save( array_merge( $g, array( 'status' => 'paused' === $status ? 'paused' : 'active' ) ), $id );
	}

	/**
	 * The SQL form of a goal's rule over the page views or events table. Values go through $wpdb->prepare() by the caller via the returned
	 * placeholders; table and column names are fixed strings. Mirrors Matcher.
	 *
	 * @param array  $g     Goal.
	 * @param string $alias Table alias.
	 * @return array{0:string,1:array}|null SQL fragment and values, or null when the goal has no SQL form here.
	 */
	public static function condition( array $g, $alias, $column = 'url' ) {
		global $wpdb;
		$a = $alias;
		$u = '' === $alias ? $column : "{$alias}.{$column}"; // The address column, qualified when the query uses a table alias.
		if ( 'page' === $g['type'] ) {
			$p = strtolower( $g['pattern'] );
			if ( 'contains' === $g['match_type'] ) {
				return array( "LOWER({$u}) LIKE %s", array( '%' . $wpdb->esc_like( $p ) . '%' ) );
			}
			if ( 'prefix' === $g['match_type'] ) {
				return array( "LOWER({$u}) LIKE %s", array( $wpdb->esc_like( $p ) . '%' ) );
			}
			$base = rtrim( $p, '/' );
			if ( false !== strpos( $p, '?' ) ) {
				return array( "LOWER({$u}) = %s", array( $p ) );
			}
			return array(
				"(LOWER({$u}) IN (%s,%s) OR LOWER({$u}) LIKE %s OR LOWER({$u}) LIKE %s)",
				array( '' === $base ? '/' : $base, $base . '/', $wpdb->esc_like( $base ) . '?%', $wpdb->esc_like( $base ) . '/?%' ),
			);
		}
		if ( 'event' === $g['type'] ) {
			$col  = in_array( $g['event_type'], Matcher::VALUE_TYPES, true ) ? 'value' : 'name';
			$sql  = "{$a}.type = %s";
			$vals = array( $g['event_type'] );
			if ( '' !== $g['pattern'] ) {
				if ( 'contains' === $g['match_type'] ) {
					$sql   .= " AND LOWER({$a}.{$col}) LIKE %s";
					$vals[] = '%' . $wpdb->esc_like( strtolower( $g['pattern'] ) ) . '%';
				} else {
					$sql   .= " AND LOWER({$a}.{$col}) = %s";
					$vals[] = strtolower( $g['pattern'] );
				}
			}
			return array( '(' . $sql . ')', $vals );
		}
		return null;
	}
}
