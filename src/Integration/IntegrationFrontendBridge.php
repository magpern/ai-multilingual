<?php
/**
 * Visitor overlay bridge for Integration API v1.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Store;

/**
 * Registers integration output hooks when a non-default language is active.
 */
final class IntegrationFrontendBridge {

	/**
	 * Builds the frontend overlay bridge.
	 *
	 * @param Settings               $settings    Settings.
	 * @param LanguageContext        $context     Language context.
	 * @param IntegrationRegistry    $registry    Registry.
	 * @param Store                  $store       Store.
	 * @param IntegrationDiagnostics $diagnostics Diagnostics.
	 */
	public function __construct(
		private Settings $settings,
		private LanguageContext $context,
		private IntegrationRegistry $registry,
		private Store $store,
		private IntegrationDiagnostics $diagnostics,
	) {
	}

	/**
	 * Register the frontend bridge.
	 */
	public function register(): void {
		add_action( 'wp', array( $this, 'on_wp' ), 20 );
	}

	/**
	 * Attach overlay hooks once the main query is available.
	 */
	public function on_wp(): void {
		if ( is_admin() || $this->context->is_default() ) {
			return;
		}

		$language = $this->context->current();
		if ( null === $language ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$language_id = (int) $language->language_id;
		$source_id   = (int) $post->ID;

		$resolve = function ( string $segment_key ) use ( $source_id, $language_id ): ?string {
			$row = $this->store->get( 'post', $source_id, $language_id, $segment_key );
			if ( null === $row ) {
				$this->diagnostics->increment( IntegrationDiagnostics::COUNTER_SOURCE_FALLBACK );
				return null;
			}
			$text = (string) ( $row->translated_text ?? '' );
			if ( '' === $text ) {
				$this->diagnostics->increment( IntegrationDiagnostics::COUNTER_SOURCE_FALLBACK );
				return null;
			}
			$this->diagnostics->increment( IntegrationDiagnostics::COUNTER_OVERLAY_APPLIED );
			return $text;
		};

		$this->registry->register_output_hooks( $resolve );
	}
}
