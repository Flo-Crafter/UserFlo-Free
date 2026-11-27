<?php
namespace UserFlo\Exclusions;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;
use UserFlo\Edition;
use UserFlo\Tracking\Validator;

defined( 'ABSPATH' ) || exit;

/** Exclusion rules: validation, storage and the compiled list used while tracking and reporting. */
final class Store {

	const CACHE       = 'userflo_exclusions_active';
	const MAX_PATTERN = 300;
	const MAX_CSS     = 200;
	const MAX_ACTIVE_COMPILED = 200;

	/** @var array|null Per-request copy of the compiled list. */
	private static $memo = null;

	/** @return array[] All rules, newest first. */
	public static function all() {
		global $wpdb;
		$t = Tables::exclusions();
		return array_map( array( __CLASS__, 'cast' ), (array) $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id DESC", ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
	}

	public static function get( $id ) {
		global $wpdb;
		$t   = Tables::exclusions();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		return $row ? self::cast( $row ) : null;
	}

	private static function cast( array $r ) {
		$r['id']         = (int) $r['id'];
		$r['created_by'] = (int) $r['created_by'];
		return $r;
	}

	/**
	 * The active rules, grouped by mode, as one small autoloaded option: a site without rules pays nothing and a site with rules pays no query
	 * while tracking. Flushed on every change, so a rule takes effect on the next request.
	 *
	 * @return array{stop:array[],stats:array[],audit:array[]}
	 */
	public static function active() {
		if ( null !== self::$memo ) {
			return self::$memo;
		}
		$c = get_option( self::CACHE, null );
		if ( is_array( $c ) && isset( $c['stop'], $c['stats'], $c['audit'] ) ) {
			return self::$memo = $c;
		}
		$list = array( 'stop' => array(), 'stats' => array(), 'audit' => array() );
		$keep = array_flip( array( 'id', 'mode', 'target', 'field', 'match_type', 'pattern', 'event_type', 'page_scope', 'finding_type' ) );
		foreach ( array_reverse( self::all() ) as $r ) { // Oldest first: a stable order for the SQL and the tests.
			if ( 'active' === $r['status'] && isset( $list[ $r['mode'] ] ) && count( $list[ $r['mode'] ] ) < self::MAX_ACTIVE_COMPILED ) {
				$list[ $r['mode'] ][] = array_intersect_key( $r, $keep );
			}
		}
		update_option( self::CACHE, $list, true );
		return self::$memo = $list;
	}

	public static function flush() {
		self::$memo = null;
		delete_option( self::CACHE );
		Sql::reset();
		self::flush_reports();
	}

	/**
	 * Report results are cached for a couple of minutes. A rule changes what they should say, so the cached results are dropped as well
	 * (only FloLens's report caches, by name; with an external object cache they expire on their own within the cache time).
	 */
	private static function flush_reports() {
		global $wpdb;
		$like = array();
		foreach ( array( 'ov_', 'jny_', 'ins_', 'foc_', 'cmp_', 'ach', 'geo_', 'geod_', 'wp_', 'hl_', 'hmx_', 'aud_', 'elm_' ) as $p ) {
			$like[] = $wpdb->prepare( 'option_name LIKE %s', $wpdb->esc_like( '_transient_userflo_' . $p ) . '%' );
		}
		$names = (array) $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE " . implode( ' OR ', $like ) . ' LIMIT 2000' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
		foreach ( $names as $name ) {
			delete_transient( substr( $name, strlen( '_transient_' ) ) ); // Through the API, so an option cache in memory is cleared too.
		}
	}

	/** How many rules may be active at once in this edition (0 = no limit). */
	public static function limit() {
		return (int) Edition::limit( 'exclusions' );
	}

	public static function active_count( $except = 0 ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Tables::exclusions() . " WHERE status = 'active' AND id <> %d", (int) $except ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	/**
	 * A CSS selector is applied by the visitor's browser, so it cannot be tested here. This keeps out the forms that are slow to evaluate on
	 * a large page or that can reach beyond the element (`:has()`, `:is()`, `:where()`, `:not()`, long chains), and anything that is not plain
	 * selector syntax. A selector the browser rejects is simply ignored there.
	 */
	public static function css_error( $css ) {
		if ( '' === $css || strlen( $css ) > self::MAX_CSS ) {
			return __( 'Enter a CSS selector of up to 200 characters.', 'flolens' );
		}
		if ( ! preg_match( '/^[A-Za-z0-9_\-#.\[\]=:>~+*"\'\s,()^$|\/%]+$/', $css ) ) {
			return __( 'The selector contains characters that are not allowed.', 'flolens' );
		}
		if ( preg_match( '/:(has|is|where|not|matches|nth-[a-z-]+|contains)\b/i', $css ) ) {
			return __( 'Selectors with :has(), :is(), :where(), :not() or :nth-…() are not supported. Use a class, an id or a data attribute.', 'flolens' );
		}
		if ( preg_match( '/^[\s*,]*$/', $css ) || preg_match( '/^(\*|html|body)$/i', trim( $css ) ) ) {
			return __( 'That selector would match the whole page.', 'flolens' );
		}
		if ( substr_count( $css, ',' ) > 5 || preg_match_all( '/[\s>~+]+/', $css ) > 12 ) {
			return __( 'That selector is too long or too complex.', 'flolens' );
		}
		return '';
	}

	/**
	 * Validates and cleans a rule from user input.
	 *
	 * @param array $in Fields of the rule.
	 * @return array|\WP_Error Clean row (without id/timestamps/creator).
	 */
	public static function clean( array $in ) {
		$bad = static function ( $msg ) {
			return new \WP_Error( 'userflo_exclusion_invalid', $msg, array( 'status' => 400 ) );
		};
		$get = static function ( $k, $max ) use ( $in ) {
			return Validator::text( isset( $in[ $k ] ) ? $in[ $k ] : '', $max );
		};
		$mode   = $get( 'mode', 8 );
		$target = $get( 'target', 8 );
		if ( ! in_array( $mode, Matcher::MODES, true ) ) {
			return $bad( __( 'Choose what the exclusion does.', 'flolens' ) );
		}
		if ( ! in_array( $target, Matcher::TARGETS, true ) ) {
			return $bad( __( 'Choose what the exclusion applies to.', 'flolens' ) );
		}
		if ( ( 'audit' === $mode ) !== ( 'finding' === $target ) ) {
			return $bad( __( 'Audit findings can only be suppressed with an audit exclusion.', 'flolens' ) );
		}
		$out = array(
			'mode'         => $mode,
			'target'       => $target,
			'field'        => 'url',
			'match_type'   => 'exact',
			'pattern'      => '',
			'event_type'   => '',
			'page_scope'   => '',
			'finding_type' => '',
			'label'        => $get( 'label', 200 ),
			'note'         => $get( 'note', 300 ),
			'status'       => ( isset( $in['status'] ) && 'paused' === $in['status'] ) ? 'paused' : 'active',
		);
		$match = $get( 'match_type', 10 );
		if ( '' === $match ) {
			$match = 'exact';
		}
		if ( ! in_array( $match, Matcher::MATCHES, true ) ) {
			return $bad( __( 'Choose how the text is compared.', 'flolens' ) );
		}
		$out['match_type'] = $match;
		$pattern           = trim( $get( 'pattern', self::MAX_PATTERN ) );

		$scope = trim( $get( 'page_scope', self::MAX_PATTERN ) );
		if ( '' !== $scope && ( '/' !== $scope[0] || preg_match( '#^https?://#i', $scope ) ) ) {
			return $bad( __( 'Limit a rule to pages with a path that starts with a slash, for example /shop/.', 'flolens' ) );
		}
		$out['page_scope'] = $scope;

		if ( 'page' === $target ) {
			if ( 'stop' !== $mode && 'stats' !== $mode ) {
				return $bad( __( 'A page can be left untracked or left out of statistics.', 'flolens' ) );
			}
			if ( '' === $pattern || preg_match( '#^https?://#i', $pattern ) || ( 'contains' !== $match && '/' !== $pattern[0] ) ) {
				return $bad( 'contains' === $match ? __( 'Enter the text the page address must contain.', 'flolens' ) : __( 'Enter a page path that starts with a slash, for example /thank-you/.', 'flolens' ) );
			}
			if ( 'contains' === $match && strlen( $pattern ) < 3 ) {
				return $bad( __( 'Enter at least three characters.', 'flolens' ) );
			}
			if ( 'exact' !== $match && '/' === $pattern ) {
				return $bad( __( 'That would cover every page on the site. To stop tracking altogether use Settings → Tracking.', 'flolens' ) );
			}
			$out['pattern']    = $pattern;
			$out['page_scope'] = '';
			return $out;
		}

		if ( 'element' === $target ) {
			$field = $get( 'field', 10 );
			if ( '' === $field ) {
				$field = 'target';
			}
			if ( ! in_array( $field, Matcher::ELEMENT_FIELDS, true ) ) {
				return $bad( __( 'Choose how to recognise the element.', 'flolens' ) );
			}
			if ( 'css' === $field ) {
				if ( 'stop' !== $mode ) {
					return $bad( __( 'A CSS selector can only stop tracking: it is applied in the visitor’s browser and cannot be used to filter data that was already recorded.', 'flolens' ) );
				}
				$err = self::css_error( $pattern );
				if ( '' !== $err ) {
					return $bad( $err );
				}
				$out['match_type'] = 'exact';
			} elseif ( strlen( $pattern ) < ( 'contains' === $match ? 3 : 2 ) ) {
				return $bad( __( 'Enter the identifier, label or address of the element (at least three characters when matching part of a text).', 'flolens' ) );
			}
			$et = $get( 'event_type', 20 );
			if ( '' !== $et && ! in_array( $et, Validator::$event_types, true ) ) {
				return $bad( __( 'Choose the kind of interaction.', 'flolens' ) );
			}
			$out['field']      = $field;
			$out['pattern']    = $pattern;
			$out['event_type'] = $et;
			return $out;
		}

		// Audit finding: the type of finding, optionally one page (scope) and optionally one element.
		$ft = strtolower( $get( 'finding_type', 40 ) );
		if ( '' !== $ft && ! preg_match( '/^[a-z0-9_]{2,40}$/', $ft ) ) {
			return $bad( __( 'The finding type is not valid.', 'flolens' ) );
		}
		if ( '' === $ft && '' === $pattern && '' === $scope ) {
			return $bad( __( 'Name a type of finding, a page or an element; an empty rule would hide every finding.', 'flolens' ) );
		}
		$out['field']        = 'target';
		$out['finding_type'] = $ft;
		$out['pattern']      = $pattern;
		return $out;
	}

	/** Whether an identical rule already exists (it is returned instead of adding a copy). */
	private static function duplicate( array $row, $self ) {
		foreach ( self::all() as $r ) {
			if ( $r['id'] === (int) $self ) {
				continue;
			}
			$same = true;
			foreach ( array( 'mode', 'target', 'field', 'match_type', 'pattern', 'event_type', 'page_scope', 'finding_type' ) as $k ) {
				if ( strtolower( (string) $r[ $k ] ) !== strtolower( (string) $row[ $k ] ) ) {
					$same = false;
					break;
				}
			}
			if ( $same ) {
				return $r;
			}
		}
		return null;
	}

	/** @return array|\WP_Error The saved rule. */
	public static function save( array $in, $id = 0 ) {
		global $wpdb;
		$row = self::clean( $in );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$id = (int) $id;
		if ( $id && ! self::get( $id ) ) {
			return new \WP_Error( 'userflo_exclusion_missing', __( 'That rule no longer exists.', 'flolens' ), array( 'status' => 404 ) );
		}
		$dup = self::duplicate( $row, $id );
		if ( $dup ) {
			return new \WP_Error( 'userflo_exclusion_duplicate', __( 'An identical rule already exists.', 'flolens' ), array( 'status' => 409, 'rule' => $dup ) );
		}
		$limit = self::limit();
		if ( $limit && 'active' === $row['status'] && self::active_count( $id ) >= $limit ) {
			return new \WP_Error( 'userflo_exclusion_limit', sprintf( /* translators: %d: number of rules */ __( 'This edition allows %d active exclusion rules. Pause or delete one first.', 'flolens' ), $limit ), array( 'status' => 403 ) );
		}
		$now = Clock::now();
		if ( $id ) {
			$wpdb->update( Tables::exclusions(), $row + array( 'updated_at' => $now ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		} else {
			$wpdb->insert( Tables::exclusions(), $row + array( 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own tables: names are internal constants, values are prepared.
			$id = (int) $wpdb->insert_id;
		}
		self::flush();
		do_action( 'userflo_exclusions_changed', $id );
		return self::get( $id );
	}

	/** Deletes a rule. Nothing already recorded is touched; statistics that were filtered by it simply count that data again. */
	public static function delete( $id ) {
		global $wpdb;
		if ( ! self::get( (int) $id ) ) {
			return false;
		}
		$wpdb->delete( Tables::exclusions(), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
		self::flush();
		do_action( 'userflo_exclusions_changed', (int) $id );
		return true;
	}

	public static function set_status( $id, $status ) {
		$r = self::get( (int) $id );
		if ( ! $r ) {
			return new \WP_Error( 'userflo_exclusion_missing', __( 'That rule no longer exists.', 'flolens' ), array( 'status' => 404 ) );
		}
		return self::save( array_merge( $r, array( 'status' => 'paused' === $status ? 'paused' : 'active' ) ), (int) $id );
	}
}
