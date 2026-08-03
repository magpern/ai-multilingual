<?php
/**
 * Auto-translate boundary for the translator workspace.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

use AIMultilingual\Language\Languages;
use AIMultilingual\Translation\AI\AIProviderInterface;
use AIMultilingual\Translation\AI\NullAIProvider;
use AIMultilingual\Translation\AI\ProviderResult;
use AIMultilingual\Translation\AI\ProviderSegment;
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
	 * Builds the collaborator.
	 *
	 * @param Store               $store     Segment store.
	 * @param SegmentAssembler    $assembler Segment assembler.
	 * @param Languages           $languages Language registry.
	 * @param AIProviderInterface $provider  AI provider boundary.
	 */
	public function __construct(
		Store $store,
		SegmentAssembler $assembler,
		Languages $languages,
		AIProviderInterface $provider
	) {
		$this->store     = $store;
		$this->assembler = $assembler;
		$this->languages = $languages;
		$this->provider  = $provider;
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
			'workspace',
			'1',
			'',
			array(
				new ProviderSegment(
					$segment_key,
					(string) ( $current['source_text'] ?? '' ),
					(string) ( $current['text_format'] ?? Store::FORMAT_PLAIN )
				),
			)
		);

		$result = $this->provider->translate_batch( $batch );
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		return $this->persist_provider_result( $post, $language_id, $current, $result );
	}

	/**
	 * Persists provider output through the Store write path.
	 *
	 * @param WP_Post                    $post        Canonical post.
	 * @param int                        $language_id Target language id.
	 * @param array<string, mixed>       $current     Current segment DTO.
	 * @param ProviderResult             $result      Provider outcome.
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
}
