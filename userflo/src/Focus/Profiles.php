<?php
namespace UserFlo\Focus;

defined( 'ABSPATH' ) || exit;

/**
 * Site types and what UserFlo emphasises for each: page groups (matched by URL prefix or query fragment), a funnel of
 * those groups, and event highlights. Patterns: "/prefix" matches the start of the path, "?fragment" matches anywhere in the
 * query string. Extend or change with the `userflo_site_type_profiles` filter. Groups auto-add the real WooCommerce page
 * paths and The Events Calendar base when those plugins are active.
 */
final class Profiles {

	/** id => label (no translation of patterns). */
	public static function types() {
		return array(
			'general'   => __( 'General / not specified', 'userflo' ),
			'blog'      => __( 'Blog or news site', 'userflo' ),
			'company'   => __( 'Company or business site', 'userflo' ),
			'store'     => __( 'Online store', 'userflo' ),
			'events'    => __( 'Events or listings site', 'userflo' ),
			'forum'     => __( 'Forum or community', 'userflo' ),
			'learning'  => __( 'Courses or membership', 'userflo' ),
			'portfolio' => __( 'Portfolio or landing page', 'userflo' ),
		);
	}

	public static function descriptions() {
		return array(
			'general'   => __( 'No special focus: the overview shows the standard numbers.', 'userflo' ),
			'blog'      => __( 'Focus on articles, categories, reading depth and where readers come from.', 'userflo' ),
			'company'   => __( 'Focus on services, pricing, contact and careers pages, forms and downloads.', 'userflo' ),
			'store'     => __( 'Focus on shop, product, basket, checkout and account pages with a purchase funnel and shop events.', 'userflo' ),
			'events'    => __( 'Focus on event listings, calendar and event pages, outbound clicks to ticket providers and calendar downloads.', 'userflo' ),
			'forum'     => __( 'Focus on forums and topics, member profiles, sign-ups and logins.', 'userflo' ),
			'learning'  => __( 'Focus on courses, lessons, quizzes, learner accounts and sign-ups.', 'userflo' ),
			'portfolio' => __( 'Focus on projects, about and contact pages and enquiries.', 'userflo' ),
		);
	}

	private static function g( $id, $label, array $include, array $exclude = array() ) {
		return array( 'id' => $id, 'label' => $label, 'include' => $include, 'exclude' => $exclude ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Intended: hides one page from a list.
	}

	/** @return array|null profile: groups[], funnel[] (group ids or "event:<type>"), events[] (event types to highlight). */
	public static function get( $type ) {
		$p = array(
			'blog'      => array(
				'groups' => array(
					self::g( 'posts', __( 'Articles', 'userflo' ), array( '/blog/', '/news/', '/post/', '/article', '/20' ), array( '/category/', '/tag/', '/author/' ) ),
					self::g( 'categories', __( 'Categories and tags', 'userflo' ), array( '/category/', '/tag/', '?cat=', '?tag=' ) ),
					self::g( 'authors', __( 'Author pages', 'userflo' ), array( '/author/', '?author=' ) ),
					self::g( 'search', __( 'Site search', 'userflo' ), array( '?s=' ) ),
					self::g( 'about', __( 'About and contact', 'userflo' ), array( '/about', '/contact', '/newsletter', '/subscribe' ) ),
				),
				'funnel' => array( 'posts', 'event:form' ),
				'events' => array( 'outbound', 'form', 'download' ),
			),
			'company'   => array(
				'groups' => array(
					self::g( 'services', __( 'Services and solutions', 'userflo' ), array( '/service', '/solution', '/what-we-do', '/products', '/features' ) ),
					self::g( 'pricing', __( 'Pricing', 'userflo' ), array( '/pricing', '/plans', '/prices' ) ),
					self::g( 'cases', __( 'Case studies and work', 'userflo' ), array( '/case-stud', '/portfolio', '/work', '/clients', '/references' ) ),
					self::g( 'about', __( 'About and team', 'userflo' ), array( '/about', '/team', '/company' ) ),
					self::g( 'careers', __( 'Careers', 'userflo' ), array( '/career', '/jobs', '/join' ) ),
					self::g( 'contact', __( 'Contact', 'userflo' ), array( '/contact', '/get-in-touch', '/book', '/quote' ) ),
				),
				'funnel' => array( 'services', 'pricing', 'contact', 'event:form' ),
				'events' => array( 'form', 'download', 'outbound' ),
			),
			'store'     => array(
				'groups' => array(
					self::g( 'shop', __( 'Shop and categories', 'userflo' ), array( '/shop', '/product-category/', '/product-tag/', '/store', '?post_type=product', '?product_cat=' ) ),
					self::g( 'product', __( 'Product pages', 'userflo' ), array( '/product/', '/products/', '?product=' ) ),
					self::g( 'cart', __( 'Basket', 'userflo' ), array( '/cart', '/basket', '?wc-ajax' ) ),
					self::g( 'checkout', __( 'Checkout', 'userflo' ), array( '/checkout' ), array( '/checkout/order-received', '/order-received' ) ),
					self::g( 'thankyou', __( 'Order confirmation', 'userflo' ), array( '/checkout/order-received', '/order-received', '/thank-you', '/thankyou' ) ),
					self::g( 'account', __( 'My account and orders', 'userflo' ), array( '/my-account', '/account', '/orders', '/wishlist' ) ),
				),
				'funnel' => array( 'product', 'cart', 'checkout', 'thankyou' ),
				'events' => array( 'commerce', 'form', 'outbound' ),
			),
			'events'    => array(
				'groups' => array(
					self::g( 'listing', __( 'Event listings and calendar', 'userflo' ), array( '/events', '/calendar', '/whats-on', '/programme', '/program', '?post_type=tribe_events', '?tribe-bar' ), array( '/event/', '/events/20' ) ),
					self::g( 'detail', __( 'Event pages', 'userflo' ), array( '/event/', '/events/20', '?tribe_events=' ) ),
					self::g( 'venues', __( 'Venues and organisers', 'userflo' ), array( '/venue', '/organizer', '/organiser', '/speakers', '/artists' ) ),
					self::g( 'tickets', __( 'Tickets and booking', 'userflo' ), array( '/tickets', '/book', '/cart', '/checkout', '/register' ) ),
					self::g( 'info', __( 'Visitor information', 'userflo' ), array( '/about', '/contact', '/faq', '/venue-info', '/getting-here' ) ),
				),
				'funnel' => array( 'listing', 'detail', 'event:outbound', 'tickets' ),
				'events' => array( 'outbound', 'download', 'form' ),
			),
			'forum'     => array(
				'groups' => array(
					self::g( 'forums', __( 'Forums and categories', 'userflo' ), array( '/forum', '/forums/', '/community', '/boards' ), array( '/topic/', '/reply/' ) ),
					self::g( 'topics', __( 'Topics and discussions', 'userflo' ), array( '/topic/', '/discussion', '/thread', '/reply/', '?topic=' ) ),
					self::g( 'members', __( 'Member profiles', 'userflo' ), array( '/users/', '/members/', '/member/', '/profile', '/u/' ) ),
					self::g( 'signup', __( 'Sign-up and login', 'userflo' ), array( '/register', '/signup', '/sign-up', '/login', '/wp-login.php' ) ),
					self::g( 'search', __( 'Search', 'userflo' ), array( '?s=', '/search' ) ),
				),
				'funnel' => array( 'forums', 'topics', 'signup', 'event:login' ),
				'events' => array( 'login', 'form', 'outbound' ),
			),
			'learning'  => array(
				'groups' => array(
					self::g( 'courses', __( 'Course catalogue and pages', 'userflo' ), array( '/course', '/courses', '/class', '/program' ), array( '/lesson', '/quiz' ) ),
					self::g( 'lessons', __( 'Lessons', 'userflo' ), array( '/lesson', '/lessons', '/module', '/topic' ) ),
					self::g( 'quizzes', __( 'Quizzes and assignments', 'userflo' ), array( '/quiz', '/assignment', '/exam' ) ),
					self::g( 'enrol', __( 'Pricing and enrolment', 'userflo' ), array( '/pricing', '/membership', '/enrol', '/enroll', '/checkout', '/cart' ) ),
					self::g( 'account', __( 'Learner dashboard', 'userflo' ), array( '/dashboard', '/my-account', '/my-courses', '/account' ) ),
				),
				'funnel' => array( 'courses', 'enrol', 'event:login', 'lessons' ),
				'events' => array( 'login', 'form', 'commerce' ),
			),
			'portfolio' => array(
				'groups' => array(
					self::g( 'work', __( 'Projects and work', 'userflo' ), array( '/portfolio', '/project', '/work', '/gallery', '/case' ) ),
					self::g( 'about', __( 'About', 'userflo' ), array( '/about', '/bio', '/cv', '/resume' ) ),
					self::g( 'contact', __( 'Contact and hire', 'userflo' ), array( '/contact', '/hire', '/book', '/quote' ) ),
				),
				'funnel' => array( 'work', 'contact', 'event:form' ),
				'events' => array( 'form', 'outbound', 'download' ),
			),
		);
		$p = (array) apply_filters( 'userflo_site_type_profiles', $p );
		if ( ! isset( $p[ $type ] ) ) {
			return null;
		}
		$prof = $p[ $type ];
		$prof['id'] = $type;
		return self::detect( $type, $prof );
	}

	/** Adds the real page paths of WooCommerce / The Events Calendar when they are active (custom slugs and languages). */
	private static function detect( $type, array $prof ) {
		$add = static function ( $gid, $url ) use ( &$prof ) {
			$path = $url ? wp_parse_url( $url, PHP_URL_PATH ) : '';
			if ( ! $path || '/' === $path ) {
				return;
			}
			$path = untrailingslashit( $path );
			foreach ( $prof['groups'] as &$g ) {
				if ( $g['id'] === $gid && ! in_array( $path, $g['include'], true ) ) {
					$g['include'][] = $path;
				}
			}
		};
		if ( 'store' === $type && function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'shop' => 'shop', 'cart' => 'cart', 'checkout' => 'checkout', 'account' => 'myaccount' ) as $gid => $wc ) {
				$id = (int) wc_get_page_id( $wc );
				$add( $gid, $id > 0 ? get_permalink( $id ) : '' );
			}
		}
		if ( 'events' === $type && function_exists( 'tribe_get_events_link' ) ) {
			$add( 'listing', tribe_get_events_link() );
		}
		return $prof;
	}

	/** Builds a SQL condition for a group on pageviews.url (prepared). */
	public static function condition( array $group ) {
		global $wpdb;
		$like = static function ( array $patterns ) use ( $wpdb ) {
			$parts = array();
			foreach ( $patterns as $pt ) {
				$pt = (string) $pt;
				if ( '' === $pt ) {
					continue;
				}
				$parts[] = ( '?' === $pt[0] ) ? $wpdb->prepare( 'url LIKE %s', '%' . $wpdb->esc_like( $pt ) . '%' ) : $wpdb->prepare( 'url LIKE %s', $wpdb->esc_like( $pt ) . '%' );
			}
			return $parts ? '(' . implode( ' OR ', $parts ) . ')' : '0=1';
		};
		$c = $like( $group['include'] );
		if ( ! empty( $group['exclude'] ) ) {
			$c .= ' AND NOT ' . $like( $group['exclude'] );
		}
		return '(' . $c . ')';
	}
}
