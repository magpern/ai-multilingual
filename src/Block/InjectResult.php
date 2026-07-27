<?php
/**
 * UUID injection outcome.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Immutable result of a UUID injection and duplicate-repair pass.
 */
final class InjectResult {

	/**
	 * Immutable result of a UUID injection and duplicate-repair pass.
	 *
	 * @param string                     $content        Serialized post content or empty when unused.
	 * @param bool                       $changed        Whether any block attribute changed.
	 * @param array<string, int>         $stats          Diagnostic counters.
	 * @param array<string, int>         $duplicates     Remaining duplicate UUID counts after repair.
	 * @param list<array<string, mixed>> $events         Structured log events (no body text).
	 * @param bool                       $successful     Whether the pass completed without aborting.
	 * @param string|null                $failure_reason Failure reason when unsuccessful.
	 * @param array<string, string>      $replacements   Old UUID to replacement UUID map.
	 */
	public function __construct(
		public readonly string $content = '',
		public readonly bool $changed = false,
		public readonly array $stats = array(),
		public readonly array $duplicates = array(),
		public readonly array $events = array(),
		public readonly bool $successful = true,
		public readonly ?string $failure_reason = null,
		public readonly array $replacements = array(),
	) {
	}

	/**
	 * Default diagnostic counters for an injection pass.
	 *
	 * @return array<string, int>
	 */
	public static function default_stats(): array {
		return array(
			'uuid_created'            => 0,
			'uuid_preserved'          => 0,
			'uuid_replaced_invalid'   => 0,
			'uuid_duplicate_detected' => 0,
			'uuid_duplicate_repaired' => 0,
		);
	}
}
