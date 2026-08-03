<?php
/**
 * Translation memory application service (F11).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Memory;

use AIMultilingual\Translation\Store;
use WP_Error;

/**
 * Exact/fuzzy lookup, write-back policy, and usage recording for `aiml_tm`.
 *
 * Does not write segment translations. Consumed later by
 * {@see \AIMultilingual\Workspace\Suggestion\TranslationMemorySuggestionProvider}.
 */
final class TranslationMemoryService {

	/**
	 * Default fuzzy similarity threshold (percent).
	 */
	public const DEFAULT_FUZZY_THRESHOLD = 85.0;

	/**
	 * Ambiguity gate: minimum characters for empty-context reuse (ADR-0009).
	 */
	public const AMBIGUITY_MIN_LENGTH = 25;

	/**
	 * Injected repository.
	 *
	 * @var TMRepository
	 */
	private TMRepository $repository;

	/**
	 * Builds the service.
	 *
	 * @param TMRepository $repository Persistence boundary.
	 */
	public function __construct( TMRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Returns the underlying repository (tests / composition).
	 */
	public function repository(): TMRepository {
		return $this->repository;
	}

	/**
	 * Exact hash match for a language pair and context.
	 *
	 * Confidence 100 for exact context; 95 for empty-context global reuse
	 * when the ambiguity gate passes.
	 *
	 * @param string $source_text    Source text.
	 * @param int    $source_lang_id Source language id.
	 * @param int    $target_lang_id Target language id.
	 * @param string $context        Context key.
	 * @param string $text_format    Text format.
	 * @return array<string, mixed>|null Match payload or null.
	 */
	public function lookup_exact(
		string $source_text,
		int $source_lang_id,
		int $target_lang_id,
		string $context = '',
		string $text_format = Store::FORMAT_PLAIN
	): ?array {
		$hash = Store::source_hash( $source_text, $text_format );

		$row = $this->repository->find_by_identity( $hash, $source_lang_id, $target_lang_id, $context );
		if ( null !== $row && (int) ( $row->norm_version ?? 0 ) === Store::NORM_VERSION ) {
			return $this->match_payload( $row, 100.0, 'exact' );
		}

		if ( '' !== $context ) {
			$global = $this->repository->find_by_identity( $hash, $source_lang_id, $target_lang_id, '' );
			if (
				null !== $global
				&& (int) ( $global->norm_version ?? 0 ) === Store::NORM_VERSION
				&& self::passes_ambiguity_gate( $source_text )
			) {
				return $this->match_payload( $global, 95.0, 'exact_global' );
			}
		}

		return null;
	}

	/**
	 * Ranked fuzzy candidates within a language pair.
	 *
	 * @param string $source_text    Source text.
	 * @param int    $source_lang_id Source language id.
	 * @param int    $target_lang_id Target language id.
	 * @param string $context        Preferred context (compatible filter).
	 * @param string $text_format    Text format.
	 * @param float  $threshold      Minimum similarity percent.
	 * @return list<array<string, mixed>>
	 */
	public function lookup_fuzzy(
		string $source_text,
		int $source_lang_id,
		int $target_lang_id,
		string $context = '',
		string $text_format = Store::FORMAT_PLAIN,
		float $threshold = self::DEFAULT_FUZZY_THRESHOLD
	): array {
		$threshold  = $this->resolve_threshold( $threshold );
		$normalized = Store::normalize( $source_text, $text_format );
		$candidates = $this->repository->find_fuzzy_candidates(
			$source_lang_id,
			$target_lang_id,
			$text_format,
			200
		);

		$matches = array();
		foreach ( $candidates as $row ) {
			if ( (int) ( $row->norm_version ?? 0 ) !== Store::NORM_VERSION ) {
				continue;
			}

			$row_context = (string) ( $row->context ?? '' );
			if ( '' !== $context && '' !== $row_context && $context !== $row_context ) {
				continue;
			}

			$similarity = self::similarity_percent(
				$normalized,
				Store::normalize( (string) ( $row->source_text ?? '' ), $text_format )
			);

			if ( $similarity < $threshold || $similarity >= 100.0 ) {
				continue;
			}

			$confidence = self::scale_fuzzy_confidence( $similarity, $threshold );
			$matches[]  = $this->match_payload( $row, $confidence, 'fuzzy', $similarity );
		}

		usort(
			$matches,
			static function ( array $left, array $right ): int {
				return $right['confidence'] <=> $left['confidence'];
			}
		);

		return $matches;
	}

	/**
	 * Whether a save should write back to TM (ADR-F11-004).
	 *
	 * @param string $save_origin One of: human, ai_accepted, tm_accepted, machine, import.
	 * @param string $text_format Segment text format.
	 */
	public static function is_write_back_eligible( string $save_origin, string $text_format ): bool {
		if ( in_array( $text_format, array( Store::FORMAT_SLUG, Store::FORMAT_JSON, Store::FORMAT_CODE ), true ) ) {
			return false;
		}

		return in_array( $save_origin, array( 'human', 'ai_accepted', 'import' ), true );
	}

	/**
	 * Maps a save origin to a TM provenance value.
	 *
	 * @param string $save_origin Save origin token.
	 */
	public static function origin_for_save( string $save_origin ): ?string {
		switch ( $save_origin ) {
			case 'human':
				return TMRepository::ORIGIN_HUMAN;
			case 'ai_accepted':
				return TMRepository::ORIGIN_AI;
			case 'import':
				return TMRepository::ORIGIN_IMPORT;
			default:
				return null;
		}
	}

	/**
	 * Upserts a TM entry when write-back policy allows.
	 *
	 * @param array<string, mixed> $entry       Entry fields (source/target text, langs, context, format).
	 * @param string               $save_origin Save origin token.
	 * @return object|null|WP_Error Persisted row, null when skipped, or error.
	 */
	public function write_back( array $entry, string $save_origin ) {
		$text_format = (string) ( $entry['text_format'] ?? Store::FORMAT_PLAIN );

		if ( ! self::is_write_back_eligible( $save_origin, $text_format ) ) {
			return null;
		}

		$origin = self::origin_for_save( $save_origin );
		if ( null === $origin ) {
			return null;
		}

		$source_text = (string) ( $entry['source_text'] ?? '' );
		$hash        = (string) ( $entry['source_hash'] ?? '' );
		if ( '' === $hash ) {
			$hash = Store::source_hash( $source_text, $text_format );
		}

		return $this->repository->upsert(
			array(
				'source_lang_id'   => (int) ( $entry['source_lang_id'] ?? 0 ),
				'target_lang_id'   => (int) ( $entry['target_lang_id'] ?? 0 ),
				'source_hash'      => $hash,
				'source_text'      => $source_text,
				'target_text'      => (string) ( $entry['target_text'] ?? '' ),
				'text_format'      => $text_format,
				'context'          => (string) ( $entry['context'] ?? '' ),
				'norm_version'     => (int) ( $entry['norm_version'] ?? Store::NORM_VERSION ),
				'origin'           => $origin,
				'quality'          => TMRepository::QUALITY_HUMAN_APPROVED,
				'glossary_version' => (int) ( $entry['glossary_version'] ?? 0 ),
			)
		);
	}

	/**
	 * Increments TM usage for an accepted suggestion.
	 *
	 * @param int $tm_id Memory entry id.
	 * @return object|WP_Error
	 */
	public function record_usage( int $tm_id ) {
		return $this->repository->record_usage( $tm_id );
	}

	/**
	 * Derives TM context from block name or classic field key.
	 *
	 * @param string      $block_name Block name (e.g. core/paragraph).
	 * @param string|null $field_key  Classic field key when not a block.
	 */
	public static function derive_context( string $block_name = '', ?string $field_key = null ): string {
		if ( '' !== $block_name ) {
			return 'block:' . $block_name;
		}

		if ( null !== $field_key && '' !== $field_key ) {
			return 'field:' . $field_key;
		}

		return '';
	}

	/**
	 * ADR-0009 empty-context ambiguity gate.
	 *
	 * @param string $source_text Source text.
	 */
	public static function passes_ambiguity_gate( string $source_text ): bool {
		$text = trim( $source_text );

		return strlen( $text ) >= self::AMBIGUITY_MIN_LENGTH && false !== strpos( $text, ' ' );
	}

	/**
	 * Similarity percent via PHP similar_text.
	 *
	 * @param string $left  Normalized left.
	 * @param string $right Normalized right.
	 */
	public static function similarity_percent( string $left, string $right ): float {
		if ( '' === $left && '' === $right ) {
			return 100.0;
		}

		if ( '' === $left || '' === $right ) {
			return 0.0;
		}

		similar_text( $left, $right, $percent );

		return (float) $percent;
	}

	/**
	 * Scales raw similarity into fuzzy confidence 60–94.
	 *
	 * @param float $similarity Raw similarity percent.
	 * @param float $threshold  Minimum threshold.
	 */
	public static function scale_fuzzy_confidence( float $similarity, float $threshold ): float {
		$span  = max( 0.01, 100.0 - $threshold );
		$ratio = ( $similarity - $threshold ) / $span;
		$ratio = max( 0.0, min( 1.0, $ratio ) );

		return round( 60.0 + ( 34.0 * $ratio ), 2 );
	}

	/**
	 * Resolves threshold from filter or default.
	 *
	 * @param float $threshold Requested threshold.
	 */
	private function resolve_threshold( float $threshold ): float {
		/**
		 * Filters the TM fuzzy similarity threshold (percent).
		 *
		 * @since 0.2.0
		 *
		 * @param float $threshold Minimum similarity required for fuzzy candidates.
		 */
		$filtered = apply_filters( 'aiml_tm_fuzzy_threshold', $threshold );
		$value    = is_numeric( $filtered ) ? (float) $filtered : $threshold;

		return max( 1.0, min( 99.0, $value ) );
	}

	/**
	 * Builds a normalized match payload.
	 *
	 * @param object     $row         TM row.
	 * @param float      $confidence  Confidence 0–100.
	 * @param string     $match_type  exact|exact_global|fuzzy.
	 * @param float|null $similarity  Raw similarity when fuzzy.
	 * @return array<string, mixed>
	 */
	private function match_payload(
		object $row,
		float $confidence,
		string $match_type,
		?float $similarity = null
	): array {
		return array(
			'tm_id'          => (int) $row->tm_id,
			'source_lang_id' => (int) $row->source_lang_id,
			'target_lang_id' => (int) $row->target_lang_id,
			'source_hash'    => (string) $row->source_hash,
			'source_text'    => (string) $row->source_text,
			'target_text'    => (string) $row->target_text,
			'text_format'    => (string) $row->text_format,
			'context'        => (string) $row->context,
			'origin'         => (string) $row->origin,
			'quality'        => (string) $row->quality,
			'use_count'      => (int) $row->use_count,
			'confidence'     => $confidence,
			'match_type'     => $match_type,
			'similarity'     => $similarity,
		);
	}
}
