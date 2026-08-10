<?php
/**
 * Generation-path TM outcome codes and payload (TI.3).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Memory;

/**
 * Machine-readable TM generation outcome — no full text bodies.
 */
final class TMGenerationOutcome {

	public const NO_MATCH            = 'tm_no_match';
	public const EXACT_MATCH         = 'tm_exact_match';
	public const EXACT_GLOBAL        = 'tm_exact_global';
	public const AMBIGUOUS           = 'tm_ambiguous';
	public const INELIGIBLE          = 'tm_ineligible';
	public const DIRECT_REUSE        = 'tm_direct_reuse';
	public const CONTEXT_SUPPLIED    = 'tm_context_supplied';
	public const REJECTED_STRUCTURAL = 'tm_rejected_structural';
	public const GLOSSARY_BLOCKED    = 'tm_glossary_blocked';
	public const DOMAIN_DENIED       = 'tm_domain_denied';

	/**
	 * Constructs an outcome.
	 *
	 * @param string                     $code       Outcome code.
	 * @param array<string, mixed>       $diagnostics Bounded diagnostics (ids/codes only).
	 * @param array<string, mixed>|null  $candidate Eligible exact candidate payload or null.
	 * @param list<array<string, mixed>> $examples Relevance-gated example payloads.
	 */
	public function __construct(
		public readonly string $code,
		public readonly array $diagnostics = array(),
		public readonly ?array $candidate = null,
		public readonly array $examples = array(),
	) {
	}

	/**
	 * Whether this outcome carries a TM8 direct-reuse candidate.
	 */
	public function has_direct_candidate(): bool {
		return null !== $this->candidate
			&& in_array(
				$this->code,
				array( self::EXACT_MATCH, self::EXACT_GLOBAL ),
				true
			);
	}
}
