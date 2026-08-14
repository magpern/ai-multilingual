<?php
/**
 * Prepared-route activation verification worker (MSEO.2 A2/A6).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use AIMultilingual\Routing\LocalizedUrlsActivationService;
use AIMultilingual\Routing\SlugRouteActivationOutcome;
use AIMultilingual\Routing\SlugRouteActivationVerifier;
use AIMultilingual\Routing\SlugRouteRepository;
use AIMultilingual\Settings;
use WP_Error;

/**
 * Read/validate/classify active routes; mutates global feature state only.
 */
final class SlugRouteActivationJob {

	public const HOOK_TICK = 'aiml_localized_urls_activation_tick';

	public const GROUP = 'aiml-localized-urls';

	public const BATCH_SIZE = 50;

	/**
	 * Constructs the job.
	 *
	 * @param Settings                       $settings  Settings.
	 * @param SlugRouteRepository            $routes    Route repository.
	 * @param SlugRouteActivationVerifier    $verifier  Route classifier.
	 * @param LocalizedUrlsActivationService $activation Activation state service.
	 */
	public function __construct(
		private Settings $settings,
		private SlugRouteRepository $routes,
		private SlugRouteActivationVerifier $verifier,
		private LocalizedUrlsActivationService $activation
	) {
	}

	/**
	 * Registers the Action Scheduler callback.
	 */
	public function register_hooks(): void {
		add_action(
			self::HOOK_TICK,
			array( $this, 'tick' ),
			10,
			0
		);
	}

	/**
	 * Whether Action Scheduler enqueue APIs are available.
	 */
	public function is_scheduler_available(): bool {
		return function_exists( 'as_enqueue_async_action' ) || function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Enqueues one verification batch.
	 *
	 * @return true|WP_Error
	 */
	public function enqueue_tick() {
		if ( $this->is_scheduler_available() ) {
			as_enqueue_async_action( self::HOOK_TICK, array(), self::GROUP );

			return true;
		}

		$this->tick();

		return true;
	}

	/**
	 * Processes one bounded batch of active routes.
	 */
	public function tick(): void {
		if ( ! $this->activation->should_run() ) {
			return;
		}

		$this->process_batch();
	}

	/**
	 * Runs one batch; exposed for tests.
	 */
	public function process_batch(): void {
		if ( ! $this->activation->should_run() ) {
			return;
		}

		$after_route_id = $this->activation->checkpoint_route_id();
		$rows           = $this->routes->list_active_routes_after( $after_route_id, self::BATCH_SIZE );

		if ( array() === $rows ) {
			$this->activation->complete_activation();

			return;
		}

		foreach ( $rows as $row ) {
			$route_id = (int) ( $row->route_id ?? 0 );
			$result   = $this->verifier->classify( $row );

			if ( SlugRouteActivationOutcome::is_blocking( (string) ( $result['outcome'] ?? '' ) ) ) {
				$this->activation->persist_checkpoint( $route_id );
				$message = (string) ( $result['message'] ?? 'Activation verification failed.' );
				if ( $route_id > 0 ) {
					$message = sprintf( 'Route %1$d: %2$s', $route_id, $message );
				}
				$this->activation->fail_activation( substr( $message, 0, 500 ) );

				return;
			}

			$this->activation->persist_checkpoint( $route_id );
		}

		if ( count( $rows ) < self::BATCH_SIZE ) {
			$this->activation->complete_activation();

			return;
		}

		$this->enqueue_tick();
	}

	/**
	 * Active route count for diagnostics.
	 */
	public function count_active_routes(): int {
		return $this->routes->count_active_routes();
	}
}
