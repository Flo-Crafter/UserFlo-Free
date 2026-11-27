<?php
namespace UserFlo\Help;

defined( 'ABSPATH' ) || exit;

/** Bundled guides (flolens/docs/*.md) listed in Settings → Help and opened in a modal. */
final class Guides {

	/** slug => array( title, summary, file ). */
	public static function all() {
		return array(
			'install'                 => array( __( 'Getting started', 'flolens' ), __( 'Install, activate and check that tracking works.', 'flolens' ), 'INSTALL.md' ),
			'setup-wizard'            => array( __( 'Setup wizard', 'flolens' ), __( 'The first-run wizard, what each option does, and report language.', 'flolens' ), 'SETUP-WIZARD.md' ),
			'configuration'           => array( __( 'Configuration reference', 'flolens' ), __( 'Every setting explained.', 'flolens' ), 'CONFIGURATION.md' ),
			'geolocation-setup'       => array( __( 'Geolocation: where to get the MMDB file', 'flolens' ), __( 'DB-IP Lite, MaxMind GeoLite2 or your own database, step by step.', 'flolens' ), 'GEOLOCATION-SETUP.md' ),
			'connecting-integrations' => array( __( 'Connecting GA4, Clarity and PostHog', 'flolens' ), __( 'Keys, IDs and service accounts, or reuse an installed plugin.', 'flolens' ), 'CONNECTING-INTEGRATIONS.md' ),
			'geolocation'             => array( __( 'Geolocation and the Map', 'flolens' ), __( 'How locations are stored, privacy and limits.', 'flolens' ), 'GEOLOCATION.md' ),
			'integrations'            => array( __( 'Integrations reference', 'flolens' ), __( 'What each provider can and cannot do.', 'flolens' ), 'INTEGRATIONS.md' ),
			'sharing'                 => array( __( 'Share links, feeds and Excel / Google Sheets', 'flolens' ), __( 'Expiring read-only links and data export.', 'flolens' ), 'SHARING.md' ),
			'shortcodes'              => array( __( 'Shortcodes', 'flolens' ), __( 'Show statistics, achievements and the map on your pages.', 'flolens' ), 'SHORTCODES.md' ),
			'heatmaps'                => array( __( 'Heatmaps', 'flolens' ), __( 'Click heatmaps, scroll depth and their limits.', 'flolens' ), 'HEATMAPS.md' ),
			'map-globe'               => array( __( '3D globe and live activity', 'flolens' ), __( 'The globe view of the Map: markers, soft pulses, the live feed panel, privacy and the edition boundary.', 'flolens' ), 'MAP-GLOBE.md' ),
			'editions'                => array( __( 'FloLens free and Pro', 'flolens' ), __( 'What is free, what Pro adds, why, and what happens to your data.', 'flolens' ), 'EDITIONS.md' ),
			'journey-analytics'       => array( __( 'Journey analytics', 'flolens' ), __( 'Entrances, common journeys and recorded exits for a day, week or month, and exactly how they are counted.', 'flolens' ), 'JOURNEY-ANALYTICS.md' ),
			'visual-journey'          => array( __( 'Visual journey', 'flolens' ), __( 'The pages of one visit as a connected flow, with engagement time and the moment a visitor signs in.', 'flolens' ), 'VISUAL-JOURNEY.md' ),
			'forms'                   => array( __( 'Tracking forms', 'flolens' ), __( 'Name any form with a shortcode; Pro detects the common form builders.', 'flolens' ), 'FORMS.md' ),
			'advanced-tracking'       => array( __( 'Advanced tracking (Pro)', 'flolens' ), __( 'Selector rules, event properties, the GTM bridge and diagnostics.', 'flolens' ), 'ADVANCED-TRACKING.md' ),
			'statistics-builder'      => array( __( 'Statistics Builder (Pro)', 'flolens' ), __( 'Build your own dashboards from widgets, switch between them in Statistics and share each one on its own.', 'flolens' ), 'STATISTICS-BUILDER.md' ),
			'alerts'                  => array( __( 'Alerts (Pro)', 'flolens' ), __( 'E-mail and webhook notifications for milestones, events and low or high days: setup, webhook format, signatures, privacy and limits.', 'flolens' ), 'ALERTS.md' ),
			'pro-licensing'           => array( __( 'Pro licence, plans and updates', 'flolens' ), __( 'Plans, activation, renewal, expiry, outages, moving a licence and the shop setup.', 'flolens' ), 'PRO-LICENSING.md' ),
			'traffic-calendar'        => array( __( 'Traffic calendar', 'flolens' ), __( 'Month and week heatmap of your traffic, day details, hourly heatmap and how colours and metrics are defined.', 'flolens' ), 'TRAFFIC-CALENDAR.md' ),
			'email-reports'           => array( __( 'E-mail reports', 'flolens' ), __( 'Scheduled reports by e-mail: SMTP, recipients, schedule, content, retries and privacy.', 'flolens' ), 'EMAIL-REPORTS.md' ),
			'cookieless'              => array( __( 'Session-only (cookieless) tracking', 'flolens' ), __( 'No cookies, no returning-visitor recognition, no IP address stored: what it measures, what it cannot, and the legal limits.', 'flolens' ), 'COOKIELESS.md' ),
			'woocommerce'             => array( __( 'WooCommerce sales', 'flolens' ), __( 'Orders and revenue from WooCommerce, how they are defined, attribution limits and what to verify on your store.', 'flolens' ), 'WOOCOMMERCE.md' ),
			'goals'                   => array( __( 'Goals and conversions', 'flolens' ), __( 'Define outcomes, how completions are counted, conversion rates and funnels.', 'flolens' ), 'GOALS.md' ),
			'audit'                   => array( __( 'Website audit and Page Intelligence', 'flolens' ), __( 'How FloLens reads your pages, what it finds, what the free version and Pro show, and how to hide findings you accept.', 'flolens' ), 'WEBSITE-AUDIT.md' ),
			'storage'                 => array( __( 'Database & Storage', 'flolens' ), __( 'How much space FloLens uses, how long detailed data is kept, what stays in the daily summaries, and how to run the cleanup safely.', 'flolens' ), 'DATABASE-STORAGE.md' ),
			'accessibility'           => array( __( 'Accessibility checks', 'flolens' ), __( 'What the audit checks against WCAG 2.2, what automated checks cannot tell you, and how to read the results.', 'flolens' ), 'ACCESSIBILITY.md' ),
			'elements'                => array( __( 'Elements', 'flolens' ), __( 'Buttons, links, forms and files on your pages and how visitors use them.', 'flolens' ), 'ELEMENTS.md' ),
			'exclusions'              => array( __( 'Exclusions', 'flolens' ), __( 'Stop tracking, leave data out of statistics or accept audit findings, for a page or an interaction.', 'flolens' ), 'EXCLUSIONS.md' ),
			'campaigns'               => array( __( 'Campaigns (UTM)', 'flolens' ), __( 'Which campaigns bring traffic: what is captured, the attribution rule and how it differs from GA4.', 'flolens' ), 'CAMPAIGNS.md' ),
			'wordpress-reports'       => array( __( 'WordPress reports', 'flolens' ), __( 'Content types and categories, forms, WooCommerce products and builder modules: what is measured and what is not.', 'flolens' ), 'WORDPRESS-REPORTS.md' ),
			'builders'                => array( __( 'Page builders', 'flolens' ), __( 'Divi, Elementor, Bricks, WooCommerce and Gutenberg notes.', 'flolens' ), 'BUILDERS.md' ),
			'privacy'                 => array( __( 'Privacy and data', 'flolens' ), __( 'What is collected, consent, retention and erasure.', 'flolens' ), 'PRIVACY.md' ),
			'translating'             => array( __( 'Translations', 'flolens' ), __( 'Edit or add languages from the Translations tab.', 'flolens' ), 'TRANSLATING.md' ),
			'ai-access'               => array( __( 'Copy for AI (Pro)', 'flolens' ), __( 'Copy a counters-only summary of any screen into ChatGPT, Claude or another AI chat, and set the AI personas.', 'flolens' ), 'AI.md' ),
			'changelog'               => array( __( 'Changelog', 'flolens' ), __( 'What changed in each release.', 'flolens' ), 'CHANGELOG.md' ),
		);
	}

	/** Guides live in Lite's docs folder; add-ons (FloLens Pro) add theirs with the `userflo_docs_dirs` filter. */
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
			return is_readable( $f ) ? array( __( 'Licence (GPL-2.0-or-later)', 'flolens' ), '<pre>' . esc_html( (string) file_get_contents( $f ) ) . '</pre>' ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		if ( ! isset( $all[ $slug ] ) || ! is_readable( self::path( $all[ $slug ][2] ) ) ) {
			return null;
		}
		$md = (string) file_get_contents( self::path( $all[ $slug ][2] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$md = preg_replace( '/^#\s+[^\n]*\R/', '', $md, 1 ); // The modal title already shows the guide's heading.
		return array( $all[ $slug ][0], Markdown::render( $md ) );
	}
}
