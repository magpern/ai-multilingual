<?php
/**
 * Strategy F request-scoped metrics snapshot.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Immutable in-process metrics snapshot (no persistence).
 */
final class BlockMetricsSnapshot {

	/**
	 * Builds a metrics snapshot.
	 *
	 * @param string             $generated_at            ISO-8601 timestamp.
	 * @param array<string, int> $counters                Stable counter values.
	 * @param int                $render_count            Timed render events counted.
	 * @param int                $render_total_elapsed_ms Sum of render elapsed_ms values.
	 * @param int                $render_average_elapsed_ms Average render elapsed_ms.
	 * @param int                $render_max_elapsed_ms   Maximum render elapsed_ms.
	 * @param int                $ignored_event_count     Malformed events ignored.
	 * @param bool               $incomplete              Whether metrics are partial.
	 */
	public function __construct(
		public readonly string $generated_at,
		public readonly array $counters,
		public readonly int $render_count,
		public readonly int $render_total_elapsed_ms,
		public readonly int $render_average_elapsed_ms,
		public readonly int $render_max_elapsed_ms,
		public readonly int $ignored_event_count,
		public readonly bool $incomplete,
	) {
	}

	/**
	 * Serializes the snapshot for CLI/admin consumers.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'generated_at'              => $this->generated_at,
			'counters'                  => $this->counters,
			'render_count'              => $this->render_count,
			'render_total_elapsed_ms'   => $this->render_total_elapsed_ms,
			'render_average_elapsed_ms' => $this->render_average_elapsed_ms,
			'render_max_elapsed_ms'     => $this->render_max_elapsed_ms,
			'ignored_event_count'       => $this->ignored_event_count,
			'incomplete'                => $this->incomplete,
		);
	}
}
