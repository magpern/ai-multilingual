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
	 * Builds the orchestrator.
	 *
	 * @param array<int, SuggestionProvider> $providers Suggestion providers.
	 */
	public function __construct( array $providers ) {
		$this->providers = array_values( $providers );
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
				continue;
			}

			foreach ( $provider->get_suggestions( $segment_dto, $context ) as $suggestion ) {
				if ( ! $suggestion instanceof NormalizedSuggestion ) {
					continue;
				}
				$candidates[] = $suggestion;
			}
		}

		return array_map(
			static fn( NormalizedSuggestion $s ): array => $s->to_array(),
			$this->rank( $candidates )
		);
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
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $candidate;
		}

		return $out;
	}
}
