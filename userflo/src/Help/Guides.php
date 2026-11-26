<?php
namespace UserFlo\Help;

defined( 'ABSPATH' ) || exit;

/** Bundled guides (userflo/docs/*.md) listed in Settings → Help and opened in a modal. */
final class Guides {

	/** slug => array( title, summary, file ). */
	public static function all() {
		return array(
			'install'                 => array( __( 'Getting started', 'userflo' ), __( 'Install, activate and check that tracking works.', 'userflo' ), 'INSTALL.md' ),
			'setup-wizard'            => array( __( 'Setup wizard', 'userflo' ), __( 'The first-run wizard, what each option does, and report language.', 'userflo' ), 'SETUP-WIZARD.md' ),
			'configuration'           => array( __( 'Configuration reference', 'userflo' ), __( 'Every setting explained.', 'userflo' ), 'CONFIGURATION.md' ),
			'geolocation-setup'       => array( __( 'Geolocation: where to get the MMDB file', 'userflo' ), __( 'DB-IP Lite, MaxMind GeoLite2 or your own database, step by step.', 'userflo' ), 'GEOLOCATION-SETUP.md' ),
			'connecting-integrations' => array( __( 'Connecting GA4, Clarity and PostHog', 'userflo' ), __( 'Keys, IDs and service accounts, or reuse an installed plugin.', 'userflo' ), 'CONNECTING-INTEGRATIONS.md' ),
			'geolocation'             => array( __( 'Geolocation and the Map', 'userflo' ), __( 'How locations are stored, privacy and limits.', 'userflo' ), 'GEOLOCATION.md' ),
			'integrations'            => array( __( 'Integrations reference', 'userflo' ), __( 'What each provider can and cannot do.', 'userflo' ), 'INTEGRATIONS.md' ),
			'sharing'                 => array( __( 'Share links, feeds and Excel / Google Sheets', 'userflo' ), __( 'Expiring read-only links and data export.', 'userflo' ), 'SHARING.md' ),
			'shortcodes'              => array( __( 'Shortcodes', 'userflo' ), __( 'Show statistics, achievements and the map on your pages.', 'userflo' ), 'SHORTCODES.md' ),
			'heatmaps'                => array( __( 'Heatmaps', 'userflo' ), __( 'Click heatmaps, scroll depth and their limits.', 'userflo' ), 'HEATMAPS.md' ),
			'map-globe'               => array( __( '3D globe and live activity', 'userflo' ), __( 'The globe view of the Map: markers, soft pulses, the live feed panel, privacy and the edition boundary.', 'userflo' ), 'MAP-GLOBE.md' ),
			'editions'                => array( __( 'UserFlo free and Pro', 'userflo' ), __( 'What is free, what Pro adds, why, and what happens to your data.', 'userflo' ), 'EDITIONS.md' ),
			'journey-analytics'       => array( __( 'Journey analytics', 'userflo' ), __( 'Entrances, common journeys and recorded exits for a day, week or month, and exactly how they are counted.', 'userflo' ), 'JOURNEY-ANALYTICS.md' ),
			'visual-journey'          => array( __( 'Visual journey', 'userflo' ), __( 'The pages of one visit as a connected flow, with engagement time and the moment a visitor signs in.', 'userflo' ), 'VISUAL-JOURNEY.md' ),
			'forms'                   => array( __( 'Tracking forms', 'userflo' ), __( 'Name any form with a shortcode; Pro detects the common form builders.', 'userflo' ), 'FORMS.md' ),
			'advanced-tracking'       => array( __( 'Advanced tracking (Pro)', 'userflo' ), __( 'Selector rules, event properties, the GTM bridge and diagnostics.', 'userflo' ), 'ADVANCED-TRACKING.md' ),
			'statistics-builder'      => array( __( 'Statistics Builder (Pro)', 'userflo' ), __( 'Build your own dashboards from widgets, switch between them in Statistics and share each one on its own.', 'userflo' ), 'STATISTICS-BUILDER.md' ),
			'alerts'                  => array( __( 'Alerts (Pro)', 'userflo' ), __( 'E-mail and webhook notifications for milestones, events and low or high days: setup, webhook format, signatures, privacy and limits.', 'userflo' ), 'ALERTS.md' ),
			'pro-licensing'           => array( __( 'Pro licence, plans and updates', 'userflo' ), __( 'Plans, activation, renewal, expiry, outages, moving a licence and the shop setup.', 'userflo' ), 'PRO-LICENSING.md' ),
			'traffic-calendar'        => array( __( 'Traffic calendar', 'userflo' ), __( 'Month and week heatmap of your traffic, day details, hourly heatmap and how colours and metrics are defined.', 'userflo' ), 'TRAFFIC-CALENDAR.md' ),
			'email-reports'           => array( __( 'E-mail reports', 'userflo' ), __( 'Scheduled reports by e-mail: SMTP, recipients, schedule, content, retries and privacy.', 'userflo' ), 'EMAIL-REPORTS.md' ),
			'cookieless'              => array( __( 'Session-only (cookieless) tracking', 'userflo' ), __( 'No cookies, no returning-visitor recognition, no IP address stored: what it measures, what it cannot, and the legal limits.', 'userflo' ), 'COOKIELESS.md' ),
			'woocommerce'             => array( __( 'WooCommerce sales', 'userflo' ), __( 'Orders and revenue from WooCommerce, how they are defined, attribution limits and what to verify on your store.', 'userflo' ), 'WOOCOMMERCE.md' ),
			'goals'                   => array( __( 'Goals and conversions', 'userflo' ), __( 'Define outcomes, how completions are counted, conversion rates and funnels.', 'userflo' ), 'GOALS.md' ),
			'audit'                   => array( __( 'Website audit and Page Intelligence', 'userflo' ), __( 'How UserFlo reads your pages, what it finds, what the free version and Pro show, and how to hide findings you accept.', 'userflo' ), 'WEBSITE-AUDIT.md' ),
			'storage'                 => array( __( 'Database & Storage', 'userflo' ), __( 'How much space UserFlo uses, how long detailed data is kept, what stays in the daily summaries, and how to run the cleanup safely.', 'userflo' ), 'DATABASE-STORAGE.md' ),
			'accessibility'           => array( __( 'Accessibility checks', 'userflo' ), __( 'What the audit checks against WCAG 2.2, what automated checks cannot tell you, and how to read the results.', 'userflo' ), 'ACCESSIBILITY.md' ),
			'elements'                => array( __( 'Elements', 'userflo' ), __( 'Buttons, links, forms and files on your pages and how visitors use them.', 'userflo' ), 'ELEMENTS.md' ),
			'exclusions'              => array( __( 'Exclusions', 'userflo' ), __( 'Stop tracking, leave data out of statistics or accept audit findings, for a page or an interaction.', 'userflo' ), 'EXCLUSIONS.md' ),
			'campaigns'               => array( __( 'Campaigns (UTM)', 'userflo' ), __( 'Which campaigns bring traffic: what is captured, the attribution rule and how it differs from GA4.', 'userflo' ), 'CAMPAIGNS.md' ),
			'wordpress-reports'       => array( __( 'WordPress reports', 'userflo' ), __( 'Content types and categories, forms, WooCommerce products and builder modules: what is measured and what is not.', 'userflo' ), 'WORDPRESS-REPORTS.md' ),
			'builders'                => array( __( 'Page builders', 'userflo' ), __( 'Divi, Elementor, Bricks, WooCommerce and Gutenberg notes.', 'userflo' ), 'BUILDERS.md' ),
			'privacy'                 => array( __( 'Privacy and data', 'userflo' ), __( 'What is collected, consent, retention and erasure.', 'userflo' ), 'PRIVACY.md' ),
			'translating'             => array( __( 'Translations', 'userflo' ), __( 'Edit or add languages from the Translations tab.', 'userflo' ), 'TRANSLATING.md' ),
			'ai-access'               => array( __( 'Copy for AI (Pro)', 'userflo' ), __( 'Copy a counters-only summary of any screen into ChatGPT, Claude or another AI chat, and set the AI personas.', 'userflo' ), 'AI.md' ),
			'changelog'               => array( __( 'Changelog', 'userflo' ), __( 'What changed in each release.', 'userflo' ), 'CHANGELOG.md' ),
		);
	}

	/** Guides live in Lite's docs folder; add-ons (UserFlo Pro) add theirs with the `userflo_docs_dirs` filter. */
	public static function path( $file ) {
		foreach ( (array) apply_filters( 'userflo_docs_dirs', array( USERFLO_DIR . 'docs/' ) ) as $dir ) {
			if ( is_readable( $dir . $file ) ) {
				return $dir . $file;
			}
		}
		return USERFLO_DIR . 'docs/' . $file;
	}

	public static function list_items() {
		$out = array();
		foreach ( self::all() as $slug => $g ) {
			if ( is_readable( self::path( $g[2] ) ) ) {
				$out[] = array( 'slug' => $slug, 'title' => $g[0], 'summary' => $g[1] );
			}
		}
		return $out;
	}

	/** @return array|null array( title, html ) */
	public static function render( $slug ) {
		$all = self::all();
		if ( 'license' === $slug ) {
			$f = USERFLO_DIR . 'license.txt';
			return is_readable( $f ) ? array( __( 'Licence (GPL-2.0-or-later)', 'userflo' ), '<pre>' . esc_html( (string) file_get_contents( $f ) ) . '</pre>' ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		if ( ! isset( $all[ $slug ] ) || ! is_readable( self::path( $all[ $slug ][2] ) ) ) {
			return null;
		}
		$md = (string) file_get_contents( self::path( $all[ $slug ][2] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$md = preg_replace( '/^#\s+[^\n]*\R/', '', $md, 1 ); // The modal title already shows the guide's heading.
		return array( $all[ $slug ][0], Markdown::render( $md ) );
	}
}
