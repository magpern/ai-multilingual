<?php
/**
 * Production preview URL construction.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\Languages;
use AIMultilingual\Routing\Router;
use WP_Error;
use WP_Post;

/**
 * Returns public routed frontend URLs only — never assembles preview HTML in admin.
 */
final class PreviewService {

	/**
	 * Injected dependency.
	 *
	 * @var Languages
	 */
	private Languages $languages;

	/**
	 * Injected dependency.
	 *
	 * @var LanguageContext
	 */
	private LanguageContext $context;

	/**
	 * Injected dependency.
	 *
	 * @var Router
	 */
	private Router $router;

	/**
	 * Builds the collaborator.
	 *
	 * @param Languages       $languages Language registry.
	 * @param LanguageContext $context   Request language context.
	 * @param Router          $router    Prefix routing helper.
	 */
	public function __construct( Languages $languages, LanguageContext $context, Router $router ) {
		$this->languages = $languages;
		$this->context   = $context;
		$this->router    = $router;
	}

	/**
	 * Builds the public preview URL for a post in a target language.
	 *
	 * @param WP_Post $post        Canonical post.
	 * @param string  $language_code Target language code.
	 * @return string|WP_Error
	 */
	public function preview_url( WP_Post $post, string $language_code ) {
		$language = $this->languages->find_by_code( $language_code );
		if ( null === $language ) {
			return new WP_Error(
				'aiml_invalid_language',
				__( 'Unknown language code.', 'ai-multilingual' ),
				array( 'status' => 404 )
			);
		}

		$permalink = get_permalink( $post );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return new WP_Error(
				'aiml_preview_unavailable',
				__( 'Preview URL is unavailable for this post.', 'ai-multilingual' ),
				array( 'status' => 404 )
			);
		}

		if ( ! empty( $language->is_default ) ) {
			return $permalink;
		}

		// Preview forever uses source-slug URLs (ADR-0023 / M2AC28) — prefix only.
		return (string) $this->context->with(
			$language,
			function () use ( $permalink ): string {
				return $this->router->prefix_url_without_localization( $permalink );
			}
		);
	}
}
