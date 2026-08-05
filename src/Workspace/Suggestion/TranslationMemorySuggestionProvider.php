<?php
/**
 * TM-backed suggestion provider (ADR-F11-005).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Suggestion;

use AIMultilingual\Translation\Memory\TMRepository;
use AIMultilingual\Translation\Memory\TranslationMemoryService;
use AIMultilingual\Translation\Store;

/**
 * Maps TranslationMemoryService hits to NormalizedSuggestion with rank tiers.
 */
final class TranslationMemorySuggestionProvider implements SuggestionProvider {

	public const ID = 'tm';

	public const TIER_EXACT_TM          = 1;
	public const TIER_REVIEWED_HUMAN_TM = 2;
	public const TIER_HUMAN_TM          = 3;
	public const TIER_IMPORTED_TM       = 4;
	public const TIER_FUZZY_TM          = 6;

	/**
	 * Injected dependency.
	 *
	 * @var TranslationMemoryService
	 */
	private TranslationMemoryService $tm;

	/**
	 * Last unavailable reason.
	 *
	 * @var string|null
	 */
	private ?string $unavailable_reason = null;

	/**
	 * Builds the provider.
	 *
	 * @param TranslationMemoryService $tm Translation memory service.
	 */
	public function __construct( TranslationMemoryService $tm ) {
		$this->tm = $tm;
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
			$this->unavailable_reason = 'no_tm_match';
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

		$source_lang = (int) $context['source_language_id'];
		$target_lang = (int) $context['target_language_id'];
		$source_text = (string) $segment_dto['source_text'];
		$text_format = (string) ( $segment_dto['text_format'] ?? Store::FORMAT_PLAIN );
		$context_key = TranslationMemoryService::derive_context(
			(string) ( $segment_dto['block_name'] ?? '' ),
			(string) ( $segment_dto['field_key'] ?? '' )
		);

		$out = array();

		$exact = $this->tm->lookup_exact(
			$source_text,
			$source_lang,
			$target_lang,
			$context_key,
			$text_format
		);
		if ( null !== $exact ) {
			$out[] = $this->from_hit( $exact, false );
		}

		foreach (
			$this->tm->lookup_fuzzy(
				$source_text,
				$source_lang,
				$target_lang,
				$context_key,
				$text_format
			) as $hit
		) {
			$out[] = $this->from_hit( $hit, true );
		}

		return $out;
	}

	/**
	 * Maps a TM hit to a normalized suggestion with §2.6 tier.
	 *
	 * @param array<string, mixed> $hit   TM hit from TranslationMemoryService.
	 * @param bool                 $fuzzy Whether this came from fuzzy lookup.
	 */
	private function from_hit( array $hit, bool $fuzzy ): NormalizedSuggestion {
		$match_type = (string) ( $hit['match_type'] ?? '' );
		$origin     = (string) ( $hit['origin'] ?? '' );
		$quality    = (string) ( $hit['quality'] ?? '' );
		$confidence = (float) ( $hit['confidence'] ?? 0.0 );
		$target     = (string) ( $hit['target_text'] ?? '' );

		return new NormalizedSuggestion(
			self::ID,
			$target,
			$confidence,
			self::rank_tier_for_hit( $hit, $fuzzy ),
			array(
				'match_type' => '' !== $match_type ? $match_type : ( $fuzzy ? 'fuzzy' : 'exact_global' ),
				'origin'     => $origin,
				'quality'    => $quality,
				'tm_id'      => (int) ( $hit['tm_id'] ?? 0 ),
			)
		);
	}

	/**
	 * Maps a TM hit to §2.6 rank tier (testable pure function).
	 *
	 * Reviewed human TM uses quality=human_approved as the TM-side proxy for a
	 * reviewed segment write-back (no segment FK on aiml_tm).
	 *
	 * @param array<string, mixed> $hit   TM hit payload.
	 * @param bool                 $fuzzy Whether from fuzzy lookup.
	 */
	public static function rank_tier_for_hit( array $hit, bool $fuzzy ): int {
		$match_type = (string) ( $hit['match_type'] ?? '' );
		$origin     = (string) ( $hit['origin'] ?? '' );
		$quality    = (string) ( $hit['quality'] ?? '' );

		if ( $fuzzy || 'fuzzy' === $match_type ) {
			return self::TIER_FUZZY_TM;
		}

		if ( 'exact' === $match_type ) {
			return self::TIER_EXACT_TM;
		}

		if ( TMRepository::ORIGIN_IMPORT === $origin ) {
			return self::TIER_IMPORTED_TM;
		}

		if (
			TMRepository::ORIGIN_HUMAN === $origin
			&& TMRepository::QUALITY_HUMAN_APPROVED === $quality
		) {
			return self::TIER_REVIEWED_HUMAN_TM;
		}

		return self::TIER_HUMAN_TM;
	}
}
