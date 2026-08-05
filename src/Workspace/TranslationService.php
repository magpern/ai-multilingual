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
use AIMultilingual\Translation\AI\NullAIProvider;
use AIMultilingual\Translation\AI\PromptProfileRegistry;
use AIMultilingual\Translation\AI\ProviderResult;
use AIMultilingual\Translation\AI\ProviderSegment;
use AIMultilingual\Translation\AI\ResponseValidator;
use AIMultilingual\Translation\AI\SegmentConstraintAnalyzer;
use AIMultilingual\Translation\AI\TranslationBatch;
use AIMultilingual\Translation\Store;
use WP_Error;
use WP_Post;

/**
 * Single entry point for workspace auto-translate.
 */
final class TranslationService {

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
	 * Builds the collaborator.
	 *
	 * @param Store                      $store     Segment store.
	 * @param SegmentAssembler           $assembler Segment assembler.
	 * @param Languages                  $languages Language registry.
	 * @param AIProviderInterface        $provider  AI provider boundary.
	 * @param PromptProfileRegistry|null $profiles Prompt profiles.
	 * @param ResponseValidator|null     $validator Response validator.
	 * @param GlossaryService|null       $glossary  Glossary service for fragments.
	 */
	public function __construct(
		Store $store,
		SegmentAssembler $assembler,
		Languages $languages,
		AIProviderInterface $provider,
		?PromptProfileRegistry $profiles = null,
		?ResponseValidator $validator = null,
		?GlossaryService $glossary = null
	) {
		$this->store     = $store;
		$this->assembler = $assembler;
		$this->languages = $languages;
		$this->provider  = $provider;
		$this->profiles  = $profiles ?? new PromptProfileRegistry();
		$this->validator = $validator ?? new ResponseValidator( new SegmentConstraintAnalyzer() );
		$this->glossary  = $glossary;
	}

	/**
	 * Returns the configured provider for tests and future wiring.
	 */
	public function provider(): AIProviderInterface {
		return $this->provider;
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

		$batch = new TranslationBatch(
			(string) $source->locale,
			(string) $target->locale,
			PromptProfileRegistry::TRANSLATE,
			PromptProfileRegistry::VERSION,
			$this->glossary_fragment_for( $current, (int) $source->language_id, $language_id ),
			array(
				new ProviderSegment(
					$segment_key,
					(string) ( $current['source_text'] ?? '' ),
					(string) ( $current['text_format'] ?? Store::FORMAT_PLAIN )
				),
			),
			TranslationBatch::OPERATION_TRANSLATE
		);

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
			$profile->constraints
		);

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
		$translated  = '';

		foreach ( $result->segments as $segment ) {
			if ( (string) ( $segment['segment_key'] ?? '' ) === $segment_key ) {
				$translated = (string) ( $segment['translated_text'] ?? '' );
				break;
			}
		}

		if ( '' === $translated ) {
			return new WP_Error(
				NullAIProvider::ERROR_CODE,
				__( 'Automatic translation is not configured.', 'ai-multilingual' ),
				array( 'status' => 503 )
			);
		}

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
				'text_format'     => (string) ( $current['text_format'] ?? Store::FORMAT_PLAIN ),
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
}
