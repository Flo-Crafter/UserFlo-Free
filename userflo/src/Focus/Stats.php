<?php
namespace UserFlo\Focus;

use UserFlo\Core\Clock;
use UserFlo\Database\Tables;

defined( 'ABSPATH' ) || exit;

/** Site-type focus numbers: page groups, a visit funnel and event highlights for a period. */
final class Stats {

	public static function build( $type, $from_ts, $to_ts ) {
		global $wpdb;
		$prof = Profiles::get( $type );
		$types = Profiles::types();
		if ( ! $prof ) {
			return array( 'type' => $type, 'label' => isset( $types[ $type ] ) ? $types[ $type ] : $type, 'groups' => array(), 'funnel' => array(), 'highlights' => array() );
		}
		$pv   = Tables::pageviews();
		$ev   = Tables::events();
		$from = Clock::mysql( $from_ts );
		$to   = Clock::mysql( $to_ts );
		$groups  = array();
		$byid    = array();
		foreach ( $prof['groups'] as $g ) {
			$cond = Profiles::condition( $g );
			$row  = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS views, COUNT(DISTINCT session_id) AS sessions, COUNT(DISTINCT visitor_id) AS visitors, COALESCE(AVG(engaged_seconds),0) AS eng FROM {$pv} p WHERE viewed_at >= %s AND viewed_at < %s AND {$cond}" . \UserFlo\Exclusions\Sql::pageviews( 'p' ), $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
			$top  = $wpdb->get_results( $wpdb->prepare( "SELECT url, COUNT(*) AS n FROM {$pv} p WHERE viewed_at >= %s AND viewed_at < %s AND {$cond}" . \UserFlo\Exclusions\Sql::pageviews( 'p' ) . " GROUP BY url ORDER BY n DESC LIMIT 5", $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables: names are internal constants, values are prepared.
			$out  = array(
				'id'          => $g['id'],
				'label'       => $g['label'],
				'views'       => (int) $row->views,
				'sessions'    => (int) $row->sessions,
				'visitors'    => (int) $row->visitors,
				'avg_engaged' => (int) round( $row->eng ),
				'top'         => array_map( static function ( $t ) { return array( 'url' => $t->url, 'views' => (int) $t->n ); }, (array) $top ),
			);
			$groups[]            = $out;
			$byid[ $g['id'] ]    = $out;
		}
		$event_labels = array(
			'form'     => __( 'Form submitted', 'userflo' ),
			'outbound' => __( 'Outbound link clicked', 'userflo' ),
			'download' => __( 'File downloaded', 'userflo' ),
			'login'    => __( 'Logged in', 'userflo' ),
			'commerce' => __( 'Shop event', 'userflo' ),
		);
		$funnel = array();
		$first  = 0;
		$prev   = 0;
		foreach ( $prof['funnel'] as $i => $step ) {
			if ( 0 === strpos( $step, 'event:' ) ) {
				$t        = substr( $step, 6 );
				$label    = isset( $event_labels[ $t ] ) ? $event_labels[ $t ] : $t;
				$sessions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT session_id) FROM {$ev} e WHERE type = %s AND occurred_at >= %s AND occurred_at < %s" . \UserFlo\Exclusions\Sql::events( 'e' ), $t, $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
			} elseif ( isset( $byid[ $step ] ) ) {
				$label    = $byid[ $step ]['label'];
				$sessions = $byid[ $step ]['sessions'];
			} else {
				continue;
			}
			if ( ! $funnel ) {
				$first = $sessions;
			}
			$funnel[] = array( 'id' => $step, 'label' => $label, 'sessions' => $sessions, 'pct_prev' => $prev > 0 ? round( 100 * $sessions / $prev, 1 ) : null, 'pct_first' => $first > 0 ? round( 100 * $sessions / $first, 1 ) : null );
			$prev     = $sessions;
		}
		$highlights = array();
		foreach ( $prof['events'] as $t ) {
			$label = isset( $event_labels[ $t ] ) ? $event_labels[ $t ] : $t;
			if ( 'outbound' === $t ) { // Group by destination host: "who do visitors leave to" (ticket providers, partners).
				$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT value, COUNT(*) AS n FROM {$ev} e WHERE type = 'outbound' AND occurred_at >= %s AND occurred_at < %s AND value <> ''" . \UserFlo\Exclusions\Sql::events( 'e' ) . " GROUP BY value ORDER BY n DESC LIMIT 300", $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
				$hosts = array();
				foreach ( (array) $rows as $r ) {
					$h           = (string) wp_parse_url( $r->value, PHP_URL_HOST );
					$h           = '' !== $h ? preg_replace( '/^www\./', '', $h ) : $r->value;
					$hosts[ $h ] = ( isset( $hosts[ $h ] ) ? $hosts[ $h ] : 0 ) + (int) $r->n;
				}
				arsort( $hosts );
				$items = array();
				foreach ( array_slice( $hosts, 0, 8, true ) as $h => $n ) {
					$items[] = array( 'label' => $h, 'count' => $n );
				}
				$highlights[] = array( 'id' => 'outbound', 'label' => __( 'Where visitors go (outbound destinations)', 'userflo' ), 'items' => $items );
				continue;
			}
			$col  = 'form' === $t ? "COALESCE(NULLIF(name,''), target)" : ( 'download' === $t ? 'value' : ( 'commerce' === $t ? 'name' : 'type' ) );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$col} AS k, COUNT(*) AS n FROM {$ev} e WHERE type = %s AND occurred_at >= %s AND occurred_at < %s" . \UserFlo\Exclusions\Sql::events( 'e' ) . " GROUP BY k ORDER BY n DESC LIMIT 8", $t, $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Own tables: names are internal constants, values are prepared.
			$highlights[] = array( 'id' => $t, 'label' => $label, 'items' => array_map( static function ( $r ) { return array( 'label' => (string) $r->k, 'count' => (int) $r->n ); }, (array) $rows ) );
		}
		return array(
			'type'       => $type,
			'label'      => isset( $types[ $type ] ) ? $types[ $type ] : $type,
			'groups'     => $groups,
			'funnel'     => $funnel,
			'highlights' => $highlights,
		);
	}
}
