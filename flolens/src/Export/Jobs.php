<?php
namespace UserFlo\Export;

use UserFlo\Core\Clock;

defined( 'ABSPATH' ) || exit;

/**
 * Resumable export jobs. State lives in an option (small), output in a private uploads directory.
 * A step processes bounded batches, then persists cursor + byte offset; if a step is interrupted the next
 * one truncates the file back to the last committed offset, so output is never duplicated or corrupted.
 */
final class Jobs {

	const OPTION     = 'userflo_export_jobs';
	const TTL        = DAY_IN_SECONDS;
	const MAX_ACTIVE = 2;
	const MAX_ROWS   = 5000000;
	const CRON       = 'userflo_export_cleanup';

	public static function register() {
		add_action( self::CRON, array( __CLASS__, 'cleanup' ) );
		if ( ! wp_next_scheduled( self::CRON ) && wp_doing_cron() ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON );
		}
	}

	public static function formats() {
		$f = array( 'csv' => CsvWriter::class, 'json' => JsonWriter::class );
		return apply_filters( 'userflo_export_formats', $f );
	}

	/** @return array|\WP_Error */
	public static function create( $format, $dataset, Filters $filters, $user_id ) {
		$formats = self::formats();
		if ( ! isset( $formats[ $format ] ) || ! class_exists( $formats[ $format ] ) ) {
			return new \WP_Error( 'userflo_bad_format', __( 'Unknown export format.', 'flolens' ), array( 'status' => 400 ) );
		}
		$writer = $formats[ $format ];
		if ( $writer::multi_dataset() ) {
			$sets = ( 'all' === $dataset || '' === $dataset ) ? array_keys( Datasets::all() ) : array( $dataset );
		} else {
			$sets = array( $dataset );
		}
		foreach ( $sets as $s ) {
			if ( ! Datasets::get( $s ) ) {
				return new \WP_Error( 'userflo_bad_dataset', __( 'Unknown dataset.', 'flolens' ), array( 'status' => 400 ) );
			}
		}
		$jobs   = self::all();
		$active = 0;
		foreach ( $jobs as $j ) {
			if ( (int) $j['user'] === (int) $user_id && in_array( $j['status'], array( 'running' ), true ) ) {
				++$active;
			}
		}
		if ( $active >= self::MAX_ACTIVE ) {
			return new \WP_Error( 'userflo_too_many', __( 'Too many exports are running. Wait for one to finish or cancel it.', 'flolens' ), array( 'status' => 429 ) );
		}
		$total = 0;
		foreach ( $sets as $s ) {
			$total += Query::count( $s, $filters );
		}
		if ( $total > self::MAX_ROWS ) {
			return new \WP_Error( 'userflo_too_large', __( 'This export is too large. Narrow the date range or filters.', 'flolens' ), array( 'status' => 413 ) );
		}
		$dir = self::dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}
		$id  = bin2hex( random_bytes( 16 ) );
		$job = array(
			'id'       => $id,
			'user'     => (int) $user_id,
			'format'   => $format,
			'sets'     => array_values( $sets ),
			'idx'      => 0,
			'cursor'   => 0,
			'bytes'    => 0,
			'started'  => false,
			'first'    => true,
			'rows'     => 0,
			'total'    => $total,
			'filters'  => $filters->to_array(),
			'status'   => 'running',
			'created'  => Clock::time(),
			'updated'  => Clock::time(),
			'error'    => '',
		);
		$jobs[ $id ] = $job;
		self::save( $jobs );
		return self::public_view( $job );
	}

	/**
	 * Process up to $budget seconds of work.
	 *
	 * @return array|\WP_Error
	 */
	public static function step( $id, $user_id, $budget = 2.0, $batch = Query::MAX_BATCH ) {
		$jobs = self::all();
		if ( ! self::valid_id( $id ) || ! isset( $jobs[ $id ] ) || (int) $jobs[ $id ]['user'] !== (int) $user_id ) {
			return new \WP_Error( 'userflo_not_found', __( 'Export not found.', 'flolens' ), array( 'status' => 404 ) );
		}
		$job = $jobs[ $id ];
		if ( 'running' !== $job['status'] ) {
			return self::public_view( $job );
		}
		$formats = self::formats();
		$class   = $formats[ $job['format'] ];
		/** @var WriterInterface $w */
		$w       = new $class();
		$filters = Filters::from_stored( $job['filters'] );
		$part    = self::path( $id, 'part' );

		// Recover from an interrupted step: drop anything written after the last committed offset.
		$fh = @fopen( $part, file_exists( $part ) ? 'r+' : 'w+' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return self::fail( $jobs, $id, __( 'Could not write the export file.', 'flolens' ) );
		}
		ftruncate( $fh, (int) $job['bytes'] );
		fseek( $fh, (int) $job['bytes'] );

		$end = microtime( true ) + $budget;
		if ( ! $job['started'] ) {
			fwrite( $fh, $w->begin( array( 'generated' => gmdate( 'c' ), 'plugin_version' => USERFLO_VERSION, 'site' => home_url(), 'filters' => $job['filters'], 'datasets' => $job['sets'] ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- A stream or file FloLens creates itself.
			$job['started'] = true;
		}
		$sets = $job['sets'];
		// At least one batch per step guarantees progress even with a tiny time budget.
		$first_pass = true;
		while ( $job['idx'] < count( $sets ) && ( $first_pass || microtime( true ) < $end ) ) {
			$first_pass = false;
			$key = $sets[ $job['idx'] ];
			if ( 0 === (int) $job['cursor'] && $job['first'] ) {
				fwrite( $fh, $w->dataset_start( $key, Datasets::columns( $key, $filters->anonymise ), 0 === $job['idx'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- A stream or file FloLens creates itself.
			}
			$rows = Query::batch( $key, $filters, $job['cursor'], $batch );
			foreach ( $rows as $row ) {
				fwrite( $fh, $w->row( $row, $job['first'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- A stream or file FloLens creates itself.
				$job['first']  = false;
				$job['cursor'] = (int) $row['id'];
				++$job['rows'];
			}
			if ( count( $rows ) < $batch ) {
				fwrite( $fh, $w->dataset_end() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- A stream or file FloLens creates itself.
				++$job['idx'];
				$job['cursor'] = 0;
				$job['first']  = true;
			}
		}
		$job['bytes']   = (int) ftell( $fh );
		$job['updated'] = Clock::time();
		if ( $job['idx'] >= count( $sets ) ) {
			fwrite( $fh, $w->end() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- A stream or file FloLens creates itself.
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- A stream or file FloLens creates itself.
			$final = self::path( $id, $w::extension() );
			if ( ! @rename( $part, $final ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
				return self::fail( $jobs, $id, __( 'Could not finalise the export file.', 'flolens' ) );
			}
			$job['status'] = 'done';
			$job['bytes']  = (int) filesize( $final );
		} else {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- A stream or file FloLens creates itself.
		}
		$jobs[ $id ] = $job;
		self::save( $jobs );
		return self::public_view( $job );
	}

	public static function status( $id, $user_id ) {
		$jobs = self::all();
		if ( ! self::valid_id( $id ) || ! isset( $jobs[ $id ] ) || (int) $jobs[ $id ]['user'] !== (int) $user_id ) {
			return new \WP_Error( 'userflo_not_found', __( 'Export not found.', 'flolens' ), array( 'status' => 404 ) );
		}
		return self::public_view( $jobs[ $id ] );
	}

	public static function cancel( $id, $user_id ) {
		$jobs = self::all();
		if ( ! self::valid_id( $id ) || ! isset( $jobs[ $id ] ) || (int) $jobs[ $id ]['user'] !== (int) $user_id ) {
			return new \WP_Error( 'userflo_not_found', __( 'Export not found.', 'flolens' ), array( 'status' => 404 ) );
		}
		self::remove_files( $jobs[ $id ] );
		unset( $jobs[ $id ] );
		self::save( $jobs );
		return array( 'cancelled' => true );
	}

	/** Absolute path of a finished export for the owner, or WP_Error. */
	public static function download_path( $id, $user_id ) {
		$jobs = self::all();
		if ( ! self::valid_id( $id ) || ! isset( $jobs[ $id ] ) || (int) $jobs[ $id ]['user'] !== (int) $user_id || 'done' !== $jobs[ $id ]['status'] ) {
			return new \WP_Error( 'userflo_not_found', __( 'Export not found or not finished.', 'flolens' ), array( 'status' => 404 ) );
		}
		$formats = self::formats();
		$class   = $formats[ $jobs[ $id ]['format'] ];
		$path    = self::path( $id, $class::extension() );
		if ( ! is_readable( $path ) ) {
			return new \WP_Error( 'userflo_gone', __( 'The export file has expired.', 'flolens' ), array( 'status' => 410 ) );
		}
		return array( 'path' => $path, 'mime' => $class::mime(), 'filename' => 'userflo-' . $jobs[ $id ]['sets'][0] . ( count( $jobs[ $id ]['sets'] ) > 1 ? '-all' : '' ) . '-' . gmdate( 'Ymd-His', $jobs[ $id ]['created'] ) . '.' . $class::extension() );
	}

	/** Delete expired jobs and orphaned files. */
	public static function cleanup() {
		$jobs = self::all();
		foreach ( $jobs as $id => $j ) {
			if ( Clock::time() - (int) $j['updated'] > self::TTL ) {
				self::remove_files( $j );
				unset( $jobs[ $id ] );
			}
		}
		self::save( $jobs );
		$dir = self::dir();
		if ( ! is_wp_error( $dir ) ) {
			foreach ( (array) glob( $dir . '/*' ) as $file ) {
				$base = basename( $file );
				if ( preg_match( '/^([a-f0-9]{32})\.(part|[a-z0-9]+)$/', $base, $m ) && ! isset( $jobs[ $m[1] ] ) ) {
					@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
				}
			}
		}
	}

	// ---- internals -------------------------------------------------------

	public static function valid_id( $id ) {
		return is_string( $id ) && (bool) preg_match( '/^[a-f0-9]{32}$/', $id );
	}

	public static function all() {
		$jobs = get_option( self::OPTION, array() );
		return is_array( $jobs ) ? $jobs : array();
	}

	private static function save( array $jobs ) {
		update_option( self::OPTION, $jobs, false );
	}

	private static function fail( array $jobs, $id, $msg ) {
		$jobs[ $id ]['status'] = 'failed';
		$jobs[ $id ]['error']  = $msg;
		self::save( $jobs );
		return self::public_view( $jobs[ $id ] );
	}

	public static function public_view( array $j ) {
		$pct = $j['total'] > 0 ? min( 100, (int) floor( $j['rows'] / $j['total'] * 100 ) ) : ( 'done' === $j['status'] ? 100 : 0 );
		return array( 'id' => $j['id'], 'status' => $j['status'], 'rows' => (int) $j['rows'], 'total' => (int) $j['total'], 'percent' => 'done' === $j['status'] ? 100 : $pct, 'bytes' => (int) $j['bytes'], 'error' => $j['error'], 'format' => $j['format'] );
	}

	/** Private export directory (created once, protected from direct web access). */
	public static function dir() {
		$u = wp_upload_dir( null, false );
		if ( ! empty( $u['error'] ) ) {
			return new \WP_Error( 'userflo_no_uploads', __( 'The uploads directory is not writable.', 'flolens' ), array( 'status' => 500 ) );
		}
		$dir = $u['basedir'] . '/userflo-exports';
		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return new \WP_Error( 'userflo_no_uploads', __( 'Could not create the export directory.', 'flolens' ), array( 'status' => 500 ) );
			}
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $dir . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return $dir;
	}

	/** Build a path from a validated id and extension only (no user-controlled segments). */
	private static function path( $id, $ext ) {
		$dir = self::dir();
		return $dir . '/' . $id . '.' . preg_replace( '/[^a-z0-9]/', '', strtolower( $ext ) );
	}

	private static function remove_files( array $j ) {
		if ( ! self::valid_id( $j['id'] ) ) {
			return;
		}
		$formats = self::formats();
		foreach ( array( 'part', isset( $formats[ $j['format'] ] ) ? $formats[ $j['format'] ]::extension() : 'csv' ) as $ext ) {
			$p = self::path( $j['id'], $ext );
			if ( is_file( $p ) ) {
				@unlink( $p ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions
			}
		}
	}
}
