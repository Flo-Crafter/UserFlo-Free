<?php
namespace UserFlo\Core;

use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/** Honest session status derived at read time: observed logout, still active, or estimated end. */
final class SessionState {

	const ACTIVE               = 'active';
	const ENDED_ESTIMATED      = 'ended_estimated';
	const LOGGED_OUT_OBSERVED  = 'logged_out_observed';

	/** @param object|array $s Session row with last_activity_at, logout_at, login_at. */
	public static function of( $s ) {
		$s          = (object) $s;
		$logout     = isset( $s->logout_at ) ? $s->logout_at : null;
		$login      = isset( $s->login_at ) ? $s->login_at : null;
		$logged_out = ! empty( $logout ) && ( empty( $login ) || $logout >= $login );
		if ( $logged_out ) {
			return self::LOGGED_OUT_OBSERVED;
		}
		$idle = Clock::time() - strtotime( $s->last_activity_at . ' UTC' );
		return $idle < (int) Settings::get( 'inactivity_minutes' ) * 60 ? self::ACTIVE : self::ENDED_ESTIMATED;
	}
}
