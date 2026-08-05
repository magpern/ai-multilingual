<?php
/**
 * Exact-segment glossary suggestion provider (ADR-0014).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Suggestion;

use AIMultilingual\Glossary\GlossaryService;

/**
 * Emits NormalizedSuggestion only for exact-segment glossary matches.
 */
final class GlossarySuggestionProvider implements SuggestionProvider {

	public const ID = 'glossary';

	public const TIER_GLOSSARY_EXACT = 5;

	public const CONFIDENCE = 95.0;

	/**
	 * Glossary application service.
	 *
	 * @var GlossaryService
	 */
	private GlossaryService $glossary;

	/**
	 * Last unavailable reason.
	 *
	 * @var string|null
	 */
	private ?string $unavailable_reason = null;

	/**
	 * @param GlossaryService $glossary Glossary service.
	 */
	public function __construct( GlossaryService $glossary ) {
		$this->glossary = $glossary;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $segment_dto Assembled segment DTO.
	 * @param array<string, mixed> $context     Request context.
	 */
	public function is_available( array $segment_dto, array $context ): bool {
		$this->unavailable_reason = null;

		$source_lang = (int) ( $context['source_language_id'] ?? 0 );
		$target_lang = (int) ( $context['target_language_id'] ?? 0 );
		$source_text = (string) ( $segment_dto['source_text'] ?? '' );

		if ( $source_lang <= 0 || $target_lang <= 0 ) {
			$this->unavailable_reason = 'language_mismatch';
			return false;
		}

		if ( '' === trim( $source_text ) ) {
			$this->unavailable_reason = 'no_glossary_match';
			return false;
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_unavailable_reason(): ?string {
		return $this->unavailable_reason;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $segment_dto Assembled segment DTO.
	 * @param array<string, mixed> $context     Request context.
	 * @return list<NormalizedSuggestion>
	 */
	public function get_suggestions( array $segment_dto, array $context ): array {
		if ( ! $this->is_available( $segment_dto, $context ) ) {
			return array();
		}

		$match = $this->glossary->exact_segment_suggestion(
			(string) $segment_dto['source_text'],
			(int) $context['source_language_id'],
			(int) $context['target_language_id']
		);

		if ( null === $match ) {
			$this->unavailable_reason = 'no_glossary_match';
			return array();
		}

		return array(
			new NormalizedSuggestion(
				self::ID,
				$match->target_term,
				self::CONFIDENCE,
				self::TIER_GLOSSARY_EXACT,
				array(
					'glossary_id' => $match->glossary_id,
					'match_kind'  => $match->match_kind,
				)
			),
		);
	}
}
