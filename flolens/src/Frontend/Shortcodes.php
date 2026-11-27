<?php
namespace UserFlo\Frontend;

use UserFlo\Geo\Countries;
use UserFlo\Insights\Achievements;
use UserFlo\Modules;
use UserFlo\Plugin;
use UserFlo\Rest\GeoRoutes;
use UserFlo\Rest\InsightRoutes;
use UserFlo\Rest\Routes;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end shortcodes: [userflo_overview], [userflo_statistics], [userflo_achievements], [userflo_map].
 * Output is rendered on the server from the same cached aggregates as the dashboard (aggregates only: never names, emails, IPs or visitor ids).
 *
 * Attributes: access="admin|members|public" (default admin = users who may view analytics), days="7|30|90" (default 30),
 * theme="light|dark", plus per-shortcode options documented in docs/SHORTCODES.md.
 */
final class Shortcodes {

	public static function register() {
		if ( ! Modules::on( 'shortcodes' ) ) {
			return;
		}
		foreach ( array( 'overview', 'statistics', 'achievements', 'map' ) as $t ) {
			add_shortcode( 'userflo_' . $t, array( __CLASS__, $t ) );
			add_shortcode( 'UserFlo_' . ucfirst( $t ), array( __CLASS__, $t ) ); // Mixed-case alias; WordPress tags are case sensitive.
		}
	}

	private static function atts( $atts, array $extra = array() ) {
		return shortcode_atts( array_merge( array( 'access' => 'admin', 'days' => '30', 'theme' => 'light' ), $extra ), (array) $atts );
	}

	/** @return string|null Reason text when the current viewer may not see it (rendered only for logged-in admins), or null when allowed. */
	private static function allowed( $a ) {
		$access = in_array( $a['access'], array( 'admin', 'members', 'public' ), true ) ? $a['access'] : 'admin';
		if ( 'public' !== $access && ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core constant or hook, not ours.
			define( 'DONOTCACHEPAGE', true ); // Private output must not end up in a page cache.
		}
		if ( 'public' === $access ) {
			return null;
		}
		if ( 'members' === $access ) {
			return is_user_logged_in() ? null : '';
		}
		return current_user_can( Plugin::cap() ) ? null : '';
	}

	private static function days( $a ) {
		$d = (int) $a['days'];
		return in_array( $d, array( 1, 7, 30, 90, 365 ), true ) ? $d : 30;
	}

	private static function wrap( $cls, $a, $html, $title = '' ) {
		wp_enqueue_style( 'userflo-share', USERFLO_URL . 'assets/css/share.css', array(), USERFLO_VERSION );
		$dark = 'dark' === $a['theme'] ? ' us-dark' : '';
		return '<div class="us-sc us-sc-' . esc_attr( $cls ) . $dark . '">' . ( $title ? '<h2 class="us-sc-title">' . esc_html( $title ) . '</h2>' : '' ) . $html . '</div>';
	}

	private static function req( $days ) {
		$r = new \WP_REST_Request( 'GET', '/userflo/v1/x' );
		$r->set_param( 'days', $days );
		return $r;
	}

	private static function stat( $label, $value ) {
		return '<div class="us-stat"><b>' . esc_html( $value ) . '</b><span>' . esc_html( $label ) . '</span></div>';
	}

	private static function rank( $title, array $rows ) {
		$h = '<section class="us-card"><h3>' . esc_html( $title ) . '</h3>';
		if ( ! $rows ) {
			return $h . '<p class="us-empty">' . esc_html__( 'No data in this period.', 'flolens' ) . '</p></section>';
		}
		$max = max( 1, max( array_column( $rows, 1 ) ) );
		$h  .= '<ol class="us-rank">';
		foreach ( array_slice( $rows, 0, 8 ) as $r ) {
			$h .= '<li><div class="t"><span class="l">' . esc_html( $r[0] ) . '</span><span>' . esc_html( number_format_i18n( $r[1] ) ) . '</span></div><div class="bar"><i style="width:' . esc_attr( max( 2, round( $r[1] / $max * 100 ) ) ) . '%"></i></div></li>';
		}
		return $h . '</ol></section>';
	}

	private static function dur( $s ) {
		$s = (int) $s;
		return $s < 60 ? $s . 's' : ( $s < 3600 ? floor( $s / 60 ) . 'm ' . ( $s % 60 ) . 's' : floor( $s / 3600 ) . 'h ' . floor( ( $s % 3600 ) / 60 ) . 'm' );
	}

	private static function chart( array $daily ) {
		if ( ! $daily ) {
			return '';
		}
		$max = max( 1, max( array_column( $daily, 'pageviews' ) ) );
		$h   = '<section class="us-card"><h3>' . esc_html__( 'Sessions and page views per day', 'flolens' ) . '</h3><div class="us-chart" role="img" aria-label="' . esc_attr__( 'Sessions and page views per day', 'flolens' ) . '">';
		foreach ( $daily as $p ) {
			$h .= '<i style="height:' . esc_attr( max( 1, round( $p['pageviews'] / $max * 100 ) ) ) . '%" title="' . esc_attr( $p['date'] . ': ' . $p['pageviews'] ) . '"><b style="height:' . esc_attr( min( 100, round( $p['sessions'] / max( 1, $p['pageviews'] ) * 100 ) ) ) . '%"></b></i>';
		}
		return $h . '</div></section>';
	}

	public static function overview( $atts ) {
		$a = self::atts( $atts, array( 'show' => 'kpis,daily,pages,sources' ) );
		if ( null !== ( $deny = self::allowed( $a ) ) ) {
			return $deny;
		}
		$d = Routes::overview( self::req( self::days( $a ) ) );
		if ( is_wp_error( $d ) ) {
			return '';
		}
		$show = array_map( 'trim', explode( ',', $a['show'] ) );
		$k    = $d['kpis'];
		$h    = '';
		if ( in_array( 'kpis', $show, true ) ) {
			$h .= '<div class="us-stats">' . self::stat( __( 'Visitors', 'flolens' ), number_format_i18n( $k['visitors'] ) ) . self::stat( __( 'Sessions', 'flolens' ), number_format_i18n( $k['sessions'] ) ) . self::stat( __( 'Page views', 'flolens' ), number_format_i18n( $k['pageviews'] ) ) . self::stat( __( 'Avg. session', 'flolens' ), self::dur( $k['avg_session'] ) ) . self::stat( __( 'Engaged time', 'flolens' ), self::dur( $k['engaged'] ) ) . self::stat( __( 'Active now', 'flolens' ), number_format_i18n( $k['active_now'] ) ) . '</div>';
		}
		if ( in_array( 'daily', $show, true ) ) {
			$h .= self::chart( $d['daily'] );
		}
		$cols = '';
		if ( in_array( 'pages', $show, true ) ) {
			$cols .= self::rank( __( 'Popular pages', 'flolens' ), array_map( static function ( $p ) { return array( $p['url'], $p['views'] ); }, $d['pages'] ) );
		}
		if ( in_array( 'sources', $show, true ) ) {
			$cols .= self::rank( __( 'Traffic sources', 'flolens' ), array_map( static function ( $r ) { return array( $r['source'], $r['sessions'] ); }, $d['referrers'] ) );
		}
		return self::wrap( 'overview', $a, $h . ( $cols ? '<div class="us-cols">' . $cols . '</div>' : '' ) );
	}

	public static function statistics( $atts ) {
		$a = self::atts( $atts, array( 'show' => 'pages,buttons,links,outbound,downloads,journeys' ) );
		if ( ! Modules::on( 'statistics' ) ) {
			return '';
		}
		if ( null !== ( $deny = self::allowed( $a ) ) ) {
			return $deny;
		}
		$d = InsightRoutes::insights( self::req( self::days( $a ) ) );
		if ( is_wp_error( $d ) ) {
			return '';
		}
		$ev   = static function ( $rows ) { return array_map( static function ( $r ) { return array( $r['key'], $r['count'] ); }, $rows ); };
		$show = array_map( 'trim', explode( ',', $a['show'] ) );
		$cols = '';
		$map  = array(
			'pages'     => array( __( 'Most viewed pages', 'flolens' ), array_map( static function ( $p ) { return array( $p['url'], $p['views'] ); }, $d['pages'] ) ),
			'buttons'   => array( __( 'Most clicked buttons', 'flolens' ), $ev( $d['buttons'] ) ),
			'links'     => array( __( 'Most clicked internal links', 'flolens' ), $ev( $d['links'] ) ),
			'outbound'  => array( __( 'Most visited outbound links', 'flolens' ), $ev( $d['outbound'] ) ),
			'downloads' => array( __( 'Most downloaded files', 'flolens' ), $ev( $d['downloads'] ) ),
			'devices'   => array( __( 'Devices', 'flolens' ), $ev( $d['devices'] ) ),
			'browsers'  => array( __( 'Browsers', 'flolens' ), $ev( $d['browsers'] ) ),
		);
		foreach ( $map as $k => $m ) {
			if ( in_array( $k, $show, true ) ) {
				$cols .= self::rank( $m[0], $m[1] );
			}
		}
		$h = '<div class="us-cols">' . $cols . '</div>';
		if ( in_array( 'journeys', $show, true ) ) {
			$h .= '<section class="us-card"><h3>' . esc_html__( 'Common user journeys', 'flolens' ) . '</h3>';
			foreach ( array_slice( $d['journeys']['top'], 0, 8 ) as $j ) {
				$h .= '<p>' . esc_html( number_format_i18n( $j['count'] ) . '×  ' . implode( '  →  ', $j['steps'] ) ) . '</p>';
			}
			if ( ! $d['journeys']['top'] ) {
				$h .= '<p class="us-empty">' . esc_html__( 'No data in this period.', 'flolens' ) . '</p>';
			}
			$h .= '</section>';
		}
		return self::wrap( 'statistics', $a, $h );
	}

	public static function achievements( $atts ) {
		$a = self::atts( $atts, array( 'status' => 'all', 'category' => '', 'limit' => '60' ) );
		if ( ! Modules::on( 'achievements' ) ) {
			return '';
		}
		if ( null !== ( $deny = self::allowed( $a ) ) ) {
			return $deny;
		}
		$d = Achievements::evaluate( Achievements::metrics() );
		$tiers = array( 'copper' => __( 'Copper', 'flolens' ), 'silver' => __( 'Silver', 'flolens' ), 'gold' => __( 'Gold', 'flolens' ), 'diamond' => __( 'Diamond', 'flolens' ), 'olympian' => __( 'Olympian', 'flolens' ) );
		$h = '<div class="us-ach-grid">';
		$n = 0;
		foreach ( $d['items'] as $i ) {
			if ( 'secret' === $i['status'] || ( 'unlocked' === $a['status'] && 'unlocked' !== $i['status'] ) || ( '' !== $a['category'] && $a['category'] !== $i['category'] ) ) {
				continue;
			}
			if ( ++$n > max( 1, (int) $a['limit'] ) ) {
				break;
			}
			$pct = (int) round( $i['progress'] * 100 );
			$h  .= '<article class="us-ach us-tier-' . esc_attr( $i['tier'] ) . ' us-ach-' . esc_attr( $i['status'] ) . '"><header><strong>' . esc_html( $i['name'] ) . '</strong>' . ( 'family' === $i['kind'] && 'locked' !== $i['status'] ? '<span class="us-tier">' . esc_html( $tiers[ $i['tier'] ] ) . '</span>' : '' ) . '</header><p>' . esc_html( $i['desc'] ) . '</p><div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( $pct ) . '"><i style="width:' . esc_attr( $pct ) . '%"></i></div><small>' . esc_html( number_format_i18n( $i['value'] ) . ' / ' . number_format_i18n( $i['target'] ) ) . '</small></article>';
		}
		$h .= '</div>';
		$sum = '<p class="us-sc-sum">' . esc_html( sprintf( /* translators: 1: unlocked tiers, 2: total tiers */ __( '%1$d of %2$d achievement tiers unlocked', 'flolens' ), $d['summary']['unlocked'], $d['summary']['total'] ) ) . '</p>';
		return self::wrap( 'achievements', $a, $sum . $h );
	}

	/** Static SVG world map (no zoom): [userflo_map level="countries" region="world"]. */
	public static function map( $atts ) {
		$a = self::atts( $atts, array( 'level' => 'countries', 'region' => 'world' ) );
		if ( ! Modules::on( 'map' ) ) {
			return '';
		}
		if ( null !== ( $deny = self::allowed( $a ) ) ) {
			return $deny;
		}
		$g = GeoRoutes::summary( self::req( self::days( $a ) ) );
		if ( is_wp_error( $g ) ) {
			return '';
		}
		$world = json_decode( (string) file_get_contents( USERFLO_DIR . 'assets/geo/world.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $world ) ) {
			return '';
		}
		$regions = array( 'world' => array( -180, 180, 84, -58 ), 'EU' => array( -25, 45, 72, 34 ), 'AS' => array( 25, 150, 75, -10 ), 'AF' => array( -20, 52, 38, -36 ), 'NA' => array( -170, -50, 75, 5 ), 'SA' => array( -85, -30, 14, -57 ), 'OC' => array( 110, 180, 5, -48 ) );
		$reg     = isset( $regions[ $a['region'] ] ) ? $regions[ $a['region'] ] : $regions['world'];
		$px      = static function ( $lon ) use ( $world ) { return ( $lon + 180 ) / 360 * $world['w']; };
		$py      = static function ( $lat ) use ( $world ) { return ( $world['latMax'] - max( $world['latMin'], min( $world['latMax'], $lat ) ) ) / ( $world['latMax'] - $world['latMin'] ) * $world['h']; };
		$x0      = $px( $reg[0] );
		$x1      = $px( $reg[1] );
		$y0      = $py( $reg[2] );
		$y1      = $py( $reg[3] );
		$level   = in_array( $a['level'], array( 'continents', 'countries', 'cities' ), true ) ? $a['level'] : 'countries';
		$pts     = 'continents' === $level ? $g['continents'] : ( 'cities' === $level ? $g['cities'] : $g['countries'] );
		$by      = array_column( $g['countries'], 'visitors', 'code' );
		$maxc    = max( 1, $by ? max( $by ) : 1 );
		$maxp    = max( 1, $pts ? max( array_column( $pts, 'visitors' ) ) : 1 );
		$svg     = '<svg class="us-map" viewBox="' . esc_attr( round( $x0, 1 ) . ' ' . round( $y0, 1 ) . ' ' . round( $x1 - $x0, 1 ) . ' ' . round( $y1 - $y0, 1 ) ) . '" role="img" aria-label="' . esc_attr__( 'World map of visitors', 'flolens' ) . '"><rect x="0" y="0" width="' . esc_attr( $world['w'] ) . '" height="' . esc_attr( $world['h'] ) . '" class="sea"/>';
		foreach ( $world['countries'] as $cc => $path ) {
			$op   = isset( $by[ $cc ] ) ? round( 0.35 + 0.65 * $by[ $cc ] / $maxc, 2 ) : 0.35;
			$svg .= '<path d="' . esc_attr( $path ) . '" class="land' . ( isset( $by[ $cc ] ) ? ' has' : '' ) . '" fill-opacity="' . esc_attr( $op ) . '"/>';
		}
		$scale   = max( 1, $world['w'] / max( 1, $x1 - $x0 ) );
		$visible = array();
		foreach ( $pts as $p ) {
			$x = $px( $p['lon'] );
			$y = $py( $p['lat'] );
			if ( $x < $x0 - 5 || $x > $x1 + 5 || $y < $y0 - 5 || $y > $y1 + 5 ) {
				continue;
			}
			$visible[] = $p;
			$r    = ( 11 + 15 * sqrt( $p['visitors'] / $maxp ) ) / $scale;
			$svg .= '<g transform="translate(' . round( $x, 1 ) . ' ' . round( $y, 1 ) . ')"><circle r="' . round( $r, 1 ) . '"/><text text-anchor="middle" dy="' . round( 4 / $scale, 2 ) . '" font-size="' . round( 12 / $scale, 2 ) . '">' . esc_html( $p['visitors'] ) . '</text></g>';
		}
		$svg .= '</svg>';
		$list = '<table class="us-t us-sr"><caption>' . esc_html__( 'Visitors by location', 'flolens' ) . '</caption><tbody>';
		foreach ( $visible as $p ) {
			$list .= '<tr><td>' . esc_html( isset( $p['country_name'] ) ? $p['name'] . ', ' . $p['country_name'] : $p['name'] ) . '</td><td>' . esc_html( $p['visitors'] ) . '</td></tr>';
		}
		$list .= '</tbody></table>';
		return self::wrap( 'map', $a, $svg . $list );
	}
}
