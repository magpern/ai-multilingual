<?php
/**
 * Auto-translate boundary for the translator workspace.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

use AIMultilingual\Language\Languages;
use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Translation\AI\AIProviderInterface;
use AIMultilingual\Translation\AI\PromptProfileRegistry;
use AIMultilingual\Translation\AI\ProviderResult;
use AIMultilingual\Translation\AI\ProviderSegment;
use AIMultilingual\Translation\AI\ResponseValidator;
use AIMultilingual\Translation\AI\SegmentConstraintAnalyzer;
use AIMultilingual\Translation\AI\TranslationBatch;
use AIMultilingual\Translation\AI\TranslationContextBuilder;
use AIMultilingual\Translation\Memory\TMGenerationLookup;
use AIMultilingual\Translation\Memory\TMGenerationOutcome;
use AIMultilingual\Translation\Memory\TranslationMemoryService;
use AIMultilingual\Translation\QA\ScaffoldingMarkerSource;
use AIMultilingual\Translation\Store;
use WP_Error;
use WP_Post;

/**
 * Single entry point for workspace auto-translate.
 *
 * TI.3: optional TM eligibility / exact reuse / relevance-gated examples
 * live here before translate_batch. Sync and Jobs share this path.
 */
final class TranslationService {

	/**
	 * Structural-fail disposition after an otherwise eligible TM hit fails TI.1.
	 *
	 * Evidence-gated selection (TI3.3): AI fallthrough once.
	 * Invalid TM text never persists; TI.1 never bypassed; no retry loop.
	 */
	public const STRUCTURAL_FAIL_DISPOSITION = 'ai_fallthrough_once';

	/**
	 * Injected dependency.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Injected dependency.
	 *
	 * @var SegmentAssembler
	 */
	private SegmentAssembler $assembler;

	/**
	 * Injected dependency.
	 *
	 * @var Languages
	 */
	private Languages $languages;

	/**
	 * Injected dependency.
	 *
	 * @var AIProviderInterface
	 */
	private AIProviderInterface $provider;

	/**
	 * Prompt profiles.
	 *
	 * @var PromptProfileRegistry
	 */
	private PromptProfileRegistry $profiles;

	/**
	 * Structural response validator.
	 *
	 * @var ResponseValidator
	 */
	private ResponseValidator $validator;

	/**
	 * Optional glossary service for AI fragment injection.
	 *
	 * @var GlossaryService|null
	 */
	private ?GlossaryService $glossary;

	/**
	 * Bounded translation context builder (TI.2).
	 *
	 * @var TranslationContextBuilder
	 */
	private TranslationContextBuilder $context_builder;

	/**
	 * Optional generation-path TM lookup (TI.3).
	 *
	 * @var TMGenerationLookup|null
	 */
	private ?TMGenerationLookup $tm_lookup;

	/**
	 * Optional TM memory service for usage recording.
	 *
	 * @var TranslationMemoryService|null
	 */
	private ?TranslationMemoryService $tm_memory;

	/**
	 * Last TM outcome for diagnostics/tests (no full text bodies).
	 *
	 * @var TMGenerationOutcome|null
	 */
	private ?TMGenerationOutcome $last_tm_outcome = null;

	/**
	 * Builds the collaborator.
	 *
	 * @param Store                          $store            Segment store.
	 * @param SegmentAssembler               $assembler        Segment assembler.
	 * @param Languages                      $languages        Language registry.
	 * @param AIProviderInterface            $provider         AI provider boundary.
	 * @param PromptProfileRegistry|null     $profiles         Prompt profiles.
	 * @param ResponseValidator|null         $validator        Response validator.
	 * @param GlossaryService|null           $glossary         Glossary service for fragments.
	 * @param TranslationContextBuilder|null $context_builder Context builder.
	 * @param TMGenerationLookup|null        $tm_lookup        Generation-path TM lookup.
	 * @param TranslationMemoryService|null  $tm_memory        TM memory for usage.
	 */
	public function __construct(
		Store $store,
		SegmentAssembler $assembler,
		Languages $languages,
		AIProviderInterface $provider,
		?PromptProfileRegistry $profiles = null,
		?ResponseValidator $validator = null,
		?GlossaryService $glossary = null,
		?TranslationContextBuilder $context_builder = null,
		?TMGenerationLookup $tm_lookup = null,
		?TranslationMemoryService $tm_memory = null
	) {
		$this->store           = $store;
		$this->assembler       = $assembler;
		$this->languages       = $languages;
		$this->provider        = $provider;
		$this->profiles        = $profiles ?? new PromptProfileRegistry();
		$this->validator       = $validator ?? new ResponseValidator( new SegmentConstraintAnalyzer() );
		$this->glossary        = $glossary;
		$this->context_builder = $context_builder ?? new TranslationContextBuilder();
		$this->tm_lookup       = $tm_lookup;
		$this->tm_memory       = $tm_memory;
	}

	/**
	 * Returns the configured provider for tests and future wiring.
	 */
	public function provider(): AIProviderInterface {
		return $this->provider;
	}

	/**
	 * Last TM generation outcome (tests / diagnostics).
	 */
	public function last_tm_outcome(): ?TMGenerationOutcome {
		return $this->last_tm_outcome;
	}

	/**
	 * Translates one segment synchronously when a provider is configured.
	 *
	 * @param WP_Post $post        Canonical post.
	 * @param int     $language_id Target language id.
	 * @param string  $segment_key Segment key.
	 * @return array<string, mixed>|WP_Error Updated segment DTO or error.
	 */
	public function translate_segment( WP_Post $post, int $language_id, string $segment_key ) {
		$this->last_tm_outcome = null;

		$current = $this->assembler->assemble_one( $post, $language_id, $segment_key );
		if ( null === $current ) {
			return new WP_Error(
				'aiml_invalid_segment',
				__( 'Unknown segment key for this post.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		if ( ! (bool) ( $current['can_edit'] ?? false ) ) {
			return new WP_Error(
				'aiml_invalid_segment',
				__( 'This segment is not editable.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		$target = $this->languages->find( $language_id );
		$source = $this->languages->default();
		if ( null === $target || null === $source ) {
			return new WP_Error(
				'aiml_invalid_language',
				__( 'Unknown language code.', 'ai-multilingual' ),
				array( 'status' => 404 )
			);
		}

		$source_text = (string) ( $current['source_text'] ?? '' );
		$format      = (string) ( $current['text_format'] ?? Store::FORMAT_PLAIN );
		$context     = TranslationMemoryService::derive_context(
			(string) ( $current['block_name'] ?? '' ),
			(string) ( $current['field_key'] ?? '' )
		);

		$tm_outcome = null;
		$examples   = array();
		if ( null !== $this->tm_lookup ) {
			$tm_outcome            = $this->tm_lookup->evaluate(
				$source_text,
				(int) $source->language_id,
				$language_id,
				$context,
				$format,
				(string) $post->post_type
			);
			$this->last_tm_outcome = $tm_outcome;
			$examples              = $tm_outcome->examples;

			if ( $tm_outcome->has_direct_candidate() && null !== $tm_outcome->candidate ) {
				$tm_target  = (string) ( $tm_outcome->candidate['target_text'] ?? '' );
				$validation = $this->validator->validate(
					$source_text,
					$tm_target,
					$format,
					$this->validator->persist_constraints( $source_text, $format )
				);

				if ( $validation->valid ) {
					$persisted = $this->persist_validated_text(
						$post,
						$language_id,
						$current,
						$tm_target
					);
					if ( ! ( $persisted instanceof WP_Error ) ) {
						$this->tm_lookup->record_direct_reuse();
						$tm_id = (int) ( $tm_outcome->candidate['tm_id'] ?? 0 );
						if ( null !== $this->tm_memory && $tm_id > 0 ) {
							$this->tm_memory->record_usage( $tm_id );
						}
						$this->last_tm_outcome = new TMGenerationOutcome(
							TMGenerationOutcome::DIRECT_REUSE,
							array_merge(
								$tm_outcome->diagnostics,
								array(
									'tm_id'      => $tm_id,
									'match_type' => (string) ( $tm_outcome->candidate['match_type'] ?? '' ),
								)
							),
							$tm_outcome->candidate
						);

						return $persisted;
					}

					return $persisted;
				}

				// Structural-fail disposition: AI fallthrough once (evidence-gated).
				$this->tm_lookup->record_structural_reject();
				$examples              = $this->tm_lookup->examples_for_blocked_candidate(
					$source_text,
					(int) $source->language_id,
					$language_id,
					$context,
					$format,
					$tm_outcome->candidate
				);
				$this->last_tm_outcome = new TMGenerationOutcome(
					TMGenerationOutcome::REJECTED_STRUCTURAL,
					array_merge(
						$tm_outcome->diagnostics,
						array(
							'disposition'    => self::STRUCTURAL_FAIL_DISPOSITION,
							'validator_code' => (string) ( $validation->code ?? '' ),
							'tm_id'          => (int) ( $tm_outcome->candidate['tm_id'] ?? 0 ),
							'example_count'  => count( $examples ),
						)
					),
					null,
					$examples
				);
				// Fall through to AI exactly once — do not re-enter TM8.
			}
		}

		$built_context = $this->context_builder->build_for_post(
			$post,
			$current,
			$this->languages,
			(string) $source->locale,
			(string) $target->locale
		);
		if ( array() !== $examples ) {
			$built_context = $this->context_builder->with_tm_examples( $built_context, $examples );
			if (
				null !== $this->last_tm_outcome
				&& TMGenerationOutcome::REJECTED_STRUCTURAL !== $this->last_tm_outcome->code
				&& TMGenerationOutcome::CONTEXT_SUPPLIED !== $this->last_tm_outcome->code
				&& array() !== $examples
			) {
				$this->last_tm_outcome = new TMGenerationOutcome(
					TMGenerationOutcome::CONTEXT_SUPPLIED,
					array_merge(
						$this->last_tm_outcome->diagnostics,
						array( 'example_count' => count( $examples ) )
					),
					null,
					$examples
				);
			}
		}

		$batch = new TranslationBatch(
			(string) $source->locale,
			(string) $target->locale,
			PromptProfileRegistry::TRANSLATE,
			PromptProfileRegistry::VERSION,
			$this->glossary_fragment_for( $current, (int) $source->language_id, $language_id ),
			array(
				new ProviderSegment(
					$segment_key,
					$source_text,
					$format
				),
			),
			TranslationBatch::OPERATION_TRANSLATE,
			array(),
			$built_context
		);
		$batch = $this->with_scaffolding_markers( $batch );

		$result = $this->provider->translate_batch( $batch );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		return $this->persist_provider_result( $post, $language_id, $current, $result );
	}

	/**
	 * Suggests a translation without persisting (F11 suggest mode).
	 *
	 * @param WP_Post $post           Canonical post.
	 * @param int     $language_id    Target language id.
	 * @param string  $segment_key    Segment key.
	 * @param string  $prompt_profile Profile id.
	 * @return SuggestionResult|WP_Error
	 */
	public function suggest_segment( WP_Post $post, int $language_id, string $segment_key, string $prompt_profile ) {
		$profile = $this->profiles->get( $prompt_profile );
		if ( null === $profile ) {
			return new WP_Error(
				'aiml_invalid_profile',
				__( 'Unknown prompt profile.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		if ( ! $this->provider->get_capabilities()->supports_profile( $prompt_profile ) ) {
			return new WP_Error(
				'aiml_provider_unavailable',
				__( 'The active provider does not support this profile.', 'ai-multilingual' ),
				array( 'status' => 503 )
			);
		}

		$current = $this->assembler->assemble_one( $post, $language_id, $segment_key );
		if ( null === $current ) {
			return new WP_Error(
				'aiml_invalid_segment',
				__( 'Unknown segment key for this post.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		$target = $this->languages->find( $language_id );
		$source = $this->languages->default();
		if ( null === $target || null === $source ) {
			return new WP_Error(
				'aiml_invalid_language',
				__( 'Unknown language code.', 'ai-multilingual' ),
				array( 'status' => 404 )
			);
		}

		$source_text = (string) ( $current['source_text'] ?? '' );
		$format      = (string) ( $current['text_format'] ?? Store::FORMAT_PLAIN );
		$existing    = (string) ( $current['translated_text'] ?? '' );

		$batch = new TranslationBatch(
			(string) $source->locale,
			(string) $target->locale,
			$profile->id,
			$profile->version,
			$this->glossary_fragment_for( $current, (int) $source->language_id, $language_id ),
			array(
				new ProviderSegment( $segment_key, $source_text, $format, $existing ),
			),
			TranslationBatch::OPERATION_SUGGEST,
			$profile->constraints,
			$this->context_builder->build_for_post(
				$post,
				$current,
				$this->languages,
				(string) $source->locale,
				(string) $target->locale
			)
		);
		$batch = $this->with_scaffolding_markers( $batch );

		$result = $this->provider->translate_batch( $batch );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$translated = '';
		foreach ( $result->segments as $segment ) {
			if ( (string) ( $segment['segment_key'] ?? '' ) === $segment_key ) {
				$translated = (string) ( $segment['translated_text'] ?? '' );
				break;
			}
		}

		$validation = $this->validator->validate( $source_text, $translated, $format, $profile->constraints );
		if ( ! $validation->valid ) {
			return new WP_Error(
				(string) ( $validation->code ?? ResponseValidator::CODE_EMPTY_TARGET ),
				'' !== $validation->message ? $validation->message : __( 'Provider response failed structural validation.', 'ai-multilingual' ),
				array(
					'status' => 422,
					'data'   => $validation->data,
				)
			);
		}

		return new SuggestionResult(
			$translated,
			$profile->id,
			$profile->version,
			$result->model
		);
	}

	/**
	 * Persists provider output through the Store write path.
	 *
	 * @param WP_Post              $post        Canonical post.
	 * @param int                  $language_id Target language id.
	 * @param array<string, mixed> $current     Current segment DTO.
	 * @param ProviderResult       $result      Provider outcome.
	 * @return array<string, mixed>|WP_Error
	 */
	private function persist_provider_result(
		WP_Post $post,
		int $language_id,
		array $current,
		ProviderResult $result
	) {
		$segment_key = (string) ( $current['segment_key'] ?? '' );
		$source_text = (string) ( $current['source_text'] ?? '' );
		$format      = (string) ( $current['text_format'] ?? Store::FORMAT_PLAIN );

		$matches    = 0;
		$translated = null;

		foreach ( $result->segments as $segment ) {
			if ( (string) ( $segment['segment_key'] ?? '' ) !== $segment_key ) {
				continue;
			}
			++$matches;
			if ( ! array_key_exists( 'translated_text', $segment ) || ! is_string( $segment['translated_text'] ) ) {
				return new WP_Error(
					'aiml_ai_invalid_response',
					__( 'Provider response could not be mapped to the requested segment.', 'ai-multilingual' ),
					array( 'status' => 422 )
				);
			}
			$translated = $segment['translated_text'];
		}

		if ( 0 === $matches || null === $translated ) {
			return new WP_Error(
				'aiml_ai_invalid_response',
				__( 'Provider response is missing the requested segment.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		if ( $matches > 1 ) {
			return new WP_Error(
				'aiml_ai_invalid_response',
				__( 'Provider response contains duplicate segment keys.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		$validation = $this->validator->validate(
			$source_text,
			$translated,
			$format,
			$this->validator->persist_constraints( $source_text, $format )
		);
		if ( ! $validation->valid ) {
			return new WP_Error(
				(string) ( $validation->code ?? ResponseValidator::CODE_EMPTY_TARGET ),
				'' !== $validation->message
					? $validation->message
					: __( 'Provider response failed structural validation.', 'ai-multilingual' ),
				array(
					'status' => 422,
					'data'   => $validation->data,
				)
			);
		}

		return $this->persist_validated_text( $post, $language_id, $current, $translated );
	}

	/**
	 * Persists already-validated target text via Store (shared AI + TM8 path).
	 *
	 * Does not write translations.tm_id (TM21 NARROWED — dormant).
	 *
	 * @param WP_Post              $post        Canonical post.
	 * @param int                  $language_id Target language id.
	 * @param array<string, mixed> $current     Segment DTO.
	 * @param string               $translated  Validated target text.
	 * @return array<string, mixed>|WP_Error
	 */
	private function persist_validated_text(
		WP_Post $post,
		int $language_id,
		array $current,
		string $translated
	) {
		$segment_key = (string) ( $current['segment_key'] ?? '' );
		$format      = (string) ( $current['text_format'] ?? Store::FORMAT_PLAIN );

		$save = $this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => (string) $post->post_type,
				'language_id'     => $language_id,
				'field_key'       => (string) ( $current['field_key'] ?? '' ),
				'segment_key'     => $segment_key,
				'segment_kind'    => (string) ( $current['segment_kind'] ?? Store::KIND_BLOCK ),
				'segment_order'   => (int) ( $current['segment_order'] ?? 0 ),
				'text_format'     => $format,
				'source_text'     => (string) ( $current['source_text'] ?? '' ),
				'translated_text' => $translated,
				'status'          => Store::STATUS_MACHINE_TRANSLATED,
			)
		);

		if ( $save instanceof WP_Error ) {
			return $save;
		}

		$refreshed = $this->assembler->assemble_one( $post, $language_id, $segment_key );
		if ( null === $refreshed ) {
			return new WP_Error(
				'aiml_invalid_segment',
				__( 'Translated segment could not be reloaded.', 'ai-multilingual' ),
				array( 'status' => 500 )
			);
		}

		return $refreshed;
	}

	/**
	 * Build a platform-owned glossary fragment for the AI batch.
	 *
	 * @param array<string, mixed> $current     Assembled segment.
	 * @param int                  $source_lang Source language id.
	 * @param int                  $target_lang Target language id.
	 */
	private function glossary_fragment_for( array $current, int $source_lang, int $target_lang ): string {
		if ( null === $this->glossary ) {
			return '';
		}

		return $this->glossary->build_fragment(
			(string) ( $current['source_text'] ?? '' ),
			$source_lang,
			$target_lang,
			(string) ( $current['text_format'] ?? Store::FORMAT_PLAIN )
		);
	}

	/**
	 * Attaches request-scoped scaffolding markers when the provider exposes them (TI.4).
	 *
	 * Builds markers from a marker-free batch, then recreates the batch with markers.
	 *
	 * @param TranslationBatch $batch Batch without scaffolding markers.
	 */
	private function with_scaffolding_markers( TranslationBatch $batch ): TranslationBatch {
		if ( ! $this->provider instanceof ScaffoldingMarkerSource ) {
			return $batch;
		}

		$markers = $this->provider->scaffolding_markers_for_batch( $batch );
		if ( array() === $markers ) {
			return $batch;
		}

		return new TranslationBatch(
			$batch->source_locale,
			$batch->target_locale,
			$batch->prompt_profile,
			$batch->prompt_version,
			$batch->glossary_fragment,
			$batch->segments,
			$batch->operation,
			$batch->constraints,
			$batch->context,
			$markers
		);
	}
}
