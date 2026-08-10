<?php
/**
 * Bounded generation-path TM lookup adapter (TI.3).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Memory;

use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Translation\Store;

/**
 * Thin adapter over TranslationMemoryService for TranslationService.
 *
 * Not a second translator. No fuzzy/vector on the auto path.
 */
final class TMGenerationLookup {

	public const MAX_EXAMPLES      = 3;
	public const MAX_EXAMPLE_CHARS = 400;

	/**
	 * Counters for TM effectiveness metrics (not quality scores).
	 *
	 * @var array<string, int>
	 */
	private array $metrics = array(
		'lookup_attempts'     => 0,
		'exact_eligible'      => 0,
		'no_match'            => 0,
		'ambiguous'           => 0,
		'ineligible'          => 0,
		'direct_reuse'        => 0,
		'assisted_examples'   => 0,
		'glossary_blocked'    => 0,
		'domain_denied'       => 0,
		'rejected_structural' => 0,
	);

	/**
	 * Wire memory, eligibility policy, and optional glossary.
	 *
	 * @param TranslationMemoryService $memory   Existing TM service.
	 * @param TMEligibilityPolicy      $policy   Eligibility policy.
	 * @param GlossaryService|null     $glossary Optional glossary.
	 */
	public function __construct(
		private readonly TranslationMemoryService $memory,
		private readonly TMEligibilityPolicy $policy,
		private readonly ?GlossaryService $glossary = null,
	) {
	}

	/**
	 * Metrics snapshot (copy).
	 *
	 * @return array<string, int>
	 */
	public function metrics(): array {
		return $this->metrics;
	}

	/**
	 * Reset metrics (tests).
	 */
	public function reset_metrics(): void {
		foreach ( array_keys( $this->metrics ) as $key ) {
			$this->metrics[ $key ] = 0;
		}
	}

	/**
	 * Evaluate TM for one generation request.
	 *
	 * @param string $source_text     Source text.
	 * @param int    $source_lang_id  Source language id.
	 * @param int    $target_lang_id  Target language id.
	 * @param string $context         derive_context result.
	 * @param string $text_format     Format.
	 * @param string $source_subtype  Requesting post_type / subtype.
	 */
	public function evaluate(
		string $source_text,
		int $source_lang_id,
		int $target_lang_id,
		string $context,
		string $text_format,
		string $source_subtype
	): TMGenerationOutcome {
		++$this->metrics['lookup_attempts'];

		if ( ! TMDomainAllowlist::is_eligible( $source_subtype ) ) {
			++$this->metrics['domain_denied'];
			++$this->metrics['ineligible'];

			return new TMGenerationOutcome(
				TMGenerationOutcome::DOMAIN_DENIED,
				array( 'source_subtype' => $source_subtype )
			);
		}

		$exact = $this->memory->lookup_exact(
			$source_text,
			$source_lang_id,
			$target_lang_id,
			$context,
			$text_format
		);

		if ( null === $exact ) {
			++$this->metrics['no_match'];
			$examples = $this->collect_examples(
				$source_text,
				$source_lang_id,
				$target_lang_id,
				$context,
				$text_format,
				null
			);
			if ( array() !== $examples ) {
				++$this->metrics['assisted_examples'];

				return new TMGenerationOutcome(
					TMGenerationOutcome::CONTEXT_SUPPLIED,
					array( 'example_count' => count( $examples ) ),
					null,
					$examples
				);
			}

			return new TMGenerationOutcome( TMGenerationOutcome::NO_MATCH );
		}

		$gate = $this->policy->evaluate_candidate(
			$exact,
			$source_text,
			$text_format,
			$source_lang_id,
			$target_lang_id
		);

		if ( ! $gate['ok'] ) {
			$code = (string) $gate['code'];
			if ( TMGenerationOutcome::GLOSSARY_BLOCKED === $code ) {
				++$this->metrics['glossary_blocked'];
			}
			++$this->metrics['ineligible'];

			$examples = $this->collect_examples(
				$source_text,
				$source_lang_id,
				$target_lang_id,
				$context,
				$text_format,
				$exact
			);
			if ( array() !== $examples ) {
				++$this->metrics['assisted_examples'];

				return new TMGenerationOutcome(
					$code,
					$gate['diagnostics'],
					null,
					$examples
				);
			}

			return new TMGenerationOutcome( $code, $gate['diagnostics'] );
		}

		$match_type = (string) ( $exact['match_type'] ?? 'exact' );
		$code       = 'exact_global' === $match_type
			? TMGenerationOutcome::EXACT_GLOBAL
			: TMGenerationOutcome::EXACT_MATCH;

		++$this->metrics['exact_eligible'];

		return new TMGenerationOutcome(
			$code,
			array_merge(
				$gate['diagnostics'],
				array( 'match_type' => $match_type )
			),
			$exact
		);
	}

	/**
	 * Mark a structural reject after TI.1 failure (metrics only).
	 */
	public function record_structural_reject(): void {
		++$this->metrics['rejected_structural'];
	}

	/**
	 * Relevance-gated examples for a candidate that failed TM8 (e.g. structural).
	 *
	 * Plan §10.1 class 1: exact-hash/exact-context may still assist AI when
	 * TM8 was not taken for structural-ineligible (or similar) reasons.
	 *
	 * @param string               $source_text     Source text.
	 * @param int                  $source_lang_id  Source language id.
	 * @param int                  $target_lang_id  Target language id.
	 * @param string               $context         derive_context result.
	 * @param string               $text_format     Format.
	 * @param array<string, mixed> $blocked         Rejected exact candidate payload.
	 * @return list<array<string, mixed>>
	 */
	public function examples_for_blocked_candidate(
		string $source_text,
		int $source_lang_id,
		int $target_lang_id,
		string $context,
		string $text_format,
		array $blocked
	): array {
		$examples = $this->collect_examples(
			$source_text,
			$source_lang_id,
			$target_lang_id,
			$context,
			$text_format,
			$blocked
		);
		if ( array() !== $examples ) {
			++$this->metrics['assisted_examples'];
		}

		return $examples;
	}

	/**
	 * Mark a successful direct reuse (metrics only).
	 */
	public function record_direct_reuse(): void {
		++$this->metrics['direct_reuse'];
	}

	/**
	 * Collect relevance-gated examples (closed freeze set).
	 *
	 * Same language-pair + human_approved alone is insufficient — every
	 * example must share exact source_hash and an admitted context class.
	 *
	 * @param string                    $source_text    Source.
	 * @param int                       $source_lang_id Source lang.
	 * @param int                       $target_lang_id Target lang.
	 * @param string                    $context        Request context.
	 * @param string                    $text_format    Format.
	 * @param array<string, mixed>|null $blocked        Blocked exact payload to prefer as example.
	 * @return list<array<string, mixed>>
	 */
	private function collect_examples(
		string $source_text,
		int $source_lang_id,
		int $target_lang_id,
		string $context,
		string $text_format,
		?array $blocked
	): array {
		$examples = array();
		$hash     = Store::source_hash( $source_text, $text_format );

		$candidates = array();
		if ( null !== $blocked ) {
			$candidates[] = $blocked;
		}

		// Exact-hash exact-context (may already be in blocked).
		$exact_ctx = $this->memory->repository()->find_by_identity(
			$hash,
			$source_lang_id,
			$target_lang_id,
			$context
		);
		if ( null !== $exact_ctx ) {
			$candidates[] = $this->row_to_example_payload( $exact_ctx, 'exact' );
		}

		// Exact-hash empty-context when ambiguity gate passes.
		if ( '' !== $context && TranslationMemoryService::passes_ambiguity_gate( $source_text ) ) {
			$global = $this->memory->repository()->find_by_identity(
				$hash,
				$source_lang_id,
				$target_lang_id,
				''
			);
			if ( null !== $global ) {
				$candidates[] = $this->row_to_example_payload( $global, 'exact_global' );
			}
		}

		$seen = array();
		foreach ( $candidates as $row ) {
			$tm_id = (int) ( $row['tm_id'] ?? 0 );
			if ( $tm_id > 0 && isset( $seen[ $tm_id ] ) ) {
				continue;
			}
			if ( $tm_id > 0 ) {
				$seen[ $tm_id ] = true;
			}

			if ( TMRepository::QUALITY_HUMAN_APPROVED !== (string) ( $row['quality'] ?? '' ) ) {
				continue;
			}
			if ( Store::NORM_VERSION !== (int) ( $row['norm_version'] ?? 0 ) ) {
				continue;
			}
			if ( (string) ( $row['source_hash'] ?? '' ) !== $hash ) {
				continue;
			}

			// Closed relevance: only exact-hash + exact/empty/same derive_context key.
			$row_context = (string) ( $row['context'] ?? '' );
			if ( $row_context !== $context && '' !== $row_context ) {
				continue;
			}
			if ( '' === $row_context && '' !== $context && ! TranslationMemoryService::passes_ambiguity_gate( $source_text ) ) {
				continue;
			}

			$target = $this->cap_example( (string) ( $row['target_text'] ?? '' ) );
			$source = $this->cap_example( (string) ( $row['source_text'] ?? $source_text ) );
			if ( '' === $target || '' === $source ) {
				continue;
			}

			$examples[] = array(
				'tm_id'       => $tm_id,
				'source_text' => $source,
				'target_text' => $target,
				'context'     => $row_context,
				'match_type'  => (string) ( $row['match_type'] ?? 'exact' ),
			);

			if ( count( $examples ) >= self::MAX_EXAMPLES ) {
				break;
			}
		}

		return $examples;
	}

	/**
	 * Normalize a TM DB row into an example payload.
	 *
	 * @param object $row        DB row.
	 * @param string $match_type Match type label.
	 * @return array<string, mixed>
	 */
	private function row_to_example_payload( object $row, string $match_type ): array {
		return array(
			'tm_id'            => (int) ( $row->tm_id ?? 0 ),
			'source_hash'      => (string) ( $row->source_hash ?? '' ),
			'source_text'      => (string) ( $row->source_text ?? '' ),
			'target_text'      => (string) ( $row->target_text ?? '' ),
			'context'          => (string) ( $row->context ?? '' ),
			'quality'          => (string) ( $row->quality ?? '' ),
			'norm_version'     => (int) ( $row->norm_version ?? 0 ),
			'text_format'      => (string) ( $row->text_format ?? Store::FORMAT_PLAIN ),
			'glossary_version' => (int) ( $row->glossary_version ?? 0 ),
			'match_type'       => $match_type,
			'use_count'        => (int) ( $row->use_count ?? 0 ),
		);
	}

	/**
	 * Cap example text at MAX_EXAMPLE_CHARS.
	 *
	 * @param string $text Text.
	 */
	private function cap_example( string $text ): string {
		if ( strlen( $text ) <= self::MAX_EXAMPLE_CHARS ) {
			return $text;
		}

		return substr( $text, 0, self::MAX_EXAMPLE_CHARS );
	}
}
