<?php
/**
 * Request-scoped rollout metrics buffer and flush orchestration.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout\Metrics;

use AIMultilingual\Rollout\RolloutPolicyDecision;

/**
 * Buffers rollout policy outcomes for non-blocking persistence.
 */
final class RolloutMetricsCollector {

	/**
	 * Request-scoped metric increment buffer.
	 *
	 * @var list<array{metric_key:string,dimensions:array<string,string>,count:int,sum:int,min:?int,max:?int}>
	 */
	private array $buffer = array();

	/**
	 * Whether persistence failed for this request.
	 *
	 * @var bool
	 */
	private bool $incomplete = false;

	/**
	 * Builds the metrics collector.
	 *
	 * @param RolloutMetricsRepository|null $daily Daily aggregate store.
	 * @param RolloutHotMetricsStore|null   $hot   Hot window store.
	 */
	public function __construct(
		private ?RolloutMetricsRepository $daily = null,
		private ?RolloutHotMetricsStore $hot = null,
	) {
		$this->daily = $daily ?? new RolloutMetricsRepository();
		$this->hot   = $hot ?? RolloutHotMetricsStore::load();
	}

	/**
	 * Registers WordPress hooks for collection and scheduled flush.
	 */
	public function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'aiml_rollout_policy_decision', array( $this, 'on_policy_decision' ), 10, 2 );
		add_action( 'aiml_rollout_metrics_flush', array( $this, 'flush' ) );

		if ( ! wp_next_scheduled( 'aiml_rollout_metrics_flush' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'aiml_rollout_metrics_flush' );
		}
	}

	/**
	 * Records one policy decision into the request buffer.
	 *
	 * @param RolloutPolicyDecision $decision       Immutable policy decision.
	 * @param bool                  $render_allowed Whether frontend render proceeds.
	 */
	public function on_policy_decision( RolloutPolicyDecision $decision, bool $render_allowed ): void {
		$dimensions = array(
			'stage'       => (string) $decision->stage,
			'reason_code' => $decision->reason_code,
		);

		$this->buffer[] = array(
			'metric_key' => RolloutMetricsRegistry::POLICY_EVALUATION,
			'dimensions' => $dimensions,
			'count'      => 1,
			'sum'        => 0,
			'min'        => null,
			'max'        => null,
		);

		$metric = $render_allowed
			? RolloutMetricsRegistry::RENDER_ALLOWED
			: RolloutMetricsRegistry::RENDER_DENIED;

		$this->buffer[] = array(
			'metric_key' => $metric,
			'dimensions' => $dimensions,
			'count'      => 1,
			'sum'        => 0,
			'min'        => null,
			'max'        => null,
		);

		$this->hot->increment( 'deny:' . $decision->reason_code );

		if ( count( $this->buffer ) > 64 ) {
			$this->buffer = array_slice( $this->buffer, -64 );
		}

		if ( function_exists( 'add_action' ) ) {
			add_action(
				'shutdown',
				function (): void {
					$this->flush();
				},
				9999
			);
		}
	}

	/**
	 * Flushes buffered aggregates to persistent storage (non-blocking best effort).
	 */
	public function flush(): void {
		if ( array() === $this->buffer ) {
			return;
		}

		$pending      = $this->buffer;
		$this->buffer = array();

		foreach ( $pending as $row ) {
			$ok = $this->daily->increment(
				$row['metric_key'],
				$row['dimensions'],
				$row['count'],
				$row['sum'],
				$row['min'],
				$row['max'],
				$this->incomplete
			);

			if ( ! $ok ) {
				$this->incomplete = true;
				$this->hot->mark_incomplete();
			}
		}

		$this->daily->increment(
			RolloutMetricsRegistry::METRICS_FLUSH,
			array( 'result_class' => $this->incomplete ? 'incomplete' : 'ok' )
		);
	}

	/**
	 * Whether buffered or hot metrics are incomplete.
	 */
	public function is_incomplete(): bool {
		return $this->incomplete || $this->hot->is_incomplete();
	}

	/**
	 * Resets request buffer for tests.
	 */
	public function reset_buffer(): void {
		$this->buffer     = array();
		$this->incomplete = false;
	}
}
