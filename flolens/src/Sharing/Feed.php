<?php
namespace UserFlo\Sharing;

use UserFlo\Core\Clock;
use UserFlo\Database\Reports;
use UserFlo\Insights\Stats;

defined( 'ABSPATH' ) || exit;

/** Aggregate datasets as rows (first row = headers). Aggregates only: no visitor ids, names, emails or IP addresses. */
final class Feed {

	public static function sets() {
		return array(
			'summary'   => __( 'Summary', 'flolens' ),
			'daily'     => __( 'Per day', 'flolens' ),
			'pages'     => __( 'Pages', 'flolens' ),
			'referrers' => __( 'Traffic sources', 'flolens' ),
			'buttons'   => __( 'Buttons', 'flolens' ),
			'links'     => __( 'Internal links', 'flolens' ),
			'outbound'  => __( 'Outbound links', 'flolens' ),
			'downloads' => __( 'Downloads', 'flolens' ),
			'journeys'  => __( 'Journeys', 'flolens' ),
			'devices'   => __( 'Devices', 'flolens' ),
			'browsers'  => __( 'Browsers', 'flolens' ),
		);
	}

	/**
	 * @param array|null $window Optional array( from_ts, to_ts ) overriding "the last $days days".
	 * @return array|null Rows or null for an unknown set.
	 */
	public static function rows( $set, $days, $window = null ) {
		if ( is_array( $window ) && isset( $window['from_ts'], $window['to_ts'] ) && (int) $window['to_ts'] > (int) $window['from_ts'] ) {
			// An explicit period (scheduled reports): the same instants the e-mail report uses, so the two always agree.
			$from = (int) $window['from_ts'];
			$to   = (int) $window['to_ts'];
			$days = max( 1, min( 366, (int) round( ( $to - $from ) / DAY_IN_SECONDS ) ) );
		} else {
			$days = max( 1, min( 366, (int) $days ) );
			$to   = Clock::time() + 1;
			$from = $to - $days * DAY_IN_SECONDS;
		}
		$f    = Clock::mysql( $from );
		$t    = Clock::mysql( $to );
		switch ( $set ) {
			case 'summary':
				$o = Reports::overview( $f, $t );
				return array(
					array( 'metric', 'value' ),
					array( 'period_days', $days ),
					array( 'visitors', (int) $o['visitors'] ),
					array( 'new_visitors', (int) $o['new'] ),
					array( 'sessions', (int) $o['sessions'] ),
					array( 'logged_in_sessions', (int) $o['logged_in'] ),
					array( 'pageviews', (int) $o['pageviews'] ),
					array( 'avg_session_seconds', (int) round( $o['avg_duration'] ) ),
					array( 'engaged_seconds', (int) $o['engaged'] ),
				);
			case 'daily':
				$rows = array( array( 'date', 'sessions', 'pageviews' ) );
				foreach ( Reports::daily( $from, $to ) as $d => $v ) {
					$rows[] = array( $d, $v['sessions'], $v['pageviews'] );
				}
				return $rows;
			case 'pages':
				$rows = array( array( 'url', 'title', 'views', 'visitors', 'avg_engaged_seconds' ) );
				foreach ( Reports::top_pages( $f, 200, $t ) as $p ) {
					$rows[] = array( $p['url'], $p['title'], (int) $p['views'], (int) $p['visitors'], (int) round( $p['avg_engaged'] ) );
				}
				return $rows;
			case 'referrers':
				$rows = array( array( 'source', 'sessions' ) );
				foreach ( Reports::referrers( $f, 50, $t ) as $s => $n ) {
					$rows[] = array( $s, (int) $n );
				}
				return $rows;
			case 'buttons':
			case 'links':
			case 'outbound':
			case 'downloads':
				$ins  = self::insights( $from, $to );
				$rows = array( array( 'item', 'label', 'clicks' ) );
				foreach ( $ins[ $set ] as $r ) {
					$rows[] = array( $r['key'], $r['name'], $r['count'] );
				}
				return $rows;
			case 'journeys':
				$ins  = self::insights( $from, $to );
				$rows = array( array( 'journey', 'sessions' ) );
				foreach ( $ins['journeys']['top'] as $j ) {
					$rows[] = array( implode( ' > ', $j['steps'] ), $j['count'] );
				}
				return $rows;
			case 'devices':
			case 'browsers':
				$ins  = self::insights( $from, $to );
				$rows = array( array( $set === 'devices' ? 'device' : 'browser', 'sessions' ) );
				foreach ( $ins[ $set ] as $r ) {
					$rows[] = array( $r['key'], $r['count'] );
				}
				return $rows;
		}
		return null;
	}

	private static function insights( $from, $to ) {
		static $cache = array();
		$k = $from . ':' . $to;
		if ( ! isset( $cache[ $k ] ) ) {
			$cache[ $k ] = Stats::build( $from, $to );
		}
		return $cache[ $k ];
	}

	/** Neutralises spreadsheet formula injection (cells starting with = + - @ or tab/CR). */
	public static function cell( $v ) {
		if ( is_string( $v ) && '' !== $v && false !== strpos( "=+-@\t\r", $v[0] ) ) {
			return "'" . $v;
		}
		return $v;
	}

	public static function csv( array $rows ) {
		$h = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- A stream or file FloLens creates itself.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- A stream or file FloLens creates itself.
		fwrite( $h, "\xEF\xBB\xBF" ); // BOM so Excel detects UTF-8.
		foreach ( $rows as $r ) {
			fputcsv( $h, array_map( array( __CLASS__, 'cell' ), $r ) );
		}
		rewind( $h );
		$out = stream_get_contents( $h );
		fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- A stream or file FloLens creates itself.
		return $out;
	}

	public static function objects( array $rows ) {
		$head = array_shift( $rows );
		$out  = array();
		foreach ( $rows as $r ) {
			$out[] = array_combine( $head, $r );
		}
		return $out;
	}
}
