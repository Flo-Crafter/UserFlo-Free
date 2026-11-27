<?php
namespace UserFlo\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts credentials at rest (libsodium secretbox, key derived from the site's secret salts).
 * This protects against database dumps/backups leaking tokens; it cannot protect against someone who can read
 * wp-config.php. Secrets are never sent back to the browser.
 */
final class Secrets {

	private static function key() {
		return sodium_crypto_generichash( wp_salt( 'secure_auth' ) . '|userflo-integrations', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	public static function encrypt( $plain ) {
		if ( '' === (string) $plain ) {
			return '';
		}
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return 'v1:' . base64_encode( $nonce . sodium_crypto_secretbox( (string) $plain, $nonce, self::key() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/** @return string Plain text, or '' if missing/corrupt/wrong key. */
	public static function decrypt( $stored ) {
		if ( ! is_string( $stored ) || 0 !== strpos( $stored, 'v1:' ) ) {
			return '';
		}
		$raw = base64_decode( substr( $stored, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::key() );
		return false === $plain ? '' : $plain;
	}
}
