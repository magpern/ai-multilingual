<?php
/**
 * Suggestion orchestration (ADR-F11-002 / ADR-F11-006).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

use AIMultilingual\Workspace\Suggestion\NormalizedSuggestion;
use AIMultilingual\Workspace\Suggestion\SuggestionProvider;

/**
 * Coordinates SuggestionProvider instances; never persists translations.
 */
final class TranslationSuggestionService {

	/**
	 * Registered providers in invocation order.
	 *
	 * @var list<SuggestionProvider>
	 */
	private array $providers;

	/**
	 * Internal rejection reason counts (§2.7.1) — not exposed in F11 REST.
	 *
	 * @var array<string, int>
	 */
	private array $rejection_counts = array();

	/**
	 * Internal empty-result diagnostic counts (§2.7.2).
	 *
	 * @var array<string, int>
	 */
	private array $empty_counts = array();

	/**
	 * Builds the orchestrator.
	 *
	 * @param array<int, SuggestionProvider> $providers Suggestion providers.
	 */
	public function __construct( array $providers ) {
		$this->providers = array_values( $providers );
	}

	/**
	 * Internal rejection diagnostics (tests / future UI).
	 *
	 * @return array<string, int>
	 */
	public function rejection_diagnostics(): array {
		return $this->rejection_counts;
	}

	/**
	 * Internal empty-result diagnostics (tests / future UI).
	 *
	 * @return array<string, int>
	 */
	public function empty_diagnostics(): array {
		return $this->empty_counts;
	}

	/**
	 * Returns ranked suggestions for one segment.
	 *
	 * @param array<string, mixed> $segment_dto Assembled segment DTO.
	 * @param array<string, mixed> $context     Request context including language ids.
	 * @return list<array<string, mixed>>
	 */
	public function suggestions_for_segment( array $segment_dto, array $context ): array {
		$candidates = array();

		foreach ( $this->providers as $provider ) {
			if ( ! $provider->is_available( $segment_dto, $context ) ) {
				$reason = $provider->get_unavailable_reason() ?? 'provider_unavailable';
				$this->bump_rejection( $reason );
				continue;
			}

			foreach ( $provider->get_suggestions( $segment_dto, $context ) as $suggestion ) {
				if ( ! $suggestion instanceof NormalizedSuggestion ) {
					continue;
				}
				$candidates[] = $suggestion;
			}
		}

		$ranked = $this->rank( $candidates );
		if ( array() === $ranked ) {
			$code = isset( $context['prompt_profile'] ) && '' !== (string) $context['prompt_profile']
				? 'provider_unavailable'
				: 'no_tm_match';
			$this->bump_empty( $code );
		}

		return array_map(
			static fn( NormalizedSuggestion $s ): array => $s->to_array(),
			$ranked
		);
	}

	/**
	 * On-demand AI (or profile-scoped) suggestion request — merges with TM via ranking.
	 *
	 * @param array<string, mixed> $segment_dto Assembled segment DTO.
	 * @param array<string, mixed> $context     Must include prompt_profile and post.
	 * @return list<array<string, mixed>>
	 */
	public function request_suggestions( array $segment_dto, array $context ): array {
		return $this->suggestions_for_segment( $segment_dto, $context );
	}

	/**
	 * Returns ranked suggestions keyed by segment_key for a batch.
	 *
	 * @param list<array<string, mixed>> $segments Assembled segment DTOs.
	 * @param array<string, mixed>       $context  Shared context.
	 * @return array<string, list<array<string, mixed>>>
	 */
	public function suggestions_for_batch( array $segments, array $context ): array {
		$out = array();

		foreach ( $segments as $segment ) {
			$key         = (string) ( $segment['segment_key'] ?? '' );
			$out[ $key ] = $this->suggestions_for_segment( $segment, $context );
		}

		return $out;
	}

	/**
	 * Deterministic ranking: tier asc, confidence desc, text asc, provider_id asc.
	 *
	 * Deduplicates identical target_text keeping the higher-ranked candidate.
	 *
	 * @param array<int, NormalizedSuggestion> $candidates Raw provider output.
	 * @return list<NormalizedSuggestion>
	 */
	private function rank( array $candidates ): array {
		usort(
			$candidates,
			static function ( NormalizedSuggestion $a, NormalizedSuggestion $b ): int {
				$tier = $a->rank_tier <=> $b->rank_tier;
				if ( 0 !== $tier ) {
					return $tier;
				}

				$conf = $b->confidence <=> $a->confidence;
				if ( 0 !== $conf ) {
					return $conf;
				}

				$text = strcmp( $a->target_text, $b->target_text );
				if ( 0 !== $text ) {
					return $text;
				}

				return strcmp( $a->provider_id, $b->provider_id );
			}
		);

		$seen = array();
		$out  = array();
		foreach ( $candidates as $candidate ) {
			$key = $candidate->target_text;
			if ( isset( $seen[ $key ] ) ) {
				$this->bump_rejection( 'duplicate_suggestion' );
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $candidate;
		}

		return $out;
	}

	/**
	 * Increments an internal rejection counter.
	 *
	 * @param string $code Reason code.
	 */
	private function bump_rejection( string $code ): void {
		$this->rejection_counts[ $code ] = ( $this->rejection_counts[ $code ] ?? 0 ) + 1;
	}

	/**
	 * Increments an internal empty-result counter.
	 *
	 * @param string $code Diagnostic code.
	 */
	private function bump_empty( string $code ): void {
		$this->empty_counts[ $code ] = ( $this->empty_counts[ $code ] ?? 0 ) + 1;
	}
}
