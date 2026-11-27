<?php
namespace UserFlo\Export;

defined( 'ABSPATH' ) || exit;

/** CSV helpers. */
final class CsvExporter {

	const BATCH = 1000;

	public static function datasets() {
		return Datasets::all();
	}

	/** Neutralise spreadsheet formula injection (OWASP CSV injection). */
	public static function cell( $v ) {
		$v = (string) $v;
		if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $v;
		}
		return $v;
	}

	public static function line( array $values ) {
		$fh = fopen( 'php://memory', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- A stream or file FloLens creates itself.
		fputcsv( $fh, array_map( array( __CLASS__, 'cell' ), array_values( $values ) ) );
		rewind( $fh );
		$s = stream_get_contents( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- A stream or file FloLens creates itself.
		return $s;
	}

	/**
	 * Stream a dataset to $out in bounded batches.
	 *
	 * @param string      $key       Dataset key.
	 * @param string|null $from      UTC lower bound.
	 * @param string|null $to        UTC upper bound (exclusive).
	 * @param bool|string $anonymise true/'strip', 'pseudo' or false/'none'.
	 * @param resource    $out       Output stream.
	 * @return int Rows written.
	 */
	public static function write( $key, $from, $to, $anonymise, $out ) {
		if ( ! Datasets::get( $key ) ) {
			return 0;
		}
		$f            = new Filters();
		$f->from      = $from;
		$f->to        = $to;
		$f->anonymise = true === $anonymise ? Filters::ANON_STRIP : ( false === $anonymise ? Filters::ANON_NONE : $anonymise );
		fwrite( $out, self::line( Datasets::columns( $key, $f->anonymise ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- A stream or file FloLens creates itself.
		$last  = 0;
		$total = 0;
		do {
			$rows = Query::batch( $key, $f, $last, self::BATCH );
			foreach ( $rows as $row ) {
				fwrite( $out, self::line( $row ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- A stream or file FloLens creates itself.
				$last = (int) $row['id'];
				++$total;
			}
		} while ( count( $rows ) === self::BATCH );
		return $total;
	}
}
