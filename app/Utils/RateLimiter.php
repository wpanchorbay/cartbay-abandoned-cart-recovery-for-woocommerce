<?php
/**
 * Rate limiter utility.
 *
 * @package WPAnchorBay\CartBay\Utils
 */

namespace WPAnchorBay\CartBay\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Transient-based rate limiter for public REST endpoints.
 *
 * @since 1.0.0
 */
class RateLimiter {

	/**
	 * Check and increment rate limit for a given endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint Short endpoint slug, e.g. 'capture'.
	 * @param int    $limit    Max requests per window.
	 * @param int    $window   TTL in seconds.
	 *
	 * @return bool True if request is allowed, false if limit exceeded.
	 */
	public static function check( string $endpoint, int $limit = 10, int $window = 600 ): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'cartbay_rl_' . $endpoint . '_' . md5( $ip );

		$current = (int) get_transient( $key );

		if ( $current >= $limit ) {
			return false;
		}

		if ( 0 === $current ) {
			set_transient( $key, 1, $window );
		} else {
			set_transient( $key, $current + 1, $window );
		}

		return true;
	}
}
