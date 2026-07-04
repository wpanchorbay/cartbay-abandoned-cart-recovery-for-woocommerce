<?php
/**
 * Token helper utility.
 *
 * @package WPAnchorBay\CartBay\Utils
 */

namespace WPAnchorBay\CartBay\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Cryptographically secure token generation and hashing.
 *
 * @since 1.0.0
 */
class TokenHelper {

	/**
	 * Generate a secure 64-character token.
	 *
	 * @since 1.0.0
	 *
	 * @return string Plain token (store only the hash).
	 */
	public static function generate(): string {
		return wp_generate_password( 64, false );
	}

	/**
	 * Hash a token for storage.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Plain token.
	 *
	 * @return string SHA-256 hash.
	 */
	public static function hash( string $token ): string {
		return hash( 'sha256', $token );
	}

	/**
	 * Hash an email for suppression lookup.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 *
	 * @return string SHA-256 hash of normalized email.
	 */
	public static function hash_email( string $email ): string {
		return hash( 'sha256', strtolower( trim( $email ) ) );
	}

	/**
	 * Create a restore token for a session, store hash, return plain token.
	 *
	 * @since 1.0.0
	 *
	 * @param int $session_id  CartBay session order ID.
	 * @param int $ttl_seconds Token time-to-live in seconds. Default 48 hours.
	 *
	 * @return string Plain token to embed in the restore URL.
	 */
	public static function create_restore_token( int $session_id, int $ttl_seconds = 172800 ): string {
		$token = self::generate();
		$hash  = self::hash( $token );

		$session = wc_get_order( $session_id );
		if ( $session ) {
			$tokens   = $session->get_meta( '_cartbay_token_hashes', true );
			$tokens   = is_array( $tokens ) ? $tokens : array();
			$tokens[] = array(
				'hash'       => $hash,
				'expires_at' => time() + $ttl_seconds,
				'created_at' => time(),
			);

			$session->update_meta_data( '_cartbay_token_hash', $hash );
			$session->update_meta_data( '_cartbay_token_expires_at', time() + $ttl_seconds );
			$session->update_meta_data( '_cartbay_token_hashes', $tokens );
			$session->save();
		}

		return $token;
	}

	/**
	 * Validate a restore token against a session.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $session WC order representing the cart session.
	 * @param string    $token   Plain token from URL.
	 *
	 * @return bool True if token is valid and not expired.
	 */
	public static function validate_restore_token( \WC_Order $session, string $token ): bool {
		$token_hash  = self::hash( $token );
		$stored_hash = $session->get_meta( '_cartbay_token_hash', true );
		$expires_at  = (int) $session->get_meta( '_cartbay_token_expires_at', true );

		if ( ! empty( $stored_hash ) && ! empty( $expires_at ) && time() <= $expires_at && hash_equals( $stored_hash, $token_hash ) ) {
			return true;
		}

		return self::validate_token_hash_history( $session, $token_hash );
	}

	/**
	 * Validate a token hash against historical restore token hashes.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $session    WC order representing the cart session.
	 * @param string    $token_hash SHA-256 token hash.
	 *
	 * @return bool True if a non-expired historical token matches.
	 */
	private static function validate_token_hash_history( \WC_Order $session, string $token_hash ): bool {
		$tokens = $session->get_meta( '_cartbay_token_hashes', true );
		if ( ! is_array( $tokens ) ) {
			return false;
		}

		foreach ( $tokens as $token_data ) {
			if ( ! is_array( $token_data ) ) {
				continue;
			}

			$stored_hash = sanitize_text_field( (string) ( $token_data['hash'] ?? '' ) );
			$expires_at  = absint( $token_data['expires_at'] ?? 0 );
			if ( '' === $stored_hash || 0 === $expires_at || time() > $expires_at ) {
				continue;
			}

			if ( hash_equals( $stored_hash, $token_hash ) ) {
				return true;
			}
		}

		return false;
	}
}
