<?php
/**
 * Dependency injection container.
 *
 * @package WPAnchorBay\CartBay\Core
 */

namespace WPAnchorBay\CartBay\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Simple dependency injection container.
 *
 * @since 1.0.0
 */
class Container {
	/**
	 * Registered container bindings.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, callable>
	 */
	private array $bindings = array();

	/**
	 * Resolved singleton instances.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Register a binding.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $service_id Service identifier.
	 * @param callable $factory  Factory callback.
	 *
	 * @return void
	 */
	public function bind( string $service_id, callable $factory ): void {
		$this->bindings[ $service_id ] = $factory;
	}

	/**
	 * Register a singleton binding.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $service_id Service identifier.
	 * @param callable $factory  Factory callback.
	 *
	 * @return void
	 */
	public function singleton( string $service_id, callable $factory ): void {
		$this->bindings[ $service_id ] = function () use ( $service_id, $factory ) {
			if ( ! isset( $this->instances[ $service_id ] ) ) {
				$instance = $factory( $this );

				if ( ! is_object( $instance ) ) {
					throw new \RuntimeException( esc_html__( 'CartBay singleton factories must return an object.', 'cartbay' ) );
				}

				$this->instances[ $service_id ] = $instance;
			}

			return $this->instances[ $service_id ];
		};
	}

	/**
	 * Resolve a binding.
	 *
	 * @since 1.0.0
	 *
	 * @param string $service_id Service identifier.
	 *
	 * @return mixed
	 *
	 * @throws \InvalidArgumentException When a service cannot be resolved.
	 */
	public function make( string $service_id ): mixed {
		if ( isset( $this->bindings[ $service_id ] ) ) {
			return ( $this->bindings[ $service_id ] )( $this );
		}

		if ( class_exists( $service_id ) ) {
			return new $service_id();
		}

		throw new \InvalidArgumentException(
			sprintf(
				/* translators: %s: unresolved service identifier. */
				esc_html__( 'CartBay could not resolve the service "%s".', 'cartbay' ),
				esc_html( $service_id )
			)
		);
	}
}
