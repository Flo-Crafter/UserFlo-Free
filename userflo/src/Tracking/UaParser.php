<?php
namespace UserFlo\Tracking;

defined( 'ABSPATH' ) || exit;

/** Small, dependency-free user-agent classifier (device, browser, OS, bot). */
final class UaParser {

	public static function parse( $ua ) {
		$ua = substr( (string) $ua, 0, 400 );
		return array(
			'bot'     => self::is_bot( $ua ),
			'device'  => self::device( $ua ),
			'browser' => self::browser( $ua ),
			'os'      => self::os( $ua ),
		);
	}

	public static function is_bot( $ua ) {
		if ( '' === $ua ) {
			return true;
		}
		return (bool) preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|uptime|pingdom|gtmetrix|python-requests|curl\/|wget|httpclient|java\/|go-http-client/i', $ua );
	}

	private static function device( $ua ) {
		if ( preg_match( '/iPad|Tablet|PlayBook|Silk/i', $ua ) || ( preg_match( '/Android/i', $ua ) && ! preg_match( '/Mobile/i', $ua ) ) ) {
			return 'tablet';
		}
		if ( preg_match( '/Mobi|iPhone|iPod|Android.*Mobile/i', $ua ) ) {
			return 'mobile';
		}
		return 'desktop';
	}

	private static function browser( $ua ) {
		$map = array(
			'/Edg(e|A|iOS)?\//'    => 'Edge',
			'/OPR\/|Opera/'        => 'Opera',
			'/SamsungBrowser/'     => 'Samsung Internet',
			'/Firefox\/|FxiOS/'    => 'Firefox',
			'/Chrome\/|CriOS/'     => 'Chrome',
			'/Safari\//'           => 'Safari',
			'/MSIE |Trident\//'    => 'Internet Explorer',
		);
		foreach ( $map as $re => $name ) {
			if ( preg_match( $re, $ua ) ) {
				return $name;
			}
		}
		return 'Other';
	}

	private static function os( $ua ) {
		$map = array(
			'/Windows/'            => 'Windows',
			'/iPhone|iPad|iPod/'   => 'iOS',
			'/Android/'            => 'Android',
			'/Mac OS X|Macintosh/' => 'macOS',
			'/CrOS/'               => 'ChromeOS',
			'/Linux|X11/'          => 'Linux',
		);
		foreach ( $map as $re => $name ) {
			if ( preg_match( $re, $ua ) ) {
				return $name;
			}
		}
		return 'Other';
	}
}
