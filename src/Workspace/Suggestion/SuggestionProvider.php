<?php
/**
 * Suggestion provider contract (ADR-F11-005).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Suggestion;

/**
 * Pluggable suggestion source for TranslationSuggestionService.
 */
interface SuggestionProvider {

	/**
	 * Stable provider id (tm, ai, …).
	 */
	public function get_id(): string;

	/**
	 * Whether this provider can produce suggestions for the segment.
	 *
	 * @param array<string, mixed> $segment_dto Assembled segment DTO.
	 * @param array<string, mixed> $context     Request context (langs, etc.).
	 */
	public function is_available( array $segment_dto, array $context ): bool;

	/**
	 * Optional reason when unavailable (internal diagnostics).
	 */
	public function get_unavailable_reason(): ?string;

	/**
	 * Returns normalized suggestions for one segment.
	 *
	 * @param array<string, mixed> $segment_dto Assembled segment DTO.
	 * @param array<string, mixed> $context     Request context.
	 * @return list<NormalizedSuggestion>
	 */
	public function get_suggestions( array $segment_dto, array $context ): array;
}
