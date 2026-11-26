<?php
namespace UserFlo\Privacy;

use UserFlo\Database\Reports;
use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress personal-data export and erasure (Tools > Export/Erase Personal Data) and privacy-policy text.
 * Only data linked to a WordPress account (visitors.user_id) can be found by e-mail address; purely
 * anonymous visitors are not identifiable and are therefore out of scope for these tools.
 */
final class PersonalData {

	const SESSIONS_PER_PAGE = 20;

	public static function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy_text' ) );
	}

	public static function exporter( $exporters ) {
		$exporters['userflo'] = array( 'exporter_friendly_name' => __( 'UserFlo visitor activity', 'userflo' ), 'callback' => array( __CLASS__, 'export' ) );
		return $exporters;
	}

	public static function eraser( $erasers ) {
		$erasers['userflo'] = array( 'eraser_friendly_name' => __( 'UserFlo visitor activity', 'userflo' ), 'callback' => array( __CLASS__, 'erase' ) );
		return $erasers;
	}

	private static function visitor_ids( $email ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array();
		}
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Tables::visitors() . ' WHERE user_id = %d ORDER BY id', $user->ID ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
	}

	private static function item( $group, $label, $id, array $pairs ) {
		$data = array();
		foreach ( $pairs as $k => $v ) {
			$data[] = array( 'name' => $k, 'value' => (string) $v );
		}
		return array( 'group_id' => $group, 'group_label' => $label, 'item_id' => $id, 'data' => $data );
	}

	public static function export( $email, $page = 1 ) {
		global $wpdb;
		$ids   = self::visitor_ids( $email );
		$items = array();
		if ( ! $ids ) {
			return array( 'data' => array(), 'done' => true );
		}
		$page = max( 1, (int) $page );
		$in   = implode( ',', $ids );
		$vt   = Tables::visitors();
		$st   = Tables::sessions();

		if ( 1 === $page ) {
			foreach ( $wpdb->get_results( "SELECT * FROM {$vt} WHERE id IN ({$in})" ) as $v ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own tables: names are internal constants, values are prepared.
				$items[] = self::item( 'userflo-visitor', __( 'UserFlo visitor profiles', 'userflo' ), 'userflo-visitor-' . $v->id, array(
					__( 'Visitor ID', 'userflo' )       => $v->visitor_uid,
					__( 'First seen (UTC)', 'userflo' ) => $v->first_seen,
					__( 'Last seen (UTC)', 'userflo' )  => $v->last_seen,
					__( 'Sessions', 'userflo' )         => $v->session_count,
					__( 'Page views', 'userflo' )       => $v->pageview_count,
					__( 'Device', 'userflo' )           => trim( $v->device . ' / ' . $v->browser . ' / ' . $v->os ),
					__( 'IP address (as stored)', 'userflo' ) => $v->ip,
					__( 'First referrer', 'userflo' )   => $v->first_referrer,
				) );
			}
		}
		$sessions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$st} WHERE visitor_id IN ({$in}) ORDER BY id ASC LIMIT %d OFFSET %d", self::SESSIONS_PER_PAGE, ( $page - 1 ) * self::SESSIONS_PER_PAGE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
		foreach ( $sessions as $s ) {
			$pv    = $wpdb->get_results( $wpdb->prepare( 'SELECT viewed_at, url, title FROM ' . Tables::pageviews() . ' WHERE session_id = %d ORDER BY viewed_at LIMIT 500', $s->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
			$ev    = $wpdb->get_results( $wpdb->prepare( 'SELECT occurred_at, type, name, value FROM ' . Tables::events() . ' WHERE session_id = %d ORDER BY occurred_at LIMIT 1000', $s->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
			$pages = array();
			foreach ( $pv as $p ) {
				$pages[] = $p->viewed_at . ' ' . $p->url;
			}
			$evs = array();
			foreach ( $ev as $e ) {
				$evs[] = $e->occurred_at . ' ' . $e->type . ' ' . $e->name . ( $e->value ? ' ' . $e->value : '' );
			}
			$items[] = self::item( 'userflo-session', __( 'UserFlo sessions', 'userflo' ), 'userflo-session-' . $s->id, array(
				__( 'Started (UTC)', 'userflo' )       => $s->started_at,
				__( 'Last activity (UTC)', 'userflo' ) => $s->last_activity_at,
				__( 'Login (UTC)', 'userflo' )         => $s->login_at,
				__( 'Logout (UTC)', 'userflo' )        => $s->logout_at,
				__( 'Entry page', 'userflo' )          => $s->entry_url,
				__( 'Referrer', 'userflo' )            => $s->referrer,
				__( 'Pages viewed', 'userflo' )        => implode( "\n", $pages ),
				__( 'Events', 'userflo' )              => implode( "\n", $evs ),
			) );
		}
		return array( 'data' => $items, 'done' => count( $sessions ) < self::SESSIONS_PER_PAGE );
	}

	public static function erase( $email, $page = 1 ) {
		$ids     = self::visitor_ids( $email );
		$removed = 0;
		foreach ( array_slice( $ids, 0, 20 ) as $id ) {
			if ( Reports::delete_visitor( $id ) ) {
				++$removed;
			}
		}
		return array( 'items_removed' => $removed > 0, 'items_retained' => false, 'messages' => $removed ? array( sprintf( /* translators: %d: number of visitor profiles */ _n( 'Removed %d UserFlo visitor profile with its sessions, page views and events.', 'Removed %d UserFlo visitor profiles with their sessions, page views and events.', $removed, 'userflo' ), $removed ) ) : array(), 'done' => count( $ids ) <= 20 );
	}

	public static function policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$ret = (int) \UserFlo\Settings\Settings::get( 'retention_days' );
		$ip  = \UserFlo\Settings\Settings::get( 'ip_mode' );
		$ipt = array( 'off' => __( 'IP addresses are not stored.', 'userflo' ), 'truncate' => __( 'IP addresses are stored in anonymised form (last part removed).', 'userflo' ), 'hash' => __( 'IP addresses are stored only as a salted hash.', 'userflo' ), 'full' => __( 'Full IP addresses are stored.', 'userflo' ) );
		$txt = '<h2>' . esc_html__( 'Visitor activity (UserFlo)', 'userflo' ) . '</h2><p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text. Review and adapt it to your site and your legal requirements.', 'userflo' ) . '</p>';
		$txt .= '<p>' . esc_html__( 'We measure how visitors use this website with a self-hosted tool. It sets two first-party cookies (a random visitor ID kept for up to 13 months and a session ID kept while you are active) and records the pages you view, clicks on links and buttons, scroll depth, time on page, your approximate device type, browser and referring website. If you log in, your activity is linked to your account. Form field contents are never recorded.', 'userflo' ) . ' ' . esc_html( $ipt[ $ip ] ) . ' ';
		$txt .= esc_html( $ret > 0 ? sprintf( /* translators: %d: days */ __( 'Data is deleted after %d days.', 'userflo' ), $ret ) : __( 'Data is kept until it is deleted manually.', 'userflo' ) ) . ' ' . esc_html__( 'The data stays on our own server and is not shared with third parties.', 'userflo' ) . '</p>';
		$sy  = (int) \UserFlo\Settings\Settings::get( 'summary_years' );
		if ( $sy > 0 ) {
			$txt .= ' ' . esc_html( sprintf( /* translators: %d: years */ _n( 'In addition, one daily row of totals is kept for %d year (visit counts per day, page, source, country and device). It contains no visitor ID, IP address or other personal data.', 'In addition, one daily row of totals is kept for %d years (visit counts per day, page, source, country and device). It contains no visitor ID, IP address or other personal data.', $sy, 'userflo' ), $sy ) );
		}
		wp_add_privacy_policy_content( 'UserFlo', wp_kses_post( wpautop( $txt, false ) ) );
	}
}
