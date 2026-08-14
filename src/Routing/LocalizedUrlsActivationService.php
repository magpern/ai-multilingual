<?php
/**
 * Localized URL activation state transitions (MSEO.2).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Jobs\SlugRouteActivationJob;
use AIMultilingual\Settings;

/**
 * O(1) enable/disable/retry; only the activation job may set state to on.
 */
final class LocalizedUrlsActivationService {

	public const STATE_OFF        = 'off';
	public const STATE_ACTIVATING = 'activating';
	public const STATE_ON         = 'on';
	public const STATE_FAILED     = 'failed';

	/**
	 * Activation job scheduler (bound after construction to avoid cycles).
	 *
	 * @var SlugRouteActivationJob|null
	 */
	private ?SlugRouteActivationJob $job = null;

	/**
	 * Constructs the service.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( private Settings $settings ) {
	}

	/**
	 * Binds the verification job scheduler.
	 *
	 * @param SlugRouteActivationJob $job Activation job.
	 */
	public function bind_job( SlugRouteActivationJob $job ): void {
		$this->job = $job;
	}

	/**
	 * Starts activation from off (O(1)).
	 */
	public function request_enable(): bool {
		$state = $this->settings->localized_urls_state();
		if ( in_array( $state, array( self::STATE_ACTIVATING, self::STATE_ON ), true ) ) {
			return false;
		}

		$this->persist(
			array(
				'localized_urls_state'                 => self::STATE_ACTIVATING,
				'localized_urls_activation_checkpoint' => null,
				'localized_urls_activation_error'      => '',
			)
		);

		$this->schedule_tick();

		return true;
	}

	/**
	 * Disables localized URL generation immediately (O(1)).
	 */
	public function request_disable(): void {
		$this->persist(
			array(
				'localized_urls_state'            => self::STATE_OFF,
				'localized_urls_activation_error' => '',
			)
		);
	}

	/**
	 * Resumes verification after failure.
	 */
	public function request_retry(): bool {
		if ( self::STATE_FAILED !== $this->settings->localized_urls_state() ) {
			return false;
		}

		$this->persist(
			array(
				'localized_urls_state'            => self::STATE_ACTIVATING,
				'localized_urls_activation_error' => '',
			)
		);

		$this->schedule_tick();

		return true;
	}

	/**
	 * Whether the activation worker should process a batch.
	 */
	public function should_run(): bool {
		return self::STATE_ACTIVATING === $this->settings->localized_urls_state();
	}

	/**
	 * Marks activation complete — only callable from the verification job.
	 */
	public function complete_activation(): void {
		$this->persist(
			array(
				'localized_urls_state'                 => self::STATE_ON,
				'localized_urls_activation_checkpoint' => null,
				'localized_urls_activation_error'      => '',
			)
		);
	}

	/**
	 * Records a blocking activation failure.
	 *
	 * @param string $message Error message for administrators.
	 */
	public function fail_activation( string $message ): void {
		$this->persist(
			array(
				'localized_urls_state'            => self::STATE_FAILED,
				'localized_urls_activation_error' => $message,
			)
		);
	}

	/**
	 * Persists the route-id cursor for resumable batches.
	 *
	 * @param int $last_route_id Last processed route id.
	 */
	public function persist_checkpoint( int $last_route_id ): void {
		$this->persist(
			array(
				'localized_urls_activation_checkpoint' => wp_json_encode(
					array( 'last_route_id' => max( 0, $last_route_id ) )
				),
			)
		);
	}

	/**
	 * Reads the checkpoint cursor route id (0 when unset).
	 */
	public function checkpoint_route_id(): int {
		$raw = $this->settings->localized_urls_activation_checkpoint();
		if ( null === $raw || '' === $raw ) {
			return 0;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return 0;
		}

		return max( 0, (int) ( $decoded['last_route_id'] ?? 0 ) );
	}

	/**
	 * @param array<string, mixed> $patch Settings patch.
	 */
	private function persist( array $patch ): void {
		$next = array_merge( $this->settings->get(), $patch );
		$this->settings->save( Settings::sanitize( $next ) );
		$this->settings->reload();
	}

	/**
	 * Enqueues or runs the next verification batch.
	 */
	private function schedule_tick(): void {
		if ( null !== $this->job ) {
			$this->job->enqueue_tick();
		}
	}
}
