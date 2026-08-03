<?php
/**
 * AI-backed suggestion provider (ADR-F11-005).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Suggestion;

use AIMultilingual\Workspace\TranslationService;

/**
 * Maps TranslationService suggest mode to NormalizedSuggestion (tier 6).
 */
final class AISuggestionProvider implements SuggestionProvider {

	public const ID = 'ai';

	public const TIER_AI = 6;

	/**
	 * Injected dependency.
	 *
	 * @var TranslationService
	 */
	private TranslationService $translation;

	/**
	 * Last unavailable reason.
	 *
	 * @var string|null
	 */
	private ?string $unavailable_reason = null;

	/**
	 * Builds the provider.
	 *
	 * @param TranslationService $translation Translation service (suggest mode).
	 */
	public function __construct( TranslationService $translation ) {
		$this->translation = $translation;
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
	 * @param array<string, mixed> $context     Must include prompt_profile for on-demand AI.
	 */
	public function is_available( array $segment_dto, array $context ): bool {
		$this->unavailable_reason = null;

		$profile = (string) ( $context['prompt_profile'] ?? '' );
		if ( '' === $profile ) {
			$this->unavailable_reason = 'disabled_by_policy';
			return false;
		}

		$caps = $this->translation->provider()->get_capabilities();
		if ( ! $caps->supports_profile( $profile ) ) {
			$this->unavailable_reason = 'provider_unavailable';
			return false;
		}

		if ( empty( $context['post'] ) || empty( $context['target_language_id'] ) ) {
			$this->unavailable_reason = 'provider_unavailable';
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

		$profile = (string) $context['prompt_profile'];
		$post    = $context['post'];
		$lang_id = (int) $context['target_language_id'];
		$key     = (string) ( $segment_dto['segment_key'] ?? '' );

		$result = $this->translation->suggest_segment( $post, $lang_id, $key, $profile );
		if ( $result instanceof \WP_Error ) {
			$this->unavailable_reason = $result->get_error_code();
			return array();
		}

		return array(
			new NormalizedSuggestion(
				self::ID,
				$result->target_text,
				$result->confidence,
				self::TIER_AI,
				array(
					'profile'        => $result->prompt_profile,
					'prompt_version' => $result->prompt_version,
					'model'          => $result->model,
					'match_type'     => 'ai',
				)
			),
		);
	}
}
