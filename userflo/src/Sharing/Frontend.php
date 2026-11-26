<?php
namespace UserFlo\Sharing;

use UserFlo\Brand;
use UserFlo\Settings\Settings;
use UserFlo\Modules;
use UserFlo\Rest\InsightRoutes;
use UserFlo\Rest\LiveRoutes;
use UserFlo\Rest\Routes;
use UserFlo\Tracking\RateLimiter;
use UserFlo\Privacy\IpHandler;

defined( 'ABSPATH' ) || exit;

/** Public, token-protected views: the shared page (no WordPress login) and its read-only data endpoints. */
final class Frontend {

	public static function register() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_page' ), 0 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_csv' ), 10, 2 );
	}

	public static function routes() {
		if ( ! Modules::on( 'sharing' ) ) {
			return;
		}
		$tok = '(?P<token>[a-f0-9]{32})';
		register_rest_route( Routes::NS, '/shared/' . $tok . '/(?P<what>overview|insights|live)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'shared' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( Routes::NS, '/feed/' . $tok . '/(?P<set>[a-z_]+)\.(?P<fmt>csv|json)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'feed' ), 'permission_callback' => '__return_true' ) );
	}

	public static function limited() {
		$ip = IpHandler::client_ip();
		return '' !== $ip && ! RateLimiter::allow( 'sh' . $ip, (int) apply_filters( 'userflo_rate_limit_share', 120 ) );
	}

	private static function days( \WP_REST_Request $r, array $link ) {
		$d = (int) $r->get_param( 'days' );
		return in_array( $d, array( 1, 7, 30, 90 ), true ) ? min( $d, max( 90, $link['days'] ) ) : $link['days'];
	}

	public static function shared( \WP_REST_Request $r ) {
		$link = Shares::find( $r['token'] );
		$need = 'live' === $r['what'] ? 'live' : 'stats';
		if ( ! $link || $link['scope'] !== $need ) {
			return new \WP_Error( 'userflo_share_invalid', __( 'This link is invalid or has expired.', 'userflo' ), array( 'status' => 404 ) );
		}
		if ( self::limited() ) {
			return new \WP_Error( 'userflo_rate', __( 'Too many requests.', 'userflo' ), array( 'status' => 429 ) );
		}
		$req = new \WP_REST_Request( 'GET', '/userflo/v1/x' );
		if ( 'live' === $r['what'] ) {
			$req->set_param( 'pv', (int) $r->get_param( 'pv' ) );
			$req->set_param( 'ev', (int) $r->get_param( 'ev' ) );
			$d = LiveRoutes::live( $req )->get_data();
			$d['active'] = array_map( static function ( $s ) {
				return array( 'id' => $s['id'], 'exit' => $s['exit'], 'elapsed' => $s['elapsed'], 'device' => $s['device'], 'browser' => $s['browser'], 'logged_in' => ! empty( $s['logged_in'] ) );
			}, $d['active'] );
			$d['items'] = array_map( static function ( $i ) {
				return array( 'key' => $i['key'], 'kind' => $i['kind'], 'at' => $i['at'], 'url' => $i['url'], 'title' => $i['title'], 'type' => $i['type'], 'name' => $i['name'], 'logged_in' => $i['logged_in'] ); // No ids, names or targets.
			}, $d['items'] );
			$resp = rest_ensure_response( $d );
		} else {
			$req->set_param( 'days', self::days( $r, $link ) );
			$d = 'overview' === $r['what'] ? Routes::overview( $req ) : InsightRoutes::insights( $req );
			if ( is_wp_error( $d ) ) {
				return $d;
			}
			unset( $d['recent'] ); // Contains visitor labels.
			$resp = rest_ensure_response( $d );
		}
		$resp->header( 'Cache-Control', 'no-store' );
		$resp->header( 'X-Robots-Tag', 'noindex, nofollow' );
		return $resp;
	}

	public static function feed( \WP_REST_Request $r ) {
		$link = Shares::find( $r['token'] );
		if ( ! $link || 'data' !== $link['scope'] ) {
			return new \WP_Error( 'userflo_share_invalid', __( 'This link is invalid or has expired.', 'userflo' ), array( 'status' => 404 ) );
		}
		if ( self::limited() ) {
			return new \WP_Error( 'userflo_rate', __( 'Too many requests.', 'userflo' ), array( 'status' => 429 ) );
		}
		$days = (int) $r->get_param( 'days' );
		$rows = Feed::rows( $r['set'], $days > 0 ? $days : $link['days'] );
		if ( null === $rows ) {
			return new \WP_Error( 'userflo_unknown_set', __( 'Unknown dataset.', 'userflo' ), array( 'status' => 404 ) );
		}
		$h = array( 'Cache-Control' => 'no-store, max-age=0', 'X-Robots-Tag' => 'noindex, nofollow' );
		if ( 'json' === $r['fmt'] ) {
			$resp = rest_ensure_response( Feed::objects( $rows ) );
		} else {
			$resp = new \WP_REST_Response( Feed::csv( $rows ) );
			$h   += array( 'Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'inline; filename="userflo-' . $r['set'] . '.csv"' );
		}
		$resp->set_headers( $h );
		return $resp;
	}

	/** Serves a CSV string body as-is instead of JSON-encoding it. */
	public static function serve_csv( $served, $result ) {
		if ( $result instanceof \WP_REST_Response && is_string( $result->get_data() ) && isset( $result->get_headers()['Content-Type'] ) && false !== strpos( $result->get_headers()['Content-Type'], 'text/csv' ) ) {
			echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV, formula-neutralised.
			return true;
		}
		return $served;
	}

	public static function maybe_page() {
		if ( ! isset( $_GET['userflo_share'] ) || ! Modules::on( 'sharing' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core constant or hook, not ours.
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'Content-Type: text/html; charset=utf-8' );
		$token = sanitize_text_field( wp_unslash( $_GET['userflo_share'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$link  = self::limited() ? null : Shares::find( $token );
		// The add-on that owns a scope (Pro: dashboards) lists it here; with the add-on off the link simply shows as unavailable.
		if ( ! $link || ! in_array( $link['scope'], (array) apply_filters( 'userflo_share_page_scopes', array( 'stats', 'live' ) ), true ) ) {
			status_header( 404 );
			self::template( null, '' );
			exit;
		}
		self::template( $link, $token );
		exit;
	}

	private static function strings() {
		return array(
			'visitors' => __( 'Visitors', 'userflo' ), 'sessions' => __( 'Sessions', 'userflo' ), 'pageviews' => __( 'Page views', 'userflo' ), 'avg' => __( 'Avg. session', 'userflo' ),
			'engaged' => __( 'Engaged time', 'userflo' ), 'active' => __( 'Active now', 'userflo' ), 'daily' => __( 'Sessions and page views per day', 'userflo' ),
			'pages' => __( 'Most viewed pages', 'userflo' ), 'sources' => __( 'Traffic sources', 'userflo' ), 'buttons' => __( 'Most clicked buttons', 'userflo' ),
			'links' => __( 'Most clicked internal links', 'userflo' ), 'outbound' => __( 'Most visited outbound links', 'userflo' ), 'downloads' => __( 'Most downloaded files', 'userflo' ),
			'journeys' => __( 'Common user journeys', 'userflo' ), 'devices' => __( 'Devices', 'userflo' ), 'browsers' => __( 'Browsers', 'userflo' ),
			'none' => __( 'No data in this period.', 'userflo' ), 'days' => __( 'days', 'userflo' ), 'last' => __( 'Last', 'userflo' ),
			'live_active' => __( 'Active visitors', 'userflo' ), 'feed' => __( 'Activity feed', 'userflo' ), 'current' => __( 'Current page', 'userflo' ),
			'time_on_site' => __( 'Time on site', 'userflo' ), 'device' => __( 'Device', 'userflo' ), 'loggedin' => __( 'Logged-in user', 'userflo' ), 'anon' => __( 'Anonymous visitor', 'userflo' ),
			'viewed' => __( 'viewed', 'userflo' ), 'updated' => __( 'Updated', 'userflo' ), 'error' => __( 'Could not load data. The link may have expired.', 'userflo' ),
		);
	}

	/** @param array|null $link Link record or null for the "invalid" page. */
	private static function template( $link, $token ) {
		$logo  = Brand::company_logo();
		$title = $link ? ( 'live' === $link['scope'] ? __( 'Live view', 'userflo' ) : __( 'Statistics', 'userflo' ) ) : __( 'Link unavailable', 'userflo' );
		$title = $link ? (string) apply_filters( 'userflo_share_title', $title, $link ) : $title;
		$cfg   = $link ? array(
			'scope'   => $link['scope'],
			'api'     => esc_url_raw( rest_url( 'userflo/v1/shared/' . $token . '/' ) ),
			'days'    => $link['days'],
			'expires' => gmdate( 'c', $link['expires'] ),
			'strings' => self::strings(),
			'locale'  => str_replace( '_', '-', \UserFlo\I18n::locale() ?: get_locale() ),
		) : null;
		$cfg   = $cfg ? apply_filters( 'userflo_share_config', $cfg, $link, $token ) : $cfg;
		// An add-on's scope brings its own script and style; Lite's share.js only knows statistics and the live view.
		$script = $link ? (string) apply_filters( 'userflo_share_script', USERFLO_URL . 'assets/js/share.js?ver=' . USERFLO_VERSION, $link ) : '';
		$styles = $link ? (array) apply_filters( 'userflo_share_styles', array(), $link ) : array();
		// The page is a small document of its own (no theme), so its styles and script go through WordPress's own queue and are printed here.
		wp_register_style( 'userflo-share', USERFLO_URL . 'assets/css/share.css', array(), USERFLO_VERSION );
		$style_handles = array( 'userflo-share' );
		foreach ( array_values( $styles ) as $i => $css ) {
			wp_register_style( 'userflo-share-extra-' . $i, $css, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			$style_handles[] = 'userflo-share-extra-' . $i;
		}
		if ( $cfg ) {
			wp_register_script( 'userflo-share', $script, array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_add_inline_script( 'userflo-share', 'window.userfloShare=' . wp_json_encode( $cfg ) . ';', 'before' );
		}
		$credit = (bool) Settings::get( 'share_credit' );
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer">
<title><?php echo esc_html( $title . ' — ' . get_bloginfo( 'name' ) ); ?></title>
<link rel="icon" href="<?php echo esc_url( Brand::icon_url() ); ?>" type="image/svg+xml">
<?php wp_print_styles( $style_handles ); ?>
</head>
<body>
<header class="us-head"><div class="us-wrap us-head-in">
	<div class="us-brand"><?php if ( $logo ) : ?><img class="us-company" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"><?php else : ?><strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong><?php endif; ?></div>
	<h1><?php echo esc_html( $title ); ?></h1>
</div></header>
<main class="us-wrap" id="us-app">
<?php if ( ! $link ) : ?>
	<p class="us-msg"><?php esc_html_e( 'This link is invalid or has expired. Ask the sender for a new one.', 'userflo' ); ?></p>
<?php else : ?>
	<noscript><p class="us-msg"><?php esc_html_e( 'JavaScript is required to show this page.', 'userflo' ); ?></p></noscript>
<?php endif; ?>
</main>
<footer class="us-foot"><div class="us-wrap"><?php if ( $credit ) : ?><img src="<?php echo esc_url( Brand::icon_url() ); ?>" width="20" height="20" alt=""> <?php esc_html_e( 'Powered by UserFlo', 'userflo' ); ?><?php endif; ?>
<?php if ( $link ) : ?><?php echo $credit ? ' · ' : ''; ?><?php echo esc_html( sprintf( /* translators: %s: date and time */ __( 'link valid until %s', 'userflo' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $link['expires'] ) ) ); ?><?php endif; ?></div></footer>
<?php if ( $cfg ) { wp_print_scripts( 'userflo-share' ); } ?>
</body></html>
		<?php
	}
}
