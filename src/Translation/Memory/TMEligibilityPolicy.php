<?php
/**
 * TM generation-path eligibility policy (TI.3).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Memory;

use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Translation\Store;

/**
 * Authority/eligibility gates over exact `aiml_tm` candidates.
 *
 * Does not perform lookups. Does not invent glossary modes.
 */
final class TMEligibilityPolicy {

	/**
	 * Allowed text formats for automatic TM reuse.
	 *
	 * @var list<string>
	 */
	private const ALLOWED_FORMATS = array(
		Store::FORMAT_PLAIN,
		Store::FORMAT_HTML,
	);

	/**
	 * Optional glossary for version-aware TM safety.
	 *
	 * @var GlossaryService|null
	 */
	private ?GlossaryService $glossary;

	/**
	 * @param GlossaryService|null $glossary Glossary service (optional).
	 */
	public function __construct( ?GlossaryService $glossary = null ) {
		$this->glossary = $glossary;
	}

	/**
	 * Whether a raw lookup payload may become a TM8 candidate.
	 *
	 * @param array<string, mixed> $match        Lookup payload from TranslationMemoryService.
	 * @param string               $source_text  Current source text.
	 * @param string               $text_format  Current format.
	 * @param int                  $source_lang  Source language id.
	 * @param int                  $target_lang  Target language id.
	 * @return array{ok:bool,code:string,diagnostics:array<string,mixed>}
	 */
	public function evaluate_candidate(
		array $match,
		string $source_text,
		string $text_format,
		int $source_lang,
		int $target_lang
	): array {
		$quality = (string) ( $match['quality'] ?? '' );
		if ( TMRepository::QUALITY_HUMAN_APPROVED !== $quality ) {
			return $this->reject( TMGenerationOutcome::INELIGIBLE, array( 'reason' => 'quality' ) );
		}

		$format = (string) ( $match['text_format'] ?? $text_format );
		if ( ! in_array( $format, self::ALLOWED_FORMATS, true ) ) {
			return $this->reject( TMGenerationOutcome::INELIGIBLE, array( 'reason' => 'format' ) );
		}

		$norm = (int) ( $match['norm_version'] ?? 0 );
		if ( $norm !== Store::NORM_VERSION ) {
			return $this->reject( TMGenerationOutcome::INELIGIBLE, array( 'reason' => 'norm_version' ) );
		}

		$hash = (string) ( $match['source_hash'] ?? '' );
		if ( '' === $hash || $hash !== Store::source_hash( $source_text, $text_format ) ) {
			return $this->reject( TMGenerationOutcome::INELIGIBLE, array( 'reason' => 'source_hash' ) );
		}

		$target = (string) ( $match['target_text'] ?? '' );
		if ( '' === trim( $target ) ) {
			return $this->reject( TMGenerationOutcome::INELIGIBLE, array( 'reason' => 'empty_target' ) );
		}

		$glossary_gate = $this->glossary_compatibility(
			$match,
			$source_text,
			$text_format,
			$source_lang,
			$target_lang
		);
		if ( ! $glossary_gate['ok'] ) {
			return $glossary_gate;
		}

		return array(
			'ok'          => true,
			'code'        => TMGenerationOutcome::EXACT_MATCH,
			'diagnostics' => array(
				'tm_id'      => (int) ( $match['tm_id'] ?? 0 ),
				'match_type' => (string) ( $match['match_type'] ?? 'exact' ),
			),
		);
	}

	/**
	 * Glossary-version-aware skip using existing lexicon contracts only.
	 *
	 * @param array<string, mixed> $match       Candidate.
	 * @param string               $source_text Source.
	 * @param string               $text_format Format.
	 * @param int                  $source_lang Source lang.
	 * @param int                  $target_lang Target lang.
	 * @return array{ok:bool,code:string,diagnostics:array<string,mixed>}
	 */
	private function glossary_compatibility(
		array $match,
		string $source_text,
		string $text_format,
		int $source_lang,
		int $target_lang
	): array {
		if ( null === $this->glossary ) {
			return array(
				'ok'          => true,
				'code'        => TMGenerationOutcome::EXACT_MATCH,
				'diagnostics' => array(),
			);
		}

		$current = $this->glossary->current_version();
		$stamped = (int) ( $match['glossary_version'] ?? 0 );
		if ( $stamped >= $current ) {
			return array(
				'ok'          => true,
				'code'        => TMGenerationOutcome::EXACT_MATCH,
				'diagnostics' => array( 'glossary_version' => $stamped ),
			);
		}

		// Behind stamp: skip only when source hits current lexicon terms (ADR-0009 selective check).
		$hits = $this->glossary->match_terms( $source_text, $source_lang, $target_lang, $text_format );
		if ( array() !== $hits ) {
			return $this->reject(
				TMGenerationOutcome::GLOSSARY_BLOCKED,
				array(
					'reason'                 => 'glossary_version_behind_with_hits',
					'glossary_version'       => $stamped,
					'glossary_version_current'=> $current,
					'hit_count'              => count( $hits ),
				)
			);
		}

		return array(
			'ok'          => true,
			'code'        => TMGenerationOutcome::EXACT_MATCH,
			'diagnostics' => array(
				'glossary_version'         => $stamped,
				'glossary_version_current' => $current,
			),
		);
	}

	/**
	 * @param string               $code        Outcome code.
	 * @param array<string, mixed> $diagnostics Diagnostics.
	 * @return array{ok:bool,code:string,diagnostics:array<string,mixed>}
	 */
	private function reject( string $code, array $diagnostics ): array {
		return array(
			'ok'          => false,
			'code'        => $code,
			'diagnostics' => $diagnostics,
		);
	}
}
