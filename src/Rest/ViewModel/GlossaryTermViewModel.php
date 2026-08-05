<?php
/**
 * REST presentation contract for one glossary term.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Rest\ViewModel;

/**
 * Glossary term ViewModel v1 (not GlossaryTermMatch).
 */
final class GlossaryTermViewModel {

	/**
	 * Builds the ViewModel.
	 *
	 * @param int    $glossary_id            Term id.
	 * @param int    $source_lang_id         Source language id.
	 * @param int    $target_lang_id         Target language id.
	 * @param string $source_term            Source spelling.
	 * @param string $source_term_normalized Canonical source.
	 * @param string $target_term            Approved target.
	 * @param string $context                Optional context.
	 * @param string $description            Operator note.
	 * @param bool   $is_active              Active flag.
	 * @param string $created_at             Created timestamp.
	 * @param string $updated_at             Updated timestamp.
	 */
	public function __construct(
		public readonly int $glossary_id,
		public readonly int $source_lang_id,
		public readonly int $target_lang_id,
		public readonly string $source_term,
		public readonly string $source_term_normalized,
		public readonly string $target_term,
		public readonly string $context,
		public readonly string $description,
		public readonly bool $is_active,
		public readonly string $created_at,
		public readonly string $updated_at
	) {
	}

	/**
	 * Serializes the ViewModel to REST JSON.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'glossary_id'            => $this->glossary_id,
			'source_lang_id'         => $this->source_lang_id,
			'target_lang_id'         => $this->target_lang_id,
			'source_term'            => $this->source_term,
			'source_term_normalized' => $this->source_term_normalized,
			'target_term'            => $this->target_term,
			'context'                => $this->context,
			'description'            => $this->description,
			'is_active'              => $this->is_active,
			'created_at'             => $this->created_at,
			'updated_at'             => $this->updated_at,
		);
	}
}
