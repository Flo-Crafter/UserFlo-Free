<?php
namespace UserFlo\Admin;

use UserFlo\Edition;
use UserFlo\Modules;
use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * First-run setup wizard (also reachable again from Settings). One form, shown a step at a time; every option explains what it
 * does, why you might want it and how much it affects the site. Nothing is saved until the last step; skipping changes nothing.
 */
final class SetupWizard {

	const SLUG   = 'userflo-setup';
	const OPTION = 'userflo_setup';
	const REDIRECT = 'userflo_setup_redirect';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
		add_action( 'admin_post_userflo_setup_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_userflo_setup_skip', array( __CLASS__, 'handle_skip' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	public static function url() {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	public static function state() {
		$o = get_option( self::OPTION, array() );
		return is_array( $o ) ? $o : array();
	}

	public static function is_done() {
		$s = self::state();
		return ! empty( $s['done'] ) || ! empty( $s['skipped'] );
	}

	/** Hidden page: reachable by URL (and from Settings) but not listed in the menu. */
	public static function menu() {
		add_submenu_page( 'options.php', __( 'Setup wizard', 'flolens' ), __( 'Setup wizard', 'flolens' ), 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function maybe_redirect() {
		if ( ! get_transient( self::REDIRECT ) || wp_doing_ajax() || wp_doing_cron() || is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		delete_transient( self::REDIRECT );
		if ( ! current_user_can( 'manage_options' ) || self::is_done() ) {
			return;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	private static function on_userflo_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && false !== strpos( (string) $screen->id, 'userflo' );
	}

	public static function notice() {
		if ( ! current_user_can( 'manage_options' ) || ! self::on_userflo_screen() ) {
			return;
		}
		$screen = get_current_screen();
		if ( false !== strpos( (string) $screen->id, self::SLUG ) ) {
			return;
		}
		if ( isset( $_GET['uf_setup'] ) && 'done' === $_GET['uf_setup'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Setup finished. You can change any of these choices later under FloLens → Settings, or run the wizard again from there.', 'flolens' ) . '</p></div>';
			return;
		}
		if ( ! self::is_done() ) {
			echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Welcome to FloLens.', 'flolens' ) . '</strong> ' . esc_html__( 'A two-minute setup wizard helps you pick the logo, style, language, site type and privacy options.', 'flolens' ) . ' <a class="button button-primary" href="' . esc_url( self::url() ) . '">' . esc_html__( 'Start the setup wizard', 'flolens' ) . '</a> <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=userflo_setup_skip' ), 'userflo_setup_skip' ) ) . '">' . esc_html__( 'Not now', 'flolens' ) . '</a></p></div>';
		}
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_media();
		$f = USERFLO_DIR . 'assets/admin/setup.js';
		wp_enqueue_script( 'userflo-setup', USERFLO_URL . 'assets/admin/setup.js', array( 'wp-i18n' ), USERFLO_VERSION . '-' . filemtime( $f ), true );
		\UserFlo\I18n::script( 'userflo-setup' );
	}

	public static function handle_skip() {
		check_admin_referer( 'userflo_setup_skip' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'flolens' ), 403 );
		}
		update_option( self::OPTION, array_merge( self::state(), array( 'skipped' => time() ) ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=userflo' ) );
		exit;
	}

	/** Applies the wizard answers on top of the current settings (anything the wizard does not ask about is kept). */
	public static function apply( array $p ) {
		$in = Settings::all();
		foreach ( array( 'ui_style', 'language', 'report_language', 'site_type', 'ip_mode', 'geo_mode' ) as $k ) {
			if ( isset( $p[ $k ] ) ) {
				$in[ $k ] = sanitize_text_field( (string) $p[ $k ] );
			}
		}
		foreach ( array( 'respect_gpc', 'identity_association', 'identity_retroactive', 'heatmaps_enabled' ) as $k ) {
			$in[ $k ] = ! empty( $p[ $k ] );
		}
		if ( isset( $p['retention_days'] ) ) {
			$in['retention_days'] = $p['retention_days'];
		}
		if ( isset( $p['brand_logo_id'] ) ) {
			$in['brand_logo_id'] = absint( $p['brand_logo_id'] );
		}
		$consent = \UserFlo\Privacy\ConsentDetect::detect();
		if ( '' !== $consent && 'none' === (string) $in['consent_mode'] ) {
			$in['consent_mode'] = $consent; // A consent plugin is active: follow its choice (changeable under Settings → Privacy).
		}
		$mods = isset( $p['modules'] ) && is_array( $p['modules'] ) ? $p['modules'] : array();
		$m    = array();
		foreach ( array_keys( Modules::defaults() ) as $k ) {
			if ( in_array( $k, Modules::MANAGER_ONLY, true ) ) {
				$m[ $k ] = Modules::switched_on( $k ); // Not shown in the wizard: left as it is.
				continue;
			}
			$m[ $k ] = ! empty( $mods[ $k ] );
		}
		$in['modules'] = $m;
		return Settings::sanitize( $in );
	}

	public static function handle_save() {
		check_admin_referer( 'userflo_setup_save' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'flolens' ), 403 );
		}
		$p   = isset( $_POST['uf'] ) && is_array( $_POST['uf'] ) ? wp_unslash( $_POST['uf'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in apply().
		$new = self::apply( $p );
		update_option( Settings::OPTION, $new );
		Settings::reset_cache();
		$ints = isset( $p['integrations'] ) && is_array( $p['integrations'] ) ? array_keys( array_filter( $p['integrations'] ) ) : array();
		foreach ( class_exists( '\\UserFlo\\Integrations\\Detect' ) ? $ints : array() as $id ) { // Integrations belong to FloLens Pro.
			\UserFlo\Integrations\Detect::apply( sanitize_key( $id ) );
		}
		if ( 'off' !== $new['geo_mode'] && 'dbip' === $new['geo_source'] ) {
			wp_schedule_single_event( time() + 5, \UserFlo\Geo\Database::CRON, array( 'force' ) );
			spawn_cron();
		}
		update_option( self::OPTION, array( 'done' => time(), 'version' => USERFLO_VERSION ), false );
		delete_transient( self::REDIRECT );
		wp_safe_redirect( add_query_arg( 'uf_setup', 'done', admin_url( 'admin.php?page=userflo' ) ) );
		exit;
	}

	// ---- rendering ------------------------------------------------------------------------------------------------------------

	public static function n( $key ) {
		return 'uf[' . $key . ']';
	}

	/** What / why / effect on the site, as a compact definition list. */
	public static function help( $what, $why, $effect ) {
		echo '<dl class="uf-wz-help"><div><dt>' . esc_html__( 'What it does', 'flolens' ) . '</dt><dd>' . esc_html( $what ) . '</dd></div><div><dt>' . esc_html__( 'Why', 'flolens' ) . '</dt><dd>' . esc_html( $why ) . '</dd></div><div><dt>' . esc_html__( 'Effect on your site', 'flolens' ) . '</dt><dd>' . esc_html( $effect ) . '</dd></div></dl>';
	}

	private static function radios( $key, array $options, $current ) {
		echo '<div class="uf-wz-choices" role="radiogroup">';
		foreach ( $options as $value => $o ) {
			printf(
				'<label class="uf-wz-choice"><input type="radio" name="%1$s" value="%2$s" %3$s data-summary="%4$s" data-vlabel="%5$s"><span class="uf-wz-choice-t">%5$s</span><span class="uf-wz-choice-d">%6$s</span></label>',
				esc_attr( self::n( $key ) ),
				esc_attr( $value ),
				checked( (string) $current, (string) $value, false ),
				esc_attr( $o['group'] ),
				esc_attr( $o['label'] ),
				esc_html( $o['desc'] )
			);
		}
		echo '</div>';
	}

	private static function step_open( $id, $title, $lead ) {
		echo '<section class="uf-wz-step" data-step="' . esc_attr( $id ) . '" hidden><h2 class="uf-wz-h" tabindex="-1">' . esc_html( $title ) . '</h2><p class="uf-wz-lead">' . esc_html( $lead ) . '</p>';
	}

	private static function step_close() {
		echo '</section>';
	}

	private static function languages() {
		return \UserFlo\Translations\Routes::available();
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'flolens' ), 403 );
		}
		$S = Settings::all();
		Ui::open( __( 'Setup wizard', 'flolens' ) );
		$steps = array(
			'welcome'      => __( 'Welcome', 'flolens' ),
			'branding'     => __( 'Logo and style', 'flolens' ),
			'language'     => __( 'Language', 'flolens' ),
			'site'         => __( 'Site type', 'flolens' ),
			'privacy'      => __( 'Privacy', 'flolens' ),
			'features'     => __( 'Features', 'flolens' ),
			'integrations' => __( 'Integrations', 'flolens' ),
			'finish'       => __( 'Review', 'flolens' ),
		);
		if ( ! Edition::can( 'integrations' ) ) {
			unset( $steps['integrations'] );
		}
		echo '<div class="uf-wizard" id="uf-wizard"><ol class="uf-wz-progress" aria-label="' . esc_attr__( 'Setup steps', 'flolens' ) . '">';
		$i = 1;
		foreach ( $steps as $id => $label ) {
			echo '<li data-for="' . esc_attr( $id ) . '"><span class="uf-wz-num" aria-hidden="true">' . (int) $i++ . '</span> <span class="uf-wz-lbl">' . esc_html( $label ) . '</span></li>';
		}
		echo '</ol><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="uf-wizard-form"><input type="hidden" name="action" value="userflo_setup_save">';
		wp_nonce_field( 'userflo_setup_save' );

		// 1. Welcome.
		self::step_open( 'welcome', __( 'Welcome to FloLens', 'flolens' ), __( 'FloLens records how visitors move through your site and keeps everything in your own WordPress database. This short wizard sets the basics; each choice can be changed later in Settings.', 'flolens' ) );
		self::help( __( 'Records page views, clicks, scrolling and sessions of anonymous and logged-in visitors, and shows them in this dashboard.', 'flolens' ), __( 'To understand which pages, buttons and journeys matter, without sending visitor data to an outside service.', 'flolens' ), __( 'A tracker script of about 4 KB (gzipped) loads on public pages and sends small requests to your own server. Typical sites will not notice it.', 'flolens' ) );
		echo '<p class="description">' . esc_html__( 'Nothing is saved until you press “Finish setup” on the last step. You can leave at any time.', 'flolens' ) . '</p>';
		self::step_close();

		// 2. Branding.
		self::step_open( 'branding', __( 'Logo and style', 'flolens' ), __( 'Make FloLens look like yours.', 'flolens' ) );
		do_action( 'userflo_wizard_branding', $S ); // FloLens Pro adds the company logo here.
		echo '<div class="uf-wz-card"><h3>' . esc_html__( 'Dashboard style', 'flolens' ) . '</h3>';
		self::help( __( 'Chooses how all FloLens screens look. The page previews your choice straight away.', 'flolens' ), __( 'Pick what is easiest to read for you and your team (for example Dark for low light).', 'flolens' ), __( 'Looks only. It changes nothing on your public site and has no effect on speed.', 'flolens' ) );
		$group = __( 'Style', 'flolens' );
		self::radios(
			'ui_style',
			array(
				'default' => array( 'label' => __( 'Default (WordPress)', 'flolens' ), 'desc' => __( 'Matches the WordPress admin.', 'flolens' ), 'group' => $group ),
				'dark'    => array( 'label' => __( 'Dark', 'flolens' ), 'desc' => __( 'Dark background, easy on the eyes.', 'flolens' ), 'group' => $group ),
				'glass'   => array( 'label' => __( 'Glass light', 'flolens' ), 'desc' => __( 'Soft translucent panels.', 'flolens' ), 'group' => $group ),
				'elegant' => array( 'label' => __( 'Elegant', 'flolens' ), 'desc' => __( 'Calm, professional look.', 'flolens' ), 'group' => $group ),
				'fancy'   => array( 'label' => __( 'Fancy', 'flolens' ), 'desc' => __( 'Colourful and playful.', 'flolens' ), 'group' => $group ),
				'mono'    => array( 'label' => __( 'Mono', 'flolens' ), 'desc' => __( 'Minimal black and white, smooth edges.', 'flolens' ), 'group' => $group ),
				'noir'    => array( 'label' => __( 'Noir', 'flolens' ), 'desc' => __( 'Minimal grayscale on black.', 'flolens' ), 'group' => $group ),
			),
			$S['ui_style']
		);
		echo '</div>';
		self::step_close();

		// 3. Language.
		self::step_open( 'language', __( 'Language', 'flolens' ), Edition::can( 'reports' ) ? __( 'Choose the language of the dashboard and of exported reports.', 'flolens' ) : __( 'Choose the language of the dashboard.', 'flolens' ) );
		echo '<div class="uf-wz-card"><h3><label for="uf-wz-lang">' . esc_html__( 'Dashboard language', 'flolens' ) . '</label></h3>';
		self::help( __( 'The language of FloLens screens.', 'flolens' ), __( 'Automatic follows each user’s WordPress language; pick one language if your whole team should see the same.', 'flolens' ), __( 'Admin screens only. Visitors never see it.', 'flolens' ) );
		echo '<select id="uf-wz-lang" name="' . esc_attr( self::n( 'language' ) ) . '" data-summary="' . esc_attr__( 'Dashboard language', 'flolens' ) . '"><option value="auto"' . selected( $S['language'], 'auto', false ) . '>' . esc_html__( 'Automatic (WordPress / user language)', 'flolens' ) . '</option>';
		foreach ( self::languages() as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $S['language'], $k, false ), esc_html( $l ) );
		}
		echo '</select></div>';
		if ( Edition::can( 'reports' ) ) { // Exported reports (PDF and PNG) are Pro.
		echo '<div class="uf-wz-card"><h3><label for="uf-wz-rlang">' . esc_html__( 'Report language', 'flolens' ) . '</label></h3>';
		self::help( __( 'The language used inside exported reports (PDF and PNG).', 'flolens' ), __( 'To send reports to clients or colleagues who read another language than the one you work in. You can still change it for each report.', 'flolens' ), __( 'Reports only. Only languages that are installed or added under Settings → Translations are offered.', 'flolens' ) );
		echo '<select id="uf-wz-rlang" name="' . esc_attr( self::n( 'report_language' ) ) . '" data-summary="' . esc_attr__( 'Report language', 'flolens' ) . '"><option value="">' . esc_html__( 'Same as the dashboard language', 'flolens' ) . '</option>';
		foreach ( self::languages() as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $S['report_language'], $k, false ), esc_html( $l ) );
		}
		echo '</select></div>';
		}
		self::step_close();

		// 4. Site type.
		self::step_open( 'site', __( 'Site type', 'flolens' ), __( 'Tell FloLens what kind of site this is.', 'flolens' ) );
		self::help( __( 'Adds a Focus section to the Overview and to reports with the pages, funnel and events that matter for this kind of site (for example basket and checkout for a store).', 'flolens' ), __( 'So the first thing you see answers the questions of your kind of site. “General” keeps the standard overview.', 'flolens' ), __( 'Display only. No extra tracking; it reads data FloLens already collected, with one small query set per view.', 'flolens' ) );
		$opts  = array();
		$descs = \UserFlo\Focus\Profiles::descriptions();
		foreach ( \UserFlo\Focus\Profiles::types() as $k => $l ) {
			$opts[ $k ] = array( 'label' => $l, 'desc' => isset( $descs[ $k ] ) ? $descs[ $k ] : '', 'group' => __( 'Site type', 'flolens' ) );
		}
		self::radios( 'site_type', $opts, $S['site_type'] );
		self::step_close();

		// 5. Privacy.
		self::step_open( 'privacy', __( 'Privacy', 'flolens' ), __( 'Decide how much is stored about visitors. The defaults are the cautious choices.', 'flolens' ) );
		echo '<div class="uf-wz-card"><h3><label for="uf-wz-ip">' . esc_html__( 'IP addresses', 'flolens' ) . '</label></h3>';
		self::help( __( 'Controls whether visitor IP addresses are kept: not at all, shortened (last part removed), as a salted hash, or in full.', 'flolens' ), __( 'Shortened addresses still tell abuse and rough origin apart without identifying a person. Full addresses are personal data in many countries.', 'flolens' ), __( 'No effect on speed. Geolocation still works whatever you pick; the address itself is only used to look the location up.', 'flolens' ) );
		echo '<select id="uf-wz-ip" name="' . esc_attr( self::n( 'ip_mode' ) ) . '" data-summary="' . esc_attr__( 'IP addresses', 'flolens' ) . '">';
		foreach ( array( 'off' => __( 'Do not store', 'flolens' ), 'truncate' => __( 'Store shortened (recommended)', 'flolens' ), 'hash' => __( 'Store a salted hash only', 'flolens' ), 'full' => __( 'Store the full address', 'flolens' ) ) as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $S['ip_mode'], $k, false ), esc_html( $l ) );
		}
		echo '</select></div><div class="uf-wz-card"><h3><label for="uf-wz-ret">' . esc_html__( 'Keep data for', 'flolens' ) . '</label></h3>';
		self::help( __( 'Deletes visitor records older than this number of days. 0 keeps everything.', 'flolens' ), __( 'Keeping less data lowers privacy risk and database size.', 'flolens' ), __( 'A daily background job removes old rows in small batches; it will not slow visitors down. Older numbers disappear from reports.', 'flolens' ) );
		printf( '<input id="uf-wz-ret" type="number" min="0" max="3650" name="%s" value="%d" data-summary="%s"> %s', esc_attr( self::n( 'retention_days' ) ), (int) $S['retention_days'], esc_attr__( 'Keep data for (days)', 'flolens' ), esc_html__( 'days (0 = forever)', 'flolens' ) );
		echo '</div><div class="uf-wz-card"><h3>' . esc_html__( 'Privacy signals and accounts', 'flolens' ) . '</h3>';
		self::help( __( 'Respecting browser signals skips tracking for visitors whose browser sends Global Privacy Control or Do Not Track. Linking connects a visitor to their WordPress account when they sign in, and attaches their earlier browsing from the same browser.', 'flolens' ), __( 'The first is good practice. The second shows the whole journey of customers and members; switch it off if you do not want activity linked to accounts.', 'flolens' ), __( 'Fewer visitors are counted when signals are respected. Linking only adds a user reference to rows FloLens already has.', 'flolens' ) );
		foreach ( array( 'respect_gpc' => __( 'Respect Global Privacy Control / Do Not Track', 'flolens' ), 'identity_association' => __( 'Link visitors to their account when they sign in', 'flolens' ), 'identity_retroactive' => __( 'Also attach their earlier browsing from the same browser', 'flolens' ) ) as $k => $l ) {
			printf( '<p><label><input type="checkbox" name="%s" value="1" %s data-summary="%s"> %s</label></p>', esc_attr( self::n( $k ) ), checked( ! empty( $S[ $k ] ), true, false ), esc_attr( $l ), esc_html( $l ) );
		}
		$consent = \UserFlo\Privacy\ConsentDetect::detect();
		if ( '' !== $consent && 'none' === (string) $S['consent_mode'] ) {
			/* translators: %s: name of the cookie consent plugin */
			echo '<p class="description"><strong>' . esc_html( sprintf( __( '%s detected: FloLens will wait for its consent choice. You can change this under Settings → Privacy.', 'flolens' ), \UserFlo\Privacy\ConsentDetect::label( $consent ) ) ) . '</strong></p>';
		}
		echo '<p class="description">' . esc_html__( 'Cookie consent banners are set up under Settings → Privacy. You are responsible for meeting the laws that apply to you.', 'flolens' ) . '</p></div>';
		self::step_close();

		// 6. Features.
		self::step_open( 'features', __( 'Features', 'flolens' ), __( 'Switch on the optional parts you want. Each one lists its cost.', 'flolens' ) );
		echo '<div class="uf-wz-card"><h3>' . esc_html__( 'Geolocation', 'flolens' ) . '</h3>';
		self::help( __( 'Shows roughly where visitors are from (country, optionally city) and enables the Map. Uses a free database file stored on your server, never an outside lookup service.', 'flolens' ), __( 'To see which countries or cities your audience is in. Country is the lower-risk choice for privacy.', 'flolens' ), __( 'Country: a download of a few megabytes. City: a much larger file (over 100 MB, roughly). Both download once in the background, then update monthly; lookups take well under a millisecond. Needs outbound HTTPS to download.db-ip.com.', 'flolens' ) );
		$g = __( 'Geolocation', 'flolens' );
		$geo_opts = array(
				'off'     => array( 'label' => __( 'Off', 'flolens' ), 'desc' => __( 'No locations are recorded.', 'flolens' ), 'group' => $g ),
				'country' => array( 'label' => __( 'Country', 'flolens' ), 'desc' => __( 'Small download, lowest privacy impact.', 'flolens' ), 'group' => $g ),
				'city'    => array( 'label' => __( 'Country and city', 'flolens' ), 'desc' => __( 'Large download, more precise (approximate) locations.', 'flolens' ), 'group' => $g ),
			);
		if ( ! Edition::can( 'map_city' ) ) {
			unset( $geo_opts['city'] ); // City level is part of FloLens Pro.
		}
		self::radios( 'geo_mode', $geo_opts, 'city' === $S['geo_mode'] && ! isset( $geo_opts['city'] ) ? 'country' : $S['geo_mode'] );
		echo '<p class="description">' . esc_html__( 'Uses the free DB-IP Lite database. To use your own MaxMind or other .mmdb file, choose it later under Settings → Geolocation.', 'flolens' ) . '</p></div>';
		if ( Edition::can( 'heatmaps' ) ) {
		echo '<div class="uf-wz-card"><h3>' . esc_html__( 'Heatmaps', 'flolens' ) . '</h3>';
		self::help( __( 'Collects where visitors click and how far they scroll on each page and shows it as a heatmap overlay.', 'flolens' ), __( 'To see which parts of a page get attention and which are ignored.', 'flolens' ), __( 'Adds a script of about 1.5 KB (gzipped) and one small request per page view; stores aggregated click positions, never what people type. Off by default.', 'flolens' ) );
		printf( '<p><label><input type="checkbox" name="%s" value="1" %s data-summary="%s"> %s</label></p></div>', esc_attr( self::n( 'heatmaps_enabled' ) ), checked( ! empty( $S['heatmaps_enabled'] ), true, false ), esc_attr__( 'Heatmaps', 'flolens' ), esc_html__( 'Collect heatmap data', 'flolens' ) );
		}
		echo '<div class="uf-wz-card"><h3>' . esc_html__( 'Dashboard sections', 'flolens' ) . '</h3>';
		self::help( __( 'Choose which sections appear in the FloLens menu. A switched-off section is fully disabled, including its routes.', 'flolens' ), __( 'A smaller menu is easier to use, and unused features need not run.', 'flolens' ), __( 'Very small. Sections only read data that was already collected; turning one off never deletes data.', 'flolens' ) );
		$labels = array(
			'live'         => array( __( 'Live view', 'flolens' ), __( 'Who is on the site right now.', 'flolens' ) ),
			'pages'        => array( __( 'Pages', 'flolens' ), __( 'Per-page history of who visited.', 'flolens' ) ),
			'statistics'   => array( __( 'Statistics', 'flolens' ), __( 'Top pages, buttons, links and journeys.', 'flolens' ) ),
			'reports'      => array( __( 'Reports', 'flolens' ), __( 'Build and export PDF or PNG reports.', 'flolens' ) ),
			'achievements' => array( __( 'Achievements', 'flolens' ), __( 'Milestones for your tracking numbers.', 'flolens' ) ),
			'map'          => array( __( 'Map', 'flolens' ), __( 'Visitor map (needs geolocation).', 'flolens' ) ),
			'shortcodes'   => array( __( 'Front-end shortcodes', 'flolens' ), __( 'Show statistics on public pages.', 'flolens' ) ),
			'sharing'      => array( __( 'Sharing and data feeds', 'flolens' ), __( 'Expiring share links, CSV/Excel feeds.', 'flolens' ) ),
			'integrations' => array( __( 'Integrations', 'flolens' ), __( 'GA4, Clarity and PostHog.', 'flolens' ) ),
		);
		$mods = (array) $S['modules'];
		echo '<ul class="uf-wz-mods">';
		foreach ( $labels as $k => $l ) {
			if ( isset( Modules::PRO[ $k ] ) && ! Edition::can( Modules::PRO[ $k ] ) ) {
				continue;
			}
			printf( '<li><label><input type="checkbox" name="%s" value="1" %s data-summary="%s"> <strong>%s</strong> <span class="description">%s</span></label></li>', esc_attr( self::n( 'modules][' . $k ) ), checked( ! isset( $mods[ $k ] ) || ! empty( $mods[ $k ] ), true, false ), esc_attr( $l[0] ), esc_html( $l[0] ), esc_html( $l[1] ) );
		}
		echo '</ul></div>';
		self::step_close();

		if ( Edition::can( 'integrations' ) ) {
		// 7. Integrations.
		self::step_open( 'integrations', __( 'Integrations', 'flolens' ), __( 'Optionally bring numbers from Google Analytics 4, Microsoft Clarity or PostHog into FloLens.', 'flolens' ) );
		self::help( __( 'Reads summary numbers from those services (and can reuse the settings of plugins you already run, such as Google Site Kit).', 'flolens' ), __( 'To see your FloLens and analytics numbers side by side. Completely optional.', 'flolens' ), __( 'Nothing is sent about your visitors. Imports are small scheduled requests to the service you connect; no extra tracking tags are added to your pages.', 'flolens' ) );
		$found = \UserFlo\Integrations\Detect::all();
		if ( $found ) {
			echo '<div class="uf-wz-card"><h3>' . esc_html__( 'Found on this site', 'flolens' ) . '</h3><ul class="uf-wz-mods">';
			foreach ( $found as $d ) {
				$ready = ! empty( $d['ready'] );
				printf(
					'<li><label><input type="checkbox" name="%1$s" value="1" %2$s data-summary="%3$s"> <strong>%3$s</strong> <span class="description">%4$s</span></label></li>',
					esc_attr( self::n( 'integrations][' . $d['id'] ) ),
					$ready ? '' : 'disabled',
					esc_attr( $d['name'] ),
					esc_html( $ready ? __( 'Reuse its settings (no second tag is added).', 'flolens' ) : __( 'Detected, but not fully set up yet.', 'flolens' ) )
				);
			}
			echo '</ul></div>';
		} else {
			echo '<p>' . esc_html__( 'No Google Site Kit, Clarity or PostHog plugin was found. You can connect a service by hand later under FloLens → Integrations.', 'flolens' ) . '</p>';
		}
		echo '<p class="description">' . esc_html__( 'Connecting with your own keys is done on the Integrations page after setup.', 'flolens' ) . '</p>';
		self::step_close();
		}

		// 8. Review.
		self::step_open( 'finish', __( 'Review and finish', 'flolens' ), __( 'Check your choices. Nothing has been saved yet.', 'flolens' ) );
		echo '<table class="widefat striped uf-wz-summary" id="uf-wz-summary"><tbody></tbody></table>';
		echo '<p class="description">' . esc_html__( 'Geolocation databases download in the background after you finish. You can change everything later under FloLens → Settings, and run this wizard again from there.', 'flolens' ) . '</p>';
		self::step_close();

		echo '<div class="uf-wz-nav"><button type="button" class="button" id="uf-wz-back">' . esc_html__( 'Back', 'flolens' ) . '</button> <button type="button" class="button button-primary" id="uf-wz-next">' . esc_html__( 'Next', 'flolens' ) . '</button> <button type="submit" class="button button-primary" id="uf-wz-finish" hidden>' . esc_html__( 'Finish setup', 'flolens' ) . '</button> <a class="uf-wz-skip" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=userflo_setup_skip' ), 'userflo_setup_skip' ) ) . '">' . esc_html__( 'Skip the wizard', 'flolens' ) . '</a></div>';
		echo '</form></div>';
		Ui::close();
	}
}
