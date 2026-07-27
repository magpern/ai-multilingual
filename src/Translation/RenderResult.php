<?php
/**
 * Strategy F block renderer proof outcome.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

/**
 * Immutable result of a block renderer proof pass.
 */
final class RenderResult {

	/**
	 * Builds a render result.
	 *
	 * @param array<int, array<string, mixed>> $blocks  Parsed block tree after rendering.
	 * @param bool                             $changed Whether any block changed.
	 * @param list<array<string, mixed>>       $events  Structured log events.
	 * @param string                           $content Serialized content when produced.
	 */
	public function __construct(
		public readonly array $blocks = array(),
		public readonly bool $changed = false,
		public readonly array $events = array(),
		public readonly string $content = '',
	) {
	}
}
