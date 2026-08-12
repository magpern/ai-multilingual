<?php
/**
 * Strategy F block render gate request context.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Settings;
use AIMultilingual\Surface\AdmittedPostTypes;
use WP_Post;

/**
 * Immutable request facts used by {@see BlockRenderGate}.
 */
final class RenderGateContext {

	/**
	 * Post types eligible for Strategy F frontend block rendering.
	 *
	 * @var list<string>
	 */
	public const SUPPORTED_POST_TYPES = AdmittedPostTypes::FRONTEND_OVERLAY_TYPES;

	/**
	 * Builds a render gate context.
	 *
	 * @param Settings        $settings         Plugin settings.
	 * @param LanguageContext $language         Request language state.
	 * @param Extractor       $extractor        Body classifier.
	 * @param WP_Post|null    $post             Source post.
	 * @param string          $content          Candidate post content string.
	 * @param bool            $rendering_active Whether an overlay is already active.
	 * @param bool            $is_admin         Admin request.
	 * @param bool            $is_rest          REST API request.
	 * @param bool            $is_ajax          AJAX request.
	 * @param bool            $is_cron          Cron request.
	 * @param bool            $is_feed          Feed request.
	 * @param bool            $is_preview       Preview request.
	 * @param bool            $is_block_editor  Block editor request.
	 */
	public function __construct(
		public Settings $settings,
		public LanguageContext $language,
		public Extractor $extractor,
		public ?WP_Post $post,
		public string $content,
		public bool $rendering_active = false,
		public bool $is_admin = false,
		public bool $is_rest = false,
		public bool $is_ajax = false,
		public bool $is_cron = false,
		public bool $is_feed = false,
		public bool $is_preview = false,
		public bool $is_block_editor = false,
	) {
	}

	/**
	 * Builds context from the current WordPress request.
	 *
	 * @param Settings        $settings         Plugin settings.
	 * @param LanguageContext $language         Request language state.
	 * @param Extractor       $extractor        Body classifier.
	 * @param WP_Post|null    $post             Source post.
	 * @param string          $content          Candidate post content string.
	 * @param bool            $rendering_active Whether an overlay is already active.
	 */
	public static function from_request(
		Settings $settings,
		LanguageContext $language,
		Extractor $extractor,
		?WP_Post $post,
		string $content,
		bool $rendering_active = false,
	): self {
		$is_block_editor = false;
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( null !== $screen && method_exists( $screen, 'is_block_editor' ) ) {
				$is_block_editor = (bool) $screen->is_block_editor();
			}
		}

		return new self(
			$settings,
			$language,
			$extractor,
			$post,
			$content,
			$rendering_active,
			is_admin(),
			defined( 'REST_REQUEST' ) && REST_REQUEST,
			defined( 'DOING_AJAX' ) && DOING_AJAX,
			defined( 'DOING_CRON' ) && DOING_CRON,
			function_exists( 'is_feed' ) && is_feed(),
			function_exists( 'is_preview' ) && is_preview(),
			$is_block_editor,
		);
	}
}
