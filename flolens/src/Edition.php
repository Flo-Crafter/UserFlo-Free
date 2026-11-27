<?php
namespace UserFlo;

defined( 'ABSPATH' ) || exit;

/**
 * The single place that decides what this installation may do: FloLens Lite (free, no licence) or Lite plus the FloLens Pro
 * add-on. Nothing else in the plugin compares editions or numbers; screens and REST routes ask `Edition::can()` /
 * `Edition::limit()` and the Pro plugin raises them through two filters.
 *
 * Principles (see docs/EDITIONS.md):
 *  - Collection, storage, retention and every privacy control are never edition dependent.
 *  - The free plugin has no commercial limits (see lite_limits()); Pro adds features, not larger numbers.
 *  - The checks live on the server (REST callbacks and queries); hiding a control is only a convenience.
 *  - A missing, expired or unreachable licence only returns the installation to the Lite values below. It never stops tracking.
 */
final class Edition {

	/**
	 * Where "Upgrade to Pro" points. The Pro product page on flocrafter.com; can be changed with the filter `userflo_upgrade_url`.
	 * The `.invalid` TLD is reserved (RFC 2606) so this can never resolve to a real site by accident.
	 */
	const UPGRADE_URL = 'https://flocrafter.com/flolens/pro/';

	/** Feature keys and their Lite value. Pro switches them on through the `userflo_edition_features` filter. */
	private static function lite_features() {
		return array(
			'range_extended'    => false, // Overview and report ranges beyond Lite's day options, and custom date ranges.
			'visitor_filters'   => false, // Advanced visitor filters, facets and segments (basic search stays).
			'history_extended'  => false, // Event and session history beyond the Lite display limits.
			'identity_detail'   => false, // E-mail and detailed user-linked context in visitor profiles.
			'calendar_week'     => false, // Calendar week view and navigation outside the Lite window.
			'calendar_metrics'  => false, // Calendar metrics other than page views.
			'calendar_detail'   => false, // Calendar day and hour detail.
			'map_city'          => false, // City level detail in the map and city-level geolocation.
			'map_globe'         => false, // 3D globe and live feed on the map.
			'highlights'        => false, // Overview movers, engaged content and exit pages.
			'wordpress_reports' => false, // Content, forms, shop and builder reports.
			'heatmaps'          => false,
			'reports'           => false, // Report builder / PDF.
			'scheduled_reports' => false, // E-mail reports.
			'integrations'      => false, // GA4 / Clarity / PostHog import.
			'advanced_tracking' => false, // Selector rules, labels, mailto/tel context, event properties, diagnostics.
			'gtm'               => false, // Google Tag Manager dataLayer bridge.
			'journey_analysis'  => false, // Journey analytics beyond the basic lists: flow diagram, exploring by selection, filters, comparison, full lists.
			'commerce_attribution' => false, // WooCommerce: sales by campaign, add-to-cart numbers, choosing which statuses count.
			'goals_advanced'    => false, // Goals: unlimited goals, a value per goal, funnels and segmentation.
			'campaigns_detail'  => false, // Campaign report: combined source / medium / campaign views and comparison with the previous period.
			'journey_visual'    => false, // Visual journey of one session: connected flow, identity-change card, session navigation.
			'branding'          => false, // Company logo on FloLens screens, shared links and reports.
			'page_audit'        => false, // Page Intelligence: the full set of audit findings, the report per page and the prioritised list. The page and element inventory stays in Lite.
			'elements_advanced' => false, // Elements: journeys around an element, goal correlation and findings per element.
			'studio_remote'     => false, // Studio: this site can be read by a central FloLens Studio (summaries only, read only).
			'statistics_builder' => false, // Statistics Builder: several named dashboards with a widget library, each shareable on its own.
			'ai_access'         => false, // Copy for AI: a button on the screens that builds a pasteable, counters-only text for any AI chat, and the AI Personas settings (the live connection is built but switched off).
			'alerts'            => false, // Alerts: milestone, event and daily-threshold rules delivered by e-mail or a generic webhook.
			'form_detection'    => false, // Automatic recognition of form builders (WPForms, Gravity Forms, …) and confirmed form sends. Manual [userflo_form] naming stays in Lite.
		);
	}

	/**
	 * Numeric limits. `0` means "no limit". FloLens (free) has no commercial limits: everything it can show, count or list is unlimited, so
	 * nothing is withheld until someone upgrades. Pro adds features (screens and tools that exist only in the Pro plugin), not bigger numbers.
	 * What remains is a safety bound that applies to every edition.
	 */
	private static function lite_limits() {
		return array(
			'overview_days'     => 0,
			'activity_events'   => 0,
			'visitor_sessions'  => 0,
			'calendar_months'   => 0,
			'goals'             => 0,
			'exclusions'        => 0,
			'audit_pages'       => 0, // The audit still has a hard cap and a daily scan cap that protect the site (Scanner), the same for everyone.
			'alert_rules'       => 50, // Active alert rules per site. A safety bound, not a commercial limit: Pro keeps it (see Features::limits).
			'journey_rows'      => 0,
		);
	}

	/** @var array|null */
	private static $cache = null;

	public static function reset() {
		self::$cache = null;
	}

	/** @return array<string,bool> */
	public static function features() {
		if ( null === self::$cache ) {
			$f = (array) apply_filters( 'userflo_edition_features', self::lite_features() );
			$l = (array) apply_filters( 'userflo_edition_limits', self::lite_limits() );
			// Only known keys survive and values are normalised, so a careless filter cannot invent a feature.
			$features = array();
			foreach ( self::lite_features() as $k => $_v ) {
				$features[ $k ] = ! empty( $f[ $k ] );
			}
			$limits = array();
			foreach ( self::lite_limits() as $k => $v ) {
				$limits[ $k ] = isset( $l[ $k ] ) ? max( 0, (int) $l[ $k ] ) : $v;
			}
			self::$cache = array( 'features' => $features, 'limits' => $limits );
		}
		return self::$cache['features'];
	}

	public static function can( $feature ) {
		$f = self::features();
		return isset( $f[ $feature ] ) && $f[ $feature ];
	}

	/** @return int Limit value; 0 means unlimited. */
	public static function limit( $name ) {
		self::features();
		return isset( self::$cache['limits'][ $name ] ) ? self::$cache['limits'][ $name ] : 0;
	}

	/** True when the Pro add-on is installed, entitled and active. */
	public static function is_pro() {
		return (bool) apply_filters( 'userflo_is_pro', false );
	}

	public static function upgrade_url() {
		return (string) apply_filters( 'userflo_upgrade_url', self::UPGRADE_URL );
	}

	/** Lite and Pro share this REST error so clients can recognise it. */
	public static function denied( $feature ) {
		return new \WP_Error(
			'userflo_pro_required',
			__( 'This feature is part of FloLens Pro.', 'flolens' ),
			array( 'status' => 403, 'feature' => (string) $feature, 'upgrade_url' => self::upgrade_url() )
		);
	}

	/** @return true|\WP_Error */
	public static function require_feature( $feature ) {
		return self::can( $feature ) ? true : self::denied( $feature );
	}

	/** Whole configuration for the admin app (read only). */
	public static function export() {
		self::features();
		return array(
			'pro'        => self::is_pro(),
			'features'   => self::$cache['features'],
			'limits'     => self::$cache['limits'],
			'upgradeUrl' => self::upgrade_url(),
		);
	}
}
