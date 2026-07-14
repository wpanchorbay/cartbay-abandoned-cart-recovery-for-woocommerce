<?php
/**
 * Logger utilities.
 *
 * @package WPAnchorBay\CartBay\Utils
 */

namespace WPAnchorBay\CartBay\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Write CartBay messages to the WooCommerce logger and a bounded database log.
 *
 * Messages are always sent to the WooCommerce logger (wc_get_logger), which is
 * the WordPress.org-sanctioned logging surface. In addition, a bounded,
 * non-autoloaded option keeps the most recent sanitized entries so the admin
 * Logs screen can display them without reading any files. No data is written to
 * the filesystem — plugin data lives in the database, per WordPress.org
 * guidelines.
 *
 * @since 1.0.0
 */
class Logger {
	/**
	 * WooCommerce logger source name.
	 *
	 * @since 1.0.0
	 */
	private const SOURCE = 'cartbay';

	/**
	 * Option that stores the bounded CartBay log ring buffer (non-autoloaded).
	 *
	 * @since 1.0.0
	 */
	private const OPTION_KEY = 'cartbay_log_entries';

	/**
	 * Default CartBay log retention in days.
	 *
	 * @since 1.0.0
	 */
	private const DEFAULT_RETENTION_DAYS = 7;

	/**
	 * Maximum number of entries kept in the database log.
	 *
	 * The log is a bounded ring buffer stored in a single non-autoloaded option,
	 * so the entry count is capped to keep the option small regardless of how
	 * much the plugin logs.
	 *
	 * @since 1.0.0
	 */
	private const MAX_ENTRIES = 500;

	/**
	 * Log an informational message.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $message   Log message.
	 * @param array       $context   Additional structured context.
	 * @param string|null $subsystem Optional subsystem slug for system attribute.
	 *
	 * @return void
	 */
	public static function info( string $message, array $context = array(), ?string $subsystem = null ): void {
		self::log( 'info', $message, $context, $subsystem );
	}

	/**
	 * Log a warning message.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $message   Log message.
	 * @param array       $context   Additional structured context.
	 * @param string|null $subsystem Optional subsystem slug for system attribute.
	 *
	 * @return void
	 */
	public static function warning( string $message, array $context = array(), ?string $subsystem = null ): void {
		self::log( 'warning', $message, $context, $subsystem );
	}

	/**
	 * Log an error message.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $message   Log message.
	 * @param array       $context   Additional structured context.
	 * @param string|null $subsystem Optional subsystem slug for system attribute.
	 *
	 * @return void
	 */
	public static function error( string $message, array $context = array(), ?string $subsystem = null ): void {
		self::log( 'error', $message, $context, $subsystem );
	}

	/**
	 * Return recent CartBay log entries for admin display.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Maximum entries.
	 *
	 * @return array<int, array<string, mixed>> Log entries.
	 */
	public static function get_recent_entries( int $limit = 20 ): array {
		return self::get_entries( $limit, 'DESC', 0, 0, '' );
	}

	/**
	 * Return CartBay log entries for admin display.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $limit    Maximum entries. Use 0 for all entries.
	 * @param string $order    Sort order: 'ASC' or 'DESC'.
	 * @param int    $offset   Number of entries to skip.
	 * @param int    $per_page Entries per page. Use 0 for unlimited.
	 * @param string $level    Filter by level. Use '' for all levels.
	 *
	 * @return array<int, array<string, mixed>> Log entries.
	 */
	public static function get_entries(
		int $limit = 0,
		string $order = 'DESC',
		int $offset = 0,
		int $per_page = 0,
		string $level = ''
	): array {
		$entries = self::read_entries();

		if ( '' !== $level ) {
			$entries = array_values(
				array_filter(
					$entries,
					static fn ( array $entry ): bool => ( $entry['level'] ?? '' ) === $level
				)
			);
		}

		usort(
			$entries,
			static function ( array $a, array $b ) use ( $order ): int {
				$a_ts   = absint( $a['time'] ?? 0 );
				$b_ts   = absint( $b['time'] ?? 0 );
				$result = $a_ts <=> $b_ts;
				return 'ASC' === $order ? $result : -$result;
			}
		);

		if ( $offset > 0 ) {
			$entries = array_slice( $entries, $offset );
		}

		if ( $per_page > 0 ) {
			$entries = array_slice( $entries, 0, $per_page );
		} elseif ( $limit > 0 ) {
			$entries = array_slice( $entries, 0, $limit );
		}

		return $entries;
	}

	/**
	 * Count total log entries optionally filtered by level.
	 *
	 * @since 1.0.0
	 *
	 * @param string $level Filter by level. Use '' for all levels.
	 *
	 * @return int Total count.
	 */
	public static function count_entries( string $level = '' ): int {
		$entries = self::read_entries();

		if ( '' === $level ) {
			return count( $entries );
		}

		return count(
			array_filter(
				$entries,
				static fn ( array $entry ): bool => ( $entry['level'] ?? '' ) === $level
			)
		);
	}

	/**
	 * Clear the stored CartBay log entries.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Log a message with the requested level.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $level      WooCommerce logger level.
	 * @param string      $message    Log message.
	 * @param array       $context    Additional structured context.
	 * @param string|null $subsystem  Optional subsystem slug for system attribute.
	 *
	 * @return void
	 */
	private static function log( string $level, string $message, array $context = array(), ?string $subsystem = null ): void {
		$safe_context = self::sanitize_context( $context );

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->log(
				$level,
				$message,
				array_merge(
					array(
						'source' => self::SOURCE,
					),
					$safe_context
				)
			);
		}

		self::store_entry( $level, $message, $safe_context, $subsystem );
	}

	/**
	 * Append a sanitized entry to the bounded database log.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $level     Log level.
	 * @param string               $message   Log message.
	 * @param array<string, mixed> $context   Log context.
	 * @param string|null          $subsystem Subsystem slug for system attribute.
	 *
	 * @return void
	 */
	private static function store_entry( string $level, string $message, array $context, ?string $subsystem = null ): void {
		if ( ! self::is_logging_enabled() ) {
			return;
		}

		$now = time();

		$entry = array(
			'time'      => $now,
			'timestamp' => gmdate( 'c', $now ),
			'level'     => sanitize_key( $level ),
			'message'   => sanitize_text_field( $message ),
			'context'   => $context,
			'system'    => null !== $subsystem ? sanitize_key( $subsystem ) : 'core',
		);

		$entries   = self::read_entries();
		$entries[] = $entry;
		$entries   = self::prune( $entries );

		update_option( self::OPTION_KEY, $entries, false );
	}

	/**
	 * Read the stored log entries.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Stored entries.
	 */
	private static function read_entries(): array {
		$entries = get_option( self::OPTION_KEY, array() );

		return is_array( $entries ) ? array_values( array_filter( $entries, 'is_array' ) ) : array();
	}

	/**
	 * Prune entries beyond the retention window and the entry cap.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $entries Entries to prune.
	 *
	 * @return array<int, array<string, mixed>> Pruned entries.
	 */
	private static function prune( array $entries ): array {
		$cutoff = time() - ( self::get_retention_days() * DAY_IN_SECONDS );

		$entries = array_values(
			array_filter(
				$entries,
				static fn ( array $entry ): bool => absint( $entry['time'] ?? 0 ) >= $cutoff
			)
		);

		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -self::MAX_ENTRIES );
		}

		return $entries;
	}

	/**
	 * Whether CartBay-owned database logging is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether logging is enabled.
	 */
	private static function is_logging_enabled(): bool {
		$settings = get_option( 'cartbay_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		if ( ! array_key_exists( 'log_enabled', $settings ) ) {
			return true;
		}

		$value = $settings['log_enabled'];

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return 1 === absint( $value );
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return false;
	}

	/**
	 * Get configured log retention days.
	 *
	 * @since 1.0.0
	 *
	 * @return int Retention days.
	 */
	private static function get_retention_days(): int {
		$settings = get_option( 'cartbay_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		return max( 1, min( 30, absint( $settings['log_retention_days'] ?? self::DEFAULT_RETENTION_DAYS ) ) );
	}

	/**
	 * Sanitize log context recursively and mask sensitive values.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> $context Raw context.
	 *
	 * @return array<mixed> Safe context.
	 */
	private static function sanitize_context( array $context ): array {
		$sanitized = array();
		foreach ( $context as $key => $value ) {
			if ( is_string( $key ) ) {
				$sanitized_key               = sanitize_key( $key );
				$sanitized[ $sanitized_key ] = self::sanitize_context_value( $sanitized_key, $value );
				continue;
			}

			$sanitized[] = self::sanitize_context_value( '', $value );
		}

		return $sanitized;
	}

	/**
	 * Sanitize a single context value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   Context key.
	 * @param mixed  $value Context value.
	 *
	 * @return mixed Safe value.
	 */
	private static function sanitize_context_value( string $key, mixed $value ): mixed {
		if ( str_contains( $key, 'license' ) || str_contains( $key, 'token' ) || str_contains( $key, 'email' ) ) {
			return '[redacted]';
		}

		if ( is_array( $value ) ) {
			return self::sanitize_context( $value );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}
}
