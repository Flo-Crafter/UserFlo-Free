<?php
namespace UserFlo\Insights;

defined( 'ABSPATH' ) || exit;

/**
 * Derives a traffic channel for a session from data UserFlo already records: the referrer and the utm_* parameters kept in the entry URL.
 * Nothing is looked up externally. The result is a heuristic grouping (a visit from a search engine is "Organic search", a utm_medium of
 * cpc is "Paid", and so on); the Overview labels it as derived. Rules can be extended with the `userflo_channel_rules` filter.
 */
final class Channels {

	const KEYS = array( 'direct', 'organic_search', 'paid', 'social', 'email', 'referral' );

	/** Host fragments (matched against the registrable part of the referrer host) and utm_source words. */
	public static function rules() {
		$rules = array(
			'search' => array( 'google', 'bing', 'duckduckgo', 'yahoo', 'baidu', 'yandex', 'ecosia', 'brave', 'startpage', 'qwant', 'naver', 'seznam', 'ask', 'aol', 'kagi', 'perplexity' ),
			'social' => array( 'facebook', 'fb', 'instagram', 'twitter', 't.co', 'x.com', 'linkedin', 'lnkd', 'pinterest', 'reddit', 'youtube', 'youtu.be', 'tiktok', 'threads', 'mastodon', 'whatsapp', 'telegram', 'snapchat', 'tumblr', 'vk.com', 'bsky', 'bluesky', 'discord', 'quora' ),
			'email'  => array( 'mail.google', 'outlook', 'mail.yahoo', 'mail.live', 'mailchimp', 'newsletter', 'webmail', 'proton.me', 'mail.proton' ),
			'paid'   => array( 'cpc', 'ppc', 'paid', 'paidsearch', 'paid-search', 'paid_social', 'paidsocial', 'display', 'cpm', 'banner', 'retargeting', 'remarketing', 'sem' ),
		);
		return function_exists( 'apply_filters' ) ? (array) apply_filters( 'userflo_channel_rules', $rules ) : $rules;
	}

	/** Channel key for one session. */
	public static function classify( $referrer, $entry_url, $site_host = '' ) {
		$rules  = self::rules();
		$utm    = self::utm( (string) $entry_url );
		$medium = isset( $utm['utm_medium'] ) ? strtolower( $utm['utm_medium'] ) : '';
		$source = isset( $utm['utm_source'] ) ? strtolower( $utm['utm_source'] ) : '';

		if ( '' !== $medium ) {
			if ( in_array( $medium, $rules['paid'], true ) || 0 === strpos( $medium, 'paid' ) ) {
				return 'paid';
			}
			if ( in_array( $medium, array( 'email', 'e-mail', 'mail', 'newsletter' ), true ) ) {
				return 'email';
			}
			if ( in_array( $medium, array( 'social', 'social-network', 'social-media', 'sm', 'social_media' ), true ) ) {
				return 'social';
			}
			if ( 'organic' === $medium ) {
				return self::has( $source, $rules['social'] ) ? 'social' : 'organic_search';
			}
			if ( 'referral' === $medium ) {
				return 'referral';
			}
		}
		if ( '' !== $source && '' === $medium ) {
			if ( self::has( $source, $rules['email'] ) ) {
				return 'email';
			}
			if ( self::has( $source, $rules['social'] ) ) {
				return 'social';
			}
			if ( self::has( $source, $rules['search'] ) ) {
				return 'organic_search';
			}
		}

		$ref = (string) $referrer;
		if ( '' === $ref || '/' === $ref[0] ) {
			return 'direct';
		}
		if ( 0 === strpos( $ref, 'android-app://' ) ) {
			if ( false !== strpos( $ref, 'google.android.gm' ) ) {
				return 'email';
			}
			return false !== strpos( $ref, 'googlequicksearchbox' ) ? 'organic_search' : 'referral';
		}
		$host = strtolower( (string) wp_parse_url( $ref, PHP_URL_HOST ) );
		$host = preg_replace( '/^www\./', '', $host );
		if ( '' === $host ) {
			return 'direct';
		}
		$site = preg_replace( '/^www\./', '', strtolower( (string) $site_host ) );
		if ( '' !== $site && ( $host === $site || substr( $host, -strlen( $site ) - 1 ) === '.' . $site ) ) {
			return 'direct'; // The visitor came from the site itself (a new session after a pause).
		}
		if ( self::has( $host, $rules['email'] ) ) {
			return 'email';
		}
		if ( self::has_host( $host, $rules['social'] ) ) {
			return 'social';
		}
		if ( self::has_host( $host, $rules['search'] ) ) {
			return 'organic_search';
		}
		return 'referral';
	}

	/** utm_* parameters in a URL (lower-case keys, URL-decoded values). */
	public static function utm( $url ) {
		$q = (string) wp_parse_url( $url, PHP_URL_QUERY );
		if ( '' === $q || false === stripos( $q, 'utm_' ) ) {
			return array();
		}
		$out = array();
		foreach ( explode( '&', $q ) as $pair ) {
			$kv = explode( '=', $pair, 2 );
			$k  = strtolower( urldecode( $kv[0] ) );
			if ( 0 === strpos( $k, 'utm_' ) ) {
				$out[ $k ] = isset( $kv[1] ) ? trim( urldecode( $kv[1] ) ) : '';
			}
		}
		return $out;
	}

	/** Substring match on a free-text word such as a utm_source value. */
	private static function has( $text, array $words ) {
		foreach ( $words as $w ) {
			if ( '' !== $w && false !== strpos( $text, $w ) ) {
				return true;
			}
		}
		return false;
	}

	/** Match on a host: the word must be a whole label (google.com, www.google.co.uk, l.facebook.com), not a substring of another name. */
	private static function has_host( $host, array $words ) {
		$labels = explode( '.', $host );
		foreach ( $words as $w ) {
			if ( false !== strpos( $w, '.' ) ) {
				if ( $host === $w || substr( $host, -strlen( $w ) - 1 ) === '.' . $w ) {
					return true;
				}
			} elseif ( in_array( $w, $labels, true ) ) {
				return true;
			}
		}
		return false;
	}

	/** Translated labels, in display order. */
	public static function labels() {
		return array(
			'organic_search' => __( 'Organic search', 'userflo' ),
			'direct'         => __( 'Direct', 'userflo' ),
			'social'         => __( 'Social', 'userflo' ),
			'referral'       => __( 'Referral', 'userflo' ),
			'email'          => __( 'Email', 'userflo' ),
			'paid'           => __( 'Paid', 'userflo' ),
		);
	}
}
