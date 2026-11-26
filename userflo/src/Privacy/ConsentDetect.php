<?php
namespace UserFlo\Privacy;

defined( 'ABSPATH' ) || exit;

/**
 * Finds an active cookie-consent plugin that UserFlo can follow (the choices of Settings → Privacy → consent). Read-only: it looks at
 * the list of active plugins and never contacts the plugins or reads their data.
 */
final class ConsentDetect {

	/** Plugin folder => UserFlo consent mode. */
	const PLUGINS = array(
		'complianz-gdpr'         => 'complianz',
		'complianz-gdpr-premium' => 'complianz',
		'cookie-law-info'        => 'cookieyes',
		'cookiebot'              => 'cookiebot',
		'borlabs-cookie'         => 'borlabs',
	);

	/** @return string complianz|cookieyes|cookiebot|borlabs, or '' when none is active. */
	public static function detect() {
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		foreach ( $active as $file ) {
			$dir = strstr( (string) $file, '/', true );
			if ( false !== $dir && isset( self::PLUGINS[ $dir ] )  ) {
				return self::PLUGINS[ $dir ];
			}
		}
		return '';
	}

	public static function label( $mode ) {
		$labels = array(
			'complianz' => 'Complianz',
			'cookieyes' => 'CookieYes',
			'cookiebot' => 'Cookiebot',
			'borlabs'   => 'Borlabs Cookie',
		);
		return isset( $labels[ $mode ] ) ? $labels[ $mode ] : '';
	}
}
