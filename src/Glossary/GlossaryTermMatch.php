<?php
/**
 * Internal glossary terminology match DTO (not a public API).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Glossary;

/**
 * Application-internal match result. Not a REST/ViewModel/persistence model.
 */
final class GlossaryTermMatch {

	public const KIND_EXACT_SEGMENT = 'exact_segment';
	public const KIND_EMBEDDED      = 'embedded';

	/**
	 * Build an internal terminology match.
	 *
	 * @param int    $glossary_id            Term id.
	 * @param string $source_term            Original source spelling.
	 * @param string $target_term            Approved target.
	 * @param string $source_term_normalized Canonical source.
	 * @param string $match_kind             exact_segment|embedded.
	 * @param int    $char_offset            Start offset in scan text.
	 * @param int    $length                 Match length in scan text (chars).
	 * @param string $context                Optional context.
	 */
	public function __construct(
		public readonly int $glossary_id,
		public readonly string $source_term,
		public readonly string $target_term,
		public readonly string $source_term_normalized,
		public readonly string $match_kind,
		public readonly int $char_offset,
		public readonly int $length,
		public readonly string $context = ''
	) {
	}

	/**
	 * Whether this match covers the entire source segment.
	 */
	public function is_exact_segment(): bool {
		return self::KIND_EXACT_SEGMENT === $this->match_kind;
	}
}
