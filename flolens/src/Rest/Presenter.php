<?php
namespace UserFlo\Rest;

use UserFlo\Core\SessionState;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/** Shapes database rows into API responses (ISO-8601 UTC timestamps, identity filtered by privacy settings). */
final class Presenter {

	public static function iso( $utc ) {
		return $utc ? gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $utc . ' UTC' ) ) : null;
	}

	public static function visitor_label( $row ) {
		if ( ! empty( $row->user_id ) ) {
			if ( Settings::get( 'show_identity' ) && ! empty( $row->user_login ) ) {
				return ! empty( $row->display_name ) ? $row->display_name : $row->user_login;
			}
			return sprintf( /* translators: %d: user id */ __( 'Registered user #%d', 'flolens' ), (int) $row->user_id );
		}
		return sprintf( /* translators: %s: short visitor id */ __( 'Anonymous · %s', 'flolens' ), substr( $row->visitor_uid, 0, 8 ) );
	}

	/**
	 * The two-letter code of a country FloLens knows (upper case), or '' for an empty, malformed or unknown value, so a flag is only ever
	 * drawn for a real country. Taken from the country already stored with the visitor or session; nothing new is collected.
	 */
	public static function country_code( $country ) {
		$c = is_string( $country ) ? strtoupper( trim( $country ) ) : '';
		return preg_match( '/^[A-Z]{2}$/', $c ) && \UserFlo\Geo\Countries::get( $c ) ? $c : '';
	}

	/** "City, Country" or "Country" (empty when unknown). */
	public static function location( $country, $city ) {
		if ( empty( $country ) ) {
			return '';
		}
		$name = \UserFlo\Geo\Countries::name( $country );
		return $city ? $city . ', ' . $name : $name;
	}

	/**
	 * Approximate place of a session for the live map (null when geolocation is off or unknown). Never an IP address, and never more
	 * precise than the data the Map already shows: a country centroid, or a city position rounded to 0.1 degree (about 11 km).
	 *
	 * @return array{country:string,city:string,name:string,key:string,lat:float,lon:float}|null
	 */
	public static function geo( $country, $city, $lat, $lon ) {
		$mode = (string) \UserFlo\Geo\Locator::mode();
		if ( 'off' === $mode || empty( $country ) ) {
			return null;
		}
		$c = \UserFlo\Geo\Countries::get( $country );
		if ( ! $c ) {
			return null;
		}
		$city = 'city' === $mode ? (string) $city : '';
		if ( '' !== $city && null !== $lat && null !== $lon ) {
			return array( 'country' => $country, 'city' => $city, 'name' => self::location( $country, $city ), 'key' => $country . '|' . $city, 'lat' => round( (float) $lat, 1 ), 'lon' => round( (float) $lon, 1 ) );
		}
		return array( 'country' => $country, 'city' => '', 'name' => self::location( $country, '' ), 'key' => $country, 'lat' => (float) $c[2], 'lon' => (float) $c[3] );
	}

	public static function visitor( $v, $detail = false ) {
		$cc  = self::country_code( isset( $v->country ) ? $v->country : '' );
		$out = array(
			'id'          => (int) $v->id,
			'uid'         => $v->visitor_uid,
			'label'       => self::visitor_label( $v ),
			'logged_in'   => ! empty( $v->user_id ),
			'returning'   => (int) $v->session_count > 1,
			'email'       => null, // FloLens Pro adds the e-mail address (`userflo_visitor_presented`).
			'first_seen'  => self::iso( $v->first_seen ),
			'last_seen'   => self::iso( $v->last_seen ),
			'sessions'    => (int) $v->session_count,
			'pageviews'   => (int) $v->pageview_count,
			'events'      => (int) $v->event_count,
			'engaged'     => (int) $v->engaged_seconds,
			'device'      => $v->device,
			'browser'     => $v->browser,
			'os'          => $v->os,
			'location'    => self::location( isset( $v->country ) ? $v->country : '', '' ),
			'country'     => $cc,
			'country_name' => $cc ? \UserFlo\Geo\Countries::name( $cc ) : '',
		);
		if ( $detail ) {
			$link = \UserFlo\Settings\Settings::get( 'user_link' );
			if ( 'off' !== $link && ! empty( $v->user_id ) && Settings::get( 'show_identity' ) && current_user_can( 'edit_user', (int) $v->user_id ) ) {
				$url = get_edit_user_link( (int) $v->user_id );
				if ( $url ) {
					$out['profile_url']    = $url;
					$out['profile_target'] = 'same' === $link ? 'same' : 'new';
				}
			}
			$out['roles']          = array();
			$out['first_landing']  = $v->first_landing;
			$out['first_referrer'] = $v->first_referrer;
			$out['country']        = $v->country;
			$out['identified_at']  = isset( $v->identified_at ) ? self::iso( $v->identified_at ) : null;
			$out['city']           = '';
		}
		/**
		 * Lets an add-on add to a visitor in the list or the profile (FloLens Pro adds the account details, the IP address as stored and the city).
		 *
		 * @param array  $out    The visitor as shaped so far.
		 * @param object $v      The visitor row (with user_login, user_email, display_name when it is a registered user).
		 * @param bool   $detail Whether this is the profile.
		 */
		return (array) apply_filters( 'userflo_visitor_presented', $out, $v, (bool) $detail );
	}

	public static function session( $s ) {
		$out = array(
			'id'            => (int) $s->id,
			'visitor_id'    => (int) $s->visitor_id,
			'started_at'    => self::iso( $s->started_at ),
			'last_activity' => self::iso( $s->last_activity_at ),
			'elapsed'       => max( 0, strtotime( $s->last_activity_at . ' UTC' ) - strtotime( $s->started_at . ' UTC' ) ),
			'engaged'       => (int) $s->engaged_seconds,
			'pageviews'     => (int) $s->pageview_count,
			'events'        => (int) $s->event_count,
			'entry'         => $s->entry_url,
			'exit'          => $s->exit_url,
			'referrer'      => $s->referrer,
			'device'        => $s->device,
			'browser'       => $s->browser,
			'os'            => $s->os,
			'screen'        => $s->screen_w ? $s->screen_w . '×' . $s->screen_h : '',
			'location'      => self::location( isset( $s->country ) ? $s->country : '', '' ),
			'user_id'       => $s->user_id ? (int) $s->user_id : null,
			'login_at'      => self::iso( $s->login_at ),
			'logout_at'     => self::iso( $s->logout_at ),
			'state'         => SessionState::of( $s ),
		);
		return (array) apply_filters( 'userflo_session_presented', $out, $s ); // FloLens Pro adds the city.
	}
}
