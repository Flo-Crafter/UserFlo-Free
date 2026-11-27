<?php
namespace UserFlo\Admin;

use UserFlo\Edition;
use UserFlo\Links;

defined( 'ABSPATH' ) || exit;

/**
 * Three small Settings tabs that talk to the developer and the user: "Support the developer", "Report a bug or request" (a form that sends
 * one e-mail) and, in Lite, "Free vs Pro" (what the upgrade adds). Nothing here records or sends anything on its own: the form sends only
 * when an administrator presses the button, and only what is shown in it.
 */
final class CommunityTabs {

	const ACTION = 'userflo_feedback';
	const RATE   = 'userflo_feedback_rate';

	public static function register() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/** Adds the tabs (before About) to the Settings tab list. */
	public static function tabs( $tabs ) {
		$out = array();
		foreach ( $tabs as $id => $t ) {
			if ( 'about' === $id ) {
				if ( Links::feedback_ready() ) { // Without a configured mailbox the form could only say "not set up", so the tab is left out.
					$out['feedback'] = array( __( 'Report a bug or request', 'flolens' ), 'dashicons-megaphone' );
				}
				$out['support']  = array( __( 'Support the developer', 'flolens' ), 'dashicons-heart' );
				if ( ! Edition::is_pro() ) {
					$out['compare'] = array( __( 'Free vs Pro', 'flolens' ), 'dashicons-columns' );
				}
			}
			$out[ $id ] = $t;
		}
		return $out;
	}

	/* ---- Support the developer ---- */

	public static function support() {
		$donate = Links::donate();
		if ( $donate ) {
			echo '<p>' . esc_html__( 'FloLens is developed by a very small team of one person. If this free version is useful to you and you are happy with it, please consider a donation towards its development. Every contribution helps to keep it maintained and improving. Thank you!', 'flolens' ) . '</p>';
			echo '<p class="uf-support-buttons">';
			foreach ( $donate as $id => $l ) {
				echo '<a class="button button-primary uf-support-' . esc_attr( $id ) . '" href="' . esc_url( $l[1] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $l[0] ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'flolens' ) . '</span></a> ';
			}
			echo '</p>';
		}
		echo '<h3>' . esc_html__( 'More from the same developer', 'flolens' ) . '</h3>';
		echo '<p>' . esc_html__( 'Take a look at the main website for other useful apps and plugins, and for deals.', 'flolens' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( Links::website() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Visit the website', 'flolens' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'flolens' ) . '</span></a></p>';
		if ( $donate ) {
			echo '<p class="description">' . esc_html__( 'Donations are optional and nothing in FloLens depends on them. FloLens never contacts these sites by itself: the links only open when you click them.', 'flolens' ) . '</p>';
		}
	}

	/* ---- Report a bug or request ---- */

	public static function types() {
		return array(
			'bug'     => __( 'Bug', 'flolens' ),
			'feature' => __( 'Feature request', 'flolens' ),
			'other'   => __( 'Other', 'flolens' ),
		);
	}

	public static function feedback() {
		$user  = wp_get_current_user();
		$flash = isset( $_GET['uf_fb'] ) ? sanitize_key( wp_unslash( $_GET['uf_fb'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$msgs  = array(
			'sent'    => array( 'success', __( 'Thank you! Your message was sent.', 'flolens' ) ),
			'invalid' => array( 'error', __( 'Please fill in your name, a valid e-mail address and the type of request.', 'flolens' ) ),
			'rate'    => array( 'error', __( 'Several messages were sent in the last hour. Please try again later.', 'flolens' ) ),
			'nosetup' => array( 'warning', __( 'Sending is not set up yet: the developer’s mailbox has not been configured in this build. Nothing was sent.', 'flolens' ) ),
			'fail'    => array( 'error', __( 'The message could not be sent. Check that this site can send e-mail and try again.', 'flolens' ) ),
		);
		if ( isset( $msgs[ $flash ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $msgs[ $flash ][0] ) . ' inline" role="status"><p>' . esc_html( $msgs[ $flash ][1] ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Found a problem or have an idea? Tell the developer. This sends one e-mail with exactly what you enter below; nothing else is sent unless you tick the box for technical details.', 'flolens' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="uf-feedback">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		wp_nonce_field( self::ACTION );
		echo '<table class="form-table" role="presentation">';
		printf( '<tr><th scope="row"><label for="uf-fb-name">%s</label></th><td><input id="uf-fb-name" type="text" name="fb_name" class="regular-text" maxlength="100" required value="%s" autocomplete="name"></td></tr>', esc_html__( 'Your name', 'flolens' ), esc_attr( $user->display_name ) );
		printf( '<tr><th scope="row"><label for="uf-fb-email">%s</label></th><td><input id="uf-fb-email" type="email" name="fb_email" class="regular-text" maxlength="150" required value="%s" autocomplete="email"><p class="description">%s</p></td></tr>', esc_html__( 'Your e-mail', 'flolens' ), esc_attr( $user->user_email ), esc_html__( 'So the developer can reply.', 'flolens' ) );
		echo '<tr><th scope="row"><label for="uf-fb-type">' . esc_html__( 'Type of request', 'flolens' ) . '</label></th><td><select id="uf-fb-type" name="fb_type">';
		foreach ( self::types() as $k => $l ) {
			printf( '<option value="%s">%s</option>', esc_attr( $k ), esc_html( $l ) );
		}
		echo '</select></td></tr>';
		printf( '<tr><th scope="row"><label for="uf-fb-msg">%s</label></th><td><textarea id="uf-fb-msg" name="fb_message" rows="6" class="large-text" maxlength="5000"></textarea><p class="description">%s</p></td></tr>', esc_html__( 'Message (optional)', 'flolens' ), esc_html__( 'What happened, or what would you like? Do not include passwords or visitor data.', 'flolens' ) );
		printf( '<tr><th scope="row">%s</th><td><label><input type="checkbox" name="fb_system" value="1"> %s</label><p class="description">%s</p></td></tr>', esc_html__( 'Technical details', 'flolens' ), esc_html__( 'Include versions of WordPress, PHP, FloLens and the theme, and the site address', 'flolens' ), esc_html__( 'Helps to reproduce a bug. Off by default; no visitor data and no settings values are included.', 'flolens' ) );
		echo '</table>';
		// Honeypot: invisible to people, tempting to bots.
		echo '<p class="screen-reader-text" aria-hidden="true"><label>' . esc_html__( 'Leave this empty', 'flolens' ) . ' <input type="text" name="fb_website" tabindex="-1" autocomplete="off"></label></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Send', 'flolens' ) . '</button></p></form>';
	}

	/** @return string One of: sent | invalid | rate | nosetup | fail. Pure of redirects, so tests can call it. */
	public static function send( array $in ) {
		if ( ! empty( $in['fb_website'] ) ) {
			return 'sent'; // A bot: pretend it worked, send nothing.
		}
		$name  = sanitize_text_field( isset( $in['fb_name'] ) ? (string) $in['fb_name'] : '' );
		$email = sanitize_email( isset( $in['fb_email'] ) ? (string) $in['fb_email'] : '' );
		$type  = isset( $in['fb_type'] ) ? sanitize_key( (string) $in['fb_type'] ) : '';
		$msg   = isset( $in['fb_message'] ) ? trim( sanitize_textarea_field( (string) $in['fb_message'] ) ) : '';
		$msg   = \UserFlo\Core\Text::substr( $msg, 5000 );
		if ( '' === $name || ! is_email( $email ) || ! isset( self::types()[ $type ] ) ) {
			return 'invalid';
		}
		$to = Links::feedback_email();
		if ( '' === $to || ! Links::is_real( $to ) ) {
			return 'nosetup';
		}
		$count = (int) get_transient( self::RATE );
		if ( $count >= 5 ) {
			return 'rate';
		}
		$body  = self::types()[ $type ] . "\n\n" . $name . ' <' . $email . ">\n\n" . ( '' !== $msg ? $msg : '(no message)' ) . "\n";
		if ( ! empty( $in['fb_system'] ) ) {
			$theme = wp_get_theme();
			$body .= "\n--\n" . 'Site: ' . home_url() . "\n" . 'WordPress: ' . get_bloginfo( 'version' ) . "\n" . 'PHP: ' . PHP_VERSION . "\n" . 'FloLens: ' . USERFLO_VERSION . ( defined( 'USERFLO_PRO_VERSION' ) ? ' · Pro ' . USERFLO_PRO_VERSION . ( Edition::is_pro() ? ' (active)' : ' (not entitled)' ) : '' ) . "\n" . 'Theme: ' . $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . "\n";
		}
		$subject = '[FloLens ' . self::types()[ $type ] . '] ' . $name;
		$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );
		$ok      = wp_mail( $to, wp_strip_all_tags( $subject ), $body, $headers );
		if ( ! $ok ) {
			return 'fail';
		}
		set_transient( self::RATE, $count + 1, HOUR_IN_SECONDS );
		return 'sent';
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'flolens' ), 403 );
		}
		check_admin_referer( self::ACTION );
		$result = self::send( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitised in send().
		wp_safe_redirect( add_query_arg( array( 'page' => 'userflo-settings', 'tab' => 'feedback', 'uf_fb' => $result ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---- Free vs Pro ---- */

	/** @return array<int,array{0:string,1:string,2:string}> area, what Lite has, what Pro adds. */
	public static function matrix() {
		return array(
			array( __( 'Tracking and privacy', 'flolens' ), __( 'Everything: page views, visitors, sessions, clicks, forms, scroll depth, consent, retention, erasure', 'flolens' ), __( 'The same, with the same privacy controls', 'flolens' ) ),
			array( __( 'Overview and statistics', 'flolens' ), __( '24 hours to 1 year, KPIs, top pages and sources, standard summaries', 'flolens' ), __( 'Custom date ranges, highlights, WordPress content, forms and shop reports', 'flolens' ) ),
			array( __( 'Visitors', 'flolens' ), __( 'List, search, sorting; every session and event of a visitor', 'flolens' ), __( 'Advanced filters, lifetime calendar, e-mail and account detail', 'flolens' ) ),
			array( __( 'Journeys', 'flolens' ), __( 'Basic summary: top entrances, exits and complete paths; simple timeline of a visit', 'flolens' ), __( 'Flow diagram, filters, comparison, longer periods; visual journey of a visit with the identity change', 'flolens' ) ),
			array( __( 'Map', 'flolens' ), __( '2D map with country totals', 'flolens' ), __( 'City level, 3D globe, live activity feed', 'flolens' ) ),
			array( __( 'Traffic calendar', 'flolens' ), __( 'Monthly view of page views, all months', 'flolens' ), __( 'Week view, all metrics, day and hour detail', 'flolens' ) ),
			array( __( 'Forms', 'flolens' ), __( 'Every submission counted; name a form with a shortcode', 'flolens' ), __( 'Automatic detection of the main form builders with confirmed sends', 'flolens' ) ),
			array( __( 'Heatmaps', 'flolens' ), '', __( 'Click heatmaps and scroll depth', 'flolens' ) ),
			array( __( 'Reports and e-mail', 'flolens' ), __( 'CSV and JSON export, shortcodes, share links', 'flolens' ), __( 'Report builder (PDF and PNG), scheduled e-mail reports', 'flolens' ) ),
			array( __( 'Integrations', 'flolens' ), '', __( 'Google Analytics 4, Microsoft Clarity, PostHog, Google Tag Manager', 'flolens' ) ),
			array( __( 'Advanced tracking', 'flolens' ), __( 'UserFlo.track() events, exclusion rules', 'flolens' ), __( 'Selector rules and labels, event properties, scroll-depth steps, diagnostics', 'flolens' ) ),
			array( __( 'Branding', 'flolens' ), '', __( 'Your company logo on screens, shared links and reports', 'flolens' ) ),
			array( __( 'Updates', 'flolens' ), __( 'From the WordPress plugin directory', 'flolens' ), __( 'Licensed updates', 'flolens' ) ),
		);
	}

	public static function compare() {
		echo '<p>' . esc_html__( 'The free FloLens is a complete analytics tool for a small site. FloLens Pro adds the deeper tools below. Everything the free version has collected stays in your database and is there when you upgrade; Pro adds no second tracker.', 'flolens' ) . '</p>';
		echo '<table class="widefat striped uf-compare"><thead><tr><th scope="col">' . esc_html__( 'Area', 'flolens' ) . '</th><th scope="col">' . esc_html__( 'FloLens (free)', 'flolens' ) . '</th><th scope="col">' . esc_html__( 'FloLens Pro', 'flolens' ) . '</th></tr></thead><tbody>';
		foreach ( self::matrix() as $r ) {
			echo '<tr><th scope="row">' . esc_html( $r[0] ) . '</th><td>' . ( '' === $r[1] ? '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Not included', 'flolens' ) . '</span>' : '<span class="uf-yes" aria-hidden="true">✓</span> ' . esc_html( $r[1] ) ) . '</td><td><span class="uf-yes" aria-hidden="true">✓</span> ' . esc_html( $r[2] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="uf-compare-actions"><a class="button button-primary" href="' . esc_url( Edition::upgrade_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Upgrade to Pro', 'flolens' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'flolens' ) . '</span></a> <a class="button" href="' . esc_url( Links::website() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Visit the website', 'flolens' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'flolens' ) . '</span></a> <a class="button" href="' . esc_url( Links::directory() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'WordPress plugin directory', 'flolens' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'flolens' ) . '</span></a></p>';
		echo '<p class="description">' . esc_html__( 'Read the full guide under Help → “FloLens free and Pro”.', 'flolens' ) . '</p>';
	}
}
