<?php
/**
 * UUID injection outcome.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Immutable result of a UUID injection pass.
 */
final class InjectResult {

	/**
	 * @param string                     $content Serialized post content.
	 * @param bool                       $changed Whether any block attribute changed.
	 * @param array<string, int>         $stats   Counters keyed by event/stat name.
	 * @param array<string, int>         $duplicates Duplicate UUID counts after injection.
	 * @param list<array<string, mixed>> $events Structured log events (no body text).
	 */
	public function __construct(
		public readonly string $content,
		public readonly bool $changed,
		public readonly array $stats = array(),
		public readonly array $duplicates = array(),
		public readonly array $events = array(),
	) {
	}
}
