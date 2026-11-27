<?php
namespace UserFlo\Core;

defined( 'ABSPATH' ) || exit;

/** UTC time source (overridable in tests). */
final class Clock {

	/** @var int|null */
	public static $fixed = null;

	public static function time() {
		return null === self::$fixed ? time() : self::$fixed;
	}

	/** Current time as a UTC MySQL datetime. */
	public static function now() {
		return gmdate( 'Y-m-d H:i:s', self::time() );
	}

	public static function mysql( $timestamp ) {
		return gmdate( 'Y-m-d H:i:s', (int) $timestamp );
	}
}
