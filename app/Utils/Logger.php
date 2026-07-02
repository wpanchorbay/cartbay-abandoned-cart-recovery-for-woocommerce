<?php
/**
 * Logger utilities.
 *
 * @package WPAnchorBay\CartBay\Utils
 */

namespace WPAnchorBay\CartBay\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Write CartBay messages to the WooCommerce logger.
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
	 * Default CartBay log retention in days.
	 *
	 * @since 1.0.0
	 */
	private const DEFAULT_RETENTION_DAYS = 7;

	/**
	 * Default CartBay log file size cap in MB.
	 *
	 * @since 1.0.0
	 */
	private const DEFAULT_MAX_SIZE_MB = 5;

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
	 * Return recent CartBay file log entries for admin display.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Maximum entries.
	 *
	 * @return array<int, array<string, mixed>> Log entries.
	 */
	public static function get_recent_entries( int $limit = 20 ): array {
		return self::get_entries( 0, 'DESC', $limit, 0, '' );
	}

	/**
	 * Return CartBay file log entries for admin display.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $limit   Maximum entries. Use 0 for all entries.
	 * @param string $order   Sort order: 'ASC' or 'DESC'.
	 * @param int    $offset  Number of entries to skip.
	 * @param int    $per_page Entries per page. Use 0 for unlimited.
	 * @param string $level   Filter by level. Use '' for all levels.
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
		$path = self::get_log_file_path();

		if ( '' === $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
			return array();
		}

		$lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( ! is_array( $lines ) ) {
			return array();
		}

		$entries = array();
		foreach ( $lines as $line ) {
			$data = json_decode( $line, true );
			if ( is_array( $data ) ) {
				if ( '' !== $level && isset( $data['level'] ) && $data['level'] !== $level ) {
					continue;
				}
				$entries[] = $data;
			}
		}

		usort(
			$entries,
			static function ( $a, $b ) use ( $order ): int {
				$a_ts   = isset( $a['timestamp'] ) ? strtotime( (string) $a['timestamp'] ) : 0;
				$b_ts   = isset( $b['timestamp'] ) ? strtotime( (string) $b['timestamp'] ) : 0;
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
			$entries = array_slice( $entries, -absint( $limit ) );
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
		$path = self::get_log_file_path();

		if ( '' === $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
			return 0;
		}

		$lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( ! is_array( $lines ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $lines as $line ) {
			$data = json_decode( $line, true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			if ( '' !== $level && isset( $data['level'] ) && $data['level'] !== $level ) {
				continue;
			}
			++$count;
		}

		return $count;
	}

	/**
	 * Get the CartBay log file path.
	 *
	 * @since 1.0.0
	 *
	 * @return string Log file path.
	 */
	public static function get_log_file_path(): string {
		$upload_dir = wp_upload_dir();
		$base_dir   = (string) $upload_dir['basedir'];

		if ( '' === $base_dir ) {
			return '';
		}

		return trailingslashit( $base_dir ) . 'cartbay/cartbay.log';
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

		self::write_file_log( $level, $message, $safe_context, $subsystem );
	}

	/**
	 * Write a sanitized CartBay-owned JSON-line log entry.
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
	private static function write_file_log( string $level, string $message, array $context, ?string $subsystem = null ): void {
		if ( ! self::is_file_logging_enabled() ) {
			return;
		}

		$path = self::get_log_file_path();
		if ( '' === $path ) {
			return;
		}

		$directory = dirname( $path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		if ( ! wp_mkdir_p( $directory ) || ! is_writable( $directory ) ) {
			return;
		}

		// Protect directory from browsing.
		$index = trailingslashit( $directory ) . 'index.php';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_readable
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden." . PHP_EOL );
		}

		self::prune_file_log( $path );

		$entry = array(
			'timestamp' => gmdate( 'c' ),
			'level'     => sanitize_key( $level ),
			'message'   => sanitize_text_field( $message ),
			'context'   => $context,
			'system'    => null !== $subsystem ? sanitize_key( $subsystem ) : 'core',
		);

		$encoded = wp_json_encode( $entry );
		if ( ! is_string( $encoded ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $path, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Prune the file log by retention and size limits.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Log file path.
	 *
	 * @return void
	 */
	private static function prune_file_log( string $path ): void {
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return;
		}

		$lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( ! is_array( $lines ) ) {
			return;
		}

		$cutoff = time() - ( self::get_retention_days() * DAY_IN_SECONDS );
		$kept   = array();

		foreach ( $lines as $line ) {
			$data      = json_decode( $line, true );
			$timestamp = is_array( $data ) && isset( $data['timestamp'] ) ? strtotime( (string) $data['timestamp'] ) : false;
			if ( false === $timestamp || $timestamp >= $cutoff ) {
				$kept[] = $line;
			}
		}

		$max_bytes  = self::get_max_size_mb() * MB_IN_BYTES;
		$log_size   = strlen( implode( PHP_EOL, $kept ) );
		$line_count = count( $kept );

		while ( $log_size > $max_bytes && $line_count > 1 ) {
			array_shift( $kept );
			$log_size   = strlen( implode( PHP_EOL, $kept ) );
			$line_count = count( $kept );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $path, empty( $kept ) ? '' : implode( PHP_EOL, $kept ) . PHP_EOL, LOCK_EX );
	}

	/**
	 * Whether CartBay-owned file logging is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether file logging is enabled.
	 */
	private static function is_file_logging_enabled(): bool {
		$settings = get_option( 'cartbay_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		return ! array_key_exists( 'log_enabled', $settings ) || ! empty( $settings['log_enabled'] );
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
	 * Get configured log max size in MB.
	 *
	 * @since 1.0.0
	 *
	 * @return int Max size in MB.
	 */
	private static function get_max_size_mb(): int {
		$settings = get_option( 'cartbay_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		return max( 1, min( 20, absint( $settings['log_max_size_mb'] ?? self::DEFAULT_MAX_SIZE_MB ) ) );
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
