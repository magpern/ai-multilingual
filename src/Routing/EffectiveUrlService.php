<?php
/**
 * Effective URL authority (inert foundation in MSEO.0).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Settings;

/**
 * Future sole effective URL authority (ADR-0023 §16).
 *
 * MSEO.0: inert passthrough — returns source paths unchanged when generation
 * is disabled (default `localized_urls_state=off`).
 */
final class EffectiveUrlService {

	/** Plugin settings accessor.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Builds the inert effective URL service.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Returns the unprefixed effective path for a source path and language.
	 *
	 * MSEO.0: always returns the source path unchanged.
	 *
	 * @param string $source_path Unprefixed source/canonical path.
	 * @param int    $language_id Target language id (unused in MSEO.0 passthrough).
	 */
	public function unprefixed_effective_path( string $source_path, int $language_id ): string {
		unset( $language_id );

		if ( ! $this->settings->is_localized_url_generation_enabled() ) {
			return $source_path;
		}

		// MSEO.2+ will localize here when state=on and route exists.
		return $source_path;
	}
}
