<?php
namespace UserFlo\Admin;

use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {

	public static function register() {
		register_setting(
			'userflo_settings_group',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	public static function name( $key ) {
		return Settings::OPTION . '[' . $key . ']';
	}

	private static function check( $key, $label, $help = '' ) {
		printf( '<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="%2$s" value="1" %3$s> %4$s</label></td></tr>', esc_html( $label ), esc_attr( self::name( $key ) ), checked( (bool) Settings::get( $key ), true, false ), esc_html( '' !== $help ? $help : $label ) );
	}

	private static function tab_tracking() {
		echo '<table class="form-table" role="presentation">';
		$stype = Settings::get( 'site_type' );
		$descs = \UserFlo\Focus\Profiles::descriptions();
		echo '<tr><th scope="row"><label for="uf-sitetype">' . esc_html__( 'Site type', 'flolens' ) . '</label></th><td><select id="uf-sitetype" name="' . esc_attr( self::name( 'site_type' ) ) . '">';
		foreach ( \UserFlo\Focus\Profiles::types() as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $stype, $k, false ), esc_html( $l ) );
		}
		echo '</select><p class="description">' . esc_html__( 'What kind of site is this? The Overview and reports then add a focus section with the pages and funnel that matter for it (for example basket and checkout for a store, event pages and outbound ticket links for an events site).', 'flolens' ) . '</p><ul class="uf-int-limits">';
		foreach ( $descs as $k => $d ) {
			if ( 'general' !== $k ) {
				echo '<li><strong>' . esc_html( \UserFlo\Focus\Profiles::types()[ $k ] ) . ':</strong> ' . esc_html( $d ) . '</li>';
			}
		}
		echo '</ul></td></tr>';
		self::check( 'enabled', __( 'Enable tracking', 'flolens' ), __( 'Load the tracker and accept events.', 'flolens' ) );
		self::check( 'track_clicks', __( 'Track clicks', 'flolens' ), __( 'Links, buttons, downloads and outbound links (never input values).', 'flolens' ) );
		self::check( 'filter_bots', __( 'Ignore known bots', 'flolens' ) );
		self::check( 'bot_counter', __( 'Count visits by AI crawlers and other bots', 'flolens' ), __( 'Adds daily counters of how often AI crawlers (such as GPTBot or ClaudeBot), search engines and other bots requested each page, shown next to your human visitors. Only the bot’s name, the page and a count are kept: no IP address and no full user agent. Bots are recognised by the name they give themselves, which can be faked, and pages served from a full-page cache may not be counted.', 'flolens' ) );
		printf( '<tr><th scope="row"><label for="uf-aib">%s</label></th><td><textarea id="uf-aib" name="%s" rows="3" cols="40" class="code" placeholder="SomeNewBot = Some AI">%s</textarea><p class="description">%s</p></td></tr>', esc_html__( 'More AI crawlers to recognise', 'flolens' ), esc_attr( self::name( 'ai_extra_bots' ) ), esc_textarea( Settings::get( 'ai_extra_bots' ) ), esc_html__( 'One per line: a word from the crawler’s user agent, then = and the assistant it belongs to. For example DeepSeekBot = DeepSeek. Use the name the vendor documents.', 'flolens' ) );
		printf( '<tr><th scope="row"><label for="uf-ais">%s</label></th><td><textarea id="uf-ais" name="%s" rows="3" cols="40" class="code" placeholder="chat.example.com = Some AI">%s</textarea><p class="description">%s</p></td></tr>', esc_html__( 'More AI assistants that send visitors', 'flolens' ), esc_attr( self::name( 'ai_extra_sources' ) ), esc_textarea( Settings::get( 'ai_extra_sources' ) ), esc_html__( 'One per line: the assistant’s web address (domain only), then = and its name. These are added to the built-in list used by “Visitors sent by AI assistants”.', 'flolens' ) );
		echo '<tr><th scope="row">' . esc_html__( 'Do not track these roles', 'flolens' ) . '</th><td>';
		$excluded = (array) Settings::get( 'exclude_roles' );
		foreach ( wp_roles()->get_names() as $role => $label ) {
			printf( '<label style="margin-right:12px"><input type="checkbox" name="%s[]" value="%s" %s> %s</label>', esc_attr( self::name( 'exclude_roles' ) ), esc_attr( $role ), checked( in_array( $role, $excluded, true ), true, false ), esc_html( translate_user_role( $label ) ) );
		}
		echo '<p class="description">' . esc_html__( 'Tip: leave empty while testing so your own admin visits are recorded.', 'flolens' ) . '</p></td></tr>';
		printf( '<tr><th scope="row"><label for="uf-paths">%s</label></th><td><textarea id="uf-paths" name="%s" rows="4" cols="40" class="code">%s</textarea><p class="description">%s</p></td></tr>', esc_html__( 'Excluded paths', 'flolens' ), esc_attr( self::name( 'exclude_paths' ) ), esc_textarea( Settings::get( 'exclude_paths' ) ), esc_html__( 'One path prefix per line, e.g. /cart', 'flolens' ) );
		printf( '<tr><th scope="row"><label for="uf-inact">%s</label></th><td><input id="uf-inact" type="number" min="5" max="240" name="%s" value="%d"> %s</td></tr>', esc_html__( 'Session inactivity timeout', 'flolens' ), esc_attr( self::name( 'inactivity_minutes' ) ), (int) Settings::get( 'inactivity_minutes' ), esc_html__( 'minutes', 'flolens' ) );
		// Choosing the scroll-depth steps is Pro. Lite records them at the saved steps (25, 50, 75 and 100 %) without a control; a value
		// someone set earlier is shown read-only because it is still applied.
		if ( \UserFlo\Edition::can( 'advanced_tracking' ) ) {
			printf( '<tr><th scope="row"><label for="uf-scroll">%s</label></th><td><input id="uf-scroll" type="text" name="%s" value="%s" class="regular-text"><p class="description">%s</p></td></tr>', esc_html__( 'Scroll depth thresholds (%)', 'flolens' ), esc_attr( self::name( 'scroll_thresholds' ) ), esc_attr( Settings::get( 'scroll_thresholds' ) ), esc_html__( 'Comma separated, 1–100.', 'flolens' ) );
		} elseif ( (string) Settings::get( 'scroll_thresholds' ) !== (string) Settings::defaults()['scroll_thresholds'] ) {
			printf( '<tr><th scope="row"><label for="uf-scroll">%s</label></th><td><input id="uf-scroll" type="text" value="%s" class="regular-text" readonly><p class="description">%s</p></td></tr>', esc_html__( 'Scroll depth thresholds (%)', 'flolens' ), esc_attr( Settings::get( 'scroll_thresholds' ) ), esc_html__( 'Saved before the free and Pro plugins were separated and still applied. Changing the steps is part of FloLens Pro.', 'flolens' ) );
		}
		printf( '<tr><th scope="row"><label for="uf-dl">%s</label></th><td><input id="uf-dl" type="text" name="%s" value="%s" class="large-text"></td></tr>', esc_html__( 'Download file extensions', 'flolens' ), esc_attr( self::name( 'download_extensions' ) ), esc_attr( Settings::get( 'download_extensions' ) ) );
		$is = (string) Settings::get( 'include_selectors' );
		if ( ! \UserFlo\Edition::can( 'advanced_tracking' ) ) {
			// Selector rules are a Pro feature. A value saved before the split keeps working and is shown read-only; otherwise no control is shown.
			if ( '' !== $is ) {
				printf( '<tr><th scope="row"><label for="uf-is">%s</label></th><td><textarea id="uf-is" rows="2" cols="40" class="code" readonly>%s</textarea><p class="description">%s</p></td></tr>', esc_html__( 'Also track clicks on (CSS selectors)', 'flolens' ), esc_textarea( $is ), esc_html__( 'Saved before the free and Pro plugins were separated and still applied. Changing selector rules and adding labels is part of FloLens Pro.', 'flolens' ) );
			}
		} else {
		printf( '<tr><th scope="row"><label for="uf-is">%s</label></th><td><textarea id="uf-is" name="%s" rows="2" cols="40" class="code">%s</textarea><p class="description">%s</p></td></tr>', esc_html__( 'Also track clicks on (CSS selectors)', 'flolens' ), esc_attr( self::name( 'include_selectors' ) ), esc_textarea( Settings::get( 'include_selectors' ) ), esc_html__( 'Optional, comma separated, e.g. .my-card, [data-cta]. Links, buttons and ARIA controls are tracked automatically.', 'flolens' ) );
		}
		printf( '<tr><th scope="row"><label for="uf-xs">%s</label></th><td><textarea id="uf-xs" name="%s" rows="2" cols="40" class="code">%s</textarea><p class="description">%s</p></td></tr>', esc_html__( 'Never track clicks inside (CSS selectors)', 'flolens' ), esc_attr( self::name( 'exclude_selectors' ) ), esc_textarea( Settings::get( 'exclude_selectors' ) ), esc_html__( 'Also: add the attribute data-userflo-ignore to any element to exclude it.', 'flolens' ) );
		printf( '<tr><th scope="row">%s</th><td><a class="button" href="%s">%s</a><p class="description">%s</p></td></tr>', esc_html__( 'More exclusions', 'flolens' ), esc_url( admin_url( 'admin.php?page=userflo-exclusions' ) ), esc_html__( 'Manage exclusions', 'flolens' ), esc_html__( 'Stop tracking or leave out of statistics a single page, link, button or form, with a reason, a preview of the effect and the option to pause a rule. These settings keep working and are listed there too.', 'flolens' ) );
		echo '</table>';
	}

	private static function tab_privacy() {
		$tm = Settings::get( 'tracking_mode' );
		echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="uf-tm">' . esc_html__( 'Tracking mode', 'flolens' ) . '</label></th><td><select id="uf-tm" name="' . esc_attr( self::name( 'tracking_mode' ) ) . '">';
		printf( '<option value="standard"%s>%s</option>', selected( $tm, 'standard', false ), esc_html__( 'Standard: two first-party cookies, returning visitors are recognised', 'flolens' ) );
		printf( '<option value="cookieless"%s>%s</option>', selected( $tm, 'cookieless', false ), esc_html__( 'Session-only: no cookies, no returning-visitor recognition, no IP address stored', 'flolens' ) );
		echo '</select><p class="description">' . esc_html__( 'Session-only mode writes and reads no cookie. A visit is kept together only inside one browser tab (an identifier in that tab’s session storage, gone when the tab closes), so a returning visitor counts as a new one, visitors and sessions are the same thing, journeys end with the tab, no IP address is stored, visits are never linked to a user account, locations are country only, and WooCommerce orders cannot be tied to a visit. Page views, referrers, campaigns, events and goals per session keep working. Your consent setting still applies unchanged, and this mode does not make a site compliant with any law by itself: whether you need consent depends on your country and your other tools (the optional Google Analytics, Clarity and PostHog integrations set their own cookies). See the Privacy guide.', 'flolens' ) . '</p></td></tr>';
		$ip = Settings::get( 'ip_mode' );
		echo '<tr><th scope="row"><label for="uf-ip">' . esc_html__( 'IP address handling', 'flolens' ) . '</label></th><td><select id="uf-ip" name="' . esc_attr( self::name( 'ip_mode' ) ) . '">';
		foreach ( array( 'off' => __( 'Do not store', 'flolens' ), 'truncate' => __( 'Store anonymised (recommended: last IPv4 octet / IPv6 interface bits removed)', 'flolens' ), 'hash' => __( 'Store salted hash only', 'flolens' ), 'full' => __( 'Store full IP', 'flolens' ) ) as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $ip, $k, false ), esc_html( $l ) );
		}
		echo '</select><p class="description">' . esc_html__( 'IP addresses are never sent to an external service. Geolocation is optional and is set up under Settings → Geolocation.', 'flolens' ) . '</p></td></tr>';
		$ih = Settings::get( 'ip_header' );
		echo '<tr><th scope="row"><label for="uf-ih">' . esc_html__( 'Visitor IP source', 'flolens' ) . '</label></th><td><select id="uf-ih" name="' . esc_attr( self::name( 'ip_header' ) ) . '">';
		foreach ( array( 'remote_addr' => __( 'Server connection (REMOTE_ADDR) — default', 'flolens' ), 'x_forwarded_for' => 'X-Forwarded-For', 'cf_connecting_ip' => 'CF-Connecting-IP (Cloudflare)', 'x_real_ip' => 'X-Real-IP' ) as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $ih, $k, false ), esc_html( $l ) );
		}
		echo '</select><p class="description">' . esc_html__( 'Used for abuse limits and (if enabled) stored IPs. Behind a CDN or reverse proxy every visitor otherwise looks like the proxy. Only pick a header that your proxy always sets, otherwise it can be spoofed.', 'flolens' ) . '</p></td></tr>';
		$cm = Settings::get( 'consent_mode' );
		echo '<tr><th scope="row"><label for="uf-cm">' . esc_html__( 'Consent', 'flolens' ) . '</label></th><td><select id="uf-cm" name="' . esc_attr( self::name( 'consent_mode' ) ) . '">';
		foreach ( array( 'none' => __( 'No consent required (tracker starts immediately)', 'flolens' ), 'cookie' => __( 'Wait for a consent cookie (set below)', 'flolens' ), 'js' => __( 'Wait for JavaScript call: FloLens.consent(true)', 'flolens' ), 'complianz' => __( 'Complianz: wait for statistics consent (preset, not tested with the plugin)', 'flolens' ), 'cookieyes' => __( 'CookieYes: wait for analytics consent (preset, not tested with the plugin)', 'flolens' ), 'cookiebot' => __( 'Cookiebot: wait for statistics consent (preset, not tested with the plugin)', 'flolens' ), 'borlabs' => __( 'Borlabs Cookie: wait for statistics consent (preset, not tested with the plugin)', 'flolens' ) ) as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $cm, $k, false ), esc_html( $l ) );
		}
		echo '</select><p class="description">' . esc_html__( 'Until consent is granted the tracker sets no cookies and sends nothing. You are responsible for configuring this to meet the laws that apply to you.', 'flolens' ) . '</p></td></tr>';
		printf( '<tr><th scope="row"><label for="uf-cc">%s</label></th><td><input id="uf-cc" type="text" name="%s" value="%s" class="regular-text"><p class="description">%s</p></td></tr>', esc_html__( 'Consent cookie name', 'flolens' ), esc_attr( self::name( 'consent_cookie' ) ), esc_attr( Settings::get( 'consent_cookie' ) ), esc_html__( 'Any non-empty value other than 0/no/false/denied counts as consent.', 'flolens' ) );
		self::check( 'respect_gpc', __( 'Respect browser privacy signals', 'flolens' ), __( 'Do not track when Global Privacy Control or Do Not Track is on.', 'flolens' ) );
		self::check( 'identity_association', __( 'Link visitors to WordPress accounts', 'flolens' ), __( 'Associate a browser with a user after login. Never merges people by IP.', 'flolens' ) );
		self::check( 'identity_retroactive', __( 'Attach earlier browsing to the account', 'flolens' ), __( 'When a visitor signs in, registers or checks out, their earlier anonymous sessions from the same browser are added to that account’s history (and later visits from that browser too). Based only on FloLens’s own browser cookie, never on IP address. Not applied to a second account on a shared browser.', 'flolens' ) );
		self::check( 'share_credit', __( 'Show “Powered by FloLens” on shared pages', 'flolens' ), __( 'Off by default. When on, the read-only pages you share by link carry a small credit with the FloLens icon. Nothing is added to your public website.', 'flolens' ) );
		self::check( 'history_bar', __( 'Show the FloLens menu in the WordPress toolbar', 'flolens' ), __( 'Adds a FloLens menu to the toolbar on your site’s pages, for people who can see the analytics: quick links to the screens and the latest visits and interactions. Needs the Live view component. Choose below which parts it shows.', 'flolens' ) );
		self::check( 'bar_links', __( 'Toolbar menu: quick links', 'flolens' ), __( 'Live view, Map, Latest visitors and the links for the page you are looking at (its details in Pages and Elements). With Pro it also shows the page audit dot next to the name and a Page audit link.', 'flolens' ) );
		self::check( 'bar_activity', __( 'Toolbar menu: latest activity', 'flolens' ), __( 'The last ten visits and interactions of the last 7 days, with tabs for all pages or this page. If you untick both parts, the menu is not shown.', 'flolens' ) );
		$ul = Settings::get( 'user_link' );
		printf( '<tr><th scope="row"><label for="uf-userlink">%s</label></th><td><select id="uf-userlink" name="%s"><option value="new"%s>%s</option><option value="same"%s>%s</option><option value="off"%s>%s</option></select><p class="description">%s</p></td></tr>', esc_html__( 'Button to open a logged-in visitor’s WordPress profile', 'flolens' ), esc_attr( self::name( 'user_link' ) ), selected( $ul, 'new', false ), esc_html__( 'Show it, open in a new tab', 'flolens' ), selected( $ul, 'same', false ), esc_html__( 'Show it, open in the same tab', 'flolens' ), selected( $ul, 'off', false ), esc_html__( 'Hide it', 'flolens' ), esc_html__( 'Shown on a logged-in visitor’s page, only to people who may edit that user, and only when usernames are shown in the dashboard.', 'flolens' ) );
		self::check( 'show_identity', __( 'Show usernames and emails in the dashboard', 'flolens' ), __( 'Names are read from the WordPress user table at display time and are not copied into FloLens.', 'flolens' ) );
		echo '</table><h3>' . esc_html__( 'Data retention', 'flolens' ) . '</h3><table class="form-table" role="presentation">';
		printf( '<tr><th scope="row"><label for="uf-ret">%s</label></th><td><input id="uf-ret" type="number" min="0" max="3650" name="%s" value="%d"> %s</td></tr>', esc_html__( 'Delete data older than', 'flolens' ), esc_attr( self::name( 'retention_days' ) ), (int) Settings::get( 'retention_days' ), esc_html__( 'days (0 = keep forever). Runs daily in small batches.', 'flolens' ) );
		printf( '<tr><th scope="row"><label for="uf-shortev">%s</label></th><td><input id="uf-shortev" type="number" min="0" max="3650" name="%s" value="%d"> %s <a href="%s">%s</a></td></tr>', esc_html__( 'Keep clicks and scroll events for', 'flolens' ), esc_attr( self::name( 'short_event_days' ) ), (int) Settings::get( 'short_event_days' ), esc_html__( 'days (0 = as long as everything else). These are the most numerous rows; their daily totals stay in the summaries.', 'flolens' ), esc_url( \UserFlo\Admin\Ui::url( 'userflo-tools', array( 'tab' => 'storage' ) ) ), esc_html__( 'Database & Storage: sizes, profiles and manual cleanup', 'flolens' ) );
		printf( '<tr><th scope="row"><label for="uf-sumy">%s</label></th><td><input id="uf-sumy" type="number" min="0" max="10" name="%s" value="%d"> %s</td></tr>', esc_html__( 'Keep daily summaries for', 'flolens' ), esc_attr( self::name( 'summary_years' ) ), (int) Settings::get( 'summary_years' ), esc_html__( 'years (0 = do not keep). When the detailed data above is deleted, one small row of counts per day stays: totals, and the top pages, sources, countries and devices. No visitor, session or address is kept, so it is not personal data. It lets you compare a period with the same period last year.', 'flolens' ) );
		if ( \UserFlo\Edition::can( 'heatmaps' ) || \UserFlo\Database\Tables::has_heat_data() ) { // Stored heatmap counters keep their retention control even without Pro.
			printf( '<tr><th scope="row"><label for="uf-hmret">%s</label></th><td><input id="uf-hmret" type="number" min="1" max="3650" name="%s" value="%d"> %s</td></tr>', esc_html__( 'Delete heatmap data older than', 'flolens' ), esc_attr( self::name( 'heatmap_retention_days' ) ), (int) Settings::get( 'heatmap_retention_days' ), esc_html__( 'days. Heatmaps store only anonymous click counters.', 'flolens' ) );
		}
		self::check( 'delete_on_uninstall', __( 'Delete all FloLens data when the plugin is uninstalled', 'flolens' ), __( 'Off by default: data is kept if you delete the plugin.', 'flolens' ) );
		echo '</table>';
	}

	private static function tab_components() {
		$meta   = \UserFlo\Modules::meta();
		$labels = \UserFlo\Modules::all_labels();
		$groups = \UserFlo\Modules::groups();
		$cost   = array( 'low' => __( 'Low', 'flolens' ), 'medium' => __( 'Medium', 'flolens' ), 'high' => __( 'Higher', 'flolens' ) );
		echo '<p class="description">' . esc_html__( 'Each section has three separate switches. Enabled decides whether it runs at all: a section that is off collects and processes nothing, and its recorded data is kept. Show in sidebar only controls the menu: a hidden section keeps running. Include in reports decides whether scheduled reports contain its data, whatever the menu shows. Overview, Visitors, Settings and Tools are always available.', 'flolens' ) . '</p>';
		echo '<input type="hidden" name="' . esc_attr( self::name( 'modules_nav' ) ) . '[__form]" value="1"><input type="hidden" name="' . esc_attr( self::name( 'modules_report' ) ) . '[__form]" value="1">';
		foreach ( $groups as $gid => $glabel ) {
			$rows = array_filter( $meta, static function ( $m ) use ( $gid ) {
				return $m['group'] === $gid;
			} );
			if ( ! $rows ) {
				continue;
			}
			echo '<h3>' . esc_html( $glabel ) . '</h3><table class="widefat striped uf-modtable"><thead><tr><th scope="col">' . esc_html__( 'Section', 'flolens' ) . '</th><th scope="col">' . esc_html__( 'Enabled', 'flolens' ) . '</th><th scope="col">' . esc_html__( 'Show in sidebar', 'flolens' ) . '</th><th scope="col">' . esc_html__( 'Include in reports', 'flolens' ) . '</th><th scope="col">' . esc_html__( 'Cost while on', 'flolens' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $key => $m ) {
				$locked = isset( \UserFlo\Modules::PRO[ $key ] ) && ! \UserFlo\Edition::can( \UserFlo\Modules::PRO[ $key ] );
				$on     = \UserFlo\Modules::switched_on( $key );
				$notes  = array();
				foreach ( isset( \UserFlo\Modules::REQUIRES[ $key ] ) ? \UserFlo\Modules::REQUIRES[ $key ] : array() as $need ) {
					/* translators: %s: name of another section */
					$notes[] = sprintf( __( 'Needs %s to be enabled.', 'flolens' ), $labels[ $need ] );
				}
				$deps = array_map( static function ( $d ) use ( $labels ) {
					return $labels[ $d ];
				}, \UserFlo\Modules::dependants( $key ) );
				if ( $deps ) {
					/* translators: %s: list of section names */
					$notes[] = sprintf( __( 'Turning this off also stops: %s.', 'flolens' ), implode( ', ', $deps ) );
				}
				if ( isset( \UserFlo\Modules::USES[ $key ] ) ) {
					/* translators: %s: list of section names */
					$notes[] = sprintf( __( 'Uses the data of %s, which stay available to it even if those sections are off.', 'flolens' ), implode( ', ', array_map( static function ( $d ) use ( $labels ) {
						return $labels[ $d ];
					}, \UserFlo\Modules::USES[ $key ] ) ) );
				}
				$needs  = 'woocommerce' === $key && ! \UserFlo\Commerce\Source::active();
				$state = $needs ? __( 'Needs WooCommerce', 'flolens' ) : ( $locked ? __( 'Pro', 'flolens' ) : ( $on ? ( \UserFlo\Modules::on( $key ) ? __( 'On', 'flolens' ) : __( 'Waiting', 'flolens' ) ) : __( 'Off', 'flolens' ) ) );
				$id    = 'uf-mod-' . $key;
				echo '<tr><th scope="row"><strong id="' . esc_attr( $id ) . '-n">' . esc_html( $labels[ $key ] ) . '</strong> <span class="uf-chip-state">' . esc_html( $state ) . '</span><p class="description" id="' . esc_attr( $id ) . '-d">' . esc_html( $m['desc'] . ( $notes ? ' ' . implode( ' ', $notes ) : '' ) ) . '</p></th>';
				$dis  = ( $locked || $needs ) ? ' disabled' : '';
				$name = 'heatmaps' === $key ? self::name( 'heatmaps_enabled' ) : self::name( 'modules' ) . '[' . $key . ']';
				echo '<td><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" aria-labelledby="' . esc_attr( $id ) . '-n" aria-describedby="' . esc_attr( $id ) . '-d" ' . checked( $on, true, false ) . $dis . '></td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- $dis is a literal.
				if ( $m['menu'] ) {
					echo '<td><input type="checkbox" aria-label="' . esc_attr( sprintf( /* translators: %s: section name */ __( 'Show %s in the sidebar', 'flolens' ), $labels[ $key ] ) ) . '" name="' . esc_attr( self::name( 'modules_nav' ) ) . '[' . esc_attr( $key ) . ']" value="1" ' . checked( \UserFlo\Modules::nav( $key ), true, false ) . $dis . '></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
				} else {
					echo '<td><span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'No sidebar entry', 'flolens' ) . '</span></td>';
				}
				if ( $m['report'] ) {
					echo '<td><input type="checkbox" aria-label="' . esc_attr( sprintf( /* translators: %s: section name */ __( 'Include %s in reports', 'flolens' ), $labels[ $key ] ) ) . '" name="' . esc_attr( self::name( 'modules_report' ) ) . '[' . esc_attr( $key ) . ']" value="1" ' . checked( \UserFlo\Modules::in_reports( $key ), true, false ) . $dis . '></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
				} else {
					echo '<td><span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Not part of reports', 'flolens' ) . '</span></td>';
				}
				echo '<td>' . esc_html( $cost[ $m['cost'] ] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		if ( \UserFlo\Commerce\Source::active() ) {
			$pro     = \UserFlo\Edition::can( 'commerce_attribution' );
			$counted = \UserFlo\Commerce\Statuses::counted();
			echo '<h3>' . esc_html__( 'WooCommerce sales', 'flolens' ) . '</h3><p class="description">' . esc_html__( 'Orders in these statuses are counted as sales. Revenue is the order total minus refunds, in the order’s own currency. Orders in other statuses are listed separately and never added.', 'flolens' ) . '</p><fieldset><legend class="screen-reader-text">' . esc_html__( 'Order statuses that count as sales', 'flolens' ) . '</legend>';
			foreach ( \UserFlo\Commerce\Statuses::all() as $k => $label ) {
				printf( '<label style="display:inline-block;margin:0 16px 6px 0"><input type="checkbox" name="%1$s[woo_statuses][]" value="%2$s" %3$s%4$s> %5$s</label>', esc_attr( Settings::OPTION ), esc_attr( $k ), checked( in_array( $k, $counted, true ), true, false ), $pro ? '' : ' disabled', esc_html( $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed literals.
			}
			echo '</fieldset>';
			if ( ! $pro ) {
				echo '<p class="description">' . esc_html__( 'The free version counts Processing and Completed orders. Choosing other statuses is part of Pro.', 'flolens' ) . '</p>';
			}
		}
	}

	private static function tab_geolocation() {
		echo '<table class="form-table" role="presentation">';
		$gm = Settings::get( 'geo_mode' );
		echo '<tr><th scope="row"><label for="uf-geo">' . esc_html__( 'Visitor location', 'flolens' ) . '</label></th><td><select id="uf-geo" name="' . esc_attr( self::name( 'geo_mode' ) ) . '">';
		$modes = array( 'off' => __( 'Off', 'flolens' ), 'country' => __( 'Country only', 'flolens' ) );
		if ( \UserFlo\Edition::can( 'map_city' ) || 'city' === $gm ) {
			$modes['city'] = __( 'Country and city', 'flolens' ) . ( \UserFlo\Edition::can( 'map_city' ) ? '' : ' — ' . __( 'FloLens Pro', 'flolens' ) );
		}
		foreach ( $modes as $k => $l ) {
			printf( '<option value="%s"%s%s>%s</option>', esc_attr( $k ), selected( $gm, $k, false ), '', esc_html( $l ) );
		}
		echo '</select><p class="description">' . ( \UserFlo\Edition::can( 'map_city' ) || 'city' === $gm
			? esc_html__( 'Looks up the visitor\'s IP address once per session in a local database and stores only the result (country, optional city and approximate coordinates). The IP itself is not stored unless you allow that above. Location data is personal data in many laws: mention it in your privacy notice; country-only is the lower-risk option.', 'flolens' )
			: esc_html__( 'Looks up the visitor\'s IP address once per session in a local database and stores only the result (the country). The IP itself is not stored unless you allow that above. Location data is personal data in many laws: mention it in your privacy notice.', 'flolens' ) ) . '</p></td></tr>';
		$gs = Settings::get( 'geo_source' );
		echo '<tr><th scope="row"><label for="uf-geosrc">' . esc_html__( 'Database', 'flolens' ) . '</label></th><td><select id="uf-geosrc" name="' . esc_attr( self::name( 'geo_source' ) ) . '">';
		foreach ( array( 'dbip' => __( 'DB-IP Lite (free, downloaded automatically)', 'flolens' ), 'custom' => __( 'My own .mmdb file (MaxMind GeoLite2, DB-IP, …)', 'flolens' ) ) as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $gs, $k, false ), esc_html( $l ) );
		}
		echo '</select></td></tr>';
		printf( '<tr><th scope="row"><label for="uf-geopath">%s</label></th><td><input id="uf-geopath" type="text" class="large-text code" name="%s" value="%s"><p class="description">%s</p></td></tr>', esc_html__( 'Custom file path', 'flolens' ), esc_attr( self::name( 'geo_custom_path' ) ), esc_attr( Settings::get( 'geo_custom_path' ) ), esc_html__( 'Absolute server path to a City or Country database in MMDB format. Used only with "My own .mmdb file".', 'flolens' ) );
		self::check( 'geo_auto_update', __( 'Update the free database automatically', 'flolens' ), __( 'Checks daily and downloads a new file when the new month is published (background task, never during a visitor request).', 'flolens' ) );
		$st = \UserFlo\Geo\Database::status();
		echo '<tr><th scope="row">' . esc_html__( 'Database status', 'flolens' ) . '</th><td>';
		if ( 'off' === $gm ) {
			echo esc_html__( 'Geolocation is off.', 'flolens' );
		} elseif ( \UserFlo\Geo\Locator::database_path() ) {
			echo '<span class="uf-badge uf-badge-live">' . esc_html__( 'Ready', 'flolens' ) . '</span> ' . esc_html( ( 'custom' === $gs ? (string) Settings::get( 'geo_custom_path' ) : basename( $st['file'] ) ) . ( $st['size'] && 'custom' !== $gs ? ' · ' . size_format( $st['size'] ) . ( $st['built'] ? ' · ' . $st['built'] : '' ) : '' ) );
		} else {
			echo '<span class="uf-badge uf-badge-est">' . esc_html__( 'No database yet', 'flolens' ) . '</span> ' . esc_html__( 'Locations stay empty until a database is installed.', 'flolens' );
		}
		if ( $st['error'] ) {
			echo '<p class="uf-err">' . esc_html( $st['error'] ) . '</p>';
		}
		$uf_geo = isset( $_GET['uf_geo'] ) ? sanitize_key( wp_unslash( $_GET['uf_geo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'done' === $uf_geo ) {
			echo '<p>' . esc_html__( 'The database was downloaded and installed.', 'flolens' ) . '</p>';
		} elseif ( 'failed' === $uf_geo ) {
			echo '<p class="uf-err">' . esc_html__( 'The download did not work. The reason is shown above.', 'flolens' ) . '</p>';
		} elseif ( '' !== $uf_geo ) {
			echo '<p>' . esc_html__( 'Download queued: it runs in the background (the city database is large, up to several minutes).', 'flolens' ) . '</p>';
		}
		echo '<p class="description">' . wp_kses_post( __( 'Free data by <a href="https://db-ip.com" target="_blank" rel="noopener">DB-IP.com</a> (CC BY 4.0). Downloads need outbound HTTPS to download.db-ip.com.', 'flolens' ) ) . '</p>';
		echo '</td></tr>';
		echo '</table>';
		if ( 'off' !== $gm && 'dbip' === $gs ) {
			echo '<p>' . '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=userflo_geo_download' ), 'userflo_geo_download' ) ) . '">' . esc_html__( 'Download / update the database now', 'flolens' ) . '</a> <span class="description">' . esc_html__( 'Save your settings first.', 'flolens' ) . '</span></p>';
		}
		echo '<table class="form-table" role="presentation">';
		echo '</table>';
	}

	private static function tab_branding() {
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row">' . esc_html__( 'Buttons at the top of each screen', 'flolens' ) . '</th><td><fieldset><legend class="screen-reader-text">' . esc_html__( 'Buttons at the top of each screen', 'flolens' ) . '</legend>';
		$buttons = array( 'show_guide_button' => __( 'Guide', 'flolens' ), 'show_export_button' => __( 'Export CSV', 'flolens' ) );
		if ( \UserFlo\Edition::can( 'ai_access' ) ) {
			$buttons = array( 'show_guide_button' => __( 'Guide', 'flolens' ), 'show_ai_button' => __( 'Copy for AI', 'flolens' ), 'show_export_button' => __( 'Export CSV', 'flolens' ) );
		}
		foreach ( $buttons as $k => $l ) {
			printf( '<label style="display:block"><input type="checkbox" name="%s" value="1" %s> %s</label>', esc_attr( self::name( $k ) ), checked( (bool) Settings::get( $k ), true, false ), esc_html( $l ) );
		}
		echo '<p class="description">' . esc_html__( 'Untick a button to hide it on every FloLens screen. Hidden buttons are only removed from view; nothing else changes.', 'flolens' ) . '</p></fieldset></td></tr>';
		printf(
			'<tr><th scope="row">%s</th><td><label><input type="checkbox" name="%s" value="1" %s> %s</label><p class="description">%s</p></td></tr>',
			esc_html__( 'Animations', 'flolens' ),
			esc_attr( self::name( 'animations' ) ),
			checked( (bool) Settings::get( 'animations' ), true, false ),
			esc_html__( 'Animate charts, tables and the map', 'flolens' ),
			esc_html__( 'Lines draw in from left to right, pie and ring charts sweep round, table rows fade in and a country grows under the pointer on the map. Untick it to show the data instantly. Visitors whose device is set to reduce motion never see the animations, whatever this says.', 'flolens' )
		);
		/** FloLens Pro adds the company logo row here. */
		do_action( 'userflo_settings_branding' );
		if ( ! \UserFlo\Edition::can( 'branding' ) ) {
			echo '<tr><th scope="row">' . esc_html__( 'Company logo', 'flolens' ) . '</th><td><p class="description">' . esc_html__( 'Showing your own logo next to the FloLens mark on screens, shared links and reports is part of FloLens Pro.', 'flolens' ) . '</p></td></tr>';
		}
		if ( \UserFlo\Edition::can( 'map_globe' ) && '' !== \UserFlo\Admin\AppPage::globe_url() ) { // The 3D globe ships with Pro.
			$gs = Settings::get( 'globe_style' );
			echo '<tr><th scope="row"><label for="uf-globe-style">' . esc_html__( '3D globe style', 'flolens' ) . '</label></th><td><select id="uf-globe-style" name="' . esc_attr( self::name( 'globe_style' ) ) . '">';
			foreach ( array( 'hex' => __( 'Hexagons (dotted land, finer as you zoom in)', 'flolens' ), 'vector' => __( 'Vector map (smooth and sharp at any zoom)', 'flolens' ) ) as $k => $l ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $gs, $k, false ), esc_html( $l ) );
			}
			echo '</select><p class="description">' . esc_html__( 'How land is drawn on the Map screen\'s 3D globe. Each person can also switch it on the Map screen; this is the starting choice. Takes effect after saving.', 'flolens' ) . '</p></td></tr>';
		}
		$us = Settings::get( 'ui_style' );
		echo '<tr><th scope="row"><label for="uf-style">' . esc_html__( 'Dashboard style', 'flolens' ) . '</label></th><td><select id="uf-style" name="' . esc_attr( self::name( 'ui_style' ) ) . '">';
		foreach ( array( 'default' => __( 'Default (WordPress)', 'flolens' ), 'dark' => __( 'Dark', 'flolens' ), 'glass' => __( 'Glass light (glassmorphism)', 'flolens' ), 'elegant' => __( 'Elegant (professional)', 'flolens' ), 'fancy' => __( 'Fancy (colourful)', 'flolens' ), 'mono' => __( 'Mono (minimal white)', 'flolens' ), 'noir' => __( 'Noir (minimal black)', 'flolens' ) ) as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $us, $k, false ), esc_html( $l ) );
		}
		echo '</select><p class="description">' . esc_html__( 'Changes how FloLens screens look for everyone on this site. Takes effect after saving.', 'flolens' ) . '</p></td></tr>';
		echo '</table>';
	}

	private static function tab_language() {
		echo '<table class="form-table" role="presentation">';
		$lg = Settings::get( 'language' );
		echo '<tr><th scope="row"><label for="uf-lang">' . esc_html__( 'Plugin language', 'flolens' ) . '</label></th><td><select id="uf-lang" name="' . esc_attr( self::name( 'language' ) ) . '">';
		$names = array( 'auto' => __( 'Automatic (WordPress / user language)', 'flolens' ), 'en_US' => 'English', 'es_ES' => 'Español', 'fr_FR' => 'Français', 'hu_HU' => 'Magyar' );
		foreach ( \UserFlo\Translations\Store::all() as $code => $d ) {
			if ( ! empty( $d['name'] ) ) {
				$names[ $code ] = $d['name'];
			}
		}
		foreach ( \UserFlo\Translations\Store::custom_languages() as $code => $name ) {
			$names[ $code ] = $name;
		}
		foreach ( $names as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $lg, $k, false ), esc_html( $l ) );
		}
		echo '</select><p class="description">' . esc_html__( 'Language of the FloLens screens. Edit or add languages below.', 'flolens' ) . '</p></td></tr>';
		if ( \UserFlo\Edition::can( 'reports' ) ) { // Exported reports (PDF and PNG) are Pro.
			$rl = (string) Settings::get( 'report_language' );
			echo '<tr><th scope="row"><label for="uf-rlang">' . esc_html__( 'Report language', 'flolens' ) . '</label></th><td><select id="uf-rlang" name="' . esc_attr( self::name( 'report_language' ) ) . '"><option value="">' . esc_html__( 'Same as the plugin language', 'flolens' ) . '</option>';
			foreach ( \UserFlo\Translations\Routes::available() as $k => $l ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $rl, $k, false ), esc_html( $l ) );
			}
			echo '</select><p class="description">' . esc_html__( 'Language used in exported reports (PDF and PNG). It can differ from the dashboard language and can be changed for each report. Only languages that are installed or added under Translations are offered.', 'flolens' ) . '</p></td></tr>';
		}
		echo '</table>';
	}

	private static function tabs() {
		$tabs = array(
			'tracking'     => array( __( 'Tracking', 'flolens' ), 'dashicons-chart-line' ),
			'privacy'      => array( __( 'Privacy', 'flolens' ), 'dashicons-privacy' ),
			'components'   => array( __( 'Components', 'flolens' ), 'dashicons-screenoptions' ),
			'geolocation'  => array( __( 'Geolocation', 'flolens' ), 'dashicons-location-alt' ),
			'branding'     => array( __( 'Branding and appearance', 'flolens' ), 'dashicons-art' ),
			'translations' => array( __( 'Translations', 'flolens' ), 'dashicons-translation' ),
			'help'         => array( __( 'Help', 'flolens' ), 'dashicons-editor-help' ),
			'about'        => array( __( 'About', 'flolens' ), 'dashicons-info-outline' ),
		);
		/** Add-ons insert their own tabs (id => [label, dashicon]) and render them on the `userflo_settings_panel_{id}` action. */
		return (array) apply_filters( 'userflo_settings_tabs', CommunityTabs::tabs( $tabs ) );
	}

	private static function panel_open( $id, $title, $in_form = true ) {
		echo '<section class="uf-tabpanel" id="uf-tab-' . esc_attr( $id ) . '" data-panel="' . esc_attr( $id ) . '" data-form="' . ( $in_form ? '1' : '0' ) . '" role="tabpanel" aria-labelledby="uf-tabbtn-' . esc_attr( $id ) . '"><h2 class="uf-panel-title">' . esc_html( $title ) . '</h2>';
	}

	private static function panel_close() {
		echo '</section>';
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'userflo-settings' ) ) {
			return;
		}
		foreach ( array( 'settings', 'translations' ) as $n ) {
			$f = USERFLO_DIR . 'assets/admin/' . $n . '.js';
			wp_enqueue_script( 'userflo-' . $n, USERFLO_URL . 'assets/admin/' . $n . '.js', array( 'wp-api-fetch', 'wp-i18n' ), USERFLO_VERSION . '-' . filemtime( $f ), true );
			\UserFlo\I18n::script( 'userflo-' . $n );
		}
		wp_add_inline_script( 'userflo-translations', 'window.userfloTr=' . wp_json_encode( array( 'export' => admin_url( 'admin-post.php' ), 'nonce' => wp_create_nonce( 'userflo_translations_export' ) ) ) . ';', 'before' );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'flolens' ), 403 );
		}
		Ui::open( __( 'Settings', 'flolens' ) );
		echo '<p class="uf-setup-link"><a class="button" href="' . esc_url( SetupWizard::url() ) . '">' . esc_html__( 'Run the setup wizard again', 'flolens' ) . '</a></p>';
		echo '<div class="uf-settings"><div class="uf-side"><nav class="uf-tabnav" role="tablist" aria-orientation="vertical" aria-label="' . esc_attr__( 'Settings sections', 'flolens' ) . '">';
		foreach ( self::tabs() as $id => $t ) {
			echo '<button type="button" role="tab" class="uf-tabbtn" id="uf-tabbtn-' . esc_attr( $id ) . '" data-tab="' . esc_attr( $id ) . '" aria-controls="uf-tab-' . esc_attr( $id ) . '" aria-selected="false"><span class="dashicons ' . esc_attr( $t[1] ) . '" aria-hidden="true"></span> ' . esc_html( $t[0] ) . '</button>';
		}
		echo '</nav>';
		$sections = Menu::settings_sections();
		if ( $sections ) {
			// Screens that live under Settings (no sidebar entries of their own).
			echo '<nav class="uf-tabnav uf-moresettings" aria-labelledby="uf-more-sections"><p class="uf-tabgroup" id="uf-more-sections">' . esc_html__( 'More in Settings', 'flolens' ) . '</p>';
			foreach ( $sections as $slug => $s ) {
				echo '<a class="uf-tablink" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"><span class="dashicons ' . esc_attr( $s[1] ) . '" aria-hidden="true"></span> ' . esc_html( $s[0] ) . '</a>';
			}
			echo '</nav>';
		}
		echo '</div><div class="uf-settings-main">';
		echo '<form method="post" action="options.php" id="uf-settings-form">';
		settings_fields( 'userflo_settings_group' );
		$tabs = self::tabs();
		foreach ( array( 'tracking', 'privacy', 'components', 'geolocation', 'branding' ) as $id ) {
			self::panel_open( $id, $tabs[ $id ][0] );
			call_user_func( array( __CLASS__, 'tab_' . $id ) );
			self::panel_close();
		}
		self::panel_open( 'translations', $tabs['translations'][0] );
		self::tab_language();
		submit_button( __( 'Save language setting', 'flolens' ), 'secondary', 'submit_language', true );
		self::panel_close();
		echo '<div class="uf-savebar">';
		submit_button( __( 'Save settings', 'flolens' ), 'primary', 'submit', false );
		echo '</div></form>';
		$core = array( 'tracking', 'privacy', 'components', 'geolocation', 'branding', 'translations', 'help', 'about', 'feedback', 'support', 'compare' );
		foreach ( $tabs as $id => $t ) {
			if ( ! in_array( $id, $core, true ) ) {
				self::panel_open( $id, $t[0], false );
				do_action( 'userflo_settings_panel_' . $id );
				self::panel_close();
			}
		}
		self::panel_open( 'translations-editor', __( 'Edit translations', 'flolens' ), false );
		echo '<div id="uf-translations" data-panel-extra="translations"><p>' . esc_html__( 'Loading…', 'flolens' ) . '</p></div>';
		self::panel_close();
		self::panel_open( 'help', $tabs['help'][0], false );
		HelpTab::render();
		self::panel_close();
		if ( isset( $tabs['feedback'] ) ) {
			self::panel_open( 'feedback', $tabs['feedback'][0], false );
			CommunityTabs::feedback();
			self::panel_close();
		}
		self::panel_open( 'support', $tabs['support'][0], false );
		CommunityTabs::support();
		self::panel_close();
		if ( isset( $tabs['compare'] ) ) {
			self::panel_open( 'compare', $tabs['compare'][0], false );
			CommunityTabs::compare();
			self::panel_close();
		}
		self::panel_open( 'about', $tabs['about'][0], false );
		HelpTab::about();
		self::panel_close();
		echo '</div></div>';
		Ui::close();
	}
}
