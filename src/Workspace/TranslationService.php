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
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\QA\ScaffoldingMarkerSource;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermAdoptionService;
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
	 * Optional TI.7 publication service (auto-publish after persist).
	 *
	 * @var PublicationService|null
	 */
	private ?PublicationService $publication;

	/**
	 * TSC.1 hosted-to-native term adoption (content writes only).
	 *
	 * @var TermAdoptionService|null
	 */
	private ?TermAdoptionService $term_adoption;

	/**
	 * Last TM outcome for diagnostics/tests (no full text bodies).
	 *
	 * @var TMGenerationOutcome|null
	 */
	private ?TMGenerationOutcome $last_tm_outcome = null;

	/**
	 * Last translate_segment attempt usage (bounded, provider-agnostic shape).
	 *
	 * @var array{provider_requests:int,input_tokens:int,output_tokens:int,usage_known:bool,tm_outcome_code:string}|null
	 */
	private ?array $last_attempt_usage = null;

	/**
	 * Last request-scoped scaffolding markers from the active translate attempt (TI.4/TI.7).
	 *
	 * @var list<string>
	 */
	private array $last_scaffolding_markers = array();

	/**
	 * Optional optimistic translation_hash for interactive sync retranslate (OTL.3).
	 *
	 * Null = Jobs / legacy callers — no concurrency guard.
	 *
	 * @var string|null
	 */
	private ?string $expected_translation_hash = null;

	/**
	 * Builds the collaborator.
	 *
	 * @param Store                                                    $store            Segment store.
	 * @param SegmentAssembler                                         $assembler        Segment assembler.
	 * @param Languages                                                $languages        Language registry.
	 * @param AIProviderInterface                                      $provider         AI provider boundary.
	 * @param PromptProfileRegistry|null                               $profiles         Prompt profiles.
	 * @param ResponseValidator|null                                   $validator        Response validator.
	 * @param GlossaryService|null                                     $glossary         Glossary service for fragments.
	 * @param TranslationContextBuilder|null                           $context_builder Context builder.
	 * @param TMGenerationLookup|null                                  $tm_lookup        Generation-path TM lookup.
	 * @param TranslationMemoryService|null                            $tm_memory        TM memory for usage.
	 * @param PublicationService|null                                  $publication      Optional TI.7 publication service.
	 * @param TermAdoptionService|null                                 $term_adoption    Optional TSC.1 term adoption service.
	 * @param \AIMultilingual\Surface\Meta\RegisteredMetaRegistry|null $meta_registry Optional TSC.2 catalog for provider_allowed.
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
		?TranslationMemoryService $tm_memory = null,
		?PublicationService $publication = null,
		?TermAdoptionService $term_adoption = null,
		private ?\AIMultilingual\Surface\Meta\RegisteredMetaRegistry $meta_registry = null,
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
		$this->publication     = $publication;
		$this->term_adoption   = $term_adoption;
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
	 * Last request-scoped scaffolding markers from translate_segment (TI.7 publication).
	 *
	 * @return list<string>
	 */
	public function last_scaffolding_markers(): array {
		return $this->last_scaffolding_markers;
	}

	/**
	 * Last translate_segment attempt usage evidence.
	 *
	 * @return array{provider_requests:int,input_tokens:int,output_tokens:int,usage_known:bool,tm_outcome_code:string}|null
	 */
	public function last_attempt_usage(): ?array {
		return $this->last_attempt_usage;
	}

	/**
	 * Translates one segment synchronously when a provider is configured.
	 *
	 * @param WP_Post     $post                      Canonical post.
	 * @param int         $language_id               Target language id.
	 * @param string      $segment_key               Segment key.
	 * @param bool        $allow_provider            When false, TM/skip-only — do not call the provider.
	 * @param string|null $expected_translation_hash Optional optimistic target hash (OTL.3 interactive sync).
	 * @return array<string, mixed>|WP_Error Updated segment DTO or error.
	 */
	public function translate_segment(
		WP_Post $post,
		int $language_id,
		string $segment_key,
		bool $allow_provider = true,
		?string $expected_translation_hash = null
	) {
		$this->last_tm_outcome           = null;
		$this->last_attempt_usage        = null;
		$this->last_scaffolding_markers  = array();
		$this->expected_translation_hash = $expected_translation_hash;

		$current = $this->assembler->assemble_one( $post, $language_id, $segment_key );
		if ( null === $current ) {
			return new WP_Error(
				'aiml_invalid_segment',
				__( 'Unknown segment key for this post.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		if ( null !== $this->expected_translation_hash ) {
			$assembled_hash = (string) ( $current['translation_hash'] ?? '' );
			if ( $assembled_hash !== $this->expected_translation_hash ) {
				return new WP_Error(
					'aiml_translation_hash_mismatch',
					__( 'The translation changed since this request was started.', 'ai-multilingual' ),
					array( 'status' => 409 )
				);
			}
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
						$this->last_tm_outcome    = new TMGenerationOutcome(
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
						$this->last_attempt_usage = array(
							'provider_requests' => 0,
							'input_tokens'      => 0,
							'output_tokens'     => 0,
							'usage_known'       => true,
							'tm_outcome_code'   => TMGenerationOutcome::DIRECT_REUSE,
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

		if ( ! $allow_provider ) {
			$this->last_attempt_usage = array(
				'provider_requests' => 0,
				'input_tokens'      => 0,
				'output_tokens'     => 0,
				'usage_known'       => true,
				'tm_outcome_code'   => null !== $this->last_tm_outcome ? $this->last_tm_outcome->code : '',
			);

			return new WP_Error(
				'aiml_provider_budget_exhausted',
				__( 'Provider budget exhausted; provider call forbidden for this attempt.', 'ai-multilingual' ),
				array(
					'status'                => 409,
					'provider_request_made' => false,
					'provider_requests'     => 0,
				)
			);
		}

		$meta_provider_block = $this->registered_meta_provider_block( Store::SOURCE_POST, $segment_key );
		if ( $meta_provider_block instanceof WP_Error ) {
			return $meta_provider_block;
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
			$error_data = $result->get_error_data();
			if ( is_array( $error_data ) && ! empty( $error_data['provider_request_made'] ) ) {
				// Prefer explicit provider_requests when present; default to 1 only when
				// the key is absent. Never coerce an explicit 0 up to 1.
				$requests                 = array_key_exists( 'provider_requests', $error_data )
					? max( 0, (int) $error_data['provider_requests'] )
					: 1;
				$this->last_attempt_usage = array(
					'provider_requests' => $requests,
					'input_tokens'      => max( 0, (int) ( $error_data['input_tokens'] ?? 0 ) ),
					'output_tokens'     => max( 0, (int) ( $error_data['output_tokens'] ?? 0 ) ),
					'usage_known'       => true,
					'tm_outcome_code'   => null !== $this->last_tm_outcome ? $this->last_tm_outcome->code : '',
				);
			}
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
		$this->last_attempt_usage = array(
			'provider_requests' => 1,
			'input_tokens'      => max( 0, $result->input_tokens ),
			'output_tokens'     => max( 0, $result->output_tokens ),
			'usage_known'       => true,
			'tm_outcome_code'   => null !== $this->last_tm_outcome ? $this->last_tm_outcome->code : '',
		);

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
	 * Translates one native term segment (TSC.1 Jobs path).
	 *
	 * @param int    $term_id        Term id.
	 * @param string $taxonomy       Taxonomy slug.
	 * @param int    $language_id    Target language id.
	 * @param string $segment_key    Native segment key (name|description|rankmath key).
	 * @param bool   $allow_provider Whether provider calls are allowed.
	 * @return array<string, mixed>|WP_Error
	 */
	public function translate_term_segment(
		int $term_id,
		string $taxonomy,
		int $language_id,
		string $segment_key,
		bool $allow_provider = true
	) {
		$this->last_tm_outcome           = null;
		$this->last_attempt_usage        = null;
		$this->last_scaffolding_markers  = array();
		$this->expected_translation_hash = null;

		if ( null === $this->term_adoption ) {
			return new WP_Error(
				'aiml_term_adoption_unavailable',
				__( 'Term adoption is not available.', 'ai-multilingual' ),
				array( 'status' => 500 )
			);
		}

		$ensure = $this->term_adoption->ensure_native_before_content_write(
			$term_id,
			$taxonomy,
			$language_id,
			$segment_key
		);
		if ( $ensure instanceof WP_Error ) {
			return $ensure;
		}

		$extractor = new \AIMultilingual\Translation\TermExtractor();
		$segments  = $extractor->extract( $term_id );
		$unit      = $segments[ $segment_key ] ?? null;
		if ( ! is_array( $unit ) ) {
			return new WP_Error(
				'aiml_invalid_segment',
				__( 'Unknown segment key for this term.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		$current = array_merge(
			$unit,
			array(
				'segment_key'      => $segment_key,
				'can_edit'         => true,
				'translated_text'  => '',
				'translation_hash' => '',
				'status'           => Store::STATUS_MISSING,
				'is_stale'         => false,
			)
		);

		$row = $this->store->get( Store::SOURCE_TERM, $term_id, $language_id, $segment_key );
		if ( null !== $row ) {
			$current['translated_text']  = (string) ( $row->translated_text ?? '' );
			$current['translation_hash'] = (string) ( $row->translation_hash ?? '' );
			$current['status']           = (string) ( $row->status ?? Store::STATUS_MISSING );
			$current['is_stale']         = (bool) ( (int) ( $row->is_stale ?? 0 ) );
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

		if ( ! $allow_provider ) {
			return new WP_Error(
				'aiml_provider_disabled',
				__( 'Provider calls are not allowed in this wake.', 'ai-multilingual' ),
				array( 'status' => 409 )
			);
		}

		$meta_provider_block = $this->registered_meta_provider_block( Store::SOURCE_TERM, $segment_key );
		if ( $meta_provider_block instanceof WP_Error ) {
			return $meta_provider_block;
		}

		$translated = $this->request_provider_translation(
			$source_text,
			$format,
			(string) $source->locale,
			(string) $target->locale,
			$segment_key
		);
		if ( $translated instanceof WP_Error ) {
			return $translated;
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
				array( 'status' => 422 )
			);
		}

		$save = $this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_TERM,
				'source_id'       => $term_id,
				'source_subtype'  => $taxonomy,
				'language_id'     => $language_id,
				'field_key'       => (string) ( $current['field_key'] ?? $segment_key ),
				'segment_key'     => $segment_key,
				'segment_kind'    => (string) ( $current['segment_kind'] ?? Store::KIND_FIELD ),
				'segment_order'   => (int) ( $current['segment_order'] ?? 0 ),
				'text_format'     => $format,
				'source_text'     => $source_text,
				'translated_text' => $translated,
				'status'          => Store::STATUS_MACHINE_TRANSLATED,
			)
		);

		if ( $save instanceof WP_Error ) {
			return $save;
		}

		if ( null !== $this->publication ) {
			$this->publication->maybe_auto_publish(
				Store::SOURCE_TERM,
				$term_id,
				$language_id,
				$segment_key
			);
		}

		$row = $this->store->get( Store::SOURCE_TERM, $term_id, $language_id, $segment_key );

		return array_merge(
			$current,
			array(
				'translated_text'  => null !== $row ? (string) ( $row->translated_text ?? $translated ) : $translated,
				'translation_hash' => null !== $row ? (string) ( $row->translation_hash ?? '' ) : '',
				'status'           => null !== $row ? (string) ( $row->status ?? Store::STATUS_MACHINE_TRANSLATED ) : Store::STATUS_MACHINE_TRANSLATED,
			)
		);
	}

	/**
	 * Requests a single-segment provider translation.
	 *
	 * @param string $source_text Source text.
	 * @param string $format      Text format.
	 * @param string $source_locale Source locale.
	 * @param string $target_locale Target locale.
	 * @param string $segment_key Segment key.
	 * @return string|WP_Error
	 */
	private function request_provider_translation(
		string $source_text,
		string $format,
		string $source_locale,
		string $target_locale,
		string $segment_key
	) {
		$batch = new TranslationBatch(
			$source_locale,
			$target_locale,
			PromptProfileRegistry::TRANSLATE,
			PromptProfileRegistry::VERSION,
			'',
			array(
				new ProviderSegment(
					$segment_key,
					$source_text,
					$format
				),
			),
			TranslationBatch::OPERATION_TRANSLATE
		);

		$result = $this->provider->translate_batch( $batch );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$this->last_attempt_usage = array(
			'provider_requests' => 1,
			'input_tokens'      => max( 0, $result->input_tokens ),
			'output_tokens'     => max( 0, $result->output_tokens ),
			'usage_known'       => true,
			'tm_outcome_code'   => '',
		);

		foreach ( $result->segments as $segment ) {
			if ( (string) ( $segment['segment_key'] ?? '' ) !== $segment_key ) {
				continue;
			}
			$translated = (string) ( $segment['translated_text'] ?? '' );
			if ( '' === trim( $translated ) ) {
				return new WP_Error(
					'aiml_empty_translation',
					__( 'Provider returned an empty translation.', 'ai-multilingual' ),
					array( 'status' => 422 )
				);
			}

			return $translated;
		}

		return new WP_Error(
			'aiml_ai_invalid_response',
			__( 'Provider response is missing the requested segment.', 'ai-multilingual' ),
			array( 'status' => 422 )
		);
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

		// Adoption runs before the concurrency guard: the guard must compare
		// against the row this persist is about to write, and for a term field
		// that row is the native one.
		$identity = $this->term_write_identity( (int) $post->ID, $language_id, $segment_key );
		if ( $identity instanceof WP_Error ) {
			return $identity;
		}

		$guard = $this->guard_expected_translation_hash(
			(string) ( $identity['source_type'] ?? Store::SOURCE_POST ),
			(int) ( $identity['source_id'] ?? $post->ID ),
			$language_id,
			(string) ( $identity['segment_key'] ?? $segment_key )
		);
		if ( null !== $guard ) {
			return $guard;
		}

		$save = $this->store->save_translation(
			array_merge(
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
				),
				$identity
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

		// TI.7: auto-publication is best-effort — never convert into translation failure.
		if ( null !== $this->publication ) {
			$markers            = $this->last_scaffolding_markers;
			$publication_result = $this->publication->maybe_auto_publish(
				(string) ( $identity['source_type'] ?? Store::SOURCE_POST ),
				(int) ( $identity['source_id'] ?? $post->ID ),
				$language_id,
				(string) ( $identity['segment_key'] ?? $segment_key ),
				$markers,
				array() !== $markers
			);
			if ( is_array( $publication_result ) ) {
				$refreshed['publication_result'] = $publication_result;
				$after                           = $this->assembler->assemble_one( $post, $language_id, $segment_key );
				if ( null !== $after ) {
					$after['publication_result'] = $publication_result;
					$refreshed                   = $after;
				}
			}
		}

		return $refreshed;
	}

	/**
	 * Identity a provider persist must target when the segment holds a term field.
	 *
	 * @param int    $post_id     Hosting post id.
	 * @param int    $language_id Language id.
	 * @param string $segment_key Segment key.
	 * @return array<string, mixed>|WP_Error
	 */
	private function term_write_identity( int $post_id, int $language_id, string $segment_key ) {
		if ( null === $this->term_adoption ) {
			return array();
		}

		return $this->term_adoption->native_write_identity( Store::SOURCE_POST, $post_id, $language_id, $segment_key );
	}

	/**
	 * Re-reads Store translation_hash immediately before persist (OTL.3 race window).
	 *
	 * @param string $source_type Authoritative source type.
	 * @param int    $source_id   Authoritative source id.
	 * @param int    $language_id Target language id.
	 * @param string $segment_key Segment key.
	 */
	private function guard_expected_translation_hash( string $source_type, int $source_id, int $language_id, string $segment_key ): ?WP_Error {
		if ( null === $this->expected_translation_hash ) {
			return null;
		}

		$row          = $this->store->get( $source_type, $source_id, $language_id, $segment_key );
		$current_hash = null === $row ? '' : (string) ( $row->translation_hash ?? '' );
		if ( $current_hash !== $this->expected_translation_hash ) {
			return new WP_Error(
				'aiml_translation_hash_mismatch',
				__( 'The translation changed since this request was started.', 'ai-multilingual' ),
				array( 'status' => 409 )
			);
		}

		return null;
	}

	/**
	 * Block AI provider calls for registered-meta segments that are not provider-admitted (TSC.2).
	 *
	 * @param string $source_type Source type.
	 * @param string $segment_key Segment key.
	 * @return WP_Error|null
	 */
	private function registered_meta_provider_block( string $source_type, string $segment_key ): ?WP_Error {
		if ( null === $this->meta_registry ) {
			return null;
		}
		$fact = $this->meta_registry->provider_allowed_for_segment( $source_type, $segment_key );
		if ( false !== $fact ) {
			return null;
		}
		$this->last_attempt_usage = array(
			'provider_requests' => 0,
			'input_tokens'      => 0,
			'output_tokens'     => 0,
			'usage_known'       => true,
			'tm_outcome_code'   => null !== $this->last_tm_outcome ? $this->last_tm_outcome->code : '',
		);
		return new WP_Error(
			'aiml_registered_meta_provider_denied',
			__( 'This registered meta segment is not admitted for AI provider generation.', 'ai-multilingual' ),
			array(
				'status'                => 422,
				'provider_request_made' => false,
				'provider_requests'     => 0,
			)
		);
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
			$this->last_scaffolding_markers = array();
			return $batch;
		}

		$markers = $this->provider->scaffolding_markers_for_batch( $batch );
		if ( array() === $markers ) {
			$this->last_scaffolding_markers = array();
			return $batch;
		}

		$this->last_scaffolding_markers = array_values( array_map( 'strval', $markers ) );

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
			$this->last_scaffolding_markers
		);
	}
}
