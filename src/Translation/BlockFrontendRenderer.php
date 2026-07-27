<?php
/**
 * Strategy F gated frontend block rendering orchestrator.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Settings;
use WP_Post;

/**
 * Request-context gate → Store lookup → sanitizer → pure {@see BlockRenderer}.
 */
final class BlockFrontendRenderer {

	/**
	 * Guards against nested the_content re-entry during block rendering.
	 *
	 * @var bool
	 */
	private bool $rendering = false;

	/**
	 * Builds the frontend block renderer.
	 *
	 * @param BlockRenderGate           $gate         Render gate.
	 * @param BlockTranslationLookup    $lookup       Store-backed lookup.
	 * @param BlockTranslationSanitizer $sanitizer    Translation sanitizer.
	 * @param BlockRenderer             $block_renderer Pure block renderer.
	 * @param BlockFrontendRenderLogger $logger       Structured logger.
	 * @param Settings                  $settings     Plugin settings.
	 * @param LanguageContext           $language     Request language state.
	 * @param Extractor                 $extractor    Body classifier.
	 */
	public function __construct(
		private BlockRenderGate $gate,
		private BlockTranslationLookup $lookup,
		private BlockTranslationSanitizer $sanitizer,
		private BlockRenderer $block_renderer,
		private BlockFrontendRenderLogger $logger,
		private Settings $settings,
		private LanguageContext $language,
		private Extractor $extractor,
	) {
	}

	/**
	 * Attempts gated frontend block rendering for one post body.
	 *
	 * Returns source content when denied or on any failure. Never mutates the
	 * stored post or throws to callers.
	 *
	 * @param WP_Post $post             Source post.
	 * @param string  $content          Canonical post_content string.
	 */
	public function render( WP_Post $post, string $content ): string {
		if ( $this->rendering ) {
			return $content;
		}

		$this->rendering = true;

		try {
			return $this->render_inner( $post, $content );
		} finally {
			$this->rendering = false;
		}
	}

	/**
	 * Renders block translations when the recursion guard is not active.
	 *
	 * @param WP_Post $post    Source post.
	 * @param string  $content Canonical post_content string.
	 */
	private function render_inner( WP_Post $post, string $content ): string {
		$context  = RenderGateContext::from_request(
			$this->settings,
			$this->language,
			$this->extractor,
			$post,
			$content,
			false
		);
		$decision = $this->gate->evaluate( $context );

		$meta = $this->base_meta( $post );

		if ( ! $decision->allowed ) {
			$this->logger->log(
				BlockFrontendRenderLogger::EVENT_GATE_DENIED,
				array_merge(
					$meta,
					array(
						'denial_reason' => $decision->reason,
					)
				)
			);

			return $content;
		}

		$this->logger->log(
			BlockFrontendRenderLogger::EVENT_GATE_ALLOWED,
			$meta
		);

		$lookup = $this->lookup->for_post(
			Store::SOURCE_POST,
			(int) $post->ID,
			$this->language->current_id()
		);

		if ( ! $lookup->successful ) {
			$this->logger->log(
				BlockFrontendRenderLogger::EVENT_LOOKUP_FAILED,
				array_merge(
					$meta,
					array(
						'failure_reason' => $lookup->failure_reason,
						'segment_count'  => $lookup->segment_count,
					)
				)
			);

			return $content;
		}

		$this->logger->log(
			BlockFrontendRenderLogger::EVENT_LOOKUP_COMPLETE,
			array_merge(
				$meta,
				array(
					'segment_count'    => $lookup->segment_count,
					'translated_count' => $lookup->translated_count,
					'fallback_count'   => $lookup->rejected_count,
				)
			)
		);

		if ( array() === $lookup->translations ) {
			return $content;
		}

		$sanitized = $this->sanitizer->sanitize_map(
			$lookup->translations,
			function ( string $event, array $reject_context ) use ( $meta ): void {
				$this->logger->log( $event, array_merge( $meta, $reject_context ) );
			}
		);

		if ( array() === $sanitized ) {
			return $content;
		}

		$result = $this->block_renderer->render_content( $content, $sanitized );

		if ( ! $result->changed || '' === $result->content ) {
			$this->logger->log(
				BlockFrontendRenderLogger::EVENT_RENDER_FAILED,
				array_merge(
					$meta,
					array(
						'failure_reason' => 'renderer_no_change',
					)
				)
			);

			return $content;
		}

		$this->logger->log(
			BlockFrontendRenderLogger::EVENT_RENDER_COMPLETE,
			array_merge(
				$meta,
				array(
					'translated_count' => count( $sanitized ),
				)
			)
		);

		return $result->content;
	}

	/**
	 * Shared log metadata for one post.
	 *
	 * @param WP_Post $post Source post.
	 * @return array<string, mixed>
	 */
	private function base_meta( WP_Post $post ): array {
		return array(
			'post_id'         => (int) $post->ID,
			'post_type'       => (string) $post->post_type,
			'target_language' => $this->language->current_id(),
		);
	}
}
