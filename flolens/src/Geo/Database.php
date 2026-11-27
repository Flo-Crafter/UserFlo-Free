<?php
namespace UserFlo\Geo;

use UserFlo\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Downloads and refreshes the free DB-IP "lite" database (CC BY 4.0, https://db-ip.com, no key needed) into
 * wp-content/uploads/userflo-geo/. Runs in the background (WP-Cron); never during a visitor request.
 */
final class Database {

	const OPTION = 'userflo_geo_db';
	const CRON   = 'userflo_geo_update';
	const HOST   = 'download.db-ip.com';

	public static function register() {
		add_action( self::CRON, array( __CLASS__, 'run' ) );
		add_action( 'admin_post_userflo_geo_download', array( __CLASS__, 'handle_download' ) );
		if ( 'off' !== \UserFlo\Geo\Locator::mode() && 'dbip' === Settings::get( 'geo_source' ) && Settings::get( 'geo_auto_update' ) && ! wp_next_scheduled( self::CRON ) && ( wp_doing_cron() || is_admin() ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON ); // Checked daily; downloads only when a new month's file exists.
		}
	}

	public static function dir() {
		$u = wp_upload_dir();
		return trailingslashit( $u['basedir'] ) . 'userflo-geo/';
	}

	/** Expected file for the chosen precision. */
	public static function file() {
		return self::dir() . ( 'city' === \UserFlo\Geo\Locator::mode() ? 'dbip-city-lite.mmdb' : 'dbip-country-lite.mmdb' );
	}

	public static function status() {
		$f   = self::file();
		$rec = get_option( self::OPTION, array() );
		return array(
			'installed' => is_readable( $f ),
			'size'      => is_readable( $f ) ? filesize( $f ) : 0,
			'built'     => is_array( $rec ) && isset( $rec[ basename( $f ) ] ) ? $rec[ basename( $f ) ] : '',
			'error'     => is_array( $rec ) && isset( $rec['error'] ) ? (string) $rec['error'] : '',
			'file'      => $f,
		);
	}

	public static function handle_download() {
		check_admin_referer( 'userflo_geo_download' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'flolens' ), 403 );
		}
		update_option( self::OPTION, array_merge( (array) get_option( self::OPTION, array() ), array( 'error' => '' ) ), false );
		$state = 'queued';
		if ( 'city' !== \UserFlo\Geo\Locator::mode() ) {
			// The country file is small: download it now so the result (and any error) is shown straight away instead of depending on WP-Cron.
			if ( function_exists( 'set_time_limit' ) ) {
				set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- One explicit, admin-started download.
			}
			$state = self::run( 'force' ) ? 'done' : 'failed';
		} else {
			// The city database is large: run it in the background.
			wp_schedule_single_event( time() + 5, self::CRON, array( 'force' ) );
			spawn_cron();
		}
		wp_safe_redirect( add_query_arg( array( 'tab' => 'geolocation', 'uf_geo' => $state ), admin_url( 'admin.php?page=userflo-settings' ) ) );
		exit;
	}

	/** @param string $force 'force' to download even when the current month's file is already installed. */
	public static function run( $force = '' ) {
		if ( 'dbip' !== Settings::get( 'geo_source' ) || 'off' === \UserFlo\Geo\Locator::mode() ) {
			return false;
		}
		$kind = 'city' === \UserFlo\Geo\Locator::mode() ? 'city' : 'country';
		$name = 'dbip-' . $kind . '-lite.mmdb';
		$rec  = (array) get_option( self::OPTION, array() );
		$cur  = gmdate( 'Y-m' );
		if ( 'force' !== $force && is_readable( self::dir() . $name ) && isset( $rec[ $name ] ) && $rec[ $name ] >= $cur ) {
			return true;
		}
		$res = false;
		foreach ( array( $cur, gmdate( 'Y-m', strtotime( 'first day of last month' ) ) ) as $month ) {
			$res = self::download( 'https://' . self::HOST . '/free/dbip-' . $kind . '-lite-' . $month . '.mmdb.gz', $name );
			if ( true === $res ) {
				$rec[ $name ] = $month;
				$rec['error'] = '';
				update_option( self::OPTION, $rec, false );
				Locator::reset();
				\UserFlo\Logger::add( 'info', 'geo', sprintf( 'Installed %s (%s).', $name, $month ) );
				return true;
			}
		}
		$rec['error'] = is_wp_error( $res ) ? $res->get_error_message() : 'download failed';
		update_option( self::OPTION, $rec, false );
		\UserFlo\Logger::add( 'error', 'geo', $rec['error'] );
		return false;
	}

	/** @return true|\WP_Error */
	private static function download( $url, $name ) {
		$dir = self::dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'userflo_geo_dir', __( 'Could not create the geolocation folder in uploads.', 'flolens' ) );
		}
		// Keep the folder from being listed or served as a download.
		if ( ! file_exists( $dir . 'index.php' ) ) {
			file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( self::HOST !== $host ) {
			return new \WP_Error( 'userflo_geo_host', 'Unexpected host.' );
		}
		$gz  = $dir . $name . '.gz.tmp';
		$tmp = $dir . $name . '.tmp';
		$r   = wp_safe_remote_get( $url, array( 'timeout' => 600, 'stream' => true, 'filename' => $gz, 'redirection' => 2 ) );
		if ( is_wp_error( $r ) ) {
			wp_delete_file( $gz );
			return $r;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
			wp_delete_file( $gz );
			return new \WP_Error( 'userflo_geo_http', sprintf( /* translators: %d: HTTP status */ __( 'Download failed (HTTP %d).', 'flolens' ), (int) wp_remote_retrieve_response_code( $r ) ) );
		}
		$ok = self::gunzip( $gz, $tmp );
		wp_delete_file( $gz );
		if ( ! $ok ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'userflo_geo_gz', __( 'Could not unpack the downloaded database.', 'flolens' ) );
		}
		if ( ! self::valid( $tmp ) ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'userflo_geo_invalid', __( 'The downloaded file is not a valid database.', 'flolens' ) );
		}
		if ( ! @rename( $tmp, $dir . $name ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- A stream or file FloLens creates itself.
			wp_delete_file( $tmp );
			return new \WP_Error( 'userflo_geo_move', __( 'Could not install the database file.', 'flolens' ) );
		}
		return true;
	}

	private static function gunzip( $src, $dst ) {
		if ( ! function_exists( 'gzopen' ) ) {
			return false;
		}
		$in  = gzopen( $src, 'rb' );
		$out = fopen( $dst, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $in || ! $out ) {
			return false;
		}
		while ( ! gzeof( $in ) ) {
			$chunk = gzread( $in, 1048576 );
			if ( false === $chunk || false === fwrite( $out, $chunk ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
				return false;
			}
		}
		gzclose( $in );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return true;
	}

	/** Opens the file and resolves a well-known address, so a truncated or wrong file is never installed. */
	public static function valid( $path ) {
		try {
			require_once USERFLO_DIR . 'lib/maxmind-db/MaxMind/Db/Reader/InvalidDatabaseException.php';
			require_once USERFLO_DIR . 'lib/maxmind-db/MaxMind/Db/Reader/Util.php';
			require_once USERFLO_DIR . 'lib/maxmind-db/MaxMind/Db/Reader/Metadata.php';
			require_once USERFLO_DIR . 'lib/maxmind-db/MaxMind/Db/Reader/Decoder.php';
			require_once USERFLO_DIR . 'lib/maxmind-db/MaxMind/Db/Reader.php';
			$r = new \MaxMind\Db\Reader( $path );
			$r->get( '8.8.8.8' );
			$r->close();
			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}
}
