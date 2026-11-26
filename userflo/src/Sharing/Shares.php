<?php
namespace UserFlo\Sharing;

defined( 'ABSPATH' ) || exit;

/**
 * Expiring share links. Only a SHA-256 hash of each token is stored, so a leaked database/option does not reveal usable links.
 * Scopes: stats (statistics page), live (live view), data (CSV/JSON feeds for spreadsheets and other systems), dashboard (one Statistics Builder
 * dashboard, Pro: the record names the dashboard by its stable id; Lite can store and revoke such links but never renders them).
 */
final class Shares {

	const OPTION = 'userflo_shares';
	const SCOPES = array( 'stats', 'live', 'data', 'dashboard' );
	const MAX    = 50;

	public static function all() {
		$o = get_option( self::OPTION, array() );
		return is_array( $o ) ? $o : array();
	}

	private static function save( array $all ) {
		update_option( self::OPTION, $all, false );
	}

	/** @return array|\WP_Error array( 'token' => ..., 'id' => ... ) */
	public static function create( $label, $scope, $expires_ts, $days, $user_id, $dashboard = '' ) {
		if ( ! in_array( $scope, self::SCOPES, true ) ) {
			return new \WP_Error( 'userflo_bad_scope', __( 'Unknown share type.', 'userflo' ) );
		}
		if ( 'dashboard' === $scope ) {
			// Only the add-on that owns dashboards can say whether this one exists and may be shared.
			$names = (array) apply_filters( 'userflo_share_dashboards', array() );
			if ( ! is_string( $dashboard ) || ! isset( $names[ $dashboard ] ) ) {
				return new \WP_Error( 'userflo_bad_dashboard', __( 'Choose a dashboard to share.', 'userflo' ) );
			}
		} else {
			$dashboard = '';
		}
		$now = time();
		if ( $expires_ts <= $now + 60 || $expires_ts > $now + 366 * DAY_IN_SECONDS ) {
			return new \WP_Error( 'userflo_bad_expiry', __( 'Choose an expiry between 1 minute and 1 year from now.', 'userflo' ) );
		}
		$all = self::all();
		$active = array_filter( $all, static function ( $s ) use ( $now ) { return $s['expires'] > $now; } );
		if ( count( $active ) >= self::MAX ) {
			return new \WP_Error( 'userflo_too_many', __( 'Too many active links. Revoke some first.', 'userflo' ) );
		}
		$token = bin2hex( random_bytes( 16 ) );
		$id    = substr( hash( 'sha256', $token ), 0, 12 );
		$all[ hash( 'sha256', $token ) ] = array(
			'id'      => $id,
			'label'   => \UserFlo\Core\Text::substr( sanitize_text_field( $label ), 80 ),
			'scope'   => $scope,
			'days'    => max( 1, min( 366, (int) $days ) ),
			'created' => $now,
			'expires' => (int) $expires_ts,
			'user'    => (int) $user_id,
		);
		if ( 'dashboard' === $scope ) {
			$all[ hash( 'sha256', $token ) ]['dashboard'] = $dashboard;
		}
		self::save( self::prune( $all ) );
		\UserFlo\Logger::add( 'info', 'sharing', sprintf( 'Link created (%s, expires %s) by user %d.', $scope, gmdate( 'Y-m-d H:i', $expires_ts ), $user_id ) );
		return array( 'token' => $token, 'id' => $id );
	}

	/** @return array|null The link record when the token is valid and not expired. */
	public static function find( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return null;
		}
		$all = self::all();
		$h   = hash( 'sha256', $token );
		if ( ! isset( $all[ $h ] ) || $all[ $h ]['expires'] <= time() ) {
			return null;
		}
		return $all[ $h ];
	}

	public static function revoke( $id ) {
		$all = self::all();
		foreach ( $all as $h => $s ) {
			if ( $s['id'] === $id ) {
				unset( $all[ $h ] );
				self::save( $all );
				\UserFlo\Logger::add( 'info', 'sharing', 'Link revoked (' . $id . ').' );
				return true;
			}
		}
		return false;
	}

	/** Revokes every link to a dashboard (it was deleted). @return int Links removed. */
	public static function revoke_dashboard( $uid ) {
		$all = self::all();
		$n   = 0;
		foreach ( $all as $h => $s ) {
			if ( 'dashboard' === $s['scope'] && isset( $s['dashboard'] ) && $s['dashboard'] === $uid ) {
				unset( $all[ $h ] );
				++$n;
			}
		}
		if ( $n ) {
			self::save( $all );
			\UserFlo\Logger::add( 'info', 'sharing', sprintf( '%d dashboard link(s) revoked (dashboard deleted).', $n ) );
		}
		return $n;
	}

	/** Drops links that expired more than 30 days ago. */
	private static function prune( array $all ) {
		$cut = time() - 30 * DAY_IN_SECONDS;
		return array_filter( $all, static function ( $s ) use ( $cut ) { return $s['expires'] > $cut; } );
	}

	public static function page_url( $token ) {
		return add_query_arg( 'userflo_share', $token, home_url( '/' ) );
	}

	public static function feed_url( $token, $set, $fmt = 'csv' ) {
		return rest_url( 'userflo/v1/feed/' . $token . '/' . $set . '.' . $fmt );
	}
}
