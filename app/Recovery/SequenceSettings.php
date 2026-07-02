<?php
/**
 * Recovery sequence settings helper.
 *
 * @package WPAnchorBay\CartBay\Recovery
 */

namespace WPAnchorBay\CartBay\Recovery;

defined( 'ABSPATH' ) || exit;

/**
 * Provides defaults, formatting, and validation for recovery sequence settings.
 *
 * @since 1.0.0
 */
class SequenceSettings {
	/**
	 * Minimum allowed delay in minutes.
	 *
	 * @since 1.0.0
	 */
	private const MIN_DELAY_MINUTES = 15;

	/**
	 * Maximum allowed delay in minutes.
	 *
	 * @since 1.0.0
	 */
	private const MAX_DELAY_MINUTES = 10080;

	/**
	 * Minimum gap between sequence steps in minutes.
	 *
	 * @since 1.0.0
	 */
	private const MIN_STEP_GAP_MINUTES = 15;

	/**
	 * Get the recommended campaign settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Default campaign settings.
	 */
	public static function get_defaults(): array {
		return array(
			'enabled' => true,
			'steps'   => array(
				array(
					'delay_minutes'  => 45,
					'template_id'    => 0,
					'coupon_enabled' => false,
				),
				array(
					'delay_minutes'  => 24 * 60,
					'template_id'    => 0,
					'coupon_enabled' => false,
				),
				array(
					'delay_minutes'  => 72 * 60,
					'template_id'    => 0,
					'coupon_enabled' => true,
				),
			),
		);
	}

	/**
	 * Get UX copy for each recovery step.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, string>> Step labels and guidance.
	 */
	public static function get_step_blueprint(): array {
		return array(
			0 => array(
				'label'             => __( 'Email 1', 'cartbay' ),
				'name'              => __( 'Initial reminder', 'cartbay' ),
				'when'              => __( 'Sent shortly after abandonment while purchase intent is still fresh.', 'cartbay' ),
				'why'               => __( 'Early reminders recover straightforward purchases before shoppers move on or compare alternatives.', 'cartbay' ),
				'expected_behavior' => __( 'Keep this email concise. Restore the cart, restate the product value, and avoid discounting too early.', 'cartbay' ),
				'coupon_guidance'   => __( 'Leave coupons off here unless discounts are part of your standard first-touch strategy.', 'cartbay' ),
			),
			1 => array(
				'label'             => __( 'Email 2', 'cartbay' ),
				'name'              => __( 'Value follow-up', 'cartbay' ),
				'when'              => __( 'Sent after the shopper has had time to compare options or step away from checkout.', 'cartbay' ),
				'why'               => __( 'This follow-up brings the cart back to mind without making the sequence feel aggressive.', 'cartbay' ),
				'expected_behavior' => __( 'Use this email to reinforce value, answer common objections, and make returning to checkout simple.', 'cartbay' ),
				'coupon_guidance'   => __( 'Use a coupon here only when price resistance is a common reason shoppers abandon checkout.', 'cartbay' ),
			),
			2 => array(
				'label'             => __( 'Email 3', 'cartbay' ),
				'name'              => __( 'Final recovery email', 'cartbay' ),
				'when'              => __( 'Sent later in the sequence when a clearer incentive may be needed to recover the order.', 'cartbay' ),
				'why'               => __( 'Final emails work best when they create a clear reason to return now.', 'cartbay' ),
				'expected_behavior' => __( 'Use this email for the strongest conversion message: urgency, a clear call to action, and an optional coupon.', 'cartbay' ),
				'coupon_guidance'   => __( 'This is the strongest place for an incentive if your store uses recovery discounts.', 'cartbay' ),
			),
		);
	}

	/**
	 * Normalize campaign settings into a safe, complete structure.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $campaign Raw campaign settings.
	 *
	 * @return array<string, mixed> Normalized campaign settings.
	 */
	public static function normalize( array $campaign ): array {
		$defaults              = self::get_defaults();
		$normalized            = $defaults;
		$normalized['enabled'] = self::normalize_boolean( $campaign['enabled'] ?? $defaults['enabled'] );
		$raw_steps             = isset( $campaign['steps'] ) && is_array( $campaign['steps'] ) ? $campaign['steps'] : array();
		$previous_delay        = 0;

		for ( $index = 0; $index < 3; $index++ ) {
			$default_step = $defaults['steps'][ $index ];
			$raw_step     = isset( $raw_steps[ $index ] ) && is_array( $raw_steps[ $index ] ) ? $raw_steps[ $index ] : array();
			$delay        = self::sanitize_delay_minutes( $raw_step, $default_step['delay_minutes'] );
			$template_id  = absint( $raw_step['template_id'] ?? $default_step['template_id'] );

			if ( $index > 0 && $delay <= $previous_delay ) {
				$delay = $previous_delay + self::MIN_STEP_GAP_MINUTES;
			}

			$delay = min( $delay, self::MAX_DELAY_MINUTES );

			$normalized['steps'][ $index ] = array(
				'delay_minutes'  => $delay,
				'template_id'    => $template_id,
				'coupon_enabled' => self::normalize_boolean( $raw_step['coupon_enabled'] ?? $default_step['coupon_enabled'] ),
			);

			$previous_delay = $delay;
		}

		return $normalized;
	}

	/**
	 * Determine whether campaign settings still match the legacy starter defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $campaign Campaign settings.
	 *
	 * @return bool True when the legacy defaults appear untouched.
	 */
	public static function is_legacy_default_campaign( array $campaign ): bool {
		$enabled = self::normalize_boolean( $campaign['enabled'] ?? false );
		$steps   = isset( $campaign['steps'] ) && is_array( $campaign['steps'] ) ? $campaign['steps'] : array();
		$delays  = array();

		for ( $index = 0; $index < 3; $index++ ) {
			$delays[] = self::sanitize_delay_minutes(
				isset( $steps[ $index ] ) && is_array( $steps[ $index ] ) ? $steps[ $index ] : array(),
				0
			);
		}

		return ! $enabled && array( 60, 1440, 4320 ) === $delays;
	}

	/**
	 * Split a delay into a friendly value and unit for form inputs.
	 *
	 * @since 1.0.0
	 *
	 * @param int $delay_minutes Delay in minutes.
	 *
	 * @return array<string, int|string> Delay parts.
	 */
	public static function get_delay_parts( int $delay_minutes ): array {
		$delay_minutes = max( self::MIN_DELAY_MINUTES, absint( $delay_minutes ) );

		if ( 0 === $delay_minutes % 1440 ) {
			return array(
				'value' => (int) ( $delay_minutes / 1440 ),
				'unit'  => 'days',
			);
		}

		if ( 0 === $delay_minutes % 60 ) {
			return array(
				'value' => (int) ( $delay_minutes / 60 ),
				'unit'  => 'hours',
			);
		}

		return array(
			'value' => $delay_minutes,
			'unit'  => 'minutes',
		);
	}

	/**
	 * Format a delay in a human-readable way.
	 *
	 * @since 1.0.0
	 *
	 * @param int $delay_minutes Delay in minutes.
	 *
	 * @return string Human-readable delay label.
	 */
	public static function format_delay( int $delay_minutes ): string {
		$parts = self::get_delay_parts( $delay_minutes );
		$value = absint( $parts['value'] );
		$unit  = sanitize_key( (string) $parts['unit'] );

		if ( 'days' === $unit ) {
			/* translators: %d: number of days */
			return sprintf( _n( '%d day', '%d days', $value, 'cartbay' ), $value );
		}

		if ( 'hours' === $unit ) {
			/* translators: %d: number of hours */
			return sprintf( _n( '%d hour', '%d hours', $value, 'cartbay' ), $value );
		}

		/* translators: %d: number of minutes */
		return sprintf( _n( '%d minute', '%d minutes', $value, 'cartbay' ), $value );
	}

	/**
	 * Convert raw delay input into minutes.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $raw_step      Raw step settings.
	 * @param int                  $default_delay Fallback delay in minutes.
	 *
	 * @return int Sanitized delay in minutes.
	 */
	private static function sanitize_delay_minutes( array $raw_step, int $default_delay ): int {
		if ( isset( $raw_step['delay_minutes'] ) ) {
			$delay_minutes = absint( $raw_step['delay_minutes'] );
		} elseif ( isset( $raw_step['delay_value'] ) ) {
			$delay_value = absint( $raw_step['delay_value'] );
			$delay_unit  = isset( $raw_step['delay_unit'] ) ? sanitize_key( (string) $raw_step['delay_unit'] ) : 'minutes';
			$multiplier  = match ( $delay_unit ) {
				'days'    => 1440,
				'hours'   => 60,
				default   => 1,
			};
			$delay_minutes = $delay_value * $multiplier;
		} elseif ( isset( $raw_step['delay_hours'] ) ) {
			$delay_minutes = absint( $raw_step['delay_hours'] ) * 60;
		} else {
			$delay_minutes = absint( $default_delay );
		}

		return max( self::MIN_DELAY_MINUTES, min( $delay_minutes, self::MAX_DELAY_MINUTES ) );
	}

	/**
	 * Normalize checkbox-like values into booleans.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return bool Normalized boolean.
	 */
	private static function normalize_boolean( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array( $value, array( 'yes', '1', 'true', 'on' ), true );
		}

		return ! empty( $value );
	}
}
